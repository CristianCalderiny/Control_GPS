<?php
session_start();
require 'conexion/db.php';

// Invalidar el remember_token en la base de datos para que la cookie
// (si alguien la conservara) ya no sirva para un auto-login.
if (!empty($_SESSION['usuario_id'])) {
    $clearSql = "UPDATE usuarios SET remember_token = NULL, remember_expira = NULL WHERE id = :id";
    $clearStmt = $conn->prepare($clearSql);
    $clearStmt->execute([':id' => $_SESSION['usuario_id']]);
} elseif (!empty($_COOKIE['forza_remember'])) {
    // Por si hay cookie de recordarme pero ya no hay sesión activa
    $tokenHash = hash('sha256', $_COOKIE['forza_remember']);
    $clearSql = "UPDATE usuarios SET remember_token = NULL, remember_expira = NULL WHERE remember_token = :token";
    $clearStmt = $conn->prepare($clearSql);
    $clearStmt->execute([':token' => $tokenHash]);
}

// Limpiar variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Limpiar cookies si existen
if (isset($_COOKIE['forza_remember'])) {
    setcookie('forza_remember', '', time() - 3600, '/', '', true, true);
}

if (isset($_COOKIE['usuario_logueado'])) {
    setcookie('usuario_logueado', '', time() - 3600, '/');
}

// Redirigir al login
header("Location: login.php?success=" . urlencode("Sesión cerrada correctamente"));
exit;