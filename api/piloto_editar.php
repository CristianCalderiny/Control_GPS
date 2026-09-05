<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id        = $_POST['id'] ?? '';
$nombre    = trim($_POST['nombre'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$pin       = trim($_POST['pin'] ?? '');
$estado    = $_POST['estado'] ?? 'activo';
$zona      = trim($_POST['zona'] ?? '');

$zonasValidas = ['Norte','Centro','Sur','Motorizadas'];

if ($id === '' || !ctype_digit((string)$id)) {
    echo json_encode(['success' => false, 'message' => 'Falta el id del piloto a editar']);
    exit;
}
if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    exit;
}
if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) {
    echo json_encode(['success' => false, 'message' => 'El PIN debe tener exactamente 4 dígitos']);
    exit;
}
if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $estado = 'activo';
}

// La zona es opcional; si viene, debe ser una de las zonas válidas.
if ($zona === '') {
    $zona = null;
} elseif (!in_array($zona, $zonasValidas)) {
    echo json_encode(['success' => false, 'message' => 'Zona inválida']);
    exit;
}

try {
    if ($pin !== '') {
        // Se asignó un PIN nuevo: lo hasheamos y lo actualizamos junto al resto
        $pinHash = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            UPDATE pilotos
            SET nombre = ?, telefono = ?, estado = ?, zona = ?, pin_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $telefono !== '' ? $telefono : null, $estado, $zona, $pinHash, $id]);
    } else {
        // Sin PIN nuevo: se deja el PIN actual tal cual
        $stmt = $conn->prepare("
            UPDATE pilotos
            SET nombre = ?, telefono = ?, estado = ?, zona = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $telefono !== '' ? $telefono : null, $estado, $zona, $id]);
    }

    echo json_encode(['success' => true, 'message' => 'Piloto actualizado correctamente']);
} catch (PDOException $e) {
    error_log("Error editando piloto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el piloto']);
}