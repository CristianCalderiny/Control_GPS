<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $id = $_POST['id'] ?? null;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        exit;
    }

    // ✅ Verificar si tiene asignaciones ACTIVAS en AMBAS tablas
    $checkCustodio = $conn->prepare("SELECT COUNT(*) FROM asignaciones_gps WHERE gps_id = ? AND estado = 'asignado'");
    $checkCustodio->execute([$id]);

    $checkCliente = $conn->prepare("SELECT COUNT(*) FROM asignaciones_cliente_gps WHERE gps_id = ? AND estado = 'asignado'");
    $checkCliente->execute([$id]);

    if ($checkCustodio->fetchColumn() > 0 || $checkCliente->fetchColumn() > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'No se puede eliminar: este GPS tiene asignaciones activas. Primero registra el retorno.'
        ]);
        exit;
    }

    // ✅ Limpiar historial de AMBAS tablas antes de eliminar el GPS
    $conn->prepare("DELETE FROM asignaciones_cliente_gps WHERE gps_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM asignaciones_gps WHERE gps_id = ?")->execute([$id]);

    // ✅ Ahora sí eliminar el GPS
    $stmt = $conn->prepare("DELETE FROM gps_dispositivos WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'GPS eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'GPS no encontrado']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>