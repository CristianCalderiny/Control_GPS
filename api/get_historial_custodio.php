<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $custodio_id = $_GET['custodio_id'] ?? null;

    if (!$custodio_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta custodio_id']);
        exit;
    }

    // Obtener datos del custodio
    $sqlCustodio = "SELECT * FROM custodios WHERE id = ?";
    $stmtCustodio = $conn->prepare($sqlCustodio);
    $stmtCustodio->execute([$custodio_id]);
    $custodio = $stmtCustodio->fetch(PDO::FETCH_ASSOC);

    if (!$custodio) {
        http_response_code(404);
        echo json_encode(['error' => 'Custodio no encontrado']);
        exit;
    }

    // Obtener estadísticas
    $sqlStats = "SELECT 
        COUNT(*) as total_misiones,
        SUM(CASE WHEN tipo_mision = 'corta' THEN 1 ELSE 0 END) as misiones_cortas,
        SUM(CASE WHEN tipo_mision = 'larga' THEN 1 ELSE 0 END) as misiones_largas,
        COALESCE(SUM(CASE 
            WHEN tipo_mision = 'corta' THEN 4
            WHEN tipo_mision = 'larga' THEN 8
            ELSE 0
        END), 0) as horas_totales
    FROM misiones WHERE custodio_id = ?";

    $stmtStats = $conn->prepare($sqlStats);
    $stmtStats->execute([$custodio_id]);
    $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Obtener misiones del custodio
    $sqlMisiones = "SELECT 
        m.id,
        m.tipo_mision,
        m.fecha_inicio,
        m.fecha_fin,
        m.observaciones as descripcion,
        m.estado,
        CASE WHEN m.tipo_mision = 'corta' THEN 4 ELSE 8 END as duracion_estimada,
        NULL as duracion_real,
        CONCAT('Misión ', UPPER(SUBSTRING(m.tipo_mision, 1, 1)), ' #', m.id) as nombre_mision,
        CONCAT('MIS-', UPPER(SUBSTRING(m.tipo_mision, 1, 1)), '-', m.id) as codigo_mision
    FROM misiones m
    WHERE m.custodio_id = ?
    ORDER BY m.fecha_inicio DESC";

    $stmtMisiones = $conn->prepare($sqlMisiones);
    $stmtMisiones->execute([$custodio_id]);
    $misiones = $stmtMisiones->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'custodio' => $custodio,
        'estadisticas' => $estadisticas ?: [
            'total_misiones' => 0,
            'misiones_cortas' => 0,
            'misiones_largas' => 0,
            'horas_totales' => 0
        ],
        'misiones' => $misiones ?: []
    ]);

} catch (PDOException $e) {
    error_log("Error en get_historial_custodio.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en get_historial_custodio.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del sistema']);
}
?>