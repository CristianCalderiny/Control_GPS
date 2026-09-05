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
<title>FORZA - Combustible</title>
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
.role-pill.rol-lectura{ background:#eef1f5; color:var(--text-secondary); box-shadow:none; border:1px solid var(--border); }
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
.sidebar.collapsed .logo-text, .sidebar.collapsed .nav-item span{ display:none; }
.sidebar.collapsed .logo-container{ justify-content:center; }
.sidebar.collapsed .nav-item{ justify-content:center; padding:0.9rem; }
.top-bar.collapsed{ left:84px; }
.main-content.collapsed{ margin-left:84px; }
.content-header{ margin-bottom:1.75rem; }
.content-header h2{ font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; }
.content-header p{ color:var(--text-secondary); font-size:0.92rem; margin-top:0.3rem; }
.stats-row{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:1.1rem 1.25rem; box-shadow:var(--shadow); display:flex; align-items:center; gap:0.9rem; }
.stat-icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.stat-value{ font-size:1.4rem; font-weight:800; line-height:1; font-family:'Space Grotesk',sans-serif; }
.stat-label{ font-size:0.75rem; color:var(--text-secondary); margin-top:0.2rem; font-weight:600; }
.stat-litros .stat-icon{ background:var(--info-light); color:var(--info); }
.stat-gasto .stat-icon{ background:var(--success-light); color:var(--success); }
.stat-cargas .stat-icon{ background:#eef1f5; color:var(--secondary); }
.stat-alertas .stat-icon{ background:var(--primary-light); color:var(--primary); }
.panels-wrap{
    display:grid; gap:1.5rem; align-items:start;
    grid-template-columns:380px 1fr;
    grid-template-areas:
        "formulario listado"
        "formulario alertas";
}
.panel[data-panel="formulario"]{ grid-area:formulario; }
.panel[data-panel="listado"]{ grid-area:listado; }
.panel[data-panel="alertas"]{ grid-area:alertas; }
@media (max-width:980px){
    .panels-wrap{ grid-template-columns:1fr; grid-template-areas:"formulario" "listado" "alertas"; }
}
.panel-scroll{ max-height:420px; overflow-y:auto; padding-right:0.3rem; }
.panel-scroll::-webkit-scrollbar{ width:7px; }
.panel-scroll::-webkit-scrollbar-thumb{ background:#d7d9e0; border-radius:10px; }
.panel-scroll::-webkit-scrollbar-thumb:hover{ background:#c3c5cf; }
@keyframes panelFadeIn{ from{ opacity:0; transform:translateX(28px); } to{ opacity:1; transform:translateX(0); } }
.panel{ animation:panelFadeIn .38s cubic-bezier(.16,1,.3,1) backwards; }
.panel:nth-child(2){ animation-delay:.05s; }
.panel:nth-child(3){ animation-delay:.1s; }
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
.form-input:focus, .form-select:focus, .form-textarea:focus{ outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light); }
.form-input.campo-invalido, .form-select.campo-invalido, .form-textarea.campo-invalido{ border-color:var(--danger); background:var(--danger-light); }
.form-input.campo-invalido:focus, .form-select.campo-invalido:focus, .form-textarea.campo-invalido:focus{ box-shadow:0 0 0 4px var(--danger-light); }
.form-error-hint{ font-size:0.76rem; color:var(--danger); margin-top:0.35rem; font-weight:600; display:none; align-items:center; gap:0.35rem; }
.form-error-hint.visible{ display:flex; }
.form-textarea{ min-height:70px; resize:vertical; }
.form-hint{ font-size:0.74rem; color:var(--text-secondary); margin-top:0.35rem; }

/* Combobox de vehículo: buscador + lista desplegable con altura limitada,
   para que no tape el resto de los campos del formulario. */
.combo-select{ position:relative; }
.combo-select-trigger{
    width:100%; display:flex; align-items:center; justify-content:space-between; gap:0.6rem;
    padding:0.8rem 1rem; border:1.5px solid var(--border); border-radius:11px; font-size:0.93rem;
    background:#fafafa; color:var(--text-primary); font-family:inherit; cursor:pointer;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.combo-select-trigger:hover{ border-color:#c7cad1; }
.combo-select.open .combo-select-trigger{ border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light); }
.combo-select.invalid .combo-select-trigger{ border-color:var(--danger); box-shadow:0 0 0 4px var(--danger-light); }
.combo-select-value{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:left; }
.combo-select-value.placeholder{ color:#9ca3af; }
.combo-select-arrow{ font-size:0.75rem; color:var(--text-secondary); flex-shrink:0; transition:transform .15s ease; }
.combo-select.open .combo-select-arrow{ transform:rotate(180deg); color:var(--primary); }
.combo-select-panel{
    display:none; position:absolute; top:calc(100% + 6px); left:0; right:0; z-index:60;
    background:var(--bg-card); border:1px solid var(--border); border-radius:13px;
    box-shadow:0 14px 32px rgba(16,24,40,0.18); overflow:hidden;
}
.combo-select.open .combo-select-panel{ display:block; }
.combo-select-search{ position:relative; padding:0.6rem; border-bottom:1px solid var(--border); }
.combo-select-search i{ position:absolute; left:1.15rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:0.8rem; }
.combo-select-search input{ width:100%; padding:0.55rem 0.7rem 0.55rem 2rem; border:1.5px solid var(--border); border-radius:9px; font-size:0.85rem; background:#fafafa; font-family:inherit; color:var(--text-primary); }
.combo-select-search input:focus{ outline:none; border-color:var(--primary); background:#fff; }
.combo-select-options{ max-height:220px; overflow-y:auto; padding:0.4rem; }
.combo-select-options::-webkit-scrollbar{ width:7px; }
.combo-select-options::-webkit-scrollbar-thumb{ background:#d7d9e0; border-radius:10px; }
.combo-select-option{
    display:flex; flex-direction:column; gap:0.1rem; padding:0.65rem 0.75rem; border-radius:9px;
    cursor:pointer; transition:background .12s ease;
}
.combo-select-option:hover, .combo-select-option.active{ background:var(--primary-light); }
.combo-select-option.selected{ background:var(--primary-light); }
.combo-select-option .op-placa{ font-weight:700; font-size:0.88rem; }
.combo-select-option .op-detalle{ font-size:0.74rem; color:var(--text-secondary); }
.combo-select-option.selected .op-placa{ color:var(--primary-dark); }
.combo-select-empty{ padding:1rem 0.75rem; text-align:center; font-size:0.82rem; color:var(--text-secondary); }
.combo-select-group-label{
    position:sticky; top:0; z-index:1; background:var(--bg-card); padding:0.55rem 0.75rem 0.3rem;
    font-size:0.66rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-secondary);
    display:flex; align-items:center; gap:0.4rem;
}
.combo-select-group-label:not(:first-child){ margin-top:0.2rem; border-top:1px solid #f1f2f4; padding-top:0.6rem; }
.combo-select-group-label .zona-dot{ width:6px; height:6px; border-radius:50%; background:var(--primary); flex-shrink:0; }
.combo-select-group-label.zona-norte .zona-dot{ background:var(--info); }
.combo-select-group-label.zona-centro .zona-dot{ background:var(--warning); }
.combo-select-group-label.zona-sur .zona-dot{ background:var(--success); }
.combo-select-group-label.zona-motorizadas .zona-dot{ background:var(--primary); }
.combo-select-group-label.zona-sin-zona .zona-dot{ background:var(--secondary); }
.combo-select-option .op-zona-tag{
    display:inline-block; margin-top:0.15rem; font-size:0.66rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.03em; color:var(--text-secondary);
}
body.dark-mode .combo-select-trigger, body.dark-mode .combo-select-search input{ background:#12141a; }
.table-container{ overflow-x:auto; overflow-y:auto; max-height:460px; border-radius:14px; border:1px solid var(--border); }
.table-container thead th{ position:sticky; top:0; z-index:1; }
table{ width:100%; border-collapse:collapse; }
th{ text-align:left; padding:0.85rem 1.1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); background:#f9fafb; border-bottom:1px solid var(--border); font-weight:700; }
td{ padding:0.95rem 1.1rem; border-bottom:1px solid #f1f2f4; font-size:0.88rem; }
tbody tr:last-child td{ border-bottom:none; }
tbody tr{ transition:background .1s ease; }
tbody tr:hover td{ background:#faf9ff; }
.plate-chip{ display:inline-flex; align-items:center; gap:0.55rem; font-weight:700; }
.plate-icon{ width:32px; height:32px; border-radius:9px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:0.85rem; }
.rendimiento-celda{ font-size:0.82rem; line-height:1.35; }
.rendimiento-valor{ font-weight:700; }
.rendimiento-meta{ color:var(--text-secondary); font-size:0.74rem; }
.variacion-pill{ display:inline-flex; align-items:center; gap:0.35rem; padding:0.28rem 0.65rem; border-radius:20px; font-size:0.74rem; font-weight:700; white-space:nowrap; }
.variacion-pill.nivel-0{ background:var(--success-light); color:#0f766e; }
.variacion-pill.nivel-1{ background:var(--warning-light); color:#b45309; }
.variacion-pill.nivel-2{ background:#ffedd5; color:#9a3412; }
.variacion-pill.nivel-3{ background:var(--danger-light); color:#be123c; }
.search-wrap{ position:relative; margin-bottom:1rem; }
.search-wrap i{ position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:0.85rem; }
.search-input{ width:100%; padding:0.75rem 1rem 0.75rem 2.6rem; border:1.5px solid var(--border); border-radius:11px; background:#fafafa; font-size:0.9rem; transition:border-color .15s ease, box-shadow .15s ease; }
.search-input:focus{ outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light); }
.empty-row td, .error-row td{ text-align:center; padding:3rem 1rem; color:var(--text-secondary); }
.error-row td{ color:var(--danger); }
.modal-overlay{ display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:200; align-items:center; justify-content:center; padding:1.5rem; }
.modal-overlay.active{ display:flex; }
.modal-box{ background:var(--bg-card); border-radius:var(--radius); padding:1.6rem; width:100%; max-width:520px; max-height:88vh; overflow-y:auto; }
.modal-box h3{ font-size:1.1rem; font-weight:800; margin-bottom:0.2rem; display:flex; align-items:center; gap:0.55rem; }
.modal-box .modal-sub{ font-size:0.82rem; color:var(--text-secondary); margin-bottom:1.2rem; }
.modal-close{ float:right; background:none; border:none; font-size:1.1rem; color:var(--text-secondary); cursor:pointer; }

/* ===== Reporte de combustible por zona ===== */
.modal-box-wide{ max-width:1220px; }
.reporte-header{ display:flex; align-items:flex-start; gap:0.9rem; margin-bottom:1.4rem; }
.reporte-header .reporte-header-icon{
    width:46px; height:46px; border-radius:13px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
    font-size:1.15rem; color:#fff; background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    box-shadow:0 6px 16px rgba(220,38,38,0.3);
}
.reporte-header h3{ margin-bottom:0.15rem; }

.reporte-toolbar{
    display:flex; flex-wrap:wrap; align-items:flex-end; gap:1rem;
    background:#f9fafb; border:1px solid var(--border); border-radius:14px;
    padding:0.9rem 1.1rem; margin-bottom:1.3rem;
}
.reporte-toolbar .campo{ display:flex; flex-direction:column; gap:0.32rem; }
.reporte-toolbar .campo label{ font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); }
.reporte-toolbar .campo input{ padding:0.6rem 0.8rem; border:1.5px solid var(--border); border-radius:9px; font-size:0.85rem; background:#fff; font-family:inherit; transition:border-color .15s ease, box-shadow .15s ease; }
.reporte-toolbar .campo input:focus{ outline:none; border-color:var(--primary); box-shadow:0 0 0 4px var(--primary-light); }
.reporte-toolbar .campo-accion{ margin-left:auto; }

.reporte-tabs{ display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.3rem; }
.reporte-tab{
    display:inline-flex; align-items:center; gap:0.5rem; padding:0.6rem 1.05rem; border-radius:30px;
    border:1.5px solid var(--border); background:#fff; font-size:0.82rem; font-weight:700; cursor:pointer;
    color:var(--text-secondary); transition:all .15s ease;
}
.reporte-tab .cnt{ background:#eef1f5; color:var(--text-secondary); font-size:0.68rem; font-weight:800; padding:0.12rem 0.5rem; border-radius:20px; }
.reporte-tab:hover{ border-color:var(--primary-soft); color:var(--primary-dark); }
.reporte-tab.active{ background:linear-gradient(135deg,var(--primary),var(--primary-accent)); border-color:transparent; color:#fff; box-shadow:0 4px 12px rgba(220,38,38,0.28); }
.reporte-tab.active .cnt{ background:rgba(255,255,255,0.28); color:#fff; }

.reporte-kpis{ display:grid; grid-template-columns:repeat(auto-fit,minmax(148px,1fr)); gap:0.85rem; margin-bottom:1.4rem; }
.kpi-card{ display:flex; align-items:center; gap:0.75rem; background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:0.85rem 1rem; box-shadow:var(--shadow); }
.kpi-icon{ width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:0.92rem; flex-shrink:0; }
.kpi-cargas .kpi-icon{ background:#eef1f5; color:var(--secondary); }
.kpi-litros .kpi-icon{ background:var(--info-light); color:var(--info); }
.kpi-galones .kpi-icon{ background:#ede9fe; color:#7c3aed; }
.kpi-gasto .kpi-icon{ background:var(--success-light); color:var(--success); }
.kpi-km .kpi-icon{ background:var(--warning-light); color:var(--warning); }
.kpi-rendimiento .kpi-icon{ background:var(--primary-light); color:var(--primary); }
.kpi-value{ font-size:1.15rem; font-weight:800; font-family:'Space Grotesk',sans-serif; line-height:1.15; white-space:nowrap; }
.kpi-label{ font-size:0.66rem; color:var(--text-secondary); font-weight:700; text-transform:uppercase; letter-spacing:0.03em; margin-top:0.15rem; }

.reporte-tabla-wrap{ overflow:auto; border:1px solid var(--border); border-radius:14px; max-height:46vh; box-shadow:var(--shadow); }
.reporte-tabla{ min-width:1080px; width:100%; border-collapse:collapse; }
.reporte-tabla thead th{
    position:sticky; top:0; z-index:2; background:#f9fafb; white-space:nowrap;
    padding:0.8rem 0.95rem; font-size:0.66rem; text-transform:uppercase; letter-spacing:0.04em;
    color:var(--text-secondary); font-weight:800; border-bottom:1px solid var(--border);
}
.reporte-tabla thead th.num{ text-align:right; }
.reporte-tabla tbody td{ padding:0.75rem 0.95rem; font-size:0.82rem; white-space:nowrap; border-bottom:1px solid #f1f2f4; }
.reporte-tabla tbody tr:nth-child(even) td{ background:#fbfbfd; }
.reporte-tabla tbody tr:hover td{ background:var(--primary-light); }
.reporte-tabla td.num{ text-align:right; font-variant-numeric:tabular-nums; font-feature-settings:'tnum'; }
.reporte-tabla td.obs{ max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-secondary); }
.reporte-tabla .placa-chip{ display:inline-flex; align-items:center; gap:0.5rem; font-weight:700; }
.reporte-tabla .placa-chip .dot{ width:8px; height:8px; border-radius:50%; background:var(--primary); flex-shrink:0; }
.reporte-tabla tfoot td{
    font-weight:800; background:#f4f5f9; border-top:2px solid var(--primary);
    position:sticky; bottom:0; z-index:1; padding:0.85rem 0.95rem; font-size:0.82rem;
}
.reporte-tabla tfoot td.num{ text-align:right; }

.reporte-acciones{ display:flex; justify-content:flex-end; margin-top:1.2rem; }
.historial-item{ display:flex; justify-content:space-between; align-items:center; padding:0.7rem 0; border-bottom:1px solid #f1f2f4; font-size:0.85rem; gap:0.5rem; }
.historial-item:last-child{ border-bottom:none; }
.historial-item .h-valor{ font-weight:700; }
.historial-item .h-meta{ font-size:0.74rem; color:var(--text-secondary); }
.alerta-item{ display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:0.85rem 0; border-bottom:1px solid #f1f2f4; }
.alerta-item:last-child{ border-bottom:none; }
.alerta-info .alerta-placa{ font-weight:700; font-size:0.9rem; }
.alerta-info .alerta-meta{ font-size:0.76rem; color:var(--text-secondary); margin-top:0.1rem; }
.toast-container{ position:fixed; top:80px; right:1.5rem; z-index:300; display:flex; flex-direction:column; gap:0.6rem; max-width:340px; }
.toast{ display:flex; align-items:flex-start; gap:0.7rem; background:var(--bg-card); border:1px solid var(--border); border-left:4px solid var(--warning); border-radius:12px; padding:0.85rem 1rem; box-shadow:var(--shadow-md); animation:toastIn .2s ease; }
.toast.nivel-2{ border-left-color:#c2410c; }
.toast.nivel-3{ border-left-color:var(--danger); }
.toast.toast-out{ opacity:0; transform:translateX(20px); transition:opacity .2s ease, transform .2s ease; }
@keyframes toastIn{ from{ opacity:0; transform:translateX(20px); } to{ opacity:1; transform:translateX(0); } }
.toast-icon{ color:var(--warning); font-size:1.1rem; margin-top:0.1rem; }
.toast.nivel-2 .toast-icon{ color:#c2410c; }
.toast.nivel-3 .toast-icon{ color:var(--danger); }
.toast-title{ font-weight:700; font-size:0.86rem; }
.toast-msg{ font-size:0.78rem; color:var(--text-secondary); margin-top:0.15rem; }
.toast-close{ margin-left:auto; background:none; border:none; color:var(--text-secondary); cursor:pointer; }
body.dark-mode{ --bg-app:#12141a; --bg-card:#1b1e26; --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038; }
body.dark-mode .sidebar-toggle-btn{ background:#232733; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .form-input, body.dark-mode .form-select, body.dark-mode .form-textarea, body.dark-mode .search-input{ background:#12141a; }
body.dark-mode .reporte-toolbar{ background:#181b23; }
body.dark-mode .reporte-toolbar .campo input{ background:#12141a; color:var(--text-primary); }
body.dark-mode .reporte-tab{ background:#1b1e26; }
body.dark-mode .reporte-tabla thead th{ background:#1b1e26; }
body.dark-mode .reporte-tabla tbody tr:nth-child(even) td{ background:#20242e; }
body.dark-mode .reporte-tabla tfoot td{ background:#20242e; }
</style>
</head>
<body>

<div class="toast-container" id="toast-container"></div>

<div class="top-bar">
    <div class="top-bar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Contraer/expandir menú">
            <i class="fas fa-bars"></i>
        </button>
        <button class="sidebar-toggle-btn" onclick="toggleDarkMode()" title="Modo oscuro/claro">
            <i class="fas fa-moon" id="theme-toggle-icon"></i>
        </button>
        <strong>Combustible</strong>
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
            <div class="logo-text"><h1>FORZA</h1><p>COMBUSTIBLE · HN</p></div>
        </div>
    </div>
    <nav class="nav-menu">
        <div class="nav-section-label">Principal</div>
        <a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
        <a class="nav-item" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>

        <div class="nav-section-label">Gestión de flota</div>
        <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Crear Piloto</span></a>
        <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Crear Vehículo</span></a>
        <a class="nav-item active" href="combustible.php"><i class="fas fa-gas-pump"></i><span>Combustible</span><span class="nav-badge" id="nav-alertas-combustible">0</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-alert-widget" id="sidebar-alert-widget">
            <div class="icon ok" id="sidebar-alert-icon"><i class="fas fa-gas-pump"></i></div>
            <div>
                <div class="txt-value" id="sidebar-alert-value">0</div>
                <div class="txt-label">Vehículos derrochando combustible</div>
            </div>
        </div>
    </div>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <h2>Combustible</h2>
        <p>Registra cada carga y detecta qué vehículo está rindiendo menos que su propio promedio</p>
    </div>

    <div class="stats-row">
        <div class="stat-card stat-cargas">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div><div class="stat-value" id="stat-cargas">0</div><div class="stat-label">Cargas registradas</div></div>
        </div>
        <div class="stat-card stat-litros">
            <div class="stat-icon"><i class="fas fa-droplet"></i></div>
            <div><div class="stat-value" id="stat-litros">0</div><div class="stat-label">Litros totales</div></div>
        </div>
        <div class="stat-card stat-gasto">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div><div class="stat-value" id="stat-gasto">L. 0</div><div class="stat-label">Gasto total</div></div>
        </div>
        <div class="stat-card stat-alertas">
            <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div><div class="stat-value" id="stat-alertas">0</div><div class="stat-label">En alerta</div></div>
        </div>
    </div>

    <div class="panels-wrap" id="panels-wrap">
        <!-- FORMULARIO DE REGISTRO -->
        <div class="card panel" data-panel="formulario">
            <div class="card-title"><span class="title-icon"><i class="fas fa-gas-pump"></i></span> Registrar carga</div>
            <form id="form-combustible" onsubmit="guardarCombustible(event)">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-car"></i> Vehículo</label>
                    <div class="combo-select" id="combo-vehiculo">
                        <button type="button" class="combo-select-trigger" id="combo-vehiculo-trigger" onclick="toggleComboVehiculo()">
                            <span class="combo-select-value placeholder" id="combo-vehiculo-value">Selecciona un vehículo</span>
                            <i class="fas fa-chevron-down combo-select-arrow"></i>
                        </button>
                        <div class="combo-select-panel" id="combo-vehiculo-panel">
                            <div class="combo-select-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="combo-vehiculo-search" placeholder="Buscar por placa, marca o modelo..." oninput="filtrarComboVehiculo()" onclick="event.stopPropagation()">
                            </div>
                            <div class="combo-select-options" id="combo-vehiculo-options">
                                <div class="combo-select-empty">Cargando vehículos...</div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="comb-vehiculo" required>
                </div>

                <div class="form-group" id="grupo-km-inicial" style="display:none;">
                    <label class="form-label"><i class="fas fa-gauge"></i> Kilometraje inicial</label>
                    <input type="number" class="form-input" id="comb-km-inicial" min="0" step="1" required placeholder="Primera carga: km con el que arrancó" oninput="validarKmFinal()">
                    <div class="form-hint">Este vehículo no tiene cargas previas, así que se necesita el km inicial una sola vez.</div>
                </div>
                <div class="form-hint" id="hint-km-automatico" style="margin-bottom:1.15rem; display:none;"></div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-gauge-high"></i> Kilometraje actual</label>
                    <input type="number" class="form-input" id="comb-km-final" min="0" step="1" required placeholder="Ej: 31648" oninput="validarKmFinal()">
                    <div class="form-error-hint" id="hint-km-final-error"><i class="fas fa-triangle-exclamation"></i> <span></span></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-droplet"></i> Litros cargados</label>
                        <input type="number" class="form-input" id="comb-litros" min="0.01" step="any" required placeholder="Ej: 8.43">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-coins"></i> Monto (Lps)</label>
                        <input type="number" class="form-input" id="comb-monto" min="0" step="any" placeholder="Opcional">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-gauge-simple"></i> Nivel al iniciar</label>
                        <select class="form-select" id="comb-nivel-inicio">
                            <option value="">—</option>
                            <option value="Vacío">Vacío</option>
                            <option value="1/4">1/4</option>
                            <option value="2/4">2/4</option>
                            <option value="3/4">3/4</option>
                            <option value="TANQUE LLENO">Tanque lleno</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-gauge-simple-high"></i> Nivel de recarga</label>
                        <select class="form-select" id="comb-nivel-recarga">
                            <option value="">—</option>
                            <option value="1/4">1/4</option>
                            <option value="2/4">2/4</option>
                            <option value="3/4">3/4</option>
                            <option value="TANQUE LLENO">Tanque lleno</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-note-sticky"></i> Observaciones</label>
                    <textarea class="form-textarea" id="comb-observaciones" placeholder="Opcional"></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar carga</button>
            </form>
        </div>

        <!-- RANKING / LISTADO -->
        <div class="card panel" data-panel="listado">
            <div class="card-title" style="justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:0.6rem;"><span class="title-icon"><i class="fas fa-ranking-star"></i></span> Rendimiento por vehículo</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="abrirModalReporteZona()"><i class="fas fa-file-export"></i> Reporte por zona</button>
            </div>
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="buscador" placeholder="Buscar por placa, marca o piloto..." oninput="renderRanking()">
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Piloto</th>
                            <th>Promedio km/l</th>
                            <th>Última carga</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-ranking-body">
                        <tr class="empty-row"><td colspan="6">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ALERTAS -->
        <div class="card panel" data-panel="alertas">
            <div class="card-title"><span class="title-icon"><i class="fas fa-triangle-exclamation"></i></span> Vehículos derrochando combustible</div>
            <div class="panel-scroll" id="lista-alertas">
                <div style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;">Cargando...</div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL: historial de un vehículo -->
<div class="modal-overlay" id="modal-historial">
    <div class="modal-box">
        <button class="modal-close" onclick="cerrarModalHistorial()"><i class="fas fa-xmark"></i></button>
        <h3><i class="fas fa-clock-rotate-left"></i> Historial — <span id="historial-placa-titulo"></span></h3>
        <div class="modal-sub">Todas las cargas registradas para este vehículo</div>
        <div id="historial-lista">Cargando...</div>
    </div>
</div>

<!-- MODAL: reporte de combustible agrupado por zona -->
<div class="modal-overlay" id="modal-reporte-zona">
    <div class="modal-box modal-box-wide">
        <button class="modal-close" onclick="cerrarModalReporteZona()"><i class="fas fa-xmark"></i></button>

        <div class="reporte-header">
            <div class="reporte-header-icon"><i class="fas fa-map-location-dot"></i></div>
            <div>
                <h3 style="margin-bottom:0.25rem;">Reporte de combustible por zona</h3>
                <div class="modal-sub" style="margin-bottom:0;">Igual que tus pestañas de Excel — Norte, Centro, Sur y Motorizadas, con totales por zona</div>
            </div>
        </div>

        <div class="reporte-toolbar">
            <div class="campo">
                <label>Desde</label>
                <input type="date" id="reporte-fecha-desde" onchange="cargarReporteZona()">
            </div>
            <div class="campo">
                <label>Hasta</label>
                <input type="date" id="reporte-fecha-hasta" onchange="cargarReporteZona()">
            </div>
            <div class="campo campo-accion">
                <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarFiltrosReporteZona()"><i class="fas fa-rotate-left"></i> Limpiar filtro</button>
            </div>
        </div>

        <div class="reporte-tabs" id="reporte-tabs">Cargando zonas...</div>
        <div class="reporte-kpis" id="reporte-resumen"></div>

        <div class="reporte-tabla-wrap">
            <table class="reporte-tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Placa</th>
                        <th>Marca / Modelo</th>
                        <th>Piloto</th>
                        <th class="num">Km inicial</th>
                        <th class="num">Km final</th>
                        <th class="num">Km recorrido</th>
                        <th>Nivel que inicia</th>
                        <th>Nivel antes de recarga</th>
                        <th class="num">Litros cargados</th>
                        <th class="num">Galones cargados</th>
                        <th class="num">Cantidad (Lps)</th>
                        <th class="num">Km por litro</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody id="reporte-tabla-body">
                    <tr class="empty-row"><td colspan="14">Cargando...</td></tr>
                </tbody>
                <tfoot id="reporte-tabla-foot"></tfoot>
            </table>
        </div>

        <div class="reporte-acciones">
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="descargarCsvReporteZona()"><i class="fas fa-file-excel"></i> Descargar Excel detallado (todas las zonas)</button>
        </div>
    </div>
</div>


<script>
let vehiculos = [];
let ranking = [];
let alertas = [];
let ultimoKmVehiculoActual = null; // Último km registrado del vehículo elegido (null = primera carga)

// Escapa texto antes de insertarlo en innerHTML (placas, nombres, observaciones, etc.
// vienen de la base de datos / entrada de usuario y no deben inyectarse como HTML crudo).
function escapeHtml(valor) {
    if (valor === null || valor === undefined) return '';
    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Igual que JSON.stringify, pero además escapa comillas simples para poder
// insertarse dentro de un atributo onclick='...' sin que datos como la placa
// o el nombre del piloto puedan "romper" el atributo e inyectar HTML/JS.
function attrJsString(valor) {
    return JSON.stringify(valor ?? '').replace(/'/g, '\\u0027').replace(/</g, '\\u003c');
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('top-bar')?.classList.toggle('collapsed');
    document.querySelector('.top-bar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
}

function actualizarIconoTema() {
    if (localStorage.getItem('forza-theme') === 'dark') document.body.classList.add('dark-mode');
    const icono = document.getElementById('theme-toggle-icon');
    if (icono) icono.className = document.body.classList.contains('dark-mode') ? 'fas fa-sun' : 'fas fa-moon';
}
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('forza-theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    actualizarIconoTema();
}
actualizarIconoTema();

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

async function cargarVehiculos() {
    const opciones = document.getElementById('combo-vehiculo-options');
    try {
        const res = await fetch('api/vehiculo_listar.php');
        const data = await res.json();
        vehiculos = Array.isArray(data) ? data : [];
        renderComboVehiculo(vehiculos);
    } catch (err) {
        console.error('No se pudo cargar la lista de vehículos', err);
        opciones.innerHTML = '<div class="combo-select-empty">Error al cargar vehículos</div>';
    }
}

function etiquetaVehiculo(v) {
    return { placa: v.placa, detalle: [v.marca, v.modelo].filter(Boolean).join(' ') };
}

// Intenta leer la zona del vehículo desde distintos nombres de campo posibles,
// por si la API la llama diferente (zona, zone, division, region).
function zonaVehiculo(v) {
    const cruda = (v.zona ?? v.zone ?? v.division ?? v.region ?? '').toString().trim();
    return cruda || 'Sin zona';
}

// Orden preferido de zonas; cualquier otra zona no listada aquí aparece
// después, en orden alfabético, y "Sin zona" siempre queda al final.
const ORDEN_ZONAS = ['Norte', 'Centro', 'Sur', 'Motorizadas'];

function slugZona(zona) {
    return zona.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'sin-zona';
}

function agruparPorZona(lista) {
    const grupos = new Map();
    lista.forEach(v => {
        const zona = zonaVehiculo(v);
        if (!grupos.has(zona)) grupos.set(zona, []);
        grupos.get(zona).push(v);
    });
    const zonas = Array.from(grupos.keys());
    zonas.sort((a, b) => {
        if (a === 'Sin zona') return 1;
        if (b === 'Sin zona') return -1;
        const ia = ORDEN_ZONAS.indexOf(a), ib = ORDEN_ZONAS.indexOf(b);
        if (ia !== -1 && ib !== -1) return ia - ib;
        if (ia !== -1) return -1;
        if (ib !== -1) return 1;
        return a.localeCompare(b, 'es');
    });
    return zonas.map(zona => ({ zona, items: grupos.get(zona) }));
}

function renderComboVehiculo(lista) {
    const opciones = document.getElementById('combo-vehiculo-options');
    const valorActual = document.getElementById('comb-vehiculo').value;
    if (lista.length === 0) {
        opciones.innerHTML = '<div class="combo-select-empty">No se encontraron vehículos</div>';
        return;
    }
    const grupos = agruparPorZona(lista);
    opciones.innerHTML = grupos.map(({ zona, items }) => `
        <div class="combo-select-group-label zona-${slugZona(zona)}"><span class="zona-dot"></span>${zona} · ${items.length}</div>
        ${items.map(v => {
            const { placa, detalle } = etiquetaVehiculo(v);
            const seleccionado = String(v.id) === String(valorActual) ? ' selected' : '';
            return `
                <div class="combo-select-option${seleccionado}" onclick='seleccionarVehiculoCombo(${v.id})'>
                    <span class="op-placa">${escapeHtml(placa)}</span>
                    ${detalle ? `<span class="op-detalle">${escapeHtml(detalle)}</span>` : ''}
                </div>
            `;
        }).join('')}
    `).join('');
}

function toggleComboVehiculo(forzarCerrado) {
    const combo = document.getElementById('combo-vehiculo');
    const abrir = forzarCerrado === false ? false : !combo.classList.contains('open');
    combo.classList.toggle('open', abrir);
    if (abrir) {
        combo.classList.remove('invalid');
        const buscador = document.getElementById('combo-vehiculo-search');
        buscador.value = '';
        renderComboVehiculo(vehiculos);
        setTimeout(() => buscador.focus(), 0);
    }
}

function filtrarComboVehiculo() {
    const texto = document.getElementById('combo-vehiculo-search').value.trim().toLowerCase();
    if (!texto) { renderComboVehiculo(vehiculos); return; }
    const filtrados = vehiculos.filter(v => {
        const { placa, detalle } = etiquetaVehiculo(v);
        return (placa + ' ' + detalle + ' ' + zonaVehiculo(v)).toLowerCase().includes(texto);
    });
    renderComboVehiculo(filtrados);
}

function seleccionarVehiculoCombo(id) {
    const v = vehiculos.find(x => String(x.id) === String(id));
    if (!v) return;
    const { placa, detalle } = etiquetaVehiculo(v);
    const zona = zonaVehiculo(v);
    document.getElementById('comb-vehiculo').value = v.id;
    const valorEl = document.getElementById('combo-vehiculo-value');
    valorEl.textContent = placa + (detalle ? ' · ' + detalle : '') + (zona !== 'Sin zona' ? ' · ' + zona : '');
    valorEl.classList.remove('placeholder');
    document.getElementById('combo-vehiculo').classList.remove('invalid');
    toggleComboVehiculo(false);
    alCambiarVehiculo();
}

function reiniciarComboVehiculo() {
    document.getElementById('comb-vehiculo').value = '';
    const valorEl = document.getElementById('combo-vehiculo-value');
    valorEl.textContent = 'Selecciona un vehículo';
    valorEl.classList.add('placeholder');
    document.getElementById('combo-vehiculo').classList.remove('invalid', 'open');
}

// Cierra el combobox al hacer clic fuera de él.
document.addEventListener('click', (ev) => {
    const combo = document.getElementById('combo-vehiculo');
    if (combo && !combo.contains(ev.target)) {
        combo.classList.remove('open');
    }
});
document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') {
        document.getElementById('combo-vehiculo')?.classList.remove('open');
    }
});

// Al elegir un vehículo, revisa si ya tiene cargas previas para decidir si se
// pide el kilometraje inicial manualmente o se usa el automático.
async function alCambiarVehiculo() {
    const vehiculoId = document.getElementById('comb-vehiculo').value;
    const grupoKmInicial = document.getElementById('grupo-km-inicial');
    const hintAuto = document.getElementById('hint-km-automatico');
    ultimoKmVehiculoActual = null;
    if (!vehiculoId) {
        grupoKmInicial.style.display = 'none';
        hintAuto.style.display = 'none';
        validarKmFinal();
        return;
    }
    try {
        const res = await fetch('api/combustible_listar.php?vehiculo_id=' + vehiculoId);
        const data = await res.json();
        if (Array.isArray(data) && data.length > 0) {
            ultimoKmVehiculoActual = Number(data[0].km_final);
            grupoKmInicial.style.display = 'none';
            hintAuto.style.display = 'block';
            hintAuto.innerHTML = `<i class="fas fa-circle-info"></i> Km inicial automático: ${ultimoKmVehiculoActual.toLocaleString('es-HN')} km (de la última carga)`;
        } else {
            grupoKmInicial.style.display = 'block';
            hintAuto.style.display = 'none';
        }
    } catch (err) {
        grupoKmInicial.style.display = 'block';
        hintAuto.style.display = 'none';
    }
    validarKmFinal();
}

// El kilometraje actual nunca puede ser igual o menor al último kilometraje
// registrado del vehículo (o al km inicial, si es la primera carga).
function validarKmFinal() {
    const inputFinal = document.getElementById('comb-km-final');
    const hint = document.getElementById('hint-km-final-error');
    const mensaje = hint.querySelector('span');
    const kmFinal = inputFinal.value === '' ? null : Number(inputFinal.value);

    let referencia = ultimoKmVehiculoActual;
    let etiquetaReferencia = 'el último kilometraje registrado';
    if (referencia === null) {
        const kmInicialInput = document.getElementById('comb-km-inicial');
        referencia = kmInicialInput.value === '' ? null : Number(kmInicialInput.value);
        etiquetaReferencia = 'el kilometraje inicial ingresado';
    }

    if (kmFinal === null || referencia === null) {
        inputFinal.classList.remove('campo-invalido');
        hint.classList.remove('visible');
        return true;
    }

    if (kmFinal <= referencia) {
        inputFinal.classList.add('campo-invalido');
        mensaje.textContent = `El kilometraje actual debe ser mayor a ${referencia.toLocaleString('es-HN')} km (${etiquetaReferencia}).`;
        hint.classList.add('visible');
        return false;
    }

    inputFinal.classList.remove('campo-invalido');
    hint.classList.remove('visible');
    return true;
}

async function guardarCombustible(event) {
    event.preventDefault();

    const vehiculoId = document.getElementById('comb-vehiculo').value;
    if (!vehiculoId) {
        document.getElementById('combo-vehiculo').classList.add('invalid');
        document.getElementById('combo-vehiculo-trigger').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (!validarKmFinal()) {
        document.getElementById('comb-km-final').scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('comb-km-final').focus();
        return;
    }

    const fd = new FormData();
    fd.append('vehiculo_id', vehiculoId);
    fd.append('fecha', fechaLocalHN());
    fd.append('km_inicial', document.getElementById('comb-km-inicial').value);
    fd.append('km_final', document.getElementById('comb-km-final').value);
    fd.append('litros_cargados', document.getElementById('comb-litros').value);
    fd.append('monto_lempiras', document.getElementById('comb-monto').value);
    fd.append('nivel_inicio', document.getElementById('comb-nivel-inicio').value);
    fd.append('nivel_recarga', document.getElementById('comb-nivel-recarga').value);
    fd.append('observaciones', document.getElementById('comb-observaciones').value);

    try {
        const res = await fetch('api/combustible_guardar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('form-combustible').reset();
            reiniciarComboVehiculo();
            document.getElementById('grupo-km-inicial').style.display = 'none';
            document.getElementById('hint-km-automatico').style.display = 'none';
            document.getElementById('comb-km-final').classList.remove('campo-invalido');
            document.getElementById('hint-km-final-error').classList.remove('visible');
            ultimoKmVehiculoActual = null;
            if (data.alerta_combustible) {
                mostrarToastCombustible(data.alerta_combustible);
            }
            await cargarRanking();
        } else {
            alert('❌ ' + (data.message || 'Error al registrar la carga'));
        }
    } catch (err) {
        alert('❌ Error de conexión al registrar la carga');
    }
}

function mostrarToastCombustible(alerta) {
    const cont = document.getElementById('toast-container');
    if (!cont) return;
    const ICONO = { aviso: 'fa-gas-pump', urgente: 'fa-triangle-exclamation', critico: 'fa-circle-exclamation' };
    const TITULO = { aviso: 'Rendimiento bajo', urgente: 'Rendimiento urgente', critico: 'Posible fuga o robo de combustible' };
    const toast = document.createElement('div');
    toast.className = `toast nivel-${alerta.nivel}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${ICONO[alerta.tipo] || 'fa-gas-pump'}"></i></div>
        <div class="toast-body">
            <div class="toast-title">${TITULO[alerta.tipo] || 'Alerta de combustible'}</div>
            <div class="toast-msg">${alerta.mensaje}</div>
        </div>
        <button type="button" class="toast-close" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
    `;
    const cerrar = () => { toast.classList.add('toast-out'); setTimeout(() => toast.remove(), 200); };
    toast.querySelector('.toast-close').addEventListener('click', cerrar);
    cont.appendChild(toast);
    setTimeout(cerrar, alerta.nivel >= 2 ? 12000 : 7000);
}

async function cargarRanking() {
    try {
        const res = await fetch('api/combustible_listar.php');
        const data = await res.json();
        if (!Array.isArray(data)) {
            document.getElementById('tabla-ranking-body').innerHTML =
                `<tr class="error-row"><td colspan="6">${data.message || 'Error al cargar el rendimiento'}</td></tr>`;
            return;
        }
        ranking = data;
        alertas = data.filter(r => r.nivel > 0);
        actualizarStats();
        renderRanking();
        renderAlertas();
    } catch (err) {
        document.getElementById('tabla-ranking-body').innerHTML = '<tr class="error-row"><td colspan="6">Error de conexión</td></tr>';
    }
}

function actualizarStats() {
    const totalCargas = ranking.reduce((s, r) => s + r.total_cargas, 0);
    const totalLitros = ranking.reduce((s, r) => s + (r.total_litros || 0), 0);
    const totalLempiras = ranking.reduce((s, r) => s + (r.total_lempiras || 0), 0);
    document.getElementById('stat-cargas').textContent = totalCargas.toLocaleString('es-HN');
    document.getElementById('stat-litros').textContent = totalLitros.toLocaleString('es-HN', { maximumFractionDigits: 1 });
    document.getElementById('stat-gasto').textContent = 'L. ' + totalLempiras.toLocaleString('es-HN', { maximumFractionDigits: 0 });
    document.getElementById('stat-alertas').textContent = alertas.length;
    document.getElementById('nav-alertas-combustible').textContent = alertas.length;
    document.getElementById('sidebar-alert-value').textContent = alertas.length;
    document.getElementById('sidebar-alert-icon').classList.toggle('ok', alertas.length === 0);
}

const ETIQUETA_NIVEL = { aviso: 'Aviso', urgente: 'Urgente', critico: 'Crítico' };

function renderRanking() {
    const texto = document.getElementById('buscador').value.trim().toLowerCase();
    const cont = document.getElementById('tabla-ranking-body');
    const filtrados = ranking.filter(r =>
        !texto || (r.placa + ' ' + (r.marca || '') + ' ' + (r.modelo || '') + ' ' + (r.patrullero_nombre || '')).toLowerCase().includes(texto)
    );
    if (filtrados.length === 0) {
        cont.innerHTML = '<tr class="empty-row"><td colspan="6">Sin cargas de combustible registradas todavía.</td></tr>';
        return;
    }
    cont.innerHTML = filtrados.map(r => `
        <tr>
            <td>
                <div class="plate-chip"><span class="plate-icon"><i class="fas fa-car"></i></span>${escapeHtml(r.placa)}</div>
                <div style="font-size:0.76rem;color:var(--text-secondary);margin-top:0.15rem;">${escapeHtml([r.marca, r.modelo].filter(Boolean).join(' ')) || '—'}</div>
            </td>
            <td>${escapeHtml(r.patrullero_nombre) || '—'}</td>
            <td class="rendimiento-celda">
                <div class="rendimiento-valor">${r.promedio_km_por_litro !== null ? r.promedio_km_por_litro + ' km/l' : '—'}</div>
                <div class="rendimiento-meta">${r.total_cargas} carga${r.total_cargas === 1 ? '' : 's'}</div>
            </td>
            <td class="rendimiento-celda">
                <div class="rendimiento-valor">${r.ultimo_km_por_litro !== null ? r.ultimo_km_por_litro + ' km/l' : '—'}</div>
                <div class="rendimiento-meta">${r.fecha_ultima_carga || ''}</div>
            </td>
            <td>
                ${r.nivel > 0
                    ? `<span class="variacion-pill nivel-${r.nivel}"><i class="fas fa-arrow-trend-down"></i> ${ETIQUETA_NIVEL[r.tipo]} · -${r.variacion_pct}%</span>`
                    : `<span class="variacion-pill nivel-0"><i class="fas fa-check"></i> Normal</span>`
                }
            </td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick='abrirModalHistorial(${r.vehiculo_id}, ${attrJsString(r.placa)})' title="Ver historial"><i class="fas fa-clock-rotate-left"></i></button>
            </td>
        </tr>
    `).join('');
}

function renderAlertas() {
    const cont = document.getElementById('lista-alertas');
    if (alertas.length === 0) {
        cont.innerHTML = '<div style="padding:1rem 0;color:var(--text-secondary);font-size:0.85rem;"><i class="fas fa-circle-check" style="color:var(--success);"></i> Ningún vehículo está rindiendo por debajo de su promedio.</div>';
        return;
    }
    cont.innerHTML = alertas.map(a => `
        <div class="alerta-item">
            <div class="alerta-info">
                <div class="alerta-placa">${escapeHtml(a.placa)}${a.marca || a.modelo ? ' · ' + escapeHtml([a.marca, a.modelo].filter(Boolean).join(' ')) : ''}</div>
                <div class="alerta-meta">${a.patrullero_nombre ? 'Piloto: ' + escapeHtml(a.patrullero_nombre) : 'Sin piloto asignado'} · ${a.ultimo_km_por_litro} km/l (promedio: ${a.promedio_km_por_litro} km/l)</div>
            </div>
            <span class="variacion-pill nivel-${a.nivel}">${ETIQUETA_NIVEL[a.tipo]} · -${a.variacion_pct}%</span>
        </div>
    `).join('');
}

async function abrirModalHistorial(vehiculoId, placa) {
    document.getElementById('historial-placa-titulo').textContent = placa;
    document.getElementById('modal-historial').classList.add('active');
    const cont = document.getElementById('historial-lista');
    cont.innerHTML = 'Cargando...';
    try {
        const res = await fetch('api/combustible_listar.php?vehiculo_id=' + vehiculoId);
        const data = await res.json();
        if (!Array.isArray(data) || data.length === 0) {
            cont.innerHTML = '<div style="padding:0.5rem 0;color:var(--text-secondary);font-size:0.85rem;">Sin cargas registradas todavía.</div>';
            return;
        }
        cont.innerHTML = data.map(h => `
            <div class="historial-item">
                <div>
                    <div class="h-valor">${h.km_por_litro !== null ? h.km_por_litro + ' km/l' : '—'} <span style="font-weight:400;color:var(--text-secondary);">(${h.km_recorridos} km, ${h.litros_cargados} L)</span></div>
                    <div class="h-meta">${h.fecha}${h.patrullero_nombre ? ' · ' + escapeHtml(h.patrullero_nombre) : ''}${h.observaciones ? ' · ' + escapeHtml(h.observaciones) : ''}</div>
                </div>
                <button class="btn btn-danger btn-sm" onclick="eliminarCarga(${h.id}, ${vehiculoId}, ${JSON.stringify(placa)})" title="Eliminar registro"><i class="fas fa-trash"></i></button>
            </div>
        `).join('');
    } catch (err) {
        cont.innerHTML = '<div style="padding:0.5rem 0;color:var(--danger);font-size:0.85rem;">Error al cargar el historial.</div>';
    }
}

function cerrarModalHistorial() {
    document.getElementById('modal-historial').classList.remove('active');
}

// ===== Reporte de combustible agrupado por zona =====
let reporteZonaDatos = [];   // Respuesta cruda de la API: [{ zona, filas, totales }, ...]
let reporteZonaActiva = null;

function abrirModalReporteZona() {
    document.getElementById('modal-reporte-zona').classList.add('active');
    cargarReporteZona();
}

function cerrarModalReporteZona() {
    document.getElementById('modal-reporte-zona').classList.remove('active');
}

function limpiarFiltrosReporteZona() {
    document.getElementById('reporte-fecha-desde').value = '';
    document.getElementById('reporte-fecha-hasta').value = '';
    cargarReporteZona();
}

async function cargarReporteZona() {
    const cont = document.getElementById('reporte-tabla-body');
    const tabs = document.getElementById('reporte-tabs');
    tabs.innerHTML = 'Cargando zonas...';
    cont.innerHTML = '<tr class="empty-row"><td colspan="14">Cargando...</td></tr>';
    document.getElementById('reporte-tabla-foot').innerHTML = '';
    document.getElementById('reporte-resumen').innerHTML = '';

    const desde = document.getElementById('reporte-fecha-desde').value;
    const hasta = document.getElementById('reporte-fecha-hasta').value;
    const params = new URLSearchParams();
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);

    try {
        const res = await fetch('api/combustible_reporte_zona.php' + (params.toString() ? '?' + params.toString() : ''));
        const data = await res.json();
        if (!data.success) {
            tabs.innerHTML = '';
            cont.innerHTML = `<tr class="error-row"><td colspan="14">${data.message || 'Error al generar el reporte'}</td></tr>`;
            return;
        }
        reporteZonaDatos = data.zonas || [];
        if (reporteZonaDatos.length === 0) {
            tabs.innerHTML = '';
            cont.innerHTML = '<tr class="empty-row"><td colspan="14">Sin cargas registradas en este rango.</td></tr>';
            return;
        }
        reporteZonaActiva = reporteZonaDatos[0].zona;
        renderTabsReporteZona();
        renderReporteZona();
    } catch (err) {
        tabs.innerHTML = '';
        cont.innerHTML = '<tr class="error-row"><td colspan="14">Error de conexión al generar el reporte</td></tr>';
    }
}

function renderTabsReporteZona() {
    const tabs = document.getElementById('reporte-tabs');
    tabs.innerHTML = reporteZonaDatos.map(g => `
        <button type="button" class="reporte-tab${g.zona === reporteZonaActiva ? ' active' : ''}" onclick="seleccionarTabReporteZona(${JSON.stringify(g.zona)})">
            ${escapeHtml(g.zona)} <span class="cnt">${g.totales.cargas}</span>
        </button>
    `).join('');
}

function seleccionarTabReporteZona(zona) {
    reporteZonaActiva = zona;
    renderTabsReporteZona();
    renderReporteZona();
}

function grupoZonaActivo() {
    return reporteZonaDatos.find(g => g.zona === reporteZonaActiva) || null;
}

function renderReporteZona() {
    const grupo = grupoZonaActivo();
    const cuerpo = document.getElementById('reporte-tabla-body');
    const pie = document.getElementById('reporte-tabla-foot');
    const resumen = document.getElementById('reporte-resumen');
    if (!grupo) {
        cuerpo.innerHTML = '<tr class="empty-row"><td colspan="14">Sin datos</td></tr>';
        pie.innerHTML = '';
        resumen.innerHTML = '';
        return;
    }

    const t = grupo.totales;
    resumen.innerHTML = `
        <div class="kpi-card kpi-cargas"><div class="kpi-icon"><i class="fas fa-clipboard-list"></i></div><div><div class="kpi-value">${t.cargas}</div><div class="kpi-label">Cargas</div></div></div>
        <div class="kpi-card kpi-litros"><div class="kpi-icon"><i class="fas fa-droplet"></i></div><div><div class="kpi-value">${t.litros.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 3 })}</div><div class="kpi-label">Litros</div></div></div>
        <div class="kpi-card kpi-galones"><div class="kpi-icon"><i class="fas fa-gas-pump"></i></div><div><div class="kpi-value">${t.galones.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div><div class="kpi-label">Galones</div></div></div>
        <div class="kpi-card kpi-gasto"><div class="kpi-icon"><i class="fas fa-coins"></i></div><div><div class="kpi-value">L. ${t.lempiras.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div><div class="kpi-label">Gasto total</div></div></div>
        <div class="kpi-card kpi-km"><div class="kpi-icon"><i class="fas fa-road"></i></div><div><div class="kpi-value">${t.km_recorridos.toLocaleString('es-HN')}</div><div class="kpi-label">Km recorridos</div></div></div>
        <div class="kpi-card kpi-rendimiento"><div class="kpi-icon"><i class="fas fa-gauge-high"></i></div><div><div class="kpi-value">${t.promedio_km_por_litro !== null ? t.promedio_km_por_litro : '—'}</div><div class="kpi-label">Promedio km/l</div></div></div>
    `;

    if (grupo.filas.length === 0) {
        cuerpo.innerHTML = '<tr class="empty-row"><td colspan="14">Sin cargas registradas en esta zona</td></tr>';
        pie.innerHTML = '';
        return;
    }

    cuerpo.innerHTML = grupo.filas.map(f => `
        <tr>
            <td>${f.fecha}</td>
            <td><span class="placa-chip"><span class="dot"></span>${escapeHtml(f.placa)}</span></td>
            <td>${escapeHtml([f.marca, f.modelo].filter(Boolean).join(' ')) || '—'}</td>
            <td>${escapeHtml(f.patrullero_nombre) || '—'}</td>
            <td class="num">${f.km_inicial.toLocaleString('es-HN')}</td>
            <td class="num">${f.km_final.toLocaleString('es-HN')}</td>
            <td class="num">${f.km_recorridos < 0
                ? `<span style="color:var(--danger);font-weight:700;" title="Dato inconsistente: revisar km inicial/final de esta carga">${f.km_recorridos.toLocaleString('es-HN')} ⚠</span>`
                : f.km_recorridos.toLocaleString('es-HN')}</td>
            <td>${escapeHtml(f.nivel_inicio) || '—'}</td>
            <td>${escapeHtml(f.nivel_recarga) || '—'}</td>
            <td class="num">${f.litros_cargados.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 3 })}</td>
            <td class="num">${f.galones_cargados.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td class="num">${f.monto_lempiras !== null ? f.monto_lempiras.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}</td>
            <td class="num">${f.km_por_litro !== null ? f.km_por_litro : '—'}</td>
            <td class="obs" title="${escapeHtml(f.observaciones)}">${escapeHtml(f.observaciones)}</td>
        </tr>
    `).join('');

    pie.innerHTML = `
        <tr>
            <td colspan="6">Totales — ${escapeHtml(grupo.zona)}</td>
            <td class="num">${t.km_recorridos.toLocaleString('es-HN')}</td>
            <td colspan="2"></td>
            <td class="num">${t.litros.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 3 })}</td>
            <td class="num">${t.galones.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td class="num">L. ${t.lempiras.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td class="num">${t.promedio_km_por_litro !== null ? t.promedio_km_por_litro : '—'}</td>
            <td></td>
        </tr>
    `;
}

// ===== Descarga en Excel con formato (título, KPIs, encabezados de color,
// franjas y totales resaltados) — una hoja por zona, igual que tus pestañas
// originales de Excel (Norte, Centro, Sur, Motorizadas). No es un simple CSV:
// se genera un documento HTML con las etiquetas que Excel reconoce como un
// libro real de varias hojas (truco estándar y ampliamente soportado por
// Excel de escritorio).
const COLUMNAS_REPORTE_EXCEL = [
    { titulo: 'Fecha', ancho: 90 },
    { titulo: 'Placa', ancho: 90 },
    { titulo: 'Marca / Modelo', ancho: 160 },
    { titulo: 'Piloto', ancho: 150 },
    { titulo: 'Km inicial', ancho: 90 },
    { titulo: 'Km final', ancho: 90 },
    { titulo: 'Km recorrido', ancho: 100 },
    { titulo: 'Nivel que inicia', ancho: 110 },
    { titulo: 'Nivel antes de recarga', ancho: 130 },
    { titulo: 'Litros cargados', ancho: 100 },
    { titulo: 'Galones cargados', ancho: 100 },
    { titulo: 'Cantidad (Lps)', ancho: 110 },
    { titulo: 'Km por litro', ancho: 90 },
    { titulo: 'Observación', ancho: 220 },
];
const TOTAL_COLUMNAS_REPORTE = COLUMNAS_REPORTE_EXCEL.length;

function sanitizarNombreHoja(zona) {
    // Los nombres de hoja en Excel no pueden tener : \ / ? * [ ] ni pasar de 31 caracteres.
    return zona.replace(/[:\\/?*\[\]]/g, '').slice(0, 31) || 'Zona';
}

function tdTexto(valor, extra = '') {
    return `<td style="mso-number-format:'\\@';${extra}">${escapeHtml(valor ?? '')}</td>`;
}
function tdNumero(valor, formato, extra = '') {
    const contenido = (valor === null || valor === undefined || valor === '') ? '' : valor;
    return `<td class="num" style="mso-number-format:'${formato}';${extra}">${contenido}</td>`;
}

function construirHojaExcelZona(grupo, desde, hasta) {
    const t = grupo.totales;
    const rangoTexto = (desde || hasta)
        ? `Del ${desde || 'inicio'} al ${hasta || 'hoy'}`
        : 'Todas las fechas registradas';
    const generado = new Date().toLocaleString('es-HN', { timeZone: 'America/Tegucigalpa' });

    const filasDatos = grupo.filas.map((f, i) => {
        const claseFila = i % 2 === 0 ? 'par' : 'impar';
        const kmRecorridoCelda = f.km_recorridos < 0
            ? `<td class="num neg" style="mso-number-format:'#,##0';">${f.km_recorridos} ⚠</td>`
            : tdNumero(f.km_recorridos, '#,##0');
        return `<tr class="${claseFila}">
            ${tdTexto(f.fecha)}
            ${tdTexto(f.placa)}
            ${tdTexto([f.marca, f.modelo].filter(Boolean).join(' '))}
            ${tdTexto(f.patrullero_nombre || '—')}
            ${tdNumero(f.km_inicial, '#,##0')}
            ${tdNumero(f.km_final, '#,##0')}
            ${kmRecorridoCelda}
            ${tdTexto(f.nivel_inicio || '—')}
            ${tdTexto(f.nivel_recarga || '—')}
            ${tdNumero(f.litros_cargados, '0.000')}
            ${tdNumero(f.galones_cargados, '0.00')}
            ${tdNumero(f.monto_lempiras, '"L. "#,##0.00')}
            ${tdNumero(f.km_por_litro, '0.000')}
            ${tdTexto(f.observaciones || '')}
        </tr>`;
    }).join('');

    return `
    <table>
        <colgroup>${COLUMNAS_REPORTE_EXCEL.map(c => `<col style="width:${c.ancho}px;">`).join('')}</colgroup>
        <tr><td class="titulo" colspan="${TOTAL_COLUMNAS_REPORTE}">FORZA · Combustible — Zona ${escapeHtml(grupo.zona)}</td></tr>
        <tr><td class="subtitulo" colspan="${TOTAL_COLUMNAS_REPORTE}">${escapeHtml(rangoTexto)} &nbsp;·&nbsp; Generado el ${escapeHtml(generado)}</td></tr>
        <tr><td colspan="${TOTAL_COLUMNAS_REPORTE}" style="border:none;height:8px;line-height:8px;font-size:1px;">&nbsp;</td></tr>
        <tr>
            <td class="kpi-label" colspan="2">Cargas</td>
            <td class="kpi-label" colspan="3">Litros</td>
            <td class="kpi-label" colspan="2">Galones</td>
            <td class="kpi-label" colspan="3">Gasto total</td>
            <td class="kpi-label" colspan="2">Km recorridos</td>
            <td class="kpi-label" colspan="2">Promedio km/l</td>
        </tr>
        <tr>
            <td class="kpi-value" colspan="2">${t.cargas}</td>
            <td class="kpi-value" colspan="3">${t.litros.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 3 })}</td>
            <td class="kpi-value" colspan="2">${t.galones.toLocaleString('es-HN', { minimumFractionDigits: 2 })}</td>
            <td class="kpi-value" colspan="3">L. ${t.lempiras.toLocaleString('es-HN', { minimumFractionDigits: 2 })}</td>
            <td class="kpi-value" colspan="2">${t.km_recorridos.toLocaleString('es-HN')}</td>
            <td class="kpi-value" colspan="2">${t.promedio_km_por_litro !== null ? t.promedio_km_por_litro : '—'}</td>
        </tr>
        <tr><td colspan="${TOTAL_COLUMNAS_REPORTE}" style="border:none;height:10px;line-height:10px;font-size:1px;">&nbsp;</td></tr>
        <tr>${COLUMNAS_REPORTE_EXCEL.map(c => `<th class="enc">${escapeHtml(c.titulo)}</th>`).join('')}</tr>
        ${filasDatos}
        <tr class="totales">
            <td colspan="6">Totales — ${escapeHtml(grupo.zona)} (${t.cargas} carga${t.cargas === 1 ? '' : 's'})</td>
            ${tdNumero(t.km_recorridos, '#,##0')}
            <td colspan="2"></td>
            ${tdNumero(t.litros, '0.000')}
            ${tdNumero(t.galones, '0.00')}
            ${tdNumero(t.lempiras, '"L. "#,##0.00')}
            ${tdNumero(t.promedio_km_por_litro, '0.000')}
            <td></td>
        </tr>
    </table>`;
}

function descargarCsvReporteZona() {
    if (!reporteZonaDatos || reporteZonaDatos.length === 0) {
        alert('No hay datos para descargar con los filtros actuales.');
        return;
    }

    const desde = document.getElementById('reporte-fecha-desde').value;
    const hasta = document.getElementById('reporte-fecha-hasta').value;

    const nombresHojas = reporteZonaDatos.map(g => sanitizarNombreHoja(g.zona));
    const bloqueHojasXml = nombresHojas.map(nombre => `
        <x:ExcelWorksheet>
            <x:Name>${nombre}</x:Name>
            <x:WorksheetOptions><x:DisplayGridlines/><x:FreezePanes/><x:FrozenNoSplit/><x:SplitHorizontal>7</x:SplitHorizontal><x:TopRowBottomPane>7</x:TopRowBottomPane></x:WorksheetOptions>
        </x:ExcelWorksheet>`).join('');

    const tablasHtml = reporteZonaDatos.map(g => construirHojaExcelZona(g, desde, hasta)).join('\n');

    const estilos = `
        body{ font-family:Calibri,Arial,sans-serif; }
        table{ border-collapse:collapse; margin-bottom:6px; }
        td, th{ font-size:10pt; }
        .titulo{ background:#dc2626; color:#ffffff; font-size:15pt; font-weight:bold; padding:10px 8px; }
        .subtitulo{ background:#fef2f2; color:#7f1d1d; font-size:9.5pt; padding:6px 8px; }
        .kpi-label{ background:#f3f4f6; color:#6b7280; font-size:8.5pt; font-weight:bold; text-transform:uppercase; text-align:center; border:1px solid #d1d5db; padding:4px; }
        .kpi-value{ background:#ffffff; color:#111827; font-size:12pt; font-weight:bold; text-align:center; border:1px solid #d1d5db; padding:6px 4px; }
        th.enc{ background:#dc2626; color:#ffffff; font-size:9.5pt; font-weight:bold; padding:7px 6px; border:1px solid #991b1b; text-align:left; }
        td{ border:1px solid #e5e7eb; padding:5px 6px; }
        tr.par td{ background:#fbfbfd; }
        tr.impar td{ background:#ffffff; }
        td.num{ text-align:right; }
        td.neg{ color:#be123c; font-weight:bold; }
        tr.totales td{ background:#fef2f2; font-weight:bold; border-top:2px solid #dc2626; }
    `;

    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook><x:ExcelWorksheets>${bloqueHojasXml}</x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
<style>${estilos}</style>
</head>
<body>
${tablasHtml}
</body>
</html>`;

    const blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const sufijoFecha = fechaLocalHN();
    a.href = url;
    a.download = `combustible_reporte_zonas_${sufijoFecha}.xls`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

async function eliminarCarga(id, vehiculoId, placa) {
    if (!confirm('¿Eliminar este registro de combustible?')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/combustible_eliminar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            await abrirModalHistorial(vehiculoId, placa);
            await cargarRanking();
        } else {
            alert('❌ ' + (data.message || 'Error al eliminar'));
        }
    } catch (err) {
        alert('❌ Error de conexión al eliminar');
    }
}

(async function init() {
    await cargarVehiculos();
    await cargarRanking();
})();

// Refresca el ranking cada 60s por si otro admin registra una carga.
setInterval(() => { cargarRanking(); }, 60000);
</script>

</body>
</html>