<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$esAdmin  = isset($_SESSION['usuario_id']);
$esPiloto = isset($_SESSION['patrullero_id']);

if (!$esAdmin && !$esPiloto) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$kilometraje   = $_POST['kilometraje'] ?? '';
$fecha         = $_POST['fecha'] ?? date('Y-m-d');
$observaciones = trim($_POST['observaciones'] ?? '');

if ($esPiloto) {
    // Seguridad: como ya no hay selector de vehículo en la app del piloto, NUNCA
    // confiamos en un vehiculo_id que venga del formulario. Se obtiene el vehículo
    // que el admin le asignó, directo de la base.
    $stmtAsignado = $conn->prepare("SELECT id, km_limite_aceite FROM vehiculos WHERE patrullero_id = ? LIMIT 1");
    $stmtAsignado->execute([$_SESSION['patrullero_id']]);
    $vehiculoAsignado = $stmtAsignado->fetch(PDO::FETCH_ASSOC);

    if (!$vehiculoAsignado) {
        echo json_encode(['success' => false, 'message' => 'No tienes un vehículo asignado. Contacta a un administrador.']);
        exit;
    }

    $vehiculoId   = $vehiculoAsignado['id'];
    $patrulleroId = $_SESSION['patrullero_id'];
} else {
    // Un admin sí puede indicar vehículo y, opcionalmente, a nombre de qué piloto
    // quedó la lectura (por si reporta manualmente por él).
    $vehiculoId   = $_POST['vehiculo_id'] ?? '';
    $patrulleroId = $_POST['patrullero_id'] ?? null;
    if ($patrulleroId === '') $patrulleroId = null;

    if ($vehiculoId === '' || !ctype_digit((string)$vehiculoId)) {
        echo json_encode(['success' => false, 'message' => 'Selecciona un vehículo']);
        exit;
    }

    // Si el admin no especificó a nombre de qué piloto quedó la lectura (que es
    // el caso normal, ya que el formulario del panel no manda patrullero_id),
    // se usa automáticamente el piloto que tiene asignado el vehículo. Así el
    // historial y el reporte diario de kilometraje muestran el nombre correcto.
    if ($patrulleroId === null) {
        $stmtPiloto = $conn->prepare("SELECT patrullero_id FROM vehiculos WHERE id = ?");
        $stmtPiloto->execute([$vehiculoId]);
        $patrulleroAsignado = $stmtPiloto->fetchColumn();
        if ($patrulleroAsignado) {
            $patrulleroId = $patrulleroAsignado;
        }
    }
}

if ($kilometraje === '' || !ctype_digit((string)$kilometraje)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa el kilometraje (solo números)']);
    exit;
}

try {
    // El odómetro no puede retroceder: validamos contra la última lectura de ese vehículo
    $stmtUltimo = $conn->prepare("SELECT kilometraje FROM vehiculo_kilometrajes WHERE vehiculo_id = ? ORDER BY creado_en DESC LIMIT 1");
    $stmtUltimo->execute([$vehiculoId]);
    $ultimo = $stmtUltimo->fetchColumn();

    if ($ultimo !== false && (int)$kilometraje < (int)$ultimo) {
        echo json_encode(['success' => false, 'message' => "El kilometraje no puede ser menor al último registrado ($ultimo km)"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO vehiculo_kilometrajes (vehiculo_id, patrullero_id, kilometraje, fecha, observaciones)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$vehiculoId, $patrulleroId, $kilometraje, $fecha, $observaciones !== '' ? $observaciones : null]);

    // ---- Cálculo de alerta de cambio de aceite ----
    // Nivel 1 (aviso):    km actual >= límite
    // Nivel 2 (urgente):  km actual >= límite + 1000
    // Nivel 3 (crítico):  km actual >= límite + 1500
    $stmtLimite = $conn->prepare("SELECT km_limite_aceite FROM vehiculos WHERE id = ?");
    $stmtLimite->execute([$vehiculoId]);
    $kmLimite = $stmtLimite->fetchColumn();

    $alerta = null;
    if ($kmLimite !== false && $kmLimite !== null) {
        $diferencia = (int)$kilometraje - (int)$kmLimite;
        if ($diferencia >= 1500) {
            $alerta = ['nivel' => 3, 'tipo' => 'critico', 'mensaje' => "Crítico: el vehículo lleva $diferencia km pasado el límite de cambio de aceite. Debe ir a mantenimiento de inmediato."];
        } elseif ($diferencia >= 1000) {
            $alerta = ['nivel' => 2, 'tipo' => 'urgente', 'mensaje' => "Urgente: el vehículo lleva $diferencia km pasado el límite de cambio de aceite."];
        } elseif ($diferencia >= 0) {
            $alerta = ['nivel' => 1, 'tipo' => 'aviso', 'mensaje' => "Aviso: el vehículo llegó al límite de kilometraje para cambio de aceite."];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Kilometraje registrado correctamente',
        'alerta_aceite' => $alerta,
    ]);
} catch (PDOException $e) {
    error_log("Error guardando kilometraje: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al registrar el kilometraje']);
}