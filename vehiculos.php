<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion/db.php';

$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
$usuario_es_admin = in_array(strtolower(trim($_SESSION['rol'] ?? '')), ['admin', 'administrador']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FORZA - Vehículos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#dc2626; --primary-dark:#991b1b; --primary-light:#fef2f2;
    --primary-accent:#ef4444; --primary-soft:#f87171;
    --secondary:#64748b;
    --success:#0d9488; --success-light:#ccfbf1;
    --danger:#be123c; --danger-light:#ffe4e6;
    --warning:#d97706; --warning-light:#fef3c7;
    --info:#2563eb; --info-light:#dbeafe;
    --bg-app:#f4f5fb; --bg-card:#ffffff;
    --text-primary:#111827; --text-secondary:#6b7280; --border:#e5e7eb;
    --shadow:0 1px 2px rgba(16,24,40,0.06), 0 1px 3px rgba(16,24,40,0.08);
    --shadow-md:0 4px 12px rgba(16,24,40,0.08);
    --radius:18px;
}
body{ font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; background:var(--bg-app); color:var(--text-primary); }
h1,h2,h3{ font-family:'Space Grotesk','Inter',sans-serif; }
.top-bar{
    position:fixed; top:0; right:0; left:280px; height:70px; background:var(--bg-card);
    border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;
    padding:0 2rem; z-index:100; box-shadow:var(--shadow); transition:left .2s ease;
}
.top-bar-left{ display:flex; align-items:center; gap:1rem; }
.sidebar-toggle-btn{
    width:38px; height:38px; border-radius:10px; border:1px solid var(--border);
    background:#f3f4f6; color:var(--text-primary); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:0.95rem;
    transition:background .15s ease;
}
.sidebar-toggle-btn:hover{ background:#e5e7eb; }
.user-chip{ display:flex; align-items:center; gap:0.6rem; font-size:0.85rem; color:var(--text-secondary); }
.user-avatar{ width:38px; height:38px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.9rem; box-shadow:0 3px 8px rgba(220,38,38,0.35); }
.user-info{ display:flex; flex-direction:column; line-height:1.2; gap:0.15rem; }
.user-info span{ font-weight:600; color:var(--text-primary); }
.role-pill{
    display:inline-flex; align-items:center; gap:0.3rem; font-size:0.62rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.04em; padding:0.15rem 0.55rem; border-radius:20px;
    margin-top:0.25rem; width:fit-content;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff;
    box-shadow:0 2px 6px rgba(220,38,38,0.3);
}
.role-pill i{ font-size:0.6rem; }
.role-pill.rol-lectura{
    background:#eef1f5; color:var(--text-secondary); box-shadow:none; border:1px solid var(--border);
}
.sidebar{
    position:fixed; left:0; top:0; width:280px; height:100vh; background:var(--bg-card);
    border-right:1px solid var(--border); display:flex; flex-direction:column; z-index:101;
    transition:width .18s ease;
}
.sidebar-header{ padding:2rem; border-bottom:1px solid var(--border); position:relative; }
.logo-container{ display:flex; align-items:center; gap:1rem; }
.logo-icon{
    width:50px; height:50px; background:linear-gradient(135deg,#dc2626,#991b1b);
    border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem;
    box-shadow:0 6px 16px rgba(220,38,38,0.35); flex-shrink:0;
}
.logo-text h1{ font-size:1.4rem; font-weight:800; letter-spacing:-0.02em; white-space:nowrap; }
.logo-text p{ font-size:0.72rem; color:var(--text-secondary); margin-top:0.15rem; letter-spacing:0.02em; white-space:nowrap; }
.nav-menu{ flex:1; padding:1rem; overflow-y:auto; overflow-x:hidden; }
.nav-item{
    width:100%; padding:0.9rem 1.1rem; margin-bottom:0.35rem; background:transparent; border:none;
    border-radius:12px; display:flex; align-items:center; gap:0.85rem; color:var(--text-secondary);
    font-size:0.92rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:left;
    transition:background .15s ease, color .15s ease; white-space:nowrap; overflow:hidden;
}
.nav-item i{ width:18px; text-align:center; color:var(--text-secondary); transition:color .15s ease; flex-shrink:0; }
.nav-item:hover{ background:var(--primary-light); color:var(--primary-dark); }
.nav-item:hover i{ color:var(--primary); }
.nav-item.active{ background:linear-gradient(135deg,var(--primary),var(--primary-accent)); color:#fff; box-shadow:0 4px 12px rgba(220,38,38,0.3); }
.nav-item.active i{ color:#fff; }
.main-content{ margin-left:280px; padding:90px 2rem 2rem; min-height:100vh; transition:margin-left .18s ease; }

.nav-section-label{ font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; padding:1rem 1.1rem 0.4rem; white-space:nowrap; overflow:hidden; }
.sidebar.collapsed .nav-section-label{ display:none; }
.nav-badge{ margin-left:auto; background:var(--primary-light); color:var(--primary-dark); font-size:0.7rem; font-weight:800; padding:0.15rem 0.5rem; border-radius:20px; flex-shrink:0; }
.nav-item.active .nav-badge{ background:rgba(255,255,255,0.25); color:#fff; }
.sidebar.collapsed .nav-badge{ display:none; }
.sidebar-footer{ border-top:1px solid var(--border); padding:1rem 1.1rem 1.2rem; }
.sidebar.collapsed .sidebar-footer{ display:none; }
.sidebar-alert-widget{ display:flex; align-items:center; gap:0.7rem; background:var(--primary-light); border:1px solid #fecaca; border-radius:12px; padding:0.7rem 0.85rem; }
.sidebar-alert-widget .icon{ width:34px; height:34px; border-radius:9px; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; }
.sidebar-alert-widget .icon.ok{ background:var(--success); }
.sidebar-alert-widget .txt-value{ font-size:0.95rem; font-weight:800; line-height:1.1; color:var(--text-primary); }
.sidebar-alert-widget .txt-label{ font-size:0.7rem; color:var(--text-secondary); font-weight:600; }

.sidebar.collapsed{ width:84px; }
.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-item span{ display:none; }
.sidebar.collapsed .logo-container{ justify-content:center; }
.sidebar.collapsed .nav-item{ justify-content:center; padding:0.9rem; }
.top-bar.collapsed{ left:84px; }
.main-content.collapsed{ margin-left:84px; }
.content-header{ margin-bottom:1.75rem; }
.content-header h2{ font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; }
.content-header p{ color:var(--text-secondary); font-size:0.92rem; margin-top:0.3rem; }

.stats-row{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-sinpiloto .stat-icon{ background:#f1f2f4; color:var(--secondary); }
.stat-alertas .stat-icon{ background:var(--primary-light); color:var(--primary); }
.search-filter-row{ display:flex; gap:0.75rem; margin-bottom:1rem; }
.search-filter-row .search-wrap{ flex:1; margin-bottom:0; }
.filter-select{ width:170px; flex-shrink:0; padding:0.75rem 1rem; border:1.5px solid var(--border); border-radius:11px; background:#fafafa; font-size:0.85rem; font-weight:600; color:var(--text-primary); }
.filter-select:focus{ outline:none; border-color:var(--primary); background:#fff; }
@media (max-width:520px){ .search-filter-row{ flex-direction:column; } .filter-select{ width:100%; } }
.stat-card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:1.1rem 1.25rem; box-shadow:var(--shadow); display:flex; align-items:center; gap:0.9rem; }
.stat-icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.stat-value{ font-size:1.4rem; font-weight:800; line-height:1; font-family:'Space Grotesk',sans-serif; }
.stat-label{ font-size:0.75rem; color:var(--text-secondary); margin-top:0.2rem; font-weight:600; }
.stat-total .stat-icon{ background:var(--primary-light); color:var(--primary); }
.stat-disponible .stat-icon{ background:var(--success-light); color:var(--success); }
.stat-asignado .stat-icon{ background:var(--info-light); color:var(--info); }
.stat-mantenimiento .stat-icon{ background:var(--warning-light); color:var(--warning); }

.flex{ display:flex; } .gap-2{ gap:0.5rem; }

.panels-wrap{
    display:grid; gap:1.5rem; align-items:start;
    grid-template-columns:380px 1fr 1fr;
    grid-template-areas:
        "formulario listado listado"
        "formulario alertas resumen";
}
.panel[data-panel="formulario"]{ grid-area:formulario; }
.panel[data-panel="listado"]{ grid-area:listado; }
.panel[data-panel="alertas"]{ grid-area:alertas; }
.panel[data-panel="resumen"]{ grid-area:resumen; }
.panel-scroll{ max-height:360px; overflow-y:auto; padding-right:0.3rem; }
.panel-scroll::-webkit-scrollbar{ width:7px; }
.panel-scroll::-webkit-scrollbar-thumb{ background:#d7d9e0; border-radius:10px; }
.panel-scroll::-webkit-scrollbar-thumb:hover{ background:#c3c5cf; }

@keyframes panelFadeIn{ from{ opacity:0; transform:translateX(28px); } to{ opacity:1; transform:translateX(0); } }
.panel{ animation:panelFadeIn .38s cubic-bezier(.16,1,.3,1) backwards; }
.panel:nth-child(2){ animation-delay:.05s; }
.panel:nth-child(3){ animation-delay:.1s; }
.panel:nth-child(4){ animation-delay:.15s; }
@media (prefers-reduced-motion:reduce){ .panel{ animation:none; } }
.card{ background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:1.6rem; box-shadow:var(--shadow); }
.card-title{ font-size:1.02rem; font-weight:700; margin-bottom:1.4rem; display:flex; align-items:center; gap:0.6rem; }
.card-title .title-icon{ width:34px; height:34px; border-radius:10px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:0.9rem; }

.btn{ display:inline-flex; align-items:center; gap:0.5rem; padding:0.8rem 1.3rem; border:none; border-radius:11px; font-size:0.9rem; font-weight:700; cursor:pointer; transition:transform .1s ease, box-shadow .15s ease; }
.btn-primary{ background:linear-gradient(135deg,var(--primary),var(--primary-accent)); color:#fff; width:100%; justify-content:center; box-shadow:0 4px 14px rgba(220,38,38,0.35); }
.btn-primary:hover{ transform:translateY(-1px); box-shadow:0 6px 18px rgba(220,38,38,0.45); }
.btn-secondary{ background:#f3f4f6; color:var(--text-primary); border:1px solid var(--border); }
.btn-secondary:hover{ background:#e5e7eb; }
.btn-danger{ background:var(--danger-light); color:var(--danger); }
.btn-danger:hover{ background:#fecdd3; }
.btn-sm{ padding:0.45rem 0.75rem; font-size:0.78rem; border-radius:9px; }

.form-group{ margin-bottom:1.15rem; }
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.form-label{ display:flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; margin-bottom:0.45rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em; }
.form-label i{ font-size:0.75rem; color:var(--primary); }
.form-input, .form-select, .form-textarea{
    width:100%; padding:0.8rem 1rem; border:1.5px solid var(--border); border-radius:11px; font-size:0.93rem;
    background:#fafafa; color:var(--text-primary); font-family:inherit; transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.form-input:focus, .form-select:focus, .form-textarea:focus{
    outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light);
}
.form-textarea{ min-height:70px; resize:vertical; }

.table-container{ overflow-x:auto; overflow-y:auto; max-height:420px; border-radius:14px; border:1px solid var(--border); }
.table-container thead th{ position:sticky; top:0; z-index:1; }
table{ width:100%; border-collapse:collapse; }
th{ text-align:left; padding:0.85rem 1.1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); background:#f9fafb; border-bottom:1px solid var(--border); font-weight:700; }
td{ padding:0.95rem 1.1rem; border-bottom:1px solid #f1f2f4; font-size:0.88rem; }
tbody tr:last-child td{ border-bottom:none; }
tbody tr{ transition:background .1s ease; }
tbody tr:hover td{ background:#faf9ff; }

.plate-chip{ display:inline-flex; align-items:center; gap:0.55rem; font-weight:700; }
.plate-icon{ width:32px; height:32px; border-radius:9px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:0.85rem; }

.aceite-celda{ font-size:0.78rem; line-height:1.35; min-width:130px; }
.aceite-restante{ font-weight:700; }
.aceite-restante.ok{ color:var(--success); }
.aceite-restante.aviso{ color:var(--warning); }
.aceite-restante.excedido{ color:var(--danger); }
.aceite-limite{ color:var(--text-secondary); font-size:0.72rem; }
.aceite-bar{ height:5px; border-radius:4px; background:#f1f2f4; margin-top:0.3rem; overflow:hidden; }
.aceite-bar-fill{ height:100%; border-radius:4px; }

.badge{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.32rem 0.75rem; border-radius:20px; font-size:0.74rem; font-weight:700; }
.badge::before{ content:''; width:6px; height:6px; border-radius:50%; }
.badge-disponible{ background:var(--success-light); color:#0f766e; } .badge-disponible::before{ background:var(--success); }
.badge-asignado{ background:var(--info-light); color:#1d4ed8; } .badge-asignado::before{ background:var(--info); }
.badge-mantenimiento{ background:var(--warning-light); color:#b45309; } .badge-mantenimiento::before{ background:var(--warning); }
.badge-inactivo{ background:#f1f2f4; color:var(--text-secondary); } .badge-inactivo::before{ background:var(--secondary); }

.search-wrap{ position:relative; margin-bottom:1rem; }
.search-wrap i{ position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:0.85rem; }
.search-input{ width:100%; padding:0.75rem 1rem 0.75rem 2.6rem; border:1.5px solid var(--border); border-radius:11px; background:#fafafa; font-size:0.9rem; transition:border-color .15s ease, box-shadow .15s ease; }
.search-input:focus{ outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light); }

.empty-row td, .error-row td{ text-align:center; padding:3rem 1rem; color:var(--text-secondary); }
.error-row td{ color:var(--danger); }

.modal-overlay{
    display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:200;
    align-items:center; justify-content:center; padding:1.5rem;
}
.modal-overlay.active{ display:flex; }
.modal-box{ background:var(--bg-card); border-radius:var(--radius); padding:1.6rem; width:100%; max-width:460px; max-height:88vh; overflow-y:auto; }
.modal-box h3{ font-size:1.1rem; font-weight:800; margin-bottom:0.2rem; display:flex; align-items:center; gap:0.55rem; }
.modal-box .modal-sub{ font-size:0.82rem; color:var(--text-secondary); margin-bottom:1.2rem; }
.modal-close{ float:right; background:none; border:none; font-size:1.1rem; color:var(--text-secondary); cursor:pointer; }
.km-form-row{ display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
.km-historial{ margin-top:1.3rem; border-top:1px solid var(--border); padding-top:1rem; }
.km-historial h4{ font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); margin-bottom:0.7rem; }
.km-item{ display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid #f1f2f4; font-size:0.85rem; }
.km-item:last-child{ border-bottom:none; }
.km-item .km-valor{ font-weight:700; }
.km-item .km-meta{ font-size:0.74rem; color:var(--text-secondary); }

.form-hint{ font-size:0.74rem; color:var(--text-secondary); margin-top:0.35rem; }


.alerta-item{ display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:0.85rem 0; border-bottom:1px solid #f1f2f4; }
.alerta-item:last-child{ border-bottom:none; }
.alerta-info .alerta-placa{ font-weight:700; font-size:0.9rem; }
.alerta-info .alerta-meta{ font-size:0.76rem; color:var(--text-secondary); margin-top:0.1rem; }
.alerta-pill{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.32rem 0.7rem; border-radius:20px; font-size:0.74rem; font-weight:700; white-space:nowrap; }
.alerta-pill.nivel-0{ background:#fee2e2; color:#991b1b; }
.alerta-pill.nivel-1{ background:var(--warning-light); color:#b45309; }
.alerta-pill.nivel-2{ background:#ffedd5; color:#9a3412; }
.alerta-pill.nivel-3{ background:var(--danger-light); color:#be123c; }

.resumen-flota{ display:flex; align-items:center; gap:1.75rem; padding:0.5rem 0.25rem; }
.resumen-dona{ position:relative; width:140px; height:140px; border-radius:50%; flex-shrink:0; background:conic-gradient(var(--border) 0 0); transition:background .4s ease; }
.resumen-dona::after{ content:''; position:absolute; inset:22px; background:var(--bg-card); border-radius:50%; z-index:0; }
.resumen-dona-centro{ position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:2; }
.resumen-dona-centro .valor{ font-size:1.6rem; font-weight:800; font-family:'Space Grotesk',sans-serif; line-height:1; }
.resumen-dona-centro .label{ font-size:0.68rem; color:var(--text-secondary); font-weight:600; margin-top:0.2rem; }
.resumen-leyenda{ flex:1; display:flex; flex-direction:column; gap:0.85rem; }
.resumen-leyenda-item{ display:flex; align-items:center; justify-content:space-between; font-size:0.88rem; }
.resumen-leyenda-item .nombre{ display:flex; align-items:center; gap:0.55rem; font-weight:600; color:var(--text-primary); }
.resumen-leyenda-item .dot{ width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.resumen-leyenda-item .valor{ font-weight:800; color:var(--text-secondary); font-family:'Space Grotesk',sans-serif; }
@media (max-width:420px){ .resumen-flota{ flex-direction:column; } }

/* ================= MODO NOCHE ================= */
body.dark-mode{
    --bg-app:#12141a; --bg-card:#1b1e26;
    --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038;
}
body.dark-mode .sidebar-toggle-btn{ background:#232733; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .sidebar-toggle-btn:hover{ background:#2c3038; }
body.dark-mode .btn-secondary{ background:#232733; color:var(--text-primary); }
body.dark-mode .btn-secondary:hover{ background:#2c3038; }
body.dark-mode .filter-select{ background:#20232c; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .filter-select:focus{ background:#20232c; }
body.dark-mode .form-input, body.dark-mode .form-select, body.dark-mode .search-input{ background:#20232c; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .form-input:focus, body.dark-mode .form-select:focus, body.dark-mode .search-input:focus{ background:#20232c; }
body.dark-mode th{ background:#20232c; }
body.dark-mode tbody tr:hover td{ background:#20232c; }
body.dark-mode .stat-sinpiloto .stat-icon{ background:#20232c; }
body.dark-mode .badge-inactivo{ background:#20232c; }
body.dark-mode .aceite-bar{ background:#20232c; }
body.dark-mode .role-pill.rol-lectura{ background:#20232c; border-color:var(--border); color:var(--text-secondary); }


@media (max-width:900px){
  .sidebar{ transform:translateX(-100%); }
  .sidebar.collapsed{ transform:translateX(0); width:280px; }
  .top-bar, .main-content{ left:0; margin-left:0; }
  .top-bar.collapsed, .main-content.collapsed{ margin-left:0; left:0; }
  .panels-wrap, .form-row, .stats-row{ grid-template-columns:1fr; }
  .panels-wrap{ grid-template-areas:"formulario" "listado" "alertas" "resumen"; }
}

/* ===== Notificaciones emergentes (toast) de cambio de aceite ===== */
.toast-container{
    position:fixed; top:86px; right:1.5rem; z-index:300;
    display:flex; flex-direction:column; gap:0.7rem; width:340px; max-width:calc(100vw - 2rem);
}
.toast{
    background:var(--bg-card); border:1px solid var(--border); border-left:5px solid var(--primary);
    border-radius:14px; box-shadow:var(--shadow-md); padding:0.9rem 1rem;
    display:flex; align-items:flex-start; gap:0.7rem;
    animation:toast-in .25s ease;
}
.toast.nivel-0{ border-left-color:#4f46e5; }
.toast.nivel-1{ border-left-color:var(--warning); }
.toast.nivel-2{ border-left-color:#ea580c; }
.toast.nivel-3{ border-left-color:var(--danger); }
.toast.toast-out{ animation:toast-out .2s ease forwards; }
@keyframes toast-in{ from{ opacity:0; transform:translateX(20px); } to{ opacity:1; transform:translateX(0); } }
@keyframes toast-out{ from{ opacity:1; transform:translateX(0); } to{ opacity:0; transform:translateX(20px); } }
.toast-icon{ width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.toast.nivel-0 .toast-icon{ background:#fee2e2; color:#991b1b; }
.toast.nivel-1 .toast-icon{ background:var(--warning-light); color:#b45309; }
.toast.nivel-2 .toast-icon{ background:#ffedd5; color:#9a3412; }
.toast.nivel-3 .toast-icon{ background:var(--danger-light); color:#be123c; }
.toast-body{ flex:1; min-width:0; }
.toast-title{ font-size:0.85rem; font-weight:700; margin-bottom:0.15rem; }
.toast-msg{ font-size:0.78rem; color:var(--text-secondary); line-height:1.35; }
.toast-close{ background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:0.85rem; padding:0.15rem; flex-shrink:0; }
.toast-close:hover{ color:var(--text-primary); }
</style>
</head>
<body>
<script>(function(){ if (localStorage.getItem('forza-theme') === 'dark') document.body.classList.add('dark-mode'); })();</script>

<div class="toast-container" id="toast-container"></div>


<div class="top-bar">
    <div class="top-bar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Contraer/expandir menú">
            <i class="fas fa-bars"></i>
        </button>
        <button class="sidebar-toggle-btn" onclick="toggleDarkMode()" title="Modo oscuro/claro">
            <i class="fas fa-moon" id="theme-toggle-icon"></i>
        </button>
        <strong>Vehículos</strong>
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($usuario_nombre,0,1)) ?></div>
        <div class="user-info">
            <span><?= htmlspecialchars($usuario_nombre) ?></span>
            <?php if ($usuario_es_admin): ?>
                <span class="role-pill"><i class="fas fa-user-shield"></i> Administrador</span>
            <?php else: ?>
                <span class="role-pill rol-lectura"><i class="fas fa-user"></i> Usuario</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text"><h1>FORZA</h1><p>VEHÍCULOS · HN</p></div>
        </div>
    </div>
    <nav class="nav-menu">
        <div class="nav-section-label">Principal</div>
        <a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
        <a class="nav-item" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>

        <div class="nav-section-label">Gestión de flota</div>
        <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Crear Piloto</span></a>
        <a class="nav-item active" href="vehiculos.php"><i class="fas fa-car"></i><span>Crear Vehículo</span><span class="nav-badge" id="nav-total-vehiculos">0</span></a>
        <a class="nav-item" href="combustible.php"><i class="fas fa-gas-pump"></i><span>Combustible</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-alert-widget" id="sidebar-alert-widget">
            <div class="icon ok" id="sidebar-alert-icon"><i class="fas fa-oil-can"></i></div>
            <div>
                <div class="txt-value" id="sidebar-alert-value">0</div>
                <div class="txt-label">Alertas de aceite</div>
            </div>
        </div>
    </div>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <h2>Vehículos</h2>
        <p>Registra los vehículos de la flota y consulta el listado</p>
    </div>

    <div class="stats-row">
        <div class="stat-card stat-total">
            <div class="stat-icon"><i class="fas fa-car-side"></i></div>
            <div><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card stat-disponible">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-value" id="stat-disponible">0</div><div class="stat-label">Disponibles</div></div>
        </div>
        <div class="stat-card stat-asignado">
            <div class="stat-icon"><i class="fas fa-route"></i></div>
            <div><div class="stat-value" id="stat-asignado">0</div><div class="stat-label">Asignados</div></div>
        </div>
        <div class="stat-card stat-mantenimiento">
            <div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div>
            <div><div class="stat-value" id="stat-mantenimiento">0</div><div class="stat-label">Mantenimiento</div></div>
        </div>
        <div class="stat-card stat-sinpiloto">
            <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            <div><div class="stat-value" id="stat-sinpiloto">0</div><div class="stat-label">Sin piloto</div></div>
        </div>
        <div class="stat-card stat-alertas">
            <div class="stat-icon"><i class="fas fa-oil-can"></i></div>
            <div><div class="stat-value" id="stat-alertas">0</div><div class="stat-label">Alertas aceite</div></div>
        </div>
    </div>

    <div class="panels-wrap" id="panels-wrap">
        <!-- FORMULARIO DE CREACIÓN -->
        <div class="card panel" data-panel="formulario">
            <div class="card-title" id="form-titulo">
                <span class="title-icon"><i class="fas fa-car"></i></span> Nuevo Vehículo
            </div>
            <form id="form-vehiculo" onsubmit="guardarVehiculo(event)">
                <input type="hidden" name="id" id="veh-id">

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-hashtag"></i> Placa</label>
                    <input type="text" class="form-input" name="placa" id="veh-placa" required placeholder="Ej: PR-215" style="text-transform:uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-industry"></i> Marca</label>
                        <input type="text" class="form-input" name="marca" id="veh-marca" placeholder="Ej: Toyota">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-truck-pickup"></i> Modelo</label>
                        <input type="text" class="form-input" name="modelo" id="veh-modelo" placeholder="Ej: Hilux">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-palette"></i> Color</label>
                        <input type="text" class="form-input" name="color" id="veh-color" placeholder="Ej: Blanco">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-toggle-on"></i> Estado</label>
                        <select class="form-select" name="estado" id="veh-estado">
                            <option value="disponible">Disponible</option>
                            <option value="asignado" id="opcion-estado-asignado" disabled>Asignado (automático)</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                       
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> Piloto asignado</label>
                        <select class="form-select" name="patrullero_id" id="veh-patrullero">
                            <option value="">Sin asignar</option>
                        </select>
                        
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-location-dot"></i> Zona</label>
                        <select class="form-select" name="zona" id="veh-zona" onchange="onCambioZonaVehiculo()">
                            <option value="">Sin asignar</option>
                            <option value="Norte">Norte</option>
                            <option value="Centro">Centro</option>
                            <option value="Sur">Sur</option>
                            <option value="Motorizadas">Motorizadas</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-oil-can"></i> Límite aceite (km)</label>
                        <input type="number" class="form-input" name="km_limite_aceite" id="veh-km-limite" min="0" step="1" placeholder="Ej: 50000" oninput="actualizarHintLimiteAceite()">
                        <div class="form-hint" id="veh-km-limite-hint"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-note-sticky"></i> Observaciones</label>
                    <textarea class="form-textarea" name="observaciones" id="veh-observaciones" placeholder="Notas adicionales..."></textarea>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Guardar Vehículo</button>
                </div>
                <div class="flex gap-2" style="margin-top:0.6rem;" id="btn-cancelar-edicion-wrapper" hidden>
                    <button type="button" class="btn btn-secondary" style="width:100%;justify-content:center;" onclick="cancelarEdicion()">Cancelar edición</button>
                </div>
            </form>
        </div>

        <!-- LISTADO -->
        <div class="card panel" data-panel="listado">
            <div class="card-title"><span class="title-icon"><i class="fas fa-list"></i></span> Vehículos registrados</div>
            <div class="search-filter-row">
                <div class="search-wrap">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" class="search-input" id="buscar-vehiculo" placeholder="Buscar por placa, marca o modelo..." oninput="renderVehiculos()">
                </div>
                <select class="filter-select" id="filtro-estado" onchange="renderVehiculos()">
                    <option value="">Todos los estados</option>
                    <option value="disponible">Disponible</option>
                    <option value="asignado">Asignado</option>
                    <option value="mantenimiento">Mantenimiento</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Vehículo</th><th>Modelo</th><th>Color</th><th>Estado</th><th>Piloto</th><th>Kilometraje</th><th>Cambio de aceite</th><th style="text-align:right;">Acciones</th></tr>
                    </thead>
                    <tbody id="tabla-vehiculos-body">
                        <tr class="empty-row"><td colspan="8">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ALERTAS DE CAMBIO DE ACEITE -->
        <div class="card panel" data-panel="alertas">
            <div class="card-title"><span class="title-icon"><i class="fas fa-oil-can"></i></span> Alertas de cambio de aceite</div>
            <div id="lista-alertas" class="panel-scroll">
                <div style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;">Cargando...</div>
            </div>
        </div>

        <!-- RESUMEN DE LA FLOTA POR ESTADO -->
        <div class="card panel" data-panel="resumen">
            <div class="card-title"><span class="title-icon"><i class="fas fa-chart-pie"></i></span> Resumen de la flota</div>
            <div class="resumen-flota">
                <div class="resumen-dona" id="resumen-dona">
                    <div class="resumen-dona-centro">
                        <div class="valor" id="resumen-total-valor">0</div>
                        <div class="label">vehículos</div>
                    </div>
                </div>
                <div class="resumen-leyenda" id="resumen-leyenda"></div>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modal-km">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="cerrarModalKilometraje()"><i class="fas fa-xmark"></i></button>
        <h3><i class="fas fa-gauge-high"></i> Kilometraje — <span id="km-placa-titulo"></span></h3>
        <div class="modal-sub">Registra o consulta las lecturas de odómetro de este vehículo.</div>

        <form id="form-km" onsubmit="guardarKilometraje(event)">
            <input type="hidden" id="km-vehiculo-id">
            <div class="km-form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-gauge-high"></i> Kilometraje</label>
                    <input type="number" class="form-input" id="km-valor" min="0" step="1" required placeholder="Ej: 45230">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-calendar"></i> Fecha</label>
                    <input type="date" class="form-input" id="km-fecha" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-note-sticky"></i> Observaciones (opcional)</label>
                <input type="text" class="form-input" id="km-observaciones" placeholder="Ej: fin de jornada">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Registrar lectura</button>
        </form>

        <div class="km-historial">
            <h4>Historial</h4>
            <div id="km-historial-lista"><div class="loading" style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;">Cargando...</div></div>
        </div>
    </div>
</div>

<script>
const USUARIO_ES_ADMIN = <?php echo $usuario_es_admin ? 'true' : 'false'; ?>;
let vehiculos = [];
let ultimosKilometrajes = {};
let pilotos = [];
let alertasPorVehiculo = {};
const ESTADO_LABEL = { disponible:'Disponible', asignado:'Asignado', mantenimiento:'Mantenimiento', inactivo:'Inactivo' };
const COLOR_NIVEL = { 0:'#4338ca', 1:'#b45309', 2:'#9a3412', 3:'#be123c' };

// ===== Colapsar / expandir panel izquierdo =====
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.top-bar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
}

// ===== Modo oscuro / claro =====
function actualizarIconoTema() {
    const icono = document.getElementById('theme-toggle-icon');
    if (icono) icono.className = document.body.classList.contains('dark-mode') ? 'fas fa-sun' : 'fas fa-moon';
}

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('forza-theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    actualizarIconoTema();
}

actualizarIconoTema();

async function cargarPilotos() {
    try {
        const res = await fetch('api/piloto_listar.php');
        const data = await res.json();
        pilotos = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('No se pudo cargar la lista de pilotos', err);
        pilotos = [];
    }
}

// Rellena el selector de piloto dejando solo a los que no tienen otro vehículo
// asignado (más el que ya tenga este vehículo, si estamos editando), y filtrando
// por la zona elegida en el vehículo: si el vehículo tiene una zona, solo se
// muestran pilotos de esa misma zona (o sin zona asignada, para no bloquear
// pilotos antiguos que aún no tengan zona configurada).
function poblarSelectPilotos(vehiculoIdActual, patrulleroIdActual) {
    const select = document.getElementById('veh-patrullero');
    const zonaVehiculo = document.getElementById('veh-zona').value;

    const idsOcupados = new Set(
        vehiculos
            .filter(v => v.patrullero_id && v.id != vehiculoIdActual)
            .map(v => String(v.patrullero_id))
    );

    let disponibles = pilotos.filter(p => !idsOcupados.has(String(p.id)));

    if (zonaVehiculo) {
        disponibles = disponibles.filter(p => !p.zona || p.zona === zonaVehiculo);
    }

    if (disponibles.length === 0) {
        select.innerHTML = '<option value="">Sin asignar</option>' +
            `<option value="" disabled>— No hay pilotos disponibles en la zona ${zonaVehiculo} —</option>`;
    } else {
        select.innerHTML = '<option value="">Sin asignar</option>' +
            disponibles.map(p => `<option value="${p.id}">${p.nombre}${p.zona ? ' — ' + p.zona : ''}</option>`).join('');
    }

    select.value = patrulleroIdActual || '';
}

// Al cambiar la zona del vehículo, se refresca el selector de pilotos para
// que solo aparezcan los de esa zona.
function onCambioZonaVehiculo() {
    const idVehiculo = document.getElementById('veh-id').value || null;
    const patrulleroActual = document.getElementById('veh-patrullero').value;
    poblarSelectPilotos(idVehiculo, patrulleroActual);
}

async function cargarUltimosKilometrajes() {
    try {
        const res = await fetch('api/vehiculo_kilometraje_listar.php');
        const data = await res.json();
        ultimosKilometrajes = {};
        if (Array.isArray(data)) {
            data.forEach(k => { ultimosKilometrajes[k.vehiculo_id] = k; });
        }
    } catch (err) {
        console.error('No se pudo cargar el último kilometraje', err);
    }
}

async function cargarVehiculos() {
    try {
        const res = await fetch('api/vehiculo_listar.php');
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) {
            console.error('Respuesta no-JSON:', raw);
            document.getElementById('tabla-vehiculos-body').innerHTML =
                '<tr class="error-row"><td colspan="8"><i class="fas fa-triangle-exclamation"></i> Error del servidor. Revisa la consola (F12).</td></tr>';
            return;
        }
        if (data && data.success === false) {
            document.getElementById('tabla-vehiculos-body').innerHTML =
                `<tr class="error-row"><td colspan="8">${data.message}</td></tr>`;
            return;
        }
        vehiculos = Array.isArray(data) ? data : [];
        await cargarUltimosKilometrajes();
        actualizarStats();
        renderVehiculos();
        // Si el formulario está en modo "nuevo" (sin edición en curso), refresca el
        // selector de pilotos para reflejar quién sigue disponible.
        if (!document.getElementById('veh-id').value) {
            poblarSelectPilotos(null, '');
        }
    } catch (err) {
        console.error(err);
        document.getElementById('tabla-vehiculos-body').innerHTML =
            '<tr class="error-row"><td colspan="8">Error de conexión</td></tr>';
    }
}

function actualizarStats() {
    document.getElementById('stat-total').textContent = vehiculos.length;
    document.getElementById('stat-disponible').textContent = vehiculos.filter(v => v.estado === 'disponible').length;
    document.getElementById('stat-asignado').textContent = vehiculos.filter(v => v.estado === 'asignado').length;
    document.getElementById('stat-mantenimiento').textContent = vehiculos.filter(v => v.estado === 'mantenimiento').length;
    document.getElementById('stat-sinpiloto').textContent = vehiculos.filter(v => v.estado !== 'inactivo' && !v.patrullero_id).length;
    document.getElementById('nav-total-vehiculos').textContent = vehiculos.length;
    actualizarResumenFlota();
}

// ===== Resumen de la flota por estado (dona) =====
const RESUMEN_ESTADO_COLOR = {
    disponible: 'var(--success)',
    asignado: 'var(--info)',
    mantenimiento: 'var(--warning)',
    inactivo: 'var(--secondary)'
};

function actualizarResumenFlota() {
    const total = vehiculos.length;
    document.getElementById('resumen-total-valor').textContent = total;

    const dona = document.getElementById('resumen-dona');
    const leyenda = document.getElementById('resumen-leyenda');

    if (total === 0) {
        dona.style.background = 'conic-gradient(var(--border) 0 100%)';
        leyenda.innerHTML = '<div style="color:var(--text-secondary);font-size:0.85rem;">Aún no hay vehículos registrados.</div>';
        return;
    }

    // Cuenta por estado, en un orden fijo para que la leyenda no salte de posición
    const conteo = { disponible: 0, asignado: 0, mantenimiento: 0, inactivo: 0 };
    vehiculos.forEach(v => { if (conteo.hasOwnProperty(v.estado)) conteo[v.estado]++; });

    // Arma el conic-gradient acumulando porcentajes
    let acumulado = 0;
    const segmentos = [];
    Object.entries(conteo).forEach(([estado, cantidad]) => {
        if (cantidad === 0) return;
        const desde = acumulado;
        acumulado += (cantidad / total) * 100;
        segmentos.push(`${RESUMEN_ESTADO_COLOR[estado]} ${desde}% ${acumulado}%`);
    });
    dona.style.background = `conic-gradient(${segmentos.join(', ')})`;

    leyenda.innerHTML = Object.entries(conteo).map(([estado, cantidad]) => `
        <div class="resumen-leyenda-item">
            <div class="nombre"><span class="dot" style="background:${RESUMEN_ESTADO_COLOR[estado]};"></span>${ESTADO_LABEL[estado] || estado}</div>
            <div class="valor">${cantidad}</div>
        </div>
    `).join('');
}

function renderVehiculos() {
    const texto = document.getElementById('buscar-vehiculo').value.toLowerCase().trim();
    const estadoFiltro = document.getElementById('filtro-estado').value;
    const datos = vehiculos.filter(v =>
        (!texto || (v.placa + ' ' + (v.marca || '') + ' ' + (v.modelo || '')).toLowerCase().includes(texto)) &&
        (!estadoFiltro || v.estado === estadoFiltro)
    );

    const tbody = document.getElementById('tabla-vehiculos-body');
    if (datos.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><i class="fas fa-car-side" style="font-size:1.4rem;display:block;margin-bottom:0.5rem;opacity:0.4;"></i>No hay vehículos registrados</td></tr>';
        return;
    }

    tbody.innerHTML = datos.map(v => {
        const alerta = alertasPorVehiculo[v.id];
        const filaEstilo = alerta ? `border-left:4px solid ${COLOR_NIVEL[alerta.nivel] || 'var(--primary)'};` : '';
        return `
        <tr style="${filaEstilo}">
            <td>
                <span class="plate-chip">
                    <span class="plate-icon"><i class="fas fa-car"></i></span>
                    ${v.placa}
                </span>
            </td>
            <td>${[v.marca, v.modelo].filter(Boolean).join(' ') || '—'}</td>
            <td>${v.color || '—'}</td>
            <td><span class="badge badge-${v.estado}">${ESTADO_LABEL[v.estado] || v.estado}</span></td>
            <td>${nombrePiloto(v.patrullero_id)}</td>
            <td>${renderKilometrajeCelda(v.id)}</td>
            <td>${renderCeldaAceite(v)}${alerta ? `<i class="fas fa-oil-can" style="color:${COLOR_NIVEL[alerta.nivel] || 'var(--primary)'};margin-left:0.4rem;" title="Alerta de cambio de aceite"></i>` : ''}</td>
            <td style="text-align:right;">
                <button class="btn btn-secondary btn-sm" onclick='abrirModalKilometraje(${v.id}, ${JSON.stringify(v.placa || "").replace(/'/g,"&#39;")})' title="Ver/Registrar kilometraje"><i class="fas fa-gauge-high"></i></button>
                ${USUARIO_ES_ADMIN ? `
                <button class="btn btn-secondary btn-sm" onclick='editarVehiculo(${JSON.stringify(v).replace(/'/g,"&#39;")})'><i class="fas fa-pen"></i></button>
                <button class="btn btn-danger btn-sm" onclick="eliminarVehiculo(${v.id})"><i class="fas fa-ban"></i></button>
                ` : ''}
            </td>
        </tr>
    `;}).join('');
}

function nombrePiloto(patrulleroId) {
    if (!patrulleroId) return '<span style="color:var(--text-secondary);">Sin asignar</span>';
    const p = pilotos.find(p => String(p.id) === String(patrulleroId));
    return p ? p.nombre : `#${patrulleroId}`;
}

function renderKilometrajeCelda(vehiculoId) {
    const ultimo = ultimosKilometrajes[vehiculoId];
    if (!ultimo) return '<span style="color:var(--text-secondary);">Sin registro</span>';
    const km = Number(ultimo.kilometraje).toLocaleString('es-HN');
    return `<strong>${km} km</strong><div style="font-size:0.72rem;color:var(--text-secondary);">${ultimo.fecha}</div>`;
}

function renderCeldaAceite(v) {
    const limite = Number(v.km_limite_aceite) || 0;
    const ultimo = ultimosKilometrajes[v.id];

    if (!limite) {
        return '<span style="color:var(--text-secondary);">Sin límite definido</span>';
    }
    if (!ultimo) {
        return `<div class="aceite-celda"><span style="color:var(--text-secondary);">Sin kilometraje registrado</span><div class="aceite-limite">Límite: ${limite.toLocaleString('es-HN')} km</div></div>`;
    }

    const actual = Number(ultimo.kilometraje);
    const restante = limite - actual;
    const porcentaje = Math.min(100, Math.max(0, (actual / limite) * 100));

    let nivel = 'ok', barColor = 'var(--success)', texto;
    if (restante <= 0) {
        nivel = 'excedido'; barColor = 'var(--danger)';
        texto = `Excedido por ${Math.abs(restante).toLocaleString('es-HN')} km`;
    } else if (restante <= 1500) {
        nivel = 'aviso'; barColor = 'var(--warning)';
        texto = `Faltan ${restante.toLocaleString('es-HN')} km`;
    } else {
        texto = `Faltan ${restante.toLocaleString('es-HN')} km`;
    }

    return `
        <div class="aceite-celda">
            <span class="aceite-restante ${nivel}">${texto}</span>
            <div class="aceite-limite">Límite: ${limite.toLocaleString('es-HN')} km</div>
            <div class="aceite-bar"><div class="aceite-bar-fill" style="width:${porcentaje}%;background:${barColor};"></div></div>
        </div>
    `;
}

function editarVehiculo(v) {
    document.getElementById('veh-id').value = v.id;
    document.getElementById('veh-placa').value = v.placa;
    document.getElementById('veh-marca').value = v.marca || '';
    document.getElementById('veh-modelo').value = v.modelo || '';
    document.getElementById('veh-color').value = v.color || '';
    document.getElementById('veh-estado').value = v.estado;
    document.getElementById('veh-observaciones').value = v.observaciones || '';
    document.getElementById('veh-km-limite').value = v.km_limite_aceite || '';
    document.getElementById('veh-zona').value = v.zona || '';
    poblarSelectPilotos(v.id, v.patrullero_id || '');
    document.getElementById('form-titulo').innerHTML = '<span class="title-icon"><i class="fas fa-pen"></i></span> Editar Vehículo';
    document.getElementById('btn-cancelar-edicion-wrapper').hidden = false;
    actualizarHintLimiteAceite();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function actualizarHintLimiteAceite() {
    const hint = document.getElementById('veh-km-limite-hint');
    const vehiculoId = document.getElementById('veh-id').value;
    const limite = Number(document.getElementById('veh-km-limite').value) || 0;

    if (!limite) { hint.textContent = ''; return; }

    const ultimo = vehiculoId ? ultimosKilometrajes[vehiculoId] : null;
    if (!ultimo) {
        hint.textContent = 'Aún no hay kilometraje registrado para calcular cuánto falta.';
        return;
    }

    const actual = Number(ultimo.kilometraje);
    const restante = limite - actual;
    if (restante <= 0) {
        hint.innerHTML = `<span style="color:var(--danger);font-weight:700;">⚠ Ya superó el límite por ${Math.abs(restante).toLocaleString('es-HN')} km</span> (último registro: ${actual.toLocaleString('es-HN')} km)`;
    } else {
        hint.innerHTML = `Con el último registro de <strong>${actual.toLocaleString('es-HN')} km</strong>, faltan <strong>${restante.toLocaleString('es-HN')} km</strong> para el cambio de aceite.`;
    }
}

function cancelarEdicion() {
    document.getElementById('form-vehiculo').reset();
    document.getElementById('veh-id').value = '';
    poblarSelectPilotos(null, '');
    document.getElementById('form-titulo').innerHTML = '<span class="title-icon"><i class="fas fa-car"></i></span> Nuevo Vehículo';
    document.getElementById('btn-cancelar-edicion-wrapper').hidden = true;
    document.getElementById('veh-km-limite-hint').textContent = '';
}

async function guardarVehiculo(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const id = document.getElementById('veh-id').value;
    const endpoint = id ? 'api/vehiculo_editar.php' : 'api/vehiculo_guardar.php';

    try {
        const res = await fetch(endpoint, { method: 'POST', body: formData });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) {
            console.error('Respuesta no-JSON del servidor:', raw);
            alert('❌ El servidor devolvió una respuesta inesperada. Revisa la consola (F12) para ver el detalle.');
            return;
        }
        if (data.success) {
            alert('✅ ' + data.message);
            cancelarEdicion();
            await cargarVehiculos();
        } else {
            alert('❌ ' + (data.message || 'Error al guardar el vehículo'));
        }
    } catch (err) {
        console.error(err);
        alert('❌ Error de conexión al guardar');
    }
}

async function eliminarVehiculo(id) {
    if (!confirm('¿Marcar este vehículo como inactivo?')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/vehiculo_eliminar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            await cargarVehiculos();
        } else {
            alert('❌ ' + (data.message || 'Error al eliminar'));
        }
    } catch (err) {
        alert('❌ Error de conexión al eliminar');
    }
}

(async function init() {
    await cargarPilotos();
    await cargarVehiculos();
    cargarAlertas();
})();

// Refresca alertas cada 60s, por si llega un reporte nuevo mientras el admin tiene la pantalla abierta
setInterval(() => { cargarAlertas(); }, 60000);

// ===== Alertas de cambio de aceite =====
async function cargarAlertas() {
    const cont = document.getElementById('lista-alertas');
    try {
        const res = await fetch('api/vehiculo_alertas_listar.php');
        const data = await res.json();
        if (!Array.isArray(data)) {
            cont.innerHTML = `<div style="padding:1rem 0;color:var(--danger);font-size:0.85rem;">${data.message || 'Error al cargar las alertas'}</div>`;
            return;
        }
        alertasPorVehiculo = {};
        data.forEach(a => { alertasPorVehiculo[a.vehiculo_id] = a; });
        actualizarWidgetAlertas(data.length);
        renderVehiculos();
        if (data.length === 0) {
            cont.innerHTML = '<div style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;"><i class="fas fa-circle-check" style="color:var(--success);"></i> Ningún vehículo necesita cambio de aceite por ahora.</div>';
            return;
        }
        const ETIQUETA = { proximo: 'Próximo', aviso: 'Aviso', urgente: 'Urgente', critico: 'Crítico' };
        notificarAlertasNuevas(data, ETIQUETA);
        cont.innerHTML = data.map(a => {
            const metaKm = a.km_pasado_limite >= 0
                ? `${a.km_pasado_limite.toLocaleString('es-HN')} km sobre el límite`
                : `faltan ${Math.abs(a.km_pasado_limite).toLocaleString('es-HN')} km para el límite`;
            return `
            <div class="alerta-item">
                <div class="alerta-info">
                    <div class="alerta-placa">${a.placa}${a.marca || a.modelo ? ' · ' + [a.marca, a.modelo].filter(Boolean).join(' ') : ''}</div>
                    <div class="alerta-meta">${a.patrullero_nombre ? 'Piloto: ' + a.patrullero_nombre : 'Sin piloto asignado'} · ${a.ultimo_kilometraje.toLocaleString('es-HN')} km (${metaKm})</div>
                </div>
                <span class="alerta-pill nivel-${a.nivel}">${ETIQUETA[a.tipo] || a.tipo}</span>
            </div>
        `;}).join('');
    } catch (err) {
        cont.innerHTML = '<div style="padding:1rem 0;color:var(--danger);font-size:0.85rem;">Error de conexión al cargar las alertas</div>';
    }
}

function actualizarWidgetAlertas(total) {
    document.getElementById('stat-alertas').textContent = total;
    document.getElementById('sidebar-alert-value').textContent = total;
    const icon = document.getElementById('sidebar-alert-icon');
    icon.classList.toggle('ok', total === 0);
}

// ===== Kilometraje =====
function abrirModalKilometraje(vehiculoId, placa) {
    document.getElementById('km-vehiculo-id').value = vehiculoId;
    document.getElementById('km-placa-titulo').textContent = placa;
    document.getElementById('km-valor').value = '';
    document.getElementById('km-observaciones').value = '';
    document.getElementById('km-fecha').value = fechaLocalHN();
    document.getElementById('modal-km').classList.add('active');
    cargarHistorialKilometraje(vehiculoId);
}

// Devuelve la fecha de HOY en hora de Honduras (America/Tegucigalpa), como YYYY-MM-DD.
// OJO: no usar new Date().toISOString() para esto, porque toISOString() siempre
// da la fecha en UTC. Honduras es UTC-6, así que entre las 6:00pm y medianoche
// hora local, toISOString() ya devuelve el día siguiente, lo que desincroniza
// el kilometraje reportado contra el "Reporte diario" (que compara por fecha).
function fechaLocalHN() {
    const ahora = new Date();
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Tegucigalpa',
        year: 'numeric', month: '2-digit', day: '2-digit'
    }).formatToParts(ahora);
    const obj = {};
    partes.forEach(p => obj[p.type] = p.value);
    return `${obj.year}-${obj.month}-${obj.day}`;
}

function cerrarModalKilometraje() {
    document.getElementById('modal-km').classList.remove('active');
}

async function cargarHistorialKilometraje(vehiculoId) {
    const cont = document.getElementById('km-historial-lista');
    cont.innerHTML = '<div style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;">Cargando...</div>';
    try {
        const res = await fetch('api/vehiculo_kilometraje_listar.php?vehiculo_id=' + vehiculoId);
        const data = await res.json();
        if (data && data.success === false) {
            cont.innerHTML = `<div style="padding:0.5rem 0;color:var(--danger);font-size:0.85rem;">❌ ${data.message || 'Error al cargar el historial.'}</div>`;
            console.error('Error del servidor al listar kilometraje:', data);
            return;
        }
        if (!Array.isArray(data) || data.length === 0) {
            cont.innerHTML = '<div style="padding:0.5rem 0;color:var(--text-secondary);font-size:0.85rem;">Sin lecturas registradas todavía.</div>';
            return;
        }
        cont.innerHTML = data.map(k => `
            <div class="km-item">
                <div>
                    <div class="km-valor">${Number(k.kilometraje).toLocaleString('es-HN')} km</div>
                    <div class="km-meta">${k.fecha}${k.patrullero_nombre ? ' · ' + k.patrullero_nombre : ''}${k.observaciones ? ' · ' + k.observaciones : ''}</div>
                </div>
            </div>
        `).join('');
    } catch (err) {
        cont.innerHTML = '<div style="padding:0.5rem 0;color:var(--danger);font-size:0.85rem;">Error al cargar el historial.</div>';
    }
}

async function guardarKilometraje(event) {
    event.preventDefault();
    const vehiculoId = document.getElementById('km-vehiculo-id').value;
    const fd = new FormData();
    fd.append('vehiculo_id', vehiculoId);
    fd.append('kilometraje', document.getElementById('km-valor').value);
    fd.append('fecha', document.getElementById('km-fecha').value);
    fd.append('observaciones', document.getElementById('km-observaciones').value);

    try {
        const res = await fetch('api/vehiculo_kilometraje_guardar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('km-valor').value = '';
            document.getElementById('km-observaciones').value = '';
            await cargarHistorialKilometraje(vehiculoId);
            await cargarUltimosKilometrajes();
            renderVehiculos();
        } else {
            alert('❌ ' + (data.message || 'Error al registrar el kilometraje'));
        }
    } catch (err) {
        alert('❌ Error de conexión al registrar el kilometraje');
    }
}

// ===== Notificaciones emergentes (toast) de cambio de aceite =====
// Se dispara cada vez que aparece una alerta que no se había mostrado antes
// (por vehículo + tipo de alerta). Se persiste en localStorage para que no
// se repita en cada recarga si nada cambió, pero sí avise cuando un vehículo
// entra a un nivel nuevo (ej: pasó de "aviso" a "urgente").
const TOAST_STORAGE_KEY = 'forza_alertas_aceite_notificadas';
const TOAST_ICONO = { proximo: 'fa-clock', aviso: 'fa-oil-can', urgente: 'fa-triangle-exclamation', critico: 'fa-circle-exclamation' };
const TOAST_TITULO = { proximo: 'Próximo cambio de aceite', aviso: 'Cambio de aceite pendiente', urgente: 'Cambio de aceite urgente', critico: 'Cambio de aceite crítico' };

function leerAlertasNotificadas() {
    try {
        return new Set(JSON.parse(localStorage.getItem(TOAST_STORAGE_KEY) || '[]'));
    } catch (e) {
        return new Set();
    }
}

function guardarAlertasNotificadas(set) {
    try {
        localStorage.setItem(TOAST_STORAGE_KEY, JSON.stringify([...set]));
    } catch (e) { /* almacenamiento no disponible, se ignora */ }
}

function notificarAlertasNuevas(alertas, ETIQUETA) {
    const vistas = leerAlertasNotificadas();
    const clavesActuales = new Set();

    alertas.forEach(a => {
        const clave = `${a.vehiculo_id}:${a.tipo}`;
        clavesActuales.add(clave);
        if (!vistas.has(clave)) {
            mostrarToastAceite(a, ETIQUETA);
            vistas.add(clave);
        }
    });

    // Limpia del storage las claves de vehículos que ya no están en alerta
    // (por ejemplo, después de un cambio de aceite), para que si vuelven a
    // entrar en ese mismo nivel más adelante, se vuelva a notificar.
    [...vistas].forEach(clave => {
        if (!clavesActuales.has(clave)) vistas.delete(clave);
    });

    guardarAlertasNotificadas(vistas);
}

function mostrarToastAceite(a, ETIQUETA) {
    const cont = document.getElementById('toast-container');
    if (!cont) return;

    const metaKm = a.km_pasado_limite >= 0
        ? `${a.km_pasado_limite.toLocaleString('es-HN')} km sobre el límite`
        : `faltan ${Math.abs(a.km_pasado_limite).toLocaleString('es-HN')} km para el límite`;

    const toast = document.createElement('div');
    toast.className = `toast nivel-${a.nivel}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${TOAST_ICONO[a.tipo] || 'fa-oil-can'}"></i></div>
        <div class="toast-body">
            <div class="toast-title">${TOAST_TITULO[a.tipo] || 'Alerta de aceite'} · ${a.placa}</div>
            <div class="toast-msg">${a.patrullero_nombre ? a.patrullero_nombre + ' · ' : ''}${a.ultimo_kilometraje.toLocaleString('es-HN')} km (${metaKm})</div>
        </div>
        <button type="button" class="toast-close" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
    `;

    const cerrar = () => {
        toast.classList.add('toast-out');
        setTimeout(() => toast.remove(), 200);
    };
    toast.querySelector('.toast-close').addEventListener('click', cerrar);

    cont.appendChild(toast);

    // Autocierre: las críticas/urgentes duran más en pantalla
    const duracion = (a.nivel >= 2) ? 12000 : 7000;
    setTimeout(cerrar, duracion);
}
</script>

</body>
</html>