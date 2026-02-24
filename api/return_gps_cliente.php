<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    $asignacionId      = isset($_POST['asignacionId'])        ? intval($_POST['asignacionId'])        : null;
    $fechaRetorno      = isset($_POST['fechaRetorno'])         ? trim($_POST['fechaRetorno'])          : null;
    $estadoGPS         = isset($_POST['estadoGPS'])            ? trim($_POST['estadoGPS'])             : null;
    $observaciones     = isset($_POST['observacionesRetorno']) ? trim($_POST['observacionesRetorno'])  : '';

    if (empty($asignacionId) || empty($fechaRetorno) || empty($estadoGPS)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }

    // ✅ CORRECTO: Buscar en asignaciones_CLIENTE_gps, no en asignaciones_gps
    $stmt = $conn->prepare("
        SELECT 
            a.id, a.gps_id, a.cliente, a.piloto, a.placa,
            a.contenedor, a.origen, a.destino, a.estado, a.fecha_asignacion,
            g.imei, g.marca, g.modelo
        FROM asignaciones_cliente_gps a
        INNER JOIN gps_dispositivos g ON a.gps_id = g.id
        WHERE a.id = ? AND a.estado = 'asignado'
    ");
    $stmt->execute([$asignacionId]);
    $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asignacion) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asignación no encontrada o ya ha sido retornada']);
        exit;
    }

    $conn->beginTransaction();

    // 1. Actualizar asignacion_cliente_gps
    $conn->prepare("
        UPDATE asignaciones_cliente_gps 
        SET estado = 'retornado',
            fecha_retorno = ?,
            estado_gps_retorno = ?,
            observaciones_retorno = ?
        WHERE id = ?
    ")->execute([$fechaRetorno, $estadoGPS, $observaciones, $asignacionId]);

    // 2. Liberar el GPS
    $conn->prepare("
        UPDATE gps_dispositivos 
        SET estado = 'disponible', ubicacion = 'Instalaciones' 
        WHERE id = ?
    ")->execute([$asignacion['gps_id']]);

    // 3. Registrar en historial_movimientos
    $detalles = "Retorno de cliente: " . $asignacion['cliente'];
    if ($asignacion['piloto']) $detalles .= " | Piloto: " . $asignacion['piloto'];
    if ($asignacion['placa'])  $detalles .= " | Placa: "  . $asignacion['placa'];
    $detalles .= " | Estado GPS: $estadoGPS";

    $conn->prepare("
        INSERT INTO historial_movimientos (gps_id, tipo_movimiento, detalles, usuario_id)
        VALUES (?, 'retorno_cliente', ?, ?)
    ")->execute([$asignacion['gps_id'], $detalles, $_SESSION['usuario_id']]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'GPS retornado correctamente',
        'datos' => [
            'imei'          => $asignacion['imei'],
            'cliente'       => $asignacion['cliente'],
            'piloto'        => $asignacion['piloto'] ?? 'N/A',
            'placa'         => $asignacion['placa']  ?? 'N/A',
            'fecha_retorno' => $fechaRetorno,
            'estado'        => $estadoGPS
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}