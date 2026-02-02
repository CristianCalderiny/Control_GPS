<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    // Validar datos requeridos
    $required = ['custodioId', 'tipoMisionId', 'nombreMision', 'fechaInicio'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "El campo {$field} es requerido"]);
            exit;
        }
    }

    $custodioId = $_POST['custodioId'];
    $gpsId = $_POST['gpsId'] ?? null;
    $asignacionId = $_POST['asignacionId'] ?? null;
    $tipoMisionId = $_POST['tipoMisionId'];
    $codigoMision = $_POST['codigoMision'] ?? null; // Se generará automáticamente si no se proporciona
    $nombreMision = $_POST['nombreMision'];
    $descripcion = $_POST['descripcion'] ?? null;
    $cliente = $_POST['cliente'] ?? null;
    $ubicacionOrigen = $_POST['ubicacionOrigen'] ?? null;
    $ubicacionDestino = $_POST['ubicacionDestino'] ?? null;
    $coordenadasOrigen = $_POST['coordenadasOrigen'] ?? null;
    $coordenadasDestino = $_POST['coordenadasDestino'] ?? null;
    $fechaInicio = $_POST['fechaInicio'];
    $fechaFin = $_POST['fechaFin'] ?? null;
    $duracionEstimada = $_POST['duracionEstimada'] ?? null;
    $estado = $_POST['estado'] ?? 'pendiente';
    $prioridad = $_POST['prioridad'] ?? 'media';
    $observaciones = $_POST['observaciones'] ?? null;
    $usuarioId = $_SESSION['usuario_id'];

    // Validar que el custodio existe y está activo
    $stmtCustodio = $conn->prepare("SELECT id FROM custodios WHERE id = ? AND estado = 'activo'");
    $stmtCustodio->execute([$custodioId]);
    if (!$stmtCustodio->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Custodio no válido o inactivo']);
        exit;
    }

    // Validar que el tipo de misión existe
    $stmtTipo = $conn->prepare("SELECT id FROM tipos_misiones WHERE id = ?");
    $stmtTipo->execute([$tipoMisionId]);
    if (!$stmtTipo->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tipo de misión no válido']);
        exit;
    }

    // Insertar misión
    $sql = "INSERT INTO misiones (
        custodio_id, gps_id, asignacion_id, tipo_mision_id, codigo_mision,
        nombre_mision, descripcion, cliente, ubicacion_origen, ubicacion_destino,
        coordenadas_origen, coordenadas_destino, fecha_inicio, fecha_fin,
        duracion_estimada, estado, prioridad, observaciones, created_by
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $custodioId, $gpsId, $asignacionId, $tipoMisionId, $codigoMision,
        $nombreMision, $descripcion, $cliente, $ubicacionOrigen, $ubicacionDestino,
        $coordenadasOrigen, $coordenadasDestino, $fechaInicio, $fechaFin,
        $duracionEstimada, $estado, $prioridad, $observaciones, $usuarioId
    ]);

    $misionId = $conn->lastInsertId();

    // Obtener el código generado automáticamente
    $stmtCodigo = $conn->prepare("SELECT codigo_mision FROM misiones WHERE id = ?");
    $stmtCodigo->execute([$misionId]);
    $codigo = $stmtCodigo->fetch(PDO::FETCH_ASSOC)['codigo_mision'];

    // Si se inició la misión, cambiar estado del GPS si está asignado
    if ($estado === 'en_curso' && $gpsId) {
        $stmtGps = $conn->prepare("UPDATE gps_dispositivos SET estado = 'asignado' WHERE id = ?");
        $stmtGps->execute([$gpsId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Misión creada exitosamente',
        'misionId' => $misionId,
        'codigoMision' => $codigo
    ]);

} catch (PDOException $e) {
    error_log("Error en add_mision.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al crear misión: ' . $e->getMessage()]);
}