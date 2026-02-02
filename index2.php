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
        'asignaciones_activas' => 0
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

        .nav-item:nth-child(1) i {
            color: #3b82f6;
        }

        .nav-item:nth-child(1):hover {
            background: #dbeafe;
        }

        .nav-item:nth-child(2) i {
            color: #f59e0b;
        }

        .nav-item:nth-child(2):hover {
            background: #fef3c7;
        }

        .nav-item:nth-child(3) i {
            color: #8b5cf6;
        }

        .nav-item:nth-child(3):hover {
            background: #ede9fe;
        }

        .nav-item:nth-child(4) i {
            color: #ec4899;
        }

        .nav-item:nth-child(4):hover {
            background: #fce7f3;
        }

        .nav-item:nth-child(5) i {
            color: #10b981;
        }

        .nav-item:nth-child(5):hover {
            background: #dcfce7;
        }

        .nav-item:nth-child(6) i {
            color: #06b6d4;
        }

        .nav-item:nth-child(6):hover {
            background: #cffafe;
        }

        .nav-item:nth-child(7) i {
            color: #f97316;
        }

        .nav-item:nth-child(7):hover {
            background: #ffedd5;
        }

        .nav-item:nth-child(8) i {
            color: #6366f1;
        }

        .nav-item:nth-child(8):hover {
            background: #e0e7ff;
        }

        .nav-item:nth-child(9) i {
            color: #ef4444;
        }

        .nav-item:nth-child(9):hover {
            background: #fee2e2;
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

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 768px) {
            .table-container {
                max-height: 400px;
            }
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
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

        th,
        td {
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

        tr:last-child td {
            border-bottom: none;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: var(--bg-secondary);
            color: var(--danger);
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

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
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

        .empty-state p {
            color: var(--text-secondary);
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

        .mb-4 {
            margin-bottom: 1rem;
        }

        .hidden {
            display: none;
        }

        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
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

        .form-card form {
            padding: 2rem;
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        body.dark-mode .alert-info {
            background: #1e3a8a;
            color: #dbeafe;
        }

        body.dark-mode .alert-success {
            background: #14532d;
            color: #dcfce7;
        }

        body.dark-mode .alert-danger {
            background: #7f1d1d;
            color: #fee2e2;
        }

        .ubicacion-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .ubicacion-instalaciones {
            background: #dcfce7;
            color: #166534;
        }

        .ubicacion-campo {
            background: #fef3c7;
            color: #92400e;
        }

        body.dark-mode .ubicacion-instalaciones {
            background: #166534;
            color: #dcfce7;
        }

        body.dark-mode .ubicacion-campo {
            background: #92400e;
            color: #fef3c7;
        }

        .user-menu-container {
            position: relative;
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

        .user-menu-btn:hover {
            background: var(--bg-primary);
            box-shadow: var(--shadow);
        }

        .user-menu-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: var(--success);
            border: 2px solid var(--bg-card);
            border-radius: 50%;
        }

        .user-menu-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .user-menu-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-menu-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
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

        .user-dropdown-header {
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .user-dropdown-avatar {
            position: relative;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .status-indicator-large {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 16px;
            height: 16px;
            background: var(--success);
            border: 3px solid var(--bg-card);
            border-radius: 50%;
        }

        .user-dropdown-name {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .user-dropdown-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .user-dropdown-divider {
            height: 1px;
            background: var(--border);
        }

        .user-dropdown-item {
            width: 100%;
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }

        .user-dropdown-item:hover {
            background: var(--bg-secondary);
        }

        .user-dropdown-item i {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
            color: var(--text-secondary);
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

        .notification-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .notification-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .notification-item:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow);
        }

        .notification-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .notification-item-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .notification-item-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .notification-item-body {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .notif-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 9999px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }

        .notif-badge.hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .notification-panel {
                width: 100%;
                right: -100%;
            }

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

            .user-menu-info span {
                display: none;
            }

            .top-bar-actions .btn span {
                display: none;
            }
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 100;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .sidebar-toggle-btn {
            position: fixed;
            left: 10px;
            top: 20px;
            width: 45px;
            height: 45px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.3rem;
            z-index: 102;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .sidebar-toggle-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-item span {
            display: none;
        }

        .sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 1rem;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 1rem;
            text-align: center;
        }

        .sidebar.collapsed .logo-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            margin: 0 auto;
        }

        .top-bar.sidebar-collapsed {
            left: 80px;
        }

        .main-content.sidebar-collapsed {
            margin-left: 80px;
        }

        @media (max-width: 768px) {
            .sidebar.collapsed {
                width: 250px;
            }

            .sidebar.collapsed .logo-text,
            .sidebar.collapsed .nav-item span {
                display: inline;
            }

            .top-bar.sidebar-collapsed {
                left: 0;
            }

            .main-content.sidebar-collapsed {
                margin-left: 0;
            }
        }

        .alert-card {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #ef4444;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.1);
            animation: pulseAlert 2s infinite;
        }

        body.dark-mode .alert-card {
            background: linear-gradient(135deg, #7f1d1d, #991b1b);
            border-color: #dc2626;
        }

        @keyframes pulseAlert {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }
        }

        .alert-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .alert-icon {
            font-size: 2rem;
            animation: shake 0.5s infinite;
        }

        @keyframes shake {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(-10deg);
            }

            75% {
                transform: rotate(10deg);
            }
        }

        .alert-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #991b1b;
        }

        body.dark-mode .alert-title {
            color: #fca5a5;
        }

        .alert-subtitle {
            font-size: 0.9rem;
            color: #7f1d1d;
            margin-top: 0.25rem;
        }

        body.dark-mode .alert-subtitle {
            color: #fecaca;
        }

        .alert-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .alert-item {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
        }

        body.dark-mode .alert-item {
            background: #450a0a;
            border-left-color: #dc2626;
        }

        .alert-item-label {
            font-size: 0.75rem;
            color: #991b1b;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        body.dark-mode .alert-item-label {
            color: #fca5a5;
        }

        .alert-item-value {
            font-size: 1rem;
            font-weight: 700;
            color: #7f1d1d;
        }

        body.dark-mode .alert-item-value {
            color: #fee2e2;
        }

        .alert-days {
            font-size: 1.5rem;
            color: #ef4444;
        }

        .alert-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        /* ==================== ESTILOS PARA MÓDULO DE MISIONES ==================== */
        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-icon.teal {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .stat-icon.indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }

        /* Estados de misión */
        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
        }

        .estado-en_curso {
            background: #dbeafe;
            color: #1e40af;
        }

        .estado-completada {
            background: #dcfce7;
            color: #166534;
        }

        .estado-cancelada {
            background: #fee2e2;
            color: #991b1b;
        }

        body.dark-mode .estado-pendiente {
            background: #92400e;
            color: #fef3c7;
        }

        body.dark-mode .estado-en_curso {
            background: #1e40af;
            color: #dbeafe;
        }

        body.dark-mode .estado-completada {
            background: #166534;
            color: #dcfce7;
        }

        body.dark-mode .estado-cancelada {
            background: #991b1b;
            color: #fee2e2;
        }

        /* Prioridades de misión */
        .priority-baja {
            color: #10b981;
        }

        .priority-media {
            color: #f59e0b;
        }

        .priority-alta {
            color: #ef4444;
        }

        .priority-critica {
            color: #dc2626;
            font-weight: 700;
        }

        /* Rating Stars */
        .rating-stars {
            display: inline-flex;
            gap: 0.25rem;
        }

        .rating-stars i {
            color: #fbbf24;
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--info));
            transition: width 0.3s ease;
        }

        /* Performance Card para estadísticas de custodios */
        .performance-card {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-secondary));
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }

        .performance-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .performance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .custodio-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .custodio-cargo {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .performance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .performance-stat {
            text-align: center;
            padding: 0.75rem;
            background: var(--bg-card);
            border-radius: 10px;
        }

        .performance-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .performance-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
                <button class="sidebar-toggle-btn" onclick="toggleSidebarCollapse()" title="Contraer/Expandir">
                    <i class="fas fa-chevron-left" id="toggle-icon"></i>
                </button>
            </div>
        </div>

        <div class="top-bar-actions">
            <button class="theme-toggle-top" onclick="toggleTheme()">
                <i class="fas fa-moon" id="theme-icon"></i>
                <span id="theme-text">Tema Oscuro</span>
            </button>

            <button class="btn btn-primary" onclick="toggleNotificaciones()" style="position: relative; padding: 0.75rem 1rem;">
                <i class="fas fa-bell"></i>
                <span id="notif-badge" class="notif-badge hidden">0</span>
            </button>

            <div class="user-menu-container">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <div class="user-menu-avatar">
                        <i class="fas fa-user"></i>
                        <span class="status-indicator"></span>
                    </div>
                    <div class="user-menu-info">
                        <span class="user-menu-name" id="user-menu-nombre">Admin</span>
                        <span class="user-menu-role" id="user-menu-rol">Administrador</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="user-dropdown hidden" id="user-dropdown">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-avatar">
                            <i class="fas fa-user"></i>
                            <span class="status-indicator-large"></span>
                        </div>
                        <div>
                            <p class="user-dropdown-name" id="dropdown-nombre">Admin</p>
                            <p class="user-dropdown-email" id="dropdown-email">admin@forza.hn</p>
                            <span class="badge badge-success" id="dropdown-rol">Administrador</span>
                        </div>
                    </div>

                    <div class="user-dropdown-divider"></div>

                    <button class="user-dropdown-item" onclick="toggleTheme()">
                        <i class="fas fa-moon" id="theme-icon-menu"></i>
                        <span id="theme-text-menu">Modo Oscuro</span>
                    </button>

                    <button class="user-dropdown-item" onclick="irAPanelAdmin()">
                        <i class="fas fa-user-shield"></i>
                        <span>Panel Admin</span>
                    </button>

                    <button class="user-dropdown-item" onclick="cerrarSesion()" style="color: var(--danger);">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="notification-panel" id="notification-panel">
        <div class="notification-header">
            <h3><i class="fas fa-bell"></i> Notificaciones</h3>
            <button class="btn btn-secondary" onclick="toggleNotificaciones()" style="padding: 0.5rem 1rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notification-list" id="notification-list">
            <div class="notification-item">
                <div class="notification-item-header">
                    <span class="notification-item-title">🔔 Sistema iniciado</span>
                    <span class="notification-item-time">Hace 1 min</span>
                </div>
                <div class="notification-item-body">
                    Bienvenido al sistema FORZA de Control de GPS
                </div>
            </div>
        </div>
    </div>

    <aside class="sidebar responsive" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="logo-text">
                    <h1>FORZA</h1>
                    <p>Control de GPS • HN</p>
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
            <button class="nav-item" onclick="showModule('alertas')">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Alertas Recuperación</span>
            </button>
            <button class="nav-item" onclick="showModule('misiones')">
                <i class="fas fa-tasks"></i>
                <span>📋 Misiones</span>
            </button>
            <button class="nav-item" onclick="showModule('nueva-mision')">
                <i class="fas fa-plus-circle"></i>
                <span>➕ Nueva Misión</span>
            </button>
            <button class="nav-item" onclick="showModule('estadisticas-misiones')">
                <i class="fas fa-chart-bar"></i>
                <span>📊 Estadísticas</span>
            </button>
            <button class="nav-item" onclick="toggleSidebarCollapse()" title="Contraer/Expandir" style="margin-top: auto;">
                <i class="fas fa-chevron-left" id="toggle-icon"></i>
                <span>Contraer</span>
            </button>
        </nav>
    </aside>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

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

            <div class="card">
                <h3 class="mb-4" style="font-size: 1.25rem; font-weight: 700;">GPS ACTUALMENTE ASIGNADOS</h3>
                <div class="table-container">
                    <table id="tabla-gps-asignados">
                        <thead>
                            <tr>
                                <th>IMEI/Serie</th>
                                <th>Modelo</th>
                                <th>Custodio</th>
                                <th>Fecha Asignación</th>
                                <th>Días Asignado</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($asignaciones)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center">
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
                                        <td><span class="ubicacion-badge ubicacion-campo"><i class="fas fa-map-marker-alt"></i> En Campo</span></td>
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
                    <input type="text" class="search-input" id="buscar-gps-tabla" placeholder="Buscar GPS por IMEI, marca, modelo..." onkeyup="filtrarTablaGPS()">
                </div>
                <div class="table-container">
                    <table id="tabla-gps">
                        <thead>
                            <tr>
                                <th>IMEI/Serie</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Estado</th>
                                <th>Ubicación</th>
                                <th>Custodio</th>
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
                    <input type="text" class="search-input" id="buscar-custodio-tabla" placeholder="Buscar custodio por nombre, teléfono..." onkeyup="filtrarTablaCustodios()">
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
            <div class="form-card">
                <div class="form-header">
                    <h3>📡 Nueva Asignación de GPS</h3>
                </div>
                <form id="form-asignar" onsubmit="asignarGPS(event)">
                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📋 Información General</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Disponible</label>
                                <select class="form-select" name="gpsId" required onchange="actualizarInfoGPS(this.value)">
                                    <option value="">Seleccione un GPS</option>
                                </select>
                                <p id="info-gps-asignar" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user"></i> Custodio Responsable</label>
                                <select class="form-select" name="custodioId" required onchange="actualizarInfoCustodio(this.value)">
                                    <option value="">Seleccione custodio</option>
                                </select>
                                <p id="info-custodio-asignar" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            </div>
                        </div>
                    </div>

                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">🏢 Datos de la Asignación</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-building"></i> Cliente/Institución</label>
                                <input type="text" class="form-input" name="cliente" placeholder="Ej: Policía Nacional, Empresa XYZ" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Asignación</label>
                                <input type="datetime-local" class="form-input" name="fechaAsignacion" required>
                            </div>
                        </div>
                    </div>

                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📍 Ubicación</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-pin"></i> Origen (Salida desde)</label>
                                <input type="text" class="form-input" name="origen" placeholder="Ej: Almacén Central, Oficina Principal" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Destino/Zona de Trabajo</label>
                                <input type="text" class="form-input" name="destino" placeholder="Ej: Patrullaje Zona Norte, Zona de Cobertura" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones Adicionales</label>
                        <textarea class="form-textarea" name="observaciones" placeholder="Notas sobre la asignación, instrucciones especiales, etc..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1; padding: 1rem; font-size: 1rem;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 1rem; font-size: 1rem;">
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
            <div class="form-card">
                <div class="form-header">
                    <h3>🔄 Retorno de GPS</h3>
                </div>
                <form id="form-retornar" onsubmit="retornarGPS(event)">
                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📡 Seleccionar GPS a Retornar</h4>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Asignado</label>
                            <select class="form-select" name="asignacionId" required onchange="mostrarInfoRetorno(this.value)">
                                <option value="">Seleccione GPS a retornar</option>
                            </select>
                        </div>
                        <div id="info-retorno-bloque" class="hidden" style="margin-top: 1rem; padding: 1rem; background: var(--bg-card); border-radius: 12px; border-left: 4px solid var(--info);">
                            <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.75rem;">ℹ️ Información de la Asignación</p>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">GPS (IMEI)</p>
                                    <p id="retorno-imei" style="font-family: monospace; font-weight: 700;">-</p>
                                </div>
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Cliente</p>
                                    <p id="retorno-cliente" style="font-weight: 700;">-</p>
                                </div>
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Custodio</p>
                                    <p id="retorno-custodio" style="font-weight: 700;">-</p>
                                </div>
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Días Asignado</p>
                                    <p id="retorno-dias" style="font-weight: 700;">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📅 Retorno</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Retorno</label>
                                <input type="datetime-local" class="form-input" name="fechaRetorno" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-clipboard-check"></i> Estado del GPS al Retornar</label>
                                <select class="form-select" name="estadoGPS" required>
                                    <option value="">Seleccione estado</option>
                                    <option value="perfecto">✅ Perfecto Estado</option>
                                    <option value="bueno">👍 Buen Estado</option>
                                    <option value="regular">⚠️ Estado Regular</option>
                                    <option value="dañado">❌ Dañado - Requiere Reparación</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones del Retorno</label>
                        <textarea class="form-textarea" name="observacionesRetorno" placeholder="Describa el estado del GPS, daños encontrados, novedades, etc..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1; padding: 1rem; font-size: 1rem;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-success" style="flex: 1; padding: 1rem; font-size: 1rem;">
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
                <p>Busca información completa sobre un GPS</p>
            </div>

            <div class="card" style="max-width: 600px;">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-search"></i> IMEI / Número de Serie
                    </label>
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
                <h3 style="margin-bottom: 1rem; font-size: 1.25rem; font-weight: 700;">Todas las Asignaciones</h3>
                <div class="table-container">
                    <table id="tabla-historial">
                        <thead>
                            <tr>
                                <th>IMEI/Serie</th>
                                <th>Custodio</th>
                                <th>Cliente</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Fecha Asignación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ALERTAS DE RECUPERACIÓN -->
        <div id="module-alertas" class="module-content hidden">
            <div class="content-header">
                <h2>⚠️ Alertas de Recuperación de GPS</h2>
                <p>GPS que requieren seguimiento para su devolución</p>
            </div>

            <div id="alertas-container"></div>
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
                        <label class="form-label"><i class="fas fa-barcode"></i> IMEI / Número de Serie</label>
                        <input type="text" class="form-input" name="imei" required placeholder="Ej: 123456789012345">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-building"></i> Marca</label>
                            <input type="text" class="form-input" name="marca" required placeholder="Ej: Garmin, TomTom">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tag"></i> Modelo</label>
                            <input type="text" class="form-input" name="modelo" required placeholder="Ej: GPS-200">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Descripción</label>
                        <textarea class="form-textarea" name="descripcion" placeholder="Características adicionales del GPS..."></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gps')" style="flex: 1; padding: 1rem;">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 1rem;">
                            <i class="fas fa-check"></i> Guardar GPS
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
                        <input type="text" class="form-input" name="nombre" required placeholder="Ej: Juan Pérez López">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="tel" class="form-input" name="telefono" required placeholder="Ej: +504 9999-9999">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-briefcase"></i> Cargo</label>
                        <input type="text" class="form-input" name="cargo" required placeholder="Ej: Oficial, Agente">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-custodio')" style="flex: 1; padding: 1rem;">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 1rem;">
                            <i class="fas fa-check"></i> Guardar Custodio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let sidebarCollapsed = false;
        let gpsDispositivos = [];
        let custodios = [];
        let asignaciones = [];

        function toggleSidebarCollapse() {
            sidebarCollapsed = !sidebarCollapsed;
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const topBar = document.querySelector('.top-bar');
            const toggleIcon = document.getElementById('toggle-icon');

            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
                topBar.classList.add('sidebar-collapsed');
                toggleIcon.className = 'fas fa-chevron-right';
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                topBar.classList.remove('sidebar-collapsed');
                toggleIcon.className = 'fas fa-chevron-left';
            }
        }

        function toggleNotificaciones() {
            const panel = document.getElementById('notification-panel');
            if (panel) {
                panel.classList.toggle('active');
            }
        }

        function toggleUserMenu() {
            document.getElementById('user-dropdown').classList.toggle('hidden');
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');

            document.getElementById('theme-icon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            document.getElementById('theme-text').textContent = isDark ? 'Tema Claro' : 'Tema Oscuro';
            document.getElementById('theme-icon-menu').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            document.getElementById('theme-text-menu').textContent = isDark ? 'Modo Claro' : 'Modo Oscuro';
        }

        function irAPanelAdmin() {
            window.location.href = 'panel.php';
        }

        function cerrarSesion() {
            if (confirm('¿Está seguro que desea cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }

        function showModalGPS() {
            document.getElementById('form-gps').reset();
            document.getElementById('modal-gps').classList.add('active');
            document.querySelector('#modal-gps h3').textContent = '🛰️ Agregar Nuevo GPS';
            document.getElementById('form-gps').onsubmit = agregarGPS;
        }

        function showModalCustodio() {
            document.getElementById('form-custodio').reset();
            document.getElementById('modal-custodio').classList.add('active');
            document.querySelector('#modal-custodio h3').textContent = '👤 Agregar Nuevo Custodio';
            document.getElementById('form-custodio').onsubmit = agregarCustodio;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showModule(moduleName) {
            document.querySelectorAll('.module-content').forEach(module => module.classList.add('hidden'));
            document.getElementById('module-' + moduleName).classList.remove('hidden');
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            event.target.closest('.nav-item').classList.add('active');

            if (moduleName === 'alertas') {
                actualizarAlertasRecuperacion();
            }
        }

        function agregarNotificacion(titulo, mensaje) {
            const notificationList = document.getElementById('notification-list');
            const notifBadge = document.getElementById('notif-badge');

            const notifItem = document.createElement('div');
            notifItem.className = 'notification-item';
            const notifId = 'notif-' + Date.now();
            notifItem.id = notifId;
            notifItem.innerHTML = `
                <div class="notification-item-header">
                    <span class="notification-item-title">🔔 ${titulo}</span>
                    <span class="notification-item-time">Ahora</span>
                </div>
                <div class="notification-item-body">${mensaje}</div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-success" onclick="confirmarNotificacion('${notifId}')" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                        <i class="fas fa-check"></i> Confirmar lectura
                    </button>
                </div>
            `;

            notificationList.insertBefore(notifItem, notificationList.firstChild);

            const currentCount = parseInt(notifBadge.textContent) || 0;
            notifBadge.textContent = currentCount + 1;
            notifBadge.classList.remove('hidden');
        }

        function confirmarNotificacion(notifId) {
            const notifItem = document.getElementById(notifId);
            if (notifItem) {
                notifItem.style.opacity = '0.5';
                notifItem.style.textDecoration = 'line-through';
                const notifBadge = document.getElementById('notif-badge');
                const currentCount = parseInt(notifBadge.textContent) || 0;
                if (currentCount > 0) {
                    notifBadge.textContent = currentCount - 1;
                    if (currentCount - 1 === 0) {
                        notifBadge.classList.add('hidden');
                    }
                }
                setTimeout(() => {
                    notifItem.remove();
                }, 300);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Iniciando carga de datos');
            cargarDatosUsuario();
            solicitarPermisoNotificaciones();

            // Esperar un poco y luego cargar datos del servidor
            setTimeout(() => {
                cargarDatosDelServidor();
                iniciarVerificacionNotificaciones();
            }, 500);
        });

        window.addEventListener('beforeunload', function() {
            detenerVerificacionNotificaciones();
        });

        function cargarDatosUsuario() {
            document.getElementById('user-menu-nombre').textContent = '<?php echo htmlspecialchars($usuario_nombre); ?>';
            document.getElementById('dropdown-nombre').textContent = '<?php echo htmlspecialchars($usuario_nombre); ?>';
            document.getElementById('user-menu-rol').textContent = '<?php echo ucfirst(htmlspecialchars($usuario_rol)); ?>';
            document.getElementById('dropdown-rol').textContent = '<?php echo ucfirst(htmlspecialchars($usuario_rol)); ?>';
        }

        async function cargarDatosDelServidor() {
            try {
                const [gpsResponse, custodiosResponse, asignacionesResponse] = await Promise.all([
                    fetch('api/get_gps.php'),
                    fetch('api/get_custodios.php'),
                    fetch('api/get_asignaciones.php')
                ]);

                console.log('GPS Response:', gpsResponse);
                console.log('Custodios Response:', custodiosResponse);
                console.log('Asignaciones Response:', asignacionesResponse);

                const gpsData = await gpsResponse.json();
                const custodiosData = await custodiosResponse.json();
                const asignacionesData = await asignacionesResponse.json();

                console.log('GPS Data:', gpsData);
                console.log('Custodios Data:', custodiosData);
                console.log('Asignaciones Data:', asignacionesData);

                gpsDispositivos = Array.isArray(gpsData) ? gpsData : [];
                custodios = Array.isArray(custodiosData) ? custodiosData : [];
                asignaciones = Array.isArray(asignacionesData) ? asignacionesData : [];

                console.log('Variables globales actualizadas:', {
                    gpsDispositivos,
                    custodios,
                    asignaciones
                });
                actualizarTodo();
            } catch (error) {
                console.error('Error cargando datos del servidor:', error);
                gpsDispositivos = [];
                custodios = [];
                asignaciones = [];
                actualizarTodo();
            }
        }

        function actualizarTodo() {
            actualizarDashboard();
            actualizarTablaGPS();
            actualizarTablaCustodios();
            actualizarTablaHistorial();
            cargarSelectores();
            actualizarAlertasRecuperacion();
            verificarNotificacionesRecuperacion();
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
                    agregarNotificacion('Nuevo GPS agregado', `GPS ${formData.get('imei')} registrado exitosamente`);
                    event.target.reset();
                    closeModal('modal-gps');
                    await cargarDatosDelServidor();
                    alert('✅ GPS agregado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al agregar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
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
                    agregarNotificacion('Nuevo custodio agregado', `${formData.get('nombre')} registrado exitosamente`);
                    event.target.reset();
                    closeModal('modal-custodio');
                    await cargarDatosDelServidor();
                    alert('✅ Custodio agregado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al agregar custodio'));
                }
            } catch (error) {
                console.error('Error:', error);
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
                    agregarNotificacion('GPS Asignado', `GPS asignado exitosamente`);
                    event.target.reset();
                    await cargarDatosDelServidor();
                    alert('✅ GPS asignado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al asignar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
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
                    agregarNotificacion('GPS Retornado', `GPS retornado exitosamente`);
                    event.target.reset();
                    document.getElementById('info-retorno').classList.add('hidden');
                    await cargarDatosDelServidor();
                    alert('✅ Retorno registrado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al retornar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al retornar GPS');
            }
        }

        function actualizarDashboard() {
            console.log('Actualizando dashboard con:', {
                gpsDispositivos,
                asignaciones
            });

            const disponibles = Array.isArray(gpsDispositivos) ? gpsDispositivos.filter(g => g.estado === 'disponible').length : 0;
            const asignados = Array.isArray(gpsDispositivos) ? gpsDispositivos.filter(g => g.estado === 'asignado').length : 0;
            const total = Array.isArray(gpsDispositivos) ? gpsDispositivos.length : 0;

            document.getElementById('stat-disponibles').textContent = disponibles;
            document.getElementById('stat-asignados').textContent = asignados;
            document.getElementById('stat-total').textContent = total;

            const tbody = document.querySelector('#tabla-gps-asignados tbody');
            const asignacionesActivas = Array.isArray(asignaciones) ? asignaciones.filter(a => a.estado === 'asignado') : [];

            if (asignacionesActivas.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center">
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No hay GPS asignados</h3>
                        <p>Todos los GPS están disponibles</p>
                    </div>
                </td></tr>`;
                return;
            }

            let html = '';
            asignacionesActivas.forEach(asignacion => {
                const fechaObj = new Date(asignacion.fecha_asignacion);
                const fechaFormato = fechaObj.toLocaleString('es-HN');
                const diasAsignado = Math.floor((Date.now() - new Date(asignacion.fecha_asignacion)) / 86400000);

                html += `
                    <tr>
                        <td style="font-family: monospace;">${asignacion.imei || 'N/A'}</td>
                        <td>${asignacion.marca || '-'} ${asignacion.modelo || '-'}</td>
                        <td>${asignacion.custodio_nombre || 'N/A'}</td>
                        <td>${fechaFormato}</td>
                        <td>${diasAsignado} días</td>
                        <td><span class="ubicacion-badge ubicacion-campo"><i class="fas fa-map-marker-alt"></i> En Campo</span></td>
                        <td><span class="badge badge-warning">Asignado</span></td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        function actualizarTablaGPS() {
            console.log('Actualizando tabla GPS con datos:', gpsDispositivos);
            const tbody = document.querySelector('#tabla-gps tbody');

            if (!Array.isArray(gpsDispositivos) || gpsDispositivos.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center;"><div class="empty-state"><i class="fas fa-satellite-dish"></i><h3>No hay GPS registrados</h3><p>Comienza agregando tu primer GPS</p></div></td></tr>`;
                return;
            }

            let html = '';
            gpsDispositivos.forEach(gps => {
                const estadoClass = gps.estado === 'disponible' ? 'badge-success' : 'badge-warning';
                const estadoTexto = gps.estado === 'disponible' ? 'Disponible' : 'Asignado';
                const ubicacion = gps.estado === 'disponible' ?
                    '<span class="ubicacion-badge ubicacion-instalaciones"><i class="fas fa-building"></i> Instalaciones</span>' :
                    '<span class="ubicacion-badge ubicacion-campo"><i class="fas fa-map-marker-alt"></i> En Campo</span>';

                html += `<tr>
                    <td style="font-family: monospace;">${gps.imei || '-'}</td>
                    <td>${gps.marca || '-'}</td>
                    <td>${gps.modelo || '-'}</td>
                    <td><span class="badge ${estadoClass}">${estadoTexto}</span></td>
                    <td>${ubicacion}</td>
                    <td>-</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn" onclick="editarGPS(${gps.id})" style="padding: 0.5rem 1rem; background-color: #fbbf24; color: #000; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-danger" onclick="eliminarGPS(${gps.id})" style="padding: 0.5rem 1rem;">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function editarGPS(id) {
            const gps = gpsDispositivos.find(g => parseInt(g.id) === parseInt(id));
            if (!gps) {
                alert('GPS no encontrado');
                return;
            }

            document.getElementById('form-gps').reset();
            document.getElementById('modal-gps').classList.add('active');
            document.querySelector('#modal-gps h3').textContent = '✏️ Editar GPS';
            document.querySelector('#form-gps input[name="imei"]').value = gps.imei;
            document.querySelector('#form-gps input[name="marca"]').value = gps.marca;
            document.querySelector('#form-gps input[name="modelo"]').value = gps.modelo;
            document.querySelector('#form-gps textarea[name="descripcion"]').value = gps.descripcion || '';

            document.querySelector('#form-gps').onsubmit = function(event) {
                guardarGPS(event, id);
            };
        }

        async function guardarGPS(event, id) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('id', id);

            try {
                const response = await fetch('api/update_gps.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('GPS actualizado', `GPS actualizado correctamente`);
                    closeModal('modal-gps');
                    document.getElementById('form-gps').reset();
                    document.querySelector('#form-gps').onsubmit = agregarGPS;
                    await cargarDatosDelServidor();
                    alert('✅ GPS actualizado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al actualizar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al actualizar GPS');
            }
        }

        function actualizarTablaCustodios() {
            console.log('Actualizando tabla custodios con datos:', custodios);
            const tbody = document.querySelector('#tabla-custodios tbody');

            if (!Array.isArray(custodios) || custodios.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align: center;"><div class="empty-state"><i class="fas fa-users"></i><h3>No hay custodios registrados</h3><p>Agrega el primer custodio</p></div></td></tr>`;
                return;
            }

            let html = '';
            custodios.forEach(custodio => {
                const gpsAsignados = Array.isArray(asignaciones) ? asignaciones.filter(a => parseInt(a.custodio_id) === parseInt(custodio.id) && a.estado === 'asignado').length : 0;

                html += `<tr>
                    <td>${custodio.nombre || '-'}</td>
                    <td>${custodio.telefono || '-'}</td>
                    <td>${custodio.cargo || '-'}</td>
                    <td><span class="badge badge-info">${gpsAsignados}</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn" onclick="editarCustodio(${custodio.id})" style="padding: 0.5rem 1rem; background-color: #fbbf24; color: #000; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-danger" onclick="eliminarCustodio(${custodio.id})" style="padding: 0.5rem 1rem;">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function editarCustodio(id) {
            const custodio = custodios.find(c => parseInt(c.id) === parseInt(id));
            if (!custodio) {
                alert('Custodio no encontrado');
                return;
            }

            document.getElementById('form-custodio').reset();
            document.getElementById('modal-custodio').classList.add('active');
            document.querySelector('#modal-custodio h3').textContent = '✏️ Editar Custodio';
            document.querySelector('#form-custodio input[name="nombre"]').value = custodio.nombre;
            document.querySelector('#form-custodio input[name="telefono"]').value = custodio.telefono;
            document.querySelector('#form-custodio input[name="cargo"]').value = custodio.cargo;

            document.querySelector('#form-custodio').onsubmit = function(event) {
                guardarCustodio(event, id);
            };
        }

        async function guardarCustodio(event, id) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('id', id);

            try {
                const response = await fetch('api/update_custodio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('Custodio actualizado', `Custodio actualizado correctamente`);
                    closeModal('modal-custodio');
                    document.getElementById('form-custodio').reset();
                    document.querySelector('#form-custodio').onsubmit = agregarCustodio;
                    await cargarDatosDelServidor();
                    alert('✅ Custodio actualizado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al actualizar custodio'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al actualizar custodio');
            }
        }

        function actualizarTablaHistorial() {
            console.log('Actualizando tabla historial con datos:', asignaciones);
            const tbody = document.querySelector('#tabla-historial tbody');

            if (!Array.isArray(asignaciones) || asignaciones.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center;"><div class="empty-state"><i class="fas fa-history"></i><h3>No hay historial</h3><p>Las asignaciones aparecerán aquí</p></div></td></tr>`;
                return;
            }

            let html = '';
            asignaciones.slice().reverse().forEach(asignacion => {
                const estadoClass = asignacion.estado === 'asignado' ? 'badge-warning' : 'badge-success';
                const estadoTexto = asignacion.estado ? asignacion.estado.charAt(0).toUpperCase() + asignacion.estado.slice(1) : 'N/A';
                const fechaObj = new Date(asignacion.fecha_asignacion);
                const fechaFormato = fechaObj.toLocaleString('es-HN');

                html += `<tr>
                    <td style="font-family: monospace;">${asignacion.imei || 'N/A'}</td>
                    <td>${asignacion.custodio_nombre || 'N/A'}</td>
                    <td>${asignacion.cliente || '-'}</td>
                    <td>${asignacion.origen || '-'}</td>
                    <td>${asignacion.destino || '-'}</td>
                    <td>${fechaFormato}</td>
                    <td><span class="badge ${estadoClass}">${estadoTexto}</span></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function cargarSelectores() {
            const selectGPS = document.querySelector('#form-asignar select[name="gpsId"]');
            if (selectGPS && Array.isArray(gpsDispositivos)) {
                const disponibles = gpsDispositivos.filter(g => g.estado === 'disponible');
                selectGPS.innerHTML = '<option value="">Seleccione un GPS</option>';
                disponibles.forEach(g => {
                    selectGPS.innerHTML += `<option value="${g.id}">${g.imei} - ${g.marca} ${g.modelo}</option>`;
                });
            }

            document.querySelectorAll('select[name="custodioId"]').forEach(select => {
                select.innerHTML = '<option value="">Seleccione custodio</option>';
                if (Array.isArray(custodios)) {
                    custodios.forEach(c => {
                        select.innerHTML += `<option value="${c.id}">${c.nombre} - ${c.cargo}</option>`;
                    });
                }
            });

            const selectRetornar = document.querySelector('#form-retornar select[name="asignacionId"]');
            if (selectRetornar && Array.isArray(asignaciones)) {
                const activos = asignaciones.filter(a => a.estado === 'asignado');
                selectRetornar.innerHTML = '<option value="">Seleccione GPS</option>';
                activos.forEach(a => {
                    selectRetornar.innerHTML += `<option value="${a.id}">${a.imei} (${a.custodio_nombre})</option>`;
                });
            }
        }

        function actualizarInfoGPS(gpsId) {
            if (!gpsId) {
                document.getElementById('info-gps-asignar').textContent = '';
                return;
            }
            const gps = gpsDispositivos.find(g => parseInt(g.id) === parseInt(gpsId));
            if (gps) {
                document.getElementById('info-gps-asignar').textContent = `${gps.marca} ${gps.modelo} • IMEI: ${gps.imei}`;
            }
        }

        function actualizarInfoCustodio(custodioId) {
            if (!custodioId) {
                document.getElementById('info-custodio-asignar').textContent = '';
                return;
            }
            const custodio = custodios.find(c => parseInt(c.id) === parseInt(custodioId));
            if (custodio) {
                document.getElementById('info-custodio-asignar').textContent = `${custodio.cargo} • Teléfono: ${custodio.telefono}`;
            }
        }

        function mostrarInfoRetorno(id) {
            if (!id) {
                document.getElementById('info-retorno-bloque').classList.add('hidden');
                return;
            }

            const asignacion = Array.isArray(asignaciones) ? asignaciones.find(x => parseInt(x.id) === parseInt(id)) : null;
            if (!asignacion) {
                document.getElementById('info-retorno-bloque').classList.add('hidden');
                return;
            }

            const dias = Math.floor((Date.now() - new Date(asignacion.fecha_asignacion)) / 86400000);

            document.getElementById('retorno-imei').textContent = asignacion.imei || '-';
            document.getElementById('retorno-cliente').textContent = asignacion.cliente || '-';
            document.getElementById('retorno-custodio').textContent = asignacion.custodio_nombre || '-';
            document.getElementById('retorno-dias').textContent = `${dias} día${dias !== 1 ? 's' : ''}`;

            document.getElementById('info-retorno-bloque').classList.remove('hidden');
        }

        function consultarGPS() {
            const imei = document.getElementById('buscar-imei-consulta').value.trim();
            if (!imei) {
                alert('Por favor ingrese un IMEI');
                return;
            }

            const gps = Array.isArray(gpsDispositivos) ? gpsDispositivos.find(g => g.imei.toLowerCase().includes(imei.toLowerCase())) : null;
            if (!gps) {
                alert('❌ GPS no encontrado');
                document.getElementById('resultado-consulta').innerHTML = '';
                document.getElementById('resultado-consulta').classList.add('hidden');
                return;
            }

            const asignacionActual = Array.isArray(asignaciones) ? asignaciones.find(a => parseInt(a.gps_id) === parseInt(gps.id) && a.estado === 'asignado') : null;

            let html = `
                <div class="card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">📡 Información del GPS</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">IMEI/Serie</p>
                            <p style="font-family: monospace; font-size: 1.25rem; font-weight: 700; word-break: break-all;">${gps.imei || 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Estado</p>
                            <span class="badge badge-${gps.estado === 'disponible' ? 'success' : 'warning'}">${gps.estado === 'disponible' ? 'Disponible' : 'Asignado'}</span>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Marca</p>
                            <p style="font-size: 1.25rem; font-weight: 700;">${gps.marca || 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Modelo</p>
                            <p style="font-size: 1.25rem; font-weight: 700;">${gps.modelo || 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Ubicación</p>
                            <p style="font-size: 1.1rem; font-weight: 700;">${gps.ubicacion || 'Instalaciones'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; margin-bottom: 0.5rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Descripción</p>
                            <p style="font-size: 1rem; font-weight: 600;">${gps.descripcion || 'Sin descripción'}</p>
                        </div>
                    </div>
                </div>
            `;

            if (asignacionActual) {
                const custodio = Array.isArray(custodios) ? custodios.find(c => parseInt(c.id) === parseInt(asignacionActual.custodio_id)) : null;
                const dias = Math.floor((Date.now() - new Date(asignacionActual.fecha_asignacion)) / 86400000);
                const fechaAsignacion = new Date(asignacionActual.fecha_asignacion).toLocaleString('es-HN');

                html += `
                    <div class="card" style="margin-top: 2rem; border-left: 5px solid var(--warning);">
                        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                            <span style="color: var(--warning);">⚠️</span> GPS ACTUALMENTE ASIGNADO
                        </h3>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">👤 Custodio</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">${custodio ? custodio.nombre : 'N/A'}</p>
                            </div>
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">📞 Teléfono</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); font-family: monospace;">${custodio ? custodio.telefono : 'N/A'}</p>
                            </div>
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">💼 Cargo</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">${custodio ? custodio.cargo : 'N/A'}</p>
                            </div>
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">🏢 Cliente</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">${asignacionActual.cliente || 'N/A'}</p>
                            </div>
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">📍 Origen</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">${asignacionActual.origen || 'N/A'}</p>
                            </div>
                            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border-left: 4px solid var(--warning);">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">🎯 Destino</p>
                                <p style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">${asignacionActual.destino || 'N/A'}</p>
                            </div>
                        </div>

                        <div style="border-top: 1px solid var(--border); padding-top: 1.5rem;">
                            <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 1rem;">Detalles de la Asignación</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Fecha de Asignación</p>
                                    <p style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">${fechaAsignacion}</p>
                                </div>
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Días Asignado</p>
                                    <p style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">${dias} día${dias !== 1 ? 's' : ''}</p>
                                </div>
                                <div>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Observaciones</p>
                                    <p style="font-size: 0.9rem; color: var(--text-primary);">${asignacionActual.observaciones || 'Sin observaciones'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="card" style="margin-top: 2rem; border-left: 5px solid var(--success);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 2rem;">✅</div>
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--success);">GPS DISPONIBLE</h3>
                                <p style="color: var(--text-secondary);">Este GPS se encuentra actualmente disponible para ser asignado a un custodio</p>
                            </div>
                        </div>
                    </div>
                `;
            }

            document.getElementById('resultado-consulta').innerHTML = html;
            document.getElementById('resultado-consulta').classList.remove('hidden');
        }

        function actualizarAlertasRecuperacion() {
            const container = document.getElementById('alertas-container');
            if (!container) return;

            const asignacionesActivas = Array.isArray(asignaciones) ? asignaciones.filter(a => a.estado === 'asignado') : [];

            if (asignacionesActivas.length === 0) {
                container.innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-check-circle"></i><h3>No hay GPS asignados</h3><p>Todos los GPS han sido retornados o están disponibles</p></div></div>`;
                return;
            }

            let html = '<div class="card"><h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> GPS Pendientes de Recuperación</h3>';

            asignacionesActivas.forEach(asignacion => {
                const dias = Math.floor((Date.now() - new Date(asignacion.fecha_asignacion)) / 86400000);

                html += `
                    <div class="alert-card">
                        <div class="alert-card-header">
                            <div class="alert-icon">🚨</div>
                            <div style="flex: 1;">
                                <div class="alert-title">GPS ${asignacion.imei} - Requiere Seguimiento</div>
                            </div>
                        </div>
                        
                        <div class="alert-content">
                            <div class="alert-item">
                                <div class="alert-item-label">👤 Custodio</div>
                                <div class="alert-item-value">${asignacion.custodio_nombre}</div>
                            </div>
                            <div class="alert-item">
                                <div class="alert-item-label">📞 Teléfono</div>
                                <div class="alert-item-value">${asignacion.custodio_telefono}</div>
                            </div>
                            <div class="alert-item">
                                <div class="alert-item-label">🏢 Cliente</div>
                                <div class="alert-item-value">${asignacion.cliente}</div>
                            </div>
                            <div class="alert-item">
                                <div class="alert-item-label">⏱️ Días Asignado</div>
                                <div class="alert-item-value"><span class="alert-days">${dias}</span> día${dias !== 1 ? 's' : ''}</div>
                            </div>
                        </div>

                        <div class="alert-actions">
                            <button type="button" class="btn btn-primary" onclick="irARetornar(${asignacion.id})" style="flex: 1;">
                                <i class="fas fa-undo"></i> Registrar Retorno
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="contactarCustodio('${asignacion.custodio_telefono}')" style="flex: 1;">
                                <i class="fas fa-phone"></i> Contactar
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function irARetornar(asignacionId) {
            showModule('retornar');
            setTimeout(() => {
                const select = document.querySelector('#form-retornar select[name="asignacionId"]');
                if (select) {
                    select.value = asignacionId;
                    mostrarInfoRetorno(asignacionId);
                }
            }, 100);
        }

        function contactarCustodio(telefono) {
            if (telefono) {
                window.open(`tel:${telefono}`);
            } else {
                alert('No hay número de teléfono disponible');
            }
        }

        async function eliminarGPS(id) {
            if (!confirm('¿Está seguro de eliminar este GPS?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('api/delete_gps.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('GPS Eliminado', 'GPS eliminado correctamente');
                    await cargarDatosDelServidor();
                    alert('✅ GPS eliminado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al eliminar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al eliminar GPS');
            }
        }

        async function eliminarCustodio(id) {
            if (!confirm('¿Está seguro de eliminar este custodio?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('api/delete_custodio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('Custodio Eliminado', 'Custodio eliminado correctamente');
                    await cargarDatosDelServidor();
                    alert('✅ Custodio eliminado correctamente');
                } else {
                    alert('❌ ' + (data.message || 'Error al eliminar custodio'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al eliminar custodio');
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

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }

            if (!e.target.closest('.user-menu-container')) {
                document.getElementById('user-dropdown').classList.add('hidden');
            }
        }


        // ==================== CONFIGURACIÓN DE NOTIFICACIONES ====================
        const CONFIG_ALERTAS = {
            PRIMERA_ALERTA: 24,
            SEGUNDA_ALERTA: 48,
            TERCERA_ALERTA: 72,
        };

        let intervaloNotificaciones = null;

        function iniciarVerificacionNotificaciones() {
            verificarNotificacionesRecuperacion();
            intervaloNotificaciones = setInterval(() => {
                verificarNotificacionesRecuperacion();
            }, 5 * 60 * 1000);
        }

        function detenerVerificacionNotificaciones() {
            if (intervaloNotificaciones) {
                clearInterval(intervaloNotificaciones);
                intervaloNotificaciones = null;
            }
        }

        function verificarNotificacionesRecuperacion() {
            if (!Array.isArray(asignaciones)) return;

            const asignacionesActivas = asignaciones.filter(a => a.estado === 'asignado');

            asignacionesActivas.forEach(asignacion => {
                const horasAsignado = calcularHorasAsignado(asignacion.fecha_asignacion);
                const notificacionKey = `notif_${asignacion.id}`;
                const notificacionesVistas = JSON.parse(localStorage.getItem('notificacionesVistas') || '{}');

                if (horasAsignado >= CONFIG_ALERTAS.PRIMERA_ALERTA &&
                    horasAsignado < CONFIG_ALERTAS.SEGUNDA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_24h`]) {
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'warning', 24);
                    notificacionesVistas[`${notificacionKey}_24h`] = true;
                    localStorage.setItem('notificacionesVistas', JSON.stringify(notificacionesVistas));
                }

                if (horasAsignado >= CONFIG_ALERTAS.SEGUNDA_ALERTA &&
                    horasAsignado < CONFIG_ALERTAS.TERCERA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_48h`]) {
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'danger', 48);
                    notificacionesVistas[`${notificacionKey}_48h`] = true;
                    localStorage.setItem('notificacionesVistas', JSON.stringify(notificacionesVistas));
                }

                if (horasAsignado >= CONFIG_ALERTAS.TERCERA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_72h`]) {
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'critical', 72);
                    notificacionesVistas[`${notificacionKey}_72h`] = true;
                    localStorage.setItem('notificacionesVistas', JSON.stringify(notificacionesVistas));
                }
            });
        }

        function calcularHorasAsignado(fechaAsignacion) {
            const fecha = new Date(fechaAsignacion);
            const ahora = new Date();
            const diferencia = ahora - fecha;
            return diferencia / (1000 * 60 * 60);
        }

        function enviarNotificacionRecuperacion(asignacion, horasAsignado, tipo, horas) {
            const notificationList = document.getElementById('notification-list');
            const notifBadge = document.getElementById('notif-badge');

            const notifId = 'notif-' + asignacion.id + '-' + horas + '-' + Date.now();

            let iconoTipo = '⏰';
            let colorTipo = '#f59e0b';
            let urgencia = 'Normal';
            let titulo = '⏰ RECORDATORIO: Tiempo de recuperación próximo';

            if (tipo === 'danger') {
                iconoTipo = '⚠️';
                colorTipo = '#ef4444';
                urgencia = 'Urgente';
                titulo = '⚠️ ALERTA: Recuperación próxima';
            } else if (tipo === 'critical') {
                iconoTipo = '🚨';
                colorTipo = '#dc2626';
                urgencia = 'CRÍTICA';
                titulo = '🚨 CRÍTICA: Recuperación vencida';
            }

            const notifItem = document.createElement('div');
            notifItem.className = 'notification-item';
            notifItem.id = notifId;
            notifItem.style.borderLeft = `4px solid ${colorTipo}`;
            notifItem.innerHTML = `
        <div class="notification-item-header">
            <span class="notification-item-title">${titulo}</span>
            <span class="notification-item-time">Ahora</span>
        </div>
        <div class="notification-item-body" style="margin: 0.75rem 0;">
            <strong>GPS IMEI:</strong> ${asignacion.imei}<br>
            <strong>Custodio:</strong> ${asignacion.custodio_nombre}<br>
            <strong>Cliente:</strong> ${asignacion.cliente}<br>
            <strong>Teléfono:</strong> ${asignacion.custodio_telefono}<br>
            <strong style="color: ${colorTipo};">Urgencia:</strong> <span style="color: ${colorTipo};">${urgencia}</span><br>
            <small style="color: var(--text-secondary);">Asignado hace ${Math.floor(horasAsignado)} horas</small>
        </div>
        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button class="btn btn-primary" onclick="irARetornar(${asignacion.id})" style="padding: 0.5rem 1rem; font-size: 0.85rem; flex: 1;">
                <i class="fas fa-undo"></i> Registrar Retorno
            </button>
            <button class="btn" onclick="contactarCustodio('${asignacion.custodio_telefono}')" style="padding: 0.5rem 1rem; font-size: 0.85rem; background-color: #06b6d4; color: white; border-radius: 8px; border: none; cursor: pointer;">
                <i class="fas fa-phone"></i> Contactar
            </button>
            <button class="btn btn-secondary" onclick="confirmarNotificacion('${notifId}')" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fas fa-check"></i> Listo
            </button>
        </div>
    `;

            notificationList.insertBefore(notifItem, notificationList.firstChild);

            const currentCount = parseInt(notifBadge.textContent) || 0;
            notifBadge.textContent = currentCount + 1;
            notifBadge.classList.remove('hidden');

            if (tipo === 'critical') {
                reproducirSonidoAlerta();
            }

            mostrarNotificacionNavegador(titulo, {
                body: `${asignacion.custodio_nombre} - ${asignacion.cliente}\nIMEI: ${asignacion.imei}\nTeléfono: ${asignacion.custodio_telefono}`,
                icon: '🚨',
                tag: 'gps-' + asignacion.id
            });
        }

        function mostrarNotificacionNavegador(titulo, opciones) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(titulo, opciones);
            }
        }

        function reproducirSonidoAlerta() {
            try {
                const audioContext = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                const ahora = audioContext.currentTime;
                oscillator.frequency.value = 800;
                gainNode.gain.setValueAtTime(0.3, ahora);
                gainNode.gain.setValueAtTime(0, ahora + 0.1);

                oscillator.frequency.setValueAtTime(800, ahora + 0.15);
                gainNode.gain.setValueAtTime(0.3, ahora + 0.15);
                gainNode.gain.setValueAtTime(0, ahora + 0.25);

                oscillator.frequency.setValueAtTime(800, ahora + 0.3);
                gainNode.gain.setValueAtTime(0.3, ahora + 0.3);
                gainNode.gain.setValueAtTime(0, ahora + 0.4);

                oscillator.start(ahora);
                oscillator.stop(ahora + 0.4);
            } catch (e) {
                console.log('No se pudo reproducir sonido de alerta');
            }
        }

        function solicitarPermisoNotificaciones() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }
    </script>
</body>

</html>