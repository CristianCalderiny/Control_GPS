<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
 
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
 
require_once '../conexion/db.php';
 
try {
    $stmt = $conn->query("SELECT id, placa, marca, modelo, color, estado, observaciones, created_at,
                                  patrullero_id, zona, km_limite_aceite
                           FROM vehiculos ORDER BY placa ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log("Error listando vehículos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al listar vehículos']);
}