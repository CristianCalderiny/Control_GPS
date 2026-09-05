<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!in_array(strtolower(trim($_SESSION['rol'] ?? '')), ['admin', 'administrador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede eliminar vehículos']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM vehiculos WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el vehículo']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Vehículo eliminado correctamente']);
} catch (PDOException $e) {
    error_log("Error eliminando vehículo: " . $e->getMessage());
    // Si el vehículo está referenciado por otra tabla (llave foránea), no se puede borrar
    echo json_encode(['success' => false, 'message' => 'No se puede eliminar: el vehículo está en uso en otro registro']);
}