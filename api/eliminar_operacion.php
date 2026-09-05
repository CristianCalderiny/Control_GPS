<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $conn->beginTransaction();

    // Verificar que la operación exista
    $check = $conn->prepare("SELECT id FROM operaciones WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'La operación no existe']);
        exit;
    }

    // Buscar automáticamente cualquier tabla que tenga una llave foránea
    // apuntando a operaciones.id, para borrar primero esas filas relacionadas
    // (custodios/GPS asignados, etc.) y evitar errores de integridad referencial.
    $fkSQL = "
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = 'operaciones'
          AND REFERENCED_COLUMN_NAME = 'id'
    ";
    $fkStmt = $conn->prepare($fkSQL);
    $fkStmt->execute();
    $relaciones = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($relaciones as $rel) {
        $tabla = $rel['TABLE_NAME'];
        $columna = $rel['COLUMN_NAME'];
        // Nombres de tabla/columna vienen del catálogo del propio motor, no de input del usuario
        $del = $conn->prepare("DELETE FROM `$tabla` WHERE `$columna` = ?");
        $del->execute([$id]);
    }

    // Finalmente eliminar el registro principal
    $stmt = $conn->prepare("DELETE FROM operaciones WHERE id = ?");
    $stmt->execute([$id]);

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error eliminando operación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la operación']);
}