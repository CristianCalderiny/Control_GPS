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
<title>FORZA - Pilotos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#e31b23; --primary-dark:#a90f15; --primary-light:#fde8e9;
    --secondary:#64748b;
    --success:#10b981; --success-light:#d1fae5;
    --danger:#ef4444; --danger-light:#fee2e2;
    --warning:#f59e0b; --warning-light:#fef3c7;
    --info:#e31b23; --info-light:#fde8e9;
    --bg-app:#f4f5fb; --bg-card:#ffffff;
    --text-primary:#111827; --text-secondary:#6b7280; --border:#e5e7eb;
    --shadow:0 1px 2px rgba(16,24,40,0.06), 0 1px 3px rgba(16,24,40,0.08);
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
.user-chip{ display:flex; align-items:center; gap:0.65rem; font-size:0.85rem; color:var(--text-secondary); }
.user-avatar{ width:38px; height:38px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.9rem; box-shadow:0 3px 8px rgba(227,27,35,0.35); }
.user-chip{ position:relative; }
.user-info{ display:flex; flex-direction:column; line-height:1.2; gap:0.15rem; }
.user-info span{ font-weight:600; color:var(--text-primary); }
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
.sidebar{
    position:fixed; left:0; top:0; width:280px; height:100vh; background:var(--bg-card);
    border-right:1px solid var(--border); display:flex; flex-direction:column; z-index:101;
    transition:width .2s ease;
}
.sidebar-header{ padding:2rem; border-bottom:1px solid var(--border); }
.logo-container{ display:flex; align-items:center; gap:1rem; }
.logo-icon{
    width:50px; height:50px; background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem;
    box-shadow:0 6px 16px rgba(227,27,35,0.35);
}
.logo-text h1{ font-size:1.4rem; font-weight:800; letter-spacing:-0.02em; }
.logo-text p{ font-size:0.72rem; color:var(--text-secondary); margin-top:0.15rem; letter-spacing:0.02em; }
.nav-menu{ flex:1; padding:1rem; overflow-y:auto; }
.nav-item{
    width:100%; padding:0.9rem 1.1rem; margin-bottom:0.35rem; background:transparent; border:none;
    border-radius:12px; display:flex; align-items:center; gap:0.85rem; color:var(--text-secondary);
    font-size:0.92rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:left;
    transition:background .15s ease, color .15s ease;
}
.nav-item i{ width:18px; text-align:center; color:var(--text-secondary); transition:color .15s ease; }
.nav-item:hover{ background:var(--primary-light); color:var(--primary-dark); }
.nav-item:hover i{ color:var(--primary); }
.nav-item.active{ background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; box-shadow:0 4px 12px rgba(227,27,35,0.3); }
.nav-item.active i{ color:#fff; }
.main-content{ margin-left:280px; padding:90px 2rem 2rem; min-height:100vh; transition:margin-left .2s ease; }

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

.stats-row{ display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.stat-card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:1.1rem 1.25rem; box-shadow:var(--shadow); display:flex; align-items:center; gap:0.9rem; }
.stat-icon{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.stat-value{ font-size:1.4rem; font-weight:800; line-height:1; font-family:'Space Grotesk',sans-serif; }
.stat-label{ font-size:0.75rem; color:var(--text-secondary); margin-top:0.2rem; font-weight:600; }
.stat-total .stat-icon{ background:var(--primary-light); color:var(--primary); }
.stat-activo .stat-icon{ background:var(--success-light); color:var(--success); }
.stat-inactivo .stat-icon{ background:#f1f2f4; color:var(--secondary); }

.flex{ display:flex; } .gap-2{ gap:0.5rem; }
.grid-2{ display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:stretch; }
.card{ background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:1.6rem; box-shadow:var(--shadow); }
.card-title{ font-size:1.02rem; font-weight:700; margin-bottom:1.4rem; display:flex; align-items:center; gap:0.6rem; }
.card-title .title-icon{ width:34px; height:34px; border-radius:10px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:0.9rem; }

.btn{ display:inline-flex; align-items:center; gap:0.5rem; padding:0.8rem 1.3rem; border:none; border-radius:11px; font-size:0.9rem; font-weight:700; cursor:pointer; transition:transform .1s ease, box-shadow .15s ease; }
.btn-primary{ background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; width:100%; justify-content:center; box-shadow:0 4px 14px rgba(227,27,35,0.35); }
.btn-primary:hover{ transform:translateY(-1px); box-shadow:0 6px 18px rgba(227,27,35,0.45); }
.btn-secondary{ background:#f3f4f6; color:var(--text-primary); border:1px solid var(--border); }
.btn-secondary:hover{ background:#e5e7eb; }
.btn-danger{ background:var(--danger-light); color:var(--danger); }
.btn-danger:hover{ background:#fecaca; }
.btn-sm{ padding:0.45rem 0.75rem; font-size:0.78rem; border-radius:9px; }

/* Botones de acción circulares (estilo consistente con Operaciones) */
.btn-icon{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:9px; border:1px solid var(--border); background:#f9fafb; color:var(--text-primary); cursor:pointer; font-size:0.82rem; transition:background .15s ease, transform .1s ease; }
.btn-icon:hover{ background:#e5e7eb; transform:translateY(-1px); }
.btn-icon-warning{ background:var(--warning-light); color:#b45309; border-color:rgba(245,158,11,0.25); }
.btn-icon-warning:hover{ background:#fde9b8; }
.btn-icon-danger{ background:var(--danger-light); color:var(--danger); border-color:rgba(239,68,68,0.25); }
.btn-icon-danger:hover{ background:#fecaca; }
.actions-cell{ display:flex; gap:0.4rem; justify-content:flex-end; }
.solo-lectura-nota{ display:flex; align-items:center; gap:0.6rem; background:var(--primary-light); color:var(--primary-dark); border:1px solid rgba(227,27,35,0.15); border-radius:14px; padding:0.9rem 1.1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.25rem; }
.solo-lectura-nota i{ font-size:1rem; }

.form-group{ margin-bottom:1.15rem; }
.form-label{ display:flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; margin-bottom:0.45rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em; }
.form-label i{ font-size:0.75rem; color:var(--primary); }
.form-input, .form-select{
    width:100%; padding:0.8rem 1rem; border:1.5px solid var(--border); border-radius:11px; font-size:0.93rem;
    background:#fafafa; color:var(--text-primary); font-family:inherit; transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.form-input:focus, .form-select:focus{
    outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light);
}
.pin-input-wrap{ position:relative; }
.pin-input-wrap .form-input{ padding-right:2.75rem; }
.pin-toggle-btn{
    position:absolute; right:0.55rem; top:50%; transform:translateY(-50%);
    width:30px; height:30px; border:none; background:transparent; color:var(--text-secondary);
    cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:8px;
    font-size:0.85rem; transition:background .15s ease, color .15s ease;
}
.pin-toggle-btn:hover{ background:var(--primary-light); color:var(--primary); }

.card-listado{ display:flex; flex-direction:column; }
.card-listado .table-container{ flex:1; overflow-x:auto; overflow-y:auto; max-height:calc(4.4rem * 6); border-radius:14px; border:1px solid var(--border); }
.table-container thead th{ position:sticky; top:0; z-index:1; }
table{ width:100%; border-collapse:collapse; }
th{ text-align:left; padding:0.85rem 1.1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); background:#f9fafb; border-bottom:1px solid var(--border); font-weight:700; }
td{ padding:0.95rem 1.1rem; border-bottom:1px solid #f1f2f4; font-size:0.88rem; }
tbody tr:last-child td{ border-bottom:none; }
tbody tr{ transition:background .1s ease; }
tbody tr:hover td{ background:#faf9ff; }

.piloto-chip{ display:inline-flex; align-items:center; gap:0.65rem; font-weight:700; }
.piloto-avatar{ width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; flex-shrink:0; }

.badge{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.32rem 0.75rem; border-radius:20px; font-size:0.74rem; font-weight:700; }
.badge::before{ content:''; width:6px; height:6px; border-radius:50%; }
.badge-activo{ background:var(--success-light); color:#047857; } .badge-activo::before{ background:var(--success); }
.badge-inactivo{ background:#f1f2f4; color:var(--text-secondary); } .badge-inactivo::before{ background:var(--secondary); }

.search-wrap{ position:relative; margin-bottom:1rem; }
.search-wrap i{ position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:0.85rem; }
.search-input{ width:100%; padding:0.75rem 1rem 0.75rem 2.6rem; border:1.5px solid var(--border); border-radius:11px; background:#fafafa; font-size:0.9rem; transition:border-color .15s ease, box-shadow .15s ease; }
.search-input:focus{ outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 4px var(--primary-light); }

.empty-row td, .error-row td{ text-align:center; padding:3rem 1rem; color:var(--text-secondary); }
.error-row td{ color:var(--danger); }

/* ================= MODO NOCHE ================= */
body.dark-mode{
    --bg-app:#12141a; --bg-card:#1b1e26;
    --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038;
}
body.dark-mode .sidebar-toggle-btn{ background:#232733; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .sidebar-toggle-btn:hover{ background:#2c3038; }
body.dark-mode .btn-secondary{ background:#232733; color:var(--text-primary); }
body.dark-mode .btn-secondary:hover{ background:#2c3038; }
body.dark-mode .btn-icon{ background:#1f222a; color:var(--text-primary); }
body.dark-mode .btn-icon:hover{ background:#2c3038; }
body.dark-mode .form-input, body.dark-mode .form-select, body.dark-mode .search-input{ background:#20232c; color:var(--text-primary); border-color:var(--border); }
body.dark-mode .form-input:focus, body.dark-mode .form-select:focus, body.dark-mode .search-input:focus{ background:#20232c; }
body.dark-mode th{ background:#20232c; }
body.dark-mode tbody tr:hover td{ background:#20232c; }
body.dark-mode .stat-inactivo .stat-icon{ background:#20232c; }
body.dark-mode .badge-inactivo{ background:#20232c; }
body.dark-mode .role-pill.rol-lectura{ background:#20232c; border-color:var(--border); color:var(--text-secondary); }

@media (max-width:900px){
  .sidebar{ transform:translateX(-100%); }
  .sidebar.collapsed{ transform:translateX(0); width:280px; }
  .top-bar, .main-content{ left:0; margin-left:0; }
  .top-bar.collapsed, .main-content.collapsed{ margin-left:0; left:0; }
  .grid-2, .stats-row{ grid-template-columns:1fr; }
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
        <strong>Pilotos</strong>
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($usuario_nombre,0,1)) ?></div>
        <div class="user-info">
            <span><?= htmlspecialchars($usuario_nombre) ?></span>
            <?php if ($es_admin): ?>
                <span class="role-pill"><i class="fas fa-user-shield"></i> Administrador</span>
            <?php else: ?>
                <span class="role-pill rol-lectura"><i class="fas fa-eye"></i> Solo lectura</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text"><h1>FORZA</h1><p> PILOTOS · HN</p></div>
        </div>
    </div>
    <nav class="nav-menu">
        <a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
        <a class="nav-item" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>
        <a class="nav-item active" href="pilotos.php"><i class="fas fa-id-card"></i><span>Crear Piloto</span></a>
        <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Crear Vehículo</span></a>
    </nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <h2>Pilotos</h2>
        <p>Registra a los pilotos y consulta el listado</p>
    </div>

    <div class="stats-row">
        <div class="stat-card stat-total">
            <div class="stat-icon"><i class="fas fa-id-card"></i></div>
            <div><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card stat-activo">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-value" id="stat-activo">0</div><div class="stat-label">Activos</div></div>
        </div>
        <div class="stat-card stat-inactivo">
            <div class="stat-icon"><i class="fas fa-circle-minus"></i></div>
            <div><div class="stat-value" id="stat-inactivo">0</div><div class="stat-label">Inactivos</div></div>
        </div>
    </div>

    <div class="<?= $es_admin ? 'grid-2' : '' ?>">
        <?php if ($es_admin): ?>
        <!-- FORMULARIO DE CREACIÓN (solo administradores) -->
        <div class="card">
            <div class="card-title" id="form-titulo">
                <span class="title-icon"><i class="fas fa-id-card"></i></span> Nuevo Piloto
            </div>
            <form id="form-piloto" onsubmit="guardarPiloto(event)">
                <input type="hidden" name="id" id="pil-id">

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user"></i> Nombre completo</label>
                    <input type="text" class="form-input" name="nombre" id="pil-nombre" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Teléfono</label>
                    <input type="tel" class="form-input" name="telefono" id="pil-telefono" autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> PIN de acceso (4 dígitos)</label>
                    <div class="pin-input-wrap">
                        <input type="password" class="form-input" name="pin" id="pil-pin" placeholder="••••" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password">
                        <button type="button" class="pin-toggle-btn" id="pil-pin-toggle" onclick="togglePinVisibility()" title="Mostrar/ocultar PIN"><i class="fas fa-eye"></i></button>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:0.35rem;" id="pil-pin-hint">Con este PIN y su teléfono, el piloto ingresa a ver sus servicios.</div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-map-location-dot"></i> Zona</label>
                    <select class="form-select" name="zona" id="pil-zona">
                        <option value="">Sin asignar</option>
                        <option value="Norte">Norte</option>
                        <option value="Centro">Centro</option>
                        <option value="Sur">Sur</option>
                        <option value="Motorizadas">Motorizadas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-toggle-on"></i> Estado</label>
                    <select class="form-select" name="estado" id="pil-estado">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Guardar Piloto</button>
                </div>
                <div class="flex gap-2" style="margin-top:0.6rem;" id="btn-cancelar-edicion-wrapper" hidden>
                    <button type="button" class="btn btn-secondary" style="width:100%;justify-content:center;" onclick="cancelarEdicion()">Cancelar edición</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- LISTADO -->
        <div class="card card-listado">
            <div class="card-title"><span class="title-icon"><i class="fas fa-list"></i></span> Pilotos registrados</div>
            <?php if (!$es_admin): ?>
                <div class="solo-lectura-nota">
                    <i class="fas fa-circle-info"></i>
                    Estás en modo de solo lectura. Si necesitas crear, editar o eliminar pilotos, contacta a un administrador.
                </div>
            <?php endif; ?>
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" class="search-input" id="buscar-piloto" placeholder="Buscar por nombre o teléfono..." oninput="renderPilotos()">
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Piloto</th><th>Teléfono</th><th>Zona</th><th>Estado</th>
                            <?php if ($es_admin): ?><th style="text-align:right;">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tabla-pilotos-body">
                        <tr class="empty-row"><td colspan="<?= $es_admin ? 5 : 4 ?>">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
let pilotos = [];
const ES_ADMIN = <?= $es_admin ? 'true' : 'false' ?>;
const COLSPAN = ES_ADMIN ? 5 : 4;

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

function iniciales(nombre) {
    return (nombre || '').trim().split(/\s+/).slice(0,2).map(p => p[0]).join('').toUpperCase();
}

async function cargarPilotos() {
    try {
        const res = await fetch('api/piloto_listar.php');
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) {
            console.error('Respuesta no-JSON:', raw);
            document.getElementById('tabla-pilotos-body').innerHTML =
                `<tr class="error-row"><td colspan="${COLSPAN}"><i class="fas fa-triangle-exclamation"></i> Error del servidor. Revisa la consola (F12).</td></tr>`;
            return;
        }
        if (data && data.success === false) {
            document.getElementById('tabla-pilotos-body').innerHTML =
                `<tr class="error-row"><td colspan="${COLSPAN}">${data.message}</td></tr>`;
            return;
        }
        pilotos = Array.isArray(data) ? data : [];
        actualizarStats();
        renderPilotos();
    } catch (err) {
        console.error(err);
        document.getElementById('tabla-pilotos-body').innerHTML =
            `<tr class="error-row"><td colspan="${COLSPAN}">Error de conexión</td></tr>`;
    }
}

function actualizarStats() {
    document.getElementById('stat-total').textContent = pilotos.length;
    document.getElementById('stat-activo').textContent = pilotos.filter(p => p.estado === 'activo').length;
    document.getElementById('stat-inactivo').textContent = pilotos.filter(p => p.estado === 'inactivo').length;
}

function renderPilotos() {
    const texto = document.getElementById('buscar-piloto').value.toLowerCase().trim();
    const datos = pilotos.filter(p =>
        !texto || (p.nombre + ' ' + (p.telefono || '')).toLowerCase().includes(texto)
    );

    const tbody = document.getElementById('tabla-pilotos-body');
    if (datos.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="${COLSPAN}"><i class="fas fa-id-card" style="font-size:1.4rem;display:block;margin-bottom:0.5rem;opacity:0.4;"></i>No hay pilotos registrados</td></tr>`;
        return;
    }

    tbody.innerHTML = datos.map(p => `
        <tr>
            <td>
                <span class="piloto-chip">
                    <span class="piloto-avatar">${iniciales(p.nombre)}</span>
                    ${p.nombre}
                </span>
            </td>
            <td>${p.telefono || '—'}</td>
            <td>${p.zona || '—'}</td>
            <td><span class="badge badge-${p.estado}">${p.estado === 'activo' ? 'Activo' : 'Inactivo'}</span></td>
            ${ES_ADMIN ? `
            <td>
                <div class="actions-cell">
                    <button class="btn-icon" title="Editar piloto" onclick='editarPiloto(${JSON.stringify(p).replace(/'/g,"&#39;")})'><i class="fas fa-pen"></i></button>
                    ${p.estado === 'activo' ? `<button class="btn-icon btn-icon-warning" title="Marcar como inactivo" onclick="inactivarPiloto(${p.id})"><i class="fas fa-toggle-off"></i></button>` : ''}
                    <button class="btn-icon btn-icon-danger" title="Eliminar permanentemente" onclick="eliminarPilotoDefinitivo(${p.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>` : ''}
        </tr>
    `).join('');
}

function editarPiloto(p) {
    document.getElementById('pil-id').value = p.id;
    document.getElementById('pil-nombre').value = p.nombre;
    document.getElementById('pil-telefono').value = p.telefono || '';
    document.getElementById('pil-pin').value = '';
    document.getElementById('pil-pin-hint').textContent = 'Déjalo vacío para no cambiar el PIN actual del piloto.';
    document.getElementById('pil-estado').value = p.estado;
    document.getElementById('pil-zona').value = p.zona || '';
    document.getElementById('form-titulo').innerHTML = '<span class="title-icon"><i class="fas fa-pen"></i></span> Editar Piloto';
    document.getElementById('btn-cancelar-edicion-wrapper').hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function togglePinVisibility() {
    const input = document.getElementById('pil-pin');
    const btn = document.getElementById('pil-pin-toggle');
    const mostrando = input.type === 'text';
    input.type = mostrando ? 'password' : 'text';
    btn.innerHTML = mostrando ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}

function cancelarEdicion() {
    document.getElementById('form-piloto').reset();
    document.getElementById('pil-id').value = '';
    document.getElementById('pil-pin-hint').textContent = 'Con este PIN y su teléfono, el piloto ingresa a ver sus servicios.';
    document.getElementById('form-titulo').innerHTML = '<span class="title-icon"><i class="fas fa-id-card"></i></span> Nuevo Piloto';
    document.getElementById('btn-cancelar-edicion-wrapper').hidden = true;
}

async function guardarPiloto(event) {
    event.preventDefault();
    const id = document.getElementById('pil-id').value;
    const pin = document.getElementById('pil-pin').value.trim();

    if (pin && !/^\d{4}$/.test(pin)) {
        alert('❌ El PIN debe tener exactamente 4 dígitos.');
        return;
    }
    if (!id && !pin) {
        alert('❌ Asigna un PIN de 4 dígitos para que el piloto pueda ingresar.');
        return;
    }

    const formData = new FormData(event.target);
    const endpoint = id ? 'api/piloto_editar.php' : 'api/piloto_guardar.php';

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
            await cargarPilotos();
        } else {
            alert('❌ ' + (data.message || 'Error al guardar el piloto'));
        }
    } catch (err) {
        console.error(err);
        alert('❌ Error de conexión al guardar');
    }
}

async function inactivarPiloto(id) {
    if (!confirm('¿Marcar este piloto como inactivo? Seguirá existiendo en el sistema, solo dejará de estar disponible para asignaciones.')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/piloto_eliminar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            await cargarPilotos();
        } else {
            alert('❌ ' + (data.message || 'Error al marcar como inactivo'));
        }
    } catch (err) {
        alert('❌ Error de conexión');
    }
}

async function eliminarPilotoDefinitivo(id) {
    if (!confirm('¿Eliminar PERMANENTEMENTE este piloto de la base de datos? Esta acción no se puede deshacer.')) return;
    if (!confirm('Confirma de nuevo: el registro se borrará por completo y no quedará historial de este piloto. ¿Continuar?')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res = await fetch('api/piloto_borrar.php', { method: 'POST', body: fd });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) {
            console.error('Respuesta no-JSON del servidor:', raw);
            alert('❌ El servidor devolvió una respuesta inesperada. Revisa la consola (F12).');
            return;
        }
        if (data.success) {
            await cargarPilotos();
            alert('✅ Piloto eliminado permanentemente');
        } else {
            alert('❌ ' + (data.message || 'Error al eliminar el piloto'));
        }
    } catch (err) {
        alert('❌ Error de conexión al eliminar');
    }
}

cargarPilotos();
</script>
</body>
</html>