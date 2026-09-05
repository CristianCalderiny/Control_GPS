<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

// Filtros opcionales: zona específica y rango de fechas.
$zonaFiltro = trim($_GET['zona'] ?? '');
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');

$zonasValidas = ['Norte', 'Centro', 'Sur', 'Motorizadas'];
if ($zonaFiltro !== '' && !in_array($zonaFiltro, $zonasValidas)) {
    echo json_encode(['success' => false, 'message' => 'Zona inválida']);
    exit;
}

$condiciones = [];
$params = [];

if ($zonaFiltro !== '') {
    $condiciones[] = "v.zona = ?";
    $params[] = $zonaFiltro;
}
if ($fechaDesde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
    $condiciones[] = "c.fecha >= ?";
    $params[] = $fechaDesde;
}
if ($fechaHasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
    $condiciones[] = "c.fecha <= ?";
    $params[] = $fechaHasta;
}

$whereSql = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

// Litros a galones estadounidenses (como en la hoja de cálculo de referencia).
const LITROS_POR_GALON = 3.785;

try {
    $sql = "
        SELECT
            c.id, c.fecha, c.km_inicial, c.km_final, c.km_recorridos,
            c.nivel_inicio, c.nivel_recarga, c.litros_cargados, c.monto_lempiras,
            c.km_por_litro, c.observaciones,
            v.placa, v.marca, v.modelo, v.zona,
            p.nombre AS patrullero_nombre
        FROM vehiculo_combustible c
        INNER JOIN vehiculos v ON v.id = c.vehiculo_id
        LEFT JOIN pilotos p ON p.id = c.patrullero_id
        $whereSql
        ORDER BY
            CASE v.zona
                WHEN 'Norte' THEN 1
                WHEN 'Centro' THEN 2
                WHEN 'Sur' THEN 3
                WHEN 'Motorizadas' THEN 4
                ELSE 5
            END,
            v.placa ASC,
            c.fecha DESC,
            c.creado_en DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupa en memoria respetando el orden Norte, Centro, Sur, Motorizadas, Sin zona.
    $ordenZonas = ['Norte', 'Centro', 'Sur', 'Motorizadas', 'Sin zona'];
    $grupos = [];
    foreach ($ordenZonas as $z) { $grupos[$z] = []; }

    foreach ($filas as $f) {
        $zona = ($f['zona'] !== null && $f['zona'] !== '') ? $f['zona'] : 'Sin zona';
        if (!isset($grupos[$zona])) { $grupos[$zona] = []; }

        $litros = (float)$f['litros_cargados'];
        $grupos[$zona][] = [
            'id' => (int)$f['id'],
            'fecha' => $f['fecha'],
            'placa' => $f['placa'],
            'marca' => $f['marca'],
            'modelo' => $f['modelo'],
            'patrullero_nombre' => $f['patrullero_nombre'],
            'km_inicial' => (int)$f['km_inicial'],
            'km_final' => (int)$f['km_final'],
            'km_recorridos' => (int)$f['km_recorridos'],
            'nivel_inicio' => $f['nivel_inicio'],
            'nivel_recarga' => $f['nivel_recarga'],
            'litros_cargados' => round($litros, 3),
            'galones_cargados' => round($litros / LITROS_POR_GALON, 2),
            'monto_lempiras' => $f['monto_lempiras'] !== null ? round((float)$f['monto_lempiras'], 2) : null,
            'km_por_litro' => $f['km_por_litro'] !== null ? round((float)$f['km_por_litro'], 3) : null,
            'observaciones' => $f['observaciones'],
        ];
    }

    $resultado = [];
    foreach ($grupos as $zona => $items) {
        if (empty($items)) continue; // no mandar pestañas vacías

        $totalLitros = 0; $totalGalones = 0; $totalLempiras = 0; $totalKm = 0;
        $sumaKmPorLitro = 0; $conRendimiento = 0;

        foreach ($items as $it) {
            $totalLitros += $it['litros_cargados'];
            $totalKm += $it['km_recorridos'];
            if ($it['monto_lempiras'] !== null) $totalLempiras += $it['monto_lempiras'];
            if ($it['km_por_litro'] !== null) { $sumaKmPorLitro += $it['km_por_litro']; $conRendimiento++; }
        }
        // Se calcula desde el total de litros (no sumando galones ya redondeados por fila)
        // para que litros y galones del total siempre cuadren entre sí.
        $totalGalones = $totalLitros / LITROS_POR_GALON;

        $resultado[] = [
            'zona' => $zona,
            'filas' => $items,
            'totales' => [
                'cargas' => count($items),
                'litros' => round($totalLitros, 2),
                'galones' => round($totalGalones, 2),
                'lempiras' => round($totalLempiras, 2),
                'km_recorridos' => $totalKm,
                'promedio_km_por_litro' => $conRendimiento > 0 ? round($sumaKmPorLitro / $conRendimiento, 3) : null,
            ],
        ];
    }

    echo json_encode(['success' => true, 'zonas' => $resultado]);
} catch (PDOException $e) {
    error_log("Error generando reporte de combustible por zona: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al generar el reporte']);
}