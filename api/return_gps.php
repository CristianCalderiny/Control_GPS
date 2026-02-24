<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    $asignacionId = $_POST['asignacionId'] ?? null;
    $tipoAsignacion = $_POST['tipoAsignacion'] ?? 'custodio';
    $fechaRetorno = $_POST['fechaRetorno'] ?? null;
    $estadoGPS = $_POST['estadoGPS'] ?? null;
    $observacionesRetorno = $_POST['observacionesRetorno'] ?? null;
    $usuarioId = $_SESSION['usuario_id'];

    // ============================================================
    // VALIDACIONES
    // ============================================================
    if (!$asignacionId || !$fechaRetorno || !$estadoGPS) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Datos incompletos'
        ]);
        exit;
    }

    error_log("=== RETORNO DE GPS ===");
    error_log("asignacionId: $asignacionId");
    error_log("tipoAsignacion: $tipoAsignacion");
    error_log("fechaRetorno: $fechaRetorno");
    error_log("estadoGPS: $estadoGPS");

    $conn->beginTransaction();

    // ============================================================
    // BUSCAR ASIGNACIÓN EN asignaciones_gps
    // ============================================================
    
    $stmtAsignacion = $conn->prepare("
        SELECT id, gps_id, tipo_asignacion 
        FROM asignaciones_gps 
        WHERE id = ? AND estado = 'asignado'
    ");
    $stmtAsignacion->execute([$asignacionId]);
    $asignacion = $stmtAsignacion->fetch(PDO::FETCH_ASSOC);

    if (!$asignacion) {
        error_log("✗ Asignación no encontrada o ya fue retornada");
        throw new Exception('Asignación no encontrada o ya ha sido retornada');
    }

    error_log("✓ Asignación encontrada en asignaciones_gps");
    $gpsId = $asignacion['gps_id'];

    // ============================================================
    // ACTUALIZAR ASIGNACIÓN
    // ============================================================
    
    $stmtUpdate = $conn->prepare("
        UPDATE asignaciones_gps 
        SET fecha_retorno = ?,
            estado_retorno = ?,
            observaciones_retorno = ?,
            estado = 'retornado',
            usuario_retorno_id = ?
        WHERE id = ?
    ");

    $resultUpdate = $stmtUpdate->execute([
        $fechaRetorno,
        $estadoGPS,
        $observacionesRetorno ?: null,
        $usuarioId,
        $asignacionId
    ]);

    if (!$resultUpdate) {
        error_log("✗ Error al actualizar asignación: " . json_encode($stmtUpdate->errorInfo()));
        throw new Exception('Error al actualizar asignación');
    }
    error_log("✓ Asignación actualizada correctamente");

    // ============================================================
    // ACTUALIZAR GPS
    // ============================================================
    
    $nuevoEstado = ($estadoGPS === 'dañado') ? 'dañado' : 'disponible';
    error_log("Actualizando GPS $gpsId a estado: $nuevoEstado");

    $stmtGPS = $conn->prepare("
        UPDATE gps_dispositivos 
        SET estado = ?, ubicacion = 'Instalaciones' 
        WHERE id = ?
    ");
    
    $resultGPS = $stmtGPS->execute([$nuevoEstado, $gpsId]);
    
    if (!$resultGPS) {
        error_log("✗ Error al actualizar GPS: " . json_encode($stmtGPS->errorInfo()));
        throw new Exception('Error al actualizar GPS');
    }
    error_log("✓ GPS actualizado correctamente");

    // ============================================================
    // REGISTRAR EN HISTORIAL
    // ============================================================
    
    $stmtHistorial = $conn->prepare("
        INSERT INTO historial_movimientos 
        (gps_id, tipo_movimiento, detalles, usuario_id)
        VALUES (?, 'retorno', ?, ?)
    ");
    $detalles = "Retornado. Estado: $estadoGPS. Observaciones: " . ($observacionesRetorno ?: 'N/A');
    
    $resultHistorial = $stmtHistorial->execute([$gpsId, $detalles, $usuarioId]);
    
    if (!$resultHistorial) {
        error_log("⚠ Advertencia al registrar historial: " . json_encode($stmtHistorial->errorInfo()));
        // No es crítico si falla el historial
    } else {
        error_log("✓ Historial registrado");
    }

    // ============================================================
    // COMMIT
    // ============================================================
    
    $conn->commit();
    error_log("✓ Transacción completada exitosamente");

    echo json_encode([
        'success' => true, 
        'message' => 'Retorno registrado correctamente',
        'gps_id' => $gpsId,
        'nuevo_estado' => $nuevoEstado
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log("✗ Transacción revertida");
    }
    
    error_log("✗ Error en return_gps.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}