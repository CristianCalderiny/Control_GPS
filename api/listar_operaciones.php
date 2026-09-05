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
    $sql = "
        SELECT
            o.id, o.fecha_servicio, o.hora_servicio, o.cliente, o.origen, o.destino,
            o.conductor, o.telefono, o.placa, o.furgon,
            o.tipo_servicio, o.estado, o.observaciones, o.hora_inicio, o.hora_fin,
            o.evidencia_foto, o.novedad,
            p.id AS patrullero_id, p.nombre AS patrullero_nombre, p.telefono AS patrullero_telefono,
            o.gps_id AS gps_id_legacy, g0.imei AS gps_imei_legacy
        FROM operaciones o
        LEFT JOIN pilotos p ON o.patrullero_id = p.id
        LEFT JOIN gps_dispositivos g0 ON o.gps_id = g0.id
        ORDER BY o.fecha_servicio DESC, o.hora_servicio DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $operaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($operaciones) > 0) {
        // Cada fila puede traer custodio (persona), GPS, o ambos (Custodio + GPS)
        $sqlCust = "
            SELECT oc.operacion_id, oc.custodio_id, oc.gps_id, oc.rol,
                   c.nombre AS custodio_nombre,
                   c.zona AS custodio_zona,
                   g.imei AS gps_imei
            FROM operaciones_custodios oc
            LEFT JOIN custodios c ON oc.custodio_id = c.id
            LEFT JOIN gps_dispositivos g ON oc.gps_id = g.id
        ";
        $stmtCust = $conn->prepare($sqlCust);
        $stmtCust->execute();
        $filasRows = $stmtCust->fetchAll(PDO::FETCH_ASSOC);

        $filasPorOperacion = [];
        foreach ($filasRows as $row) {
            $filasPorOperacion[$row['operacion_id']][] = [
                'custodio_id'   => $row['custodio_id'],
                'nombre'        => $row['custodio_nombre'],
                'zona'          => $row['custodio_zona'],
                'gps_id'        => $row['gps_id'],
                'gps_imei'      => $row['gps_imei'],
                'tipo_servicio' => $row['rol'],
            ];
        }

        foreach ($operaciones as &$op) {
            $filas = $filasPorOperacion[$op['id']] ?? [];

            // Compatibilidad con operaciones antiguas guardadas antes del
            // cambio a GPS-por-fila: si no hay filas pero sí un GPS a nivel
            // de la operación, se muestra como una fila más.
            if (count($filas) === 0 && $op['gps_id_legacy']) {
                $filas[] = [
                    'custodio_id'   => null,
                    'nombre'        => null,
                    'zona'          => null,
                    'gps_id'        => $op['gps_id_legacy'],
                    'gps_imei'      => $op['gps_imei_legacy'],
                    'tipo_servicio' => $op['tipo_servicio'],
                ];
            }

            $op['custodios'] = $filas;
            // Mantener campos planos por compatibilidad con vistas antiguas
            $op['gps_id']   = $op['gps_id_legacy'];
            $op['gps_imei'] = $op['gps_imei_legacy'];
            unset($op['gps_id_legacy'], $op['gps_imei_legacy']);
        }
        unset($op);
    }

    echo json_encode($operaciones);
} catch (PDOException $e) {
    error_log("Error listando operaciones: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener operaciones']);
}