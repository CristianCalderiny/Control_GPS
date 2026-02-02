<?php
/**
 * Archivo para depurar problemas con el login
 * Accede a: http://localhost/forza-gps/debug_login.php
 */

require_once 'conexion/db.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 2rem;
        }
        .container {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #2196F3; padding-bottom: 0.5rem; }
        .section { margin: 2rem 0; }
        .info { 
            background: #e3f2fd; 
            padding: 1rem; 
            border-left: 4px solid #2196F3;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        .success { 
            background: #e8f5e9; 
            border-left-color: #4CAF50;
            color: #2e7d32;
        }
        .error { 
            background: #ffebee; 
            border-left-color: #f44336;
            color: #c62828;
        }
        .code {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
            margin: 1rem 0;
            border: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 0.75rem;
            text-align: left;
        }
        table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .test-form {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1.5rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .test-form input {
            padding: 0.5rem;
            margin: 0.5rem 0;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .test-form button {
            background: #2196F3;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .test-form button:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Debug Login - FORZA GPS</h1>
        <p>Esta página te ayuda a diagnosticar problemas con el login.</p>";

try {
    // SECCIÓN 1: Verificar usuarios en la BD
    echo "<div class='section'>
        <h2>1. Usuarios Disponibles</h2>";
    
    $usersStmt = $conn->prepare("SELECT id, usuario, email, rol, estado FROM usuarios ORDER BY id");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
            </tr>";
        
        foreach ($users as $user) {
            echo "<tr>
                <td>" . $user['id'] . "</td>
                <td><strong>" . $user['usuario'] . "</strong></td>
                <td>" . $user['email'] . "</td>
                <td>" . $user['rol'] . "</td>
                <td>" . $user['estado'] . "</td>
            </tr>";
        }
        echo "</table>";
        echo "<div class='info success'>✅ Se encontraron " . count($users) . " usuario(s)</div>";
    } else {
        echo "<div class='info error'>❌ NO HAY USUARIOS</div>";
    }
    echo "</div>";
    
    // SECCIÓN 2: Probar contraseña
    echo "<div class='section'>
        <h2>2. Probar Contraseña (admin123)</h2>";
    
    if (count($users) > 0) {
        echo "<table>
            <tr>
                <th>Usuario</th>
                <th>¿Contraseña 'admin123'?</th>
                <th>Hash</th>
            </tr>";
        
        foreach ($users as $user) {
            $userStmt = $conn->prepare("SELECT password FROM usuarios WHERE id = :id");
            $userStmt->execute([':id' => $user['id']]);
            $userData = $userStmt->fetch();
            $hash = $userData['password'];
            $isValid = password_verify('admin123', $hash);
            
            $status = $isValid ? '✅ Válida' : '❌ Inválida';
            echo "<tr>
                <td><strong>" . $user['usuario'] . "</strong></td>
                <td>$status</td>
                <td>" . substr($hash, 0, 30) . "...</td>
            </tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // SECCIÓN 3: Test de login manual
    echo "<div class='section'>
        <h2>3. Prueba Manual de Login</h2>
        <div class='test-form'>
            <h3>Ingresa credenciales para probar:</h3>
            <form method='POST' action='test_login_action.php'>
                <div>
                    <label><strong>Usuario o Email:</strong></label>
                    <input type='text' name='username' placeholder='ej: admin' required>
                </div>
                <div>
                    <label><strong>Contraseña:</strong></label>
                    <input type='password' name='password' placeholder='ej: admin123' required>
                </div>
                <button type='submit'>🔐 Probar Login</button>
            </form>
        </div>
    </div>";
    
    // SECCIÓN 4: Información del servidor
    echo "<div class='section'>
        <h2>4. Información del Servidor</h2>";
    
    $checks = [
        'PHP Version' => phpversion(),
        'PDO Available' => extension_loaded('pdo') ? 'Sí' : 'No',
        'PDO MySQL' => extension_loaded('pdo_mysql') ? 'Sí' : 'No',
        'Session Support' => extension_loaded('session') ? 'Sí' : 'No',
        'Servidor' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
        'Directorio' => __DIR__,
    ];
    
    echo "<table>
        <tr><th>Parámetro</th><th>Valor</th></tr>";
    
    foreach ($checks as $name => $value) {
        echo "<tr><td><strong>$name</strong></td><td>$value</td></tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // SECCIÓN 5: Soluciones
    echo "<div class='section'>
        <h2>5. Soluciones Rápidas</h2>
        
        <h3>Si el login no funciona:</h3>
        <ol>
            <li><strong>Verifica las credenciales</strong> - Mira la tabla de usuarios arriba</li>
            <li><strong>Si la contraseña es inválida</strong> - Ejecuta este SQL:
                <div class='code'>UPDATE usuarios SET password = '\$2y\$10\$YIjlrDfl2stg8w/rc2H5O.f9pY8zb.iiMUvqSuuTbdH.EwL8aZsJa' WHERE usuario = 'admin';</div>
            </li>
            <li><strong>Verifica el estado</strong> - El usuario debe estar 'activo'</li>
            <li><strong>Abre la consola del navegador</strong> (F12) para ver errores</li>
        </ol>
    </div>";
    
} catch (PDOException $e) {
    echo "<div class='info error'><strong>❌ Error en BD:</strong><br>" . $e->getMessage() . "</div>";
}

echo "
    </div>
</body>
</html>";
?>