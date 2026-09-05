<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    // Para cada vehículo con límite de aceite configurado, se toma su última lectura
    // de kilometraje y se compara contra el límite. Se calcula al vuelo, sin tabla
    // de notificaciones aparte.
    $sql = "
        SELECT
            v.id, v.placa, v.marca, v.modelo, v.km_limite_aceite,
            p.id AS patrullero_id, p.nombre AS patrullero_nombre,
            k.kilometraje AS ultimo_kilometraje, k.fecha AS ultima_fecha
        FROM vehiculos v
        LEFT JOIN pilotos p ON p.id = v.patrullero_id
        LEFT JOIN vehiculo_kilometrajes k ON k.id = (
            SELECT k2.id FROM vehiculo_kilometrajes k2
            WHERE k2.vehiculo_id = v.id
            ORDER BY k2.creado_en DESC LIMIT 1
        )
        WHERE v.km_limite_aceite IS NOT NULL
    ";
    $stmt = $conn->query($sql);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alertas = [];
    foreach ($filas as $v) {
        if ($v['ultimo_kilometraje'] === null) continue;

        $diferencia = (int)$v['ultimo_kilometraje'] - (int)$v['km_limite_aceite'];

        $UMBRAL_PROXIMO = 2000; // km antes del límite para empezar a avisar
        if ($diferencia < -$UMBRAL_PROXIMO) continue; // aún falta demasiado, sin alerta

        if ($diferencia >= 1500) {
            $nivel = 3; $tipo = 'critico';
        } elseif ($diferencia >= 1000) {
            $nivel = 2; $tipo = 'urgente';
        } elseif ($diferencia >= 0) {
            $nivel = 1; $tipo = 'aviso';
        } else {
            $nivel = 0; $tipo = 'proximo';
        }

        $alertas[] = [
            'vehiculo_id' => (int)$v['id'],
            'placa' => $v['placa'],
            'marca' => $v['marca'],
            'modelo' => $v['modelo'],
            'patrullero_id' => $v['patrullero_id'] ? (int)$v['patrullero_id'] : null,
            'patrullero_nombre' => $v['patrullero_nombre'],
            'km_limite_aceite' => (int)$v['km_limite_aceite'],
            'ultimo_kilometraje' => (int)$v['ultimo_kilometraje'],
            'ultima_fecha' => $v['ultima_fecha'],
            'km_pasado_limite' => $diferencia,
            'nivel' => $nivel,
            'tipo' => $tipo,
        ];
    }

    // Los más críticos primero
    usort($alertas, fn($a, $b) => $b['nivel'] <=> $a['nivel'] ?: $b['km_pasado_limite'] <=> $a['km_pasado_limite']);

    echo json_encode($alertas);
} catch (PDOException $e) {
    error_log("Error listando alertas de aceite: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Error al consultar las alertas']);
}