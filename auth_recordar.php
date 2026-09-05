<?php
/**
 * auth_recordar.php
 * Restaura la sesión del piloto usando la cookie "recordarme", con el mismo
 * esquema que ya usa api/piloto_auth.php:
 *
 *   - Cookie "piloto_remember" = "{id}:{token_plano}"
 *   - En BD, patrulleros.remember_token guarda el HASH del token (password_hash)
 *   - patrulleros.remember_token_expira guarda la fecha de vencimiento (DATETIME)
 *
 * IMPORTANTE: este archivo NO debe llamar session_start(), porque
 * piloto_login.php ya lo hace antes de incluirlo.
 */

if (!isset($_SESSION['patrullero_id']) && isset($_COOKIE['piloto_remember'])) {

    if (!isset($conn)) {
        require_once __DIR__ . '/conexion/db.php'; // debe dejar disponible $conn (PDO)
    }

    $cookieValue = $_COOKIE['piloto_remember'];
    $partes = explode(':', $cookieValue, 2);

    if (count($partes) === 2) {
        [$idCookie, $tokenPlano] = $partes;
        $idCookie = (int) $idCookie;

        try {
            $stmt = $conn->prepare("
                SELECT id, nombre, remember_token, remember_token_expira
                FROM patrulleros
                WHERE id = ? AND remember_token IS NOT NULL
            ");
            $stmt->execute([$idCookie]);
            $piloto = $stmt->fetch(PDO::FETCH_ASSOC);

            $valido = $piloto
                && $piloto['remember_token_expira'] > date('Y-m-d H:i:s')
                && password_verify($tokenPlano, $piloto['remember_token']);

            if ($valido) {
                $_SESSION['patrullero_id']     = $piloto['id'];
                $_SESSION['patrullero_nombre'] = $piloto['nombre'];
            } else {
                // Cookie inválida, vencida, o token no coincide: la limpiamos
                setcookie('piloto_remember', '', time() - 3600, '/');
            }
        } catch (PDOException $e) {
            error_log("Error restaurando sesión del piloto: " . $e->getMessage());
        }
    } else {
        // Formato de cookie corrupto/inesperado
        setcookie('piloto_remember', '', time() - 3600, '/');
    }
}