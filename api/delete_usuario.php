<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

// Proteger que no se elimine a sí mismo
if ($_SESSION['usuario_id'] == $id) {
    echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propia cuenta']);
    exit;
}

try {
    $deleteSql = "DELETE FROM usuarios WHERE id = :id";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->execute([':id' => $id]);

    if ($deleteStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    }

} catch (PDOException $e) {
    error_log("Error en delete_usuario: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al eliminar usuario']);
}
?>