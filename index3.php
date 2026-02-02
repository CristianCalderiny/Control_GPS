<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion/db.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol'] ?? 'Usuario';

// Obtener estadísticas de la base de datos
try {
    $statsSQL = "
        SELECT 
        (SELECT COUNT(*) FROM gps_dispositivos WHERE estado = 'asignado') as gps_asignados,
        (SELECT COUNT(*) FROM gps_dispositivos WHERE estado = 'disponible') as gps_disponibles,
        (SELECT COUNT(*) FROM gps_dispositivos) as gps_total,
        (SELECT COUNT(*) FROM custodios WHERE estado = 'activo') as custodios_activos,
        (SELECT COUNT(*) FROM asignaciones_gps WHERE estado = 'asignado') as asignaciones_activas,
        (SELECT COUNT(*) FROM misiones WHERE estado = 'en_curso') as misiones_activas,
        (SELECT COUNT(*) FROM misiones WHERE estado = 'completada') as misiones_completadas,
        (SELECT COUNT(*) FROM misiones) as total_misiones
    ";

    $stmt = $conn->prepare($statsSQL);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener asignaciones activas para la tabla
    $asignacionesSQL = "
        SELECT 
            a.id,
            a.cliente,
            a.origen,
            a.destino,
            a.observaciones,
            a.fecha_asignacion,
            g.imei,
            g.marca,
            g.modelo,
            c.nombre as custodio_nombre,
            c.telefono as custodio_telefono,
            DATEDIFF(NOW(), a.fecha_asignacion) as dias_asignado
        FROM asignaciones_gps a
        INNER JOIN gps_dispositivos g ON a.gps_id = g.id
        INNER JOIN custodios c ON a.custodio_id = c.id
        WHERE a.estado = 'asignado'
        ORDER BY a.fecha_asignacion DESC
    ";

    $stmtAsignaciones = $conn->prepare($asignacionesSQL);
    $stmtAsignaciones->execute();
    $asignaciones = $stmtAsignaciones->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error en estadísticas: " . $e->getMessage());
    $stats = [
        'gps_asignados' => 0,
        'gps_disponibles' => 0,
        'gps_total' => 0,
        'custodios_activos' => 0,
        'asignaciones_activas' => 0,
        'misiones_activas' => 0,
        'misiones_completadas' => 0,
        'total_misiones' => 0
    ];
    $asignaciones = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FORZA - Control de GPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .top-bar {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            height: 70px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 100;
            box-shadow: var(--shadow);
            transition: left 0.3s ease;
        }

        .top-bar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 101;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .logo-text p {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .nav-menu {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .nav-item {
            width: 100%;
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            background: transparent;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }

        .nav-item i {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .nav-item:hover {
            background: var(--bg-secondary);
        }

        .nav-item.active {
            background: var(--primary);
            color: white;
        }

        .nav-item.active i {
            color: white;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border);
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
        }

        .module-content {
            animation: fadeIn 0.3s ease;
        }

        .module-content.hidden {
            display: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .content-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .stat-info p {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f97316, #ea580c);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-icon.teal {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .stat-icon.indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--bg-secondary);
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tbody tr:hover {
            background: var(--bg-secondary);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        body.dark-mode .badge-success {
            background: #166534;
            color: #dcfce7;
        }

        body.dark-mode .badge-warning {
            background: #92400e;
            color: #fef3c7;
        }

        body.dark-mode .badge-danger {
            background: #991b1b;
            color: #fee2e2;
        }

        body.dark-mode .badge-info {
            background: #1e40af;
            color: #dbeafe;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .hidden {
            display: none;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .form-header h3 {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-menu-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            min-width: 280px;
            z-index: 1000;
        }

        .user-dropdown.hidden {
            display: none;
        }

        .theme-toggle-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle-top:hover {
            background: var(--bg-primary);
            box-shadow: var(--shadow);
        }

        .notification-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: var(--bg-card);
            border-left: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1001;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .notification-panel.active {
            right: 0;
        }

        .notification-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .top-bar {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="user-info">
            <div class="logo-container">
                <div class="logo-icon" style="width: 45px; height: 45px;">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="logo-text">
                    <h1 style="font-size: 1.25rem;">FORZA</h1>
                    <p style="font-size: 0.7rem;">Control de GPS</p>
                </div>
            </div>
        </div>

        <div class="top-bar-actions">
            <button class="theme-toggle-top" onclick="toggleTheme()">
                <i class="fas fa-moon" id="theme-icon"></i>
                <span id="theme-text">Tema Oscuro</span>
            </button>

            <button class="btn btn-primary" onclick="toggleNotificaciones()" style="position: relative; padding: 0.75rem 1rem;">
                <i class="fas fa-bell"></i>
            </button>

            <div class="user-menu-container" style="position: relative;">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <div class="user-menu-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <span id="user-menu-nombre">Admin</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="user-dropdown hidden" id="user-dropdown">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                        <p style="font-weight: 700; margin-bottom: 0.25rem;" id="dropdown-nombre">Admin</p>
                        <p style="font-size: 0.875rem; color: var(--text-secondary);" id="dropdown-email">admin@forza.hn</p>
                    </div>

                    <button class="btn" onclick="toggleTheme()" style="width: 100%; justify-content: flex-start; background: none; color: var(--text-primary); border: none; border-radius: 0; padding: 1rem 1.5rem;">
                        <i class="fas fa-moon"></i>
                        <span id="theme-text-menu">Modo Oscuro</span>
                    </button>

                    <button class="btn" onclick="cerrarSesion()" style="width: 100%; justify-content: flex-start; background: none; color: var(--danger); border: none; border-radius: 0; padding: 1rem 1.5rem;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="notification-panel" id="notification-panel">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fas fa-bell"></i> Notificaciones</h3>
            <button class="btn btn-secondary" onclick="toggleNotificaciones()" style="padding: 0.5rem 1rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 1rem;" id="notification-list">
            <div class="notification-item">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600;">🔔 Sistema iniciado</span>
                    <span style="font-size: 0.75rem; color: var(--text-secondary);">Hace 1 min</span>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                    Bienvenido al sistema FORZA de Control de GPS
                </div>
            </div>
        </div>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="logo-text">
                    <h1>FORZA</h1>
                    <p>Control de GPS</p>
                </div>
            </div>
        </div>

        <nav class="nav-menu" id="nav-menu">
            <button class="nav-item active" onclick="showModule('dashboard')">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </button>
            <button class="nav-item" onclick="showModule('gps')">
                <i class="fas fa-satellite-dish"></i>
                <span>Gestión de GPS</span>
            </button>
            <button class="nav-item" onclick="showModule('custodios')">
                <i class="fas fa-users"></i>
                <span>Custodios</span>
            </button>
            <button class="nav-item" onclick="showModule('asignar')">
                <i class="fas fa-hand-holding"></i>
                <span>Asignar GPS</span>
            </button>
            <button class="nav-item" onclick="showModule('retornar')">
                <i class="fas fa-undo"></i>
                <span>Retornar GPS</span>
            </button>
            <button class="nav-item" onclick="showModule('consulta')">
                <i class="fas fa-search"></i>
                <span>Consultar GPS</span>
            </button>
            <button class="nav-item" onclick="showModule('historial')">
                <i class="fas fa-clock"></i>
                <span>Historial</span>
            </button>
            <button class="nav-item" onclick="showModule('misiones')">
                <i class="fas fa-tasks"></i>
                <span>📋 Misiones</span>
            </button>
        </nav>
    </aside>

    <main class="main-content">
        <!-- DASHBOARD -->
        <div id="module-dashboard" class="module-content">
            <div class="content-header">
                <h2>Dashboard General</h2>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>GPS Asignados</h3>
                        <p id="stat-asignados"><?php echo $stats['gps_asignados'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>GPS Disponibles</h3>
                        <p id="stat-disponibles"><?php echo $stats['gps_disponibles'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total de GPS</h3>
                        <p id="stat-total"><?php echo $stats['gps_total'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-list"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Misiones Activas</h3>
                        <p id="stat-misiones-activas"><?php echo $stats['misiones_activas'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-running"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Misiones Completadas</h3>
                        <p id="stat-misiones-completadas"><?php echo $stats['misiones_completadas'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon teal">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Misiones</h3>
                        <p id="stat-misiones-total"><?php echo $stats['total_misiones'] ?? 0; ?></p>
                    </div>
                    <div class="stat-icon indigo">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">GPS ACTUALMENTE ASIGNADOS</h3>
                <div class="table-container">
                    <table id="tabla-gps-asignados">
                        <thead>
                            <tr>
                                <th>IMEI/Serie</th>
                                <th>Modelo</th>
                                <th>Custodio</th>
                                <th>Fecha Asignación</th>
                                <th>Días Asignado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($asignaciones)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center">
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h3>No hay GPS asignados</h3>
                                            <p>Todos los GPS están disponibles</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($asignaciones as $asignacion): ?>
                                    <tr>
                                        <td style="font-family: monospace;"><?php echo htmlspecialchars($asignacion['imei']); ?></td>
                                        <td><?php echo htmlspecialchars($asignacion['marca'] . ' ' . $asignacion['modelo']); ?></td>
                                        <td><?php echo htmlspecialchars($asignacion['custodio_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($asignacion['fecha_asignacion']); ?></td>
                                        <td><?php echo htmlspecialchars($asignacion['dias_asignado']); ?> días</td>
                                        <td><span class="badge badge-warning">Asignado</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- GESTIÓN DE GPS -->
        <div id="module-gps" class="module-content hidden">
            <div class="content-header">
                <div class="flex justify-between items-center">
                    <div>
                        <h2>Gestión de GPS</h2>
                        <p>Administra el inventario de GPS</p>
                    </div>
                    <button class="btn btn-primary" onclick="showModalGPS()">
                        <i class="fas fa-plus"></i> Agregar GPS
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="buscar-gps-tabla" placeholder="Buscar GPS..." onkeyup="filtrarTablaGPS()">
                </div>
                <div class="table-container">
                    <table id="tabla-gps">
                        <thead>
                            <tr>
                                <th>IMEI/Serie</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- GESTIÓN DE CUSTODIOS -->
        <div id="module-custodios" class="module-content hidden">
            <div class="content-header">
                <div class="flex justify-between items-center">
                    <div>
                        <h2>Gestión de Custodios</h2>
                        <p>Administra el personal autorizado</p>
                    </div>
                    <button class="btn btn-primary" onclick="showModalCustodio()">
                        <i class="fas fa-plus"></i> Agregar Custodio
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="buscar-custodio-tabla" placeholder="Buscar custodio..." onkeyup="filtrarTablaCustodios()">
                </div>
                <div class="table-container">
                    <table id="tabla-custodios">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Teléfono</th>
                                <th>Cargo</th>
                                <th>GPS Asignados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ASIGNAR GPS -->
        <div id="module-asignar" class="module-content hidden">
            <div class="content-header">
                <h2>Asignar GPS a Custodio</h2>
                <p>Registra la entrega de un equipo GPS</p>
            </div>
            <div class="card">
                <div class="form-header">
                    <h3>📡 Nueva Asignación de GPS</h3>
                </div>
                <form id="form-asignar" onsubmit="asignarGPS(event)" style="padding: 2rem;">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Disponible</label>
                        <select class="form-select" name="gpsId" required>
                            <option value="">Seleccione un GPS</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Custodio Responsable</label>
                        <select class="form-select" name="custodioId" required>
                            <option value="">Seleccione custodio</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-building"></i> Cliente/Institución</label>
                        <input type="text" class="form-input" name="cliente" placeholder="Ej: Policía Nacional" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-pin"></i> Origen</label>
                        <input type="text" class="form-input" name="origen" placeholder="Ej: Almacén Central" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Destino</label>
                        <input type="text" class="form-input" name="destino" placeholder="Ej: Zona de Patrullaje" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora</label>
                        <input type="datetime-local" class="form-input" name="fechaAsignacion" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones</label>
                        <textarea class="form-textarea" name="observaciones" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> Asignar GPS
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RETORNAR GPS -->
        <div id="module-retornar" class="module-content hidden">
            <div class="content-header">
                <h2>Retornar GPS</h2>
                <p>Registra la devolución de un equipo GPS</p>
            </div>
            <div class="card">
                <div class="form-header">
                    <h3>🔄 Retorno de GPS</h3>
                </div>
                <form id="form-retornar" onsubmit="retornarGPS(event)" style="padding: 2rem;">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Asignado</label>
                        <select class="form-select" name="asignacionId" required>
                            <option value="">Seleccione GPS a retornar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Retorno</label>
                        <input type="datetime-local" class="form-input" name="fechaRetorno" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clipboard-check"></i> Estado del GPS</label>
                        <select class="form-select" name="estadoGPS" required>
                            <option value="">Seleccione estado</option>
                            <option value="perfecto">✅ Perfecto Estado</option>
                            <option value="bueno">👍 Buen Estado</option>
                            <option value="regular">⚠️ Estado Regular</option>
                            <option value="dañado">❌ Dañado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones</label>
                        <textarea class="form-textarea" name="observacionesRetorno" placeholder="Describa el estado del GPS..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-check-circle"></i> Registrar Retorno
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CONSULTAR GPS -->
        <div id="module-consulta" class="module-content hidden">
            <div class="content-header">
                <h2>Consultar Estado de GPS</h2>
                <p>Busca información sobre un GPS</p>
            </div>

            <div class="card" style="max-width: 600px;">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-search"></i> IMEI</label>
                    <div class="flex gap-2">
                        <input type="text" class="form-input" id="buscar-imei-consulta" placeholder="Ingrese IMEI del GPS">
                        <button type="button" class="btn btn-primary" onclick="consultarGPS()">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </div>

            <div id="resultado-consulta" class="hidden"></div>
        </div>

        <!-- HISTORIAL -->
        <div id="module-historial" class="module-content hidden">
            <div class="content-header">
                <h2>Historial Completo</h2>
                <p>Registro de todos los movimientos de GPS</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom: 1rem;">Todas las Asignaciones</h3>
                <div class="table-container">
                    <table id="tabla-historial">
                        <thead>
                            <tr>
                                <th>IMEI</th>
                                <th>Custodio</th>
                                <th>Cliente</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MISIONES -->
        <div id="module-misiones" class="module-content hidden">
            <div class="content-header">
                <h2>📋 Gestión de Misiones</h2>
                <p>Control y seguimiento de todas las misiones</p>
            </div>

            <div class="card">
                <div style="margin-bottom: 1.5rem;">
                    <input type="text" class="form-input" id="buscar-mision" placeholder="Buscar misión..." onkeyup="filtrarMisiones()">
                </div>

                <div class="table-container">
                    <table id="tabla-misiones">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Misión</th>
                                <th>Custodio</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- MODALES -->
    <div class="modal" id="modal-gps">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-satellite-dish"></i> Agregar Nuevo GPS</h3>
                <button class="close-modal" onclick="closeModal('modal-gps')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="form-gps" onsubmit="agregarGPS(event)">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-barcode"></i> IMEI</label>
                        <input type="text" class="form-input" name="imei" required placeholder="Ej: 123456789012345">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-building"></i> Marca</label>
                            <input type="text" class="form-input" name="marca" required placeholder="Ej: Garmin">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tag"></i> Modelo</label>
                            <input type="text" class="form-input" name="modelo" required placeholder="Ej: GPS-200">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gps')" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="modal-custodio">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-shield"></i> Agregar Nuevo Custodio</h3>
                <button class="close-modal" onclick="closeModal('modal-custodio')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="form-custodio" onsubmit="agregarCustodio(event)">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Nombre Completo</label>
                        <input type="text" class="form-input" name="nombre" required placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="tel" class="form-input" name="telefono" required placeholder="Ej: +504 9999-9999">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-briefcase"></i> Cargo</label>
                        <input type="text" class="form-input" name="cargo" required placeholder="Ej: Oficial">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-custodio')" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let gpsDispositivos = [];
        let custodios = [];
        let asignaciones = [];
        let misiones = [];

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosUsuario();
            cargarDatosDelServidor();
        });

        function cargarDatosUsuario() {
            document.getElementById('user-menu-nombre').textContent = '<?php echo htmlspecialchars($usuario_nombre); ?>';
            document.getElementById('dropdown-nombre').textContent = '<?php echo htmlspecialchars($usuario_nombre); ?>';
        }

        async function cargarDatosDelServidor() {
            try {
                const [gpsRes, custodiosRes, asignacionesRes] = await Promise.all([
                    fetch('api/get_gps.php'),
                    fetch('api/get_custodios.php'),
                    fetch('api/get_asignaciones.php')
                ]);

                gpsDispositivos = await gpsRes.json();
                custodios = await custodiosRes.json();
                asignaciones = await asignacionesRes.json();

                actualizarTodo();
            } catch (error) {
                console.error('Error cargando datos:', error);
            }
        }

        function actualizarTodo() {
            actualizarDashboard();
            actualizarTablaGPS();
            actualizarTablaCustodios();
            actualizarTablaHistorial();
            cargarSelectores();
        }

        function actualizarDashboard() {
            const disponibles = gpsDispositivos.filter(g => g.estado === 'disponible').length;
            const asignados = gpsDispositivos.filter(g => g.estado === 'asignado').length;
            const total = gpsDispositivos.length;

            document.getElementById('stat-disponibles').textContent = disponibles;
            document.getElementById('stat-asignados').textContent = asignados;
            document.getElementById('stat-total').textContent = total;
        }

        function actualizarTablaGPS() {
            const tbody = document.querySelector('#tabla-gps tbody');
            if (gpsDispositivos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;"><div class="empty-state"><i class="fas fa-satellite-dish"></i><h3>No hay GPS</h3></div></td></tr>';
                return;
            }

            let html = '';
            gpsDispositivos.forEach(gps => {
                const estadoClass = gps.estado === 'disponible' ? 'badge-success' : 'badge-warning';
                html += `<tr>
                    <td style="font-family: monospace;">${gps.imei}</td>
                    <td>${gps.marca}</td>
                    <td>${gps.modelo}</td>
                    <td><span class="badge ${estadoClass}">${gps.estado}</span></td>
                    <td>
                        <button class="btn" style="padding: 0.5rem; background: #fbbf24; color: #000;" onclick="editarGPS(${gps.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger" style="padding: 0.5rem;" onclick="eliminarGPS(${gps.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function actualizarTablaCustodios() {
            const tbody = document.querySelector('#tabla-custodios tbody');
            if (custodios.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;"><div class="empty-state"><i class="fas fa-users"></i><h3>No hay custodios</h3></div></td></tr>';
                return;
            }

            let html = '';
            custodios.forEach(custodio => {
                const gpsAsignados = asignaciones.filter(a => a.custodio_id == custodio.id && a.estado === 'asignado').length;
                html += `<tr>
                    <td>${custodio.nombre}</td>
                    <td>${custodio.telefono}</td>
                    <td>${custodio.cargo}</td>
                    <td><span class="badge badge-info">${gpsAsignados}</span></td>
                    <td>
                        <button class="btn" style="padding: 0.5rem; background: #fbbf24; color: #000;" onclick="editarCustodio(${custodio.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger" style="padding: 0.5rem;" onclick="eliminarCustodio(${custodio.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function actualizarTablaHistorial() {
            const tbody = document.querySelector('#tabla-historial tbody');
            if (asignaciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;"><div class="empty-state"><i class="fas fa-history"></i><h3>No hay historial</h3></div></td></tr>';
                return;
            }

            let html = '';
            asignaciones.forEach(asignacion => {
                const estadoClass = asignacion.estado === 'asignado' ? 'badge-warning' : 'badge-success';
                html += `<tr>
                    <td style="font-family: monospace;">${asignacion.imei}</td>
                    <td>${asignacion.custodio_nombre}</td>
                    <td>${asignacion.cliente}</td>
                    <td>${asignacion.origen}</td>
                    <td>${asignacion.destino}</td>
                    <td>${asignacion.fecha_asignacion}</td>
                    <td><span class="badge ${estadoClass}">${asignacion.estado}</span></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function cargarSelectores() {
            // Selectores de GPS disponibles
            document.querySelectorAll('select[name="gpsId"]').forEach(select => {
                const disponibles = gpsDispositivos.filter(g => g.estado === 'disponible');
                select.innerHTML = '<option value="">Seleccione un GPS</option>';
                disponibles.forEach(g => {
                    select.innerHTML += `<option value="${g.id}">${g.imei} - ${g.marca} ${g.modelo}</option>`;
                });
            });

            // Selectores de custodios
            document.querySelectorAll('select[name="custodioId"]').forEach(select => {
                select.innerHTML = '<option value="">Seleccione custodio</option>';
                custodios.forEach(c => {
                    select.innerHTML += `<option value="${c.id}">${c.nombre}</option>`;
                });
            });

            // Selector de retorno
            const selectRetorno = document.querySelector('select[name="asignacionId"]');
            if (selectRetorno) {
                const activos = asignaciones.filter(a => a.estado === 'asignado');
                selectRetorno.innerHTML = '<option value="">Seleccione GPS</option>';
                activos.forEach(a => {
                    selectRetorno.innerHTML += `<option value="${a.id}">${a.imei}</option>`;
                });
            }
        }

        async function agregarGPS(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const response = await fetch('api/add_gps.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ GPS agregado');
                    closeModal('modal-gps');
                    await cargarDatosDelServidor();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error al agregar GPS');
            }
        }

        async function agregarCustodio(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const response = await fetch('api/add_custodio.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ Custodio agregado');
                    closeModal('modal-custodio');
                    await cargarDatosDelServidor();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error al agregar custodio');
            }
        }

        async function asignarGPS(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const response = await fetch('api/assign_gps.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ GPS asignado');
                    event.target.reset();
                    await cargarDatosDelServidor();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error al asignar GPS');
            }
        }

        async function retornarGPS(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const response = await fetch('api/return_gps.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ GPS retornado');
                    event.target.reset();
                    await cargarDatosDelServidor();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error al retornar GPS');
            }
        }

        function consultarGPS() {
            const imei = document.getElementById('buscar-imei-consulta').value.trim();
            if (!imei) {
                alert('Ingrese un IMEI');
                return;
            }

            const gps = gpsDispositivos.find(g => g.imei.toLowerCase().includes(imei.toLowerCase()));
            if (!gps) {
                alert('GPS no encontrado');
                return;
            }

            let html = `
                <div class="card">
                    <h3>Información del GPS</h3>
                    <p><strong>IMEI:</strong> ${gps.imei}</p>
                    <p><strong>Marca:</strong> ${gps.marca}</p>
                    <p><strong>Modelo:</strong> ${gps.modelo}</p>
                    <p><strong>Estado:</strong> ${gps.estado}</p>
                </div>
            `;

            const asignacion = asignaciones.find(a => a.gps_id == gps.id && a.estado === 'asignado');
            if (asignacion) {
                html += `
                    <div class="card">
                        <h3>Asignación Actual</h3>
                        <p><strong>Custodio:</strong> ${asignacion.custodio_nombre}</p>
                        <p><strong>Cliente:</strong> ${asignacion.cliente}</p>
                        <p><strong>Teléfono:</strong> ${asignacion.custodio_telefono}</p>
                        <p><strong>Fecha:</strong> ${asignacion.fecha_asignacion}</p>
                    </div>
                `;
            }

            document.getElementById('resultado-consulta').innerHTML = html;
            document.getElementById('resultado-consulta').classList.remove('hidden');
        }

        function editarGPS(id) {
            const gps = gpsDispositivos.find(g => g.id == id);
            if (!gps) return;
            document.querySelector('#form-gps input[name="imei"]').value = gps.imei;
            document.querySelector('#form-gps input[name="marca"]').value = gps.marca;
            document.querySelector('#form-gps input[name="modelo"]').value = gps.modelo;
            document.getElementById('modal-gps').classList.add('active');
        }

        function editarCustodio(id) {
            const custodio = custodios.find(c => c.id == id);
            if (!custodio) return;
            document.querySelector('#form-custodio input[name="nombre"]').value = custodio.nombre;
            document.querySelector('#form-custodio input[name="telefono"]').value = custodio.telefono;
            document.querySelector('#form-custodio input[name="cargo"]').value = custodio.cargo;
            document.getElementById('modal-custodio').classList.add('active');
        }

        async function eliminarGPS(id) {
            if (!confirm('¿Está seguro?')) return;
            try {
                const response = await fetch('api/delete_gps.php', {
                    method: 'POST',
                    body: new FormData(Object.assign(document.createElement('form'), {
                        elements: {
                            id: { value: id }
                        }
                    }))
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ GPS eliminado');
                    await cargarDatosDelServidor();
                }
            } catch (error) {
                alert('❌ Error');
            }
        }

        async function eliminarCustodio(id) {
            if (!confirm('¿Está seguro?')) return;
            try {
                const response = await fetch('api/delete_custodio.php', {
                    method: 'POST',
                    body: new FormData(Object.assign(document.createElement('form'), {
                        elements: {
                            id: { value: id }
                        }
                    }))
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ Custodio eliminado');
                    await cargarDatosDelServidor();
                }
            } catch (error) {
                alert('❌ Error');
            }
        }

        function filtrarTablaGPS() {
            const term = document.getElementById('buscar-gps-tabla').value.toLowerCase();
            document.querySelectorAll('#tabla-gps tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        }

        function filtrarTablaCustodios() {
            const term = document.getElementById('buscar-custodio-tabla').value.toLowerCase();
            document.querySelectorAll('#tabla-custodios tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        }

        function filtrarMisiones() {
            const term = document.getElementById('buscar-mision').value.toLowerCase();
            document.querySelectorAll('#tabla-misiones tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        }

        function showModalGPS() {
            document.getElementById('form-gps').reset();
            document.getElementById('modal-gps').classList.add('active');
        }

        function showModalCustodio() {
            document.getElementById('form-custodio').reset();
            document.getElementById('modal-custodio').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showModule(moduleName) {
            document.querySelectorAll('.module-content').forEach(m => m.classList.add('hidden'));
            document.getElementById('module-' + moduleName).classList.remove('hidden');
            
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            event.target.closest('.nav-item').classList.add('active');
        }

        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            document.getElementById('theme-icon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            document.getElementById('theme-text').textContent = isDark ? 'Tema Claro' : 'Tema Oscuro';
            if (document.getElementById('theme-icon-menu')) {
                document.getElementById('theme-icon-menu').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                document.getElementById('theme-text-menu').textContent = isDark ? 'Modo Claro' : 'Modo Oscuro';
            }
        }

        function toggleNotificaciones() {
            document.getElementById('notification-panel').classList.toggle('active');
        }

        function toggleUserMenu() {
            document.getElementById('user-dropdown').classList.toggle('hidden');
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function cerrarSesion() {
            if (confirm('¿Cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
            if (!e.target.closest('.user-menu-container')) {
                const dropdown = document.getElementById('user-dropdown');
                if (dropdown) dropdown.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
s.textContent.toLowerCase();let i=!0;e&&!a.includes(e)&&(i=!1),t&&!a.includes(t.replace('_',' '))&&(i=!1),o&&!a.includes(o)&&(i=!1),s.style.display=i?'':'none'})}
    </script>
</body>
</html>