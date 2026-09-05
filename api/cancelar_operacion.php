<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE operaciones SET estado = 'cancelado' WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error cancelando operación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cancelar la operación']);
}