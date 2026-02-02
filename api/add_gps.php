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
    $imei = $_POST['imei'] ?? '';
    $marca = $_POST['marca'] ?? '';
    $modelo = $_POST['modelo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';

    if (empty($imei) || empty($marca) || empty($modelo)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    // Verificar si ya existe
    $checkSql = "SELECT id FROM gps_dispositivos WHERE imei = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$imei]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un GPS con este IMEI']);
        exit;
    }

    $sql = "INSERT INTO gps_dispositivos (imei, marca, modelo, descripcion, estado, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'disponible', NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$imei, $marca, $modelo, $descripcion]);

    echo json_encode(['success' => true, 'message' => 'GPS agregado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>