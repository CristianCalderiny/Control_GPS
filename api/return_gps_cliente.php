<?php
/**
 * API para retornar GPS asignados a CLIENTES
 * Maneja la devolución de equipos GPS de transporte/clientes
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// ========== FUNCIÓN DE LOGGING ==========
function escribir_log($mensaje)
{
    $log_dir = __DIR__ . '/logs';
    $log_file = $log_dir . '/return_gps_cliente.log';
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('[Y-m-d H:i:s] ');
    @file_put_contents($log_file, $timestamp . $mensaje . "\n", FILE_APPEND);
}

// ========== INICIO ==========
escribir_log("=== INICIO RETORNO GPS CLIENTE ===");

// ========== VALIDAR SESIÓN ==========
if (!isset($_SESSION['usuario_id'])) {
    escribir_log("ERROR: No autenticado");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    // ========== OBTENER PARÁMETROS ==========
    $asignacionId = isset($_POST['asignacionId']) ? intval($_POST['asignacionId']) : null;
    $fechaRetorno = isset($_POST['fechaRetorno']) ? trim($_POST['fechaRetorno']) : null;
    $estadoGPS = isset($_POST['estadoGPS']) ? trim($_POST['estadoGPS']) : null;
    $observacionesRetorno = isset($_POST['observacionesRetorno']) ? trim($_POST['observacionesRetorno']) : '';

    escribir_log("Datos recibidos: asignacionId=$asignacionId, fecha=$fechaRetorno, estado=$estadoGPS");

    // ========== VALIDACIÓN: DATOS OBLIGATORIOS ==========
    if (empty($asignacionId) || empty($fechaRetorno) || empty($estadoGPS)) {
        escribir_log("ERROR: Faltan datos obligatorios");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }

    // ========== OBTENER ASIGNACIÓN ==========
    $sqlAsignacion = "
        SELECT 
            a.id, a.gps_id, a.custodio_id, a.cliente, a.piloto, a.placa, 
            a.contenedor, a.origen, a.destino, a.tipo_asignacion, a.estado, 
            a.fecha_asignacion,
            g.imei, g.marca, g.modelo
        FROM asignaciones_gps a
        INNER JOIN gps_dispositivos g ON a.gps_id = g.id
        WHERE a.id = :asignacion_id AND a.estado = 'asignado'
    ";

    $stmt = $conn->prepare($sqlAsignacion);
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . implode(", ", $conn->errorInfo()));
    }

    $stmt->execute([':asignacion_id' => $asignacionId]);
    $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asignacion) {
        escribir_log("ERROR: Asignación no encontrada. ID: $asignacionId");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asignación no encontrada o ya ha sido retornada']);
        exit;
    }

    // ========== VALIDAR TIPO DE ASIGNACIÓN ==========
    // ✅ CORREGIDO: Ahora acepta AMBOS tipos (cliente y custodio)
    $tipoAsignacion = strtolower(trim($asignacion['tipo_asignacion']));
    
    if (!in_array($tipoAsignacion, ['cliente', 'custodio'])) {
        escribir_log("ERROR: Tipo de asignación inválido. Tipo: " . $asignacion['tipo_asignacion']);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de asignación inválido']);
        exit;
    }

    escribir_log("✓ Asignación encontrada: IMEI=" . $asignacion['imei'] . ", Cliente=" . $asignacion['cliente'] . ", Tipo=" . $tipoAsignacion);

    // ========== INICIAR TRANSACCIÓN ==========
    if (!$conn->beginTransaction()) {
        throw new Exception("Error iniciando transacción");
    }

    // ========== 1. ACTUALIZAR ASIGNACIÓN ==========
    $sqlUpdate = "
        UPDATE asignaciones_gps 
        SET 
            estado = 'retornado',
            fecha_retorno = :fecha_retorno,
            estado_gps_retorno = :estado_gps,
            observaciones_retorno = :observaciones
        WHERE id = :asignacion_id
    ";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        throw new Exception("Error preparando UPDATE asignacion");
    }

    $stmtUpdate->execute([
        ':fecha_retorno' => $fechaRetorno,
        ':estado_gps' => $estadoGPS,
        ':observaciones' => $observacionesRetorno,
        ':asignacion_id' => $asignacionId
    ]);

    escribir_log("✓ Asignación actualizada a 'retornado'");

    // ========== 2. ACTUALIZAR GPS ==========
    $gpsId = intval($asignacion['gps_id']);
    $sqlGPS = "UPDATE gps_dispositivos SET estado = 'disponible' WHERE id = :gps_id";
    
    $stmtGPS = $conn->prepare($sqlGPS);
    if (!$stmtGPS) {
        throw new Exception("Error preparando UPDATE GPS");
    }

    $stmtGPS->execute([':gps_id' => $gpsId]);
    escribir_log("✓ GPS actualizado a 'disponible'");

    // ========== 3. REGISTRAR EN HISTORIAL ==========
    $sqlHistorial = "
        INSERT INTO historial_retornos 
        (asignacion_id, gps_id, custodio_id, cliente, piloto, placa, contenedor, 
         origen, destino, fecha_retorno, estado_gps, observaciones, usuario_id, tipo_asignacion)
        VALUES (:asignacion_id, :gps_id, :custodio_id, :cliente, :piloto, :placa, :contenedor, 
                :origen, :destino, :fecha_retorno, :estado_gps, :observaciones, :usuario_id, :tipo_asignacion)
    ";

    $stmtHistorial = $conn->prepare($sqlHistorial);
    if (!$stmtHistorial) {
        throw new Exception("Error preparando INSERT historial");
    }

    $custodio_id = !empty($asignacion['custodio_id']) ? intval($asignacion['custodio_id']) : null;
    $usuario_id = intval($_SESSION['usuario_id']);
    
    $stmtHistorial->execute([
        ':asignacion_id' => intval($asignacion['id']),
        ':gps_id' => $gpsId,
        ':custodio_id' => $custodio_id,
        ':cliente' => $asignacion['cliente'],
        ':piloto' => !empty($asignacion['piloto']) ? $asignacion['piloto'] : null,
        ':placa' => !empty($asignacion['placa']) ? $asignacion['placa'] : null,
        ':contenedor' => !empty($asignacion['contenedor']) ? $asignacion['contenedor'] : null,
        ':origen' => $asignacion['origen'],
        ':destino' => $asignacion['destino'],
        ':fecha_retorno' => $fechaRetorno,
        ':estado_gps' => $estadoGPS,
        ':observaciones' => $observacionesRetorno,
        ':usuario_id' => $usuario_id,
        ':tipo_asignacion' => $tipoAsignacion
    ]);

    escribir_log("✓ Historial registrado");

    // ========== CONFIRMAR TRANSACCIÓN ==========
    if (!$conn->commit()) {
        throw new Exception("Error confirmando transacción");
    }

    escribir_log("✅ RETORNO EXITOSO - GPS: " . $asignacion['imei']);

    // ========== RESPUESTA EXITOSA ==========
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'GPS retornado correctamente',
        'datos' => [
            'imei' => $asignacion['imei'],
            'cliente' => $asignacion['cliente'],
            'piloto' => $asignacion['piloto'] ?? 'N/A',
            'placa' => $asignacion['placa'] ?? 'N/A',
            'fecha_retorno' => $fechaRetorno,
            'estado' => $estadoGPS
        ]
    ]);

} catch (Exception $e) {
    
    // ========== ROLLBACK EN CASO DE ERROR ==========
    if (isset($conn) && $conn) {
        try {
            $conn->rollBack();
        } catch (Exception $rollbackError) {
            escribir_log("⚠️ Error en rollback: " . $rollbackError->getMessage());
        }
    }
    
    $errorMsg = $e->getMessage();
    escribir_log("❌ ERROR: " . $errorMsg);
    
    // ========== RESPUESTA DE ERROR ==========
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar retorno: ' . $errorMsg
    ]);
}
?>