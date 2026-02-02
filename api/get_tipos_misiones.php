<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $sql = "SELECT * FROM tipos_misiones ORDER BY nombre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tipos);

} catch (PDOException $e) {
    error_log("Error en get_tipos_misiones.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener tipos de misiones: ' . $e->getMessage()]);
}