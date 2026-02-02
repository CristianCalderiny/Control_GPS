<?php
/**
 * ARCHIVO: test_conexion.php
 * 
 * Prueba de conexión a la base de datos
 * Accede a: http://localhost/forza-gps/test_conexion.php
 */

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Conexión FORZA GPS</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            font-size: 2rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #718096;
            font-size: 1.05rem;
        }
        
        .test-item {
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            background: #f7fafc;
            transition: all 0.3s ease;
        }
        
        .test-item.success {
            border-left-color: #48bb78;
            background: #f0fff4;
        }
        
        .test-item.error {
            border-left-color: #f56565;
            background: #fff5f5;
        }
        
        .test-item.warning {
            border-left-color: #ed8936;
            background: #fffaf0;
        }
        
        .test-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .test-item.success .test-title {
            color: #22543d;
        }
        
        .test-item.error .test-title {
            color: #742a2a;
        }
        
        .test-item.warning .test-title {
            color: #7c2d12;
        }
        
        .test-item.success .test-title i {
            color: #48bb78;
        }
        
        .test-item.error .test-title i {
            color: #f56565;
        }
        
        .test-item.warning .test-title i {
            color: #ed8936;
        }
        
        .test-description {
            color: #4a5568;
            font-size: 0.95rem;
            margin-left: 1.75rem;
        }
        
        .test-item.success .test-description {
            color: #22543d;
        }
        
        .test-item.error .test-description {
            color: #742a2a;
        }
        
        .test-item.warning .test-description {
            color: #7c2d12;
        }
        
        .details {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #2d3748;
            word-break: break-all;
            margin-left: 1.75rem;
        }
        
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 2rem;
            text-align: center;
        }
        
        .summary h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .summary p {
            font-size: 1rem;
            opacity: 0.95;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            min-width: 150px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class='container'>";

// Array para almacenar resultados
$tests = [];

// TEST 1: Verificar archivo de conexión
echo "<div class='header'>
    <h1><i class='fas fa-database'></i> Test de Conexión</h1>
    <p>Verifica que tu aplicación FORZA GPS esté correctamente configurada</p>
</div>";

// TEST 1: ¿Existe el archivo db.php?
if (file_exists('conexion/db.php')) {
    $tests[] = ['name' => 'Archivo db.php existe', 'status' => 'success'];
} else {
    $tests[] = ['name' => 'Archivo db.php existe', 'status' => 'error', 'message' => 'No se encontró conexion/db.php'];
}

// TEST 2: Intentar conexión a BD
try {
    require_once 'conexion/db.php';
    $tests[] = ['name' => 'Conexión a MySQL', 'status' => 'success', 'message' => 'Conexión exitosa a la base de datos'];
    
    // TEST 3: Verificar tabla usuarios
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = $result['count'];
    
    $tests[] = [
        'name' => 'Tabla usuarios', 
        'status' => 'success', 
        'message' => "Tabla existe con $userCount usuario(s)"
    ];
    
    // TEST 4: Verificar usuario admin
    $adminStmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = 'admin' LIMIT 1");
    $adminStmt->execute();
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $tests[] = [
            'name' => 'Usuario admin', 
            'status' => 'success', 
            'message' => "Usuario admin encontrado (ID: {$admin['id']})"
        ];
    } else {
        $tests[] = [
            'name' => 'Usuario admin', 
            'status' => 'error', 
            'message' => 'Usuario admin no encontrado. Ejecuta el script SQL.'
        ];
    }
    
    // TEST 5: Listar todas las tablas
    $tablesStmt = $conn->prepare("SHOW TABLES FROM forza_gps");
    $tablesStmt->execute();
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredTables = ['usuarios', 'gps_dispositivos', 'custodios', 'asignaciones_gps', 'historial_movimientos'];
    $missingTables = array_diff($requiredTables, $tables);
    
    if (empty($missingTables)) {
        $tests[] = [
            'name' => 'Tablas de BD', 
            'status' => 'success', 
            'message' => 'Todas las tablas requeridas existen: ' . implode(', ', $tables)
        ];
    } else {
        $tests[] = [
            'name' => 'Tablas de BD', 
            'status' => 'warning', 
            'message' => 'Faltan tablas: ' . implode(', ', $missingTables)
        ];
    }
    
    // TEST 6: Verificar estructura de tabla usuarios
    $columnsStmt = $conn->prepare("DESCRIBE usuarios");
    $columnsStmt->execute();
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['id', 'nombre_completo', 'usuario', 'email', 'password', 'rol', 'estado'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (empty($missingColumns)) {
        $tests[] = [
            'name' => 'Estructura tabla usuarios', 
            'status' => 'success', 
            'message' => 'Todas las columnas requeridas existen'
        ];
    } else {
        $tests[] = [
            'name' => 'Estructura tabla usuarios', 
            'status' => 'error', 
            'message' => 'Faltan columnas: ' . implode(', ', $missingColumns)
        ];
    }
    
} catch (PDOException $e) {
    $tests[] = [
        'name' => 'Conexión a MySQL', 
        'status' => 'error', 
        'message' => $e->getMessage()
    ];
}

// TEST 8: Verificar configuración PHP
$phpTests = [
    'PDO extension' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Sessions' => extension_loaded('session'),
];

foreach ($phpTests as $name => $loaded) {
    $tests[] = [
        'name' => $name,
        'status' => $loaded ? 'success' : 'error',
        'message' => $loaded ? 'Extensión cargada' : 'Extensión no cargada'
    ];
}

// Mostrar resultados
foreach ($tests as $test) {
    $icon = $test['status'] === 'success' ? 'fa-check-circle' : ($test['status'] === 'error' ? 'fa-times-circle' : 'fa-exclamation-triangle');
    echo "<div class='test-item {$test['status']}'>
        <div class='test-title'>
            <i class='fas {$icon}'></i>
            {$test['name']}
        </div>
        <div class='test-description'>
            {$test['message']}
        </div>
    </div>";
}

// Resumen
$successCount = count(array_filter($tests, fn($t) => $t['status'] === 'success'));
$errorCount = count(array_filter($tests, fn($t) => $t['status'] === 'error'));
$totalTests = count($tests);

$summaryStatus = $errorCount === 0 ? '✅ LISTO PARA USAR' : '❌ REQUIERE CONFIGURACIÓN';
$summaryColor = $errorCount === 0 ? 'success' : 'error';

echo "<div class='summary'>
    <h3>$summaryStatus</h3>
    <p>$successCount/$totalTests pruebas pasadas</p>
</div>";

// Acciones
echo "<div class='actions'>
    <a href='login.php' class='btn btn-primary'>
        <i class='fas fa-sign-in-alt'></i> Ir al Login
    </a>
    <button onclick='location.reload()' class='btn btn-secondary'>
        <i class='fas fa-sync-alt'></i> Recargar
    </button>
</div>";

echo "    </div>
</body>
</html>";
?>