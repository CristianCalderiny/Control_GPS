<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    $sql = "
        SELECT 
            g.id,
            g.imei,
            g.marca,
            g.modelo,
            g.descripcion,
            g.estado,
            g.ubicacion,
            g.created_at,
            g.updated_at,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM asignaciones_gps 
                    WHERE gps_id = g.id AND estado = 'asignado'
                ) THEN (
                    SELECT c.nombre FROM asignaciones_gps ag
                    INNER JOIN custodios c ON ag.custodio_id = c.id
                    WHERE ag.gps_id = g.id AND ag.estado = 'asignado'
                    LIMIT 1
                )
                WHEN EXISTS (
                    SELECT 1 FROM asignaciones_cliente_gps 
                    WHERE gps_id = g.id AND estado = 'asignado'
                ) THEN (
                    SELECT CONCAT('Cliente: ', cliente) FROM asignaciones_cliente_gps
                    WHERE gps_id = g.id AND estado = 'asignado'
                    LIMIT 1
                )
                ELSE NULL
            END as asignado_a,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM asignaciones_gps 
                    WHERE gps_id = g.id AND estado = 'asignado'
                ) THEN 'custodio'
                WHEN EXISTS (
                    SELECT 1 FROM asignaciones_cliente_gps 
                    WHERE gps_id = g.id AND estado = 'asignado'
                ) THEN 'cliente'
                ELSE NULL
            END as tipo_asignacion
        FROM gps_dispositivos g
        ORDER BY g.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $gps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($gps);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en get_gps.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
