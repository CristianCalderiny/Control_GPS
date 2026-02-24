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
            ac.id,
            ac.gps_id,
            ac.cliente,
            ac.piloto,
            ac.placa,
            ac.contenedor,
            ac.origen,
            ac.destino,
            ac.observaciones,
            ac.fecha_asignacion,
            ac.fecha_retorno,
            ac.estado_retorno,
            ac.estado,
            gd.imei,
            gd.marca,
            gd.modelo,
            DATEDIFF(NOW(), ac.fecha_asignacion) as dias_asignado
        FROM asignaciones_cliente_gps ac
        INNER JOIN gps_dispositivos gd ON ac.gps_id = gd.id
        ORDER BY ac.fecha_asignacion DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($asignaciones);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}