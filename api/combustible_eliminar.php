<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    echo json_encode(['success' => false, 'message' => 'Registro inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM vehiculo_combustible WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Registro de combustible eliminado']);
} catch (PDOException $e) {
    error_log("Error eliminando registro de combustible: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el registro']);
}