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
<title>FORZA - Rol de Misiones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ======================================================================
   Estilos idénticos a operaciones.php para mantener el mismo look & feel.
   ====================================================================== */
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
.nav-item:nth-child(1) i{ color:#e31b23; }
.nav-item:nth-child(1):hover{ background:#fde8e9; }
.nav-item:nth-child(2) i{ color:#10b981; }
.nav-item:nth-child(2):hover{ background:#dcfce7; }
.nav-item:nth-child(3) i{ color:#e31b23; }
.nav-item:nth-child(3):hover{ background:#fde8e9; }
.nav-item:nth-child(4) i{ color:#e31b23; }
.nav-item:nth-child(4):hover{ background:#fde8e9; }
.nav-item:nth-child(5) i{ color:#8b5cf6; }
.nav-item:nth-child(5):hover{ background:#ede9fe; }
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
.btn:disabled{ opacity:0.45; cursor:not-allowed; }
.filters{ display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.filters select, .filters input{
    padding:0.6rem 0.9rem; border-radius:10px; border:1px solid var(--border); background:var(--bg-secondary);
    font-size:0.85rem; color:var(--text-primary);
}
.table-container{ overflow-x:auto; border-radius:12px; -webkit-overflow-scrolling:touch; }
table{ width:100%; border-collapse:collapse; }
th{ text-align:center; padding:0.85rem 1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); border-bottom:2px solid var(--border); background:var(--bg-card); white-space:nowrap; }
td{ padding:0.95rem 1rem; border-bottom:1px solid var(--border); font-size:0.88rem; vertical-align:middle; }
.tabla-ancha thead th{
    background:linear-gradient(180deg,var(--primary),var(--primary-dark)); color:#fff; text-align:center;
    font-size:0.74rem; font-weight:800; border-bottom:none;
}
.tabla-ancha tbody tr:nth-child(even){ background:#f8fafc; }
.tabla-ancha td{ text-align:center; }
.td-izq{ text-align:left !important; white-space:normal; }
.td-custodio{ background:#dcfce7; font-weight:700; color:#166534; text-align:left; }
tbody tr:nth-child(even) td{ background:#fbfcfe; }
tr:hover td{ background:#fef2f2; }
.text-muted{ color:var(--text-secondary); }
.badge{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.7rem; border-radius:20px; font-size:0.75rem; font-weight:700; white-space:nowrap; }
.badge-dot{ width:6px; height:6px; border-radius:50%; background:currentColor; }
.badge-vigente{ background:rgba(16,185,129,0.12); color:var(--success); }
.badge-vencido{ background:rgba(239,68,68,0.12); color:var(--danger); }
.actions-cell{ display:flex; gap:0.4rem; justify-content:center; }
.btn-icon{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--border); background:var(--bg-secondary); color:var(--text-primary); cursor:pointer; font-size:0.85rem; transition:background 0.15s; }
.btn-icon:hover{ background:#e2e8f0; }
.btn-icon-danger{ background:rgba(239,68,68,0.08); color:var(--danger); border-color:rgba(239,68,68,0.2); }
.btn-icon-danger:hover{ background:rgba(239,68,68,0.16); }
.btn-icon:disabled:hover{ background:var(--bg-secondary); }
.empty-state{ text-align:center; color:var(--text-secondary); padding:3rem 1rem; }
.empty-state i{ font-size:1.6rem; margin-bottom:0.6rem; display:block; color:var(--border); }
.modal-mensaje-content{ max-width:420px; text-align:center; padding:2rem 1.75rem 1.75rem; }
.modal-mensaje-icono{ width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; }
.modal-mensaje-icono.exito{ background:rgba(16,185,129,0.12); color:var(--success); }
.modal-mensaje-icono.error{ background:rgba(239,68,68,0.12); color:var(--danger); }
.modal-mensaje-titulo{ font-size:1.05rem; font-weight:700; margin-bottom:0.5rem; }
.modal-mensaje-texto{ font-size:0.9rem; color:var(--text-secondary); line-height:1.5; margin-bottom:1.5rem; white-space:pre-line; text-align:left; }
.modal{ display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:200; align-items:center; justify-content:center; }
.modal.active{ display:flex; }
.modal-content{ background:var(--bg-card); border-radius:20px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; margin:1rem; }
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
.form-input:focus, .form-select:focus, .form-textarea:focus{
    outline:none; border-color:var(--primary); background:#fff;
    box-shadow:0 0 0 3px rgba(227,27,35,0.12);
}
.form-hint-suave{ font-size:0.74rem; color:var(--text-secondary); margin-top:0.3rem; }
.hidden{ display:none !important; }
.form-seccion{ background:#fff; border:1px solid var(--border); border-radius:14px; padding:1.15rem 1.25rem 0.35rem; margin-bottom:1.15rem; }
.form-seccion-titulo{ display:flex; align-items:center; gap:0.55rem; font-size:0.76rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--primary); margin-bottom:1rem; }
.form-seccion-titulo i{ font-size:0.85rem; width:16px; text-align:center; }
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
.role-pill.rol-lectura{ background:#eef1f5; color:var(--text-secondary); box-shadow:none; border:1px solid var(--border); }

/* ---------- Específico del Rol de Misiones ---------- */
.sugerencias-box{ background:var(--bg-secondary); border:1px dashed var(--border); border-radius:12px; padding:0.85rem 1rem; max-height:220px; overflow-y:auto; }
.sugerencia-item{ display:flex; align-items:flex-start; gap:0.6rem; padding:0.5rem 0.25rem; border-bottom:1px solid var(--border); font-size:0.83rem; }
.sugerencia-item:last-child{ border-bottom:none; }
.sugerencia-item input[type="checkbox"]{ margin-top:0.2rem; flex-shrink:0; }
.sugerencia-item .sug-detalle{ color:var(--text-secondary); font-size:0.78rem; }
.sugerencias-vacio{ text-align:center; color:var(--text-secondary); font-size:0.82rem; padding:0.75rem; }
.chip-mini{ display:inline-flex; align-items:center; gap:0.3rem; background:#fde8e9; color:var(--primary-dark); border-radius:8px; padding:0.15rem 0.5rem; font-size:0.72rem; font-weight:700; margin:0.1rem; }

/* ---------- Ruleta de Misión Larga ---------- */
.ruleta-nota{ display:flex; align-items:center; gap:0.45rem; justify-content:center; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; border-radius:10px; padding:0.6rem 0.9rem; font-size:0.78rem; font-weight:700; margin-bottom:1.1rem; text-align:left; }
.ruleta-wrap{ position:relative; width:320px; height:320px; margin:0 auto 1.25rem; max-width:100%; }
.ruleta-disco{
    width:100%; height:100%; border-radius:50%; position:relative;
    border:7px solid #fff; box-shadow:0 12px 32px rgba(0,0,0,0.32), inset 0 0 0 3px rgba(0,0,0,0.08);
    background:#eef1f5; transform-origin:50% 50%;
}
.ruleta-etiqueta{
    position:absolute; top:50%; left:50%; width:132px; height:22px; margin-top:-11px; margin-left:-66px;
    text-align:center; transform-origin:66px 11px; pointer-events:none;
    color:#fff; font-size:0.82rem; font-weight:800; letter-spacing:0.2px;
    text-shadow:0 1px 3px rgba(0,0,0,0.6), 0 0 3px rgba(0,0,0,0.45); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.ruleta-etiqueta-excluida{ color:#f3f4f6; opacity:0.9; }
.ruleta-puntero{
    position:absolute; top:-18px; left:50%; transform:translateX(-50%); font-size:2.4rem; z-index:5;
    color:var(--primary-dark); filter:drop-shadow(0 3px 4px rgba(0,0,0,0.4));
}
.ruleta-centro{
    position:absolute; top:50%; left:50%; width:54px; height:54px; margin:-27px 0 0 -27px; border-radius:50%;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); box-shadow:0 4px 14px rgba(0,0,0,0.35), 0 0 0 4px #fff;
    display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.3rem; z-index:4;
}
.ruleta-resultado{ font-size:1.2rem; font-weight:800; margin-top:0.25rem; margin-bottom:0.5rem; color:var(--text-primary); text-align:center; }
.ruleta-vacio{ text-align:center; color:var(--text-secondary); padding:2rem 1rem; }

/* ================= MODO NOCHE ================= */
body.dark-mode{
    --bg-primary:#12141a; --bg-secondary:#1b1e26; --bg-card:#1b1e26;
    --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038;
}
body.dark-mode .tabla-ancha tbody tr:nth-child(even){ background:#20232c; }
body.dark-mode tbody tr:nth-child(even) td{ background:#1e212a; }
body.dark-mode tr:hover td{ background:#262229; }
body.dark-mode .form-seccion{ background:#1b1e26; }
body.dark-mode .form-input:focus, body.dark-mode .form-select:focus, body.dark-mode .form-textarea:focus{ background:#20232c; }
body.dark-mode .role-pill.rol-lectura{ background:#20232c; border-color:var(--border); color:var(--text-secondary); }
body.dark-mode .sugerencias-box{ background:#20232c; }

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
        <strong>Rol de Misiones</strong>
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
        <a class="nav-item" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>
        <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Pilotos</span></a>
        <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Mi Flota</span></a>
        <a class="nav-item active" href="rol_misiones.php"><i class="fas fa-calendar-week"></i><span>Rol de Misiones</span></a>
    </nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <div class="flex justify-between items-center">
            <div>
                <h2>Rol de Misiones Largas — Zona Norte</h2>
                <p>Gira la ruleta para decidir, misión por misión, qué custodio de zona Norte se la lleva. Cada registro vence a las 2 semanas y solo entonces se puede eliminar.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary" onclick="abrirModalNuevo()">
                    <i class="fas fa-plus"></i> Agregar manualmente
                </button>
                <button class="btn btn-primary" onclick="abrirRuleta()">
                    <i class="fas fa-dharmachakra"></i> Girar Ruleta
                </button>
                <button class="btn btn-primary" id="btn-lista-aleatoria" onclick="generarListaAleatoria()">
                    <i class="fas fa-shuffle"></i> Generar lista aleatoria
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex justify-between items-center" style="margin-bottom:1rem;">
            <div>
                <h3 style="font-size:1.05rem;"><i class="fas fa-list-ol"></i> Fila de Turno — Misión Larga (semana actual)</h3>
                <p class="form-hint-suave" style="margin-top:0.2rem;">
                    Se genera un orden aleatorio de custodios. Al que le toca el turno se le sigue asignando
                    la misma misión hasta que se marque el check de "aceptada"; solo entonces pasa el turno
                    al siguiente. La fila se reinicia cada semana.
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btn-generar-fila" onclick="generarFilaTurno()">
                <i class="fas fa-shuffle"></i> Generar fila de esta semana
            </button>
        </div>
        <div class="sugerencias-box" id="fila-turno-lista" style="max-height:none;">
            <div class="sugerencias-vacio">Cargando...</div>
        </div>
    </div>

    <div class="card">
        <div class="filters">
            <input type="week" id="filtro-semana" onchange="renderRegistros()">
            <input type="text" id="filtro-custodio" placeholder="Buscar custodio..." style="flex:1;min-width:200px;" oninput="renderRegistros()">
            <select id="filtro-estado" onchange="renderRegistros()">
                <option value="">Todos los estados</option>
                <option value="vigente">Vigente</option>
                <option value="vencido">Vencido</option>
            </select>
            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('filtro-semana').value='';document.getElementById('filtro-custodio').value='';document.getElementById('filtro-estado').value='';renderRegistros()">
                <i class="fas fa-eraser"></i> Limpiar filtros
            </button>
        </div>

        <div id="contador-resultados" style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.75rem;"></div>

        <div class="table-container">
            <table class="tabla-ancha">
                <thead>
                    <tr>
                        <th>Custodio</th><th>Semana</th><th>N° Misiones Largas</th><th>Turno</th><th>Notas</th>
                        <th>Registrado por</th><th>Vence</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-rol-body">
                    <tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:2rem;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL NUEVO / EDITAR REGISTRO -->
<div class="modal" id="modal-rol">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-rol-titulo"><i class="fas fa-calendar-week"></i> Nuevo registro de misión larga</h3>
            <button class="close-modal" onclick="cerrarModalRol()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-rol" onsubmit="guardarRegistro(event)">
                <input type="hidden" id="rm-id">

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-user-shield"></i> Custodio (Zona Norte)</div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Custodio</label>
                        <select class="form-select" id="rm-custodio" required></select>
                        <div class="form-hint-suave">Solo se listan custodios cuya zona es "Norte".</div>
                    </div>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-calendar-day"></i> Semana</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Semana del registro</label>
                            <input type="week" class="form-input" id="rm-semana" required onchange="cargarSugerencias()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">N° de misiones largas</label>
                            <input type="number" class="form-input" id="rm-numero" min="0" value="0" required>
                        </div>
                    </div>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-route"></i> Sugerencias de Operaciones (Zona Norte, misma semana)</div>
                    <div class="form-hint-suave" style="margin-bottom:0.6rem;">
                        Selecciona las operaciones de <strong>operaciones.php</strong> que respaldan este registro.
                        Es opcional: puedes llenar el rol totalmente a mano.
                    </div>
                    <div class="sugerencias-box" id="sugerencias-lista">
                        <div class="sugerencias-vacio">Elige primero una semana para ver operaciones de zona Norte en ese rango.</div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top:0.6rem;" onclick="usarSeleccionComoNumero()">
                        <i class="fas fa-calculator"></i> Usar cantidad seleccionada como N° de misiones
                    </button>
                </div>

                <div class="form-seccion">
                    <div class="form-seccion-titulo"><i class="fas fa-note-sticky"></i> Notas</div>
                    <div class="form-group" style="margin-bottom:0;">
                        <textarea class="form-textarea" id="rm-notas" placeholder="Observaciones del rol de esta semana (opcional)"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-save"></i> Guardar registro
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL RULETA -->
<div class="modal" id="modal-ruleta">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3><i class="fas fa-dharmachakra"></i> Ruleta de Misión Larga</h3>
            <button class="close-modal" onclick="cerrarRuleta()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <p class="form-hint-suave" style="margin-bottom:1.1rem;">
                Cada custodio de zona Norte tiene la misma probabilidad, excepto quien haya ido a la
                operación más reciente. Al girar, a quien le toque se le suma una misión larga al rol
                de la semana actual.
            </p>
            <div id="ruleta-nota-exclusion" class="ruleta-nota hidden"></div>

            <div id="ruleta-contenido">
                <div class="ruleta-wrap">
                    <div class="ruleta-puntero"><i class="fas fa-caret-down"></i></div>
                    <div class="ruleta-disco" id="ruleta-disco"></div>
                    <div class="ruleta-centro"><i class="fas fa-shield-halved"></i></div>
                </div>

                <div id="ruleta-resultado" class="ruleta-resultado hidden"></div>

                <div style="display:flex; gap:0.6rem; justify-content:center; margin-top:0.5rem;">
                    <button class="btn btn-primary" id="btn-girar" onclick="girarRuleta()">
                        <i class="fas fa-dharmachakra"></i> Girar
                    </button>
                    <button class="btn btn-secondary hidden" id="btn-registrar-resultado" onclick="registrarResultadoRuleta()">
                        <i class="fas fa-save"></i> Registrar misión
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MENSAJE -->
<div class="modal" id="modal-mensaje">
    <div class="modal-content modal-mensaje-content">
        <div class="modal-mensaje-icono" id="modal-mensaje-icono"><i class="fas fa-check"></i></div>
        <div class="modal-mensaje-titulo" id="modal-mensaje-titulo">Título</div>
        <div class="modal-mensaje-texto" id="modal-mensaje-texto">Texto</div>
        <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="cerrarModalMensaje()">Entendido</button>
    </div>
</div>

<script>
const ES_ADMIN = <?= $es_admin ? 'true' : 'false' ?>;

let registros = [];
let custodiosNorte = [];
let operacionesNorte = [];
let seleccionSugerencias = new Set();
let anguloActualRuleta = 0;
let custodioGanadorRuleta = null;
let custodiosExcluidosRuleta = new Set();
let ruletaGirando = false;

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
function cerrarModalMensaje() { document.getElementById('modal-mensaje').classList.remove('active'); }

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

/* ---------- Carga inicial ---------- */
async function init() {
    await Promise.all([cargarCustodiosNorte(), cargarOperacionesNorte()]);
    await cargarRegistros();
    // Sorteo automático: si a algún custodio de zona Norte le falta su registro
    // de la semana actual, se le genera solo, sin pedir nada al admin.
    await generarRolAutomatico(false);
    await cargarRegistros();
}

async function cargarCustodiosNorte() {
    try {
        const res = await fetch('api/get_custodios.php');
        const todos = await safeJson(res);
        custodiosNorte = Array.isArray(todos) ? todos.filter(c => (c.zona || '') === 'Norte') : [];
    } catch (e) {
        custodiosNorte = [];
    }
    const select = document.getElementById('rm-custodio');
    select.innerHTML = custodiosNorte.length
        ? custodiosNorte.map(c => `<option value="${c.id}" data-nombre="${escapeHtml(c.nombre)}">${escapeHtml(c.nombre)}</option>`).join('')
        : '<option value="">No hay custodios de zona Norte registrados</option>';
}

// Reutiliza el mismo endpoint que ya usa operaciones.php, así no dependemos
// de conocer la estructura interna de la tabla de operaciones.
async function cargarOperacionesNorte() {
    try {
        const res = await fetch('api/listar_operaciones.php');
        const todas = await safeJson(res);
        operacionesNorte = Array.isArray(todas)
            ? todas.filter(op => (op.custodios || []).some(c => (c.zona || '') === 'Norte'))
            : [];
    } catch (e) {
        operacionesNorte = [];
    }
}

async function cargarRegistros() {
    try {
        const res = await fetch('api/rol_misiones_listar.php');
        registros = await safeJson(res);
        if (!Array.isArray(registros)) registros = [];
    } catch (e) {
        registros = [];
    }
    renderRegistros();
    renderFilaTurno();
}

/* ---------- Semanas ISO (lunes a domingo) ---------- */
function fechasDeSemanaISO(weekString) {
    if (!weekString || !weekString.includes('-W')) return null;
    const [yearStr, weekStr] = weekString.split('-W');
    const year = parseInt(yearStr, 10);
    const week = parseInt(weekStr, 10);
    const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
    const dow = simple.getUTCDay() || 7;
    const monday = new Date(simple);
    if (dow <= 4) monday.setUTCDate(simple.getUTCDate() - dow + 1);
    else monday.setUTCDate(simple.getUTCDate() + 8 - dow);
    const sunday = new Date(monday);
    sunday.setUTCDate(monday.getUTCDate() + 6);
    return { inicio: monday.toISOString().slice(0, 10), fin: sunday.toISOString().slice(0, 10) };
}

function formatearRangoSemana(inicio, fin) {
    if (!inicio || !fin) return '—';
    const opts = { day: '2-digit', month: 'short' };
    const i = new Date(inicio + 'T00:00:00');
    const f = new Date(fin + 'T00:00:00');
    return `${i.toLocaleDateString('es-HN', opts)} – ${f.toLocaleDateString('es-HN', opts)}`;
}

/* ---------- Modal Nuevo / Editar ---------- */
function abrirModalNuevo() {
    document.getElementById('modal-rol-titulo').innerHTML = '<i class="fas fa-calendar-week"></i> Nuevo registro de misión larga';
    document.getElementById('form-rol').reset();
    document.getElementById('rm-id').value = '';
    seleccionSugerencias = new Set();
    document.getElementById('sugerencias-lista').innerHTML = '<div class="sugerencias-vacio">Elige primero una semana para ver operaciones de zona Norte en ese rango.</div>';
    document.getElementById('modal-rol').classList.add('active');
}

function abrirModalEditar(id) {
    const reg = registros.find(r => String(r.id) === String(id));
    if (!reg) return;
    document.getElementById('modal-rol-titulo').innerHTML = '<i class="fas fa-pen"></i> Editar registro de misión larga';
    document.getElementById('rm-id').value = reg.id;
    document.getElementById('rm-custodio').value = reg.custodio_id;
    document.getElementById('rm-numero').value = reg.numero_misiones;
    document.getElementById('rm-notas').value = reg.notas || '';

    // Reconstruimos el input type="week" a partir de semana_inicio (lunes de esa semana)
    const fecha = new Date(reg.semana_inicio + 'T00:00:00');
    const inicioAno = new Date(Date.UTC(fecha.getUTCFullYear(), 0, 1));
    const dias = Math.floor((fecha - inicioAno) / 86400000);
    const numSemana = Math.ceil((dias + inicioAno.getUTCDay() + 1) / 7);
    document.getElementById('rm-semana').value = `${fecha.getUTCFullYear()}-W${String(numSemana).padStart(2, '0')}`;

    seleccionSugerencias = new Set((reg.operaciones_relacionadas || []).map(o => String(o.id)));
    cargarSugerencias();
    document.getElementById('modal-rol').classList.add('active');
}

function cerrarModalRol() {
    document.getElementById('modal-rol').classList.remove('active');
}

function cargarSugerencias() {
    const weekValue = document.getElementById('rm-semana').value;
    const cont = document.getElementById('sugerencias-lista');
    const rango = fechasDeSemanaISO(weekValue);
    if (!rango) {
        cont.innerHTML = '<div class="sugerencias-vacio">Elige primero una semana para ver operaciones de zona Norte en ese rango.</div>';
        return;
    }
    const enRango = operacionesNorte.filter(op => op.fecha_servicio >= rango.inicio && op.fecha_servicio <= rango.fin);
    if (enRango.length === 0) {
        cont.innerHTML = '<div class="sugerencias-vacio">No hay operaciones de zona Norte registradas en esa semana.</div>';
        return;
    }
    cont.innerHTML = enRango.map(op => {
        const nombres = (op.custodios || []).filter(c => (c.zona || '') === 'Norte').map(c => c.nombre).join(', ');
        const marcado = seleccionSugerencias.has(String(op.id)) ? 'checked' : '';
        return `
        <label class="sugerencia-item">
            <input type="checkbox" value="${op.id}" data-cliente="${escapeHtml(op.cliente || '')}" data-fecha="${escapeHtml(op.fecha_servicio || '')}"
                   onchange="toggleSugerencia(this)" ${marcado}>
            <span>
                <strong>${escapeHtml(op.cliente || 'Sin cliente')}</strong> — ${escapeHtml(op.fecha_servicio || '')}
                <br><span class="sug-detalle">${escapeHtml(op.origen || '')} → ${escapeHtml(op.destino || '')} · Custodio(s): ${escapeHtml(nombres || '—')}</span>
            </span>
        </label>`;
    }).join('');
}

function toggleSugerencia(checkbox) {
    if (checkbox.checked) seleccionSugerencias.add(String(checkbox.value));
    else seleccionSugerencias.delete(String(checkbox.value));
}

function usarSeleccionComoNumero() {
    document.getElementById('rm-numero').value = seleccionSugerencias.size;
}

function obtenerOperacionesRelacionadasJson() {
    const seleccionadas = [];
    document.querySelectorAll('#sugerencias-lista input[type="checkbox"]:checked').forEach(cb => {
        seleccionadas.push({ id: cb.value, cliente: cb.dataset.cliente, fecha: cb.dataset.fecha });
    });
    return JSON.stringify(seleccionadas);
}

/* ---------- Guardar ---------- */
async function guardarRegistro(event) {
    event.preventDefault();

    const id = document.getElementById('rm-id').value;
    const custodioSelect = document.getElementById('rm-custodio');
    const custodioId = custodioSelect.value;
    const custodioNombre = custodioSelect.options[custodioSelect.selectedIndex]?.dataset.nombre || '';
    const weekValue = document.getElementById('rm-semana').value;
    const rango = fechasDeSemanaISO(weekValue);

    if (!custodioId) { mostrarMensaje('error', 'Selecciona un custodio de zona Norte.'); return; }
    if (!rango) { mostrarMensaje('error', 'Selecciona una semana válida.'); return; }

    const fd = new FormData();
    fd.set('id', id || '');
    fd.set('custodio_id', custodioId);
    fd.set('custodio_nombre', custodioNombre);
    fd.set('zona', 'Norte');
    fd.set('semana_inicio', rango.inicio);
    fd.set('semana_fin', rango.fin);
    fd.set('numero_misiones', document.getElementById('rm-numero').value || 0);
    fd.set('notas', document.getElementById('rm-notas').value || '');
    fd.set('operaciones_relacionadas', obtenerOperacionesRelacionadasJson());

    try {
        const res = await fetch('api/rol_misiones_guardar.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            cerrarModalRol();
            await cargarRegistros();
            mostrarMensaje('exito', id ? 'Registro actualizado correctamente' : 'Registro creado correctamente');
        } else {
            mostrarMensaje('error', data.message || 'Error al guardar');
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al guardar');
    }
}

/* ---------- Eliminar (solo admin, solo si ya venció) ---------- */
async function eliminarRegistro(id) {
    if (!ES_ADMIN) {
        mostrarMensaje('error', 'Solo un administrador puede eliminar registros.');
        return;
    }
    if (!confirm('¿Eliminar este registro vencido del rol de misiones? Esta acción no se puede deshacer.')) return;

    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/rol_misiones_eliminar.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            await cargarRegistros();
            mostrarMensaje('exito', 'Registro eliminado correctamente');
        } else {
            mostrarMensaje('error', data.message || 'Error al eliminar');
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al eliminar');
    }
}

/* ---------- Sorteo automático del rol semanal ---------- */
// Rango de misiones que se puede sortear por custodio. Ajusta estos dos
// números si quieres que el sorteo sea más o menos generoso.
const ROL_MIN_MISIONES = 1;
const ROL_MAX_MISIONES = 5;

function obtenerWeekStringDeFecha(fecha) {
    const d = new Date(Date.UTC(fecha.getFullYear(), fecha.getMonth(), fecha.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    return `${d.getUTCFullYear()}-W${String(weekNo).padStart(2, '0')}`;
}

function randomEnRango(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// Sortea la semana actual. Si forzar=false, solo completa a los custodios
// que todavía no tienen registro esta semana (no toca los que ya tienen).
// Si forzar=true, vuelve a sortear a TODOS los custodios de zona Norte
// para la semana actual (botón "Volver a sortear").
async function generarRolAutomatico(forzar) {
    if (!custodiosNorte.length) return;

    const rango = fechasDeSemanaISO(obtenerWeekStringDeFecha(new Date()));
    if (!rango) return;

    const existentesSemana = {};
    registros.forEach(r => { if (r.semana_inicio === rango.inicio) existentesSemana[String(r.custodio_id)] = r; });

    const custodiosAProcesar = forzar
        ? custodiosNorte
        : custodiosNorte.filter(c => !existentesSemana[String(c.id)]);

    if (custodiosAProcesar.length === 0) return;

    // Carga reciente = misiones asignadas en las últimas 2 semanas previas a esta,
    // para cada custodio. A quien tuvo más carga reciente se le sortea en un
    // rango más bajo esta semana; a quien tuvo menos, en un rango más alto.
    const cargaPorCustodio = custodiosNorte.map(c => {
        const historial = registros
            .filter(r => String(r.custodio_id) === String(c.id) && r.semana_inicio < rango.inicio)
            .sort((a, b) => b.semana_inicio.localeCompare(a.semana_inicio))
            .slice(0, 2);
        const carga = historial.reduce((sum, r) => sum + (r.numero_misiones || 0), 0);
        return { id: c.id, carga };
    }).sort((a, b) => a.carga - b.carga);

    function rangoParaCustodio(custodioId) {
        const total = cargaPorCustodio.length;
        const idx = cargaPorCustodio.findIndex(x => String(x.id) === String(custodioId));
        const tercio = total <= 1 ? 0 : Math.floor((idx / total) * 3);
        if (tercio === 0) return [Math.round(ROL_MIN_MISIONES + (ROL_MAX_MISIONES - ROL_MIN_MISIONES) * 0.6), ROL_MAX_MISIONES];
        if (tercio === 1) return [ROL_MIN_MISIONES + 1, Math.max(ROL_MIN_MISIONES + 1, ROL_MAX_MISIONES - 1)];
        return [ROL_MIN_MISIONES, ROL_MIN_MISIONES + 1];
    }

    for (const custodio of custodiosAProcesar) {
        const existente = existentesSemana[String(custodio.id)];
        const [min, max] = rangoParaCustodio(custodio.id);
        const numero = randomEnRango(min, max);

        const fd = new FormData();
        fd.set('id', existente ? existente.id : '');
        fd.set('custodio_id', custodio.id);
        fd.set('custodio_nombre', custodio.nombre);
        fd.set('zona', 'Norte');
        fd.set('semana_inicio', rango.inicio);
        fd.set('semana_fin', rango.fin);
        fd.set('numero_misiones', numero);
        fd.set('notas', existente ? (existente.notas || '') : 'Generado automáticamente por sorteo semanal.');
        fd.set('operaciones_relacionadas', existente ? JSON.stringify(existente.operaciones_relacionadas || []) : '[]');

        try {
            await fetch('api/rol_misiones_guardar.php', { method: 'POST', body: fd });
        } catch (e) {
            console.error('Error generando rol para', custodio.nombre, e);
        }
    }
}

async function volverASortear() {
    if (!confirm('Esto vuelve a sortear el número de misiones de TODOS los custodios de zona Norte para la semana actual. ¿Continuar?')) return;
    await generarRolAutomatico(true);
    await cargarRegistros();
    mostrarMensaje('exito', 'Se volvió a sortear el rol de la semana actual.');
}

/* ---------- Ruleta de Misión Larga ---------- */
// Encuentra quién fue el último en hacer una misión larga (el registro más
// reciente de zona Norte en ESTA tabla, por fecha_registro) y lo devuelve
// como excluido, para que no le vuelva a tocar en el sorteo siguiente.
function obtenerCustodiosExcluidosRuleta() {
    const deNorte = registros.filter(r => (r.zona || '') === 'Norte');
    if (!deNorte.length) return new Set();
    const masReciente = deNorte.reduce((max, r) =>
        (!max || (r.fecha_registro || '') > (max.fecha_registro || '')) ? r : max, null);
    if (!masReciente) return new Set();
    return new Set([String(masReciente.custodio_id)]);
}

// Genera de una sola vez un orden aleatorio para TODOS los custodios de zona
// Norte (a diferencia de la ruleta, que reparte de uno en uno) y guarda ese
// orden directamente, sin pasos manuales. Al custodio que fue a la operación
// más reciente no le puede tocar la primera posición de la lista.
async function generarListaAleatoria() {
    if (!custodiosNorte.length) {
        mostrarMensaje('error', 'No hay custodios de zona Norte registrados para generar la lista.');
        return;
    }
    if (!confirm('Esto genera un orden aleatorio para TODOS los custodios de zona Norte y suma una misión larga a cada uno, guardando todo de una vez. ¿Continuar?')) return;

    const excluidosPrimerLugar = obtenerCustodiosExcluidosRuleta();

    // Fisher-Yates: orden aleatorio con la misma probabilidad para cada custodio.
    const orden = [...custodiosNorte];
    for (let i = orden.length - 1; i > 0; i--) {
        const j = randomEnRango(0, i);
        [orden[i], orden[j]] = [orden[j], orden[i]];
    }

    // Si el sorteo puso de primero a quien fue a la última misión, lo
    // intercambiamos con el primer custodio elegible más adelante en la lista.
    if (orden.length > 1 && excluidosPrimerLugar.size && excluidosPrimerLugar.size < orden.length
        && excluidosPrimerLugar.has(String(orden[0].id))) {
        const idxReemplazo = orden.findIndex((c, i) => i > 0 && !excluidosPrimerLugar.has(String(c.id)));
        if (idxReemplazo > 0) {
            [orden[0], orden[idxReemplazo]] = [orden[idxReemplazo], orden[0]];
        }
    }

    const rango = fechasDeSemanaISO(obtenerWeekStringDeFecha(new Date()));
    if (!rango) return;

    const existentesSemana = {};
    registros.forEach(r => { if (r.semana_inicio === rango.inicio) existentesSemana[String(r.custodio_id)] = r; });

    const boton = document.getElementById('btn-lista-aleatoria');
    if (boton) boton.disabled = true;

    try {
        for (let i = 0; i < orden.length; i++) {
            const custodio = orden[i];
            const posicion = i + 1;
            const existente = existentesSemana[String(custodio.id)];
            const numero = existente ? (Number(existente.numero_misiones) || 0) + 1 : 1;
            const notaOrden = `Posición #${posicion} de la lista aleatoria de misión larga.`;

            const fd = new FormData();
            fd.set('id', existente ? existente.id : '');
            fd.set('custodio_id', custodio.id);
            fd.set('custodio_nombre', custodio.nombre);
            fd.set('zona', 'Norte');
            fd.set('semana_inicio', rango.inicio);
            fd.set('semana_fin', rango.fin);
            fd.set('numero_misiones', numero);
            fd.set('notas', existente && existente.notas ? `${existente.notas}\n${notaOrden}` : notaOrden);
            fd.set('operaciones_relacionadas', JSON.stringify(existente ? (existente.operaciones_relacionadas || []) : []));

            const res = await fetch('api/rol_misiones_guardar.php', { method: 'POST', body: fd });
            await safeJson(res);
        }

        await cargarRegistros();
        const listaTexto = orden.map((c, i) => `${i + 1}. ${c.nombre}`).join('\n');
        mostrarMensaje('exito', `Lista aleatoria guardada para ${orden.length} custodio(s):\n${listaTexto}`);
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al guardar la lista aleatoria.');
    } finally {
        if (boton) boton.disabled = false;
    }
}

/* ---------- Fila de Turno (misión larga con checkbox de aceptación) ---------- */
// Devuelve los registros de la fila de turno de la semana actual (los que
// tienen orden_turno asignado), ordenados por posición.
function obtenerFilaTurnoSemanaActual() {
    const rango = fechasDeSemanaISO(obtenerWeekStringDeFecha(new Date()));
    if (!rango) return { rango: null, fila: [] };
    const fila = registros
        .filter(r => r.semana_inicio === rango.inicio && r.orden_turno !== null && r.orden_turno !== undefined)
        .sort((a, b) => a.orden_turno - b.orden_turno);
    return { rango, fila };
}

// El "turno actual" es siempre el primero de la fila que todavía no tiene el
// check de aceptada. Mientras no se marque, sigue siendo el mismo custodio.
function turnoActualDeFila(fila) {
    return fila.find(r => !r.aceptada) || null;
}

function renderFilaTurno() {
    const cont = document.getElementById('fila-turno-lista');
    const btn = document.getElementById('btn-generar-fila');
    if (!cont || !btn) return;

    const { rango, fila } = obtenerFilaTurnoSemanaActual();

    if (!rango) {
        cont.innerHTML = '<div class="sugerencias-vacio">No se pudo calcular la semana actual.</div>';
        return;
    }

    if (!fila.length) {
        cont.innerHTML = '<div class="sugerencias-vacio">Todavía no se ha generado la fila de esta semana.</div>';
        btn.innerHTML = '<i class="fas fa-shuffle"></i> Generar fila de esta semana';
        btn.dataset.modo = 'generar';
        return;
    }

    const actual = turnoActualDeFila(fila);
    const todosAceptaron = !actual;

    cont.innerHTML = (todosAceptaron
        ? '<div class="sugerencias-vacio" style="color:var(--success);"><i class="fas fa-circle-check"></i> Todos aceptaron su turno esta semana.</div>'
        : '') + fila.map(r => {
        const esActual = actual && String(actual.id) === String(r.id);
        let chip = '';
        if (esActual) chip = '<span class="chip-mini">EN TURNO</span>';
        else if (r.aceptada) chip = '<span class="chip-mini" style="background:#dcfce7;color:#166534;">Aceptada</span>';
        return `
        <label class="sugerencia-item" style="align-items:center;">
            <input type="checkbox" ${r.aceptada ? 'checked' : ''} onchange="marcarAceptada(${r.id}, this.checked)">
            <span style="flex:1;">
                <strong>#${r.orden_turno} · ${escapeHtml(r.custodio_nombre)}</strong> ${chip}
            </span>
        </label>`;
    }).join('');

    btn.innerHTML = '<i class="fas fa-rotate"></i> Regenerar fila (reinicia el progreso)';
    btn.dataset.modo = 'regenerar';
}

// Genera (o regenera) el orden aleatorio de la fila de turno para la semana
// actual. Reutiliza la misma regla de exclusión de la ruleta: quien fue a la
// operación más reciente no puede quedar de primero en la fila.
async function generarFilaTurno() {
    const btn = document.getElementById('btn-generar-fila');
    const modo = btn.dataset.modo || 'generar';

    if (modo === 'regenerar') {
        if (!confirm('Esto reinicia la fila de turno de esta semana: se vuelve a barajar el orden y se pierde el progreso de aceptaciones ya marcado. ¿Continuar?')) return;
    }
    if (!custodiosNorte.length) {
        mostrarMensaje('error', 'No hay custodios de zona Norte registrados para generar la fila.');
        return;
    }

    const rango = fechasDeSemanaISO(obtenerWeekStringDeFecha(new Date()));
    if (!rango) return;

    const excluidosPrimerLugar = obtenerCustodiosExcluidosRuleta();

    const orden = [...custodiosNorte];
    for (let i = orden.length - 1; i > 0; i--) {
        const j = randomEnRango(0, i);
        [orden[i], orden[j]] = [orden[j], orden[i]];
    }
    if (orden.length > 1 && excluidosPrimerLugar.size && excluidosPrimerLugar.size < orden.length
        && excluidosPrimerLugar.has(String(orden[0].id))) {
        const idxReemplazo = orden.findIndex((c, i) => i > 0 && !excluidosPrimerLugar.has(String(c.id)));
        if (idxReemplazo > 0) {
            [orden[0], orden[idxReemplazo]] = [orden[idxReemplazo], orden[0]];
        }
    }

    const existentesSemana = {};
    registros.forEach(r => { if (r.semana_inicio === rango.inicio) existentesSemana[String(r.custodio_id)] = r; });

    btn.disabled = true;
    try {
        for (let i = 0; i < orden.length; i++) {
            const custodio = orden[i];
            const posicion = i + 1;
            const existente = existentesSemana[String(custodio.id)];
            const numero = existente ? (Number(existente.numero_misiones) || 0) + 1 : 1;
            const notaTurno = `Turno #${posicion} de la fila de misión larga.`;

            const fd = new FormData();
            fd.set('id', existente ? existente.id : '');
            fd.set('custodio_id', custodio.id);
            fd.set('custodio_nombre', custodio.nombre);
            fd.set('zona', 'Norte');
            fd.set('semana_inicio', rango.inicio);
            fd.set('semana_fin', rango.fin);
            fd.set('numero_misiones', numero);
            fd.set('notas', existente && existente.notas ? `${existente.notas}\n${notaTurno}` : notaTurno);
            fd.set('operaciones_relacionadas', existente ? JSON.stringify(existente.operaciones_relacionadas || []) : '[]');
            fd.set('orden_turno', posicion);
            fd.set('aceptada', '0'); // la fila arranca de cero: nadie ha aceptado todavía

            const res = await fetch('api/rol_misiones_guardar.php', { method: 'POST', body: fd });
            await safeJson(res);
        }

        await cargarRegistros();
        mostrarMensaje('exito', `Fila de turno generada para ${orden.length} custodio(s) de esta semana.`);
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al generar la fila de turno.');
    } finally {
        btn.disabled = false;
    }
}

// Marca (o desmarca) la aceptación de un registro de la fila. Al aceptar,
// el turno pasa automáticamente al siguiente de la fila (se recalcula solo,
// buscando el primero sin aceptar). Si NO se marca, ese mismo custodio sigue
// siendo "el turno actual" indefinidamente.
async function marcarAceptada(id, checked) {
    const fd = new FormData();
    fd.set('id', id);
    fd.set('aceptada', checked ? '1' : '0');

    try {
        const res = await fetch('api/rol_misiones_marcar_aceptada.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            const reg = registros.find(r => String(r.id) === String(id));
            if (reg) reg.aceptada = checked;
            renderFilaTurno();
            renderRegistros();
        } else {
            mostrarMensaje('error', data.message || 'No se pudo actualizar la aceptación.');
            renderFilaTurno();
            renderRegistros();
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al actualizar la aceptación.');
        renderFilaTurno();
        renderRegistros();
    }
}

function actualizarNotaExclusion() {
    const nota = document.getElementById('ruleta-nota-exclusion');
    if (!nota) return;
    if (!custodiosExcluidosRuleta.size) {
        nota.classList.add('hidden');
        nota.innerHTML = '';
        return;
    }
    const nombres = custodiosNorte
        .filter(c => custodiosExcluidosRuleta.has(String(c.id)))
        .map(c => c.nombre);
    if (!nombres.length) {
        nota.classList.add('hidden');
        nota.innerHTML = '';
        return;
    }
    nota.innerHTML = `<i class="fas fa-circle-info"></i> Esta vuelta no puede tocarle a ${nombres.map(escapeHtml).join(', ')} (fue a la misión más reciente).`;
    nota.classList.remove('hidden');
}

// Construye visualmente el disco de la ruleta: un gajo por cada custodio de
// zona Norte, con colores alternados y su nombre rotado hacia afuera. El
// custodio excluido (última misión) se marca en gris para que se note que
// no puede salir ganador esta vuelta.
function construirRuleta() {
    const disco = document.getElementById('ruleta-disco');
    const n = custodiosNorte.length;
    if (!n) {
        disco.style.background = '#eef1f5';
        disco.innerHTML = '';
        return;
    }
    const paso = 360 / n;
    const colores = ['#e31b23', '#a90f15', '#7a0c11'];
    const gajos = custodiosNorte.map((c, i) => {
        const excluido = custodiosExcluidosRuleta.has(String(c.id));
        const color = excluido ? '#9ca3af' : colores[i % colores.length];
        return `${color} ${i * paso}deg ${(i + 1) * paso}deg`;
    });
    disco.style.background = `conic-gradient(${gajos.join(',')})`;

    disco.innerHTML = custodiosNorte.map((c, i) => {
        const medio = paso * i + paso / 2;
        const excluido = custodiosExcluidosRuleta.has(String(c.id));
        const claseExtra = excluido ? ' ruleta-etiqueta-excluida' : '';
        return `<span class="ruleta-etiqueta${claseExtra}" style="transform: rotate(${medio}deg) translate(0, -124px) rotate(${-medio}deg);">${escapeHtml(c.nombre)}</span>`;
    }).join('');
}

function abrirRuleta() {
    if (!custodiosNorte.length) {
        mostrarMensaje('error', 'No hay custodios de zona Norte registrados para girar la ruleta.');
        return;
    }
    custodiosExcluidosRuleta = obtenerCustodiosExcluidosRuleta();
    // Si excluir dejara la ruleta sin candidatos (ej. solo hay 1 custodio, o
    // todos fueron en la misma última operación), no se excluye a nadie.
    if (custodiosExcluidosRuleta.size >= custodiosNorte.length) custodiosExcluidosRuleta = new Set();
    construirRuleta();
    actualizarNotaExclusion();
    custodioGanadorRuleta = null;
    ruletaGirando = false;
    document.getElementById('ruleta-resultado').classList.add('hidden');
    document.getElementById('ruleta-resultado').innerHTML = '';
    document.getElementById('btn-registrar-resultado').classList.add('hidden');
    document.getElementById('btn-girar').disabled = false;
    document.getElementById('btn-girar').classList.remove('hidden');
    document.getElementById('modal-ruleta').classList.add('active');
}

function cerrarRuleta() {
    if (ruletaGirando) return; // evita cerrar a medio giro
    document.getElementById('modal-ruleta').classList.remove('active');
}

// Gira la ruleta con una animación y elige un custodio al azar con la misma
// probabilidad para todos los elegibles (ruleta pura, sin ponderar por carga
// previa), excluyendo únicamente a quien haya ido a la operación más reciente.
function girarRuleta() {
    const n = custodiosNorte.length;
    if (!n || ruletaGirando) return;

    const indicesElegibles = custodiosNorte
        .map((c, i) => i)
        .filter(i => !custodiosExcluidosRuleta.has(String(custodiosNorte[i].id)));
    const pool = indicesElegibles.length ? indicesElegibles : custodiosNorte.map((c, i) => i);

    ruletaGirando = true;
    const boton = document.getElementById('btn-girar');
    boton.disabled = true;
    document.getElementById('btn-registrar-resultado').classList.add('hidden');
    document.getElementById('ruleta-resultado').classList.add('hidden');

    const paso = 360 / n;
    const indiceGanador = pool[randomEnRango(0, pool.length - 1)];

    // El puntero está fijo arriba del disco (0deg). Giramos varias vueltas
    // completas de más (efecto visual) y terminamos justo con el gajo
    // ganador bajo el puntero.
    const centroGajo = indiceGanador * paso + paso / 2;
    const vueltasExtra = 360 * 6;
    anguloActualRuleta += vueltasExtra + (360 - centroGajo) - (anguloActualRuleta % 360);

    const disco = document.getElementById('ruleta-disco');
    disco.style.transition = 'transform 4.4s cubic-bezier(0.15,0.65,0.1,1)';
    disco.style.transform = `rotate(${anguloActualRuleta}deg)`;

    setTimeout(() => {
        custodioGanadorRuleta = custodiosNorte[indiceGanador];
        const resDiv = document.getElementById('ruleta-resultado');
        resDiv.innerHTML = `🎉 Le tocó a: <strong>${escapeHtml(custodioGanadorRuleta.nombre)}</strong>`;
        resDiv.classList.remove('hidden');
        document.getElementById('btn-registrar-resultado').classList.remove('hidden');
        boton.disabled = false;
        ruletaGirando = false;
    }, 4500);
}

// Registra el resultado de la ruleta como una misión larga más para el
// custodio ganador, en la semana actual (suma 1 si ya tenía registro).
async function registrarResultadoRuleta() {
    if (!custodioGanadorRuleta) return;

    const rango = fechasDeSemanaISO(obtenerWeekStringDeFecha(new Date()));
    if (!rango) return;

    const existente = registros.find(r =>
        String(r.custodio_id) === String(custodioGanadorRuleta.id) && r.semana_inicio === rango.inicio
    );
    const numero = existente ? (Number(existente.numero_misiones) || 0) + 1 : 1;
    const notaRuleta = 'Asignado por ruleta.';

    const fd = new FormData();
    fd.set('id', existente ? existente.id : '');
    fd.set('custodio_id', custodioGanadorRuleta.id);
    fd.set('custodio_nombre', custodioGanadorRuleta.nombre);
    fd.set('zona', 'Norte');
    fd.set('semana_inicio', rango.inicio);
    fd.set('semana_fin', rango.fin);
    fd.set('numero_misiones', numero);
    fd.set('notas', existente && existente.notas ? `${existente.notas}\n${notaRuleta}` : notaRuleta);
    fd.set('operaciones_relacionadas', JSON.stringify(existente ? (existente.operaciones_relacionadas || []) : []));

    const boton = document.getElementById('btn-registrar-resultado');
    boton.disabled = true;
    try {
        const res = await fetch('api/rol_misiones_guardar.php', { method: 'POST', body: fd });
        const data = await safeJson(res);
        if (data.success) {
            await cargarRegistros();
            document.getElementById('modal-ruleta').classList.remove('active');
            mostrarMensaje('exito', `Misión larga registrada para ${custodioGanadorRuleta.nombre}.`);
        } else {
            mostrarMensaje('error', data.message || 'Error al registrar el resultado de la ruleta.');
        }
    } catch (err) {
        mostrarMensaje('error', 'Error de conexión al registrar el resultado de la ruleta.');
    } finally {
        boton.disabled = false;
    }
}

/* ---------- Render tabla ---------- */
function renderRegistros() {
    const tbody = document.getElementById('tabla-rol-body');
    const contador = document.getElementById('contador-resultados');

    const semanaFiltro = document.getElementById('filtro-semana').value;
    const rangoFiltro = semanaFiltro ? fechasDeSemanaISO(semanaFiltro) : null;
    const custodioFiltro = document.getElementById('filtro-custodio').value.trim().toLowerCase();
    const estadoFiltro = document.getElementById('filtro-estado').value;

    let filtrados = registros.filter(r => {
        if (rangoFiltro && r.semana_inicio !== rangoFiltro.inicio) return false;
        if (custodioFiltro && !r.custodio_nombre.toLowerCase().includes(custodioFiltro)) return false;
        if (estadoFiltro && r.estado !== estadoFiltro) return false;
        return true;
    });

    contador.textContent = `${filtrados.length} registro${filtrados.length === 1 ? '' : 's'} de ${registros.length} en total`;

    if (filtrados.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state"><i class="fas fa-inbox"></i>No hay registros que coincidan con los filtros</div></td></tr>`;
        return;
    }

    tbody.innerHTML = filtrados.map(r => {
        const vencido = !!r.vencido;
        const badge = vencido
            ? `<span class="badge badge-vencido"><span class="badge-dot"></span>Vencido</span>`
            : `<span class="badge badge-vigente"><span class="badge-dot"></span>Vigente</span>`;

        const venceTexto = vencido
            ? `Venció hace ${Math.abs(r.dias_para_vencer)} día(s)`
            : `Vence en ${r.dias_para_vencer} día(s)`;

        const notasCorta = r.notas ? escapeHtml(r.notas).slice(0, 60) + (r.notas.length > 60 ? '…' : '') : '<span class="text-muted">—</span>';

        // Columna de turno: solo los registros generados por "Generar fila de
        // esta semana" tienen orden_turno; el resto (sorteo automático,
        // registros manuales) muestran un guion.
        let celdaTurno;
        if (r.orden_turno === null || r.orden_turno === undefined) {
            celdaTurno = '<span class="text-muted">—</span>';
        } else {
            const filaSemana = registros
                .filter(x => x.semana_inicio === r.semana_inicio && x.orden_turno !== null && x.orden_turno !== undefined)
                .sort((a, b) => a.orden_turno - b.orden_turno);
            const actual = filaSemana.find(x => !x.aceptada);
            const esActual = actual && String(actual.id) === String(r.id);
            const chip = esActual ? ' <span class="chip-mini">EN TURNO</span>' : '';
            celdaTurno = `
                <label style="display:inline-flex;align-items:center;gap:0.35rem;white-space:nowrap;">
                    <input type="checkbox" ${r.aceptada ? 'checked' : ''} onchange="marcarAceptada(${r.id}, this.checked)">
                    #${r.orden_turno}${chip}
                </label>`;
        }

        let btnEliminar;
        if (!ES_ADMIN) {
            btnEliminar = '';
        } else if (vencido) {
            btnEliminar = `<button class="btn-icon btn-icon-danger" title="Eliminar (ya venció)" onclick="eliminarRegistro(${r.id})"><i class="fas fa-trash"></i></button>`;
        } else {
            btnEliminar = `<button class="btn-icon" title="Disponible cuando venza" disabled><i class="fas fa-lock"></i></button>`;
        }

        return `
        <tr>
            <td class="td-custodio">${escapeHtml(r.custodio_nombre)}</td>
            <td>${formatearRangoSemana(r.semana_inicio, r.semana_fin)}</td>
            <td><strong>${r.numero_misiones}</strong></td>
            <td>${celdaTurno}</td>
            <td class="td-izq">${notasCorta}</td>
            <td>${escapeHtml(r.creado_por_nombre || '—')}</td>
            <td>${venceTexto}</td>
            <td>${badge}</td>
            <td>
                <div class="actions-cell">
                    <button class="btn-icon" title="Editar" onclick="abrirModalEditar(${r.id})"><i class="fas fa-pen"></i></button>
                    ${btnEliminar}
                </div>
            </td>
        </tr>`;
    }).join('');
}

init();
</script>
</body>
</html>