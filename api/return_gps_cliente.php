<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Log para debug
$log_file = __DIR__ . '/logs/return_gps_cliente.log';
@mkdir(dirname($log_file), 0755, true);

function escribir_log($mensaje) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $mensaje . "\n", FILE_APPEND);
}

escribir_log("=== INICIO RETORNO GPS CLIENTE ===");

if (!isset($_SESSION['usuario_id'])) {
    escribir_log("ERROR: No autenticado");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    $asignacionId = $_POST['asignacionId'] ?? null;
    $fechaRetorno = $_POST['fechaRetorno'] ?? null;
    $estadoGPS = $_POST['estadoGPS'] ?? null;
    $observacionesRetorno = $_POST['observacionesRetorno'] ?? null;

    escribir_log("Datos recibidos: asignacionId=$asignacionId, fecha=$fechaRetorno, estado=$estadoGPS");

    // VALIDACIÓN 1: DATOS OBLIGATORIOS
    if (!$asignacionId || !$fechaRetorno || !$estadoGPS) {
        escribir_log("ERROR: Faltan datos obligatorios");
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }

    // VALIDACIÓN 2: OBTENER ASIGNACIÓN
    $sqlAsignacion = "
        SELECT 
            a.id, a.gps_id, a.custodio_id, a.cliente, a.piloto, a.placa, 
            a.contenedor, a.origen, a.destino, a.tipo_asignacion, a.estado, 
            a.fecha_asignacion,
            g.imei, g.marca, g.modelo
        FROM asignaciones_gps a
        INNER JOIN gps_dispositivos g ON a.gps_id = g.id
        WHERE a.id = ? AND a.estado = 'asignado'
    ";

    $stmt = $conn->prepare($sqlAsignacion);
    if (!$stmt) {
        escribir_log("ERROR al preparar SQL: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Error en BD']);
        exit;
    }

    $stmt->bind_param("i", $asignacionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $asignacion = $result->fetch_assoc();

    if (!$asignacion) {
        escribir_log("ERROR: Asignación no encontrada o ya retornada. ID: $asignacionId");
        echo json_encode(['success' => false, 'message' => 'Asignación no encontrada o ya ha sido retornada']);
        exit;
    }

    // VALIDAR QUE SEA ASIGNACIÓN A CLIENTE
    if ($asignacion['tipo_asignacion'] !== 'cliente') {
        escribir_log("ERROR: No es asignación a cliente. Tipo: " . $asignacion['tipo_asignacion']);
        echo json_encode(['success' => false, 'message' => 'Esta asignación es a CUSTODIO, no a cliente']);
        exit;
    }

    escribir_log("Asignación encontrada: " . json_encode($asignacion));

    // INICIAR TRANSACCIÓN
    $conn->begin_transaction();

    try {
        // ACTUALIZAR ASIGNACIÓN
        $sqlUpdate = "
            UPDATE asignaciones_gps 
            SET 
                estado = 'retornado',
                fecha_retorno = ?,
                estado_gps_retorno = ?,
                observaciones_retorno = ?
            WHERE id = ?
        ";

        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            throw new Exception("Error preparando UPDATE: " . $conn->error);
        }

        $stmtUpdate->bind_param("sssi", $fechaRetorno, $estadoGPS, $observacionesRetorno, $asignacionId);
        
        if (!$stmtUpdate->execute()) {
            throw new Exception("Error ejecutando UPDATE: " . $stmtUpdate->error);
        }

        escribir_log("✓ Asignación actualizada a 'retornado'");

        // ACTUALIZAR GPS A DISPONIBLE
        $sqlGPS = "UPDATE gps_dispositivos SET estado = 'disponible' WHERE id = ?";
        $stmtGPS = $conn->prepare($sqlGPS);
        $stmtGPS->bind_param("i", $asignacion['gps_id']);
        
        if (!$stmtGPS->execute()) {
            throw new Exception("Error actualizando GPS: " . $stmtGPS->error);
        }

        escribir_log("✓ GPS actualizado a 'disponible'");

        // REGISTRAR EN HISTORIAL (CON CAMPOS DE CLIENTE)
        $sqlHistorial = "
            INSERT INTO historial_retornos 
            (asignacion_id, gps_id, custodio_id, cliente, piloto, placa, contenedor, 
             origen, destino, fecha_retorno, estado_gps, observaciones, usuario_id, tipo_asignacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cliente')
        ";

        $stmtHistorial = $conn->prepare($sqlHistorial);
        if (!$stmtHistorial) {
            throw new Exception("Error preparando historial: " . $conn->error);
        }

        $stmtHistorial->bind_param(
            "iissssssssssi",
            $asignacionId,
            $asignacion['gps_id'],
            $asignacion['custodio_id'],
            $asignacion['cliente'],
            $asignacion['piloto'],
            $asignacion['placa'],
            $asignacion['contenedor'],
            $asignacion['origen'],
            $asignacion['destino'],
            $fechaRetorno,
            $estadoGPS,
            $observacionesRetorno,
            $_SESSION['usuario_id']
        );

        if (!$stmtHistorial->execute()) {
            throw new Exception("Error insertando historial: " . $stmtHistorial->error);
        }

        escribir_log("✓ Historial registrado");

        // CONFIRMAR TRANSACCIÓN
        $conn->commit();

        escribir_log("✅ RETORNO EXITOSO");

        echo json_encode([
            'success' => true,
            'message' => 'GPS retornado correctamente',
            'datos' => [
                'imei' => $asignacion['imei'],
                'cliente' => $asignacion['cliente'],
                'piloto' => $asignacion['piloto'],
                'placa' => $asignacion['placa'],
                'fecha_retorno' => $fechaRetorno,
                'estado' => $estadoGPS
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        escribir_log("❌ ERROR EN TRANSACCIÓN: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} catch (Exception $e) {
    escribir_log("❌ ERROR GENERAL: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>