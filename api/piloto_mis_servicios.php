<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['patrullero_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada, vuelve a ingresar']);
    exit;
}

require_once '../conexion/db.php';

$patrullero_id = $_SESSION['patrullero_id'];

try {
    $stmt = $conn->prepare("
        SELECT id, fecha_servicio, hora_servicio, cliente, origen, destino,
               tipo_servicio, estado, observaciones, conductor, telefono, furgon, placa
        FROM operaciones
        WHERE patrullero_id = ?
          AND (
                estado NOT IN ('cancelado', 'finalizado')
                OR fecha_servicio >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
              )
        ORDER BY fecha_servicio ASC, hora_servicio ASC
    ");
    $stmt->execute([$patrullero_id]);
    $operaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traemos el detalle de custodios + GPS de cada operación (misma lógica
    // que usa el panel admin en listar_operaciones.php), para que el piloto
    // vea el nombre del custodio y el IMEI del GPS de cada fila del servicio.
    if ($operaciones) {
        $ids = array_column($operaciones, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtC = $conn->prepare("
            SELECT oc.operacion_id, oc.custodio_id, c.nombre AS custodio_nombre,
                   oc.gps_id, g.imei AS gps_imei, oc.rol AS tipo_servicio
            FROM operaciones_custodios oc
            LEFT JOIN custodios c ON c.id = oc.custodio_id
            LEFT JOIN gps_dispositivos g ON g.id = oc.gps_id
            WHERE oc.operacion_id IN ($placeholders)
        ");
        $stmtC->execute($ids);

        $porOperacion = [];
        foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porOperacion[$fila['operacion_id']][] = [
                'custodio_id'   => $fila['custodio_id'],
                'nombre'        => $fila['custodio_nombre'],
                'gps_id'        => $fila['gps_id'],
                'gps_imei'      => $fila['gps_imei'],
                'tipo_servicio' => $fila['tipo_servicio'],
            ];
        }

        foreach ($operaciones as &$op) {
            $op['custodios'] = $porOperacion[$op['id']] ?? [];
        }
        unset($op);
    }

    echo json_encode($operaciones);
} catch (PDOException $e) {
    error_log("Error listando servicios del piloto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener tus servicios']);
}