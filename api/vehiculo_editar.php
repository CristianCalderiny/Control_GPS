<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!in_array(strtolower(trim($_SESSION['rol'] ?? '')), ['admin', 'administrador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede editar vehículos']);
    exit;
}

require_once '../conexion/db.php';

// --- Helper: trata "", "null", "undefined" (strings que a veces manda el JS) como vacío ---
function limpiarValor($v) {
    $v = trim((string)($v ?? ''));
    if ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'undefined') {
        return '';
    }
    return $v;
}

$id            = limpiarValor($_POST['id'] ?? null);
$placa         = limpiarValor($_POST['placa'] ?? '');
$marca         = trim($_POST['marca'] ?? '');
$modelo        = trim($_POST['modelo'] ?? '');
$color         = trim($_POST['color'] ?? '');
$estado        = limpiarValor($_POST['estado'] ?? 'disponible');
$observaciones = trim($_POST['observaciones'] ?? '');
$patrulleroId  = limpiarValor($_POST['patrullero_id'] ?? '');
$kmLimiteAceite = limpiarValor($_POST['km_limite_aceite'] ?? '');
$zona          = limpiarValor($_POST['zona'] ?? '');

$estadosValidos = ['disponible','asignado','mantenimiento','inactivo'];
$zonasValidas   = ['Norte','Centro','Sur','Motorizadas'];

// --- Validación de campos requeridos, con detalle de cuál falta (quitar el detalle en producción si molesta) ---
$faltantes = [];
if ($id === '') $faltantes[] = 'id';
if ($placa === '') $faltantes[] = 'placa';
if (!in_array($estado, $estadosValidos)) $faltantes[] = 'estado (valor recibido: "' . $estado . '")';

if (!empty($faltantes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Faltan campos requeridos: ' . implode(', ', $faltantes)
    ]);
    exit;
}

// El piloto es opcional (un vehículo puede quedar sin asignar). Si viene, debe ser numérico.
if ($patrulleroId === '') {
    $patrulleroId = null;
} elseif (!ctype_digit($patrulleroId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Piloto inválido (valor recibido: "' . $patrulleroId . '")']);
    exit;
}

// El límite de aceite es opcional, pero si viene debe ser numérico positivo.
if ($kmLimiteAceite === '') {
    $kmLimiteAceite = null;
} elseif (!ctype_digit($kmLimiteAceite)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El límite de kilometraje para aceite debe ser un número (valor recibido: "' . $kmLimiteAceite . '")']);
    exit;
}

// La zona es opcional; si viene, debe ser una de las zonas válidas (comparación insensible a mayúsculas).
if ($zona === '') {
    $zona = null;
} else {
    $zonaNormalizada = null;
    foreach ($zonasValidas as $zv) {
        if (strcasecmp($zv, $zona) === 0) {
            $zonaNormalizada = $zv;
            break;
        }
    }
    if ($zonaNormalizada === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Zona inválida (valor recibido: "' . $zona . '")']);
        exit;
    }
    $zona = $zonaNormalizada;
}

// Si se asigna un piloto, el estado pasa a "asignado" automáticamente; si se quita
// el piloto, no puede quedar en "asignado" sin nadie detrás.
if ($patrulleroId !== null) {
    $estado = 'asignado';
} elseif ($estado === 'asignado') {
    $estado = 'disponible';
}

try {
    if ($patrulleroId !== null) {
        // Un piloto no puede tener dos vehículos asignados a la vez (se excluye este mismo vehículo)
        $stmtDup = $conn->prepare("SELECT id, placa FROM vehiculos WHERE patrullero_id = ? AND id != ? LIMIT 1");
        $stmtDup->execute([$patrulleroId, $id]);
        $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Ese piloto ya tiene asignado el vehículo {$dup['placa']}"]);
            exit;
        }
    }

    // El límite de aceite no puede quedar por debajo del kilometraje que el
    // vehículo ya tiene reportado (si no, la alerta nacería "vencida" al instante).
    // OJO: esta regla solo debe aplicar si el usuario está CAMBIANDO el límite a
    // un valor nuevo. Si el límite que llega es el mismo que ya estaba guardado,
    // no se vuelve a validar; de lo contrario, un vehículo que ya superó su
    // límite quedaría "atascado" y no se podría editar nada más en él
    // (por ejemplo, quitarle el piloto).
    if ($kmLimiteAceite !== null) {
        $stmtLimiteActual = $conn->prepare("SELECT km_limite_aceite FROM vehiculos WHERE id = ?");
        $stmtLimiteActual->execute([$id]);
        $limiteActual = $stmtLimiteActual->fetchColumn();

        $limiteEstaCambiando = ($limiteActual === false)
            || ($limiteActual === null)
            || ((int)$kmLimiteAceite !== (int)$limiteActual);

        if ($limiteEstaCambiando) {
            $stmtUltimoKm = $conn->prepare("SELECT kilometraje FROM vehiculo_kilometrajes WHERE vehiculo_id = ? ORDER BY creado_en DESC LIMIT 1");
            $stmtUltimoKm->execute([$id]);
            $ultimoKm = $stmtUltimoKm->fetchColumn();

            if ($ultimoKm !== false && (int)$kmLimiteAceite < (int)$ultimoKm) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "El límite de aceite no puede ser menor al kilometraje ya reportado del vehículo ($ultimoKm km)"]);
                exit;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE vehiculos SET placa=?, marca=?, modelo=?, color=?, estado=?, observaciones=?, patrullero_id=?, zona=?, km_limite_aceite=? WHERE id=?");
    $stmt->execute([$placa, $marca, $modelo, $color, $estado, $observaciones, $patrulleroId, $zona, $kmLimiteAceite, $id]);
    echo json_encode(['success' => true, 'message' => 'Vehículo actualizado correctamente']);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ya existe un vehículo con esa placa']);
        exit;
    }
    error_log("Error editando vehículo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el vehículo']);
}