<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../conexion/db.php';

$id  = $_POST['id'] ?? null;
$pin = $_POST['pin'] ?? '';

if (!$id || !preg_match('/^\d{4}$/', $pin)) {
    echo json_encode(['success' => false, 'message' => 'El PIN debe ser de exactamente 4 dígitos']);
    exit;
}

try {
    $hash = password_hash($pin, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE pilotos SET pin_hash = ?, pin_actualizado_at = NOW() WHERE id = ?");
    $stmt->execute([$hash, $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontró ese piloto (verifica el id)']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error asignando PIN: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al asignar el PIN']);
}