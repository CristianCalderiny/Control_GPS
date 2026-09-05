<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$vehiculoId = $_GET['vehiculo_id'] ?? null;

try {
    if ($vehiculoId !== null) {
        // ---- Modo historial: todas las cargas de un vehículo específico ----
        if (!ctype_digit((string)$vehiculoId)) {
            echo json_encode(['success' => false, 'message' => 'Vehículo inválido']);
            exit;
        }
        $stmt = $conn->prepare("
            SELECT c.id, c.fecha, c.km_inicial, c.km_final, c.km_recorridos,
                   c.nivel_inicio, c.nivel_recarga, c.litros_cargados, c.monto_lempiras,
                   c.km_por_litro, c.observaciones, c.creado_en,
                   p.nombre AS patrullero_nombre
            FROM vehiculo_combustible c
            LEFT JOIN pilotos p ON p.id = c.patrullero_id
            WHERE c.vehiculo_id = ?
            ORDER BY c.creado_en DESC
        ");
        $stmt->execute([$vehiculoId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ---- Modo dashboard/ranking: un resumen por vehículo, peor rendimiento primero ----
    $sql = "
        SELECT
            v.id AS vehiculo_id, v.placa, v.marca, v.modelo,
            p.nombre AS patrullero_nombre,
            COUNT(c.id) AS total_cargas,
            SUM(c.litros_cargados) AS total_litros,
            SUM(c.monto_lempiras) AS total_lempiras,
            AVG(c.km_por_litro) AS promedio_km_por_litro,
            MAX(c.fecha) AS fecha_ultima_carga
        FROM vehiculos v
        LEFT JOIN pilotos p ON p.id = v.patrullero_id
        INNER JOIN vehiculo_combustible c ON c.vehiculo_id = v.id
        GROUP BY v.id, v.placa, v.marca, v.modelo, p.nombre
    ";
    $stmt = $conn->query($sql);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Última carga de cada vehículo, para comparar contra su propio promedio.
    $stmtUltimas = $conn->query("
        SELECT c1.vehiculo_id, c1.km_por_litro AS ultimo_km_por_litro, c1.fecha AS ultima_fecha
        FROM vehiculo_combustible c1
        INNER JOIN (
            SELECT vehiculo_id, MAX(creado_en) AS max_creado
            FROM vehiculo_combustible
            GROUP BY vehiculo_id
        ) ult ON ult.vehiculo_id = c1.vehiculo_id AND ult.max_creado = c1.creado_en
    ");
    $ultimasPorVehiculo = [];
    foreach ($stmtUltimas->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $ultimasPorVehiculo[$u['vehiculo_id']] = $u;
    }

    $resultado = [];
    foreach ($filas as $f) {
        $vehId = (int)$f['vehiculo_id'];
        $ultima = $ultimasPorVehiculo[$vehId] ?? null;
        $promedio = $f['promedio_km_por_litro'] !== null ? round((float)$f['promedio_km_por_litro'], 3) : null;
        $ultimoKmPorLitro = $ultima ? round((float)$ultima['ultimo_km_por_litro'], 3) : null;

        $variacionPct = null;
        $nivelAlerta = 0;
        $tipoAlerta = null;
        if ($promedio !== null && $promedio > 0 && $ultimoKmPorLitro !== null && (int)$f['total_cargas'] >= 4) {
            // Promedio excluyendo la última carga, para no comparar el dato contra sí mismo.
            $promedioSinUltima = ((float)$f['promedio_km_por_litro'] * (int)$f['total_cargas'] - $ultimoKmPorLitro) / max(1, (int)$f['total_cargas'] - 1);
            if ($promedioSinUltima > 0) {
                $variacionPct = round((($promedioSinUltima - $ultimoKmPorLitro) / $promedioSinUltima) * 100, 1);
                if ($variacionPct >= 40) { $nivelAlerta = 3; $tipoAlerta = 'critico'; }
                elseif ($variacionPct >= 25) { $nivelAlerta = 2; $tipoAlerta = 'urgente'; }
                elseif ($variacionPct >= 15) { $nivelAlerta = 1; $tipoAlerta = 'aviso'; }
            }
        }

        $resultado[] = [
            'vehiculo_id' => $vehId,
            'placa' => $f['placa'],
            'marca' => $f['marca'],
            'modelo' => $f['modelo'],
            'patrullero_nombre' => $f['patrullero_nombre'],
            'total_cargas' => (int)$f['total_cargas'],
            'total_litros' => round((float)$f['total_litros'], 2),
            'total_lempiras' => $f['total_lempiras'] !== null ? round((float)$f['total_lempiras'], 2) : null,
            'promedio_km_por_litro' => $promedio,
            'ultimo_km_por_litro' => $ultimoKmPorLitro,
            'fecha_ultima_carga' => $f['fecha_ultima_carga'],
            'variacion_pct' => $variacionPct,
            'nivel' => $nivelAlerta,
            'tipo' => $tipoAlerta,
        ];
    }

    // Peor rendimiento primero: los que están derrochando más combustible arriba.
    usort($resultado, function ($a, $b) {
        if ($a['nivel'] !== $b['nivel']) return $b['nivel'] <=> $a['nivel'];
        return ($b['variacion_pct'] ?? -999) <=> ($a['variacion_pct'] ?? -999);
    });

    echo json_encode($resultado);
} catch (PDOException $e) {
    error_log("Error listando combustible: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener los datos de combustible']);
}