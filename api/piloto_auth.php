<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion/db.php';

$telefono = trim($_POST['telefono'] ?? '');
$pin      = trim($_POST['pin'] ?? '');

if ($telefono === '' || $pin === '') {
    echo json_encode(['success' => false, 'message' => 'Ingresa tu teléfono y tu PIN']);
    exit;
}

// Normalizamos el teléfono quitando espacios, guiones, etc. para comparar solo dígitos
$telefonoDigitos = preg_replace('/\D/', '', $telefono);

try {
    // Traemos activos y comparamos el teléfono ya normalizado en PHP,
    // así no importa si en la BD está guardado con o sin guiones
    $stmt = $conn->prepare("SELECT id, nombre, telefono, pin_hash, estado FROM pilotos WHERE estado = 'activo'");
    $stmt->execute();
    $patrullero = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (preg_replace('/\D/', '', $row['telefono']) === $telefonoDigitos) {
            $patrullero = $row;
            break;
        }
    }

    if (!$patrullero) {
        echo json_encode(['success' => false, 'message' => 'Teléfono no encontrado o cuenta inactiva']);
        exit;
    }

    if (empty($patrullero['pin_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Tu cuenta aún no tiene PIN asignado. Contacta al administrador.']);
        exit;
    }

    if (!password_verify($pin, $patrullero['pin_hash'])) {
        echo json_encode(['success' => false, 'message' => 'PIN incorrecto']);
        exit;
    }

    // Login correcto: sesión separada de la del panel admin (claves distintas)
    $_SESSION['patrullero_id']     = $patrullero['id'];
    $_SESSION['patrullero_nombre'] = $patrullero['nombre'];

    // Sesión persistente: generamos un token, guardamos su hash en la BD,
    // y ponemos el token real (sin hashear) en una cookie de 30 días.
    $tokenPlano = bin2hex(random_bytes(32));
    $tokenHash  = password_hash($tokenPlano, PASSWORD_BCRYPT);
    $expira     = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 días

    $up = $conn->prepare("UPDATE pilotos SET remember_token = ?, remember_token_expira = ? WHERE id = ?");
    $up->execute([$tokenHash, $expira, $patrullero['id']]);

    // Ya confirmaste que tienes HTTPS en producción, pero mientras pruebas en
    // localhost (http, sin candado) dejamos "secure" en false. Cámbialo a true
    // cuando subas esto a tu dominio real con HTTPS.
    setcookie(
        'piloto_remember',
        $patrullero['id'] . ':' . $tokenPlano,
        time() + (86400 * 30),
        '/',
        '',
        false,   // secure: false para localhost, true en producción con HTTPS
        true     // httponly
    );

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error en login de piloto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}