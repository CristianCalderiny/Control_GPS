<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$id = intval($_POST['id'] ?? 0);
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$email = trim($_POST['email'] ?? '');
$rol = trim($_POST['rol'] ?? 'Usuario');
$estado = trim($_POST['estado'] ?? 'activo');

if (!$id || empty($nombre_completo) || empty($usuario) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar que no sea auto-modificación indebida del rol del último admin
    if ($_SESSION['usuario_id'] == $id && $rol !== 'Administrador') {
        echo json_encode(['success' => false, 'error' => 'No puedes cambiar tu propio rol']);
        exit;
    }

    $updateSql = "UPDATE usuarios 
                  SET nombre_completo = :nombre_completo,
                      usuario = :usuario,
                      email = :email,
                      rol = :rol,
                      estado = :estado
                  WHERE id = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([
        ':nombre_completo' => $nombre_completo,
        ':usuario' => $usuario,
        ':email' => $email,
        ':rol' => $rol,
        ':estado' => $estado,
        ':id' => $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);

} catch (PDOException $e) {
    error_log("Error en update_usuario: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al actualizar usuario']);
}
?>