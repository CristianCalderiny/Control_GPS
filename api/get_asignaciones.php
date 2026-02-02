<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $sql = "SELECT 
                a.id,
                a.gps_id,
                a.custodio_id,
                a.cliente,
                a.origen,
                a.destino,
                a.observaciones,
                a.fecha_asignacion,
                a.fecha_retorno,
                a.estado,
                g.imei,
                g.marca,
                g.modelo,
                c.nombre as custodio_nombre,
                c.telefono as custodio_telefono
            FROM asignaciones_gps a
            INNER JOIN gps_dispositivos g ON a.gps_id = g.id
            INNER JOIN custodios c ON a.custodio_id = c.id
            ORDER BY a.fecha_asignacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($asignaciones);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>