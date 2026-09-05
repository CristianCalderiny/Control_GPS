<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$placa = trim($_POST['placa'] ?? '');
$marca = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$color = trim($_POST['color'] ?? '');
$estado = $_POST['estado'] ?? 'disponible';
$observaciones = trim($_POST['observaciones'] ?? '');
$patrulleroId = trim($_POST['patrullero_id'] ?? '');
$kmLimiteAceite = trim($_POST['km_limite_aceite'] ?? '');
$zona = trim($_POST['zona'] ?? '');

$estadosValidos = ['disponible','asignado','mantenimiento','inactivo'];
$zonasValidas = ['Norte','Centro','Sur','Motorizadas'];

if ($placa === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La placa es requerida']);
    exit;
}
if (!in_array($estado, $estadosValidos)) {
    $estado = 'disponible';
}

// El piloto es opcional (un vehículo puede quedar sin asignar). Si viene, debe ser numérico.
if ($patrulleroId === '') {
    $patrulleroId = null;
} elseif (!ctype_digit($patrulleroId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Piloto inválido']);
    exit;
}

// El límite de aceite es opcional, pero si viene debe ser numérico positivo.
if ($kmLimiteAceite === '') {
    $kmLimiteAceite = null;
} elseif (!ctype_digit($kmLimiteAceite)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El límite de kilometraje para aceite debe ser un número']);
    exit;
}

// La zona es opcional; si viene, debe ser una de las zonas válidas.
if ($zona === '') {
    $zona = null;
} elseif (!in_array($zona, $zonasValidas)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Zona inválida']);
    exit;
}

// Si se asigna un piloto, el estado del vehículo pasa a "asignado" automáticamente
// (el admin ya no elige "asignado" a mano, lo determina si hay o no piloto).
if ($patrulleroId !== null) {
    $estado = 'asignado';
} elseif ($estado === 'asignado') {
    // No puede quedar en "asignado" sin un piloto real detrás
    $estado = 'disponible';
}

try {
    if ($patrulleroId !== null) {
        // Un piloto no puede tener dos vehículos asignados a la vez
        $stmtDup = $conn->prepare("SELECT id, placa FROM vehiculos WHERE patrullero_id = ? LIMIT 1");
        $stmtDup->execute([$patrulleroId]);
        $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Ese piloto ya tiene asignado el vehículo {$dup['placa']}"]);
            exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO vehiculos (placa, marca, modelo, color, estado, observaciones, patrullero_id, zona, km_limite_aceite)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$placa, $marca, $modelo, $color, $estado, $observaciones, $patrulleroId, $zona, $kmLimiteAceite]);
    echo json_encode(['success' => true, 'message' => 'Vehículo agregado correctamente', 'id' => $conn->lastInsertId()]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ya existe un vehículo con esa placa, o el piloto elegido ya tiene otro vehículo asignado']);
        exit;
    }
    error_log("Error creando vehículo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el vehículo']);
}