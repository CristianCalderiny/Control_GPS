<?php
session_start();

// Limpiar variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Limpiar cookies si existen
if (isset($_COOKIE['forza_remember'])) {
    setcookie('forza_remember', '', time() - 3600, '/', '', true, true);
}

// Redirigir al login
header("Location: login.php?success=" . urlencode("Sesión cerrada correctamente"));
exit;
?>