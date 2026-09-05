<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Solo administradores pueden marcar pilotos como inactivos.
$usuario_rol = $_SESSION['rol'] ?? 'Usuario';
$es_admin = in_array(strtolower(trim($usuario_rol)), ['admin', 'administrador']);
if (!$es_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede modificar pilotos.']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    // Se marca inactivo (no se borra) para no perder el historial de operaciones ya asignadas.
    $stmt = $conn->prepare("UPDATE pilotos SET estado = 'inactivo' WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Piloto marcado como inactivo']);
} catch (PDOException $e) {
    error_log("Error eliminando piloto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el piloto']);
}