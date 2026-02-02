<?php
/**
 * Generador de Hash de Contraseña
 * Accede a: http://localhost/forza-gps/generar_hash.php
 */

$passwordIngresada = $_POST['password'] ?? '';
$hashGenerado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($passwordIngresada)) {
    $hashGenerado = password_hash($passwordIngresada, PASSWORD_DEFAULT);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Hash de Contraseña</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 3rem 2.5rem;
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
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: monospace;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .result-section {
            margin-top: 2rem;
            padding: 2rem;
            background: #f7fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        
        .result-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .hash-output {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e0;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            word-break: break-all;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .copy-btn {
            background: #48bb78;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .copy-btn:hover {
            background: #38a169;
        }
        
        .copy-btn.copied {
            background: #667eea;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            color: #1565c0;
            font-size: 0.95rem;
        }
        
        .sql-example {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .sql-example strong {
            display: block;
            margin-bottom: 0.75rem;
            color: #856404;
        }
        
        .code {
            background: white;
            padding: 0.75rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .step {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .step h3 {
            color: #22543d;
            margin-bottom: 0.5rem;
        }
        
        .step p {
            color: #276749;
            font-size: 0.95rem;
        }
        
        .success {
            background: #e8f5e9;
            border: 2px solid #48bb78;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Generar Hash de Contraseña</h1>
            <p>Crea un hash seguro para tu contraseña</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Ingresa la contraseña que quieres hashear:</label>
                <input type="text" name="password" class="form-input" placeholder="ej: MiContraseña123" required>
            </div>
            <button type="submit" class="btn">
                <i style="margin-right: 0.5rem;">🔒</i> Generar Hash
            </button>
        </form>
        
        <?php if ($hashGenerado): ?>
        <div class="result-section">
            <div class="result-title">✅ Hash Generado</div>
            
            <div class="hash-output" id="hashOutput">
                <?php echo $hashGenerado; ?>
            </div>
            
            <button class="copy-btn" onclick="copiarAlPortapapeles()">
                📋 Copiar Hash al Portapapeles
            </button>
            
            <div class="sql-example">
                <strong>📝 Para usar en MySQL, ejecuta esto:</strong>
                <div class="code">
UPDATE usuarios SET password = '<?php echo $hashGenerado; ?>' WHERE usuario = 'admin';
                </div>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Información:</strong><br>
                Contraseña: <strong><?php echo htmlspecialchars($passwordIngresada); ?></strong><br>
                Este hash corresponde a esa contraseña y es seguro.
            </div>
            
            <div class="step">
                <h3>📋 Pasos para actualizar la contraseña:</h3>
                <p>
                    <strong>1.</strong> Copia el hash anterior (botón "Copiar")<br>
                    <strong>2.</strong> Abre MySQL Workbench<br>
                    <strong>3.</strong> Conecta a la BD forza_gps<br>
                    <strong>4.</strong> Pega y ejecuta el SQL de arriba<br>
                    <strong>5.</strong> Vuelve a abrir <a href="debug_login.php" style="color: #1565c0; font-weight: 600;">debug_login.php</a>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-box" style="margin-top: 2rem;">
            <strong>💡 Tip:</strong> Si quieres cambiar la contraseña de ambos usuarios a <strong>admin123</strong>, primero ingresa esa contraseña aquí, copia el hash, y luego ejecuta este SQL en MySQL:
            <div class="code" style="margin-top: 0.75rem;">
UPDATE usuarios SET password = '[HASH AQUÍ]' WHERE rol = 'Administrador';
            </div>
        </div>
    </div>
    
    <script>
        function copiarAlPortapapeles() {
            const hashElement = document.getElementById('hashOutput');
            const texto = hashElement.textContent;
            
            // Intentar copiar con la API moderna
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(() => {
                    mostrarMensajeCopia();
                }).catch(err => {
                    console.error('Error al copiar:', err);
                    copiarManual(texto);
                });
            } else {
                // Fallback para navegadores antiguos
                copiarManual(texto);
            }
        }
        
        function copiarManual(texto) {
            const textarea = document.createElement('textarea');
            textarea.value = texto;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            mostrarMensajeCopia();
        }
        
        function mostrarMensajeCopia() {
            const btn = document.querySelector('.copy-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '✅ ¡Copiado!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }
    </script>
</body>
</html>