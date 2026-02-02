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
    $sql = "SELECT id, nombre, telefono, cargo, estado FROM custodios WHERE estado = 'activo' ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $custodios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($custodios);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>