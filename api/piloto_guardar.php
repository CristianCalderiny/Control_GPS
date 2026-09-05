<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$nombre    = trim($_POST['nombre'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$pin       = trim($_POST['pin'] ?? '');
$estado    = $_POST['estado'] ?? 'activo';
$zona      = trim($_POST['zona'] ?? '');

$zonasValidas = ['Norte','Centro','Sur','Motorizadas'];

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    exit;
}

if ($pin === '' || !preg_match('/^\d{4}$/', $pin)) {
    echo json_encode(['success' => false, 'message' => 'Asigna un PIN de 4 dígitos para que el piloto pueda ingresar']);
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
    $pinHash = password_hash($pin, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("
        INSERT INTO pilotos (nombre, telefono, estado, zona, pin_hash)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nombre, $telefono !== '' ? $telefono : null, $estado, $zona, $pinHash]);

    echo json_encode(['success' => true, 'message' => 'Piloto creado correctamente']);
} catch (PDOException $e) {
    error_log("Error guardando piloto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar el piloto']);
}