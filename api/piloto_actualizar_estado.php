<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['patrullero_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada, vuelve a ingresar']);
    exit;
}

require_once '../conexion/db.php';

$patrullero_id = $_SESSION['patrullero_id'];
$id            = $_POST['id'] ?? null;
$nuevoEstado   = $_POST['estado'] ?? '';

$permitidos = ['en_ruta', 'finalizado'];
if (!$id || !in_array($nuevoEstado, $permitidos, true)) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

try {
    // Seguridad: solo puede tocar una operación que sea suya
    $check = $conn->prepare("SELECT id FROM operaciones WHERE id = ? AND patrullero_id = ?");
    $check->execute([$id, $patrullero_id]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Este servicio no te pertenece']);
        exit;
    }

    if ($nuevoEstado === 'en_ruta') {
        $stmt = $conn->prepare("UPDATE operaciones SET estado = 'en_ruta', hora_inicio = NOW() WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE operaciones SET estado = 'finalizado', hora_fin = NOW() WHERE id = ?");
    }
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error actualizando estado del servicio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el servicio']);
}