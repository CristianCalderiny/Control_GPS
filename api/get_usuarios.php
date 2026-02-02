<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

require_once '../conexion/db.php';

try {
    $sql = "SELECT id, nombre_completo, usuario, email, rol, estado, created_at, ultimo_acceso 
            FROM usuarios 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'total' => count($usuarios)
    ]);

} catch (PDOException $e) {
    error_log("Error en get_usuarios: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener usuarios'
    ]);
}
?>