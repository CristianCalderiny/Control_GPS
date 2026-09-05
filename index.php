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

// Rol con permisos administrativos (eliminar, exportar todo, etc.)
$es_admin = in_array(strtolower(trim($usuario_rol)), ['admin', 'administrador']);


// Mostrar la bienvenida solo una vez por sesión (no en cada recarga de página)
$mostrar_bienvenida = empty($_SESSION['bienvenida_mostrada']);
$_SESSION['bienvenida_mostrada'] = true;

// Obtener estadísticas de la base de datos
try {
    $statsSQL = "
        SELECT 
            (SELECT COUNT(*) FROM gps_dispositivos WHERE estado = 'asignado') as gps_asignados,
            (SELECT COUNT(*) FROM gps_dispositivos WHERE estado = 'disponible') as gps_disponibles,
            (SELECT COUNT(*) FROM gps_dispositivos) as gps_total,
            (SELECT COUNT(*) FROM custodios WHERE estado = 'activo') as custodios_activos,
            (SELECT COUNT(*) FROM asignaciones_gps WHERE estado = 'asignado') as asignaciones_activas
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e31b23;
            --primary-dark: #a90f15;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #e31b23;
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
            color: #e31b23;
        }

        .nav-item:nth-child(1):hover {
            background: #fde8e9;
        }

        .nav-item:nth-child(2) i {
            color: #f59e0b;
        }

        .nav-item:nth-child(2):hover {
            background: #fef3c7;
        }

        .nav-item:nth-child(3) i {
            color: #c31920;
        }

        .nav-item:nth-child(3):hover {
            background: #f9e7e8;
        }

        .nav-item:nth-child(4) i {
            color: #e31b23;
        }

        .nav-item:nth-child(4):hover {
            background: #fdebed;
        }

        .nav-item:nth-child(5) i {
            color: #10b981;
        }

        .nav-item:nth-child(5):hover {
            background: #dcfce7;
        }

        .nav-item:nth-child(6) i {
            color: #7b7c84;
        }

        .nav-item:nth-child(6):hover {
            background: #eeeeF1;
        }

        .nav-item:nth-child(7) i {
            color: #f97316;
        }

        .nav-item:nth-child(7):hover {
            background: #ffedd5;
        }

        .nav-item:nth-child(8) i {
            color: #c31920;
        }

        .nav-item:nth-child(8):hover {
            background: #f9e0e1;
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
            background: linear-gradient(135deg, #c31920, #a90f15);
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
            background: #fde8e9;
            color: #a90f15;
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
            background: #a90f15;
            color: #fde8e9;
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

        /* --- Combobox con búsqueda (reemplaza selects con listas largas) --- */
        .searchable-select-wrap {
            position: relative;
        }

        .searchable-select-wrap .searchable-select-search {
            cursor: text;
            padding-right: 2.5rem;
        }

        .searchable-select-wrap::after {
            content: '\f002';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 0.9rem;
            top: 0.9rem;
            color: var(--text-secondary);
            pointer-events: none;
            font-size: 0.85rem;
        }

        .searchable-select-wrap.open::after {
            content: '\f00d';
        }

        .searchable-select-native {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .searchable-select-list {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            max-height: 260px;
            overflow-y: auto;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            z-index: 50;
            padding: 0.35rem;
        }

        .searchable-select-wrap.open .searchable-select-list {
            display: block;
        }

        .searchable-select-item {
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.92rem;
            color: var(--text-primary);
            transition: background 0.15s ease;
        }

        .searchable-select-item:hover,
        .searchable-select-item.is-active {
            background: var(--bg-secondary);
        }

        .searchable-select-item.is-selected {
            color: var(--primary);
            font-weight: 600;
        }

        .searchable-select-empty {
            padding: 0.85rem 0.75rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.88rem;
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

        /* ====== REDISEÑO SIMPLIFICADO DE MODALES (solo estilos, sin tocar variables/lógica) ====== */
        .modal-simple .modal-content {
            max-width: 520px;
            padding: 0;
            display: flex;
            flex-direction: column;
            max-height: 85vh;
        }

        .modal-simple .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 1.5rem 1.75rem;
            border-bottom: none;
            border-radius: 20px 20px 0 0;
            gap: 1rem;
        }

        .modal-simple-header-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .modal-simple .modal-header h3 {
            font-size: 1.15rem;
            gap: 0;
            line-height: 1.3;
        }

        .modal-simple-subtitle {
            font-size: 0.78rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.15rem;
        }

        .modal-simple .close-modal {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .modal-simple .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .modal-simple .modal-body {
            padding: 1.5rem 1.75rem;
            overflow-y: auto;
        }

        .modal-simple .modal-section + .modal-section {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px dashed var(--border);
        }

        .modal-simple-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            margin-bottom: 0.9rem;
        }

        .modal-simple-section-title i {
            color: var(--primary);
        }

        .modal-simple .form-group:last-child {
            margin-bottom: 0;
        }

        .modal-simple-hint {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 0.4rem;
        }

        .modal-simple-optional {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: none;
            letter-spacing: normal;
            background: var(--bg-secondary);
            border-radius: 6px;
            padding: 0.1rem 0.5rem;
            margin-left: 0.35rem;
        }

        .modal-simple-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.25rem 1.75rem;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            border-radius: 0 0 20px 20px;
        }

        .modal-simple-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 0.9rem;
            font-weight: 600;
        }

        /* ====== FORMULARIOS DE ASIGNACIÓN MÁS COMPACTOS Y FORMALES ====== */
        .assign-form .form-header {
            padding: 0.75rem 1.25rem;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .assign-form .form-header-icon {
            width: 28px;
            height: 28px;
            min-width: 28px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .assign-form .form-header h3 {
            font-size: 0.92rem;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.1rem;
        }

        .assign-form .form-header-note {
            font-size: 0.66rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.8);
        }

        .assign-form form {
            padding: 0.9rem 1.25rem 1.1rem;
        }

        .assign-section {
            margin-bottom: 0.85rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border);
        }

        .assign-section:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
        }

        .assign-section-title {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .assign-section-title i {
            color: var(--primary);
            font-size: 0.7rem;
        }

        .assign-form .form-row {
            gap: 0.6rem;
        }

        .assign-form .form-group {
            margin-bottom: 0.5rem;
        }

        .assign-form .form-group:last-child {
            margin-bottom: 0;
        }

        .assign-form .form-label {
            font-size: 0.78rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            gap: 0.35rem;
        }

        .assign-form .form-label i {
            font-size: 0.75rem;
        }

        .assign-form .form-input,
        .assign-form .form-select,
        .assign-form .form-textarea {
            padding: 0.5rem 0.65rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }

        .assign-form .form-textarea {
            min-height: 50px;
        }

        .assign-form #info-gps-asignar,
        .assign-form #info-custodio-asignar,
        .assign-form #info-gps-cliente {
            font-size: 0.72rem !important;
            margin-top: 0.25rem !important;
        }

        .assign-form .btn {
            padding: 0.6rem !important;
            font-size: 0.85rem !important;
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

        .notification-subtitle {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
            font-weight: 500;
        }

        .notif-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .notif-action-btn:hover {
            background: var(--bg-primary);
            box-shadow: var(--shadow);
            color: var(--text-primary);
        }

        .notif-action-btn.notif-action-close:hover {
            color: var(--danger);
            border-color: var(--danger);
        }

        .notification-item {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
            animation: notifSlideIn 0.25s ease;
        }

        .notification-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--notif-color, var(--primary));
            border-radius: 4px 0 0 4px;
        }

        .notification-item.leida {
            opacity: 0.55;
        }

        .notif-icon-avatar {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.05rem;
            background: var(--notif-bg, rgba(227,27,35,.12));
            color: var(--notif-color, var(--primary));
        }

        .notification-item-content {
            flex: 1;
            min-width: 0;
        }

        @keyframes notifSlideIn {
            from { opacity: 0; transform: translateX(12px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes notifSlideOut {
            to { opacity: 0; transform: translateX(30px); max-height: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; }
        }

        .notification-item.saliendo {
            animation: notifSlideOut 0.3s ease forwards;
        }

        .notification-item.filtro-oculto {
            display: none !important;
        }

        /* ===== Pestañas de filtro del panel de notificaciones ===== */
        .notification-tabs {
            display: flex;
            gap: 0.4rem;
            padding: 0.75rem 1.25rem 0;
            border-bottom: 1px solid var(--border);
        }

        .notif-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 0.5rem 0.65rem 0.7rem;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.2s var(--forza-ease, ease), border-color 0.2s var(--forza-ease, ease);
        }

        .notif-tab-btn:hover {
            color: var(--text-primary);
        }

        .notif-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .notif-tab-count {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.05rem 0.4rem;
            border-radius: 9999px;
            min-width: 1.2rem;
            text-align: center;
        }

        .notif-tab-btn.active .notif-tab-count {
            background: var(--primary);
            color: #fff;
        }

        /* Animación de la campana al llegar una notificación nueva */
        @keyframes notifBellRing {
            0%, 100% { transform: rotate(0); }
            15% { transform: rotate(14deg); }
            30% { transform: rotate(-12deg); }
            45% { transform: rotate(9deg); }
            60% { transform: rotate(-6deg); }
            75% { transform: rotate(3deg); }
        }

        #notif-bell-icon.notif-bell-ring {
            display: inline-block;
            animation: notifBellRing 0.6s ease;
        }

        #notif-bell-btn.notif-bell-pulse {
            animation: notifBellPulse 0.6s ease;
        }

        @keyframes notifBellPulse {
            0% { box-shadow: 0 0 0 0 rgba(227,27,35,.55); }
            100% { box-shadow: 0 0 0 12px rgba(227,27,35,0); }
        }

        .consulta-detalle-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--warning);
        }

        .consulta-detalle-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .consulta-detalle-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
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

        .btn-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-tab:hover {
            color: var(--primary);
        }

        .btn-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }


        /* ==========================================================
           FORZA SECURE LOGISTIC — REDISEÑO CORPORATIVO
           Cambios exclusivamente visuales. No altera lógica PHP/JS.
           Referencia: negro carbón + rojo FORZA + blanco/gris.
           ========================================================== */

        :root {
            --primary: #e31b23;
            --primary-dark: #a90f15;
            --secondary: #51525a;
            --success: #20bf84;
            --danger: #e31b23;
            --warning: #d99a32;
            --info: #d51a21;
            --bg-primary: #ffffff;
            --bg-secondary: #f4f4f6;
            --bg-card: #ffffff;
            --text-primary: #202126;
            --text-secondary: #73747d;
            --border: #e1e1e6;
            --shadow: 0 5px 18px rgba(23, 23, 28, .07);
            --shadow-lg: 0 18px 45px rgba(18, 18, 22, .15);
            --forza-charcoal: #202126;
            --forza-charcoal-soft: #292a2f;
            --forza-charcoal-lighter: #34353b;
            --forza-red: #e31b23;
            --forza-red-dark: #a90f15;
            --forza-red-soft: rgba(227, 27, 35, .09);
            --forza-red-border: rgba(227, 27, 35, .24);
        }

        body.dark-mode {
            --bg-primary: #24252a;
            --bg-secondary: #1d1e22;
            --bg-card: #292a2f;
            --text-primary: #f7f7f8;
            --text-secondary: #a9a9b2;
            --border: #3a3b42;
            --shadow: 0 5px 18px rgba(0, 0, 0, .28);
            --shadow-lg: 0 20px 48px rgba(0, 0, 0, .42);
        }

        html { scroll-behavior: smooth; }

        body {
            background:
                radial-gradient(circle at 82% -20%, rgba(227, 27, 35, .07), transparent 28%),
                var(--bg-secondary);
        }

        body.dark-mode {
            background:
                radial-gradient(circle at 88% 0%, rgba(227, 27, 35, .10), transparent 28%),
                linear-gradient(135deg, #1d1e22 0%, #24252a 100%);
        }

        /* ===== Barra superior ===== */
        .top-bar {
            background: rgba(32, 33, 38, .98);
            border-bottom: 1px solid #383940;
            box-shadow: 0 10px 26px rgba(0,0,0,.18);
            backdrop-filter: blur(12px);
        }

        .top-bar .logo-text h1 {
            color: #fff;
            letter-spacing: .055em;
        }

        .top-bar .logo-text p { color: #9f9fa8; }

        .theme-toggle-top,
        .user-menu-btn {
            background: #292a2f;
            border-color: #414249;
            color: #f7f7f8;
        }

        .theme-toggle-top:hover,
        .user-menu-btn:hover {
            background: #303137;
            border-color: rgba(227,27,35,.48);
            box-shadow: 0 8px 22px rgba(0,0,0,.22);
        }

        .user-menu-role { color: #a8a8b0; }

        /* ===== Sidebar ===== */
        .sidebar {
            background:
                linear-gradient(180deg, rgba(227,27,35,.035) 0, transparent 180px),
                linear-gradient(180deg, #1d1e22 0%, #25262b 100%);
            border-right: 1px solid #34353b;
            box-shadow: 12px 0 28px rgba(0,0,0,.12);
        }

        .sidebar-header {
            border-bottom-color: #34353b;
            background: linear-gradient(180deg, rgba(255,255,255,.018), transparent);
        }

        .logo-icon,
        .user-menu-avatar,
        .user-dropdown-avatar {
            background: linear-gradient(135deg, #f12d35 0%, #b10f16 100%);
            border: 1px solid rgba(255,255,255,.08);
            box-shadow: 0 8px 20px rgba(227,27,35,.24);
        }

        .sidebar .logo-text h1 {
            color: #fff;
            letter-spacing: .055em;
        }

        .sidebar .logo-text p { color: #9899a2; }

        .nav-menu { padding: 1.15rem .9rem; }

        .nav-item {
            color: #b6b6be;
            border: 1px solid transparent;
            border-radius: 11px;
            padding: .92rem 1.05rem;
        }

        .nav-item i,
        .nav-item:nth-child(n) i { color: #ef434a; }

        .nav-item:hover,
        .nav-item:nth-child(n):hover {
            color: #fff;
            background: rgba(227,27,35,.10);
            border-color: rgba(227,27,35,.16);
            transform: translateX(3px);
        }

        .nav-item.active {
            background: linear-gradient(135deg, #dd1c24 0%, #a70e15 100%);
            color: #fff;
            border-color: rgba(255,255,255,.07);
            box-shadow: 0 8px 22px rgba(227,27,35,.22);
        }

        .nav-item.active i { color: #fff; }

        .mobile-menu-btn {
            background: linear-gradient(135deg, #eb252d, #ad0f16);
            box-shadow: 0 8px 20px rgba(227,27,35,.26);
        }

        .mobile-menu-btn:hover {
            background: linear-gradient(135deg, #f43840, #c4121a);
        }

        /* ===== Contenido ===== */
        .main-content {
            background:
                linear-gradient(180deg, rgba(227,27,35,.025), transparent 170px),
                transparent;
        }

        .content-header { margin-bottom: 1.75rem; }

        .content-header h2 {
            letter-spacing: -.025em;
            line-height: 1.15;
        }

        .content-header h2::after {
            content: "";
            display: block;
            width: 52px;
            height: 4px;
            margin-top: .7rem;
            border-radius: 999px;
            background: linear-gradient(90deg, #e31b23, #ff4a50);
            box-shadow: 0 4px 12px rgba(227,27,35,.22);
        }

        /* ===== Tarjetas ===== */
        .card,
        .form-card,
        .stat-card,
        .modal-content,
        .user-dropdown,
        .notification-panel {
            border-color: var(--border);
            box-shadow: var(--shadow);
        }

        .card,
        .form-card,
        .stat-card {
            border-radius: 16px;
        }

        body.dark-mode .card,
        body.dark-mode .form-card,
        body.dark-mode .stat-card,
        body.dark-mode .modal-content,
        body.dark-mode .user-dropdown,
        body.dark-mode .notification-panel {
            background: linear-gradient(145deg, #2a2b30 0%, #25262b 100%);
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            border-top: 3px solid rgba(227,27,35,.88);
        }

        .stat-card::before {
            content: "";
            position: absolute;
            width: 90px;
            height: 90px;
            right: -35px;
            top: -35px;
            border-radius: 50%;
            background: rgba(227,27,35,.045);
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(227,27,35,.36);
            box-shadow: 0 16px 34px rgba(227,27,35,.10);
        }

        .stat-icon.orange,
        .stat-icon.purple {
            background: linear-gradient(135deg, #ef3138 0%, #a90f15 100%);
            box-shadow: 0 9px 20px rgba(227,27,35,.17);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #2dcc97 0%, #148762 100%);
            box-shadow: 0 9px 20px rgba(32,191,132,.16);
        }

        /* ===== Tablas ===== */
        .table-container {
            border-color: var(--border);
            background: var(--bg-card);
        }

        thead,
        th { background: #f1f1f3; }

        body.dark-mode thead,
        body.dark-mode th { background: #222329; }

        th {
            color: #b51219;
            border-bottom-color: rgba(227,27,35,.25);
            font-weight: 750;
        }

        body.dark-mode th { color: #f05c62; }

        td { border-bottom-color: var(--border); }

        tbody tr { transition: background .18s ease; }

        tbody tr:hover { background: rgba(227,27,35,.045); }

        .table-container::-webkit-scrollbar-thumb {
            background: #7d4a4d;
        }

        .table-container::-webkit-scrollbar-thumb:hover { background: #e31b23; }

        /* ===== Formularios ===== */
        .form-header {
            background:
                radial-gradient(circle at 85% 0%, rgba(255,255,255,.15), transparent 30%),
                linear-gradient(135deg, #dc1c24 0%, #9b0c12 100%);
        }

        .form-input,
        .form-select,
        .form-textarea,
        .search-input {
            border-color: var(--border);
            background: var(--bg-primary);
        }

        body.dark-mode .form-input,
        body.dark-mode .form-select,
        body.dark-mode .form-textarea,
        body.dark-mode .search-input {
            background: #222329;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus,
        .search-input:focus {
            outline: none;
            border-color: #e31b23;
            box-shadow: 0 0 0 3px rgba(227,27,35,.13);
        }

        .form-label i,
        .search-icon { color: #e31b23; }

        input[type="checkbox"],
        input[type="radio"] { accent-color: #e31b23; }

        body.dark-mode select option {
            background: #24252a;
            color: #f7f7f8;
        }

        /* ===== Botones ===== */
        .btn { border-radius: 10px; }

        .btn-primary {
            background: linear-gradient(135deg, #e31b23 0%, #b10f16 100%);
            box-shadow: 0 7px 16px rgba(227,27,35,.17);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #f12e36 0%, #c5121a 100%);
            box-shadow: 0 10px 24px rgba(227,27,35,.25);
        }

        .btn-secondary { background: #4a4b52; }
        .btn-secondary:hover { background: #5b5c64; }

        .btn-danger {
            background: linear-gradient(135deg, #ef343b, #af0e15);
        }

        .btn-success {
            background: linear-gradient(135deg, #29c991, #168563);
        }

        /* ===== Badges / estados ===== */
        .badge-info {
            background: rgba(227,27,35,.10);
            color: #a90f15;
        }

        body.dark-mode .badge-info {
            background: rgba(227,27,35,.18);
            color: #ff969a;
        }

        .badge-danger {
            background: #fee7e8;
            color: #9e0c12;
        }

        body.dark-mode .badge-danger {
            background: rgba(227,27,35,.22);
            color: #ffabad;
        }

        .status-indicator,
        .status-indicator-large {
            background: #21c78a;
            box-shadow: 0 0 0 3px rgba(33,199,138,.10);
        }

        .notif-badge { background: #e31b23; }

        /* ===== Pestañas ===== */
        .btn-tab:hover,
        .btn-tab.active { color: #e31b23; }
        .btn-tab.active { border-bottom-color: #e31b23; }

        /* ===== Dropdown / notificaciones ===== */
        .user-dropdown-item:hover,
        .notification-item:hover { background: rgba(227,27,35,.075); }

        .user-dropdown-item i { color: #e5484f; }

        .notification-header { border-color: var(--border); }
        .notification-header h3 i { color: #e31b23; }
        .notification-item { border-color: var(--border); }

        body.dark-mode .notification-item { background: #232429; }

        .notification-header button[onclick="limpiarTodasNotificaciones()"] {
            background: rgba(227,27,35,.11) !important;
            color: #ef5960 !important;
            border-color: rgba(227,27,35,.32) !important;
        }

        /* ===== Alertas ===== */
        .alert-card {
            background: linear-gradient(135deg, #fff2f2, #ffe6e7);
            border-color: #e31b23;
            box-shadow: 0 8px 24px rgba(227,27,35,.11);
        }

        body.dark-mode .alert-card {
            background: linear-gradient(135deg, #451418, #321013);
            border-color: #d91b23;
        }

        .alert-title,
        .alert-item-label,
        .alert-item-value { color: #a80f15; }

        body.dark-mode .alert-title,
        body.dark-mode .alert-item-label,
        body.dark-mode .alert-item-value { color: #ff969a; }

        body.dark-mode .alert-item {
            background: #2c171a;
            border-left-color: #e31b23;
        }

        /* ===== Modales ===== */
        .modal {
            background: rgba(8,8,11,.76);
            backdrop-filter: blur(4px);
        }

        .modal-header { border-color: var(--border); }
        .modal-header h3 i { color: #e31b23; }

        .close-modal:hover {
            background: rgba(227,27,35,.10);
            color: #ef3b42;
        }

        /* ===== Detalles de marca ===== */
        ::selection {
            background: rgba(227,27,35,.32);
            color: #fff;
        }

        a { color: #d8171f; }
        a:hover { color: #f12c34; }

        /* Mantener consistencia del tema incluso en colores inline heredados */
        [style*="#1e40af"], [style*="#3b82f6"], [style*="#1d4ed8"],
        [style*="#8b5cf6"], [style*="#7c3aed"], [style*="#5b21b6"],
        [style*="#4338ca"], [style*="#ec4899"] {
            border-color: var(--forza-red) !important;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .top-bar { padding: 0 1rem; }
            .main-content { padding: 1.25rem; }
            .card { padding: 1.25rem; }
        }



        /* ============================================================
           FORZA UI · CAPA DE INTERACTIVIDAD VISUAL
           Solo presentación: no modifica lógica PHP, consultas ni flujos.
           ============================================================ */

        :root {
            --forza-red: #e31b23;
            --forza-red-light: #ff3b42;
            --forza-red-dark: #a90f16;
            --forza-charcoal: #202126;
            --forza-surface: #292a2f;
            --forza-ease: cubic-bezier(.2,.8,.2,1);
        }

        /* Línea corporativa animada en la barra superior */
        .top-bar::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 2px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(227, 27, 35, .20) 18%,
                rgba(255, 59, 66, .95) 50%,
                rgba(227, 27, 35, .20) 82%,
                transparent 100%);
            background-size: 220% 100%;
            animation: forzaTopLine 8s linear infinite;
            pointer-events: none;
        }

        @keyframes forzaTopLine {
            from { background-position: 0% 0; }
            to   { background-position: 220% 0; }
        }

        /* Profundidad sutil del menú lateral */
        .sidebar::after {
            content: "";
            position: absolute;
            top: 0;
            right: -1px;
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg,
                transparent,
                rgba(227, 27, 35, .38) 22%,
                rgba(227, 27, 35, .12) 60%,
                transparent);
            pointer-events: none;
        }

        /* Navegación más viva, sin tocar sus onclick existentes */
        .nav-item {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            transition:
                color .22s var(--forza-ease),
                background .22s var(--forza-ease),
                border-color .22s var(--forza-ease),
                transform .22s var(--forza-ease),
                box-shadow .22s var(--forza-ease);
        }

        .nav-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 3px;
            height: 0;
            border-radius: 0 999px 999px 0;
            background: #ff4a50;
            transform: translateY(-50%);
            transition: height .22s var(--forza-ease);
            z-index: -1;
        }

        .nav-item:hover::before { height: 54%; }
        .nav-item.active::before {
            height: 64%;
            background: rgba(255,255,255,.92);
        }

        .nav-item i {
            transition: transform .22s var(--forza-ease), color .22s ease;
        }

        .nav-item:hover i { transform: translateX(2px) scale(1.08); }
        .nav-item.active i { transform: scale(1.05); }

        /* Botones con respuesta física */
        .btn,
        .theme-toggle-top,
        .user-menu-btn,
        .mobile-menu-btn,
        .close-modal {
            position: relative;
            overflow: hidden;
            transition:
                transform .18s var(--forza-ease),
                box-shadow .22s var(--forza-ease),
                background .22s var(--forza-ease),
                border-color .22s var(--forza-ease);
        }

        .btn:hover,
        .theme-toggle-top:hover,
        .user-menu-btn:hover {
            transform: translateY(-2px);
        }

        .btn:active,
        .theme-toggle-top:active,
        .user-menu-btn:active,
        .mobile-menu-btn:active,
        .nav-item:active {
            transform: translateY(0) scale(.985);
        }

        /* Ripple agregado por JS; no altera acciones de los botones */
        .forza-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.30);
            transform: scale(0);
            animation: forzaRipple .58s ease-out;
            pointer-events: none;
            z-index: 3;
        }

        @keyframes forzaRipple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* Tarjetas: elevación y borde corporativo en hover */
        .card,
        .form-card,
        .stat-card {
            transition:
                transform .28s var(--forza-ease),
                box-shadow .28s var(--forza-ease),
                border-color .28s var(--forza-ease);
        }

        .card:hover,
        .form-card:hover {
            border-color: rgba(227,27,35,.24);
            box-shadow: 0 16px 36px rgba(0,0,0,.12);
        }

        body.dark-mode .card:hover,
        body.dark-mode .form-card:hover {
            box-shadow: 0 18px 42px rgba(0,0,0,.30);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 38px rgba(0,0,0,.16);
        }

        .stat-card .stat-icon {
            transition: transform .3s var(--forza-ease), box-shadow .3s var(--forza-ease);
        }

        .stat-card:hover .stat-icon {
            transform: rotate(-4deg) scale(1.08);
            box-shadow: 0 10px 22px rgba(227,27,35,.20);
        }

        .stat-info p {
            transition: color .2s ease, transform .2s var(--forza-ease);
        }

        .stat-card:hover .stat-info p {
            color: var(--forza-red-light);
            transform: translateX(2px);
        }

        /* Tablas más legibles e interactivas */
        tbody tr {
            transition: background .16s ease, box-shadow .16s ease;
        }

        tbody tr:hover {
            background: rgba(227,27,35,.045);
            box-shadow: inset 3px 0 0 rgba(227,27,35,.72);
        }

        body.dark-mode tbody tr:hover {
            background: rgba(227,27,35,.075);
        }

        th {
            transition: color .18s ease, background .18s ease;
        }

        thead:hover th { color: var(--text-primary); }

        /* Campos: foco claro y elegante */
        .form-input,
        .form-select,
        .form-textarea,
        .search-input {
            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background .2s ease,
                transform .2s var(--forza-ease);
        }

        .form-input:hover,
        .form-select:hover,
        .form-textarea:hover,
        .search-input:hover {
            border-color: rgba(227,27,35,.40);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus,
        .search-input:focus {
            border-color: var(--forza-red);
            box-shadow: 0 0 0 4px rgba(227,27,35,.11);
        }

        .search-box:focus-within .search-icon {
            color: var(--forza-red-light);
            transform: translateY(-50%) scale(1.06);
        }

        .search-icon { transition: color .2s ease, transform .2s var(--forza-ease); }

        /* Tabs con transición visual */
        .btn-tab {
            position: relative;
            transition: color .2s ease, background .2s ease, transform .2s var(--forza-ease);
        }

        .btn-tab:hover {
            background: rgba(227,27,35,.055);
            transform: translateY(-1px);
        }

        .btn-tab.active {
            background: linear-gradient(180deg, rgba(227,27,35,.06), transparent);
        }

        /* Modales: fondo con desenfoque y entrada más natural */
        .modal {
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .modal.active .modal-content {
            animation: forzaModalIn .28s var(--forza-ease);
        }

        @keyframes forzaModalIn {
            from { opacity: 0; transform: translateY(18px) scale(.985); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Menú de usuario */
        .user-dropdown:not(.hidden) {
            animation: forzaDropdown .20s var(--forza-ease);
            transform-origin: top right;
        }

        @keyframes forzaDropdown {
            from { opacity: 0; transform: translateY(-8px) scale(.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .user-dropdown-item {
            transition: background .18s ease, padding-left .18s var(--forza-ease), color .18s ease;
        }

        .user-dropdown-item:hover { padding-left: 1.75rem; }

        /* Notificaciones */
        .notification-panel {
            transition: right .32s var(--forza-ease), box-shadow .32s ease;
        }

        .notification-panel.active {
            box-shadow: -20px 0 55px rgba(0,0,0,.28);
        }

        .notification-item {
            transition: transform .2s var(--forza-ease), border-color .2s ease, box-shadow .2s ease;
        }

        .notification-item:hover {
            transform: translateX(-4px);
            border-color: rgba(227,27,35,.28);
        }

        /* Indicadores de sesión con pulso discreto */
        .status-indicator,
        .status-indicator-large {
            animation: forzaOnlinePulse 2.4s ease-in-out infinite;
        }

        @keyframes forzaOnlinePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,.00); }
            50%      { box-shadow: 0 0 0 5px rgba(16,185,129,.12); }
        }

        /* Botón visual para regresar arriba */
        .forza-scroll-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 46px;
            height: 46px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 14px;
            background: linear-gradient(135deg, #ee2931, #ac1017);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(227,27,35,.28);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity .22s ease, visibility .22s ease, transform .22s var(--forza-ease);
            z-index: 999;
        }

        .forza-scroll-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .forza-scroll-top:hover { transform: translateY(-3px); }
        .forza-scroll-top:active { transform: translateY(0) scale(.96); }

        /* Scrollbars acordes al tema */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(227,27,35,.55) transparent;
        }

        *::-webkit-scrollbar { width: 9px; height: 9px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb {
            background: rgba(227,27,35,.48);
            border-radius: 999px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        *::-webkit-scrollbar-thumb:hover { background: rgba(227,27,35,.72); background-clip: padding-box; }

        /* Mejor accesibilidad con teclado */
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid rgba(255,59,66,.92);
            outline-offset: 2px;
        }

        @media (max-width: 768px) {
            .card:hover,
            .form-card:hover,
            .stat-card:hover { transform: none; }

            .forza-scroll-top {
                right: 18px;
                bottom: 92px;
                width: 44px;
                height: 44px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }



        /* ================================================================
           FORZA 2026 · REDISEÑO MODERNO
           Capa visual únicamente. No modifica lógica PHP/JS ni endpoints.
           ================================================================ */
        :root {
            --primary: #e11d2e;
            --primary-dark: #b91524;
            --secondary: #667085;
            --success: #12b76a;
            --danger: #e11d2e;
            --warning: #f79009;
            --info: #475467;
            --bg-primary: #ffffff;
            --bg-secondary: #f5f6f8;
            --bg-card: #ffffff;
            --text-primary: #17181c;
            --text-secondary: #727680;
            --border: #e7e9ee;
            --shadow: 0 8px 30px rgba(16, 24, 40, .055);
            --shadow-lg: 0 22px 70px rgba(16, 24, 40, .13);
            --forza-red: #e11d2e;
            --forza-ink: #111318;
            --forza-sidebar: #121419;
            --forza-soft-red: #fff1f2;
        }

        body.dark-mode {
            --bg-primary: #171a20;
            --bg-secondary: #111318;
            --bg-card: #1b1e25;
            --text-primary: #f7f7f8;
            --text-secondary: #9ca1ad;
            --border: #2b2f38;
            --shadow: 0 10px 34px rgba(0, 0, 0, .22);
            --shadow-lg: 0 24px 72px rgba(0, 0, 0, .42);
            --forza-soft-red: rgba(225, 29, 46, .12);
        }

        html { background: var(--bg-secondary); }

        body,
        body.dark-mode {
            font-family: "Segoe UI Variable", "Segoe UI", Inter, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-secondary) !important;
            color: var(--text-primary);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0 0 auto 260px;
            height: 240px;
            pointer-events: none;
            background:
                radial-gradient(circle at 77% 0%, rgba(225, 29, 46, .085), transparent 33%),
                linear-gradient(180deg, rgba(255,255,255,.72), rgba(255,255,255,0));
            z-index: -1;
        }

        body.dark-mode::before {
            background:
                radial-gradient(circle at 77% 0%, rgba(225, 29, 46, .13), transparent 34%),
                linear-gradient(180deg, rgba(255,255,255,.018), transparent);
        }

        /* --- Sidebar estilo command center --- */
        .sidebar {
            width: 260px;
            background: #111318 !important;
            border-right: 0 !important;
            box-shadow: 8px 0 32px rgba(15, 17, 21, .08) !important;
            overflow: hidden;
        }

        .sidebar::before {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            left: -110px;
            top: -105px;
            border-radius: 50%;
            background: rgba(225,29,46,.14);
            filter: blur(2px);
            pointer-events: none;
        }

        .sidebar-header {
            padding: 1.35rem 1.25rem 1.15rem;
            border-bottom: 1px solid rgba(255,255,255,.07) !important;
            background: transparent !important;
            position: relative;
        }

        .sidebar .logo-container { gap: .85rem; }

        .sidebar .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #e11d2e !important;
            box-shadow: 0 10px 24px rgba(225,29,46,.26) !important;
            border: 1px solid rgba(255,255,255,.14) !important;
            font-size: 1.15rem;
        }

        .sidebar .logo-text h1 {
            color: #fff !important;
            font-size: 1.34rem;
            letter-spacing: .12em;
            font-weight: 800;
            line-height: 1;
        }

        .sidebar .logo-text p {
            margin-top: .4rem;
            color: #8f95a3 !important;
            font-size: .72rem;
            letter-spacing: .03em;
        }

        .sidebar-system-chip {
            margin-top: 1.15rem;
            padding: .65rem .75rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid rgba(255,255,255,.075);
            border-radius: 12px;
            background: rgba(255,255,255,.035);
            color: #c7cad1;
            font-size: .76rem;
            font-weight: 600;
        }

        .sidebar-system-chip i { color: #ff5764; }
        .sidebar-online-dot {
            width: 7px;
            height: 7px;
            margin-left: auto;
            border-radius: 50%;
            background: #26c281;
            box-shadow: 0 0 0 4px rgba(38,194,129,.10);
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            padding: 1rem .8rem .9rem !important;
        }

        .nav-menu::before {
            content: "NAVEGACIÓN";
            padding: .25rem .7rem .65rem;
            color: #666d7a;
            font-size: .64rem;
            font-weight: 800;
            letter-spacing: .15em;
        }

        .nav-item {
            min-height: 46px;
            padding: .74rem .8rem !important;
            margin-bottom: .22rem !important;
            border-radius: 11px !important;
            border: 1px solid transparent !important;
            color: #aeb3be !important;
            font-size: .88rem !important;
            font-weight: 550 !important;
            gap: .78rem !important;
            transform: none !important;
        }

        .nav-item i,
        .nav-item:nth-child(n) i {
            width: 30px !important;
            height: 30px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            color: #9da3af !important;
            background: rgba(255,255,255,.04);
            font-size: .96rem !important;
            transition: .22s ease;
        }

        .nav-item:hover,
        .nav-item:nth-child(n):hover {
            background: rgba(255,255,255,.055) !important;
            color: #fff !important;
            border-color: rgba(255,255,255,.055) !important;
        }

        .nav-item:hover i,
        .nav-item:nth-child(n):hover i {
            color: #ff5663 !important;
            background: rgba(225,29,46,.11);
        }

        .nav-item.active {
            background: #ffffff !important;
            color: #15171b !important;
            border-color: #ffffff !important;
            box-shadow: 0 10px 30px rgba(0,0,0,.14) !important;
        }

        .nav-item.active i {
            background: #fff0f1 !important;
            color: #e11d2e !important;
        }

        .nav-item[title="Contraer/Expandir"] {
            margin-top: auto !important;
            border-top: 1px solid rgba(255,255,255,.07) !important;
            border-radius: 0 !important;
            padding-top: 1rem !important;
            color: #757b87 !important;
        }

        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed .sidebar-system-chip { display: none; }
        .sidebar.collapsed .nav-menu::before { display: none; }
        .sidebar.collapsed .nav-item { padding-left: .7rem !important; padding-right: .7rem !important; }
        .sidebar.collapsed .nav-item i { margin: 0 auto; }

        /* --- Topbar flotante y limpia --- */
        .top-bar {
            left: 260px;
            height: 76px;
            padding: 0 2rem;
            background: rgba(255,255,255,.83) !important;
            border-bottom: 1px solid rgba(228,231,236,.88) !important;
            box-shadow: none !important;
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }

        body.dark-mode .top-bar {
            background: rgba(17,19,24,.84) !important;
            border-bottom-color: rgba(255,255,255,.07) !important;
        }

        .workspace-heading { display: flex; flex-direction: column; gap: .26rem; }
        .workspace-kicker {
            color: var(--text-secondary);
            font-size: .67rem;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }
        .top-bar-actions { gap: .65rem; }

        .welcome-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 20% 15%, rgba(239, 75, 75, .55), transparent 55%),
                radial-gradient(circle at 85% 80%, rgba(74, 74, 82, .40), transparent 50%),
                linear-gradient(135deg, #7a0d13 0%, #c8161f 55%, #ef2b2b 100%);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s ease;
        }

        .welcome-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .welcome-card {
            text-align: center;
            max-width: 640px;
            width: 90%;
            transform: scale(0.94) translateY(14px);
            transition: transform 0.5s ease;
        }

        .welcome-overlay.show .welcome-card {
            transform: scale(1) translateY(0);
        }

        .welcome-card-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 2.25rem;
            border-radius: 28px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .3);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 3.25rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
        }

        .welcome-card-eyebrow {
            color: rgba(255, 255, 255, .85);
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .welcome-card h2 {
            font-size: 3rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 1rem;
            text-shadow: 0 4px 18px rgba(0, 0, 0, .3);
            line-height: 1.15;
        }

        .welcome-card p {
            color: rgba(255, 255, 255, .9);
            font-size: 1.15rem;
            margin-bottom: 2.5rem;
        }

        .welcome-card-close {
            background: #ffffff;
            color: var(--primary-dark);
            border: none;
            padding: 1rem 3rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .welcome-card-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.3);
        }

        @media (max-width: 640px) {
            .welcome-card-icon {
                width: 90px;
                height: 90px;
                font-size: 2.4rem;
                margin-bottom: 1.5rem;
            }

            .welcome-card h2 {
                font-size: 2.1rem;
            }

            .welcome-card p {
                font-size: 1rem;
            }
        }

        .theme-toggle-top {
            height: 42px;
            padding: 0 .95rem !important;
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
            box-shadow: 0 2px 8px rgba(16,24,40,.035) !important;
            font-size: .82rem;
        }

        .theme-toggle-top:hover {
            border-color: #d2d5dc !important;
            transform: translateY(-1px);
        }

        .top-bar-actions > .btn.btn-primary {
            width: 42px;
            height: 42px;
            padding: 0 !important;
            justify-content: center;
            border-radius: 12px !important;
            background: #e11d2e !important;
            box-shadow: 0 8px 20px rgba(225,29,46,.17) !important;
        }

        .user-menu-btn {
            min-height: 48px;
            padding: .36rem .55rem .36rem .42rem !important;
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 14px !important;
            color: var(--text-primary) !important;
            box-shadow: 0 2px 8px rgba(16,24,40,.035) !important;
        }

        .user-menu-btn:hover {
            border-color: #d6d9e0 !important;
            transform: translateY(-1px);
        }

        .user-menu-avatar,
        .user-dropdown-avatar {
            background: #f2f3f5 !important;
            color: #363940 !important;
            border: 1px solid #e3e5e9 !important;
            box-shadow: none !important;
        }

        body.dark-mode .user-menu-avatar,
        body.dark-mode .user-dropdown-avatar {
            background: #252932 !important;
            color: #f5f5f6 !important;
            border-color: #343943 !important;
        }

        .status-indicator,
        .status-indicator-large { background: #12b76a !important; }

        /* --- Área principal --- */
        .main-content {
            margin-left: 260px;
            margin-top: 76px;
            min-height: calc(100vh - 76px);
            padding: 2.35rem 2.4rem 3rem;
            background: transparent !important;
        }

        .content-header {
            margin-bottom: 1.65rem;
        }

        .content-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: clamp(1.7rem, 2vw, 2.15rem);
            line-height: 1.1;
            letter-spacing: -.045em;
            font-weight: 760;
        }

        .content-header h2::after { display: none !important; }

        .content-header p {
            margin-top: .55rem;
            color: var(--text-secondary);
            max-width: 660px;
            font-size: .93rem;
        }

        .dashboard-hero {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.2rem;
        }

        .page-eyebrow {
            display: inline-flex;
            margin-bottom: .55rem;
            color: #e11d2e;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .hero-live-chip {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: .48rem;
            margin-bottom: .15rem;
            padding: .55rem .75rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: .76rem;
            font-weight: 650;
        }

        .hero-live-chip span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #12b76a;
            box-shadow: 0 0 0 4px rgba(18,183,106,.10);
        }

        /* --- Métricas minimalistas --- */
        .stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.35rem;
        }

        .stat-card {
            min-height: 142px;
            padding: 1.35rem 1.35rem 1.25rem;
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
            border-top: 1px solid var(--border) !important;
            border-radius: 18px !important;
            box-shadow: var(--shadow) !important;
            position: relative;
            overflow: hidden;
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 3px;
            height: auto;
            border-radius: 0;
            background: #e11d2e;
            opacity: .88;
            transform: none;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            width: 100px;
            height: 100px;
            right: -35px;
            bottom: -45px;
            border-radius: 50%;
            background: rgba(225,29,46,.045);
        }

        .stat-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 16px 42px rgba(16,24,40,.085) !important;
            border-color: #dde0e6 !important;
        }

        body.dark-mode .stat-card:hover { border-color: #353b47 !important; }

        .stat-info h3 {
            margin-bottom: .7rem;
            color: var(--text-secondary);
            font-size: .76rem;
            font-weight: 650;
            letter-spacing: .015em;
            text-transform: none;
        }

        .stat-info p {
            color: var(--text-primary) !important;
            font-size: 2.15rem;
            line-height: 1;
            letter-spacing: -.04em;
            font-weight: 760;
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px !important;
            font-size: 1.16rem;
            box-shadow: none !important;
            z-index: 1;
        }

        .stat-icon.orange,
        .stat-icon.purple {
            background: #fff0f1 !important;
            color: #e11d2e !important;
            border: 1px solid #ffd8dc;
        }

        .stat-icon.green {
            background: #ecfdf3 !important;
            color: #079455 !important;
            border: 1px solid #d1fadf;
        }

        body.dark-mode .stat-icon.orange,
        body.dark-mode .stat-icon.purple {
            background: rgba(225,29,46,.13) !important;
            color: #ff7580 !important;
            border-color: rgba(225,29,46,.20);
        }

        body.dark-mode .stat-icon.green {
            background: rgba(18,183,106,.12) !important;
            color: #4ed79a !important;
            border-color: rgba(18,183,106,.18);
        }

        /* --- Cards y contenedores --- */
        .card,
        .form-card {
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 18px !important;
            box-shadow: var(--shadow) !important;
        }

        .card { padding: 1.45rem !important; }

        .card:hover,
        .form-card:hover,
        body.dark-mode .card:hover,
        body.dark-mode .form-card:hover {
            transform: none !important;
            box-shadow: var(--shadow) !important;
        }

        .card > h3 {
            color: var(--text-primary);
            font-size: 1rem !important;
            letter-spacing: -.015em;
        }

        .card > h3:first-child {
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        /* --- Tabla estilo data-grid --- */
        .table-container {
            border: 1px solid var(--border) !important;
            border-radius: 14px !important;
            background: var(--bg-card);
            max-height: 620px;
        }

        table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        thead,
        th,
        body.dark-mode thead,
        body.dark-mode th {
            background: #fafafb !important;
        }

        body.dark-mode thead,
        body.dark-mode th { background: #20242b !important; }

        th {
            padding: .9rem 1rem !important;
            color: #747985 !important;
            border-bottom: 1px solid var(--border) !important;
            font-size: .69rem !important;
            font-weight: 750 !important;
            letter-spacing: .08em !important;
        }

        body.dark-mode th { color: #aeb3be !important; }

        td {
            padding: .92rem 1rem !important;
            border-bottom: 1px solid var(--border) !important;
            color: var(--text-primary);
            font-size: .86rem;
        }

        tbody tr { transition: background .16s ease; }
        tbody tr:hover,
        body.dark-mode tbody tr:hover {
            background: rgba(225,29,46,.035) !important;
            transform: none !important;
        }

        /* --- Estados vacíos --- */
        .empty-state { padding: 4rem 2rem !important; }
        .empty-state i {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.05rem;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: #f2f3f5;
            color: #7b808a !important;
            font-size: 1.7rem !important;
        }
        body.dark-mode .empty-state i { background: #252932; color: #abb0bb !important; }
        .empty-state h3 { font-size: 1.1rem; letter-spacing: -.015em; }
        .empty-state p { margin-top: .35rem; font-size: .86rem; }

        /* --- Botones modernos --- */
        .btn {
            border-radius: 11px !important;
            font-size: .86rem !important;
            font-weight: 650 !important;
            box-shadow: none !important;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover { transform: translateY(-1px) !important; }

        .btn-primary {
            background: #e11d2e !important;
            color: #fff !important;
        }
        .btn-primary:hover { background: #c91828 !important; box-shadow: 0 8px 20px rgba(225,29,46,.17) !important; }

        .btn-secondary {
            background: #eef0f3 !important;
            color: #3d4149 !important;
        }
        body.dark-mode .btn-secondary { background: #292d35 !important; color: #e6e7ea !important; }

        .btn-success { background: #12a968 !important; }
        .btn-danger { background: #d92d20 !important; }

        /* --- Formularios --- */
        .form-header {
            padding: 1.35rem 1.55rem !important;
            text-align: left !important;
            color: var(--text-primary) !important;
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border);
        }

        .form-header h3 {
            font-size: 1.08rem !important;
            letter-spacing: -.015em;
        }

        .form-card form { padding: 1.5rem !important; }

        .form-label {
            color: var(--text-primary) !important;
            font-size: .81rem;
            font-weight: 650;
        }

        .form-input,
        .form-select,
        .form-textarea,
        .search-input,
        body.dark-mode .form-input,
        body.dark-mode .form-select,
        body.dark-mode .form-textarea,
        body.dark-mode .search-input {
            min-height: 44px;
            border: 1px solid var(--border) !important;
            border-radius: 11px !important;
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            box-shadow: none !important;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus,
        .search-input:focus {
            border-color: rgba(225,29,46,.55) !important;
            box-shadow: 0 0 0 4px rgba(225,29,46,.075) !important;
        }

        /* --- Pestañas --- */
        .btn-tab {
            border-radius: 10px 10px 0 0 !important;
            border-bottom-width: 2px !important;
            font-size: .82rem !important;
        }
        .btn-tab.active { color: #e11d2e !important; border-bottom-color: #e11d2e !important; }

        /* --- Badges --- */
        .badge,
        .ubicacion-badge {
            padding: .38rem .65rem !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            border-radius: 999px !important;
        }
        .badge-success, .ubicacion-instalaciones { background: #ecfdf3 !important; color: #067647 !important; }
        .badge-warning, .ubicacion-campo { background: #fff7ed !important; color: #b54708 !important; }
        .badge-danger { background: #fff1f3 !important; color: #c01048 !important; }
        .badge-info { background: #f2f4f7 !important; color: #475467 !important; }

        body.dark-mode .badge-success,
        body.dark-mode .ubicacion-instalaciones { background: rgba(18,183,106,.12) !important; color: #6ce9a6 !important; }
        body.dark-mode .badge-warning,
        body.dark-mode .ubicacion-campo { background: rgba(247,144,9,.12) !important; color: #fec84b !important; }
        body.dark-mode .badge-danger { background: rgba(225,29,46,.13) !important; color: #ff8b94 !important; }
        body.dark-mode .badge-info { background: #252932 !important; color: #c7cad1 !important; }

        /* --- Modal / dropdown / notificaciones --- */
        .modal { background: rgba(17,19,24,.48) !important; backdrop-filter: blur(5px); }
        .modal-content,
        .user-dropdown,
        .notification-panel,
        body.dark-mode .modal-content,
        body.dark-mode .user-dropdown,
        body.dark-mode .notification-panel {
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
            box-shadow: var(--shadow-lg) !important;
        }

        .modal-content { border-radius: 18px !important; }
        .modal-header { padding: 1.3rem 1.5rem !important; }
        .modal-body { padding: 1.5rem !important; }

        .user-dropdown { border-radius: 14px !important; overflow: hidden; }
        .user-dropdown-item:hover { background: var(--bg-secondary) !important; }

        .notification-panel {
            width: min(390px, 100vw) !important;
            border-left: 0 !important;
        }
        .notification-item,
        body.dark-mode .notification-item {
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
        }

        /* --- Alertas menos agresivas --- */
        .alert-card,
        body.dark-mode .alert-card {
            background: var(--bg-card) !important;
            border: 1px solid #f3b8bd !important;
            border-left: 4px solid #e11d2e !important;
            box-shadow: var(--shadow) !important;
            animation: none !important;
        }
        .alert-icon { animation: none !important; color: #e11d2e; }
        .alert-title { color: #b42318 !important; }
        body.dark-mode .alert-title { color: #ff8b94 !important; }

        /* --- Scrollbars discretas --- */
        * { scrollbar-width: thin; scrollbar-color: #c5c8ce transparent; }
        body.dark-mode * { scrollbar-color: #3c414b transparent; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c5c8ce; border-radius: 999px; }
        body.dark-mode ::-webkit-scrollbar-thumb { background: #3c414b; }
        ::-webkit-scrollbar-thumb:hover { background: #aeb2ba; }

        /* --- Responsive --- */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
            .main-content { padding-left: 1.6rem; padding-right: 1.6rem; }
        }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            body::before { inset-left: 0; }
            .sidebar { width: 250px; }
            .top-bar { left: 0 !important; height: 68px; padding: 0 .85rem; }
            .main-content { margin-left: 0 !important; margin-top: 68px; padding: 1.25rem 1rem 2rem; }
            .workspace-kicker { display: none; }
            .theme-toggle-top span { display: none; }
            .theme-toggle-top { width: 42px; padding: 0 !important; justify-content: center; }
            .user-menu-btn { padding-right: .42rem !important; }
            .dashboard-hero { align-items: flex-start; }
            .hero-live-chip { display: none; }
            .content-header h2 { font-size: 1.65rem; }
            .card { padding: 1rem !important; }
        }

        /* ================================================================
           ARMONIZACIÓN CON LOGIN.PHP · Identidad de marca FORZA
           Degradado oficial, panel "hero", brillo en botones, sidebar
           más rica. No toca estructura ni lógica, solo capas visuales.
           ================================================================ */
        :root {
            --forza-gradient: linear-gradient(135deg, #7a0d13 0%, #c8161f 55%, #ef2b2b 100%);
            --forza-glow: 0 10px 26px rgba(200, 22, 31, .32);
        }

        body,
        body.dark-mode {
            font-family: 'Inter', 'Segoe UI Variable', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* --- Logo de la barra lateral: mismo degradado + anillo que el login --- */
        .sidebar .logo-icon {
            background: var(--forza-gradient) !important;
            box-shadow: var(--forza-glow) !important;
            position: relative;
        }

        .sidebar .logo-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 17px;
            border: 1px solid rgba(239, 43, 43, .45);
            pointer-events: none;
        }

        /* --- Panel "hero" del dashboard: mismo lenguaje visual que el
           panel derecho del login (degradado + resplandores + cristal) --- */
        .dashboard-hero {
            position: relative;
            overflow: hidden;
            padding: 2.1rem 2.35rem;
            border-radius: 22px;
            align-items: center;
            background:
                radial-gradient(circle at 20% 15%, rgba(239, 75, 75, .55), transparent 55%),
                radial-gradient(circle at 85% 80%, rgba(74, 74, 82, .40), transparent 50%),
                var(--forza-gradient);
            box-shadow: 0 20px 46px rgba(199, 22, 25, .28);
        }

        .dashboard-hero .page-eyebrow {
            color: rgba(255, 255, 255, .88) !important;
        }

        .dashboard-hero h2 {
            color: #ffffff !important;
            text-shadow: 0 4px 18px rgba(0, 0, 0, .3);
        }

        .dashboard-hero p {
            color: rgba(255, 255, 255, .85) !important;
        }

        .dashboard-hero .hero-live-chip {
            background: rgba(255, 255, 255, .12) !important;
            border: 1px solid rgba(255, 255, 255, .24) !important;
            color: #ffffff !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .dashboard-hero .hero-live-chip span {
            background: #34d399;
            box-shadow: 0 0 10px rgba(52, 211, 153, .9);
        }

        /* --- Barra de acento en las tarjetas de KPI con el degradado --- */
        .stat-card::before {
            background: var(--forza-gradient) !important;
            opacity: 1 !important;
        }

        /* --- Botones primarios con el mismo brillo/hover del login --- */
        .btn-primary {
            background: var(--forza-gradient) !important;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, .16);
            transition: left .3s ease;
        }

        .btn-primary:hover::before {
            left: 0;
        }

        .btn-primary:hover {
            box-shadow: var(--forza-glow) !important;
        }

        .top-bar-actions > .btn.btn-primary {
            background: var(--forza-gradient) !important;
        }

        /* --- Avatares con el mismo degradado de marca --- */
        .user-menu-avatar,
        .user-dropdown-avatar {
            background: var(--forza-gradient) !important;
            color: #ffffff !important;
            border: none !important;
        }

        /* --- Pestañas activas y detalles menores con el rojo de marca --- */
        .modal-simple .modal-header,
        .form-header {
            background: var(--forza-gradient) !important;
        }

        .form-header,
        .form-header h3,
        .form-header-note {
            color: #ffffff !important;
        }

        @media (max-width: 768px) {
            .dashboard-hero {
                padding: 1.5rem 1.35rem;
                border-radius: 18px;
            }
        }

    </style>
</head>

<body>
    <?php if ($mostrar_bienvenida): ?>
    <div class="welcome-overlay" id="welcome-overlay">
        <div class="welcome-card">
            <div class="welcome-card-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="welcome-card-eyebrow">FORZA · Secure Logistic</div>
            <h2>Bienvenido, <?php echo htmlspecialchars($usuario_nombre); ?></h2>
            <p>Sesión iniciada correctamente como <?php echo ucfirst(htmlspecialchars($usuario_rol)); ?>.</p>
            <button class="welcome-card-close" onclick="cerrarWelcomeOverlay()">Continuar</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="top-bar">
        <div class="user-info">
            <div class="workspace-heading">
                <span class="workspace-kicker">Centro de planificacion</span>
            
            </div>
        </div>

        <div class="top-bar-actions">
            <button class="theme-toggle-top" onclick="toggleTheme()">
                <i class="fas fa-moon" id="theme-icon"></i>
                <span id="theme-text">Tema Oscuro</span>
            </button>

            <button class="btn btn-primary" id="notif-bell-btn" onclick="toggleNotificaciones()" style="position: relative; padding: 0.75rem 1rem;">
                <i class="fas fa-bell" id="notif-bell-icon"></i>
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

                    <?php if ($es_admin): ?>
                    <button class="user-dropdown-item" onclick="irAPanelAdmin()">
                        <i class="fas fa-user-shield"></i>
                        <span>Panel Admin</span>
                    </button>
                    <?php endif; ?>

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
            <div>
                <h3><i class="fas fa-bell"></i> Notificaciones</h3>
                <p class="notification-subtitle" id="notification-subtitle">Sin notificaciones nuevas</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button class="notif-action-btn" id="notif-mark-read-btn" onclick="marcarTodasLeidas()" title="Marcar todas como leídas">
                    <i class="fas fa-check-double"></i>
                </button>
                <button class="notif-action-btn" onclick="limpiarTodasNotificaciones()" title="Limpiar todo">
                    <i class="fas fa-trash-alt"></i>
                </button>
                <button class="notif-action-btn notif-action-close" onclick="toggleNotificaciones()" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="notification-tabs" id="notification-tabs">
            <button type="button" class="notif-tab-btn active" data-filtro="todas" onclick="filtrarNotificaciones('todas')">
                Todas <span class="notif-tab-count" id="notif-tab-count-todas">0</span>
            </button>
            <button type="button" class="notif-tab-btn" data-filtro="no-leidas" onclick="filtrarNotificaciones('no-leidas')">
                Sin leer <span class="notif-tab-count" id="notif-tab-count-no-leidas">0</span>
            </button>
            <button type="button" class="notif-tab-btn" data-filtro="alertas" onclick="filtrarNotificaciones('alertas')">
                Alertas <span class="notif-tab-count" id="notif-tab-count-alertas">0</span>
            </button>
        </div>

        <div class="notification-list" id="notification-list">
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3 style="font-size: 1.1rem;">Sin notificaciones</h3>
                <p>Las nuevas alertas aparecerán aquí</p>
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
                    <p>Secure Logistic</p>
                </div>
            </div>
            <div class="sidebar-system-chip">
                <i class="fas fa-satellite-dish"></i>
                <span>Planificacion y coordinacion</span>
                <span class="sidebar-online-dot"></span>
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
            <button class="nav-item" onclick="window.location.href='operaciones.php'">
                <i class="fas fa-route"></i>
                <span>Operaciones</span>
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
            <div class="content-header dashboard-hero">
                <div>
                    <span class="page-eyebrow">Panel principal</span>
                    <h2>Hola, <?php echo htmlspecialchars($usuario_nombre); ?> </h2>
                    <p>Tienes <?php echo $stats['asignaciones_activas'] ?? 0; ?> asignaciones activas y <?php echo $stats['custodios_activos'] ?? 0; ?> custodios activos en este momento.</p>
                </div>
                <div class="hero-live-chip"><span></span> Sistema activo</div>
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


            <!-- TABLA MEJORADA Y ORGANIZADA PARA DASHBOARD -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0;">📡 GPS ACTUALMENTE ASIGNADOS</h3>
                    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                        <div style="position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.85rem;"></i>
                            <input autocomplete="nope-buscar-gps-asignados" type="text" id="buscar-gps-asignados" class="form-input" placeholder="Buscar IMEI, custodio, cliente, placa..." style="padding-left: 2.3rem; min-width: 260px;" oninput="actualizarDashboard()">
                        </div>
                        <button class="btn btn-secondary" onclick="exportarTablaExcel('tabla-gps-asignados', 'gps_asignados')">
                            <i class="fas fa-file-excel"></i> Exportar
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table id="tabla-gps-asignados">
                        <thead>
                            <tr>
                                <th>IMEI</th>
                                <th>MODELO</th>
                                <th>ASIGNADO A</th>
                                <th>TIPO</th>
                                <th>PILOTO/CUSTODIO</th>
                                <th>PLACA</th>
                                <th>CLIENTE</th>
                                <th>FECHA ASIGNACIÓN</th>
                                <th>DÍAS</th>
                                <th>ESTADO</th>
                                <th>ACCIONES</th>
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
                    <input autocomplete="nope-buscar-gps-tabla" type="text" class="search-input" id="buscar-gps-tabla" placeholder="Buscar GPS por IMEI, marca, modelo..." onkeyup="filtrarTablaGPS()">
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
                                <th>Custodio / Cliente</th>
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
                <div class="flex gap-2" style="flex-wrap:wrap;margin-bottom:1rem;">
                    <div class="search-box" style="flex:1;min-width:220px;">
                        <i class="fas fa-search search-icon"></i>
                        <input autocomplete="nope-buscar-custodio-tabla" type="text" class="search-input" id="buscar-custodio-tabla" placeholder="Buscar custodio por nombre, teléfono..." onkeyup="filtrarTablaCustodios()">
                    </div>
                    <select class="form-select" id="filtro-zona-custodio" style="max-width:220px;" onchange="filtrarTablaCustodios()">
                        <option value="">Todas las zonas</option>
                        <option value="Norte">Norte (San Pedro Sula)</option>
                        <option value="Centro">Centro (Tegucigalpa)</option>
                        <option value="Amatillo">Amatillo</option>
                        <option value="Guasaule">Guasaule</option>
                        <option value="Choluteca">Choluteca</option>
                        <option value="__sin_zona__">Sin asignar</option>
                    </select>
                </div>
                <div class="table-container">
                    <table id="tabla-custodios">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Teléfono</th>
                                <th>Cargo</th>
                                <th>Zona</th>
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

            <!-- TABS PARA SELECCIONAR TIPO DE ASIGNACIÓN -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border); flex-wrap: wrap;">
                    <button class="btn-tab active" onclick="cambiarTipoAsignacion('custodio')" id="tab-custodio">
                        <i class="fas fa-user-shield"></i> Asignar a Custodio
                    </button>
                    <button class="btn-tab" onclick="cambiarTipoAsignacion('cliente')" id="tab-cliente">
                        <i class="fas fa-building"></i> Asignar a Cliente (Transporte)
                    </button>
                </div>
            </div>

            <!-- MODO 1: ASIGNACIÓN A CUSTODIO (ORIGINAL) -->
            <div id="form-custodio-container" class="form-card assign-form">
                <div class="form-header">
                    <div class="form-header-icon"><i class="fas fa-satellite-dish"></i></div>
                    <h3>
                        Nueva Asignación de GPS a Custodio
                        <span class="form-header-note">Todos los campos son obligatorios</span>
                    </h3>
                </div>
                <form autocomplete="off" id="form-asignar" onsubmit="asignarGPS(event)">
                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-list"></i> Información General</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Disponible</label>
                                <div class="searchable-select-wrap" data-searchable-select>
                                    <input type="text" class="form-input searchable-select-search" placeholder="Buscar por IMEI, marca o modelo..." autocomplete="nope-campo">
                                    <select class="form-select searchable-select-native" name="gpsId" onchange="actualizarInfoGPS(this.value)">
                                        <option value="">Seleccione un GPS</option>
                                    </select>
                                    <div class="searchable-select-list"></div>
                                </div>
                                <p id="info-gps-asignar" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user"></i> Custodio Responsable</label>
                                <div class="searchable-select-wrap" data-searchable-select>
                                    <input type="text" class="form-input searchable-select-search" placeholder="Buscar custodio por nombre o cargo..." autocomplete="nope-campo">
                                    <select class="form-select searchable-select-native" name="custodioId" onchange="actualizarInfoCustodio(this.value)">
                                        <option value="">Seleccione custodio</option>
                                    </select>
                                    <div class="searchable-select-list"></div>
                                </div>
                                <p id="info-custodio-asignar" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            </div>
                        </div>
                    </div>

                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-building"></i> Datos de la Asignación</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-building"></i> Cliente/Institución</label>
                                <input autocomplete="nope-cliente" type="text" class="form-input" name="cliente" placeholder="Ej: Policía Nacional, Empresa XYZ" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Asignación</label>
                                <input autocomplete="nope-fechaAsignacion" type="datetime-local" class="form-input" name="fechaAsignacion" required>
                            </div>
                        </div>
                    </div>

                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-pin"></i> Origen (Salida desde)</label>
                                <input autocomplete="nope-origen" type="text" class="form-input" name="origen" placeholder="Ej: Almacén Central, Oficina Principal" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Destino/Zona de Trabajo</label>
                                <input autocomplete="nope-destino" type="text" class="form-input" name="destino" placeholder="Ej: Patrullaje Zona Norte, Zona de Cobertura" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones Adicionales</label>
                        <textarea autocomplete="nope-observaciones" class="form-textarea" name="observaciones" placeholder="Notas sobre la asignación, instrucciones especiales, etc..." required></textarea>
                    </div>

                    <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> Asignar GPS
                        </button>
                    </div>
                </form>
            </div>

            <!-- MODO 2: ASIGNACIÓN A CLIENTE (NUEVO) -->
            <!-- MODO 2: ASIGNACIÓN A CLIENTE (MEJORADO CON TELÉFONO) -->
            <div id="form-cliente-container" class="form-card assign-form hidden">
                <div class="form-header">
                    <div class="form-header-icon"><i class="fas fa-truck"></i></div>
                    <h3>
                        Nueva Asignación de GPS a Cliente (Transporte)
                        <span class="form-header-note">Todos los campos son obligatorios</span>
                    </h3>
                </div>
                <form autocomplete="off" id="form-asignar-cliente" onsubmit="asignarGPSCliente(event)">
                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-list"></i> Información General</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Disponible</label>
                                <div class="searchable-select-wrap" data-searchable-select>
                                    <input type="text" class="form-input searchable-select-search" placeholder="Buscar por IMEI, marca o modelo..." autocomplete="nope-campo">
                                    <select class="form-select searchable-select-native" name="gpsIdCliente" onchange="actualizarInfoGPSCliente(this.value)">
                                        <option value="">Seleccione un GPS</option>
                                    </select>
                                    <div class="searchable-select-list"></div>
                                </div>
                                <p id="info-gps-cliente" style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-building"></i> Cliente</label>
                                <input autocomplete="nope-clienteNombre" type="text" class="form-input" name="clienteNombre" placeholder="Ej: Transportes XYZ, DHL Honduras" required>
                            </div>
                        </div>
                    </div>

                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-truck"></i> Datos del Vehículo/Envío</p>

                        <!-- FILA 1: PILOTO Y TELÉFONO -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user-tie"></i> Piloto/Conductor</label>
                                <input autocomplete="nope-piloto" type="text" class="form-input" name="piloto" placeholder="Nombre del piloto" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone"></i> Teléfono del Piloto</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <select autocomplete="off" class="form-select" name="prefijoPiloto" id="prefijoPiloto" style="max-width: 130px; flex: 0 0 auto;">
                                        <option value="+502">🇬🇹 +502</option>
                                        <option value="+501">🇧🇿 +501</option>
                                        <option value="+503">🇸🇻 +503</option>
                                        <option value="+504" selected>🇭🇳 +504</option>
                                        <option value="+505">🇳🇮 +505</option>
                                        <option value="+506">🇨🇷 +506</option>
                                        <option value="+507">🇵🇦 +507</option>
                                    </select>
                                    <input autocomplete="off" type="text" inputmode="numeric" class="form-input" name="telefonoPiloto" id="telefonoPiloto" placeholder="9999-9999" maxlength="9" required style="flex: 1;" oninput="formatearTelefonoCA(this)" onkeypress="return /[0-9]/.test(event.key)" onpaste="setTimeout(() => formatearTelefonoCA(this), 0)">
                                </div>
                            </div>
                        </div>

                        <!-- FILA 2: PLACA Y CONTENEDOR -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-car"></i> Placa del Vehículo</label>
                                <input autocomplete="nope-placa" type="text" class="form-input" name="placa" placeholder="Ej: HN-001-ABC" style="text-transform: uppercase;" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-box"></i> Contenedor/Carga</label>
                                <input autocomplete="nope-contenedor" type="text" class="form-input" name="contenedor" placeholder="Descripción o número de contenedor" required>
                            </div>
                        </div>

                        <!-- FILA 3: FECHA DE ASIGNACIÓN -->
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Asignación</label>
                            <input autocomplete="nope-fechaAsignacionCliente" type="datetime-local" class="form-input" name="fechaAsignacionCliente" required>
                        </div>
                    </div>

                    <div class="assign-section">
                        <p class="assign-section-title"><i class="fas fa-route"></i> Ruta</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-pin"></i> Origen (Salida desde)</label>
                                <input autocomplete="nope-origenCliente" type="text" class="form-input" name="origenCliente" placeholder="Ej: Almacén Central, Puerto" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Destino</label>
                                <input autocomplete="nope-destinoCliente" type="text" class="form-input" name="destinoCliente" placeholder="Ej: San Pedro Sula, La Ceiba" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-comment"></i> Observaciones Adicionales</label>
                        <textarea autocomplete="nope-observacionesCliente" class="form-textarea" name="observacionesCliente" placeholder="Notas sobre la ruta, instrucciones especiales, números de seguimiento, etc..." required></textarea>
                    </div>

                    <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem;">
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> Asignar GPS a Cliente
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

            <!-- MINI RESUMEN DE PENDIENTES -->
            <div id="retorno-resumen-pendientes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.9rem; margin-bottom: 0;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(227,27,35,.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                    <div>
                        <p style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: .03em;">Pendientes de retorno</p>
                        <p id="retorno-count-total" style="font-size: 1.35rem; font-weight: 800; line-height: 1.1;">0</p>
                    </div>
                </div>
                <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.9rem; margin-bottom: 0;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(245,158,11,.14); color: var(--warning); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <p style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: .03em;">Más de 2 días asignados</p>
                        <p id="retorno-count-atencion" style="font-size: 1.35rem; font-weight: 800; line-height: 1.1;">0</p>
                    </div>
                </div>
                <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.9rem; margin-bottom: 0;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(239,68,68,.14); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <p style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: .03em;">Más de 3 días (crítico)</p>
                        <p id="retorno-count-critico" style="font-size: 1.35rem; font-weight: 800; line-height: 1.1;">0</p>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-header">
                    <h3>🔄 Retorno de GPS</h3>
                </div>
                <form autocomplete="off" id="form-retornar" onsubmit="retornarGPS(event)">
                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📡 Seleccionar GPS a Retornar</h4>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-satellite-dish"></i> GPS Asignado</label>
                            <div class="searchable-select-wrap" data-searchable-select>
                                <input type="text" class="form-input searchable-select-search" placeholder="Buscar por IMEI, cliente o custodio..." autocomplete="nope-campo">
                                <select class="form-select searchable-select-native" name="asignacionId" required onchange="mostrarInfoRetorno(this.value)">
                                    <option value="">Seleccione GPS a retornar</option>
                                </select>
                                <div class="searchable-select-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE DE INFO DINÁMICO PARA CUSTODIO -->
                    <div id="info-retorno-custodio" class="hidden" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-card); border-radius: 12px; border-left: 4px solid var(--info);">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0;">ℹ️ Información de la Asignación (CUSTODIO)</p>
                            <span id="retorno-urgencia-custodio" class="badge badge-success" style="display: none;"></span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">GPS (IMEI)</p>
                                <p id="retorno-imei-custodio" style="font-family: monospace; font-weight: 700;">-</p>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Cliente</p>
                                <p id="retorno-cliente-custodio" style="font-weight: 700;">-</p>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Custodio</p>
                                <p id="retorno-custodio-custodio" style="font-weight: 700;">-</p>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Días Asignado</p>
                                <p id="retorno-dias-custodio" style="font-weight: 700;">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE DE INFO DINÁMICO PARA CLIENTE -->
                    <div id="info-retorno-cliente" class="hidden" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-card); border-radius: 12px; border-left: 4px solid #e31b23;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                            <p style="font-size: 0.75rem; color: #a90f15; text-transform: uppercase; font-weight: 600; margin-bottom: 0;">ℹ️ Información de la Asignación (CLIENTE/TRANSPORTE)</p>
                            <span id="retorno-urgencia-cliente" class="badge badge-success" style="display: none;"></span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">GPS (IMEI)</p>
                                <p id="retorno-imei-cliente" style="font-family: monospace; font-weight: 700; word-break: break-all;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">CLIENTE</p>
                                <p id="retorno-cliente-cliente" style="font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">PILOTO</p>
                                <p id="retorno-piloto-cliente" style="font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">PLACA</p>
                                <p id="retorno-placa-cliente" style="font-family: monospace; font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">ORIGEN</p>
                                <p id="retorno-origen-cliente" style="font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">DESTINO</p>
                                <p id="retorno-destino-cliente" style="font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">CONTENEDOR</p>
                                <p id="retorno-contenedor-cliente" style="font-weight: 700;">-</p>
                            </div>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600;">DÍAS ASIGNADO</p>
                                <p id="retorno-dias-cliente" style="font-weight: 700;">-</p>
                            </div>
                        </div>
                    </div>

                    <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-secondary);">📅 Retorno</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar"></i> Fecha y Hora de Retorno</label>
                                <input autocomplete="nope-fechaRetorno" type="datetime-local" class="form-input" name="fechaRetorno" required>
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
                        <textarea autocomplete="nope-observacionesRetorno" class="form-textarea" name="observacionesRetorno" placeholder="Describa el estado del GPS, daños encontrados, novedades, etc..."></textarea>
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

            <div class="card" style="max-width: 640px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        <i class="fas fa-satellite-dish"></i> IMEI / Número de Serie
                    </label>
                    <div class="flex gap-2">
                        <div style="position: relative; flex: 1;">
                            <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.85rem;"></i>
                            <input autocomplete="nope-buscar-imei-consulta" type="text" class="form-input" id="buscar-imei-consulta" placeholder="Ingrese IMEI del GPS" style="padding-left: 2.3rem; width: 100%;" onkeypress="if(event.key==='Enter'){consultarGPS();}">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="consultarGPS()" style="padding: 0 1.5rem;">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </div>

            <div id="consulta-estado-inicial" class="card" style="max-width: 640px;">
                <div class="empty-state">
                    <i class="fas fa-satellite-dish"></i>
                    <h3>Busca un GPS</h3>
                    <p>Ingresa el IMEI o número de serie para ver su estado, ubicación y asignación actual</p>
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
                <div class="flex justify-between items-center" style="flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Todas las Asignaciones</h3>
                    <button class="btn btn-secondary" onclick="exportarTablaExcel('tabla-historial', 'historial_asignaciones')">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>

                <div class="flex gap-2" style="flex-wrap: wrap; margin-bottom: 1rem; align-items: end;">
                    <div style="position: relative; flex: 1 1 240px; min-width: 220px;">
                        <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.85rem;"></i>
                        <input autocomplete="nope-buscar-historial" type="text" id="buscar-historial" class="form-input" placeholder="Buscar IMEI, custodio, cliente, origen, destino..." style="padding-left: 2.3rem; width: 100%;" oninput="historialPaginaActual=1;renderHistorialOrdenado()">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-secondary); margin-bottom: 0.3rem;">Desde</label>
                        <input autocomplete="nope-filtro-historial-desde" type="date" id="filtro-historial-desde" class="form-input" onchange="historialPaginaActual=1;renderHistorialOrdenado()">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-secondary); margin-bottom: 0.3rem;">Hasta</label>
                        <input autocomplete="nope-filtro-historial-hasta" type="date" id="filtro-historial-hasta" class="form-input" onchange="historialPaginaActual=1;renderHistorialOrdenado()">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-secondary); margin-bottom: 0.3rem;">Zona</label>
                        <select id="filtro-historial-zona" class="form-select" onchange="historialPaginaActual=1;renderHistorialOrdenado()">
                            <option value="">Todas las zonas</option>
                            <option value="Norte">Norte (San Pedro Sula)</option>
                            <option value="Centro">Centro (Tegucigalpa)</option>
                            <option value="Amatillo">Amatillo</option>
                            <option value="Guasaule">Guasaule</option>
                            <option value="Choluteca">Choluteca</option>
                            <option value="__sin_zona__">Sin asignar</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-secondary); margin-bottom: 0.3rem;">Estado</label>
                        <select id="filtro-historial-estado" class="form-select" onchange="historialPaginaActual=1;renderHistorialOrdenado()">
                            <option value="">Todos</option>
                            <option value="asignado">Asignado</option>
                            <option value="devuelto">Devuelto</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary" onclick="limpiarFiltrosHistorial()">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>

                <div id="contador-historial" style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.75rem;"></div>

                <div class="table-container">
                    <table id="tabla-historial">
                        <thead>
                            <tr>
                                <th onclick="ordenarHistorial('imei')" style="cursor:pointer; user-select:none;">IMEI/Serie <span id="sort-imei">↕</span></th>
                                <th onclick="ordenarHistorial('custodio')" style="cursor:pointer; user-select:none;">Custodio <span id="sort-custodio">↕</span></th>
                                <th>Zona</th>
                                <th onclick="ordenarHistorial('cliente')" style="cursor:pointer; user-select:none;">Cliente <span id="sort-cliente">↕</span></th>
                                <th onclick="ordenarHistorial('origen')" style="cursor:pointer; user-select:none;">Origen <span id="sort-origen">↕</span></th>
                                <th onclick="ordenarHistorial('destino')" style="cursor:pointer; user-select:none;">Destino <span id="sort-destino">↕</span></th>
                                <th onclick="ordenarHistorial('fecha')" style="cursor:pointer; user-select:none;">Fecha Asignación <span id="sort-fecha">↓</span></th>
                                <th onclick="ordenarHistorial('estado')" style="cursor:pointer; user-select:none;">Estado <span id="sort-estado">↕</span></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="paginacion-historial" class="flex justify-between items-center" style="flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;"></div>
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
    <div class="modal modal-simple" id="modal-gps">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-simple-header-icon"><i class="fas fa-satellite-dish"></i></div>
                <h3>
                    Agregar Nuevo GPS
                    <div class="modal-simple-subtitle">Registra un dispositivo para asignarlo luego a un custodio o cliente</div>
                </h3>
                <button class="close-modal" onclick="closeModal('modal-gps')"><i class="fas fa-times"></i></button>
            </div>
            <form autocomplete="off" id="form-gps" onsubmit="agregarGPS(event)">
                <div class="modal-body">
                    <div class="modal-section">
                        <p class="modal-simple-section-title"><i class="fas fa-barcode"></i> Identificación del dispositivo</p>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-barcode"></i> IMEI / Número de Serie</label>
                            <input autocomplete="nope-imei" type="text" class="form-input" name="imei" required placeholder="Ej: 123456789012345">
                            <p class="modal-simple-hint">Este número debe ser único para cada GPS.</p>
                        </div>
                    </div>
                    <div class="modal-section">
                        <p class="modal-simple-section-title"><i class="fas fa-info-circle"></i> Detalles del equipo</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-building"></i> Marca</label>
                                <input autocomplete="nope-marca" type="text" class="form-input" name="marca" required placeholder="Ej: Garmin, TomTom">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-tag"></i> Modelo</label>
                                <input autocomplete="nope-modelo" type="text" class="form-input" name="modelo" required placeholder="Ej: GPS-200">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-comment"></i> Descripción <span class="modal-simple-optional">Opcional</span></label>
                            <textarea autocomplete="nope-descripcion" class="form-textarea" name="descripcion" placeholder="Características adicionales del GPS..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-simple-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gps')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Guardar GPS
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal modal-simple" id="modal-custodio">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-simple-header-icon"><i class="fas fa-user-shield"></i></div>
                <h3>
                    Agregar Nuevo Custodio
                    <div class="modal-simple-subtitle">Registra a la persona que podrá recibir asignaciones de GPS</div>
                </h3>
                <button class="close-modal" onclick="closeModal('modal-custodio')"><i class="fas fa-times"></i></button>
            </div>
            <form autocomplete="off" id="form-custodio" onsubmit="agregarCustodio(event)">
                <div class="modal-body">
                    <div class="modal-section">
                        <p class="modal-simple-section-title"><i class="fas fa-id-card"></i> Datos personales</p>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user"></i> Nombre Completo</label>
                            <input autocomplete="nope-nombre" type="text" class="form-input" name="nombre" required placeholder="Ej: Juan Pérez López">
                        </div>
                    </div>
                    <div class="modal-section">
                        <p class="modal-simple-section-title"><i class="fas fa-address-book"></i> Contacto y puesto</p>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i> Teléfono</label>
                            <input autocomplete="nope-telefono" type="tel" class="form-input" name="telefono" required placeholder="Ej: +504 9999-9999">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-briefcase"></i> Cargo</label>
                            <input autocomplete="nope-cargo" type="text" class="form-input" name="cargo" required placeholder="Ej: Oficial, Agente">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-location-dot"></i> Zona</label>
                            <select class="form-select" name="zona">
                                <option value="">Sin asignar</option>
                                <option value="Norte">Norte (San Pedro Sula)</option>
                                <option value="Centro">Centro (Tegucigalpa)</option>
                                <option value="Amatillo">Amatillo</option>
                                <option value="Guasaule">Guasaule</option>
                                <option value="Choluteca">Choluteca</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-simple-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-custodio')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Guardar Custodio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let sidebarCollapsed = false;
        let gpsDispositivos = [];
        let custodios = [];
        let asignaciones = [];

        // Permisos del usuario actual (ver PHP: $es_admin)
        const esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;
        const usuarioRolActual = <?php echo json_encode($usuario_rol); ?>;


        // ==================== VARIABLES GLOBALES PARA NOTIFICACIONES ====================
        let notificacionesVistas = {}; // Almacenamiento en MEMORIA (no localStorage)

        const HTML_NOTIFICACIONES_VACIO = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3 style="font-size: 1.1rem;">Sin notificaciones</h3>
                <p>Las nuevas alertas aparecerán aquí</p>
            </div>
        `;

        function inferirNotifTipo(titulo) {
            const t = (titulo || '').toLowerCase();
            if (t.includes('elimina')) return { color: '#ef4444', bg: 'rgba(239,68,68,.12)', icono: '<i class="fas fa-trash-alt"></i>' };
            if (t.includes('retorna')) return { color: '#10b981', bg: 'rgba(16,185,129,.12)', icono: '<i class="fas fa-undo"></i>' };
            if (t.includes('asigna')) return { color: '#e31b23', bg: 'rgba(227,27,35,.12)', icono: '<i class="fas fa-satellite-dish"></i>' };
            if (t.includes('actualiza')) return { color: '#f59e0b', bg: 'rgba(245,158,11,.12)', icono: '<i class="fas fa-pen"></i>' };
            if (t.includes('exporta')) return { color: '#10b981', bg: 'rgba(16,185,129,.12)', icono: '<i class="fas fa-file-excel"></i>' };
            if (t.includes('agrega') || t.includes('registra')) return { color: '#10b981', bg: 'rgba(16,185,129,.12)', icono: '<i class="fas fa-plus"></i>' };
            return { color: '#e31b23', bg: 'rgba(227,27,35,.12)', icono: '<i class="fas fa-bell"></i>' };
        }

        function actualizarBadgeNotificaciones() {
            const notifBadge = document.getElementById('notif-badge');
            if (!notifBadge) return;
            const noLeidas = document.querySelectorAll('#notification-list .notification-item.no-leida').length;
            notifBadge.textContent = noLeidas;
            notifBadge.classList.toggle('hidden', noLeidas === 0);
            actualizarContadoresPestanas();
        }

        function actualizarSubtituloNotificaciones() {
            const subtitulo = document.getElementById('notification-subtitle');
            if (!subtitulo) return;
            const noLeidas = document.querySelectorAll('#notification-list .notification-item.no-leida').length;
            subtitulo.textContent = noLeidas > 0 ? `${noLeidas} sin leer` : 'Sin notificaciones nuevas';
        }

        // ==================== PESTAÑAS / FILTRO DEL PANEL ====================
        let notifFiltroActivo = 'todas';

        function actualizarContadoresPestanas() {
            const todas = document.querySelectorAll('#notification-list .notification-item').length;
            const noLeidas = document.querySelectorAll('#notification-list .notification-item.no-leida').length;
            const alertas = document.querySelectorAll('#notification-list .notification-item.notif-cat-alerta').length;

            const elTodas = document.getElementById('notif-tab-count-todas');
            const elNoLeidas = document.getElementById('notif-tab-count-no-leidas');
            const elAlertas = document.getElementById('notif-tab-count-alertas');
            if (elTodas) elTodas.textContent = todas;
            if (elNoLeidas) elNoLeidas.textContent = noLeidas;
            if (elAlertas) elAlertas.textContent = alertas;

            const btnMarcarLeidas = document.getElementById('notif-mark-read-btn');
            if (btnMarcarLeidas) btnMarcarLeidas.style.opacity = noLeidas > 0 ? '1' : '0.5';
        }

        function filtrarNotificaciones(filtro) {
            notifFiltroActivo = filtro;

            document.querySelectorAll('#notification-tabs .notif-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filtro === filtro);
            });

            document.querySelectorAll('#notification-list .notification-item').forEach(item => {
                let mostrar = true;
                if (filtro === 'no-leidas') mostrar = item.classList.contains('no-leida');
                else if (filtro === 'alertas') mostrar = item.classList.contains('notif-cat-alerta');
                item.classList.toggle('filtro-oculto', !mostrar);
            });
        }

        function marcarTodasLeidas() {
            document.querySelectorAll('#notification-list .notification-item.no-leida').forEach(item => {
                item.classList.remove('no-leida');
                item.classList.add('leida');
            });
            actualizarBadgeNotificaciones();
            actualizarSubtituloNotificaciones();
            if (notifFiltroActivo === 'no-leidas') filtrarNotificaciones('no-leidas');
        }

        // Anima la campana (rebote + destello) cuando llega una notificación nueva.
        function animarCampanaNotificaciones() {
            const icono = document.getElementById('notif-bell-icon');
            const boton = document.getElementById('notif-bell-btn');
            if (icono) {
                icono.classList.remove('notif-bell-ring');
                void icono.offsetWidth; // reinicia la animación si ya estaba corriendo
                icono.classList.add('notif-bell-ring');
                setTimeout(() => icono.classList.remove('notif-bell-ring'), 650);
            }
            if (boton) {
                boton.classList.remove('notif-bell-pulse');
                void boton.offsetWidth;
                boton.classList.add('notif-bell-pulse');
                setTimeout(() => boton.classList.remove('notif-bell-pulse'), 650);
            }
        }

        // Convierte un timestamp en un texto relativo tipo "hace 5 min".
        function tiempoRelativo(ts) {
            const segundos = Math.floor((Date.now() - ts) / 1000);
            if (segundos < 20) return 'Ahora';
            if (segundos < 60) return `Hace ${segundos} s`;
            const minutos = Math.floor(segundos / 60);
            if (minutos < 60) return `Hace ${minutos} min`;
            const horas = Math.floor(minutos / 60);
            if (horas < 24) return `Hace ${horas} h`;
            const dias = Math.floor(horas / 24);
            return `Hace ${dias} día${dias !== 1 ? 's' : ''}`;
        }

        // Refresca los textos "Hace X min" de todas las notificaciones visibles.
        function actualizarTiemposNotificaciones() {
            document.querySelectorAll('#notification-list .notification-item[data-ts]').forEach(item => {
                const ts = parseInt(item.dataset.ts, 10);
                const tiempoEl = item.querySelector('.notification-item-time');
                if (tiempoEl && !isNaN(ts)) tiempoEl.textContent = tiempoRelativo(ts);
            });
        }

        setInterval(actualizarTiemposNotificaciones, 30000);

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

            // Repinta las etiquetas de zona con los colores correctos para el nuevo tema
            if (typeof actualizarTablaCustodios === 'function' && Array.isArray(custodios)) actualizarTablaCustodios();
            if (typeof renderHistorialOrdenado === 'function' && Array.isArray(asignaciones)) renderHistorialOrdenado();
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

            // ✅ Solo intentar marcar el nav si hay un evento de clic real
            if (typeof event !== 'undefined' && event && event.target) {
                const navItem = event.target.closest('.nav-item');
                if (navItem) navItem.classList.add('active');
            } else {
                // Llamada programática (ej: irARetornar) → marcar nav manualmente
                document.querySelectorAll('.nav-item').forEach(item => {
                    if (item.getAttribute('onclick')?.includes(`'${moduleName}'`)) {
                        item.classList.add('active');
                    }
                });
            }

            if (moduleName === 'alertas') {
                actualizarAlertasRecuperacion();
            }

            if (moduleName === 'mapa') {
                actualizarMapaEnVivo();
            }
        }

        // ============================================================
        // Exportar tablas a Excel (.xlsx)
        // Lee directamente el <table> ya renderizado (respeta la
        // búsqueda y el orden aplicados en pantalla).
        // ============================================================
        function exportarTablaExcel(tableId, nombreArchivo) {
            if (typeof XLSX === 'undefined') {
                alert('❌ No se pudo cargar la librería de exportación. Verifica tu conexión a internet.');
                return;
            }

            const tabla = document.getElementById(tableId);
            if (!tabla) {
                alert('❌ No se encontró la tabla a exportar');
                return;
            }

            const headerCells = Array.from(tabla.querySelectorAll('thead th'));
            const indicesExcluidos = [];
            const headers = [];
            headerCells.forEach((th, idx) => {
                const texto = th.textContent.replace(/[↕↑↓]/g, '').trim();
                if (texto.toUpperCase() === 'ACCIONES') {
                    indicesExcluidos.push(idx);
                    return;
                }
                headers.push(texto || `Columna ${idx + 1}`);
            });

            const filas = Array.from(tabla.querySelectorAll('tbody tr'));
            const datos = [];

            filas.forEach(fila => {
                const celdas = Array.from(fila.querySelectorAll('td'));
                // Omitir filas de "estado vacío" (colspan) o de "sin resultados"
                if (celdas.length <= 1 || celdas.length < headerCells.length) return;

                const registro = {};
                let col = 0;
                celdas.forEach((celda, idx) => {
                    if (indicesExcluidos.includes(idx)) return;
                    registro[headers[col] || `Columna ${col + 1}`] = celda.textContent.trim();
                    col++;
                });
                datos.push(registro);
            });

            if (datos.length === 0) {
                alert('⚠️ No hay datos para exportar con el filtro actual');
                return;
            }

            const hoja = XLSX.utils.json_to_sheet(datos);
            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, hoja, 'Datos');

            const fechaHoy = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(libro, `${nombreArchivo}_${fechaHoy}.xlsx`);

            agregarNotificacion('Exportación completa', `Se exportaron ${datos.length} registro${datos.length !== 1 ? 's' : ''} a Excel`);
        }

        function agregarNotificacion(titulo, mensaje, tipoOverride) {
            const notificationList = document.getElementById('notification-list');

            const vacio = notificationList.querySelector('.empty-state');
            if (vacio) vacio.remove();

            const { color, bg, icono } = tipoOverride || inferirNotifTipo(titulo);
            const ahora = Date.now();

            const notifItem = document.createElement('div');
            notifItem.className = 'notification-item no-leida notif-cat-sistema';
            const notifId = 'notif-' + ahora;
            notifItem.id = notifId;
            notifItem.dataset.ts = ahora;
            notifItem.style.setProperty('--notif-color', color);
            notifItem.style.setProperty('--notif-bg', bg);
            notifItem.innerHTML = `
                <div class="notif-icon-avatar">${icono}</div>
                <div class="notification-item-content">
                    <div class="notification-item-header">
                        <span class="notification-item-title">${titulo}</span>
                        <span class="notification-item-time">Ahora</span>
                    </div>
                    <div class="notification-item-body">${mensaje}</div>
                    <div style="margin-top: 0.85rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-success" onclick="confirmarNotificacion('${notifId}')" style="padding: 0.4rem 0.9rem; font-size: 0.8rem;">
                            <i class="fas fa-check"></i> Confirmar lectura
                        </button>
                    </div>
                </div>
            `;

            notificationList.insertBefore(notifItem, notificationList.firstChild);

            if (notifFiltroActivo !== 'todas') {
                const mostrar = notifFiltroActivo === 'no-leidas';
                notifItem.classList.toggle('filtro-oculto', !mostrar);
            }

            actualizarBadgeNotificaciones();
            actualizarSubtituloNotificaciones();
            animarCampanaNotificaciones();
        }

        function confirmarNotificacion(notifId) {
            const notifItem = document.getElementById(notifId);
            if (!notifItem) return;

            notifItem.classList.remove('no-leida');
            notifItem.classList.add('leida', 'saliendo');

            setTimeout(() => {
                notifItem.remove();
                actualizarBadgeNotificaciones();
                actualizarSubtituloNotificaciones();
                const notificationList = document.getElementById('notification-list');
                if (notificationList && !notificationList.querySelector('.notification-item')) {
                    notificationList.innerHTML = HTML_NOTIFICACIONES_VACIO;
                }
            }, 300);
        }

        // Refuerza el bloqueo de autocompletado/autofill del navegador en el formulario "Asignar a Cliente"
        function desactivarAutocompletadoFormCliente() {
            const form = document.getElementById('form-asignar-cliente');
            if (!form) return;
            form.setAttribute('autocomplete', 'off');
            form.querySelectorAll('input, select, textarea').forEach(function(el) {
                const token = 'off-' + Math.random().toString(36).slice(2);
                el.setAttribute('autocomplete', token);
                el.setAttribute('autocorrect', 'off');
                el.setAttribute('autocapitalize', 'off');
                el.setAttribute('spellcheck', 'false');
                el.setAttribute('data-lpignore', 'true');
                el.setAttribute('data-1p-ignore', 'true');
                el.setAttribute('data-form-type', 'other');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Iniciando carga de datos');
            cargarDatosUsuario();
            desactivarAutocompletadoFormCliente();

            // Solicitar permisos de notificaciones del navegador
            if ('Notification' in window && Notification.permission === 'default') {
                console.log('Solicitando permisos de notificación...');
                Notification.requestPermission();
            }

            // Cargar datos del servidor y LUEGO iniciar notificaciones
            cargarDatosDelServidor().then(() => {
                console.log('✅ Datos cargados, iniciando verificación de notificaciones');
                iniciarVerificacionNotificaciones();
            }).catch(error => {
                console.error('Error al cargar datos:', error);
                // Aún así iniciar verificación por si acaso
                iniciarVerificacionNotificaciones();
            });
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
                console.log('Iniciando carga de datos del servidor...');
                const [gpsResponse, custodiosResponse, asignacionesResponse] = await Promise.all([
                    fetch('api/get_gps.php'),
                    fetch('api/get_custodios.php'),
                    fetch('api/get_asignaciones.php')
                ]);

                console.log('Respuestas recibidas');

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
                    gpsDispositivos: gpsDispositivos.length,
                    custodios: custodios.length,
                    asignaciones: asignaciones.length
                });

                actualizarTodo();
                console.log('✅ actualizarTodo() ejecutado');
                return Promise.resolve();

            } catch (error) {
                console.error('Error cargando datos del servidor:', error);
                gpsDispositivos = [];
                custodios = [];
                asignaciones = [];
                actualizarTodo();
                return Promise.reject(error);
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

            const gpsId = formData.get('gpsId');
            const custodioId = formData.get('custodioId');

            // Validar que se hayan seleccionado GPS y custodio
            if (!gpsId || !custodioId) {
                alert('❌ Por favor completa todos los campos requeridos');
                return;
            }

            // Obtener el GPS seleccionado
            const gps = gpsDispositivos.find(g => parseInt(g.id) === parseInt(gpsId));
            if (!gps) {
                alert('❌ GPS no encontrado');
                return;
            }

            // Obtener el custodio seleccionado
            const custodio = custodios.find(c => parseInt(c.id) === parseInt(custodioId));
            if (!custodio) {
                alert('❌ Custodio no encontrado');
                return;
            }

            // Validar que el GPS no tenga una asignación activa
            const asignacionActiva = asignaciones.find(a =>
                parseInt(a.gps_id) === parseInt(gpsId) && a.estado === 'asignado'
            );

            if (asignacionActiva) {
                alert('❌ Este GPS ya tiene una asignación activa.\n\nCustodio: ' + asignacionActiva.custodio_nombre +
                    '\nAsignado desde: ' + new Date(asignacionActiva.fecha_asignacion).toLocaleString('es-HN'));
                return;
            }



            // VALIDACIÓN FUERTE: Rango de fechas (no más de 7 días anteriores)
            const hoyActual = new Date();
            const hoySolo = new Date(hoyActual.getFullYear(), hoyActual.getMonth(), hoyActual.getDate());

            // Calcular fecha mínima permitida (7 días anteriores)
            const fechaMinima = new Date(hoySolo);
            fechaMinima.setDate(fechaMinima.getDate() - 7);

            // Obtener la fecha de asignación del formulario
            const fechaAsignacionInput = event.target.querySelector('input[name="fecha_asignacion"]');
            let fechaSeleccionada = new Date(hoySolo); // Por defecto, hoy

            if (fechaAsignacionInput && fechaAsignacionInput.value) {
                fechaSeleccionada = new Date(fechaAsignacionInput.value);
            }

            const fechaSeleccionadaSolo = new Date(fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth(), fechaSeleccionada.getDate());

            // LOG PARA DEBUG
            console.log('=== VALIDACIÓN DE FECHA ===');
            console.log('Fecha hoy: ' + hoySolo.toLocaleDateString('es-HN') + ' (' + hoySolo.getTime() + ')');
            console.log('Fecha mínima (7 días atrás): ' + fechaMinima.toLocaleDateString('es-HN') + ' (' + fechaMinima.getTime() + ')');
            console.log('Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN') + ' (' + fechaSeleccionadaSolo.getTime() + ')');
            console.log('¿Es válida?', fechaSeleccionadaSolo.getTime() >= fechaMinima.getTime() && fechaSeleccionadaSolo.getTime() <= hoySolo.getTime());

            // VALIDAR QUE NO SEA MÁS DE 7 DÍAS ANTERIOR
            if (fechaSeleccionadaSolo.getTime() < fechaMinima.getTime()) {
                const diferenciaDias = Math.floor((hoySolo.getTime() - fechaSeleccionadaSolo.getTime()) / (1000 * 60 * 60 * 24));
                alert('❌ NO PUEDES ASIGNAR UN GPS CON UNA FECHA ANTERIOR A 7 DÍAS.\n\n' +
                    'Fecha mínima permitida: ' + fechaMinima.toLocaleDateString('es-HN') + '\n' +
                    'Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN') + '\n' +
                    'Días de diferencia: ' + diferenciaDias + ' días');
                console.error('❌ RECHAZADA: Fecha anterior a 7 días');
                return;
            }

            // VALIDAR QUE NO SEA UNA FECHA FUTURA
            if (fechaSeleccionadaSolo.getTime() > hoySolo.getTime()) {
                alert('❌ NO PUEDES ASIGNAR UN GPS CON UNA FECHA FUTURA.\n\n' +
                    'Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN') + '\n' +
                    'Fecha de hoy: ' + hoySolo.toLocaleDateString('es-HN'));
                console.error('❌ RECHAZADA: Fecha futura');
                return;
            }

            console.log('✅ Fecha válida - Procediendo con asignación');

            try {
                const response = await fetch('api/assign_gps.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('GPS Asignado', `${gps.imei} asignado a ${custodio.nombre}`);
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
            const asignacionId = formData.get('asignacionId');

            console.log('🔄 INICIANDO RETORNO DE GPS');
            console.log('Asignación ID:', asignacionId);

            // VALIDAR QUE SE HAYA SELECCIONADO UNA ASIGNACIÓN
            if (!asignacionId) {
                alert('❌ Por favor selecciona un GPS para retornar');
                return;
            }

            // OBTENER LA ASIGNACIÓN
            const asignacion = Array.isArray(asignaciones) ?
                asignaciones.find(a => parseInt(a.id) === parseInt(asignacionId)) : null;

            if (!asignacion) {
                alert('❌ Asignación no encontrada');
                return;
            }

            console.log('📍 Asignación encontrada:', asignacion);
            console.log('📍 Tipo de asignación:', asignacion.tipo_asignacion);

            // VALIDAR FECHA: la fecha de retorno elegida no puede ser anterior a la fecha de asignación.
            // (Antes se comparaba incorrectamente contra la fecha/hora actual del sistema,
            // lo cual bloqueaba el retorno de cualquier asignación registrada con fecha futura,
            // sin importar qué fecha de retorno se seleccionara en el formulario.)
            const fechaRetornoInput = formData.get('fechaRetorno');
            const fechaAsignacion = new Date(asignacion.fecha_asignacion);

            if (fechaRetornoInput) {
                const fechaRetorno = new Date(fechaRetornoInput);

                const fechaAsignacionSolo = new Date(fechaAsignacion.getFullYear(), fechaAsignacion.getMonth(), fechaAsignacion.getDate());
                const fechaRetornoSolo = new Date(fechaRetorno.getFullYear(), fechaRetorno.getMonth(), fechaRetorno.getDate());

                if (fechaRetornoSolo.getTime() < fechaAsignacionSolo.getTime()) {
                    alert('❌ La fecha de retorno no puede ser anterior a la fecha de asignación.\n\n' +
                        'Fecha de asignación: ' + fechaAsignacion.toLocaleString('es-HN') + '\n' +
                        'Fecha de retorno seleccionada: ' + fechaRetorno.toLocaleString('es-HN'));
                    return;
                }
            }

            // ✅ AQUÍ VA LA LÓGICA: DETERMINAR QUÉ API LLAMAR
            let apiEndpoint = 'api/return_gps.php';
            const tipoAsignacion = asignacion.tipo_asignacion || 'custodio';

            if (tipoAsignacion === 'cliente') {
                apiEndpoint = 'api/return_gps_cliente.php';
                console.log('✅ Es asignación a CLIENTE - Usando:', apiEndpoint);
            } else {
                console.log('✅ Es asignación a CUSTODIO - Usando:', apiEndpoint);
            }

            try {
                console.log('📤 Enviando petición a:', apiEndpoint);

                const response = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });

                console.log('📥 Respuesta recibida. Status:', response.status);

                const data = await response.json();

                console.log('📊 Datos JSON parseados:', data);

                if (data.success) {
                    console.log('✅ RETORNO EXITOSO');
                    agregarNotificacion('GPS Retornado', `GPS ${asignacion.imei} retornado exitosamente`);
                    event.target.reset();

                    // LIMPIAR BLOQUES DE INFO
                    const infoRetornoCustodio = document.getElementById('info-retorno-custodio');
                    const infoRetornoCliente = document.getElementById('info-retorno-cliente');
                    if (infoRetornoCustodio) infoRetornoCustodio.classList.add('hidden');
                    if (infoRetornoCliente) infoRetornoCliente.classList.add('hidden');

                    await cargarDatosDelServidor();
                    alert('✅ Retorno registrado correctamente');
                } else {
                    console.error('❌ ERROR EN RESPUESTA:', data.message);
                    alert('❌ ' + (data.message || 'Error al retornar GPS'));
                }
            } catch (error) {
                console.error('❌ ERROR DE RED/PARSING:', error);
                console.error('Stack:', error.stack);
                alert('❌ Error al retornar GPS: ' + error.message);
            }
        }

        // ==================== ACTUALIZAR DASHBOARD PARA INCLUIR CLIENTES ====================

        // REEMPLAZAR la función actualizarDashboard existente con esta:
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
            let asignacionesActivas = Array.isArray(asignaciones) ? asignaciones.filter(a => a.estado === 'asignado') : [];

            const buscarInput = document.getElementById('buscar-gps-asignados');
            const termino = buscarInput ? buscarInput.value.trim().toLowerCase() : '';
            if (termino) {
                asignacionesActivas = asignacionesActivas.filter(a => {
                    const campos = [
                        a.imei, a.marca, a.modelo, a.custodio_nombre, a.cliente,
                        a.piloto, a.placa, a.origen, a.destino
                    ];
                    return campos.some(c => (c || '').toString().toLowerCase().includes(termino));
                });
            }

            if (asignacionesActivas.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center">
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>${termino ? 'Sin resultados' : 'No hay GPS asignados'}</h3>
                <p>${termino ? 'Ningún registro coincide con tu búsqueda' : 'Todos los GPS están disponibles'}</p>
            </div>
        </td></tr>`;
                return;
            }

            let html = '';
            asignacionesActivas.forEach(asignacion => {
                const fechaObj = new Date(asignacion.fecha_asignacion);
                const fechaFormato = fechaObj.toLocaleString('es-HN');
                const diasAsignado = diasDesdeAsignacion(asignacion.fecha_asignacion);

                // Determinar si es asignación a custodio o cliente
                const tipoAsignacion = asignacion.tipo_asignacion || 'custodio';

                // VALORES POR TIPO
                let asignadoA = '';
                let pilotoCustom = '';
                let placa = '';
                let cliente = '';
                let badgeTipo = '';

                if (tipoAsignacion === 'cliente') {
                    asignadoA = asignacion.cliente || '-';
                    pilotoCustom = asignacion.piloto || '-';
                    placa = asignacion.placa || '-';
                    cliente = asignacion.cliente || '-';
                    badgeTipo = '<span class="badge" style="background: #fde8e9; color: #a90f15;"><i class="fas fa-building"></i> Cliente</span>';
                } else {
                    asignadoA = asignacion.custodio_nombre || '-';
                    pilotoCustom = asignacion.custodio_nombre || '-';
                    placa = '-';
                    cliente = asignacion.cliente || '-';
                    badgeTipo = '<span class="badge" style="background: #f9e0e1; color: #9f1117;"><i class="fas fa-user"></i> Custodio</span>';
                }

                html += `
            <tr>
                <td style="font-family: monospace; font-weight: 600;">${asignacion.imei || 'N/A'}</td>
                <td>${asignacion.marca || '-'} ${asignacion.modelo || '-'}</td>
                <td style="font-weight: 600;">${asignadoA}</td>
                <td>${badgeTipo}</td>
                <td>${pilotoCustom}</td>
                <td style="font-family: monospace; font-weight: 600;">${placa}</td>
                <td>${cliente}</td>
                <td style="font-size: 0.9rem;">${fechaFormato}</td>
                <td style="text-align: center; font-weight: 600;">
                    <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.85rem;">
                        ${diasAsignado} días
                    </span>
                </td>
                <td><span class="badge badge-warning">Asignado</span></td>
                <td>
                    <button class="btn btn-primary" onclick="verDetallesAsignacion(${asignacion.id}, '${tipoAsignacion}')" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; white-space: nowrap;">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                </td>
            </tr>`;
            });

            tbody.innerHTML = html;
        }

        // NUEVA FUNCIÓN para ver detalles de asignación
        function verDetallesAsignacion(asignacionId, tipoAsignacion) {
            const asignacion = asignaciones.find(a => parseInt(a.id) === parseInt(asignacionId));
            if (!asignacion) return;

            let html = '';

            if (tipoAsignacion === 'cliente') {
                html = `
    <div class="modal active" id="modal-detalles-asignacion" style="display: flex !important; z-index: 10000;">
        <div class="modal-content" style="max-width: 580px; margin: auto; border-radius: 24px; overflow: hidden;">
            
            <!-- HEADER -->
            <div style="background: linear-gradient(135deg, #b30f16, #e31b23); padding: 1.75rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">🚚</div>
                    <div>
                        <h3 style="margin: 0; color: white; font-size: 1.15rem; font-weight: 700;">Asignación a Cliente</h3>
                        <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 0.8rem;">Transporte / Logística</p>
                    </div>
                </div>
                <button onclick="cerrarDetallesAsignacion()" style="background: rgba(255,255,255,0.15); border: none; color: white; width: 36px; height: 36px; border-radius: 10px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center;">✕</button>
            </div>

            <div style="padding: 1.75rem 2rem; background: var(--bg-card);">

                <!-- SECCIÓN: GPS -->
                <div style="margin-bottom: 1.25rem;">
                    <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">📡 Dispositivo GPS</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">IMEI</p>
                            <p style="font-family: monospace; font-weight: 700; margin: 0; font-size: 1rem; color: var(--text-primary);">${asignacion.imei}</p>
                        </div>
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Marca / Modelo</p>
                            <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.marca} ${asignacion.modelo}</p>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: CLIENTE -->
                <div style="margin-bottom: 1.25rem;">
                    <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">🏢 Cliente</p>
                    <div style="padding: 0.9rem 1rem; background: linear-gradient(135deg, #fff4f4, #fde8e9); border-radius: 12px; border: 1px solid #f5b6b9;">
                        <p style="font-weight: 700; margin: 0; font-size: 1.1rem; color: #a90f15;">${asignacion.cliente}</p>
                    </div>
                </div>

                <!-- SECCIÓN: PILOTO -->
                <div style="margin-bottom: 1.25rem;">
                    <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">👤 Piloto / Conductor</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Nombre</p>
                            <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.piloto || '—'}</p>
                        </div>
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">📞 Teléfono</p>
                            <p style="font-family: monospace; font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.telefono || '—'}</p>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: VEHÍCULO -->
                <div style="margin-bottom: 1.25rem;">
                    <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">🚗 Vehículo / Carga</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Placa</p>
                            <p style="font-family: monospace; font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.placa || '—'}</p>
                        </div>
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Contenedor</p>
                            <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.contenedor || '—'}</p>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: RUTA -->
                <div style="margin-bottom: 1.25rem;">
                    <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">📍 Ruta</p>
                    <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center;">
                        <div style="padding: 0.9rem 1rem; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                            <p style="font-size: 0.7rem; color: #166534; margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Origen</p>
                            <p style="font-weight: 700; margin: 0; color: #15803d;">${asignacion.origen}</p>
                        </div>
                        <div style="font-size: 1.2rem; color: var(--text-secondary); text-align: center;">→</div>
                        <div style="padding: 0.9rem 1rem; background: #fef2f2; border-radius: 12px; border: 1px solid #fecaca;">
                            <p style="font-size: 0.7rem; color: #991b1b; margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Destino</p>
                            <p style="font-weight: 700; margin: 0; color: #dc2626;">${asignacion.destino}</p>
                        </div>
                    </div>
                </div>

                <!-- OBSERVACIONES (condicional) -->
                ${asignacion.observaciones ? `
                <div style="margin-bottom: 1.25rem; padding: 1rem; background: #fffbeb; border-radius: 12px; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.7rem; color: #92400e; margin: 0 0 0.4rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">💬 Observaciones</p>
                    <p style="margin: 0; color: #78350f; font-size: 0.9rem; line-height: 1.5;">${asignacion.observaciones}</p>
                </div>` : ''}

                <!-- FECHA -->
                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                    <span style="font-size: 1.5rem;">📅</span>
                    <div>
                        <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.2rem; font-weight: 600; text-transform: uppercase;">Fecha de Asignación</p>
                        <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${new Date(asignacion.fecha_asignacion).toLocaleString('es-HN')}</p>
                    </div>
                </div>

                <!-- BOTÓN -->
                <button onclick="cerrarDetallesAsignacion()" style="width: 100%; padding: 1rem; background: linear-gradient(135deg, #b30f16, #e31b23); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer;">
                    Entendido
                </button>
            </div>
        </div>
    </div>`;


            } else {
                // Caso CUSTODIO
                html = `
        <div class="modal active" id="modal-detalles-asignacion" style="display: flex !important; z-index: 10000;">
            <div class="modal-content" style="max-width: 580px; margin: auto; border-radius: 24px; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #a90f15, #c31920); padding: 1.75rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">🛡️</div>
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 1.15rem; font-weight: 700;">Asignación a Custodio</h3>
                            <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 0.8rem;">Detalle completo</p>
                        </div>
                    </div>
                    <button onclick="cerrarDetallesAsignacion()" style="background: rgba(255,255,255,0.15); border: none; color: white; width: 36px; height: 36px; border-radius: 10px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center;">✕</button>
                </div>

                <div style="padding: 1.75rem 2rem; background: var(--bg-card);">

                    <div style="margin-bottom: 1.25rem;">
                        <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">📡 Dispositivo GPS</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">IMEI</p>
                                <p style="font-family: monospace; font-weight: 700; margin: 0; font-size: 1rem; color: var(--text-primary);">${asignacion.imei}</p>
                            </div>
                            <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Marca / Modelo</p>
                                <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.marca} ${asignacion.modelo}</p>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">👤 Custodio</p>
                        <div style="padding: 0.9rem 1rem; background: linear-gradient(135deg, #fff4f4, #f9e7e8); border-radius: 12px; border: 1px solid #efb4b7;">
                            <p style="font-weight: 700; margin: 0 0 0.25rem; font-size: 1.1rem; color: #8f0d13;">${asignacion.custodio_nombre}</p>
                            <p style="font-family: monospace; font-size: 0.9rem; color: #a90f15; margin: 0;">${asignacion.custodio_telefono || 'Sin teléfono'}</p>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">🏢 Cliente</p>
                        <div style="padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${asignacion.cliente || '—'}</p>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <p style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 0.75rem 0;">📍 Ruta</p>
                        <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center;">
                            <div style="padding: 0.9rem 1rem; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                <p style="font-size: 0.7rem; color: #166534; margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Origen</p>
                                <p style="font-weight: 700; margin: 0; color: #15803d;">${asignacion.origen || '—'}</p>
                            </div>
                            <div style="font-size: 1.2rem; color: var(--text-secondary); text-align: center;">→</div>
                            <div style="padding: 0.9rem 1rem; background: #fef2f2; border-radius: 12px; border: 1px solid #fecaca;">
                                <p style="font-size: 0.7rem; color: #991b1b; margin: 0 0 0.3rem; font-weight: 600; text-transform: uppercase;">Destino</p>
                                <p style="font-weight: 700; margin: 0; color: #dc2626;">${asignacion.destino || '—'}</p>
                            </div>
                        </div>
                    </div>

                    ${asignacion.observaciones ? `
                    <div style="margin-bottom: 1.25rem; padding: 1rem; background: #fffbeb; border-radius: 12px; border-left: 3px solid #f59e0b;">
                        <p style="font-size: 0.7rem; color: #92400e; margin: 0 0 0.4rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">💬 Observaciones</p>
                        <p style="margin: 0; color: #78350f; font-size: 0.9rem; line-height: 1.5;">${asignacion.observaciones}</p>
                    </div>` : ''}

                    <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                        <span style="font-size: 1.5rem;">📅</span>
                        <div>
                            <p style="font-size: 0.7rem; color: var(--text-secondary); margin: 0 0 0.2rem; font-weight: 600; text-transform: uppercase;">Fecha de Asignación</p>
                            <p style="font-weight: 700; margin: 0; color: var(--text-primary);">${new Date(asignacion.fecha_asignacion).toLocaleString('es-HN')}</p>
                        </div>
                    </div>

                    <button onclick="cerrarDetallesAsignacion()" style="width: 100%; padding: 1rem; background: linear-gradient(135deg, #a90f15, #c31920); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer;">
                        Entendido
                    </button>
                </div>
            </div>
        </div>`;
            }

            document.body.insertAdjacentHTML('beforeend', html);
        }

        function cerrarDetallesAsignacion() {
            const modal = document.getElementById('modal-detalles-asignacion');
            if (modal) {
                modal.remove();
            }
        }

        // ACTUALIZAR TABLA DE HISTORIAL PARA INCLUIR CLIENTES
        function actualizarTablaHistorial() {
            console.log('Actualizando tabla historial con datos:', asignaciones);
            const tbody = document.querySelector('#tabla-historial tbody');

            if (!Array.isArray(asignaciones) || asignaciones.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align: center;"><div class="empty-state"><i class="fas fa-history"></i><h3>No hay historial</h3><p>Las asignaciones aparecerán aquí</p></div></td></tr>`;
                return;
            }

            // Delegado a renderHistorialOrdenado() para respetar la búsqueda y el ordenamiento activo
            renderHistorialOrdenado();
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
                   <td>${(() => {
                    const asignacion = Array.isArray(asignaciones) ? asignaciones.find(a => parseInt(a.gps_id) === parseInt(gps.id) && a.estado === 'asignado') : null;
                    if (!asignacion) return '-';
                    if (asignacion.tipo_asignacion === 'cliente') return asignacion.cliente || '-';
                    return asignacion.custodio_nombre || '-';
})()}</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn" onclick="editarGPS(${gps.id})" style="padding: 0.5rem 1rem; background-color: #fbbf24; color: #000; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            ${esAdmin ? `<button class="btn btn-danger" onclick="eliminarGPS(${gps.id})" style="padding: 0.5rem 1rem;">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>` : ''}
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

        // Paleta de colores por zona (fondo/texto para tema claro y oscuro)
        const PALETA_ZONAS = {
            'Norte':     { light: '#dbeafe', dark: '#1e3a8a', textLight: '#1e40af', textDark: '#bfdbfe' },
            'Centro':    { light: '#ede9fe', dark: '#4c1d95', textLight: '#5b21b6', textDark: '#ddd6fe' },
            'Amatillo':  { light: '#fef3c7', dark: '#78350f', textLight: '#92400e', textDark: '#fde68a' },
            'Guasaule':  { light: '#dcfce7', dark: '#14532d', textLight: '#166534', textDark: '#bbf7d0' },
            'Choluteca': { light: '#fce7f3', dark: '#831843', textLight: '#9d174d', textDark: '#fbcfe8' }
        };
        // Colores de respaldo para zonas nuevas que no estén en la paleta anterior
        const PALETA_ZONAS_RESPALDO = [
            { light: '#e0f2fe', dark: '#0c4a6e', textLight: '#075985', textDark: '#bae6fd' },
            { light: '#fee2e2', dark: '#7f1d1d', textLight: '#991b1b', textDark: '#fecaca' },
            { light: '#f3e8ff', dark: '#581c87', textLight: '#6b21a8', textDark: '#e9d5ff' },
            { light: '#fff7ed', dark: '#7c2d12', textLight: '#9a3412', textDark: '#fed7aa' },
            { light: '#ecfeff', dark: '#164e63', textLight: '#155e75', textDark: '#a5f3fc' }
        ];

        function colorZona(zona) {
            if (PALETA_ZONAS[zona]) return PALETA_ZONAS[zona];
            let hash = 0;
            for (let i = 0; i < zona.length; i++) hash = zona.charCodeAt(i) + ((hash << 5) - hash);
            return PALETA_ZONAS_RESPALDO[Math.abs(hash) % PALETA_ZONAS_RESPALDO.length];
        }

        function badgeZona(zona) {
            if (!zona) return `<span style="color:var(--text-secondary);">Sin asignar</span>`;
            const c = colorZona(zona);
            const esOscuro = document.body.classList.contains('dark-mode');
            const bg = esOscuro ? c.dark : c.light;
            const color = esOscuro ? c.textDark : c.textLight;
            return `<span class="badge" style="background:${bg}; color:${color};">${zona}</span>`;
        }

        function actualizarTablaCustodios() {
            console.log('Actualizando tabla custodios con datos:', custodios);
            const tbody = document.querySelector('#tabla-custodios tbody');
            const activos = custodios.filter(c => c.estado !== 'inactivo');

            if (!Array.isArray(activos) || activos.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center;"><div class="empty-state"><i class="fas fa-users"></i><h3>No hay custodios registrados</h3><p>Agrega el primer custodio</p></div></td></tr>`;
                return;
            }

            let html = '';
            activos.forEach(custodio => {
                const gpsAsignados = Array.isArray(asignaciones) ? asignaciones.filter(a => parseInt(a.custodio_id) === parseInt(custodio.id) && a.estado === 'asignado').length : 0;

                html += `<tr data-zona="${custodio.zona || ''}">
                    <td>${custodio.nombre || '-'}</td>
                    <td>${custodio.telefono || '-'}</td>
                    <td>${custodio.cargo || '-'}</td>
                    <td>${badgeZona(custodio.zona)}</td>
                    <td><span class="badge badge-info">${gpsAsignados}</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn" onclick="editarCustodio(${custodio.id})" style="padding: 0.5rem 1rem; background-color: #fbbf24; color: #000; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            ${esAdmin ? `<button class="btn btn-danger" onclick="eliminarCustodio(${custodio.id})" style="padding: 0.5rem 1rem;">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>` : ''}
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
            document.querySelector('#form-custodio select[name="zona"]').value = custodio.zona || '';

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

        function cargarSelectores() {
            console.log('🔄 Cargando selectores...');

            // ✅ OPCIÓN 1: Para el formulario de asignación a CUSTODIO
            const selectGPS = document.querySelector('#form-asignar select[name="gpsId"]');
            if (selectGPS && Array.isArray(gpsDispositivos)) {
                const disponibles = gpsDispositivos.filter(g => g.estado === 'disponible');
                selectGPS.innerHTML = '<option value="">Seleccione un GPS</option>';
                disponibles.forEach(g => {
                    selectGPS.innerHTML += `<option value="${g.id}">${g.imei} - ${g.marca} ${g.modelo}</option>`;
                });
                console.log('✅ GPS para CUSTODIO cargados:', disponibles.length);
            }

            // ✅ OPCIÓN 2: Selectores de CUSTODIOS
            document.querySelectorAll('select[name="custodioId"]').forEach(select => {
                select.innerHTML = '<option value="">Seleccione custodio</option>';
                if (Array.isArray(custodios)) {
                    custodios.filter(c => c.estado !== 'inactivo').forEach(c => {
                        select.innerHTML += `<option value="${c.id}">${c.nombre} - ${c.cargo}</option>`;
                    });
                }
            });

            // ✅ OPCIÓN 3: NUEVA - DROPDOWN PARA RETORNO (INCLUYE CUSTODIOS Y CLIENTES)
            const selectRetornar = document.querySelector('#form-retornar select[name="asignacionId"]');
            if (selectRetornar && Array.isArray(asignaciones)) {
                const activos = asignaciones.filter(a => a.estado === 'asignado');
                selectRetornar.innerHTML = '<option value="">Seleccione GPS a retornar</option>';

                console.log('📡 Procesando asignaciones activas:', activos.length);

                activos.forEach(a => {
                    const tipo = (a.tipo_asignacion || 'custodio').toLowerCase().trim();
                    let etiqueta = '';
                    let datos = {
                        imei: a.imei,
                        tipo: tipo,
                        id: a.id
                    };

                    console.log(`  - ${a.imei}: tipo="${tipo}"`);

                    const diasOpt = diasDesdeAsignacion(a.fecha_asignacion);
                    const iconoUrgencia = diasOpt >= 3 ? '🔴' : (diasOpt >= 2 ? '🟠' : '🟢');

                    if (tipo === 'cliente') {
                        // Para CLIENTES: mostrar cliente y piloto
                        etiqueta = `${iconoUrgencia} ${a.imei} (${a.cliente}`;
                        if (a.piloto) {
                            etiqueta += ` - Piloto: ${a.piloto}`;
                        }
                        etiqueta += `) · ${diasOpt} día${diasOpt !== 1 ? 's' : ''}`;
                        datos.cliente = a.cliente;
                        datos.piloto = a.piloto;
                    } else {
                        // Para CUSTODIOS: mostrar custodio
                        etiqueta = `${iconoUrgencia} ${a.imei} (${a.custodio_nombre}) · ${diasOpt} día${diasOpt !== 1 ? 's' : ''}`;
                        datos.custodio = a.custodio_nombre;
                    }

                    selectRetornar.innerHTML += `<option value="${a.id}" data-tipo="${tipo}">${etiqueta}</option>`;
                });

                console.log('✅ Dropdown de retorno actualizado con:', activos.length, 'GPS');

                // Actualizar el mini resumen de pendientes de retorno
                const elTotal = document.getElementById('retorno-count-total');
                const elAtencion = document.getElementById('retorno-count-atencion');
                const elCritico = document.getElementById('retorno-count-critico');
                if (elTotal && elAtencion && elCritico) {
                    let atencion = 0, critico = 0;
                    activos.forEach(a => {
                        const d = diasDesdeAsignacion(a.fecha_asignacion);
                        if (d >= 3) critico++;
                        else if (d >= 2) atencion++;
                    });
                    elTotal.textContent = activos.length;
                    elAtencion.textContent = atencion;
                    elCritico.textContent = critico;
                }
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

        // Calcula los días transcurridos desde una fecha de asignación.
        // Nunca devuelve negativos: si la fecha de asignación quedó registrada
        // en el futuro (error de captura), se muestra 0 en lugar de "-1 días".
        function diasDesdeAsignacion(fechaAsignacion) {
            const dias = Math.floor((Date.now() - new Date(fechaAsignacion)) / 86400000);
            return Math.max(0, dias);
        }

        // Calcula el nivel de urgencia de una asignación según sus días activa.
        // 0-1 día: normal · 2 días: atención · 3+ días: crítico
        function calcularUrgenciaRetorno(dias) {
            if (dias >= 3) {
                return { nivel: 'critico', clase: 'badge-danger', texto: `🔴 Crítico · ${dias} día${dias !== 1 ? 's' : ''}` };
            } else if (dias >= 2) {
                return { nivel: 'atencion', clase: 'badge-warning', texto: `🟠 Atención · ${dias} día${dias !== 1 ? 's' : ''}` };
            }
            return { nivel: 'normal', clase: 'badge-success', texto: `🟢 Reciente · ${dias} día${dias !== 1 ? 's' : ''}` };
        }

        function aplicarBadgeUrgencia(elementoId, dias) {
            const badge = document.getElementById(elementoId);
            if (!badge) return;
            const { clase, texto } = calcularUrgenciaRetorno(dias);
            badge.className = 'badge ' + clase;
            badge.style.display = 'inline-flex';
            badge.textContent = texto;
        }

        function mostrarInfoRetorno(id) {
            console.log('🔍 Mostrando info retorno para ID:', id);

            const infoRetornoCustodio = document.getElementById('info-retorno-custodio');
            const infoRetornoCliente = document.getElementById('info-retorno-cliente');

            // Si no hay ID, ocultar ambos paneles
            if (!id) {
                console.log('❌ Sin ID, ocultando paneles');
                if (infoRetornoCustodio) infoRetornoCustodio.classList.add('hidden');
                if (infoRetornoCliente) infoRetornoCliente.classList.add('hidden');
                return;
            }

            // Buscar la asignación
            const asignacion = Array.isArray(asignaciones) ?
                asignaciones.find(x => parseInt(x.id) === parseInt(id)) :
                null;

            if (!asignacion) {
                console.error('❌ Asignación no encontrada:', id);
                if (infoRetornoCustodio) infoRetornoCustodio.classList.add('hidden');
                if (infoRetornoCliente) infoRetornoCliente.classList.add('hidden');
                return;
            }

            const dias = diasDesdeAsignacion(asignacion.fecha_asignacion);
            const tipoAsignacion = (asignacion.tipo_asignacion || 'custodio').toLowerCase().trim();

            console.log('✅ Asignación encontrada:', {
                tipo: tipoAsignacion,
                imei: asignacion.imei,
                cliente: asignacion.cliente,
                dias: dias
            });

            // ================== MOSTRAR PANEL DE CLIENTE ==================
            if (tipoAsignacion === 'cliente') {
                console.log('📌 Mostrando panel de CLIENTE');

                // OCULTAR custodio, MOSTRAR cliente
                if (infoRetornoCustodio) infoRetornoCustodio.classList.add('hidden');
                if (infoRetornoCliente) infoRetornoCliente.classList.remove('hidden');

                // Rellenar datos de cliente
                const campos = {
                    'retorno-imei-cliente': asignacion.imei,
                    'retorno-cliente-cliente': asignacion.cliente,
                    'retorno-piloto-cliente': asignacion.piloto || '-',
                    'retorno-placa-cliente': asignacion.placa || '-',
                    'retorno-origen-cliente': asignacion.origen || '-',
                    'retorno-destino-cliente': asignacion.destino || '-',
                    'retorno-contenedor-cliente': asignacion.contenedor || '-',
                    'retorno-dias-cliente': `${dias} día${dias !== 1 ? 's' : ''}`
                };

                Object.entries(campos).forEach(([id, valor]) => {
                    const elemento = document.getElementById(id);
                    if (elemento) {
                        elemento.textContent = valor;
                        console.log(`  ✓ ${id} = ${valor}`);
                    }
                });

                aplicarBadgeUrgencia('retorno-urgencia-cliente', dias);

            }
            // ================== MOSTRAR PANEL DE CUSTODIO ==================
            else {
                console.log('📌 Mostrando panel de CUSTODIO');

                // OCULTAR cliente, MOSTRAR custodio
                if (infoRetornoCliente) infoRetornoCliente.classList.add('hidden');
                if (infoRetornoCustodio) infoRetornoCustodio.classList.remove('hidden');

                // Rellenar datos de custodio
                const campos = {
                    'retorno-imei-custodio': asignacion.imei,
                    'retorno-cliente-custodio': asignacion.cliente || '-',
                    'retorno-custodio-custodio': asignacion.custodio_nombre || '-',
                    'retorno-dias-custodio': `${dias} día${dias !== 1 ? 's' : ''}`
                };

                Object.entries(campos).forEach(([id, valor]) => {
                    const elemento = document.getElementById(id);
                    if (elemento) {
                        elemento.textContent = valor;
                        console.log(`  ✓ ${id} = ${valor}`);
                    }
                });

                aplicarBadgeUrgencia('retorno-urgencia-custodio', dias);
            }

            console.log('✅ Panel mostrado correctamente');
        }

        function consultarGPS() {
            const inputEl = document.getElementById('buscar-imei-consulta');
            const imei = inputEl.value.trim();
            const estadoInicial = document.getElementById('consulta-estado-inicial');
            const resultado = document.getElementById('resultado-consulta');

            if (!imei) {
                inputEl.focus();
                return;
            }

            if (estadoInicial) estadoInicial.classList.add('hidden');

            const gps = Array.isArray(gpsDispositivos) ? gpsDispositivos.find(g => g.imei.toLowerCase().includes(imei.toLowerCase())) : null;

            if (!gps) {
                resultado.innerHTML = `
                    <div class="card" style="border-left: 4px solid var(--danger);">
                        <div class="empty-state">
                            <i class="fas fa-satellite"></i>
                            <h3>GPS no encontrado</h3>
                            <p>No se encontró ningún dispositivo con el IMEI "<strong>${imei}</strong>"</p>
                        </div>
                    </div>
                `;
                resultado.classList.remove('hidden');
                return;
            }

            const disponible = gps.estado === 'disponible';
            const asignacionActual = Array.isArray(asignaciones) ? asignaciones.find(a => parseInt(a.gps_id) === parseInt(gps.id) && a.estado === 'asignado') : null;

            let html = `
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <div style="width: 56px; height: 56px; border-radius: 14px; display: grid; place-items: center; font-size: 1.5rem; background: ${disponible ? 'rgba(16,185,129,.12)' : 'rgba(245,158,11,.14)'}; color: ${disponible ? 'var(--success)' : 'var(--warning)'};">
                            <i class="fas fa-satellite-dish"></i>
                        </div>
                        <div style="flex: 1; min-width: 180px;">
                            <p style="font-family: monospace; font-size: 1.35rem; font-weight: 700; word-break: break-all; margin-bottom: 0.25rem;">${gps.imei || 'N/A'}</p>
                            <span class="badge badge-${disponible ? 'success' : 'warning'}">${disponible ? 'Disponible' : 'Asignado'}</span>
                        </div>
                        <button class="btn btn-secondary" onclick="navigator.clipboard && navigator.clipboard.writeText('${gps.imei}')" style="padding: 0.5rem 1rem; font-size: 0.8rem;" title="Copiar IMEI">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.25rem;">
                        <div>
                            <p style="font-size: 0.72rem; margin-bottom: 0.4rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: .03em;">Marca</p>
                            <p style="font-size: 1.05rem; font-weight: 700;">${gps.marca || 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.72rem; margin-bottom: 0.4rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: .03em;">Modelo</p>
                            <p style="font-size: 1.05rem; font-weight: 700;">${gps.modelo || 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.72rem; margin-bottom: 0.4rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: .03em;">Ubicación</p>
                            <p style="font-size: 1.05rem; font-weight: 700;">${gps.ubicacion || 'Instalaciones'}</p>
                        </div>
                        <div>
                            <p style="font-size: 0.72rem; margin-bottom: 0.4rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: .03em;">Descripción</p>
                            <p style="font-size: 0.95rem; font-weight: 600;">${gps.descripcion || 'Sin descripción'}</p>
                        </div>
                    </div>
                </div>
            `;

            if (asignacionActual) {
                const custodio = Array.isArray(custodios) ? custodios.find(c => parseInt(c.id) === parseInt(asignacionActual.custodio_id)) : null;
                const dias = diasDesdeAsignacion(asignacionActual.fecha_asignacion);
                const fechaAsignacion = new Date(asignacionActual.fecha_asignacion).toLocaleString('es-HN');

                html += `
                    <div class="card" style="margin-top: 1.5rem; border-left: 5px solid var(--warning);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: rgba(245,158,11,.14); color: var(--warning); font-size: 1.1rem;">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;">GPS actualmente asignado</h3>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">👤 Custodio</div>
                                <div class="consulta-detalle-value">${custodio ? custodio.nombre : 'N/A'}</div>
                            </div>
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">📞 Teléfono</div>
                                <div class="consulta-detalle-value" style="font-family: monospace;">${custodio ? custodio.telefono : 'N/A'}</div>
                            </div>
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">💼 Cargo</div>
                                <div class="consulta-detalle-value">${custodio ? custodio.cargo : 'N/A'}</div>
                            </div>
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">🏢 Cliente</div>
                                <div class="consulta-detalle-value">${asignacionActual.cliente || 'N/A'}</div>
                            </div>
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">📍 Origen</div>
                                <div class="consulta-detalle-value">${asignacionActual.origen || 'N/A'}</div>
                            </div>
                            <div class="consulta-detalle-item">
                                <div class="consulta-detalle-label">🎯 Destino</div>
                                <div class="consulta-detalle-value">${asignacionActual.destino || 'N/A'}</div>
                            </div>
                        </div>

                        <div style="border-top: 1px solid var(--border); padding-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between;">
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.35rem;">Fecha de asignación</p>
                                <p style="font-size: 0.95rem; font-weight: 600;">${fechaAsignacion}</p>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.35rem;">Días asignado</p>
                                <p style="font-size: 0.95rem; font-weight: 600;">${dias} día${dias !== 1 ? 's' : ''}</p>
                            </div>
                            <div style="flex: 1; min-width: 180px;">
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.35rem;">Observaciones</p>
                                <p style="font-size: 0.9rem;">${asignacionActual.observaciones || 'Sin observaciones'}</p>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="card" style="margin-top: 1.5rem; border-left: 5px solid var(--success);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; background: rgba(16,185,129,.14); color: var(--success); font-size: 1.3rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--success); margin: 0;">GPS disponible</h3>
                                <p style="color: var(--text-secondary); margin-top: 0.2rem;">Este dispositivo está libre para asignarse a un custodio</p>
                            </div>
                        </div>
                    </div>
                `;
            }

            resultado.innerHTML = html;
            resultado.classList.remove('hidden');
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
                const dias = diasDesdeAsignacion(asignacion.fecha_asignacion);
                const tipo = asignacion.tipo_asignacion || 'custodio';

                let camposInfo = '';

                if (tipo === 'cliente') {
                    camposInfo = `
                        <div class="alert-item">
                            <div class="alert-item-label">🏢 Cliente</div>
                            <div class="alert-item-value">${asignacion.cliente || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">👤 Piloto/Conductor</div>
                            <div class="alert-item-value">${asignacion.piloto || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">📞 Teléfono del Piloto</div>
                            <div class="alert-item-value">${asignacion.telefono || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">🚗 Placa del Vehículo</div>
                            <div class="alert-item-value">${asignacion.placa || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">📦 Contenedor/Carga</div>
                            <div class="alert-item-value">${asignacion.contenedor || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">⏱️ Días Asignado</div>
                            <div class="alert-item-value"><span class="alert-days">${dias}</span> día${dias !== 1 ? 's' : ''}</div>
                        </div>`;
                } else {
                    camposInfo = `
                        <div class="alert-item">
                            <div class="alert-item-label">👤 Custodio</div>
                            <div class="alert-item-value">${asignacion.custodio_nombre || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">📞 Teléfono</div>
                            <div class="alert-item-value">${asignacion.custodio_telefono || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">🏢 Cliente</div>
                            <div class="alert-item-value">${asignacion.cliente || '—'}</div>
                        </div>
                        <div class="alert-item">
                            <div class="alert-item-label">⏱️ Días Asignado</div>
                            <div class="alert-item-value"><span class="alert-days">${dias}</span> día${dias !== 1 ? 's' : ''}</div>
                        </div>`;
                }

                const contactoTelefono = tipo === 'cliente' ?
                    (asignacion.telefono || '') :
                    (asignacion.custodio_telefono || '');

                const badgeTipo = tipo === 'cliente' ?
                    `<span style="background:#fde8e9; color:#a90f15; font-size:0.75rem; font-weight:700; padding:0.2rem 0.6rem; border-radius:20px; margin-left:0.5rem;"><i class="fas fa-building"></i> Cliente</span>` :
                    `<span style="background:#f9e7e8; color:#8f0d13; font-size:0.75rem; font-weight:700; padding:0.2rem 0.6rem; border-radius:20px; margin-left:0.5rem;"><i class="fas fa-user-shield"></i> Custodio</span>`;

                html += `
                    <div class="alert-card">
                        <div class="alert-card-header">
                            <div class="alert-icon">🚨</div>
                            <div style="flex: 1;">
                                <div class="alert-title">
                                    GPS ${asignacion.imei} - Requiere Seguimiento
                                    ${badgeTipo}
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert-content">
                            ${camposInfo}
                        </div>

                        <div class="alert-actions">
                            <button type="button" class="btn btn-primary" onclick="irARetornar(${asignacion.id})" style="flex: 1;">
                                <i class="fas fa-undo"></i> Registrar Retorno
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="contactarCustodio('${contactoTelefono}')" style="flex: 1;">
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
            if (!esAdmin) {
                alert('⛔ Solo un administrador puede eliminar dispositivos GPS.');
                return;
            }
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
            if (!esAdmin) {
                alert('⛔ Solo un administrador puede eliminar custodios.');
                return;
            }
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
            const zonaSel = document.getElementById('filtro-zona-custodio') ? document.getElementById('filtro-zona-custodio').value : '';
            document.querySelectorAll('#tabla-custodios tbody tr').forEach(tr => {
                const coincideTexto = tr.textContent.toLowerCase().includes(term);
                const zonaFila = tr.dataset.zona || '';
                let coincideZona = true;
                if (zonaSel === '__sin_zona__') coincideZona = !zonaFila;
                else if (zonaSel) coincideZona = zonaFila === zonaSel;
                tr.style.display = (coincideTexto && coincideZona) ? '' : 'none';
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
            console.log('🚀 Iniciando sistema de notificaciones...');
            console.log('CONFIG_ALERTAS:', CONFIG_ALERTAS);

            // Verificar INMEDIATAMENTE al iniciar
            verificarNotificacionesRecuperacion();

            // Y luego cada 5 minutos
            intervaloNotificaciones = setInterval(() => {
                console.log('⏱️ Verificación periódica de notificaciones...');
                verificarNotificacionesRecuperacion();
            }, 5 * 60 * 1000); // 5 minutos

            console.log('✅ Sistema de notificaciones activo');
        }

        function detenerVerificacionNotificaciones() {
            if (intervaloNotificaciones) {
                clearInterval(intervaloNotificaciones);
                intervaloNotificaciones = null;
            }
        }

        function verificarNotificacionesRecuperacion() {
            console.log('🔔 Verificando notificaciones...');
            console.log('asignaciones disponibles:', asignaciones);
            console.log('Es array?:', Array.isArray(asignaciones));

            if (!Array.isArray(asignaciones)) {
                console.warn('⚠️ asignaciones no es un array', asignaciones);
                return;
            }

            const asignacionesActivas = asignaciones.filter(a => a.estado === 'asignado');
            console.log('Asignaciones activas encontradas:', asignacionesActivas.length);

            if (asignacionesActivas.length === 0) {
                console.log('✓ Sin asignaciones activas, no hay notificaciones que enviar');
                return;
            }

            asignacionesActivas.forEach(asignacion => {
                const horasAsignado = calcularHorasAsignado(asignacion.fecha_asignacion);
                const notificacionKey = `notif_${asignacion.id}`;

                console.log(`\n📍 GPS ${asignacion.imei}:`);
                console.log(`   Horas asignado: ${horasAsignado.toFixed(2)}`);
                console.log(`   Ya notificado en 24h: ${!!notificacionesVistas[`${notificacionKey}_24h`]}`);
                console.log(`   Ya notificado en 48h: ${!!notificacionesVistas[`${notificacionKey}_48h`]}`);
                console.log(`   Ya notificado en 72h: ${!!notificacionesVistas[`${notificacionKey}_72h`]}`);

                // ✅ AHORA USA LA VARIABLE GLOBAL EN MEMORIA, NO localStorage
                if (horasAsignado >= CONFIG_ALERTAS.PRIMERA_ALERTA &&
                    horasAsignado < CONFIG_ALERTAS.SEGUNDA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_24h`]) {
                    console.log('   ✉️ Enviando notificación 24h...');
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'warning', 24);
                    notificacionesVistas[`${notificacionKey}_24h`] = true;
                }

                if (horasAsignado >= CONFIG_ALERTAS.SEGUNDA_ALERTA &&
                    horasAsignado < CONFIG_ALERTAS.TERCERA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_48h`]) {
                    console.log('   ✉️ Enviando notificación 48h...');
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'danger', 48);
                    notificacionesVistas[`${notificacionKey}_48h`] = true;
                }

                if (horasAsignado >= CONFIG_ALERTAS.TERCERA_ALERTA &&
                    !notificacionesVistas[`${notificacionKey}_72h`]) {
                    console.log('   ✉️ Enviando notificación 72h...');
                    enviarNotificacionRecuperacion(asignacion, horasAsignado, 'critical', 72);
                    notificacionesVistas[`${notificacionKey}_72h`] = true;
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
            console.log(`📨 Enviando notificación tipo "${tipo}" para GPS ${asignacion.imei}`);

            const notificationList = document.getElementById('notification-list');

            if (!notificationList) {
                console.error('❌ ERROR: No se encontró elemento notification-list');
                return;
            }

            const notifId = 'notif-' + asignacion.id + '-' + horas + '-' + Date.now();

            let iconoTipo = '<i class="fas fa-clock"></i>';
            let colorTipo = '#f59e0b';
            let bgTipo = 'rgba(245,158,11,.12)';
            let urgencia = 'Normal';
            let titulo = 'Recordatorio: recuperación próxima';

            if (tipo === 'danger') {
                iconoTipo = '<i class="fas fa-exclamation-triangle"></i>';
                colorTipo = '#ef4444';
                bgTipo = 'rgba(239,68,68,.12)';
                urgencia = 'Urgente';
                titulo = 'Alerta: recuperación próxima';
            } else if (tipo === 'critical') {
                iconoTipo = '<i class="fas fa-exclamation-circle"></i>';
                colorTipo = '#dc2626';
                bgTipo = 'rgba(220,38,38,.14)';
                urgencia = 'CRÍTICA';
                titulo = 'Crítica: recuperación vencida';
            }

            const vacio = notificationList.querySelector('.empty-state');
            if (vacio) vacio.remove();

            const ahoraTs = Date.now();
            const notifItem = document.createElement('div');
            notifItem.className = 'notification-item no-leida notif-cat-alerta';
            notifItem.id = notifId;
            notifItem.dataset.ts = ahoraTs;
            notifItem.style.setProperty('--notif-color', colorTipo);
            notifItem.style.setProperty('--notif-bg', bgTipo);
            notifItem.innerHTML = `
                <div class="notif-icon-avatar">${iconoTipo}</div>
                <div class="notification-item-content">
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
                        <button class="btn" onclick="contactarCustodio('${asignacion.custodio_telefono}')" style="padding: 0.5rem 1rem; font-size: 0.85rem; background-color: #7b7c84; color: white; border-radius: 8px; border: none; cursor: pointer;">
                            <i class="fas fa-phone"></i> Contactar
                        </button>
                        <button class="btn btn-secondary" onclick="confirmarNotificacion('${notifId}')" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                            <i class="fas fa-check"></i> Listo
                        </button>
                    </div>
                </div>
            `;

            notificationList.insertBefore(notifItem, notificationList.firstChild);
            console.log('✅ Elemento HTML de notificación agregado:', notifId);

            if (notifFiltroActivo !== 'todas') {
                const mostrar = notifFiltroActivo === 'no-leidas' || notifFiltroActivo === 'alertas';
                notifItem.classList.toggle('filtro-oculto', !mostrar);
            }

            actualizarBadgeNotificaciones();
            actualizarSubtituloNotificaciones();
            animarCampanaNotificaciones();

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

        // ==================== FUNCIONES PARA ASIGNACIÓN POR CLIENTE ====================

        function cambiarTipoAsignacion(tipo) {
            const custodioContainer = document.getElementById('form-custodio-container');
            const clienteContainer = document.getElementById('form-cliente-container');
            const tabCustodio = document.getElementById('tab-custodio');
            const tabCliente = document.getElementById('tab-cliente');

            if (tipo === 'custodio') {
                custodioContainer.classList.remove('hidden');
                clienteContainer.classList.add('hidden');
                tabCustodio.classList.add('active');
                tabCliente.classList.remove('active');
            } else {
                custodioContainer.classList.add('hidden');
                clienteContainer.classList.remove('hidden');
                tabCustodio.classList.remove('active');
                tabCliente.classList.add('active');
            }
        }

        function actualizarInfoGPSCliente(gpsId) {
            if (!gpsId) {
                document.getElementById('info-gps-cliente').textContent = '';
                return;
            }
            const gps = gpsDispositivos.find(g => parseInt(g.id) === parseInt(gpsId));
            if (gps) {
                document.getElementById('info-gps-cliente').textContent = `${gps.marca} ${gps.modelo} • IMEI: ${gps.imei}`;
            }
        }

        // Solo permite dígitos en el teléfono del piloto y aplica el formato 9999-9999
        function formatearTelefonoCA(input) {
            let digitos = input.value.replace(/\D/g, '').slice(0, 8);
            if (digitos.length > 4) {
                input.value = digitos.slice(0, 4) + '-' + digitos.slice(4);
            } else {
                input.value = digitos;
            }
        }

        async function asignarGPSCliente(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            const gpsId = formData.get('gpsIdCliente');
            const clienteNombre = formData.get('clienteNombre');

            // Combinar prefijo de país (Centroamérica) + número del piloto en un solo valor
            const prefijoPiloto = (formData.get('prefijoPiloto') || '').trim();
            const numeroPiloto = (formData.get('telefonoPiloto') || '').replace(/\D/g, '');

            if (!numeroPiloto || numeroPiloto.length < 7) {
                alert('❌ Ingresa un número de teléfono del piloto válido (solo números).');
                return;
            }

            formData.set('telefonoPiloto', `${prefijoPiloto} ${numeroPiloto.slice(0, 4)}-${numeroPiloto.slice(4)}`.trim());

            // Validar que se hayan seleccionado GPS y cliente
            if (!gpsId || !clienteNombre) {
                alert('❌ Por favor completa los campos obligatorios marcados con *');
                return;
            }

            // Obtener el GPS seleccionado
            const gps = gpsDispositivos.find(g => parseInt(g.id) === parseInt(gpsId));
            if (!gps) {
                alert('❌ GPS no encontrado');
                return;
            }

            // Validar que el GPS no tenga una asignación activa (de custodio)
            const asignacionActiva = asignaciones.find(a =>
                parseInt(a.gps_id) === parseInt(gpsId) && a.estado === 'asignado'
            );

            if (asignacionActiva) {
                alert('❌ Este GPS ya tiene una asignación activa.\n\nCustodio: ' + asignacionActiva.custodio_nombre +
                    '\nAsignado desde: ' + new Date(asignacionActiva.fecha_asignacion).toLocaleString('es-HN'));
                return;
            }

            // VALIDACIÓN DE FECHA (NO MÁS DE 7 DÍAS ANTERIOR)
            const hoyActual = new Date();
            const hoySolo = new Date(hoyActual.getFullYear(), hoyActual.getMonth(), hoyActual.getDate());

            const fechaMinima = new Date(hoySolo);
            fechaMinima.setDate(fechaMinima.getDate() - 7);

            const fechaAsignacionInput = event.target.querySelector('input[name="fechaAsignacionCliente"]');
            let fechaSeleccionada = new Date(hoySolo);

            if (fechaAsignacionInput && fechaAsignacionInput.value) {
                fechaSeleccionada = new Date(fechaAsignacionInput.value);
            }

            const fechaSeleccionadaSolo = new Date(fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth(), fechaSeleccionada.getDate());

            console.log('=== VALIDACIÓN DE FECHA (CLIENTE) ===');
            console.log('Fecha hoy: ' + hoySolo.toLocaleDateString('es-HN'));
            console.log('Fecha mínima (7 días atrás): ' + fechaMinima.toLocaleDateString('es-HN'));
            console.log('Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN'));

            if (fechaSeleccionadaSolo.getTime() < fechaMinima.getTime()) {
                const diferenciaDias = Math.floor((hoySolo.getTime() - fechaSeleccionadaSolo.getTime()) / (1000 * 60 * 60 * 24));
                alert('❌ NO PUEDES ASIGNAR UN GPS CON UNA FECHA ANTERIOR A 7 DÍAS.\n\n' +
                    'Fecha mínima permitida: ' + fechaMinima.toLocaleDateString('es-HN') + '\n' +
                    'Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN'));
                return;
            }

            if (fechaSeleccionadaSolo.getTime() > hoySolo.getTime()) {
                alert('❌ NO PUEDES ASIGNAR UN GPS CON UNA FECHA FUTURA.\n\n' +
                    'Fecha seleccionada: ' + fechaSeleccionadaSolo.toLocaleDateString('es-HN') + '\n' +
                    'Fecha de hoy: ' + hoySolo.toLocaleDateString('es-HN'));
                return;
            }

            try {
                const response = await fetch('api/assign_gps_cliente.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    agregarNotificacion('GPS Asignado a Cliente', `${gps.imei} asignado a ${clienteNombre}`);
                    event.target.reset();
                    await cargarDatosDelServidor();
                    alert('✅ GPS asignado correctamente al cliente');
                } else {
                    alert('❌ ' + (data.message || 'Error al asignar GPS'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al asignar GPS: ' + error.message);
            }
        }

        // Cargar selectores para formulario de cliente
        function cargarSelectoresCliente() {
            const selectGPS = document.querySelector('#form-asignar-cliente select[name="gpsIdCliente"]');
            if (selectGPS && Array.isArray(gpsDispositivos)) {
                const disponibles = gpsDispositivos.filter(g => g.estado === 'disponible');
                selectGPS.innerHTML = '<option value="">Seleccione un GPS</option>';
                disponibles.forEach(g => {
                    selectGPS.innerHTML += `<option value="${g.id}">${g.imei} - ${g.marca} ${g.modelo}</option>`;
                });
            }
        }

        // Actualizar selectores cuando se cargan datos
        const originalActualizarTodo = actualizarTodo;
        actualizarTodo = function() {
            originalActualizarTodo();
            cargarSelectoresCliente();
        };


        function limpiarTodasNotificaciones() {
            const notificationList = document.getElementById('notification-list');

            // Limpiar todas las notificaciones del panel
            notificationList.innerHTML = HTML_NOTIFICACIONES_VACIO;

            // Resetear badge y subtítulo
            actualizarBadgeNotificaciones();
            actualizarSubtituloNotificaciones();

            // Volver a la pestaña "Todas" ya que no queda nada que filtrar
            filtrarNotificaciones('todas');

            // Limpiar registro de notificaciones vistas para que puedan re-aparecer
            notificacionesVistas = {};
        }


        // Variables de ordenamiento y paginación del historial
        let historialOrdenCol = 'fecha';
        let historialOrdenDir = 'desc'; // desc = más recientes primero por defecto
        let historialPaginaActual = 1;
        const HISTORIAL_POR_PAGINA = 25;

        function ordenarHistorial(columna) {
            if (historialOrdenCol === columna) {
                historialOrdenDir = historialOrdenDir === 'asc' ? 'desc' : 'asc';
            } else {
                historialOrdenCol = columna;
                historialOrdenDir = columna === 'fecha' ? 'desc' : 'asc';
            }

            // Actualizar íconos de todas las columnas
            const cols = ['imei', 'custodio', 'cliente', 'origen', 'destino', 'fecha', 'estado'];
            cols.forEach(col => {
                const el = document.getElementById('sort-' + col);
                if (!el) return;
                if (col === historialOrdenCol) {
                    el.textContent = historialOrdenDir === 'asc' ? '↑' : '↓';
                    el.style.color = 'var(--primary)';
                } else {
                    el.textContent = '↕';
                    el.style.color = 'var(--text-secondary)';
                }
            });

            renderHistorialOrdenado();
        }

        function limpiarFiltrosHistorial() {
            const buscar = document.getElementById('buscar-historial');
            const desde = document.getElementById('filtro-historial-desde');
            const hasta = document.getElementById('filtro-historial-hasta');
            const zona = document.getElementById('filtro-historial-zona');
            const estado = document.getElementById('filtro-historial-estado');
            if (buscar) buscar.value = '';
            if (desde) desde.value = '';
            if (hasta) hasta.value = '';
            if (zona) zona.value = '';
            if (estado) estado.value = '';
            historialPaginaActual = 1;
            renderHistorialOrdenado();
        }

        // Obtiene la zona del custodio de una asignación consultando el arreglo `custodios`
        function zonaDeAsignacion(asignacion) {
            if (!Array.isArray(custodios) || !asignacion.custodio_id) return '';
            const c = custodios.find(x => parseInt(x.id) === parseInt(asignacion.custodio_id));
            return (c && c.zona) ? c.zona : '';
        }

        function renderHistorialOrdenado() {
            const tbody = document.querySelector('#tabla-historial tbody');
            const contador = document.getElementById('contador-historial');
            const paginacion = document.getElementById('paginacion-historial');
            if (!Array.isArray(asignaciones)) return;

            const termino = (document.getElementById('buscar-historial')?.value || '').trim().toLowerCase();
            const desde = document.getElementById('filtro-historial-desde')?.value || '';
            const hasta = document.getElementById('filtro-historial-hasta')?.value || '';
            const zonaFiltro = document.getElementById('filtro-historial-zona')?.value || '';
            const estadoFiltro = document.getElementById('filtro-historial-estado')?.value || '';

            let base = asignaciones.filter(a => {
                if (termino) {
                    const campos = [a.imei, a.custodio_nombre, a.cliente, a.origen, a.destino, a.estado, a.piloto, a.placa];
                    if (!campos.some(c => (c || '').toString().toLowerCase().includes(termino))) return false;
                }
                if (desde || hasta) {
                    const fechaAsig = a.fecha_asignacion ? a.fecha_asignacion.slice(0, 10) : '';
                    if (desde && fechaAsig < desde) return false;
                    if (hasta && fechaAsig > hasta) return false;
                }
                if (estadoFiltro && a.estado !== estadoFiltro) return false;
                if (zonaFiltro) {
                    const zona = zonaDeAsignacion(a);
                    if (zonaFiltro === '__sin_zona__') { if (zona) return false; }
                    else if (zona !== zonaFiltro) return false;
                }
                return true;
            });

            const totalResultados = base.length;

            if (totalResultados === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>Sin resultados</h3>
                        <p>Ningún registro coincide con los filtros aplicados</p>
                    </div>
                </td></tr>`;
                if (contador) contador.textContent = `0 de ${asignaciones.length} registros`;
                if (paginacion) paginacion.innerHTML = '';
                return;
            }

            const datos = [...base].sort((a, b) => {
                let valA, valB;

                switch (historialOrdenCol) {
                    case 'imei':
                        valA = a.imei || '';
                        valB = b.imei || '';
                        break;
                    case 'custodio':
                        valA = a.custodio_nombre || '';
                        valB = b.custodio_nombre || '';
                        break;
                    case 'cliente':
                        valA = a.cliente || '';
                        valB = b.cliente || '';
                        break;
                    case 'origen':
                        valA = a.origen || '';
                        valB = b.origen || '';
                        break;
                    case 'destino':
                        valA = a.destino || '';
                        valB = b.destino || '';
                        break;
                    case 'estado':
                        valA = a.estado || '';
                        valB = b.estado || '';
                        break;
                    case 'fecha':
                        valA = new Date(a.fecha_asignacion).getTime();
                        valB = new Date(b.fecha_asignacion).getTime();
                        return historialOrdenDir === 'asc' ? valA - valB : valB - valA;
                }

                const cmp = valA.localeCompare(valB, 'es', {
                    sensitivity: 'base'
                });
                return historialOrdenDir === 'asc' ? cmp : -cmp;
            });

            // Paginación
            const totalPaginas = Math.max(1, Math.ceil(datos.length / HISTORIAL_POR_PAGINA));
            if (historialPaginaActual > totalPaginas) historialPaginaActual = totalPaginas;
            if (historialPaginaActual < 1) historialPaginaActual = 1;
            const inicio = (historialPaginaActual - 1) * HISTORIAL_POR_PAGINA;
            const paginaDatos = datos.slice(inicio, inicio + HISTORIAL_POR_PAGINA);

            if (contador) {
                contador.textContent = totalResultados === asignaciones.length
                    ? `${totalResultados} registro${totalResultados === 1 ? '' : 's'}`
                    : `Mostrando ${totalResultados} de ${asignaciones.length} registros`;
            }

            let html = '';
            paginaDatos.forEach(asignacion => {
                const estadoClass = asignacion.estado === 'asignado' ? 'badge-warning' : 'badge-success';
                const estadoTexto = asignacion.estado ?
                    asignacion.estado.charAt(0).toUpperCase() + asignacion.estado.slice(1) :
                    'N/A';
                const tipoAsignacion = asignacion.tipo_asignacion || 'custodio';
                const custodioInfo = tipoAsignacion === 'cliente' ?
                    `${asignacion.cliente} (Cliente)` :
                    asignacion.custodio_nombre;
                const zona = zonaDeAsignacion(asignacion);
                const zonaHtml = zona
                    ? badgeZona(zona)
                    : `<span style="color:var(--text-secondary);">—</span>`;

                html += `<tr>
            <td style="font-family: monospace;">${asignacion.imei || 'N/A'}</td>
            <td>${custodioInfo || 'N/A'}</td>
            <td>${zonaHtml}</td>
            <td>${asignacion.cliente || '-'}</td>
            <td>${asignacion.origen || '-'}</td>
            <td>${asignacion.destino || '-'}</td>
            <td>${new Date(asignacion.fecha_asignacion).toLocaleString('es-HN')}</td>
            <td><span class="badge ${estadoClass}">${estadoTexto}</span></td>
        </tr>`;
            });
            tbody.innerHTML = html;

            // Controles de paginación
            if (paginacion) {
                if (totalPaginas <= 1) {
                    paginacion.innerHTML = '';
                } else {
                    paginacion.innerHTML = `
                        <span style="font-size:0.82rem;color:var(--text-secondary);">Página ${historialPaginaActual} de ${totalPaginas}</span>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm" ${historialPaginaActual <= 1 ? 'disabled' : ''} onclick="cambiarPaginaHistorial(-1)"><i class="fas fa-chevron-left"></i> Anterior</button>
                            <button class="btn btn-secondary btn-sm" ${historialPaginaActual >= totalPaginas ? 'disabled' : ''} onclick="cambiarPaginaHistorial(1)">Siguiente <i class="fas fa-chevron-right"></i></button>
                        </div>`;
                }
            }
        }

        function cambiarPaginaHistorial(delta) {
            historialPaginaActual += delta;
            renderHistorialOrdenado();
        }
    </script>

    <script>
        // ============================================================
        // FORZA UI · Combobox con búsqueda para selects largos
        // (GPS disponible / Custodio responsable). No reemplaza la
        // lógica original: sigue usando los <select> reales por debajo,
        // solo añade una capa visual de búsqueda encima.
        // ============================================================
        (function () {
            'use strict';

            function initSearchableSelect(wrap) {
                const input = wrap.querySelector('.searchable-select-search');
                const select = wrap.querySelector('.searchable-select-native');
                const list = wrap.querySelector('.searchable-select-list');
                if (!input || !select || !list) return;

                let activeIndex = -1;
                let currentItems = [];

                function getOptions() {
                    return Array.from(select.options).filter(opt => opt.value !== '');
                }

                function syncInputFromSelect() {
                    const selected = select.options[select.selectedIndex];
                    input.value = (selected && selected.value) ? selected.textContent : '';
                }

                function setActive(index) {
                    currentItems.forEach(el => el.classList.remove('is-active'));
                    activeIndex = index;
                    if (currentItems[index]) {
                        currentItems[index].classList.add('is-active');
                        currentItems[index].scrollIntoView({ block: 'nearest' });
                    }
                }

                function seleccionar(opt) {
                    select.value = opt.value;
                    input.value = opt.textContent;
                    closeList();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function renderList(filterText) {
                    const term = (filterText || '').trim().toLowerCase();
                    const opts = getOptions().filter(opt => opt.textContent.toLowerCase().includes(term));
                    list.innerHTML = '';
                    currentItems = [];
                    activeIndex = -1;

                    if (opts.length === 0) {
                        const empty = document.createElement('div');
                        empty.className = 'searchable-select-empty';
                        empty.textContent = 'Sin resultados';
                        list.appendChild(empty);
                        return;
                    }

                    opts.forEach(opt => {
                        const item = document.createElement('div');
                        item.className = 'searchable-select-item' + (opt.value === select.value ? ' is-selected' : '');
                        item.textContent = opt.textContent;
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault(); // evita que el blur del input cierre la lista antes del click
                            seleccionar(opt);
                        });
                        list.appendChild(item);
                        currentItems.push(item);
                    });
                }

                function openList() {
                    wrap.classList.add('open');
                    renderList('');
                }

                function closeList() {
                    wrap.classList.remove('open');
                    currentItems = [];
                    activeIndex = -1;
                }

                input.addEventListener('focus', function () {
                    input.select();
                    openList();
                });

                input.addEventListener('input', function () {
                    wrap.classList.add('open');
                    renderList(input.value);
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!wrap.classList.contains('open')) { openList(); return; }
                        setActive(Math.min(activeIndex + 1, currentItems.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActive(Math.max(activeIndex - 1, 0));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const opts = getOptions().filter(opt => opt.textContent.toLowerCase().includes(input.value.trim().toLowerCase()));
                        const elegido = activeIndex >= 0 ? opts[activeIndex] : (opts.length === 1 ? opts[0] : null);
                        if (elegido) seleccionar(elegido);
                    } else if (e.key === 'Escape') {
                        closeList();
                        input.blur();
                    }
                });

                input.addEventListener('blur', function () {
                    setTimeout(syncInputFromSelect, 120);
                });

                document.addEventListener('click', function (e) {
                    if (!wrap.contains(e.target)) closeList();
                });

                // Si el <select> real se repuebla (nuevos datos del servidor),
                // se refleja automáticamente aquí sin tocar la lógica original.
                const observer = new MutationObserver(function () {
                    syncInputFromSelect();
                    if (wrap.classList.contains('open')) renderList(input.value);
                });
                observer.observe(select, { childList: true });

                const form = wrap.closest('form');
                if (form) {
                    form.addEventListener('reset', function () {
                        setTimeout(function () {
                            select.value = '';
                            input.value = '';
                        }, 0);
                    });
                }

                syncInputFromSelect();
            }

            document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);
        })();
    </script>


    <script>
        // ============================================================
        // FORZA UI · Ventana emergente de bienvenida al iniciar sesión
        // ============================================================
        (function () {
            const overlay = document.getElementById('welcome-overlay');
            if (!overlay) return;

            let hideTimeout;

            function ocultarOverlay() {
                overlay.classList.remove('show');
                clearTimeout(hideTimeout);
                document.body.style.overflow = '';
            }

            window.cerrarWelcomeOverlay = ocultarOverlay;

            window.addEventListener('load', function () {
                setTimeout(function () {
                    overlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                    hideTimeout = setTimeout(ocultarOverlay, 4000);
                }, 300);
            });

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) ocultarOverlay();
            });
        })();
    </script>

    <script>
        // ============================================================
        // FORZA UI · Microinteracciones visuales no invasivas
        // No reemplaza ni modifica funciones existentes del sistema.
        // ============================================================
        (function () {
            'use strict';

            // Efecto ripple en controles interactivos.
            document.addEventListener('click', function (event) {
                const target = event.target.closest('.btn, .nav-item, .theme-toggle-top, .user-menu-btn, .mobile-menu-btn');
                if (!target || target.disabled) return;

                const rect = target.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const ripple = document.createElement('span');
                ripple.className = 'forza-ripple';
                ripple.style.width = size + 'px';
                ripple.style.height = size + 'px';
                ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
                target.appendChild(ripple);
                window.setTimeout(() => ripple.remove(), 620);
            });

            // Botón para volver arriba: se crea por JS para no tocar el HTML funcional.
            const scrollTopBtn = document.createElement('button');
            scrollTopBtn.type = 'button';
            scrollTopBtn.className = 'forza-scroll-top';
            scrollTopBtn.title = 'Volver arriba';
            scrollTopBtn.setAttribute('aria-label', 'Volver arriba');
            scrollTopBtn.innerHTML = '<i class="fas fa-chevron-up" aria-hidden="true"></i>';
            document.body.appendChild(scrollTopBtn);

            function updateScrollButton() {
                scrollTopBtn.classList.toggle('visible', window.scrollY > 360);
            }

            window.addEventListener('scroll', updateScrollButton, { passive: true });
            updateScrollButton();

            scrollTopBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            // Marca visual temporal al cambiar de módulo sin interferir con showModule().
            document.addEventListener('click', function (event) {
                const nav = event.target.closest('.nav-item');
                if (!nav) return;
                nav.classList.add('forza-click-feedback');
                window.setTimeout(() => nav.classList.remove('forza-click-feedback'), 220);
            });
        })();
    </script>

    <script>
        // ============================================================
        // Bloqueo de autocompletado/sugerencias del navegador
        // Marca los campos como "solo lectura" hasta que el usuario
        // haga clic/foco en ellos, para que Chrome no llegue a mostrar
        // su listado de sugerencias guardadas (ignora autocomplete="off").
        // Se aplica también a campos creados dinámicamente (modales, filas).
        // ============================================================
        (function () {
            'use strict';
            const TIPOS_EXCLUIDOS = ['hidden', 'date', 'time', 'datetime-local', 'checkbox', 'radio', 'file', 'submit', 'button', 'number', 'color', 'range'];

            function protegerCampo(el) {
                if (el.dataset.noSugerencias) return;
                const tipo = (el.getAttribute('type') || 'text').toLowerCase();
                if (el.tagName === 'INPUT' && TIPOS_EXCLUIDOS.includes(tipo)) return;
                el.dataset.noSugerencias = '1';
                el.setAttribute('readonly', 'readonly');
                el.addEventListener('focus', function () {
                    el.removeAttribute('readonly');
                });
                el.addEventListener('mousedown', function () {
                    el.removeAttribute('readonly');
                });
            }

            function protegerTodos(raiz) {
                if (!raiz || !raiz.querySelectorAll) return;
                raiz.querySelectorAll('input, textarea').forEach(protegerCampo);
            }

            protegerTodos(document);
            document.addEventListener('DOMContentLoaded', function () { protegerTodos(document); });

            const observadorCampos = new MutationObserver(function (mutaciones) {
                mutaciones.forEach(function (mutacion) {
                    mutacion.addedNodes.forEach(function (nodo) {
                        if (nodo.nodeType !== 1) return;
                        if (nodo.matches && nodo.matches('input, textarea')) protegerCampo(nodo);
                        protegerTodos(nodo);
                    });
                });
            });
            observadorCampos.observe(document.body, { childList: true, subtree: true });
        })();
    </script>
</body>

</html>