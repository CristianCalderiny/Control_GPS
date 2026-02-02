<?php
session_start();

// Capturar mensajes de error o éxito desde la URL
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Iniciar Sesión - FORZA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #0038A8 0%, #005cbf 100%);
            --secondary-gradient: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            --accent-gradient: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            --success-gradient: linear-gradient(135deg, #059669 0%, #10b981 100%);
            --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(0, 56, 168, 0.2);
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --icon-color: #0038A8;
            --shadow-soft: 0 20px 60px rgba(0, 56, 168, 0.2);
            --bg-gradient-1: #e0f2fe;
            --bg-gradient-2: #bfdbfe;
            --bg-gradient-3: #93c5fd;
            --bg-gradient-4: #dbeafe;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        html {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(-45deg, var(--bg-gradient-1), var(--bg-gradient-2), var(--bg-gradient-3), var(--bg-gradient-4), var(--bg-gradient-4), var(--bg-gradient-3));
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body.dark-mode {
            --glass-bg: rgba(17, 24, 39, 0.95);
            --glass-border: rgba(59, 130, 246, 0.3);
            --text-primary: #f3f4f6;
            --text-secondary: #d1d5db;
            --icon-color: #60a5fa;
            --shadow-soft: 0 20px 60px rgba(0, 0, 0, 0.5);
            --bg-gradient-1: #0f172a;
            --bg-gradient-2: #1e293b;
            --bg-gradient-3: #1e3a8a;
            --bg-gradient-4: #1e293b;
            background: linear-gradient(-45deg, var(--bg-gradient-1), var(--bg-gradient-2), var(--bg-gradient-3), var(--bg-gradient-4), var(--bg-gradient-4), var(--bg-gradient-3)) !important;
        }

        body.dark-mode .form-input {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(59, 130, 246, 0.3);
            color: #f3f4f6;
        }

        body.dark-mode .form-input:focus {
            background: rgba(30, 41, 59, 1);
            border-color: #3b82f6;
        }

        body.dark-mode .form-input::placeholder {
            color: #9ca3af;
        }

        body.dark-mode .divider::before {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        }

        body.dark-mode .footer-text {
            color: #d1d5db;
            border-top-color: rgba(59, 130, 246, 0.2);
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(0,56,168,0.05)"/><circle cx="75" cy="75" r="1" fill="rgba(0,56,168,0.03)"/><circle cx="50" cy="10" r="0.5" fill="rgba(0,56,168,0.08)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
            z-index: -1;
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(0, 56, 168, 0.08);
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
        }

        .shape:nth-child(1) {
            width: 60px;
            height: 60px;
            left: 10%;
            top: 20%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 100px;
            height: 100px;
            left: 80%;
            top: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 40px;
            height: 40px;
            left: 70%;
            top: 70%;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 80px;
            height: 80px;
            left: 20%;
            top: 80%;
            animation-delay: 6s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg) scale(1);
            }
            25% {
                transform: translateY(-20px) rotate(90deg) scale(1.1);
            }
            50% {
                transform: translateY(0) rotate(180deg) scale(0.9);
            }
            75% {
                transform: translateY(20px) rotate(270deg) scale(1.1);
            }
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
        }

        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 8px 20px rgba(0, 56, 168, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1) rotate(15deg);
            box-shadow: 0 12px 30px rgba(0, 56, 168, 0.6);
        }

        .theme-toggle-btn:active {
            transform: scale(0.95);
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 80px rgba(0, 56, 168, 0.3);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-soft);
            animation: pulse 2s infinite;
            position: relative;
        }

        .logo-icon i {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .logo-text {
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
        }

        .logo-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid rgba(0, 56, 168, 0.2);
            border-radius: 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
            transform: translateY(-2px);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--icon-color);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus + .form-icon {
            color: #0038A8;
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--icon-color);
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #0038A8;
            transform: translateY(-50%) scale(1.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }

        .form-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #0038A8;
        }

        .forgot-link {
            color: #0038A8;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: #005cbf;
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-secondary {
            background: var(--accent-gradient);
            color: white;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .btn:hover::before {
            left: 0;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 56, 168, 0.3);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(239, 68, 68, 0.1) 100%);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.3);
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 56, 168, 0.2), transparent);
        }

        .divider-text {
            background: var(--glass-bg);
            padding: 0 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            position: relative;
        }

        .forgot-password-form {
            display: none;
        }

        .forgot-password-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .main-form.hidden {
            display: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #0038A8;
            transform: translateX(-3px);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn.loading .loading-spinner {
            display: inline-block;
        }

        .btn.loading .btn-text {
            display: none;
        }

        .footer-text {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 56, 168, 0.1);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <button class="theme-toggle-btn" onclick="toggleTheme()" aria-label="Cambiar tema">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1 class="logo-text">FORZA</h1>
                <p class="logo-subtitle">Sistema de Control de GPS • Honduras</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario de Login Principal -->
            <form method="POST" action="validar_login.php" class="main-form" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Usuario o Email</label>
                    <div class="form-input-wrapper">
                        <input type="text" class="form-input" name="username" required
                            placeholder="Ingrese su usuario o email">
                        <i class="form-icon fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="form-input-wrapper">
                        <input type="password" class="form-input" name="password" id="password" required
                            placeholder="Ingrese su contraseña">
                        <i class="form-icon fas fa-lock"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" class="form-checkbox" name="remember" id="remember">
                        <label for="remember">Recordarme</label>
                    </div>
                    <a href="#" class="forgot-link" onclick="showForgotPassword(); return false;">
                        ¿Olvidó su contraseña?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </span>
                    <div class="loading-spinner"></div>
                </button>
            </form>

            <!-- Formulario de Recuperación de Contraseña -->
            <form method="POST" action="forgot-password.php" class="forgot-password-form" id="forgotForm">
                <a href="#" class="back-link" onclick="showLogin(); return false;">
                    <i class="fas fa-arrow-left"></i> Volver al login
                </a>

                <div class="form-group">
                    <label class="form-label">Email de recuperación</label>
                    <div class="form-input-wrapper">
                        <input type="email" class="form-input" name="email" required
                            placeholder="Ingrese su email registrado">
                        <i class="form-icon fas fa-envelope"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <span class="btn-text">
                        <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                    </span>
                    <div class="loading-spinner"></div>
                </button>
            </form>

            <div class="footer-text">
                © <?php echo date('Y'); ?> FORZA. Todos los derechos reservados.<br>
                <strong>Versión:</strong> 1.0.0
            </div>
        </div>
    </div>

    <script>
        // Modo oscuro
        (function() {
            const currentTheme = localStorage.getItem('forza-theme');
            const themeIcon = document.getElementById('themeIcon');

            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                if (themeIcon) {
                    themeIcon.className = 'fas fa-sun';
                }
            }
        })();

        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const themeIcon = document.getElementById('themeIcon');

            if (document.body.classList.contains('dark-mode')) {
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('forza-theme', 'dark');
            } else {
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('forza-theme', 'light');
            }
        }

        function showForgotPassword() {
            document.querySelector('.main-form').classList.add('hidden');
            document.querySelector('.forgot-password-form').classList.add('active');
        }

        function showLogin() {
            document.querySelector('.forgot-password-form').classList.remove('active');
            document.querySelector('.main-form').classList.remove('hidden');
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');

            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
        });

        document.getElementById('forgotForm').addEventListener('submit', function() {
            const btn = this.querySelector('.btn');
            btn.classList.add('loading');
        });

        // Auto-focus
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('.main-form .form-input');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Limpiar URL de parámetros después de 3 segundos
        window.addEventListener('load', function() {
            if (window.location.search.includes('success=') || window.location.search.includes('error=')) {
                setTimeout(function() {
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    url.searchParams.delete('error');
                    window.history.replaceState({}, document.title, url.pathname);
                }, 3000);
            }
        });
    </script>
</body>

</html>