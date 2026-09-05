<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once '../conexion/db.php';
// $conn es una conexión PDO (definida en conexion/db.php).

try {
    $sql = "SELECT id, custodio_id, custodio_nombre, zona, semana_inicio, semana_fin,
                   numero_misiones, notas, operaciones_relacionadas,
                   creado_por, creado_por_nombre, fecha_registro, fecha_vencimiento, estado
            FROM rol_misiones
            ORDER BY fecha_registro DESC";

    $stmt = $conn->query($sql);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $registros = [];
    $ahora = new DateTime();

    foreach ($filas as $fila) {
        $vencimiento = new DateTime($fila['fecha_vencimiento']);
        $vencido = $ahora > $vencimiento;

        // El estado real siempre se calcula por fecha (no confiamos solo en la columna
        // `estado`, así evitamos inconsistencias si el reloj del servidor cambia).
        $fila['estado'] = $vencido ? 'vencido' : 'vigente';
        $fila['vencido'] = $vencido;

        $diff = $ahora->diff($vencimiento);
        $fila['dias_para_vencer'] = $vencido ? -$diff->days : $diff->days;

        $fila['numero_misiones'] = (int) $fila['numero_misiones'];
        $fila['operaciones_relacionadas'] = $fila['operaciones_relacionadas']
            ? json_decode($fila['operaciones_relacionadas'], true)
            : [];

        $registros[] = $fila;
    }

    echo json_encode($registros);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('rol_misiones_listar: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar los registros']);
}