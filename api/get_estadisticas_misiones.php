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
    // Usar la vista de estadísticas
    $sql = "SELECT * FROM vista_estadisticas_custodios ORDER BY total_misiones DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($estadisticas);

} catch (PDOException $e) {
    error_log("Error en get_estadisticas_misiones.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener estadísticas: ' . $e->getMessage()]);
}






