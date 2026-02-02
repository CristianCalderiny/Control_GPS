<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $gpsId = $_POST['gpsId'] ?? '';
    $custodioId = $_POST['custodioId'] ?? '';
    $cliente = $_POST['cliente'] ?? '';
    $origen = $_POST['origen'] ?? '';
    $destino = $_POST['destino'] ?? '';
    $fechaAsignacion = $_POST['fechaAsignacion'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';

    if (empty($gpsId) || empty($custodioId) || empty($cliente) || empty($origen) || empty($destino) || empty($fechaAsignacion)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar asignación
    $sql = "INSERT INTO asignaciones_gps (gps_id, custodio_id, cliente, origen, destino, observaciones, fecha_asignacion, estado, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'asignado', NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$gpsId, $custodioId, $cliente, $origen, $destino, $observaciones, $fechaAsignacion]);

    // Actualizar estado del GPS
    $updateGps = "UPDATE gps_dispositivos SET estado = 'asignado', updated_at = NOW() WHERE id = ?";
    $stmtUpdate = $conn->prepare($updateGps);
    $stmtUpdate->execute([$gpsId]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'GPS asignado correctamente']);
} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>