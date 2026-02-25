<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../conexion/db.php';

try {
    $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fechaFin    = $_GET['fecha_fin']    ?? date('Y-m-t');

    // Stats generales
    $stmtStats = $conn->prepare("
        SELECT
            COUNT(*) as total_misiones,
            SUM(tipo_mision = 'corta') as misiones_cortas,
            SUM(tipo_mision = 'larga') as misiones_largas,
            SUM(estado NOT IN ('completada','cancelada','finalizada')) as misiones_activas
        FROM misiones
        WHERE DATE(fecha_inicio) BETWEEN ? AND ?
    ");
    $stmtStats->execute([$fechaInicio, $fechaFin]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Misiones por custodio y día de semana
    $stmtDias = $conn->prepare("
        SELECT
            c.id,
            c.nombre,
            DAYOFWEEK(m.fecha_inicio) as dia_num,
            COUNT(*) as total_dia,
            SUM(m.tipo_mision = 'corta') as cortas_dia,
            SUM(m.tipo_mision = 'larga') as largas_dia,
            GROUP_CONCAT(
                CASE 
                    WHEN m.tipo_mision = 'corta' THEN 'MC'
                    ELSE 'ML'
                END
                ORDER BY m.fecha_inicio
                SEPARATOR '-'
            ) as tipos_dia,
            GROUP_CONCAT(
                NULLIF(m.observaciones, '')
                ORDER BY m.fecha_inicio
                SEPARATOR ' | '
            ) as obs_dia
        FROM custodios c
        LEFT JOIN misiones m ON m.custodio_id = c.id
            AND DATE(m.fecha_inicio) BETWEEN ? AND ?
        WHERE c.estado = 'activo'
        GROUP BY c.id, c.nombre, DAYOFWEEK(m.fecha_inicio)
        ORDER BY c.nombre, dia_num
    ");
    $stmtDias->execute([$fechaInicio, $fechaFin]);
    $filas = $stmtDias->fetchAll(PDO::FETCH_ASSOC);

    // Armar estructura por custodio
    $custodios = [];
    foreach ($filas as $f) {
        $id = $f['id'];
        if (!isset($custodios[$id])) {
            $custodios[$id] = [
                'id'     => $id,
                'nombre' => $f['nombre'],
                'total'  => 0,
                'dias'   => [] // 1=dom,2=lun,...,7=sab → reordenamos
            ];
        }
        if ($f['dia_num'] !== null) {
            $custodios[$id]['dias'][$f['dia_num']] = [
                'total' => (int)$f['total_dia'],
                'tipos' => $f['tipos_dia'] ?? '',
                'obs'   => $f['obs_dia']   ?? ''
            ];
            $custodios[$id]['total'] += (int)$f['total_dia'];
        }
    }

    echo json_encode([
        'success'    => true,
        'stats'      => $stats,
        'por_dia'    => array_values($custodios),
        'fecha_inicio' => $fechaInicio,
        'fecha_fin'    => $fechaFin
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}