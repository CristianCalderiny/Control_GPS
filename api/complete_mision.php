<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once 'conexion/db.php';

try {
    $mision_id = $_POST['mision_id'] ?? null;
    $observaciones_cierre = $_POST['observaciones_cierre'] ?? null;

    if (!$mision_id) {
        echo json_encode(['success' => false, 'message' => 'ID de misión requerido']);
        exit;
    }

    // Obtener la misión actual
    $stmtMision = $conn->prepare("
        SELECT 
            m.id,
            m.estado,
            m.fecha_inicio,
            m.custodio_id,
            m.tipo_mision_id,
            tm.nombre as tipo_nombre
        FROM misiones m
        LEFT JOIN tipos_misiones tm ON m.tipo_mision_id = tm.id
        WHERE m.id = ?
    ");
    $stmtMision->execute([$mision_id]);
    $mision = $stmtMision->fetch(PDO::FETCH_ASSOC);

    if (!$mision) {
        echo json_encode(['success' => false, 'message' => 'Misión no encontrada']);
        exit;
    }

    if ($mision['estado'] === 'completada') {
        echo json_encode(['success' => false, 'message' => 'La misión ya está completada']);
        exit;
    }

    // Calcular duración real en horas
    $fechaInicio = new DateTime($mision['fecha_inicio']);
    $ahora = new DateTime();
    $duracion_real = ceil(($ahora->getTimestamp() - $fechaInicio->getTimestamp()) / 3600);

    // Actualizar misión
    $sqlUpdate = "
        UPDATE misiones
        SET 
            estado = 'completada',
            fecha_fin = NOW(),
            duracion_real = ?,
            observaciones_finalizacion = ?,
            updated_by = ?
        WHERE id = ?
    ";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $result = $stmtUpdate->execute([
        $duracion_real,
        $observaciones_cierre,
        $_SESSION['usuario_id'],
        $mision_id
    ]);

    if ($result) {
        // Registrar en historial
        $sqlHistorial = "
            INSERT INTO misiones_historial (
                mision_id,
                estado_anterior,
                estado_nuevo,
                observaciones,
                usuario_id
            ) VALUES (?, 'en_curso', 'completada', ?, ?)
        ";
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $mision_id,
            $observaciones_cierre,
            $_SESSION['usuario_id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Misión completada exitosamente',
            'duracion_real' => $duracion_real,
            'mision_id' => $mision_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al completar la misión']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>