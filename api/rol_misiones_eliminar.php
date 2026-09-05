<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$usuario_rol = $_SESSION['rol'] ?? 'Usuario';
$es_admin = in_array(strtolower(trim($usuario_rol)), ['admin', 'administrador']);

if (!$es_admin) {
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede eliminar registros.']);
    exit;
}

require_once '../conexion/db.php';
// $conn es una conexión PDO (definida en conexion/db.php).

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificamos en el servidor que el registro realmente ya venció.
    // Así, aunque alguien manipule el frontend, no se puede borrar un registro
    // vigente antes de las 2 semanas.
    $stmt = $conn->prepare("SELECT fecha_vencimiento FROM rol_misiones WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        echo json_encode(['success' => false, 'message' => 'El registro no existe (puede que ya haya sido eliminado).']);
        exit;
    }

    $vencimiento = new DateTime($fila['fecha_vencimiento']);
    $ahora = new DateTime();

    if ($ahora < $vencimiento) {
        $faltan = $ahora->diff($vencimiento)->days;
        echo json_encode([
            'success' => false,
            'message' => "Este registro todavía no vence. Faltan {$faltan} día(s) (a las 2 semanas de creado)."
        ]);
        exit;
    }

    $stmtDel = $conn->prepare("DELETE FROM rol_misiones WHERE id = ?");
    $stmtDel->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('rol_misiones_eliminar: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}