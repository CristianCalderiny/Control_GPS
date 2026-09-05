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
<title>FORZA - Asignar PIN</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#2563eb; --primary-dark:#1e40af; --secondary:#64748b;
    --success:#10b981; --danger:#ef4444; --warning:#f59e0b;
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
.nav-item:nth-child(6) i{ color:#ec4899; }
.nav-item:nth-child(6):hover{ background:#fce7f3; }

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

.main-content{ margin-left:280px; padding:90px 2rem 2rem; min-height:100vh; transition:margin-left .2s ease; max-width:900px; }
.content-header{ margin-bottom:1.5rem; }
.content-header h2{ font-size:1.5rem; font-weight:800; }
.content-header p{ color:var(--text-secondary); font-size:0.9rem; margin-top:0.25rem; }
.card{ background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; box-shadow:var(--shadow); margin-bottom:1.5rem; }
.btn{ display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.25rem; border:none; border-radius:10px; font-size:0.9rem; font-weight:600; cursor:pointer; }
.btn-primary{ background:var(--primary); color:#fff; }
.btn-primary:hover{ background:var(--primary-dark); }
.btn-primary:disabled{ opacity:0.6; cursor:not-allowed; }

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

.form-group{ margin-bottom:1.1rem; max-width:360px; }
.form-label{ display:block; font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; color:var(--text-secondary); }
.form-select, .form-input{
    width:100%; padding:0.75rem 1rem; border:1px solid var(--border); border-radius:10px; font-size:0.95rem;
    background:var(--bg-secondary); color:var(--text-primary); font-family:inherit;
}
.form-input.pin-input{ text-align:center; letter-spacing:0.3em; font-size:1.2rem; }

.msg{ padding:0.85rem 1rem; border-radius:10px; margin-bottom:1.25rem; font-size:0.88rem; display:none; max-width:360px; }
.msg.active{ display:block; }
.msg-ok{ background:rgba(16,185,129,0.1); color:#047857; }
.msg-error{ background:rgba(239,68,68,0.1); color:#b91c1c; }

.piloto-info{ display:flex; align-items:center; gap:0.6rem; margin-bottom:1.25rem; font-size:0.85rem; color:var(--text-secondary); }
.piloto-info i{ color:var(--primary); }

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
        <strong>Asignar PIN a Piloto</strong>
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
        <a class="nav-item" href="asignaciones_piloto.php"><i class="fas fa-clipboard-list"></i><span>Asignaciones por Piloto</span></a>
        <a class="nav-item active" href="asignar_pin.php"><i class="fas fa-key"></i><span>Asignar PIN</span></a>
    </nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <h2>Asignar PIN a Piloto</h2>
        <p>Este es el PIN de 4 dígitos que el piloto usará junto a su teléfono para entrar al portal</p>
    </div>

    <div class="card">
        <div class="msg msg-ok" id="msg-ok"></div>
        <div class="msg msg-error" id="msg-error"></div>

        <form id="form-pin" onsubmit="guardarPin(event)">
            <div class="form-group">
                <label class="form-label">Piloto</label>
                <select class="form-select" id="piloto_id" required>
                    <option value="">-- Cargando pilotos... --</option>
                </select>
            </div>

            <div class="piloto-info" id="piloto-info" style="display:none;">
                <i class="fas fa-phone"></i>
                <span id="piloto-telefono"></span>
            </div>

            <div class="form-group">
                <label class="form-label">PIN (4 dígitos)</label>
                <input type="text" class="form-input pin-input" id="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="0000" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmar PIN</label>
                <input type="text" class="form-input pin-input" id="pin_confirm" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="0000" required>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-guardar">
                <i class="fas fa-key"></i> Guardar PIN
            </button>
        </form>
    </div>
</main>

<script>
let pilotos = [];

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.top-bar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
}

async function safeJson(res) {
    try { return await res.json(); } catch (e) { return []; }
}

function mostrarMsg(tipo, texto) {
    document.getElementById('msg-ok').classList.remove('active');
    document.getElementById('msg-error').classList.remove('active');
    const el = document.getElementById(tipo === 'ok' ? 'msg-ok' : 'msg-error');
    el.textContent = texto;
    el.classList.add('active');
}

async function cargarPilotos() {
    const select = document.getElementById('piloto_id');
    try {
        const res = await fetch('api/piloto_listar.php');
        pilotos = await safeJson(res);
        if (!Array.isArray(pilotos)) pilotos = [];

        select.innerHTML = '<option value="">-- Selecciona un piloto --</option>' +
            pilotos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');
    } catch (err) {
        select.innerHTML = '<option value="">Error al cargar pilotos</option>';
    }
}

document.getElementById('piloto_id').addEventListener('change', function () {
    const piloto = pilotos.find(p => String(p.id) === this.value);
    const info = document.getElementById('piloto-info');
    if (piloto && piloto.telefono) {
        document.getElementById('piloto-telefono').textContent = piloto.telefono;
        info.style.display = 'flex';
    } else {
        info.style.display = 'none';
    }
});

async function guardarPin(event) {
    event.preventDefault();
    document.getElementById('msg-ok').classList.remove('active');
    document.getElementById('msg-error').classList.remove('active');

    const id = document.getElementById('piloto_id').value;
    const pin = document.getElementById('pin').value.trim();
    const pinConfirm = document.getElementById('pin_confirm').value.trim();

    if (!id) { mostrarMsg('error', 'Selecciona un piloto.'); return; }
    if (!/^\d{4}$/.test(pin)) { mostrarMsg('error', 'El PIN debe ser de exactamente 4 dígitos.'); return; }
    if (pin !== pinConfirm) { mostrarMsg('error', 'Los PIN no coinciden.'); return; }

    const btn = document.getElementById('btn-guardar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('pin', pin);

        const res = await fetch('api/set_pin.php', { method: 'POST', body: fd });
        const data = await safeJson(res);

        if (data.success) {
            mostrarMsg('ok', 'PIN asignado correctamente.');
            document.getElementById('form-pin').reset();
            document.getElementById('piloto-info').style.display = 'none';
        } else {
            mostrarMsg('error', data.message || 'No se pudo guardar el PIN.');
        }
    } catch (err) {
        mostrarMsg('error', 'Error de conexión al guardar el PIN.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Guardar PIN';
    }
}

cargarPilotos();
</script>
</body>
</html>