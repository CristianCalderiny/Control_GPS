<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Accesible tanto por el panel admin como por la app de pilotos
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['patrullero_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$vehiculoId = $_GET['vehiculo_id'] ?? '';

try {
    if ($vehiculoId !== '' && ctype_digit((string)$vehiculoId)) {
        $stmt = $conn->prepare("
            SELECT k.id, k.vehiculo_id, k.patrullero_id, k.kilometraje, k.fecha, k.observaciones, k.creado_en,
                   p.nombre AS patrullero_nombre
            FROM vehiculo_kilometrajes k
            LEFT JOIN pilotos p ON p.id = k.patrullero_id
            WHERE k.vehiculo_id = ?
            ORDER BY k.fecha DESC, k.creado_en DESC
        ");
        $stmt->execute([$vehiculoId]);
    } else {
        // Sin filtro: último kilometraje registrado por cada vehículo
        $stmt = $conn->prepare("
            SELECT k.id, k.vehiculo_id, k.patrullero_id, k.kilometraje, k.fecha, k.observaciones, k.creado_en,
                   p.nombre AS patrullero_nombre
            FROM vehiculo_kilometrajes k
            LEFT JOIN pilotos p ON p.id = k.patrullero_id
            INNER JOIN (
                SELECT vehiculo_id, MAX(creado_en) AS ultimo
                FROM vehiculo_kilometrajes
                GROUP BY vehiculo_id
            ) u ON u.vehiculo_id = k.vehiculo_id AND u.ultimo = k.creado_en
        ");
        $stmt->execute();
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log("Error listando kilometraje: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener el kilometraje']);
}