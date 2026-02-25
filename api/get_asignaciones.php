<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../conexion/db.php';

try {
    // OBTENER ASIGNACIONES A CUSTODIOS
    $sqlCustodios = "
        SELECT 
            ag.id,
            ag.gps_id,
            ag.custodio_id,
            ag.cliente,
            ag.origen,
            ag.destino,
            ag.observaciones,
            ag.fecha_asignacion,
            ag.estado,
            gd.imei,
            gd.marca,
            gd.modelo,
            c.nombre as custodio_nombre,
            c.telefono as custodio_telefono,
            DATEDIFF(NOW(), ag.fecha_asignacion) as dias_asignado,
            'custodio' as tipo_asignacion
        FROM asignaciones_gps ag
        INNER JOIN gps_dispositivos gd ON ag.gps_id = gd.id
        INNER JOIN custodios c ON ag.custodio_id = c.id
        ORDER BY ag.fecha_asignacion DESC
    ";

    $stmtCustodios = $conn->prepare($sqlCustodios);
    $stmtCustodios->execute();
    $asignacionesCustodios = $stmtCustodios->fetchAll(PDO::FETCH_ASSOC);

    // OBTENER ASIGNACIONES A CLIENTES
    $sqlClientes = "
        SELECT 
    acg.id,
    acg.gps_id,
    NULL as custodio_id,
    acg.cliente,
    acg.origen,
    acg.destino,
    acg.observaciones,
    acg.fecha_asignacion,
    acg.estado,
    gd.imei,
    gd.marca,
    gd.modelo,
    CONCAT('Cliente: ', acg.cliente) as custodio_nombre,
    acg.telefono as custodio_telefono,   -- ✅ ANTES era acg.piloto
    DATEDIFF(NOW(), acg.fecha_asignacion) as dias_asignado,
    'cliente' as tipo_asignacion,
    acg.piloto,
    acg.placa,
    acg.contenedor,
    acg.telefono as telefono             -- ✅ NUEVO: para el modal de Ver
FROM asignaciones_cliente_gps acg
INNER JOIN gps_dispositivos gd ON acg.gps_id = gd.id
ORDER BY acg.fecha_asignacion DESC
    ";

    $stmtClientes = $conn->prepare($sqlClientes);
    $stmtClientes->execute();
    $asignacionesClientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

    // COMBINAR AMBAS LISTAS
    $asignacionesTodas = array_merge($asignacionesCustodios, $asignacionesClientes);
    
    // ORDENAR POR FECHA DESCENDENTE
    usort($asignacionesTodas, function($a, $b) {
        return strtotime($b['fecha_asignacion']) - strtotime($a['fecha_asignacion']);
    });

    echo json_encode($asignacionesTodas);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en get_asignaciones.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}




