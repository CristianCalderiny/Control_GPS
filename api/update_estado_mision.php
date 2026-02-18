<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $mision_id = $_POST['mision_id'] ?? null;
    $nuevo_estado = $_POST['nuevo_estado'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';

    if (!$mision_id || !$nuevo_estado) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
        exit;
    }

    // Estados válidos
    $estados_validos = ['pendiente', 'posicionado', 'en_ruta', 'finalizada', 'completada', 'cancelada'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Estado no válido']);
        exit;
    }

    // Verificar que la misión exista
    $stmtCheck = $conn->prepare("SELECT id, estado FROM misiones WHERE id = ?");
    $stmtCheck->execute([$mision_id]);
    $mision = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$mision) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Misión no encontrada']);
        exit;
    }

    // Si el nuevo estado es 'finalizada' o 'completada', registrar fecha de fin
    $fecha_fin = ($nuevo_estado === 'finalizada' || $nuevo_estado === 'completada') ? 'NOW()' : 'NULL';

    $esFinalizada = in_array($nuevo_estado, ['finalizada', 'completada', 'cancelada']);

    $sql = "UPDATE misiones SET 
    estado = ?,
    fecha_fin = " . ($esFinalizada ? 'NOW()' : 'fecha_fin') . ",
    duracion_real = " . ($esFinalizada ? 'ROUND(TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW()) / 60, 2)' : 'duracion_real') . ",
    observaciones = CONCAT(COALESCE(observaciones, ''), '\n\n[Estado: " . $nuevo_estado . "] ', ?)
WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $nuevo_estado,
        $observaciones,
        $mision_id
    ]);

    

    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $nuevo_estado,
        $observaciones,
        $mision_id
    ]);

    if ($resultado) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'mision_id' => $mision_id,
            'nuevo_estado' => $nuevo_estado
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado']);
    }
} catch (PDOException $e) {
    error_log("Error en update_estado_mision.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en update_estado_mision.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
