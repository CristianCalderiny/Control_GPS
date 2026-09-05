<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['patrullero_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $stmt = $conn->prepare("SELECT id, placa, marca, modelo, color, km_limite_aceite
                             FROM vehiculos WHERE patrullero_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['patrullero_id']]);
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehiculo) {
        echo json_encode(['asignado' => false]);
        exit;
    }

    // Última lectura de kilometraje de ese vehículo (la haya reportado quien la haya reportado)
    $stmtUltimo = $conn->prepare("SELECT kilometraje, fecha, creado_en
                                   FROM vehiculo_kilometrajes
                                   WHERE vehiculo_id = ? ORDER BY creado_en DESC LIMIT 1");
    $stmtUltimo->execute([$vehiculo['id']]);
    $ultimo = $stmtUltimo->fetch(PDO::FETCH_ASSOC);

    $alerta = null;
    if ($ultimo && $vehiculo['km_limite_aceite'] !== null) {
        $diferencia = (int)$ultimo['kilometraje'] - (int)$vehiculo['km_limite_aceite'];
        if ($diferencia >= 1500) {
            $alerta = ['nivel' => 3, 'tipo' => 'critico', 'mensaje' => "Crítico: $diferencia km pasado el límite de cambio de aceite"];
        } elseif ($diferencia >= 1000) {
            $alerta = ['nivel' => 2, 'tipo' => 'urgente', 'mensaje' => "Urgente: $diferencia km pasado el límite de cambio de aceite"];
        } elseif ($diferencia >= 0) {
            $alerta = ['nivel' => 1, 'tipo' => 'aviso', 'mensaje' => 'El vehículo llegó al límite de kilometraje para cambio de aceite'];
        }
    }

    echo json_encode([
        'asignado' => true,
        'vehiculo' => [
            'id' => $vehiculo['id'],
            'placa' => $vehiculo['placa'],
            'marca' => $vehiculo['marca'],
            'modelo' => $vehiculo['modelo'],
            'color' => $vehiculo['color'],
            'km_limite_aceite' => $vehiculo['km_limite_aceite'],
        ],
        'ultimo_kilometraje' => $ultimo ? [
            'kilometraje' => (int)$ultimo['kilometraje'],
            'fecha' => $ultimo['fecha'],
        ] : null,
        'alerta_aceite' => $alerta,
    ]);
} catch (PDOException $e) {
    error_log("Error obteniendo vehículo del piloto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Error al consultar el vehículo asignado']);
}