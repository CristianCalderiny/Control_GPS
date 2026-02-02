<?php
/**
 * Verificar si existe el usuario admin y su contraseña
 */

require_once 'conexion/db.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Verificar Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 2rem;
        }
        .container {
            background: white;
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .info { 
            background: #e3f2fd; 
            padding: 1rem; 
            margin: 1rem 0;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
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
        }
        .btn {
            background: #2196F3;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 1rem;
        }
        .btn:hover { background: #1976D2; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificación de Usuario Admin</h1>";

try {
    // 1. Contar usuarios totales
    echo "<div class='info'><strong>📊 Total de usuarios:</strong><br>";
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios");
    $countStmt->execute();
    $count = $countStmt->fetch()['total'];
    echo "Hay <strong>$count usuario(s)</strong> en la base de datos</div>";
    
    // 2. Listar TODOS los usuarios
    echo "<div class='info'><strong>👥 Todos los usuarios:</strong><br>";
    $allUsersStmt = $conn->prepare("SELECT id, usuario, email, rol, estado FROM usuarios");
    $allUsersStmt->execute();
    $allUsers = $allUsersStmt->fetchAll();
    
    if (count($allUsers) > 0) {
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><th style='border: 1px solid #ddd; padding: 0.5rem;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 0.5rem;'>Usuario</th>";
        echo "<th style='border: 1px solid #ddd; padding: 0.5rem;'>Email</th>";
        echo "<th style='border: 1px solid #ddd; padding: 0.5rem;'>Rol</th>";
        echo "<th style='border: 1px solid #ddd; padding: 0.5rem;'>Estado</th></tr>";
        
        foreach ($allUsers as $user) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 0.5rem;'>" . $user['id'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 0.5rem;'>" . $user['usuario'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 0.5rem;'>" . $user['email'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 0.5rem;'>" . $user['rol'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 0.5rem;'>" . $user['estado'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<strong style='color: red;'>❌ NO HAY USUARIOS</strong>";
    }
    echo "</div>";
    
    // 3. Buscar usuario admin específicamente
    echo "<div class='info'><strong>🔎 Buscando usuario 'admin':</strong><br>";
    $adminStmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = 'admin'");
    $adminStmt->execute();
    $admin = $adminStmt->fetch();
    
    if ($admin) {
        echo "<div class='success'>";
        echo "✅ Usuario admin ENCONTRADO<br>";
        echo "ID: " . $admin['id'] . "<br>";
        echo "Usuario: " . $admin['usuario'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Rol: " . $admin['rol'] . "<br>";
        echo "Estado: " . $admin['estado'] . "<br>";
        echo "Password Hash: " . substr($admin['password'], 0, 20) . "...";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "❌ Usuario admin NO encontrado<br>";
        echo "Necesita ejecutar el INSERT";
        echo "</div>";
    }
    echo "</div>";
    
    // 4. Probar login
    echo "<div class='info'><strong>🔐 Probando login con admin/admin123:</strong><br>";
    if ($admin) {
        $password_test = 'admin123';
        if (password_verify($password_test, $admin['password'])) {
            echo "<div class='success'>✅ La contraseña es CORRECTA</div>";
        } else {
            echo "<div class='error'>❌ La contraseña es INCORRECTA</div>";
            echo "<p>Hash almacenado: " . $admin['password'] . "</p>";
            echo "<p>Contraseña a probar: $password_test</p>";
        }
    } else {
        echo "<div class='error'>No hay usuario admin para probar</div>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ Error en BD:</strong><br>" . $e->getMessage() . "</div>";
}

echo "
    <h2>¿Qué hacer ahora?</h2>";

// Mostrar instrucciones según el resultado
if (!$admin) {
    echo "<div class='error'><h3>Si no ves el usuario admin:</h3>
    <p>Ejecuta este SQL en MySQL Workbench:</p>
    <div class='code'>
INSERT INTO usuarios (nombre_completo, usuario, email, password, rol, estado) 
VALUES (
    'Administrador',
    'admin',
    'admin@forza.hn',
    '\$2y\$10\$YIjlrDfl2stg8w/rc2H5O.f9pY8zb.iiMUvqSuuTbdH.EwL8aZsJa',
    'Administrador',
    'activo'
);
    </div>
    <p>Luego recarga esta página.</p>
    </div>";
} else if (password_verify('admin123', $admin['password'])) {
    echo "<div class='success'>
    <h3>✅ Todo está listo!</h3>
    <p>Puedes iniciar sesión con:</p>
    <p><strong>Usuario:</strong> admin</p>
    <p><strong>Contraseña:</strong> admin123</p>
    <p><a href='login.php' style='color: #2196F3; text-decoration: none;'>→ Ir a Login</a></p>
    </div>";
} else {
    echo "<div class='error'>
    <h3>⚠️ Hay un problema con la contraseña</h3>
    <p>El usuario existe pero la contraseña no coincide.</p>
    <p>Necesita resetear la contraseña:</p>
    <div class='code'>
UPDATE usuarios 
SET password = '\$2y\$10\$YIjlrDfl2stg8w/rc2H5O.f9pY8zb.iiMUvqSuuTbdH.EwL8aZsJa'
WHERE usuario = 'admin';
    </div>
    </div>";
}

echo "
    </div>
</body>
</html>";
?>