<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
 
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
 
require_once '../conexion/db.php';

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    echo json_encode(['success' => false, 'message' => 'Falta el id de la operación a editar']);
    exit;
}

$fecha_servicio = $_POST['fecha_servicio'] ?? '';
$hora_servicio  = $_POST['hora_servicio'] ?? '';
$cliente        = trim($_POST['cliente'] ?? '');
$conductor      = trim($_POST['conductor'] ?? '');
$telefono       = trim($_POST['telefono'] ?? '');
$placa          = trim($_POST['placa'] ?? '');
$furgon         = trim($_POST['furgon'] ?? '');
$origen         = trim($_POST['origen'] ?? '');
$destino        = trim($_POST['destino'] ?? '');
$tipo_servicio  = $_POST['tipo_servicio'] ?? '';
$patrullero_id  = $_POST['patrullero_id'] ?? '';
$estado         = $_POST['estado'] ?? 'pendiente';
$custodios_id   = $_POST['custodios_id'] ?? [];
$custodios_tipo = $_POST['custodios_tipo'] ?? [];
$custodios_gps  = $_POST['custodios_gps_id'] ?? [];
$observaciones  = trim($_POST['observaciones'] ?? '');

// Tipos de servicio que solo requieren GPS (sin custodio/persona)
$tiposSoloGps = ['Ruta Segura', 'Marchamo Electrónico', 'GPS'];
// Tipo combinado: requiere custodio + GPS juntos, en la misma fila
$tipoCustodioGps = 'Custodio + GPS';

// Armar cada fila con su custodio (nullable), su GPS (nullable) y su tipo
$filas = [];
foreach ($custodios_tipo as $idx => $tipoFila) {
    $custodioId = $custodios_id[$idx] ?? '';
    $gpsId      = $custodios_gps[$idx] ?? '';
    $custodioId = ($custodioId === '' || $custodioId === null) ? null : $custodioId;
    $gpsId      = ($gpsId === '' || $gpsId === null) ? null : $gpsId;

    if ($custodioId === null && $gpsId === null) continue; // fila vacía, se descarta

    if (in_array($tipoFila, $tiposSoloGps, true)) {
        if ($gpsId === null) {
            echo json_encode(['success' => false, 'message' => "Selecciona un GPS para la fila de tipo \"$tipoFila\""]);
            exit;
        }
        $custodioId = null; // este tipo no lleva custodio
    } elseif ($tipoFila === $tipoCustodioGps) {
        if ($custodioId === null || $gpsId === null) {
            echo json_encode(['success' => false, 'message' => 'El servicio "Custodio + GPS" requiere un custodio y un GPS juntos']);
            exit;
        }
    } else {
        if ($custodioId === null) {
            echo json_encode(['success' => false, 'message' => "Selecciona un custodio para la fila de tipo \"$tipoFila\""]);
            exit;
        }
        $gpsId = null; // estos tipos no llevan GPS
    }

    $filas[] = ['custodio_id' => $custodioId, 'gps_id' => $gpsId, 'tipo_servicio' => $tipoFila];
}
 
$patrullero_id = $patrullero_id === '' ? null : $patrullero_id;
$conductor = $conductor === '' ? null : $conductor;
$telefono  = $telefono === ''  ? null : $telefono;
$placa     = $placa === ''     ? null : $placa;
$furgon    = $furgon === ''    ? null : $furgon;

$estadosValidos = ['pendiente', 'en_ruta', 'finalizado', 'cancelado'];
if (!in_array($estado, $estadosValidos, true)) {
    $estado = 'pendiente';
}
 
if ($fecha_servicio === '' || $hora_servicio === '' || $cliente === '' || $origen === '' || $destino === '' || $tipo_servicio === '') {
    echo json_encode(['success' => false, 'message' => 'Completa fecha, hora, cliente, origen, destino y tipo de servicio']);
    exit;
}

if (count($filas) === 0) {
    echo json_encode(['success' => false, 'message' => 'Agrega al menos un custodio o GPS a la operación']);
    exit;
}
 
try {
    $conn->beginTransaction();

    // Validar que el piloto no tenga otra operación dentro de ±1 hora
    // con un origen diferente (no puede estar en dos lugares distintos a la vez)
    if ($patrullero_id !== null) {
        $stmtConflicto = $conn->prepare("
            SELECT origen, hora_servicio FROM operaciones
            WHERE patrullero_id = ?
              AND fecha_servicio = ?
              AND estado != 'cancelado'
              AND id != ?
        ");
        $stmtConflicto->execute([$patrullero_id, $fecha_servicio, $id]);
        $existentes = $stmtConflicto->fetchAll(PDO::FETCH_ASSOC);

        $horaNueva = strtotime($hora_servicio);
        foreach ($existentes as $fila) {
            $horaExistente = strtotime($fila['hora_servicio']);
            $diffMinutos = abs($horaNueva - $horaExistente) / 60;

            if ($diffMinutos <= 60 && strcasecmp(trim($fila['origen']), $origen) !== 0) {
                $conn->rollBack();
                $horaFmt = date('H:i', $horaExistente);
                echo json_encode(['success' => false, 'message' => "Este piloto ya tiene otra operación el $fecha_servicio a las $horaFmt con origen distinto ({$fila['origen']}), a menos de 1 hora de diferencia. No puede estar en dos lugares a la vez."]);
                exit;
            }
        }
    }

    // Verificar que la operación exista antes de actualizarla
    $stmtCheck = $conn->prepare("SELECT id FROM operaciones WHERE id = ?");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetch()) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'La operación no existe']);
        exit;
    }

    // El GPS ahora se guarda por fila en operaciones_custodios; el campo
    // operaciones.gps_id se deja en null (se mantiene solo por compatibilidad).
    $stmt = $conn->prepare("
        UPDATE operaciones
        SET fecha_servicio = ?, hora_servicio = ?, cliente = ?, conductor = ?, telefono = ?,
            placa = ?, furgon = ?, origen = ?, destino = ?,
            tipo_servicio = ?, patrullero_id = ?, gps_id = NULL, observaciones = ?, estado = ?
        WHERE id = ?
    ");
    $stmt->execute([$fecha_servicio, $hora_servicio, $cliente, $conductor, $telefono, $placa, $furgon,
                     $origen, $destino, $tipo_servicio, $patrullero_id, $observaciones, $estado, $id]);

    $operacionId = $id;

    // Reemplazar por completo los custodios/servicios de esta operación
    $stmtDelete = $conn->prepare("DELETE FROM operaciones_custodios WHERE operacion_id = ?");
    $stmtDelete->execute([$operacionId]);

    $stmtFila = $conn->prepare("INSERT INTO operaciones_custodios (operacion_id, custodio_id, gps_id, rol) VALUES (?, ?, ?, ?)");
    foreach ($filas as $f) {
        $stmtFila->execute([$operacionId, $f['custodio_id'], $f['gps_id'], $f['tipo_servicio']]);
    }
 
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Operación actualizada correctamente']);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error guardando operación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar la operación']);
}