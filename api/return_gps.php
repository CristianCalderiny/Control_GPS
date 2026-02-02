<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $asignacionId = $_POST['asignacionId'] ?? '';
    $fechaRetorno = $_POST['fechaRetorno'] ?? '';
    $estadoGPS = $_POST['estadoGPS'] ?? '';
    $observacionesRetorno = $_POST['observacionesRetorno'] ?? '';

    if (empty($asignacionId) || empty($fechaRetorno) || empty($estadoGPS)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    // Obtener la asignación para conseguir el GPS ID
    $getSql = "SELECT gps_id FROM asignaciones_gps WHERE id = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->execute([$asignacionId]);
    $asignacion = $getStmt->fetch(PDO::FETCH_ASSOC);

    if (!$asignacion) {
        echo json_encode(['success' => false, 'message' => 'Asignación no encontrada']);
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Actualizar asignación
    $updateAsignacion = "UPDATE asignaciones_gps 
                        SET fecha_retorno = ?, estado = 'retornado', observaciones = CONCAT(IFNULL(observaciones, ''), '\n\nRetorno: ', ?), updated_at = NOW() 
                        WHERE id = ?";
    $stmtAsignacion = $conn->prepare($updateAsignacion);
    $stmtAsignacion->execute([$fechaRetorno, $observacionesRetorno, $asignacionId]);

    // Actualizar GPS a disponible
    $updateGps = "UPDATE gps_dispositivos SET estado = 'disponible', updated_at = NOW() WHERE id = ?";
    $stmtGps = $conn->prepare($updateGps);
    $stmtGps->execute([$asignacion['gps_id']]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'GPS retornado correctamente']);
} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>