<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$rol = trim($_POST['rol'] ?? 'Usuario');

// Validaciones
if (empty($nombre_completo) || empty($usuario) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Todos los campos son requeridos']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email inválido']);
    exit;
}

if (!in_array($rol, ['Usuario', 'Administrador'])) {
    echo json_encode(['success' => false, 'error' => 'Rol inválido']);
    exit;
}

try {
    // Verificar duplicados
    $checkSql = "SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':usuario' => $usuario, ':email' => $email]);

    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'El usuario o email ya existen']);
        exit;
    }

    // Crear usuario
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insertSql = "INSERT INTO usuarios (nombre_completo, usuario, email, password, rol, estado) 
                  VALUES (:nombre_completo, :usuario, :email, :password, :rol, 'activo')";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->execute([
        ':nombre_completo' => $nombre_completo,
        ':usuario' => $usuario,
        ':email' => $email,
        ':password' => $passwordHash,
        ':rol' => $rol
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario creado exitosamente',
        'id' => $conn->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Error en create_usuario: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al crear usuario']);
}
?>