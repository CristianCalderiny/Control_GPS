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
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $cargo = $_POST['cargo'] ?? '';

    if (empty($nombre) || empty($telefono) || empty($cargo)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    // Verificar si ya existe
    $checkSql = "SELECT id FROM custodios WHERE telefono = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$telefono]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un custodio con este teléfono']);
        exit;
    }

    $sql = "INSERT INTO custodios (nombre, telefono, cargo, estado, created_at, updated_at) 
            VALUES (?, ?, ?, 'activo', NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nombre, $telefono, $cargo]);

    echo json_encode(['success' => true, 'message' => 'Custodio agregado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>