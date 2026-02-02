<?php
session_start();
require_once '../conexion/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $mision_id = $_POST['misionId'] ?? null;
    $fecha_fin = $_POST['fechaFin'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    
    if (!$mision_id || !$fecha_fin) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }
    
    // Obtener información de la misión
    $getMision = $conn->prepare("SELECT gps_id FROM misiones WHERE id = ?");
    $getMision->execute([$mision_id]);
    $mision = $getMision->fetch(PDO::FETCH_ASSOC);
    
    if (!$mision) {
        echo json_encode(['success' => false, 'message' => 'Misión no encontrada']);
        exit;
    }
    
    $conn->beginTransaction();
    
    // Actualizar misión
    $sql = "UPDATE misiones 
            SET fecha_fin = ?, 
                estado = 'completada',
                observaciones = CONCAT(COALESCE(observaciones, ''), ' | Completada: ', ?)
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha_fin, $observaciones, $mision_id]);
    
    // Actualizar estado del GPS a disponible
    $updateGPS = $conn->prepare("UPDATE gps_dispositivos SET estado = 'disponible' WHERE id = ?");
    $updateGPS->execute([$mision['gps_id']]);
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Misión completada exitosamente']);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en complete_mision.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al completar misión']);
}