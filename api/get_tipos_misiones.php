<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $sql = "
        SELECT 
            id,
            nombre,
            descripcion,
            color,
            icono,
            created_at
        FROM tipos_misiones
        ORDER BY id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tipos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la base de datos',
        'message' => $e->getMessage()
    ]);
}
?>