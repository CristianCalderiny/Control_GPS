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
    if (empty($_POST['misionId']) || empty($_POST['estado'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $misionId = $_POST['misionId'];
    $nuevoEstado = $_POST['estado'];
    $observaciones = $_POST['observaciones'] ?? null;
    $usuarioId = $_SESSION['usuario_id'];

    // Validar estado
    $estadosValidos = ['pendiente', 'en_curso', 'completada', 'cancelada'];
    if (!in_array($nuevoEstado, $estadosValidos)) {
        echo json_encode(['success' => false, 'message' => 'Estado no válido']);
        exit;
    }

    // Obtener estado actual
    $stmtCheck = $conn->prepare("SELECT estado, gps_id FROM misiones WHERE id = ?");
    $stmtCheck->execute([$misionId]);
    $mision = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$mision) {
        echo json_encode(['success' => false, 'message' => 'Misión no encontrada']);
        exit;
    }

    $estadoAnterior = $mision['estado'];

    // Actualizar estado
    $sql = "UPDATE misiones SET estado = ?, updated_by = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nuevoEstado, $usuarioId, $misionId]);

    // Registrar en historial
    $sqlHistorial = "INSERT INTO misiones_historial (mision_id, estado_anterior, estado_nuevo, observaciones, usuario_id)
                     VALUES (?, ?, ?, ?, ?)";
    $stmtHistorial = $conn->prepare($sqlHistorial);
    $stmtHistorial->execute([$misionId, $estadoAnterior, $nuevoEstado, $observaciones, $usuarioId]);

    // Si se inicia la misión y tiene GPS, marcarlo como asignado
    if ($nuevoEstado === 'en_curso' && $mision['gps_id']) {
        $stmtGps = $conn->prepare("UPDATE gps_dispositivos SET estado = 'asignado' WHERE id = ?");
        $stmtGps->execute([$mision['gps_id']]);
    }

    // Si se completa o cancela y tiene GPS, liberarlo
    if (($nuevoEstado === 'completada' || $nuevoEstado === 'cancelada') && $mision['gps_id']) {
        $stmtGps = $conn->prepare("UPDATE gps_dispositivos SET estado = 'disponible' WHERE id = ?");
        $stmtGps->execute([$mision['gps_id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente',
        'estadoAnterior' => $estadoAnterior,
        'estadoNuevo' => $nuevoEstado
    ]);

} catch (PDOException $e) {
    error_log("Error en update_estado_mision.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()]);
}