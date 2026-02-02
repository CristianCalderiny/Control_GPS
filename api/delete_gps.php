<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $id = $_POST['id'] ?? null;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        exit;
    }

    // Verificar si el GPS está asignado
    $checkSql = "SELECT COUNT(*) as count FROM asignaciones_gps WHERE gps_id = ? AND estado = 'asignado'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$id]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar un GPS que está asignado']);
        exit;
    }

    $sql = "DELETE FROM gps_dispositivos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'GPS eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'GPS no encontrado']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>