<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion/db.php';

$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FORZA - Asignaciones por Piloto</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#2563eb; --primary-dark:#1e40af; --secondary:#64748b;
    --success:#10b981; --danger:#ef4444; --warning:#f59e0b; --info:#3b82f6;
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
.nav-item:nth-child(1) i{ color:#3b82f6; }
.nav-item:nth-child(1):hover{ background:#dbeafe; }
.nav-item:nth-child(2) i{ color:#10b981; }
.nav-item:nth-child(2):hover{ background:#dcfce7; }
.nav-item:nth-child(3) i{ color:#f59e0b; }
.nav-item:nth-child(3):hover{ background:#fef3c7; }
.nav-item:nth-child(4) i{ color:#8b5cf6; }
.nav-item:nth-child(4):hover{ background:#ede9fe; }
.nav-item:nth-child(5) i{ color:#0ea5e9; }
.nav-item:nth-child(5):hover{ background:#e0f2fe; }

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
.btn-secondary{ background:var(--bg-secondary); color:var(--text-primary); border:1px solid var(--border); }
.btn-sm{ padding:0.4rem 0.75rem; font-size:0.8rem; }
.filters{ display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.filters select, .filters input{
    padding:0.6rem 0.9rem; border-radius:10px; border:1px solid var(--border); background:var(--bg-secondary);
    font-size:0.85rem; color:var(--text-primary);
}
.text-muted{ color:var(--text-secondary); }

.user-chip{ display:flex; align-items:center; gap:0.65rem; font-size:0.85rem; color:var(--text-primary); font-weight:600; }
.user-avatar{
    width:38px; height:38px; border-radius:50%; flex-shrink:0;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.9rem;
    box-shadow:0 3px 8px rgba(37,99,235,0.35);
}
.user-info{ display:flex; flex-direction:column; line-height:1.2; }
.user-info small{ font-weight:500; color:var(--text-secondary); font-size:0.72rem; }
.top-bar-left{ display:flex; align-items:center; gap:1rem; }

/* Tablero por piloto */
.tablero{ display:grid; grid-template-columns:repeat(auto-fill, minmax(310px, 1fr)); gap:1.25rem; }
.piloto-card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; box-shadow:var(--shadow); overflow:hidden; display:flex; flex-direction:column; }
.piloto-card-header{ display:flex; align-items:center; gap:0.85rem; padding:1.1rem 1.25rem; border-bottom:1px solid var(--border); }
.piloto-avatar{
    width:42px; height:42px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.95rem;
}
.piloto-avatar.sin-asignar{ background:linear-gradient(135deg,#94a3b8,#64748b); }
.piloto-nombre-wrap{ flex:1; min-width:0; }
.piloto-nombre{ font-weight:700; font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.piloto-telefono{ font-size:0.75rem; color:var(--text-secondary); }
.piloto-count{ font-size:0.72rem; font-weight:700; padding:0.3rem 0.65rem; border-radius:20px; background:#eef2ff; color:var(--primary-dark); white-space:nowrap; }

.piloto-ops-lista{ padding:0.75rem; display:flex; flex-direction:column; gap:0.6rem; max-height:420px; overflow-y:auto; }
.op-item{ background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; padding:0.75rem 0.9rem; }
.op-item-top{ display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem; margin-bottom:0.4rem; }
.op-cliente{ font-weight:700; font-size:0.87rem; }
.op-fecha{ font-size:0.72rem; color:var(--text-secondary); margin-top:0.1rem; }
.op-ruta{ font-size:0.8rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; }
.op-ruta i{ font-size:0.7rem; }
.op-ruta .flecha{ color:var(--border); }
.op-tipo{ display:inline-block; margin-top:0.4rem; font-size:0.72rem; font-weight:600; padding:0.2rem 0.55rem; border-radius:8px; background:#fff; border:1px solid var(--border); }

.badge{ display:inline-flex; align-items:center; gap:0.35rem; padding:0.28rem 0.65rem; border-radius:20px; font-size:0.7rem; font-weight:700; white-space:nowrap; }
.badge-dot{ width:5px; height:5px; border-radius:50%; background:currentColor; }
.badge-pendiente{ background:rgba(245,158,11,0.12); color:var(--warning); }
.badge-en_ruta{ background:rgba(16,185,129,0.12); color:var(--success); }
.badge-finalizado{ background:rgba(100,116,139,0.12); color:var(--secondary); }
.badge-cancelado{ background:rgba(239,68,68,0.12); color:var(--danger); }

.piloto-empty{ padding:1.5rem 1rem; text-align:center; color:var(--text-secondary); font-size:0.82rem; }
.piloto-empty i{ font-size:1.2rem; margin-bottom:0.4rem; display:block; opacity:0.5; }

.empty-state{ text-align:center; color:var(--text-secondary); padding:3rem 1rem; }
.empty-state i{ font-size:1.6rem; margin-bottom:0.6rem; display:block; color:var(--border); }

@media (max-width:900px){
  .sidebar{ transform:translateX(-100%); }
  .sidebar.collapsed{ transform:translateX(0); width:280px; }
  .top-bar, .main-content{ left:0; margin-left:0; }
  .top-bar.collapsed, .main-content.collapsed{ margin-left:0; left:0; }
}
</style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Contraer/expandir menú">
            <i class="fas fa-bars"></i>
        </button>
        <strong>Asignaciones por Piloto</strong>
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($usuario_nombre, 0, 1)) ?></div>
        <div class="user-info">
            <span><?= htmlspecialchars($usuario_nombre) ?></span>
            <small>Planificador</small>
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
        <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Crear Piloto</span></a>
        <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Crear Vehículo</span></a>
        <a class="nav-item active" href="asignaciones_piloto.php"><i class="fas fa-clipboard-list"></i><span>Asignaciones por Piloto</span></a>
    </nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <h2>Asignaciones por Piloto</h2>
        <p>Así se ven las operaciones asignadas, agrupadas por cada piloto</p>
    </div>

    <div class="card">
        <div class="filters">
            <input type="text" id="filtro-piloto" placeholder="Buscar piloto..." style="flex:1;min-width:220px;" oninput="renderTablero()">
            <select id="filtro-estado" onchange="renderTablero()">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="en_ruta">En ruta</option>
                <option value="finalizado">Finalizado</option>
            </select>
            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;color:var(--text-secondary);">
                <input type="checkbox" id="filtro-solo-con-asignaciones" onchange="renderTablero()">
                Solo pilotos con asignaciones
            </label>
        </div>
        <div id="contador-resultados" style="font-size:0.82rem;color:var(--text-secondary);"></div>
    </div>

    <div id="tablero" class="tablero">
        <div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-spinner fa-spin"></i>Cargando...</div>
    </div>
</main>

<script>
let operaciones = [];
let patrulleros = [];

const ESTADO_LABEL = { pendiente:'Pendiente', en_ruta:'En ruta', finalizado:'Finalizado', cancelado:'Cancelado' };

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.top-bar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
}

async function safeJson(res) {
    try { return await res.json(); } catch (e) { return []; }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatFecha(fechaISO, hora) {
    if (!fechaISO) return '';
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const [y, m, d] = fechaISO.split('-');
    const mesIdx = parseInt(m, 10) - 1;
    let texto = (isNaN(mesIdx) || !meses[mesIdx]) ? fechaISO : `${d} ${meses[mesIdx]} ${y}`;
    if (hora) texto += ` · ${hora.slice(0,5)}`;
    return texto;
}

async function init() {
    try {
        const [pRes, oRes] = await Promise.all([
            fetch('api/piloto_listar.php'),
            fetch('api/listar_operaciones.php')
        ]);
        patrulleros = await safeJson(pRes);
        operaciones = await safeJson(oRes);
        if (!Array.isArray(patrulleros)) patrulleros = [];
        if (!Array.isArray(operaciones)) operaciones = [];
        renderTablero();
    } catch (err) {
        console.error('Error cargando datos:', err);
        document.getElementById('tablero').innerHTML =
            `<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-triangle-exclamation"></i>Error al cargar los datos</div>`;
    }
}

function renderTablero() {
    const filtroPiloto = (document.getElementById('filtro-piloto').value || '').toLowerCase().trim();
    const filtroEstado = document.getElementById('filtro-estado').value;
    const soloConAsignaciones = document.getElementById('filtro-solo-con-asignaciones').checked;

    // Operaciones activas, nunca mostramos canceladas aquí
    const opsActivas = operaciones.filter(o => o.estado !== 'cancelado');

    // Agrupamos por patrullero_id
    const porPiloto = {};
    opsActivas.forEach(op => {
        const key = op.patrullero_id || 'sin_asignar';
        if (!porPiloto[key]) porPiloto[key] = [];
        porPiloto[key].push(op);
    });

    // Construimos la lista de "tarjetas" a mostrar: todos los pilotos registrados + el grupo "sin asignar"
    let tarjetas = patrulleros.map(p => ({
        id: p.id,
        nombre: p.nombre,
        telefono: p.telefono || '',
        ops: (porPiloto[p.id] || [])
    }));

    if (porPiloto['sin_asignar'] && porPiloto['sin_asignar'].length > 0) {
        tarjetas.push({ id: null, nombre: 'Sin asignar', telefono: '', ops: porPiloto['sin_asignar'], sinAsignar: true });
    }

    if (filtroEstado) {
        tarjetas = tarjetas.map(t => ({ ...t, ops: t.ops.filter(o => o.estado === filtroEstado) }));
    }

    if (filtroPiloto) {
        tarjetas = tarjetas.filter(t => t.nombre.toLowerCase().includes(filtroPiloto));
    }

    if (soloConAsignaciones) {
        tarjetas = tarjetas.filter(t => t.ops.length > 0);
    }

    tarjetas.sort((a, b) => {
        if (a.sinAsignar) return 1;
        if (b.sinAsignar) return -1;
        return b.ops.length - a.ops.length;
    });

    const totalOps = tarjetas.reduce((acc, t) => acc + t.ops.length, 0);
    document.getElementById('contador-resultados').textContent =
        `${tarjetas.length} piloto${tarjetas.length === 1 ? '' : 's'} · ${totalOps} operación${totalOps === 1 ? '' : 'es'} mostradas`;

    const cont = document.getElementById('tablero');
    if (tarjetas.length === 0) {
        cont.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-user-slash"></i>No hay pilotos que coincidan con el filtro</div>`;
        return;
    }

    cont.innerHTML = tarjetas.map(renderTarjetaPiloto).join('');
}

function renderTarjetaPiloto(t) {
    const inicial = t.nombre ? t.nombre.trim().charAt(0).toUpperCase() : '?';
    const opsHtml = t.ops.length
        ? t.ops
            .sort((a, b) => (a.fecha_servicio + (a.hora_servicio||'')).localeCompare(b.fecha_servicio + (b.hora_servicio||'')))
            .map(renderOpItem).join('')
        : `<div class="piloto-empty"><i class="fas fa-calendar-check"></i>Sin asignaciones${t.sinAsignar ? '' : ' por ahora'}</div>`;

    return `
    <div class="piloto-card">
        <div class="piloto-card-header">
            <div class="piloto-avatar ${t.sinAsignar ? 'sin-asignar' : ''}">${t.sinAsignar ? '<i class="fas fa-user-slash"></i>' : escapeHtml(inicial)}</div>
            <div class="piloto-nombre-wrap">
                <div class="piloto-nombre">${escapeHtml(t.nombre)}</div>
                ${t.telefono ? `<div class="piloto-telefono">${escapeHtml(t.telefono)}</div>` : ''}
            </div>
            <span class="piloto-count">${t.ops.length}</span>
        </div>
        <div class="piloto-ops-lista">${opsHtml}</div>
    </div>`;
}

function renderOpItem(op) {
    return `
    <div class="op-item">
        <div class="op-item-top">
            <div>
                <div class="op-cliente">${escapeHtml(op.cliente)}</div>
                <div class="op-fecha">${formatFecha(op.fecha_servicio, op.hora_servicio)}</div>
            </div>
            <span class="badge badge-${op.estado}"><span class="badge-dot"></span>${ESTADO_LABEL[op.estado] || op.estado}</span>
        </div>
        <div class="op-ruta">
            <i class="fas fa-circle" style="color:var(--success);"></i>${escapeHtml(op.origen)}
            <span class="flecha">→</span>
            <i class="fas fa-location-dot" style="color:var(--danger);"></i>${escapeHtml(op.destino)}
        </div>
        <span class="op-tipo">${escapeHtml(op.tipo_servicio)}</span>
    </div>`;
}

init();
</script>
</body>
</html>