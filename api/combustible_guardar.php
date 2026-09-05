<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo el admin registra cargas de combustible (no el piloto).
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$vehiculoId       = $_POST['vehiculo_id'] ?? '';
$fecha            = $_POST['fecha'] ?? date('Y-m-d');
$kmFinal          = $_POST['km_final'] ?? '';
$kmInicialManual  = $_POST['km_inicial'] ?? '';
$litrosCargados   = $_POST['litros_cargados'] ?? '';
$montoLempiras    = $_POST['monto_lempiras'] ?? '';
$nivelInicio      = trim($_POST['nivel_inicio'] ?? '');
$nivelRecarga     = trim($_POST['nivel_recarga'] ?? '');
$observaciones    = trim($_POST['observaciones'] ?? '');

if ($vehiculoId === '' || !ctype_digit((string)$vehiculoId)) {
    echo json_encode(['success' => false, 'message' => 'Selecciona un vehículo']);
    exit;
}
if ($kmFinal === '' || !ctype_digit((string)$kmFinal)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa el kilometraje actual (solo números)']);
    exit;
}
if ($litrosCargados === '' || !is_numeric($litrosCargados) || (float)$litrosCargados <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ingresa los litros cargados (mayor a 0)']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

try {
    // Vehículo asignado a qué piloto (se guarda automáticamente, igual que en kilometraje).
    $stmtVeh = $conn->prepare("SELECT patrullero_id FROM vehiculos WHERE id = ?");
    $stmtVeh->execute([$vehiculoId]);
    $veh = $stmtVeh->fetch(PDO::FETCH_ASSOC);
    if (!$veh) {
        echo json_encode(['success' => false, 'message' => 'El vehículo no existe']);
        exit;
    }
    $patrulleroId = $veh['patrullero_id'];

    // El kilometraje inicial de esta carga es el km_final de la última carga
    // registrada para este vehículo. Así el admin solo digita el km actual.
    $stmtUltima = $conn->prepare("SELECT km_final FROM vehiculo_combustible WHERE vehiculo_id = ? ORDER BY creado_en DESC LIMIT 1");
    $stmtUltima->execute([$vehiculoId]);
    $ultimaCarga = $stmtUltima->fetchColumn();

    if ($ultimaCarga !== false) {
        $kmInicial = (int)$ultimaCarga;
    } elseif ($kmInicialManual !== '' && ctype_digit((string)$kmInicialManual)) {
        // Primera carga registrada para este vehículo: se necesita el km inicial.
        $kmInicial = (int)$kmInicialManual;
    } else {
        echo json_encode(['success' => false, 'message' => 'Es la primera carga de este vehículo: ingresa también el kilometraje inicial']);
        exit;
    }

    if ((int)$kmFinal <= $kmInicial) {
        echo json_encode(['success' => false, 'message' => "El kilometraje actual debe ser mayor al inicial ($kmInicial km)"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO vehiculo_combustible
            (vehiculo_id, patrullero_id, fecha, km_inicial, km_final, nivel_inicio, nivel_recarga, litros_cargados, monto_lempiras, observaciones, creado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $vehiculoId,
        $patrulleroId,
        $fecha,
        $kmInicial,
        $kmFinal,
        $nivelInicio !== '' ? $nivelInicio : null,
        $nivelRecarga !== '' ? $nivelRecarga : null,
        $litrosCargados,
        $montoLempiras !== '' ? $montoLempiras : null,
        $observaciones !== '' ? $observaciones : null,
        $_SESSION['usuario_id'],
    ]);

    $kmRecorridos = (int)$kmFinal - $kmInicial;
    $kmPorLitroActual = round($kmRecorridos / (float)$litrosCargados, 3);

    // ---- Alerta de bajo rendimiento vs. el propio promedio histórico del vehículo ----
    // Se compara contra el promedio de las cargas anteriores (sin contar esta).
    // Nivel 1 (aviso):   rinde 15% o más por debajo de su propio promedio
    // Nivel 2 (urgente): rinde 25% o más por debajo
    // Nivel 3 (crítico): rinde 40% o más por debajo
    $stmtProm = $conn->prepare("
        SELECT AVG(km_por_litro) AS promedio, COUNT(*) AS total
        FROM vehiculo_combustible
        WHERE vehiculo_id = ? AND id <> LAST_INSERT_ID()
    ");
    $stmtProm->execute([$vehiculoId]);
    $prom = $stmtProm->fetch(PDO::FETCH_ASSOC);

    $alerta = null;
    // Se requiere un mínimo de 3 cargas previas para tener un promedio confiable.
    if ($prom && (int)$prom['total'] >= 3 && $prom['promedio'] !== null && (float)$prom['promedio'] > 0) {
        $promedio = (float)$prom['promedio'];
        $variacionPct = round((($promedio - $kmPorLitroActual) / $promedio) * 100, 1);

        if ($variacionPct >= 40) {
            $alerta = ['nivel' => 3, 'tipo' => 'critico', 'variacion_pct' => $variacionPct, 'mensaje' => "Crítico: este vehículo está rindiendo $variacionPct% menos que su propio promedio ({$promedio} km/l). Revisar de inmediato (posible fuga, robo de combustible o falla mecánica)."];
        } elseif ($variacionPct >= 25) {
            $alerta = ['nivel' => 2, 'tipo' => 'urgente', 'variacion_pct' => $variacionPct, 'mensaje' => "Urgente: rendimiento $variacionPct% por debajo de su promedio histórico."];
        } elseif ($variacionPct >= 15) {
            $alerta = ['nivel' => 1, 'tipo' => 'aviso', 'variacion_pct' => $variacionPct, 'mensaje' => "Aviso: rendimiento $variacionPct% por debajo de su promedio histórico."];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Carga de combustible registrada correctamente',
        'km_por_litro' => $kmPorLitroActual,
        'alerta_combustible' => $alerta,
    ]);
} catch (PDOException $e) {
    error_log("Error guardando carga de combustible: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al registrar la carga de combustible']);
}