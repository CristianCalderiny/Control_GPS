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
    // Estadísticas generales
    $sqlStats = "SELECT 
        COUNT(*) as total_misiones,
        SUM(CASE WHEN tipo_mision = 'corta' THEN 1 ELSE 0 END) as misiones_cortas,
        SUM(CASE WHEN tipo_mision = 'larga' THEN 1 ELSE 0 END) as misiones_largas,
        SUM(CASE WHEN estado IN ('posicionado', 'en_ruta') THEN 1 ELSE 0 END) as misiones_activas
    FROM misiones";

    $stmtStats = $conn->prepare($sqlStats);
    $stmtStats->execute();
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Estadísticas por custodio
    $sqlCustodios = "SELECT 
        c.id,
        c.nombre,
        COUNT(m.id) as total_misiones,
        SUM(CASE WHEN m.tipo_mision = 'corta' THEN 1 ELSE 0 END) as misiones_cortas,
        SUM(CASE WHEN m.tipo_mision = 'larga' THEN 1 ELSE 0 END) as misiones_largas,
        COALESCE(SUM(CASE 
            WHEN m.tipo_mision = 'corta' THEN 4
            WHEN m.tipo_mision = 'larga' THEN 8
            ELSE 0
        END), 0) as horas_totales
    FROM custodios c
    LEFT JOIN misiones m ON c.id = m.custodio_id
    GROUP BY c.id, c.nombre
    ORDER BY c.nombre";

    $stmtCustodios = $conn->prepare($sqlCustodios);
    $stmtCustodios->execute();
    $estadisticas_custodios = $stmtCustodios->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'stats' => $stats,
        'estadisticas_custodios' => $estadisticas_custodios
    ]);

} catch (PDOException $e) {
    error_log("Error en get_estadisticas_misiones.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>