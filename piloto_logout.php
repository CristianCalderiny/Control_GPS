<?php
session_start();
require_once __DIR__ . '/conexion/db.php';

if (isset($_SESSION['patrullero_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE patrulleros SET remember_token = NULL, remember_token_expira = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['patrullero_id']]);
    } catch (PDOException $e) {
        error_log("Error limpiando token de piloto: " . $e->getMessage());
    }
}

unset($_SESSION['patrullero_id']);
unset($_SESSION['patrullero_nombre']);
setcookie('piloto_remember', '', time() - 3600, '/');

header("Location: piloto_login.php");
exit;