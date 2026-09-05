<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Solo administradores pueden eliminar pilotos permanentemente.
$usuario_rol = $_SESSION['rol'] ?? 'Usuario';
$es_admin = in_array(strtolower(trim($usuario_rol)), ['admin', 'administrador']);
if (!$es_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede eliminar pilotos.']);
    exit;
}

require_once '../conexion/db.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $check = $conn->prepare("SELECT id, nombre FROM pilotos WHERE id = ?");
    $check->execute([$id]);
    $piloto = $check->fetch(PDO::FETCH_ASSOC);
    if (!$piloto) {
        echo json_encode(['success' => false, 'message' => 'El piloto no existe']);
        exit;
    }

    // No permitir eliminar si el piloto tiene operaciones asociadas,
    // para no perder el historial de servicios ni dejar referencias huérfanas.
    $opStmt = $conn->prepare("SELECT COUNT(*) FROM operaciones WHERE patrullero_id = ?");
    $opStmt->execute([$id]);
    $totalOperaciones = (int) $opStmt->fetchColumn();

    if ($totalOperaciones > 0) {
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar: {$piloto['nombre']} tiene {$totalOperaciones} operación(es) asociada(s). Reasigna o elimina esas operaciones primero, o márcalo como inactivo."
        ]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM pilotos WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error eliminando piloto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el piloto']);
}