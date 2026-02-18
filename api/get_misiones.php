<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

require_once '../conexion/db.php';

try {
    $sql = "SELECT 
        m.id,
        m.custodio_id,
        m.gps_id,
        m.tipo_mision,
        m.fecha_inicio,
        m.fecha_fin,
        m.observaciones,
        m.estado,
        m.duracion_real,
        c.nombre as custodio_nombre,
        COALESCE(g.imei, '') as gps_imei
    FROM misiones m
    LEFT JOIN custodios c ON m.custodio_id = c.id
    LEFT JOIN gps_dispositivos g ON m.gps_id = g.id
    ORDER BY m.fecha_inicio DESC";



    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $misiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convertir tipo_mision a tipo_mision_id para compatibilidad
    foreach ($misiones as &$mision) {
        $tipo = $mision['tipo_mision'];
        $mision['tipo_mision_id'] = ($tipo === 'corta') ? 1 : 2;
        $mision['nombre_mision'] = 'Misión ' . ucfirst($tipo) . ' #' . $mision['id'];
        $mision['codigo_mision'] = 'MIS-' . strtoupper($tipo[0]) . '-' . $mision['id'];
        $mision['descripcion'] = $mision['observaciones'];
        $mision['duracion_estimada'] = ($tipo === 'corta') ? 4 : 8;
        if (!empty($mision['duracion_real']) && $mision['duracion_real'] > 0) {
            $mision['duracion_real'] = round($mision['duracion_real'], 2);
        } elseif (!empty($mision['fecha_fin'])) {
            $inicio = new DateTime($mision['fecha_inicio']);
            $fin = new DateTime($mision['fecha_fin']);
            $mision['duracion_real'] = round(($fin->getTimestamp() - $inicio->getTimestamp()) / 3600, 2);
        } else {
            $mision['duracion_real'] = null;
        }
        $mision['prioridad'] = 'media';
        $mision['observaciones_finalizacion'] = null;
    }

    echo json_encode($misiones);
    exit;
} catch (PDOException $e) {
    error_log("Error en get_misiones.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
    exit;
} catch (Exception $e) {
    error_log("Error en get_misiones.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
    exit;
}
