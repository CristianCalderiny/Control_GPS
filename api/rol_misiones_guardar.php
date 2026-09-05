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

$id               = isset($_POST['id']) ? trim($_POST['id']) : '';
$custodio_id      = isset($_POST['custodio_id']) ? (int) $_POST['custodio_id'] : 0;
$custodio_nombre  = trim($_POST['custodio_nombre'] ?? '');
$zona             = trim($_POST['zona'] ?? 'Norte');
$semana_inicio    = trim($_POST['semana_inicio'] ?? '');
$semana_fin       = trim($_POST['semana_fin'] ?? '');
$numero_misiones  = isset($_POST['numero_misiones']) ? (int) $_POST['numero_misiones'] : 0;
$notas            = trim($_POST['notas'] ?? '');
$operaciones_rel  = trim($_POST['operaciones_relacionadas'] ?? '[]');

if (!$custodio_id || !$custodio_nombre || !$semana_inicio || !$semana_fin) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos: custodio y semana son obligatorios.']);
    exit;
}

// Validamos que operaciones_relacionadas sea JSON válido antes de guardarlo
json_decode($operaciones_rel);
if (json_last_error() !== JSON_ERROR_NONE) {
    $operaciones_rel = '[]';
}

$usuario_id     = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';

try {
    if ($id !== '') {
        // --- EDITAR registro existente ---
        $stmt = $conn->prepare(
            "UPDATE rol_misiones
             SET custodio_id = ?, custodio_nombre = ?, zona = ?, semana_inicio = ?, semana_fin = ?,
                 numero_misiones = ?, notas = ?, operaciones_relacionadas = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $custodio_id, $custodio_nombre, $zona, $semana_inicio, $semana_fin,
            $numero_misiones, $notas, $operaciones_rel, $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Registro actualizado', 'id' => $id]);
    } else {
        // --- CREAR registro nuevo ---
        $stmt = $conn->prepare(
            "INSERT INTO rol_misiones
                (custodio_id, custodio_nombre, zona, semana_inicio, semana_fin, numero_misiones,
                 notas, operaciones_relacionadas, creado_por, creado_por_nombre,
                 fecha_registro, fecha_vencimiento, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'vigente')"
        );
        $stmt->execute([
            $custodio_id, $custodio_nombre, $zona, $semana_inicio, $semana_fin,
            $numero_misiones, $notas, $operaciones_rel, $usuario_id, $usuario_nombre
        ]);

        echo json_encode(['success' => true, 'message' => 'Registro creado', 'id' => $conn->lastInsertId()]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('rol_misiones_guardar: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}