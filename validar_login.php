<?php
session_start();
require 'conexion/db.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validar campos vacíos
if (empty($username) || empty($password)) {
    header("Location: login.php?error=" . urlencode("Por favor complete todos los campos"));
    exit;
}

try {
    // Buscar usuario por username o email
    $sql = "SELECT id, usuario, email, password, rol, nombre_completo, estado 
            FROM usuarios 
            WHERE (usuario = :username OR email = :username) AND estado = 'activo'
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar si el usuario existe
    if (!$user) {
        header("Location: login.php?error=" . urlencode("Usuario no encontrado o cuenta inactiva"));
        exit;
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        header("Location: login.php?error=" . urlencode("Contraseña incorrecta"));
        exit;
    }

    // Login exitoso - Crear sesión
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['usuario'] = $user['usuario'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['nombre'] = $user['nombre_completo'];
    $_SESSION['autenticado'] = true;
    $_SESSION['login_time'] = time();
    
    // Guardar en localStorage para JavaScript también
    setcookie('usuario_logueado', json_encode([
        'id' => $user['id'],
        'usuario' => $user['usuario'],
        'email' => $user['email'],
        'rol' => $user['rol'],
        'nombre' => $user['nombre_completo']
    ]), time() + (86400 * 30), '/');
    
    // Actualizar último acceso
    $updateSql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([':id' => $user['id']]);
    
    // Si marcó "Recordarme", crear cookie segura
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('forza_remember', $token, time() + (86400 * 30), '/', '', true, true); // 30 días
    }
    
    // Redirigir al dashboard
    header("Location: index.php");
    exit;
    
} catch (PDOException $e) {
    // Log del error
    error_log("Error de login: " . $e->getMessage());
    header("Location: login.php?error=" . urlencode("Error del sistema. Intente nuevamente."));
    exit;
}
?>