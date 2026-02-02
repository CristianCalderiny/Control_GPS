<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    // Usar la vista para obtener información completa
    $sql = "SELECT * FROM vista_reporte_misiones ORDER BY fecha_inicio DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $misiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($misiones);

} catch (PDOException $e) {
    error_log("Error en get_misiones.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener misiones: ' . $e->getMessage()]);
}