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
    echo json_encode(['success' => false, 'message' => 'Falta el id del custodio a eliminar']);
    exit;
}

try {
    // No borramos la fila: un custodio puede tener asignaciones de GPS u
    // operaciones históricas relacionadas (llave foránea en asignaciones_gps),
    // así que solo lo marcamos como inactivo para que deje de aparecer como
    // disponible sin perder el historial.
    $stmt = $conn->prepare("UPDATE custodios SET estado = 'inactivo' WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'El custodio no existe']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Custodio marcado como inactivo correctamente']);
} catch (PDOException $e) {
    error_log("Error eliminando custodio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el custodio']);
}