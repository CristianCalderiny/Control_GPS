<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

// Permite consultar otro día vía ?fecha=YYYY-MM-DD, por defecto hoy
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

try {
    // Todos los pilotos que tienen vehículo asignado, con si reportaron o no ese día
    $sql = "
        SELECT
            p.id AS patrullero_id, p.nombre AS patrullero_nombre,
            v.id AS vehiculo_id, v.placa,
            k.kilometraje, k.creado_en AS hora_reporte
        FROM pilotos p
        INNER JOIN vehiculos v ON v.patrullero_id = p.id
        LEFT JOIN vehiculo_kilometrajes k
            ON k.patrullero_id = p.id AND k.fecha = ?
        ORDER BY (k.kilometraje IS NULL) DESC, p.nombre ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reporto = [];
    $noReporto = [];
    foreach ($filas as $f) {
        $item = [
            'patrullero_id' => (int)$f['patrullero_id'],
            'patrullero_nombre' => $f['patrullero_nombre'],
            'vehiculo_id' => (int)$f['vehiculo_id'],
            'placa' => $f['placa'],
        ];
        if ($f['kilometraje'] !== null) {
            $item['kilometraje'] = (int)$f['kilometraje'];
            $item['hora_reporte'] = $f['hora_reporte'];
            $reporto[] = $item;
        } else {
            $noReporto[] = $item;
        }
    }

    echo json_encode([
        'fecha' => $fecha,
        'total_pilotos_con_vehiculo' => count($filas),
        'reportaron' => $reporto,
        'no_reportaron' => $noReporto,
    ]);
} catch (PDOException $e) {
    error_log("Error en reporte diario de kilometraje: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Error al generar el reporte diario']);
}