<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    // Obtener datos del formulario
    $gpsId = $_POST['gpsIdCliente'] ?? null;
    $clienteNombre = $_POST['clienteNombre'] ?? null;
    $piloto = $_POST['piloto'] ?? null;
    $placa = $_POST['placa'] ?? null;
    $contenedor = $_POST['contenedor'] ?? null;
    $origen = $_POST['origenCliente'] ?? null;
    $destino = $_POST['destinoCliente'] ?? null;
    $observaciones = $_POST['observacionesCliente'] ?? null;
    $fechaAsignacion = $_POST['fechaAsignacionCliente'] ?? null;
    $usuarioId = $_SESSION['usuario_id'];

    // Validar campos obligatorios
    if (!$gpsId || !$clienteNombre || !$origen || !$destino || !$fechaAsignacion) {
        throw new Exception('Campos obligatorios incompletos');
    }

    // Validar que el GPS existe
    $stmtGPS = $conn->prepare("SELECT id, estado FROM gps_dispositivos WHERE id = ?");
    $stmtGPS->execute([$gpsId]);
    $gps = $stmtGPS->fetch(PDO::FETCH_ASSOC);

    if (!$gps) {
        throw new Exception('GPS no encontrado');
    }

    // Validar que el GPS está disponible
    if ($gps['estado'] !== 'disponible') {
        throw new Exception('El GPS no está disponible');
    }

    // Validar que no tenga asignación activa
    $stmtCheck = $conn->prepare("
        SELECT COUNT(*) as count FROM asignaciones_gps 
        WHERE gps_id = ? AND estado = 'asignado'
    ");
    $stmtCheck->execute([$gpsId]);
    $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($check['count'] > 0) {
        throw new Exception('Este GPS ya tiene una asignación activa');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar en tabla de asignaciones por cliente
    $stmtInsert = $conn->prepare("
        INSERT INTO asignaciones_cliente_gps 
        (gps_id, cliente, piloto, placa, contenedor, origen, destino, observaciones, 
         fecha_asignacion, estado, usuario_asigno_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'asignado', ?)
    ");

    $stmtInsert->execute([
        $gpsId,
        $clienteNombre,
        $piloto ?: null,
        $placa ?: null,
        $contenedor ?: null,
        $origen,
        $destino,
        $observaciones ?: null,
        $fechaAsignacion,
        $usuarioId
    ]);

    $asignacionId = $conn->lastInsertId();

    // Actualizar estado del GPS
    $stmtUpdateGPS = $conn->prepare("
        UPDATE gps_dispositivos 
        SET estado = 'asignado', ubicacion = 'En transporte' 
        WHERE id = ?
    ");
    $stmtUpdateGPS->execute([$gpsId]);

    // Registrar en historial
    $stmtHistorial = $conn->prepare("
        INSERT INTO historial_movimientos 
        (gps_id, tipo_movimiento, detalles, usuario_id)
        VALUES (?, 'asignacion_cliente', ?, ?)
    ");

    $detalles = "Asignado a cliente: $clienteNombre";
    if ($piloto) $detalles .= " | Piloto: $piloto";
    if ($placa) $detalles .= " | Placa: $placa";

    $stmtHistorial->execute([$gpsId, $detalles, $usuarioId]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'GPS asignado correctamente al cliente',
        'asignacion_id' => $asignacionId
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}