<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion/db.php';

$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol'] ?? 'Usuario';
$es_admin = in_array(strtolower(trim($usuario_rol)), ['admin', 'administrador']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FORZA - Operaciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#e31b23; --primary-dark:#a90f15; --secondary:#64748b;
    --success:#10b981; --danger:#ef4444; --warning:#f59e0b; --info:#e31b23;
    --bg-primary:#ffffff; --bg-secondary:#f8fafc; --bg-card:#ffffff;
    --text-primary:#0f172a; --text-secondary:#64748b; --border:#e2e8f0;
    --shadow:0 1px 3px rgba(0,0,0,0.1); --shadow-lg:0 10px 25px rgba(0,0,0,0.1);
}
body{ font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; background:var(--bg-secondary); color:var(--text-primary); }
.top-bar{
    position:fixed; top:0; right:0; left:280px; height:70px; background:var(--bg-card);
    border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;
    padding:0 2rem; z-index:100; box-shadow:var(--shadow);
    transition:left .2s ease;
}
.sidebar{
    position:fixed; left:0; top:0; width:280px; height:100vh; background:var(--bg-card);
    border-right:1px solid var(--border); display:flex; flex-direction:column; z-index:101;
    transition:width .2s ease;
}
.sidebar-header{ padding:2rem; border-bottom:1px solid var(--border); }
.logo-container{ display:flex; align-items:center; gap:1rem; }
.logo-icon{
    width:50px; height:50px; background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem;
    flex-shrink:0;
}
.logo-text h1{ font-size:1.5rem; font-weight:800; }
.logo-text p{ font-size:0.75rem; color:var(--text-secondary); margin-top:0.25rem; }
.nav-menu{ flex:1; padding:1rem; overflow-y:auto; }
.nav-item{
    width:100%; padding:1rem 1.25rem; margin-bottom:0.5rem; background:transparent; border:none;
    border-radius:12px; display:flex; align-items:center; gap:1rem; color:var(--text-secondary);
    font-size:0.95rem; font-weight:500; cursor:pointer; text-decoration:none; text-align:left;
}
.nav-item i{ width:20px; text-align:center; font-size:1.05rem; flex-shrink:0; }
.nav-item:hover{ background:var(--bg-secondary); color:var(--text-primary); }
.nav-item.active{ background:var(--primary); color:#fff; }
.nav-item.active i{ color:#fff !important; }

/* Íconos con color propio por módulo */
.nav-item:nth-child(1) i{ color:#e31b23; }
.nav-item:nth-child(1):hover{ background:#fde8e9; }
.nav-item:nth-child(2) i{ color:#10b981; }
.nav-item:nth-child(2):hover{ background:#dcfce7; }
.nav-item:nth-child(3) i{ color:#e31b23; }
.nav-item:nth-child(3):hover{ background:#fde8e9; }
.nav-item:nth-child(4) i{ color:#e31b23; }
.nav-item:nth-child(4):hover{ background:#fde8e9; }

/* Botón para colapsar el menú */
.sidebar-toggle-btn{
    width:38px; height:38px; border-radius:10px; border:1px solid var(--border);
    background:var(--bg-secondary); color:var(--text-primary); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:0.95rem;
    transition:background .15s ease;
}
.sidebar-toggle-btn:hover{ background:#e2e8f0; }

.sidebar.collapsed{ width:84px; }
.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-item span{ display:none; }
.sidebar.collapsed .logo-container{ flex-direction:column; gap:0.5rem; }
.sidebar.collapsed .sidebar-header{ padding:1.25rem 0.75rem; text-align:center; }
.sidebar.collapsed .nav-item{ justify-content:center; padding:1rem 0.5rem; }
.top-bar.collapsed{ left:84px; }
.main-content.collapsed{ margin-left:84px; }

.main-content{ margin-left:280px; padding:90px 2rem 2rem; min-height:100vh; transition:margin-left .2s ease; }
.content-header{ margin-bottom:1.5rem; }
.content-header h2{ font-size:1.5rem; font-weight:800; }
.content-header p{ color:var(--text-secondary); font-size:0.9rem; margin-top:0.25rem; }
.flex{ display:flex; } .justify-between{ justify-content:space-between; } .items-center{ align-items:center; }
.gap-2{ gap:0.5rem; }
.card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; box-shadow:var(--shadow); margin-bottom:1.5rem; }
.btn{ display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.25rem; border:none; border-radius:10px; font-size:0.9rem; font-weight:600; cursor:pointer; }
.btn-primary{ background:var(--primary); color:#fff; }
.btn-primary:hover{ background:var(--primary-dark); }
.btn-secondary{ background:var(--bg-secondary); color:var(--text-primary); border:1px solid var(--border); }
.btn-danger{ background:rgba(239,68,68,0.1); color:var(--danger); }
.btn-sm{ padding:0.4rem 0.75rem; font-size:0.8rem; }
.filters{ display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.filters select, .filters input{
    padding:0.6rem 0.9rem; border-radius:10px; border:1px solid var(--border); background:var(--bg-secondary);
    font-size:0.85rem; color:var(--text-primary);
}
.table-container{ overflow-x:auto; border-radius:12px; -webkit-overflow-scrolling:touch; }
table{ width:100%; border-collapse:collapse; }
th{ text-align:center; padding:0.85rem 1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); border-bottom:2px solid var(--border); background:var(--bg-card); white-space:nowrap; }
td{ padding:0.95rem 1rem; border-bottom:1px solid var(--border); font-size:0.88rem; vertical-align:middle; }

/* ---------- Tabla ancha estilo hoja de cálculo ---------- */
.tabla-ancha thead th{
    background:linear-gradient(180deg,var(--primary),var(--primary-dark)); color:#fff; text-align:center;
    font-size:0.74rem; font-weight:800; border-bottom:none;
}
.tabla-ancha tbody tr:nth-child(even){ background:#f8fafc; }
.tabla-ancha td{ text-align:center; white-space:nowrap; }
.td-servicio{ font-weight:800; text-transform:uppercase; font-size:0.78rem; color:var(--text-primary); }
.td-fecha, .td-hora{ color:#dc2626; font-weight:700; }
.td-cliente{ font-weight:700; }
.td-datos{ text-align:left; white-space:normal; min-width:220px; font-size:0.82rem; color:var(--text-secondary); }
.td-datos .dato-vacio{ color:#cbd5e1; font-style:italic; }
.td-ruta{ text-align:left; }
.td-custodio{ background:#dcfce7; font-weight:700; color:#166534; white-space:normal; min-width:130px; text-align:left; }
.td-custodio .custodio-linea{ display:block; }
.td-custodio .custodio-linea .gps-parte{ font-weight:600; color:var(--primary-dark); }
.td-piloto{ background:#fff; font-weight:700; color:var(--text-primary); }
tbody tr:nth-child(even) td{ background:#fbfcfe; }
tr:hover td{ background:#fef2f2; }
.text-muted{ color:var(--text-secondary); }
.col-id{ font-weight:700; color:var(--text-secondary); white-space:nowrap; }
.col-hora, .col-fecha{ white-space:nowrap; }
.ruta-cell{ display:flex; flex-direction:column; gap:4px; min-width:170px; }
.ruta-punto{ display:flex; align-items:center; gap:6px; font-size:0.85rem; }
.ruta-punto .ruta-label{ font-size:0.65rem; text-transform:uppercase; letter-spacing:0.3px; color:var(--text-secondary); font-weight:700; width:52px; flex-shrink:0; }
.ruta-punto.origen i{ color:var(--success); }
.ruta-punto.destino i{ color:var(--danger); }
.ruta-punto span.lugar{ font-weight:600; }
.tipo-pill{ display:inline-block; padding:0.25rem 0.65rem; border-radius:8px; font-size:0.78rem; font-weight:600; background:var(--bg-secondary); border:1px solid var(--border); white-space:nowrap; }
.custodios-chips{ display:flex; flex-wrap:wrap; gap:0.4rem; max-width:260px; }
.chip{ background:#fde8e9; color:var(--primary-dark); border:1px solid rgba(227,27,35,0.15); border-radius:10px; font-size:0.75rem; font-weight:700; white-space:nowrap; display:inline-flex; flex-direction:column; align-items:flex-start; line-height:1.35; padding:0.3rem 0.6rem; gap:0; }
.chip .chip-tipo{ font-weight:600; opacity:0.7; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.3px; }
.badge{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.7rem; border-radius:20px; font-size:0.75rem; font-weight:700; white-space:nowrap; }
.badge-dot{ width:6px; height:6px; border-radius:50%; background:currentColor; }
.badge-pendiente{ background:rgba(245,158,11,0.12); color:var(--warning); }
.badge-en_ruta{ background:rgba(16,185,129,0.12); color:var(--success); }
.badge-finalizado{ background:rgba(100,116,139,0.12); color:var(--secondary); }
.badge-cancelado{ background:rgba(239,68,68,0.12); color:var(--danger); }
.actions-cell{ display:flex; gap:0.4rem; }
.btn-icon{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--border); background:var(--bg-secondary); color:var(--text-primary); cursor:pointer; font-size:0.85rem; transition:background 0.15s; }
.btn-icon:hover{ background:#e2e8f0; }
.btn-icon-danger{ background:rgba(239,68,68,0.08); color:var(--danger); border-color:rgba(239,68,68,0.2); }
.btn-icon-danger:hover{ background:rgba(239,68,68,0.16); }
.empty-state{ text-align:center; color:var(--text-secondary); padding:3rem 1rem; }
.empty-state i{ font-size:1.6rem; margin-bottom:0.6rem; display:block; color:var(--border); }
.modal-mensaje-content{ max-width:420px; text-align:center; padding:2rem 1.75rem 1.75rem; }
.modal-mensaje-icono{ width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; }
.modal-mensaje-icono.exito{ background:rgba(16,185,129,0.12); color:var(--success); }
.modal-mensaje-icono.error{ background:rgba(239,68,68,0.12); color:var(--danger); }
.modal-mensaje-titulo{ font-size:1.05rem; font-weight:700; margin-bottom:0.5rem; }
.modal-mensaje-texto{ font-size:0.9rem; color:var(--text-secondary); line-height:1.5; margin-bottom:1.5rem; }
.modal{ display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:200; align-items:center; justify-content:center; }
.modal.active{ display:flex; }
.modal-content{ background:var(--bg-card); border-radius:20px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; margin:1rem; }
.modal-content.modal-ancho{ max-width:1080px; }
.modal-header{ display:flex; justify-content:space-between; align-items:center; padding:1.5rem 2rem; border-bottom:1px solid var(--border); }
.modal-header h3{ font-size:1.15rem; }
.close-modal{ background:none; border:none; font-size:1.1rem; color:var(--text-secondary); cursor:pointer; }
.modal-body{ padding:1.5rem 2rem 2rem; }
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.form-group{ margin-bottom:1.1rem; }
.form-label{ display:block; font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; color:var(--text-secondary); }
.form-input, .form-select, .form-textarea{
    width:100%; padding:0.75rem 1rem; border:1px solid var(--border); border-radius:10px; font-size:0.9rem;
    background:var(--bg-secondary); color:var(--text-primary); font-family:inherit;
}
.form-textarea{ min-height:80px; resize:vertical; }
.custodio-fila{ display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-start; margin-bottom:0.5rem; }
.combo-custodio{ position:relative; flex:1 1 150px; }
.combo-gps{ position:relative; flex:1 1 150px; }
.combo-piloto{ position:relative; }
.combo-piloto .combo-dropdown{ max-height:130px; }
.select-tipo-custodio{ flex:1 1 150px; padding:0.75rem 0.9rem; border:1px solid var(--border); border-radius:10px; font-size:0.85rem; background:var(--bg-secondary); color:var(--text-primary); font-family:inherit; }
.combo-dropdown{ position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid var(--border); border-radius:10px; max-height:220px; overflow-y:auto; box-shadow:var(--shadow-lg); z-index:300; }
.combo-dropdown.hidden{ display:none; }
.combo-option{ padding:0.6rem 0.9rem; font-size:0.85rem; cursor:pointer; }
.combo-option:hover{ background:#fde8e9; }
.combo-empty{ padding:0.6rem 0.9rem; font-size:0.85rem; color:var(--text-secondary); }
.btn-quitar-custodio{ width:38px; height:38px; flex-shrink:0; border:1px solid var(--border); background:var(--bg-secondary); color:var(--danger); border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.btn-quitar-custodio:hover{ background:rgba(239,68,68,0.08); }
.btn-agregar-custodio{ background:none; border:1px dashed var(--border); color:var(--primary); padding:0.6rem; border-radius:10px; width:100%; cursor:pointer; font-size:0.85rem; font-weight:600; margin-top:0.25rem; }
.btn-agregar-custodio:hover{ background:#fde8e9; }
.form-hint{ font-size:0.78rem; margin-bottom:0.6rem; font-weight:600; min-height:1.1em; }
.form-hint.hint-warning{ color:var(--warning); }
.form-hint.hint-ok{ color:var(--success); }
.resumen-servicios{ display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.75rem; }
.resumen-servicios .chip-resumen{ background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:0.3rem 0.7rem; border-radius:20px; font-size:0.76rem; font-weight:700; display:inline-flex; align-items:center; gap:0.35rem; }
.resumen-servicios .chip-resumen .count{ background:var(--primary); color:#fff; border-radius:10px; padding:0.05rem 0.45rem; font-size:0.7rem; }
.resumen-vacio{ font-size:0.8rem; color:var(--text-secondary); }
.hidden{ display:none !important; }

/* ---------- Formulario de Nueva Operación: secciones visuales ---------- */
.form-seccion{
    background:#fff; border:1px solid var(--border); border-radius:14px;
    padding:1.15rem 1.25rem 0.35rem; margin-bottom:1.15rem;
}
.form-seccion-titulo{
    display:flex; align-items:center; gap:0.55rem; font-size:0.76rem; font-weight:800;
    text-transform:uppercase; letter-spacing:0.5px; color:var(--primary); margin-bottom:1rem;
}
.form-seccion-titulo i{ font-size:0.85rem; width:16px; text-align:center; }
.form-seccion .form-group:last-child,
.form-seccion .form-row:last-child{ margin-bottom:1.1rem; }

.form-input, .form-select, .form-textarea{ transition:border-color .15s ease, box-shadow .15s ease, background .15s ease; }
.form-input:focus, .form-select:focus, .form-textarea:focus{
    outline:none; border-color:var(--primary); background:#fff;
    box-shadow:0 0 0 3px rgba(227,27,35,0.12);
}
.form-group .form-hint-suave{ font-size:0.74rem; color:var(--text-secondary); margin-top:0.3rem; }

/* Selector de prefijo + número, agrupados visualmente como un solo control */
.input-group-tel{ display:flex; }
.input-group-tel .form-select-prefijo{
    flex:0 0 118px; border-top-right-radius:0; border-bottom-right-radius:0; border-right:none;
    padding-left:0.65rem; padding-right:0.3rem; font-size:0.85rem; cursor:pointer;
}
.input-group-tel .form-input{
    flex:1; min-width:0; border-top-left-radius:0; border-bottom-left-radius:0;
}
.input-group-tel:focus-within .form-select-prefijo,
.input-group-tel:focus-within .form-input{
    border-color:var(--primary); box-shadow:0 0 0 3px rgba(227,27,35,0.12);
}

/* Usuario con diseño (avatar + nombre) */
.user-chip{ display:flex; align-items:center; gap:0.65rem; font-size:0.85rem; color:var(--text-primary); font-weight:600; }
.user-avatar{
    width:38px; height:38px; border-radius:50%; flex-shrink:0;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.9rem;
    box-shadow:0 3px 8px rgba(227,27,35,0.35);
}
.user-info{ display:flex; flex-direction:column; line-height:1.2; gap:0.15rem; }
.user-info small{ font-weight:500; color:var(--text-secondary); font-size:0.72rem; }
.top-bar-left{ display:flex; align-items:center; gap:1rem; }
.role-pill{
    display:inline-flex; align-items:center; gap:0.3rem; font-size:0.62rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.04em; padding:0.15rem 0.55rem; border-radius:20px;
    margin-top:0.25rem; width:fit-content;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff;
    box-shadow:0 2px 6px rgba(227,27,35,0.3);
}
.role-pill i{ font-size:0.6rem; }
.role-pill.rol-lectura{
    background:#eef1f5; color:var(--text-secondary); box-shadow:none; border:1px solid var(--border);
}

/* ---------- Vista de tarjetas detalladas (modal "Ver todo en detalle") ---------- */
.tarjetas-filters{ display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.1rem; }
.tarjetas-filters select, .tarjetas-filters input{
    padding:0.6rem 0.9rem; border-radius:10px; border:1px solid var(--border); background:var(--bg-secondary);
    font-size:0.85rem; color:var(--text-primary);
}
.tarjetas-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:1rem; }
.tarjeta-servicio{
    background:var(--bg-card); border:1px solid var(--border); border-radius:16px; overflow:hidden;
    box-shadow:var(--shadow); border-left:5px solid var(--border);
}
.tarjeta-servicio.estado-pendiente{ border-left-color:var(--warning); }
.tarjeta-servicio.estado-en_ruta{ border-left-color:var(--info); }
.tarjeta-servicio.estado-finalizado{ border-left-color:var(--success); }
.tarjeta-servicio.estado-cancelado{ border-left-color:var(--text-secondary); }
.tarjeta-header{ display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem; padding:1rem 1.1rem 0.85rem; border-bottom:1px solid var(--border); }
.tarjeta-cliente{ font-weight:800; font-size:1.02rem; }
.tarjeta-fecha{ font-size:0.78rem; color:var(--text-secondary); margin-top:0.3rem; display:flex; align-items:center; gap:0.35rem; }
.tarjeta-id{ font-size:0.72rem; color:var(--text-secondary); font-weight:700; }
.tarjeta-badges{ display:flex; flex-direction:column; align-items:flex-end; gap:0.35rem; flex-shrink:0; }
.tarjeta-body{ padding:0.95rem 1.1rem 1.1rem; }
.tarjeta-piloto{
    display:flex; align-items:center; gap:0.5rem; font-size:0.86rem; font-weight:700; color:var(--text-primary);
    background:var(--bg-secondary); border:1px solid var(--border); border-radius:10px; padding:0.55rem 0.75rem; margin-bottom:0.75rem;
}
.tarjeta-piloto i{ color:var(--primary); }
.tarjeta-piloto.sin-asignar{ color:var(--text-secondary); font-weight:600; }
.tarjeta-ruta-box{
    background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; padding:0.7rem 0.85rem;
    margin-bottom:0.75rem; display:flex; flex-direction:column; gap:0.5rem;
}
.tarjeta-seccion-label{
    font-size:0.68rem; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-secondary);
    font-weight:700; margin-bottom:0.4rem; display:flex; align-items:center; gap:0.35rem;
}
.tarjeta-personal-lista{ display:flex; flex-direction:column; gap:0.5rem; margin-bottom:0.75rem; }
.tarjeta-personal-item{
    display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
    background:var(--bg-secondary); border:1px solid var(--border); border-radius:10px; padding:0.55rem 0.75rem; font-size:0.85rem;
}
.tarjeta-personal-item-left{ display:flex; align-items:center; gap:0.5rem; min-width:0; }
.tarjeta-personal-item-left i{ color:var(--text-secondary); flex-shrink:0; }
.tarjeta-personal-item.tarjeta-personal-item-gps{ background:rgba(227,27,35,0.06); border-color:rgba(227,27,35,0.22); }
.tarjeta-personal-item.tarjeta-personal-item-gps .tarjeta-personal-item-left i{ color:var(--info); }
.tarjeta-obs{ font-size:0.83rem; background:var(--bg-secondary); border:1px solid var(--border); border-radius:10px; padding:0.65rem 0.8rem; color:var(--text-secondary); display:flex; gap:0.5rem; align-items:flex-start; }
.tarjeta-obs i{ margin-top:0.15rem; flex-shrink:0; }
.badge-gps{ background:#fde8e9; color:var(--primary-dark); }
.tarjeta-acciones{ display:flex; gap:0.5rem; margin-top:0.85rem; }

/* ================= MODO NOCHE ================= */
body.dark-mode{
    --bg-primary:#12141a; --bg-secondary:#1b1e26; --bg-card:#1b1e26;
    --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038;
}
body.dark-mode .tabla-ancha tbody tr:nth-child(even){ background:#20232c; }
body.dark-mode .td-piloto{ background:#1b1e26; }
body.dark-mode tbody tr:nth-child(even) td{ background:#1e212a; }
body.dark-mode tr:hover td{ background:#262229; }
body.dark-mode .combo-dropdown{ background:#1b1e26; }
body.dark-mode .combo-option:hover{ background:#2c2429; }
body.dark-mode .form-seccion{ background:#1b1e26; }
body.dark-mode .form-input:focus, body.dark-mode .form-select:focus, body.dark-mode .form-textarea:focus{ background:#20232c; }
body.dark-mode .role-pill.rol-lectura{ background:#20232c; border-color:var(--border); color:var(--text-secondary); }

@media (max-width:900px){
  .sidebar{ transform:translateX(-100%); }
  .sidebar.collapsed{ transform:translateX(0); width:280px; }
  .top-bar, .main-content{ left:0; margin-left:0; }
  .top-bar.collapsed, .main-content.collapsed{ margin-left:0; left:0; }
  .form-row{ grid-template-columns:1fr; }
}
</style>
</head>
<body>
<script>(function(){ if (localStorage.getItem('forza-theme') === 'dark') document.body.classList.add('dark-mode'); })();</script>

<div class="top-bar">
    <div class="top-bar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Contraer/expandir menú">
            <i class="fas fa-bars"></i>
        </button>
        <button class="sidebar-toggle-btn" onclick="toggleDarkMode()" title="Modo oscuro/claro">
            <i class="fas fa-moon" id="theme-toggle-icon"></i>
        </button>
        <strong>Operaciones</strong>
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($usuario_nombre, 0, 1)) ?></div>
        <div class="user-info">
            <span><?= htmlspecialchars($usuario_nombre) ?></span>
            <?php if ($es_admin): ?>
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
            <div class="logo-text"><h1>FORZA</h1><p>OPERACIONES • HN</p></div>
        </div>
    </div>
   <nav class="nav-menu">
    <a class="nav-item" href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
    <a class="nav-item active" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>
    <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Pilotos</span></a>
    <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Mi Flota</span></a>
    <a class="nav-item" href="rol_misiones.php"><i class="fas fa-calendar-week"></i><span>Rol de Misiones</span></a>
</nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <div class="flex justify-between items-center">
            <div>
                <h2>Operaciones</h2>
                <p>Programa y da seguimiento a los servicios diarios</p>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary" onclick="abrirModalTarjetas()">
                    <i class="fas fa-table-cells-large"></i> Ver todo en detalle
                </button>
                <button class="btn btn-primary" onclick="abrirModalNueva()">
                    <i class="fas fa-plus"></i> Nueva Operación
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="filters">
            <input type="date" id="filtro-fecha" onchange="renderOperaciones()">
            <select id="filtro-estado" onchange="renderOperaciones()">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="en_ruta">En ruta</option>
                <option value="finalizado">Finalizado</option>
                <option value="cancelado">Cancelado</option>
            </select>
            <select id="filtro-zona" onchange="renderOperaciones()">
                <option value="">Todas las zonas</option>
                <option value="Norte">Zona Norte (SPS)</option>
                <option value="Centro">Zona Centro (TGU)</option>
                <option value="Amatillo">Zona Amatillo</option>
                <option value="Guasaule">Zona Guasaule</option>
                <option value="Choluteca">Zona Choluteca</option>
            </select>
            <input type="text" id="filtro-texto" placeholder="Buscar cliente, patrullero, destino..." style="flex:1;min-width:220px;" oninput="renderOperaciones()">
            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('filtro-fecha').value='';document.getElementById('filtro-estado').value='';document.getElementById('filtro-texto').value='';document.getElementById('filtro-zona').value='';renderOperaciones()">
                <i class="fas fa-eraser"></i> Limpiar filtros
            </button>
        </div>

        <div id="contador-resultados" style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.75rem;"></div>

        <div class="table-container">
            <table class="tabla-ancha">
                <thead>
                    <tr>
                        <th>Servicio</th><th>Fecha</th><th>Cliente</th><th>Datos</th><th>Hora</th>
                        <th>Origen</th><th>Destino</th><th>Custodio</th><th>Piloto</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-operaciones-body">
                    <tr><td colspan="11" style="text-align:center;color:var(--text-secondary);padding:2rem;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL NUEVA / EDITAR OPERACIÓN -->
<div class="modal" id="modal-operacion">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-operacion-titulo"><i class="fas fa-route"></i> Nueva Operación</h3>
            <button class="close-modal" onclick="cerrarModalOperacion()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-operacion" onsubmit="guardarOperacion(event)">
                <input type="hidden" name="id" id="op-id">
                <input type="hidden" name="tipo_servicio" id="op-tipo-computed">

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-calendar-day"></i> Fecha del servicio</div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Fecha del servicio</label>
                        <input type="date" class="form-input" name="fecha_servicio" id="op-fecha" required>
                    </div>
                    <!-- La hora ya no se pide aquí: cada custodio/GPS trae su propia hora
                         en la sección "Piloto y personal asignado". Este campo se mantiene
                         oculto solo para que el guardado por grupos de hora siga funcionando. -->
                    <input type="hidden" name="hora_servicio" id="op-hora">
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-building"></i> Cliente</div>
                    <div class="form-group">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-input" name="cliente" id="op-cliente" placeholder="Ej: MAEGA" required>
                    </div>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-truck"></i> Datos del transporte <span style="font-weight:500;color:var(--text-secondary);text-transform:none;letter-spacing:normal;">(opcional — no todos los clientes los mandan completos)</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Conductor</label>
                            <input type="text" class="form-input" name="conductor" id="op-conductor" placeholder="Conductor">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <div class="input-group-tel">
                                <select class="form-select form-select-prefijo" id="op-telefono-prefijo" title="Código de país">
                                    <option value="+502">🇬🇹 +502</option>
                                    <option value="+503">🇸🇻 +503</option>
                                    <option value="+504" selected>🇭🇳 +504</option>
                                    <option value="+505">🇳🇮 +505</option>
                                    <option value="+506">🇨🇷 +506</option>
                                    <option value="+507">🇵🇦 +507</option>
                                    <option value="+501">🇧🇿 +501</option>
                                </select>
                                <input type="tel" class="form-input" name="telefono" id="op-telefono" placeholder="9987 7889">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Placa</label>
                            <input type="text" class="form-input" name="placa" id="op-placa" placeholder="Placa">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Furgón</label>
                            <input type="text" class="form-input" name="furgon" id="op-furgon" placeholder="Furgón">
                        </div>
                    </div>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-route"></i> Ruta</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Origen</label>
                            <input type="text" class="form-input" name="origen" id="op-origen" placeholder="Ej: El Florido" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Destino</label>
                            <input type="text" class="form-input" name="destino" id="op-destino" placeholder="Ej: Puerto Cortés" required>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="op-estado-wrapper" style="display:none;">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado" id="op-estado">
                        <option value="pendiente">Pendiente</option>
                        <option value="en_ruta">En ruta</option>
                        <option value="finalizado">Finalizado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-id-badge"></i> Piloto y personal asignado</div>
                    <div class="form-group">
                        <label class="form-label">Piloto</label>
                        <div class="combo-piloto">
                            <input type="text" class="form-input combo-piloto-input" id="op-patrullero-input" placeholder="Buscar piloto..." autocomplete="off">
                            <input type="hidden" name="patrullero_id" id="op-patrullero">
                            <div class="combo-dropdown hidden" id="op-patrullero-dropdown"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Custodios / GPS asignados</label>
                        <div class="resumen-servicios" id="custodios-resumen"></div>
                        <div class="form-hint" id="custodios-hint"></div>
                        <div class="form-hint-suave" style="margin-top:-0.3rem;margin-bottom:0.5rem;"><i class="fas fa-clock" style="color:var(--primary);"></i> Cada fila lleva su propia hora de servicio.</div>
                        <div id="custodios-lista"></div>
                        <button type="button" class="btn-agregar-custodio" onclick="agregarFilaCustodio()">
                            <i class="fas fa-plus"></i> Agregar servicio
                        </button>
                    </div>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-note-sticky"></i> Observaciones</div>
                    <div class="form-group" style="margin-bottom:0;">
                        <textarea class="form-textarea" name="observaciones" id="op-observaciones" placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary" style="flex:1;justify-content:center;" onclick="cerrarModalOperacion()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;"><i class="fas fa-check"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE MENSAJES (éxito / error) -->
<div class="modal" id="modal-mensaje">
    <div class="modal-content modal-mensaje-content">
        <div class="modal-mensaje-icono" id="modal-mensaje-icono"><i class="fas fa-check"></i></div>
        <div class="modal-mensaje-titulo" id="modal-mensaje-titulo">Título</div>
        <div class="modal-mensaje-texto" id="modal-mensaje-texto">Texto</div>
        <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="cerrarModalMensaje()">Aceptar</button>
    </div>
</div>

<!-- MODAL: VER TODO EN DETALLE (vista de tarjetas, todos los servicios) -->
<div class="modal" id="modal-tarjetas">
    <div class="modal-content modal-ancho">
        <div class="modal-header">
            <h3><i class="fas fa-table-cells-large"></i> Todos los servicios — vista detallada</h3>
            <button class="close-modal" onclick="cerrarModalTarjetas()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="tarjetas-filters">
                <input type="date" id="tf-fecha" onchange="renderTarjetasOperaciones()">
                <select id="tf-estado" onchange="renderTarjetasOperaciones()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_ruta">En ruta</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
                <input type="text" id="tf-texto" placeholder="Buscar cliente, patrullero, destino..." style="flex:1;min-width:220px;" oninput="renderTarjetasOperaciones()">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('tf-fecha').value='';document.getElementById('tf-estado').value='';document.getElementById('tf-texto').value='';renderTarjetasOperaciones()">
                    <i class="fas fa-eraser"></i> Limpiar filtros
                </button>
            </div>
            <div id="tf-contador" style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.9rem;"></div>
            <div id="tarjetas-operaciones-lista" class="tarjetas-grid"></div>
        </div>
    </div>
</div>

<script>
let operaciones = [];
let patrulleros = [];
let custodios = [];
let gpsDispositivos = [];
let modoEdicion = false;
const ES_ADMIN = <?= $es_admin ? 'true' : 'false' ?>;

const ESTADO_LABEL = { pendiente:'Pendiente', en_ruta:'En ruta', finalizado:'Finalizado', cancelado:'Cancelado' };
const TIPOS_SERVICIO_CUSTODIO = ['Custodio', 'Patrulla Estándar', 'Patrulla Reforzada', 'GPS', 'Marchamo Electrónico', 'Ruta Segura', 'Custodio + GPS'];
const REQUISITOS_CUSTODIOS = { 'Patrulla Estándar': 1, 'Patrulla Reforzada': 2 };
// Tipos que NO requieren seleccionar un custodio (persona), solo el GPS de arriba
const TIPOS_SOLO_GPS = ['Ruta Segura', 'Marchamo Electrónico', 'GPS'];
// Tipos que exigen que el campo GPS (de arriba) esté lleno
const TIPOS_REQUIEREN_GPS = ['Ruta Segura', 'Marchamo Electrónico', 'GPS', 'Custodio + GPS'];

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.top-bar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
}

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

async function safeJson(res) {
    try { return await res.json(); } catch (e) { return []; }
}

function mostrarMensaje(tipo, texto) {
    const icono = document.getElementById('modal-mensaje-icono');
    const titulo = document.getElementById('modal-mensaje-titulo');
    const cuerpo = document.getElementById('modal-mensaje-texto');

    icono.className = 'modal-mensaje-icono ' + (tipo === 'exito' ? 'exito' : 'error');
    icono.innerHTML = tipo === 'exito' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-xmark"></i>';
    titulo.textContent = tipo === 'exito' ? 'Listo' : 'No se pudo completar';
    cuerpo.textContent = texto;

    document.getElementById('modal-mensaje').classList.add('active');
}

function cerrarModalMensaje() {
    document.getElementById('modal-mensaje').classList.remove('active');
}

async function init() {
    await Promise.all([cargarListas(), cargarOperaciones()]);
}

async function cargarListas() {
    try {
        const [pRes, cRes, gRes] = await Promise.all([
            fetch('api/piloto_listar.php'),
            fetch('api/get_custodios.php'),
            fetch('api/get_gps.php')
        ]);
        patrulleros = await safeJson(pRes);
        custodios = await safeJson(cRes);
        gpsDispositivos = await safeJson(gRes);
        if (!Array.isArray(patrulleros)) { console.error('piloto_listar.php no devolvió un array:', patrulleros); patrulleros = []; }
        if (!Array.isArray(custodios)) custodios = [];
        if (!Array.isArray(gpsDispositivos)) gpsDispositivos = [];
        llenarSelects();
    } catch (err) {
        console.error('Error cargando listas:', err);
    }
}

function llenarSelects() {
    const input = document.getElementById('op-patrullero-input');
    const hidden = document.getElementById('op-patrullero');
    const dropdown = document.getElementById('op-patrullero-dropdown');

    function renderOpcionesPiloto(filtro) {
        const texto = (filtro || '').toLowerCase().trim();
        const opciones = [{ id: '', nombre: 'Sin asignar' }, ...patrulleros];
        const filtrados = opciones.filter(p => p.nombre.toLowerCase().includes(texto));
        dropdown.innerHTML = filtrados.length
            ? filtrados.map(p => `<div class="combo-option" data-id="${p.id}" data-nombre="${p.nombre.replace(/"/g,'&quot;')}">${p.nombre}</div>`).join('')
            : '<div class="combo-empty">Sin resultados</div>';
    }

    input.addEventListener('focus', () => { renderOpcionesPiloto(input.value); dropdown.classList.remove('hidden'); });
    input.addEventListener('input', () => {
        hidden.value = '';
        renderOpcionesPiloto(input.value);
        dropdown.classList.remove('hidden');
    });
    dropdown.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('.combo-option');
        if (!opt) return;
        input.value = opt.dataset.nombre === 'Sin asignar' ? '' : opt.dataset.nombre;
        hidden.value = opt.dataset.id;
        dropdown.classList.add('hidden');
    });
}

function crearFilaCustodio(seleccionado) {
    const esObj = seleccionado && typeof seleccionado === 'object';
    const seleccionadoId = esObj ? seleccionado.custodio_id : seleccionado;
    const gpsIdInicial = esObj ? seleccionado.gps_id : '';
    const gpsImeiInicial = esObj ? seleccionado.gps_imei : '';
    const tipoInicial = (esObj && seleccionado.tipo_servicio) ? seleccionado.tipo_servicio : 'Custodio';
    const horaInicial = esObj ? (seleccionado.hora || '') : '';

    const fila = document.createElement('div');
    fila.className = 'custodio-fila';
    fila.innerHTML = `
        <div class="combo-custodio">
            <select class="form-input select-zona-custodio" style="margin-bottom:0.35rem;" title="Filtrar por zona">
                <option value="">Todas las zonas</option>
                <option value="Norte">Zona Norte (SPS)</option>
                <option value="Centro">Zona Centro (TGU)</option>
                <option value="Amatillo">Zona Amatillo</option>
                <option value="Guasaule">Zona Guasaule</option>
                <option value="Choluteca">Zona Choluteca</option>
            </select>
            <input type="text" class="form-input combo-input" placeholder="Buscar custodio..." autocomplete="off">
            <input type="hidden" name="custodios_id[]" class="combo-value">
            <div class="combo-dropdown hidden"></div>
        </div>
        <div class="combo-gps">
            <input type="text" class="form-input combo-gps-input" placeholder="Buscar GPS por IMEI..." autocomplete="off">
            <input type="hidden" name="custodios_gps_id[]" class="combo-gps-value">
            <div class="combo-dropdown hidden"></div>
        </div>
        <select class="select-tipo-custodio" name="custodios_tipo[]">
            ${TIPOS_SERVICIO_CUSTODIO.map(t => `<option value="${t}">${t}</option>`).join('')}
        </select>
        <input type="time" class="form-input fila-hora-input" style="flex:0 0 105px;" title="Hora de este servicio" required>
        <button type="button" class="btn-quitar-custodio" title="Quitar"><i class="fas fa-xmark"></i></button>
    `;

    const input = fila.querySelector('.combo-input');
    const hidden = fila.querySelector('.combo-value');
    const dropdown = fila.querySelector('.combo-custodio .combo-dropdown');
    const comboWrapper = fila.querySelector('.combo-custodio');
    const zonaSelect = fila.querySelector('.select-zona-custodio');

    const gpsInput = fila.querySelector('.combo-gps-input');
    const gpsHidden = fila.querySelector('.combo-gps-value');
    const gpsDropdown = fila.querySelector('.combo-gps .combo-dropdown');
    const gpsWrapper = fila.querySelector('.combo-gps');

    const selectTipo = fila.querySelector('.select-tipo-custodio');
    selectTipo.value = tipoInicial;

    function actualizarVisibilidadFila() {
        const tipo = selectTipo.value;
        const esSoloGps = TIPOS_SOLO_GPS.includes(tipo);
        const requiereGps = TIPOS_REQUIEREN_GPS.includes(tipo);

        if (esSoloGps) {
            comboWrapper.classList.add('hidden');
            input.value = '';
            hidden.value = '';
        } else {
            comboWrapper.classList.remove('hidden');
        }

        if (requiereGps) {
            gpsWrapper.classList.remove('hidden');
        } else {
            gpsWrapper.classList.add('hidden');
            gpsInput.value = '';
            gpsHidden.value = '';
        }
    }
    selectTipo.addEventListener('change', () => { actualizarVisibilidadFila(); actualizarServiciosCustodios(); });
    actualizarVisibilidadFila();

    const btnQuitar = fila.querySelector('.btn-quitar-custodio');
    btnQuitar.addEventListener('click', () => { fila.remove(); actualizarServiciosCustodios(); });

    // Buscador de custodio (persona), filtrable por zona
    function renderOpciones(filtro) {
        const texto = (filtro || '').toLowerCase().trim();
        const zona = zonaSelect.value;
        const filtrados = custodios.filter(c => {
            if (zona && (c.zona || '') !== zona) return false;
            return c.nombre.toLowerCase().includes(texto);
        });
        dropdown.innerHTML = filtrados.length
            ? filtrados.map(c => `<div class="combo-option" data-id="${c.id}" data-nombre="${c.nombre.replace(/"/g,'&quot;')}">${c.nombre}${c.zona ? ` <span style="opacity:.6;font-size:.8em;">(${c.zona})</span>` : ''}</div>`).join('')
            : '<div class="combo-empty">Sin resultados en esta zona</div>';
    }

    zonaSelect.addEventListener('change', () => {
        // Si el custodio ya elegido no pertenece a la zona elegida, se limpia la selección
        if (hidden.value) {
            const actual = custodios.find(x => String(x.id) === String(hidden.value));
            if (actual && zonaSelect.value && (actual.zona || '') !== zonaSelect.value) {
                input.value = '';
                hidden.value = '';
            }
        }
        renderOpciones(input.value);
    });

    input.addEventListener('focus', () => { renderOpciones(input.value); dropdown.classList.remove('hidden'); });
    input.addEventListener('input', () => { hidden.value = ''; renderOpciones(input.value); dropdown.classList.remove('hidden'); actualizarResumenCustodios(); });
    dropdown.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('.combo-option');
        if (!opt) return;
        input.value = opt.dataset.nombre;
        hidden.value = opt.dataset.id;
        dropdown.classList.add('hidden');
        actualizarServiciosCustodios();
    });

    // Buscador de GPS (IMEI), propio de esta fila
    function renderOpcionesGps(filtro) {
        const texto = (filtro || '').toLowerCase().trim();
        const filtrados = gpsDispositivos.filter(g => (g.imei || '').toLowerCase().includes(texto));
        gpsDropdown.innerHTML = filtrados.length
            ? filtrados.map(g => `<div class="combo-option" data-id="${g.id}" data-imei="${(g.imei || ('GPS #' + g.id)).replace(/"/g,'&quot;')}">${g.imei || ('GPS #' + g.id)}</div>`).join('')
            : '<div class="combo-empty">Sin resultados</div>';
    }

    gpsInput.addEventListener('focus', () => { renderOpcionesGps(gpsInput.value); gpsDropdown.classList.remove('hidden'); });
    gpsInput.addEventListener('input', () => { gpsHidden.value = ''; renderOpcionesGps(gpsInput.value); gpsDropdown.classList.remove('hidden'); actualizarResumenCustodios(); });
    gpsDropdown.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('.combo-option');
        if (!opt) return;
        gpsInput.value = opt.dataset.imei;
        gpsHidden.value = opt.dataset.id;
        gpsDropdown.classList.add('hidden');
        actualizarServiciosCustodios();
    });

    if (seleccionadoId) {
        const c = custodios.find(x => String(x.id) === String(seleccionadoId));
        if (c) { input.value = c.nombre; hidden.value = c.id; if (c.zona) zonaSelect.value = c.zona; }
    }
    if (gpsIdInicial) {
        gpsInput.value = gpsImeiInicial || ('GPS #' + gpsIdInicial);
        gpsHidden.value = gpsIdInicial;
    }
    const horaInput = fila.querySelector('.fila-hora-input');
    if (horaInicial) horaInput.value = horaInicial;

    return fila;
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('.combo-dropdown:not(.hidden)').forEach(dd => {
        if (!dd.closest('.combo-custodio, .combo-gps, .combo-piloto').contains(e.target)) dd.classList.add('hidden');
    });
});

function agregarFilaCustodio(seleccionado) {
    document.getElementById('custodios-lista').appendChild(crearFilaCustodio(seleccionado));
    actualizarServiciosCustodios();
}

function resetCustodiosLista(seleccionados) {
    const cont = document.getElementById('custodios-lista');
    cont.innerHTML = '';
    const lista = (seleccionados && seleccionados.length) ? seleccionados : [undefined];
    lista.forEach(item => agregarFilaCustodio(item));
    actualizarServiciosCustodios();
}

function contarCustodiosPorTipo(tipo) {
    return Array.from(document.querySelectorAll('#custodios-lista .select-tipo-custodio')).filter(s => s.value === tipo).length;
}

function actualizarHintCustodios() {
    const hint = document.getElementById('custodios-hint');
    if (!hint) return;
    const mensajes = [];
    let hayError = false;
    for (const tipo in REQUISITOS_CUSTODIOS) {
        const requerido = REQUISITOS_CUSTODIOS[tipo];
        const actual = contarCustodiosPorTipo(tipo);
        if (actual === 0) continue;
        if (actual === requerido) {
            mensajes.push(`${tipo}: ${actual}/${requerido} ✓`);
        } else {
            mensajes.push(`${tipo} requiere exactamente ${requerido} custodio${requerido > 1 ? 's' : ''} (tienes ${actual})`);
            hayError = true;
        }
    }
    hint.textContent = mensajes.join('   ·   ');
    hint.className = 'form-hint ' + (mensajes.length === 0 ? '' : (hayError ? 'hint-warning' : 'hint-ok'));
}

function actualizarResumenCustodios() {
    const cont = document.getElementById('custodios-resumen');
    if (!cont) return;
    const filas = Array.from(document.querySelectorAll('#custodios-lista .custodio-fila'));
    const conteo = {};
    filas.forEach(fila => {
        const tipo = fila.querySelector('.select-tipo-custodio').value;
        const nombre = fila.querySelector('.combo-input').value.trim();
        const gpsId = fila.querySelector('.combo-gps-value').value;
        // Cuenta si tiene custodio (persona), o si es un tipo que ya trae su propio GPS
        if (!nombre && !gpsId) return;
        conteo[tipo] = (conteo[tipo] || 0) + 1;
    });

    const chips = Object.keys(conteo).map(t => `<span class="chip-resumen">${t} <span class="count">${conteo[t]}</span></span>`);

    if (!chips.length) {
        cont.innerHTML = '<span class="resumen-vacio">Aún no has agregado ningún servicio de custodio</span>';
        return;
    }
    cont.innerHTML = chips.join('');
}

function actualizarServiciosCustodios() {
    actualizarHintCustodios();
    actualizarResumenCustodios();
}

function obtenerTiposPresentes() {
    return Array.from(document.querySelectorAll('#custodios-lista .custodio-fila'))
        .filter(fila => {
            const nombre = fila.querySelector('.combo-input').value.trim();
            const gpsId = fila.querySelector('.combo-gps-value').value;
            return nombre || gpsId;
        })
        .map(fila => fila.querySelector('.select-tipo-custodio').value);
}

function calcularTipoServicioPrincipal(tipos) {
    if (tipos.includes('Patrulla Reforzada')) return 'Patrulla Reforzada';
    if (tipos.includes('Patrulla Estándar')) return 'Patrulla Estándar';
    return tipos[0] || '';
}

function validarCustodiosPorTipo() {
    const filas = Array.from(document.querySelectorAll('#custodios-lista .custodio-fila'));
    const filasConDatos = filas.filter(fila => {
        const nombre = fila.querySelector('.combo-input').value.trim();
        const gpsId = fila.querySelector('.combo-gps-value').value;
        return nombre || gpsId;
    });

    if (filasConDatos.length === 0) {
        return { ok: false, mensaje: 'Agrega al menos un custodio o GPS a la operación.' };
    }

    for (const fila of filasConDatos) {
        const tipo = fila.querySelector('.select-tipo-custodio').value;
        const nombre = fila.querySelector('.combo-input').value.trim();
        const gpsId = fila.querySelector('.combo-gps-value').value;

        if (TIPOS_SOLO_GPS.includes(tipo) && !gpsId) {
            return { ok: false, mensaje: `Selecciona un GPS para la fila de tipo "${tipo}".` };
        }
        if (tipo === 'Custodio + GPS' && (!nombre || !gpsId)) {
            return { ok: false, mensaje: 'El servicio "Custodio + GPS" requiere un custodio y un GPS juntos.' };
        }
        if (!TIPOS_SOLO_GPS.includes(tipo) && tipo !== 'Custodio + GPS' && !nombre) {
            return { ok: false, mensaje: `Selecciona un custodio para la fila de tipo "${tipo}".` };
        }
    }

    for (const tipo in REQUISITOS_CUSTODIOS) {
        const requerido = REQUISITOS_CUSTODIOS[tipo];
        const actual = contarCustodiosPorTipo(tipo);
        if (actual > 0 && actual !== requerido) {
            return {
                ok: false,
                mensaje: `${tipo} requiere exactamente ${requerido} custodio${requerido > 1 ? 's' : ''}. Actualmente tienes ${actual}.`
            };
        }
    }
    return { ok: true };
}

async function cargarOperaciones() {
    try {
        const res = await fetch('api/listar_operaciones.php');
        const data = await safeJson(res);
        if (data && data.success === false) {
            document.getElementById('tabla-operaciones-body').innerHTML =
                `<tr><td colspan="11" style="text-align:center;color:var(--danger);padding:2rem;">${data.message}</td></tr>`;
            return;
        }
        operaciones = Array.isArray(data) ? data : [];
        renderOperaciones();
        if (document.getElementById('modal-tarjetas').classList.contains('active')) {
            renderTarjetasOperaciones();
        }
    } catch (err) {
        console.error('Error cargando operaciones:', err);
        document.getElementById('tabla-operaciones-body').innerHTML =
            '<tr><td colspan="11" style="text-align:center;color:var(--danger);padding:2rem;">Error al cargar operaciones</td></tr>';
    }
}

function renderOperaciones() {
    const fecha = document.getElementById('filtro-fecha').value;
    const estado = document.getElementById('filtro-estado').value;
    const zonaFiltro = document.getElementById('filtro-zona').value;
    const texto = document.getElementById('filtro-texto').value.toLowerCase().trim();

    let datos = operaciones.filter(op => {
        if (!estado && op.estado === 'cancelado') return false;
        if (fecha && op.fecha_servicio !== fecha) return false;
        if (estado && op.estado !== estado) return false;
        if (zonaFiltro) {
            const custodiosOp = op.custodios || [];
            const perteneceAZona = custodiosOp.some(c => (c.zona || '') === zonaFiltro);
            if (!perteneceAZona) return false;
        }
        if (texto) {
            const bolsa = [op.cliente, op.origen, op.destino, op.patrullero_nombre].join(' ').toLowerCase();
            if (!bolsa.includes(texto)) return false;
        }
        return true;
    });

    // Orden: de la hora más próxima a la más lejana
    datos = [...datos].sort((a, b) => (a.fecha_servicio + (a.hora_servicio||'')).localeCompare(b.fecha_servicio + (b.hora_servicio||'')));

    const contador = document.getElementById('contador-resultados');
    const baseTotal = estado === 'cancelado'
        ? operaciones.filter(o => o.estado === 'cancelado').length
        : operaciones.filter(o => o.estado !== 'cancelado').length;
    const totalTexto = datos.length === baseTotal
        ? `${baseTotal} operación${baseTotal === 1 ? '' : 'es'}${estado === 'cancelado' ? ' cancelada(s)' : ''}`
        : `Mostrando ${datos.length} de ${baseTotal} operaciones`;
    if (contador) contador.textContent = totalTexto;

    const tbody = document.getElementById('tabla-operaciones-body');
    if (datos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="11"><div class="empty-state"><i class="fas fa-route"></i>No hay operaciones para mostrar</div></td></tr>`;
        return;
    }

    tbody.innerHTML = datos.map(op => {
        const custodios = op.custodios || [];

        // Columna CUSTODIO: nombre de la persona y/o el GPS de cada fila (una línea por fila)
        const lineasCustodio = custodios.map(c => {
            const partes = [];
            if (c.nombre) partes.push(escapeHtml(c.nombre) + (c.zona ? ` <span style="opacity:.65;font-size:.85em;">(${escapeHtml(c.zona)})</span>` : ''));
            if (c.gps_imei) partes.push(`<span class="gps-parte">GPS ${escapeHtml(c.gps_imei)}</span>`);
            else if (c.gps_id) partes.push(`<span class="gps-parte">GPS #${c.gps_id}</span>`);
            return partes.length ? `<span class="custodio-linea">${partes.join(' · ')}</span>` : '';
        }).filter(Boolean);
        const custodioHtml = lineasCustodio.length ? lineasCustodio.join('') : '—';

        const tiposPresentes = [...new Set(custodios.map(c => c.tipo_servicio).filter(Boolean))];
        const servicioHtml = tiposPresentes.length ? tiposPresentes.join(' / ') : (op.tipo_servicio || '—');

        // Columna DATOS: conductor / teléfono / furgón / placa del transporte del cliente (todos opcionales)
        const datosPartes = [];
        if (op.conductor) datosPartes.push(escapeHtml(op.conductor));
        if (op.telefono) datosPartes.push(escapeHtml(op.telefono));
        if (op.furgon) datosPartes.push(escapeHtml(op.furgon));
        if (op.placa) datosPartes.push(escapeHtml(op.placa));
        const datosHtml = datosPartes.length ? datosPartes.join(' &nbsp;·&nbsp; ') : '<span class="dato-vacio">Sin datos de transporte</span>';

        const puedeGestionar = op.estado !== 'cancelado' && op.estado !== 'finalizado';
        return `
        <tr>
            <td class="td-servicio">${servicioHtml}</td>
            <td class="td-fecha">${formatFecha(op.fecha_servicio)}</td>
            <td class="td-cliente">${escapeHtml(op.cliente)}</td>
            <td class="td-datos">${datosHtml}</td>
            <td class="td-hora">${(op.hora_servicio || '').slice(0,5)}</td>
            <td class="td-ruta">${escapeHtml(op.origen)}</td>
            <td class="td-ruta">${escapeHtml(op.destino)}</td>
            <td class="td-custodio">${custodioHtml}</td>
            <td class="td-piloto">${op.patrullero_nombre ? escapeHtml(op.patrullero_nombre) : '<span class="text-muted">Sin asignar</span>'}</td>
            <td><span class="badge badge-${op.estado}"><span class="badge-dot"></span>${ESTADO_LABEL[op.estado] || op.estado}</span></td>
            <td>
                <div class="actions-cell">
                    <button class="btn-icon" title="Editar operación" onclick='abrirModalEditar(${JSON.stringify(op).replace(/'/g,"&#39;")})'><i class="fas fa-pen"></i></button>
                    ${puedeGestionar ? `<button class="btn-icon btn-icon-danger" title="Cancelar operación" onclick="cancelarOperacion(${op.id})"><i class="fas fa-ban"></i></button>` : ''}
                    ${ES_ADMIN ? `<button class="btn-icon btn-icon-danger" title="Eliminar permanentemente" onclick="eliminarOperacion(${op.id})"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function formatFecha(fechaISO) {
    if (!fechaISO) return '';
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const [y, m, d] = fechaISO.split('-');
    const mesIdx = parseInt(m, 10) - 1;
    if (isNaN(mesIdx) || !meses[mesIdx]) return fechaISO;
    return `${d} ${meses[mesIdx]} ${y}`;
}

// ------------------------------------------------------------
// Modal "Ver todo en detalle": vista alterna en tarjetas, con
// todos los servicios (sin excluir cancelados por defecto) y su
// información completa: piloto, ruta, tipos, custodios y GPS.
// ------------------------------------------------------------
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function servicioLlevaGPS(custodios) {
    if (!Array.isArray(custodios)) return false;
    return custodios.some(c => c.gps_imei || c.gps_id || (c.tipo_servicio && /gps|marchamo|ruta segura/i.test(c.tipo_servicio)));
}

const ICONOS_ESTADO_TARJETA = { pendiente:'fa-clock', en_ruta:'fa-truck-fast', finalizado:'fa-circle-check', cancelado:'fa-ban' };

function abrirModalTarjetas() {
    document.getElementById('tf-fecha').value = '';
    document.getElementById('tf-estado').value = '';
    document.getElementById('tf-texto').value = '';
    renderTarjetasOperaciones();
    document.getElementById('modal-tarjetas').classList.add('active');
}

function cerrarModalTarjetas() {
    document.getElementById('modal-tarjetas').classList.remove('active');
}

function renderTarjetasOperaciones() {
    const fecha = document.getElementById('tf-fecha').value;
    const estado = document.getElementById('tf-estado').value;
    const texto = document.getElementById('tf-texto').value.toLowerCase().trim();

    // A diferencia de la tabla principal, aquí SÍ se muestran todos los
    // servicios por defecto (incluidos cancelados), sin excepciones.
    let datos = operaciones.filter(op => {
        if (fecha && op.fecha_servicio !== fecha) return false;
        if (estado && op.estado !== estado) return false;
        if (texto) {
            const bolsa = [op.cliente, op.origen, op.destino, op.patrullero_nombre].join(' ').toLowerCase();
            if (!bolsa.includes(texto)) return false;
        }
        return true;
    });

    const contador = document.getElementById('tf-contador');
    contador.textContent = datos.length === operaciones.length
        ? `${operaciones.length} servicio${operaciones.length === 1 ? '' : 's'} en total`
        : `Mostrando ${datos.length} de ${operaciones.length} servicios`;

    const cont = document.getElementById('tarjetas-operaciones-lista');
    if (datos.length === 0) {
        cont.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-route"></i>No hay servicios para mostrar</div>`;
        return;
    }

    // Orden: de la hora más próxima a la más lejana
    datos = [...datos].sort((a, b) => (a.fecha_servicio + (a.hora_servicio||'')).localeCompare(b.fecha_servicio + (b.hora_servicio||'')));

    cont.innerHTML = datos.map(renderTarjetaServicio).join('');
}

function renderTarjetaServicio(op) {
    const custodios = Array.isArray(op.custodios) ? op.custodios : [];
    const llevaGPS = servicioLlevaGPS(custodios);
    const icono = ICONOS_ESTADO_TARJETA[op.estado] || 'fa-circle';
    const puedeGestionar = op.estado !== 'cancelado' && op.estado !== 'finalizado';

    const tiposPresentes = [...new Set(custodios.map(c => c.tipo_servicio).filter(Boolean))];
    const tiposHtml = tiposPresentes.length
        ? `<div class="tarjetas-filters" style="margin-bottom:0.75rem;gap:0.4rem;">${tiposPresentes.map(t => `<span class="tipo-pill">${escapeHtml(t)}</span>`).join('')}</div>`
        : '';

    const personalHtml = custodios.length
        ? `<div class="tarjeta-seccion-label"><i class="fas fa-users"></i> Personal / GPS asignado</div>
           <div class="tarjeta-personal-lista">${custodios.map(c => {
               const tienePersona = !!c.nombre;
               const gpsTexto = c.gps_imei ? c.gps_imei : (c.gps_id ? ('GPS #' + c.gps_id) : '');
               const tieneGPS = !!gpsTexto;
               if (!tienePersona && !tieneGPS) return '';

               // Dispositivo GPS sin custodio: fila propia, sin mezclar "sin custodio" con el dato del GPS
               if (!tienePersona && tieneGPS) {
                   return `<div class="tarjeta-personal-item tarjeta-personal-item-gps">
                       <div class="tarjeta-personal-item-left"><i class="fas fa-satellite-dish"></i><span>${c.tipo_servicio ? escapeHtml(c.tipo_servicio) : 'Dispositivo GPS'}</span></div>
                       <span class="chip-gps" style="background:#fde8e9;color:#a90f15;font-size:0.7rem;font-weight:700;padding:0.2rem 0.55rem;border-radius:10px;display:inline-flex;align-items:center;gap:0.3rem;white-space:nowrap;"><i class="fas fa-hashtag"></i>${escapeHtml(gpsTexto)}</span>
                   </div>`;
               }

               // Custodio (persona), con GPS adicional si aplica
               const gpsChip = tieneGPS
                   ? `<span class="chip-gps" style="background:#fde8e9;color:#a90f15;font-size:0.7rem;font-weight:700;padding:0.2rem 0.55rem;border-radius:10px;display:inline-flex;align-items:center;gap:0.3rem;white-space:nowrap;"><i class="fas fa-satellite-dish"></i>${escapeHtml(gpsTexto)}</span>`
                   : '';
               return `<div class="tarjeta-personal-item">
                   <div class="tarjeta-personal-item-left"><i class="fas fa-user-shield"></i><span>${escapeHtml(c.nombre)}${c.tipo_servicio ? ' · ' + escapeHtml(c.tipo_servicio) : ''}</span></div>
                   ${gpsChip}
               </div>`;
           }).filter(Boolean).join('')}</div>`
        : '';

    const pilotoHtml = op.patrullero_nombre
        ? `<div class="tarjeta-piloto"><i class="fas fa-id-badge"></i> Piloto: ${escapeHtml(op.patrullero_nombre)}</div>`
        : `<div class="tarjeta-piloto sin-asignar"><i class="fas fa-id-badge"></i> Sin piloto asignado</div>`;

    const datosTransporte = [
        op.conductor ? `<div><span class="tarjeta-seccion-label" style="margin-bottom:0;display:inline;">Conductor</span> ${escapeHtml(op.conductor)}</div>` : '',
        op.telefono ? `<div><span class="tarjeta-seccion-label" style="margin-bottom:0;display:inline;">Teléfono</span> ${escapeHtml(op.telefono)}</div>` : '',
        op.furgon ? `<div><span class="tarjeta-seccion-label" style="margin-bottom:0;display:inline;">Furgón</span> ${escapeHtml(op.furgon)}</div>` : '',
        op.placa ? `<div><span class="tarjeta-seccion-label" style="margin-bottom:0;display:inline;">Placa</span> ${escapeHtml(op.placa)}</div>` : '',
    ].filter(Boolean);
    const transporteHtml = datosTransporte.length
        ? `<div class="tarjeta-ruta-box" style="gap:0.3rem;font-size:0.85rem;">
               <div class="tarjeta-seccion-label"><i class="fas fa-truck"></i> Datos del transporte</div>
               ${datosTransporte.join('')}
           </div>`
        : '';

    return `
    <div class="tarjeta-servicio estado-${op.estado || ''}">
        <div class="tarjeta-header">
            <div>
                <div class="tarjeta-id">#${op.id}</div>
                <div class="tarjeta-cliente">${escapeHtml(op.cliente)}</div>
                <div class="tarjeta-fecha"><i class="fas fa-calendar-day"></i> ${formatFecha(op.fecha_servicio)}${op.hora_servicio ? ' · ' + op.hora_servicio.slice(0,5) : ''}</div>
            </div>
            <div class="tarjeta-badges">
                <span class="badge badge-${op.estado}"><i class="fas ${icono}"></i>${ESTADO_LABEL[op.estado] || op.estado}</span>
                ${llevaGPS ? `<span class="badge badge-gps"><i class="fas fa-satellite-dish"></i> Con GPS</span>` : ''}
            </div>
        </div>
        <div class="tarjeta-body">
            ${pilotoHtml}
            ${transporteHtml}
            <div class="tarjeta-ruta-box">
                <div class="ruta-punto origen"><span class="ruta-label">Origen</span><i class="fas fa-circle" style="color:var(--success);"></i><span class="lugar">${escapeHtml(op.origen)}</span></div>
                <div class="ruta-punto destino"><span class="ruta-label">Destino</span><i class="fas fa-map-marker-alt" style="color:var(--danger);"></i><span class="lugar">${escapeHtml(op.destino)}</span></div>
            </div>
            ${tiposHtml}
            ${personalHtml}
            ${op.observaciones ? `<div class="tarjeta-obs"><i class="fas fa-note-sticky"></i><span>${escapeHtml(op.observaciones)}</span></div>` : ''}
            <div class="tarjeta-acciones">
                <button class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;" onclick='cerrarModalTarjetas();abrirModalEditar(${JSON.stringify(op).replace(/'/g,"&#39;")})'><i class="fas fa-pen"></i> Editar</button>
                ${puedeGestionar ? `<button class="btn btn-danger btn-sm" style="flex:1;justify-content:center;" onclick="cancelarOperacion(${op.id})"><i class="fas fa-ban"></i> Cancelar</button>` : ''}
                ${ES_ADMIN ? `<button class="btn btn-danger btn-sm" style="flex:1;justify-content:center;" onclick="eliminarOperacion(${op.id})"><i class="fas fa-trash"></i> Eliminar</button>` : ''}
            </div>
        </div>
    </div>`;
}

function abrirModalNueva() {
    document.getElementById('form-operacion').reset();
    document.getElementById('op-id').value = '';
    document.getElementById('modal-operacion-titulo').innerHTML = '<i class="fas fa-route"></i> Nueva Operación';
    document.getElementById('op-estado-wrapper').style.display = 'none';
    modoEdicion = false;
    resetCustodiosLista();
    document.getElementById('modal-operacion').classList.add('active');
}

function abrirModalEditar(op) {
    document.getElementById('op-id').value = op.id;
    document.getElementById('op-fecha').value = op.fecha_servicio;
    modoEdicion = true;
    document.getElementById('op-hora').value = (op.hora_servicio || '').slice(0,5);
    document.getElementById('op-cliente').value = op.cliente;
    document.getElementById('op-conductor').value = op.conductor || '';
    // Separa el prefijo de país (si viene guardado) del resto del número,
    // para que el select de prefijo y el input queden bien poblados al editar.
    (function poblarTelefono(telefonoGuardado) {
        const selectPrefijo = document.getElementById('op-telefono-prefijo');
        const inputNumero = document.getElementById('op-telefono');
        const texto = (telefonoGuardado || '').trim();
        const match = texto.match(/^(\+\d{1,3})\s*(.*)$/);
        if (match && [...selectPrefijo.options].some(o => o.value === match[1])) {
            selectPrefijo.value = match[1];
            inputNumero.value = match[2];
        } else {
            selectPrefijo.value = '+504';
            inputNumero.value = texto;
        }
    })(op.telefono);
    document.getElementById('op-placa').value = op.placa || '';
    document.getElementById('op-furgon').value = op.furgon || '';
    document.getElementById('op-origen').value = op.origen;
    document.getElementById('op-destino').value = op.destino;
    document.getElementById('op-patrullero').value = op.patrullero_id || '';
    document.getElementById('op-patrullero-input').value = op.patrullero_id ? (op.patrullero_nombre || (patrulleros.find(p => String(p.id) === String(op.patrullero_id)) || {}).nombre || '') : '';
    document.getElementById('op-observaciones').value = op.observaciones || '';
    document.getElementById('op-estado').value = op.estado;
    document.getElementById('op-estado-wrapper').style.display = 'block';

    // listar_operaciones.php ya devuelve cada fila con su propio custodio y/o GPS
    const horaOp = (op.hora_servicio || '').slice(0,5);
    const listaCustodios = (op.custodios || []).map(c => ({
        custodio_id:   c.custodio_id,
        gps_id:        c.gps_id,
        gps_imei:      c.gps_imei,
        tipo_servicio: c.tipo_servicio || 'Custodio',
        hora:          horaOp,
    }));
    resetCustodiosLista(listaCustodios);

    document.getElementById('modal-operacion-titulo').innerHTML = '<i class="fas fa-route"></i> Editar Operación #' + op.id;
    document.getElementById('modal-operacion').classList.add('active');
}

function cerrarModalOperacion() {
    document.getElementById('modal-operacion').classList.remove('active');
}

function obtenerFilasConDatos() {
    return Array.from(document.querySelectorAll('#custodios-lista .custodio-fila')).map(fila => {
        const tipo = fila.querySelector('.select-tipo-custodio').value;
        const nombre = fila.querySelector('.combo-input').value.trim();
        const custodioId = fila.querySelector('.combo-value').value;
        const gpsId = fila.querySelector('.combo-gps-value').value;
        const horaInput = fila.querySelector('.fila-hora-input');
        const hora = horaInput ? horaInput.value : '';
        return { tipo, custodioId, gpsId, hora, tieneDatos: !!(nombre || gpsId) };
    }).filter(f => f.tieneDatos);
}

function agruparFilasPorHora(filas, horaGeneral) {
    const grupos = {};
    filas.forEach(f => {
        const hora = f.hora || horaGeneral;
        if (!grupos[hora]) grupos[hora] = [];
        grupos[hora].push(f);
    });
    return grupos;
}

function validarFilasGrupo(filas) {
    if (filas.length === 0) {
        return { ok: false, mensaje: 'Agrega al menos un custodio o GPS a la operación.' };
    }
    for (const f of filas) {
        if (TIPOS_SOLO_GPS.includes(f.tipo) && !f.gpsId) {
            return { ok: false, mensaje: `Selecciona un GPS para la fila de tipo "${f.tipo}".` };
        }
        if (f.tipo === 'Custodio + GPS' && (!f.custodioId || !f.gpsId)) {
            return { ok: false, mensaje: 'El servicio "Custodio + GPS" requiere un custodio y un GPS juntos.' };
        }
        if (!TIPOS_SOLO_GPS.includes(f.tipo) && f.tipo !== 'Custodio + GPS' && !f.custodioId) {
            return { ok: false, mensaje: `Selecciona un custodio para la fila de tipo "${f.tipo}".` };
        }
    }
    for (const tipo in REQUISITOS_CUSTODIOS) {
        const requerido = REQUISITOS_CUSTODIOS[tipo];
        const actual = filas.filter(f => f.tipo === tipo).length;
        if (actual > 0 && actual !== requerido) {
            return { ok: false, mensaje: `${tipo} requiere exactamente ${requerido} custodio${requerido > 1 ? 's' : ''} (tienes ${actual}).` };
        }
    }
    return { ok: true };
}

async function guardarOperacion(event) {
    event.preventDefault();

    const horaGeneral = document.getElementById('op-hora').value;
    const filas = obtenerFilasConDatos();
    const id = document.getElementById('op-id').value;

    const datosComunes = {
        fecha_servicio: document.getElementById('op-fecha').value,
        cliente: document.getElementById('op-cliente').value,
        conductor: document.getElementById('op-conductor').value,
        telefono: (function() {
            const prefijo = document.getElementById('op-telefono-prefijo').value;
            const numero = document.getElementById('op-telefono').value.trim();
            return numero ? (prefijo + ' ' + numero) : '';
        })(),
        placa: document.getElementById('op-placa').value,
        furgon: document.getElementById('op-furgon').value,
        origen: document.getElementById('op-origen').value,
        destino: document.getElementById('op-destino').value,
        patrullero_id: document.getElementById('op-patrullero').value,
        observaciones: document.getElementById('op-observaciones').value,
        estado: document.getElementById('op-estado').value
    };

    function construirFormData(hora, filasGrupo, idGrupo) {
        const fd = new FormData();
        fd.set('id', idGrupo || '');
        fd.set('fecha_servicio', datosComunes.fecha_servicio);
        fd.set('hora_servicio', hora);
        fd.set('cliente', datosComunes.cliente);
        fd.set('conductor', datosComunes.conductor);
        fd.set('telefono', datosComunes.telefono);
        fd.set('placa', datosComunes.placa);
        fd.set('furgon', datosComunes.furgon);
        fd.set('origen', datosComunes.origen);
        fd.set('destino', datosComunes.destino);
        fd.set('patrullero_id', datosComunes.patrullero_id);
        fd.set('observaciones', datosComunes.observaciones);
        if (idGrupo) fd.set('estado', datosComunes.estado);
        fd.set('tipo_servicio', calcularTipoServicioPrincipal(filasGrupo.map(f => f.tipo)));
        filasGrupo.forEach(f => {
            fd.append('custodios_tipo[]', f.tipo);
            fd.append('custodios_id[]', f.custodioId || '');
            fd.append('custodios_gps_id[]', f.gpsId || '');
        });
        return fd;
    }

    async function enviar(fd, endpoint) {
        const res = await fetch(endpoint, { method: 'POST', body: fd });
        const raw = await res.text();
        try { return JSON.parse(raw); }
        catch (e) {
            console.error('Respuesta no-JSON del servidor:', raw);
            return { success: false, message: 'El servidor devolvió una respuesta inesperada. Revisa la consola (F12).' };
        }
    }

    const grupos = agruparFilasPorHora(filas, horaGeneral);
    const horas = Object.keys(grupos).sort();

    if (horas.length === 0 || horas.some(h => !h)) {
        mostrarMensaje('error', 'Define la hora de cada custodio/GPS asignado.');
        return;
    }
    for (const hora of horas) {
        const validacion = validarFilasGrupo(grupos[hora]);
        if (!validacion.ok) {
            mostrarMensaje('error', (horas.length > 1 ? `Hora ${hora}: ` : '') + validacion.mensaje);
            return;
        }
    }

    // Si estamos editando, la operación #id conserva el grupo cuya hora coincide con
    // la hora general (o el primero si esa hora ya no tiene filas); el resto de horas
    // se guardan como operaciones nuevas.
    const horaConservada = id ? (grupos[horaGeneral] ? horaGeneral : horas[0]) : null;

    const errores = [];
    let actualizada = false;
    let creadas = 0;
    for (const hora of horas) {
        const esLaEditada = id && hora === horaConservada;
        const endpoint = esLaEditada ? 'api/editar_operacion.php' : 'api/guardar_operacion.php';
        const fd = construirFormData(hora, grupos[hora], esLaEditada ? id : '');
        try {
            const data = await enviar(fd, endpoint);
            if (data.success) {
                if (esLaEditada) actualizada = true; else creadas++;
            } else {
                errores.push(`${hora}: ${data.message || 'error al guardar'}`);
            }
        } catch (err) {
            errores.push(`${hora}: error de conexión`);
        }
    }

    await cargarOperaciones();
    if (actualizada || creadas > 0) cerrarModalOperacion();

    if (errores.length === 0) {
        if (horas.length === 1) {
            mostrarMensaje('exito', id ? 'Operación actualizada correctamente' : 'Operación creada correctamente');
        } else if (id) {
            mostrarMensaje('exito', `Operación actualizada. Además se crearon ${creadas} operación${creadas === 1 ? '' : 'es'} nueva${creadas === 1 ? '' : 's'} para las otras horas.`);
        } else {
            mostrarMensaje('exito', `Se crearon ${creadas} operaciones correctamente (una por cada hora).`);
        }
    } else {
        const partes = [];
        if (actualizada) partes.push('la operación se actualizó');
        if (creadas > 0) partes.push(`se ${creadas === 1 ? 'creó 1 operación nueva' : 'crearon ' + creadas + ' operaciones nuevas'}`);
        const resumenExito = partes.length ? `${partes.join(' y ')}. ` : '';
        mostrarMensaje('error', `${resumenExito}Fallaron: ${errores.join(' · ')}`);
    }
}

async function cancelarOperacion(id) {
    if (!confirm('¿Estás seguro que quieres cancelar este servicio? Quedará marcado como cancelado y seguirá visible en el historial.')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/cancelar_operacion.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            await cargarOperaciones();
        } else {
            mostrarMensaje('error', data.message || 'Error al cancelar');
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al cancelar');
    }
}

async function eliminarOperacion(id) {
    if (!ES_ADMIN) {
        mostrarMensaje('error', 'Solo un administrador puede eliminar registros permanentemente.');
        return;
    }
    if (!confirm('¿Eliminar PERMANENTEMENTE este registro de la base de datos? Esta acción no se puede deshacer y no dejará historial.')) return;
    if (!confirm('Confirma de nuevo: el registro se borrará por completo (no quedará como "cancelado"). ¿Continuar?')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/eliminar_operacion.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            cerrarModalTarjetas();
            await cargarOperaciones();
            mostrarMensaje('exito', 'Registro eliminado permanentemente');
        } else {
            mostrarMensaje('error', data.message || 'Error al eliminar');
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al eliminar');
    }
}

init();
</script>
</body>
</html>