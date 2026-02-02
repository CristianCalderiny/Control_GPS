<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    // Validar datos requeridos
    if (empty($_POST['misionId']) || empty($_POST['fechaFin'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $misionId = $_POST['misionId'];
    $fechaFin = $_POST['fechaFin'];
    $observacionesFinalizacion = $_POST['observacionesFinalizacion'] ?? null;
    $kilometrosRecorridos = $_POST['kilometrosRecorridos'] ?? null;
    $combustibleUsado = $_POST['combustibleUsado'] ?? null;
    $calificacion = $_POST['calificacion'] ?? null;
    $comentariosCalificacion = $_POST['comentariosCalificacion'] ?? null;
    $incidentes = $_POST['incidentes'] ?? null;
    $usuarioId = $_SESSION['usuario_id'];

    // Verificar que la misión existe y no está completada
    $stmtCheck = $conn->prepare("SELECT id, fecha_inicio, estado, gps_id FROM misiones WHERE id = ?");
    $stmtCheck->execute([$misionId]);
    $mision = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$mision) {
        echo json_encode(['success' => false, 'message' => 'Misión no encontrada']);
        exit;
    }

    if ($mision['estado'] === 'completada') {
        echo json_encode(['success' => false, 'message' => 'La misión ya está completada']);
        exit;
    }

    // Calcular duración real
    $fechaInicio = new DateTime($mision['fecha_inicio']);
    $fechaFinDt = new DateTime($fechaFin);
    $interval = $fechaInicio->diff($fechaFinDt);
    $duracionReal = ($interval->days * 24) + $interval->h;

    // Actualizar misión
    $sql = "UPDATE misiones SET
        fecha_fin = ?,
        duracion_real = ?,
        estado = 'completada',
        observaciones_finalizacion = ?,
        kilometros_recorridos = ?,
        combustible_usado = ?,
        calificacion = ?,
        comentarios_calificacion = ?,
        incidentes = ?,
        updated_by = ?
    WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $fechaFin,
        $duracionReal,
        $observacionesFinalizacion,
        $kilometrosRecorridos,
        $combustibleUsado,
        $calificacion,
        $comentariosCalificacion,
        $incidentes,
        $usuarioId,
        $misionId
    ]);

    // Registrar en historial
    $sqlHistorial = "INSERT INTO misiones_historial (mision_id, estado_anterior, estado_nuevo, observaciones, usuario_id)
                     VALUES (?, ?, 'completada', 'Misión completada', ?)";
    $stmtHistorial = $conn->prepare($sqlHistorial);
    $stmtHistorial->execute([$misionId, $mision['estado'], $usuarioId]);

    // Si la misión tenía GPS asignado, liberarlo
    if ($mision['gps_id']) {
        $stmtGps = $conn->prepare("UPDATE gps_dispositivos SET estado = 'disponible' WHERE id = ?");
        $stmtGps->execute([$mision['gps_id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Misión completada exitosamente',
        'duracionReal' => $duracionReal
    ]);

} catch (PDOException $e) {
    error_log("Error en completar_mision.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al completar misión: ' . $e->getMessage()]);
}