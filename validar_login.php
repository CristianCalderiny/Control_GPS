<?php
session_start();
require 'conexion/db.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['usr_forza'] ?? '');
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

    // Datos básicos del usuario, legibles por JS del lado del cliente (no sensibles)
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

    if ($remember) {
        // Generar token, guardar solo su hash en la BD y mandar el token
        // en crudo por cookie httpOnly. Así la BD nunca guarda el valor
        // que viaja al navegador.
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expira = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 días

        $tokenSql = "UPDATE usuarios SET remember_token = :token, remember_expira = :expira WHERE id = :id";
        $tokenStmt = $conn->prepare($tokenSql);
        $tokenStmt->execute([
            ':token' => $tokenHash,
            ':expira' => $expira,
            ':id' => $user['id']
        ]);

        setcookie('forza_remember', $token, time() + (86400 * 30), '/', '', true, true);
    } else {
        // Si no marcó recordarme, invalidar cualquier token previo de este usuario
        $clearSql = "UPDATE usuarios SET remember_token = NULL, remember_expira = NULL WHERE id = :id";
        $clearStmt = $conn->prepare($clearSql);
        $clearStmt->execute([':id' => $user['id']]);

        if (isset($_COOKIE['forza_remember'])) {
            setcookie('forza_remember', '', time() - 3600, '/', '', true, true);
        }
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