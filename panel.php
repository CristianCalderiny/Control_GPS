<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar si es administrador (mismo criterio que el resto del sistema:
// acepta tanto "admin" como "administrador")
$user_role = $_SESSION['rol'] ?? 'Usuario';
$es_admin = in_array(strtolower(trim($user_role)), ['admin', 'administrador']);

if (!$es_admin) {
    header("Location: index.php");
    exit;
}

require_once 'conexion/db.php';

$user_id = $_SESSION['usuario_id'] ?? 0;
// Igual que en index.php/operaciones.php: preferir el nombre completo si existe
$user_name = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FORZA - Panel de Administración</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
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

/* Íconos con color propio por módulo (mismo criterio que operaciones.php) */
.nav-item:nth-child(1) i{ color:#e31b23; }
.nav-item:nth-child(1):hover{ background:#fde8e9; }
.nav-item:nth-child(2) i{ color:#10b981; }
.nav-item:nth-child(2):hover{ background:#dcfce7; }
.nav-item:nth-child(3) i{ color:#e31b23; }
.nav-item:nth-child(3):hover{ background:#fde8e9; }
.nav-item:nth-child(4) i{ color:#e31b23; }
.nav-item:nth-child(4):hover{ background:#fde8e9; }
.nav-item:nth-child(5) i{ color:#7c3aed; }
.nav-item:nth-child(5):hover{ background:#ede9fe; }

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
th{ text-align:left; padding:0.85rem 1rem; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); border-bottom:2px solid var(--border); background:var(--bg-card); white-space:nowrap; }
td{ padding:0.95rem 1rem; border-bottom:1px solid var(--border); font-size:0.88rem; vertical-align:middle; }
tbody tr:hover td{ background:var(--bg-secondary); }
.actions-cell, .actions{ display:flex; gap:0.4rem; }
.btn-icon{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--border); background:var(--bg-secondary); color:var(--text-primary); cursor:pointer; font-size:0.85rem; transition:background 0.15s; }
.btn-icon:hover{ background:#e2e8f0; }
.btn-icon-danger{ background:rgba(239,68,68,0.08); color:var(--danger); border-color:rgba(239,68,68,0.2); }
.btn-icon-danger:hover{ background:rgba(239,68,68,0.16); }
.empty-state{ text-align:center; color:var(--text-secondary); padding:3rem 1rem; }
.empty-state i{ font-size:1.6rem; margin-bottom:0.6rem; display:block; color:var(--border); }

.badge{ display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.8rem; border-radius:20px; font-size:0.75rem; font-weight:700; white-space:nowrap; text-transform:uppercase; letter-spacing:0.3px; }
.badge-success{ background:#dcfce7; color:#166534; }
.badge-danger{ background:#fee2e2; color:#991b1b; }
.badge-info{ background:#fde8e9; color:#a90f15; }
body.dark-mode .badge-success{ background:#166534; color:#dcfce7; }
body.dark-mode .badge-danger{ background:#991b1b; color:#fee2e2; }
body.dark-mode .badge-info{ background:#a90f15; color:#fde8e9; }

.stat-card{ background:var(--bg-card); padding:1.5rem; border-radius:16px; border:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; transition:all 0.3s; }
.stat-card:hover{ transform:translateY(-5px); box-shadow:var(--shadow-lg); }
.stat-info h3{ font-size:0.9rem; color:var(--text-secondary); margin-bottom:0.5rem; font-weight:500; }
.stat-info p{ font-size:2rem; font-weight:700; color:var(--text-primary); }
.stat-icon{ width:60px; height:60px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.75rem; color:#fff; flex-shrink:0; }
.stat-icon.purple{ background:linear-gradient(135deg,#c31920,#a90f15); }
.stat-icon.red{ background:linear-gradient(135deg,#ef4444,#dc2626); }
.stat-icon.green{ background:linear-gradient(135deg,#10b981,#059669); }
.stats-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:1.5rem; }

.user-info-cell{ display:flex; align-items:center; gap:0.85rem; }
.user-avatar{
    width:40px; height:40px; border-radius:50%; flex-shrink:0;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.85rem;
    box-shadow:0 3px 8px rgba(227,27,35,0.3);
}
.user-details h4{ font-size:0.88rem; font-weight:700; color:var(--text-primary); }
.user-details p{ font-size:0.78rem; color:var(--text-secondary); margin-top:0.1rem; }

/* Usuario con diseño (avatar + nombre) en el top-bar */
.user-chip{ display:flex; align-items:center; gap:0.65rem; font-size:0.85rem; color:var(--text-primary); font-weight:600; }
.top-bar .user-avatar{ width:38px; height:38px; font-size:0.9rem; }
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

.modal{ display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:200; align-items:center; justify-content:center; }
.modal.active{ display:flex; }
.modal-content{ background:var(--bg-card); border-radius:20px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto; margin:1rem; }
.modal-header{ display:flex; justify-content:space-between; align-items:center; padding:1.5rem 2rem; border-bottom:1px solid var(--border); }
.modal-header h3{ font-size:1.15rem; }
.close-modal{ background:none; border:none; font-size:1.1rem; color:var(--text-secondary); cursor:pointer; }
.modal-body{ padding:1.5rem 2rem 2rem; }
.form-group{ margin-bottom:1.1rem; }
.form-label{ display:block; font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; color:var(--text-secondary); }
.form-input, .form-select{
    width:100%; padding:0.75rem 1rem; border:1px solid var(--border); border-radius:10px; font-size:0.9rem;
    background:var(--bg-secondary); color:var(--text-primary); font-family:inherit;
    transition:border-color .15s ease, box-shadow .15s ease;
}
.form-input:focus, .form-select:focus{
    outline:none; border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(227,27,35,0.12);
}
.modal-actions{ display:flex; gap:1rem; margin-top:1.5rem; }
.modal-actions .btn{ flex:1; justify-content:center; }

.modal-mensaje-content{ max-width:420px; text-align:center; padding:2rem 1.75rem 1.75rem; }
.modal-mensaje-icono{ width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; }
.modal-mensaje-icono.exito{ background:rgba(16,185,129,0.12); color:var(--success); }
.modal-mensaje-icono.error{ background:rgba(239,68,68,0.12); color:var(--danger); }
.modal-mensaje-titulo{ font-size:1.05rem; font-weight:700; margin-bottom:0.5rem; }
.modal-mensaje-texto{ font-size:0.9rem; color:var(--text-secondary); line-height:1.5; margin-bottom:1.5rem; }

.hidden{ display:none !important; }

/* ================= MODO NOCHE ================= */
body.dark-mode{
    --bg-primary:#12141a; --bg-secondary:#1b1e26; --bg-card:#1b1e26;
    --text-primary:#e9ebf0; --text-secondary:#9aa1ae; --border:#2c3038;
}
body.dark-mode tbody tr:hover td{ background:#20232c; }
body.dark-mode .form-input:focus, body.dark-mode .form-select:focus{ background:#20232c; }

@media (max-width:900px){
  .sidebar{ transform:translateX(-100%); }
  .sidebar.collapsed{ transform:translateX(0); width:280px; }
  .top-bar, .main-content{ left:0; margin-left:0; }
  .top-bar.collapsed, .main-content.collapsed{ margin-left:0; left:0; }
  .actions-cell, .actions{ flex-direction:column; }
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
        <strong>Panel Admin</strong>
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
        <div class="user-info">
            <span><?= htmlspecialchars($user_name) ?></span>
            <span class="role-pill"><i class="fas fa-user-shield"></i> Administrador</span>
        </div>
    </div>
</div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text"><h1>FORZA</h1><p>PANEL ADMIN • HN</p></div>
        </div>
    </div>
    <nav class="nav-menu">
        <a class="nav-item" href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a class="nav-item" href="operaciones.php"><i class="fas fa-route"></i><span>Operaciones</span></a>
        <a class="nav-item" href="pilotos.php"><i class="fas fa-id-card"></i><span>Pilotos</span></a>
        <a class="nav-item" href="vehiculos.php"><i class="fas fa-car"></i><span>Mi Flota</span></a>
        <a class="nav-item active" href="panel.php"><i class="fas fa-user-shield"></i><span>Panel Admin</span></a>
    </nav>
</aside>

<main class="main-content" id="main-content">
    <div class="content-header">
        <div class="flex justify-between items-center">
            <div>
                <h2>Panel de Administración</h2>
                <p>Gestiona los usuarios del sistema</p>
            </div>
            <button class="btn btn-primary" onclick="mostrarFormularioNuevo()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Total de Usuarios</h3>
                <p id="totalUsers">0</p>
            </div>
            <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Administradores</h3>
                <p id="totalAdmins">0</p>
            </div>
            <div class="stat-icon red"><i class="fas fa-user-shield"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Usuarios Regulares</h3>
                <p id="totalRegularUsers">0</p>
            </div>
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        </div>
    </div>

    <div class="card">
        <div class="filters">
            <input autocomplete="nope-buscar-usuario" type="text" id="searchInput" placeholder="Buscar por nombre, usuario o email..." style="flex:1;min-width:220px;">
            <select id="roleFilter">
                <option value="">Todos los roles</option>
                <option value="Administrador">Administradores</option>
                <option value="Usuario">Usuarios</option>
            </select>
            <button class="btn btn-secondary btn-sm" onclick="cargarUsuarios()">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha de Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-secondary);">
                            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i>
                            <p style="margin-top:0.75rem;">Cargando usuarios...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal para editar usuario -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Editar Usuario</h3>
            <button class="close-modal" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form autocomplete="off" id="editForm" onsubmit="guardarCambios(event)">
                <input autocomplete="nope-edit-id" type="hidden" id="editUserId">
                <div class="form-group">
                    <label class="form-label">Nombre Completo</label>
                    <input autocomplete="nope-edit-nombre" type="text" id="editName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input autocomplete="nope-edit-usuario" type="text" id="editUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input autocomplete="nope-edit-email" type="email" id="editEmail" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rol</label>
                    <select id="editRole" class="form-select" required>
                        <option value="Usuario">Usuario</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select id="editEstado" class="form-select" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para nuevo usuario -->
<div id="newUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h3>
            <button class="close-modal" onclick="cerrarModalNuevo()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form autocomplete="off" id="newUserForm" onsubmit="crearUsuario(event)">
                <div class="form-group">
                    <label class="form-label">Nombre Completo</label>
                    <input autocomplete="nope-nuevo-nombre" type="text" name="nombre_completo" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input autocomplete="nope-nuevo-usuario" type="text" name="usuario" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input autocomplete="nope-nuevo-email" type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input autocomplete="new-password" type="password" name="password" class="form-input" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select" required>
                        <option value="Usuario">Usuario</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Crear Usuario
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalNuevo()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de mensajes de éxito/error -->
<div class="modal" id="modal-mensaje">
    <div class="modal-content modal-mensaje-content">
        <div class="modal-mensaje-icono" id="modal-mensaje-icono"><i class="fas fa-check"></i></div>
        <div class="modal-mensaje-titulo" id="modal-mensaje-titulo">Título</div>
        <div class="modal-mensaje-texto" id="modal-mensaje-texto">Texto</div>
        <button class="btn btn-primary" style="width:100%;" onclick="cerrarModalMensaje()">Entendido</button>
    </div>
</div>

<script>
    let usuarios = [];
    let usuariosFiltrados = [];

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

    function getInitials(nombre) {
        if (!nombre) return 'U';
        return nombre.trim().split(/\s+/).map(w => w[0] || '').join('').toUpperCase().substring(0, 2) || 'U';
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('es-HN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    async function safeJson(res) {
        try { return await res.json(); } catch (e) { return { success: false }; }
    }

    function cargarUsuarios() {
        fetch('api/get_usuarios.php')
            .then(safeJson)
            .then(data => {
                if (data.success) {
                    usuarios = Array.isArray(data.usuarios) ? data.usuarios : [];
                    usuariosFiltrados = [...usuarios];
                    filtrarUsuarios();
                    actualizarEstadisticas();
                } else {
                    mostrarMensaje('error', 'Error al cargar usuarios: ' + (data.message || data.error || 'Desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('error', 'Error de conexión al servidor');
            });
    }

    function renderizarTabla() {
        const tbody = document.getElementById('usersTableBody');

        if (usuariosFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            No se encontraron usuarios
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = usuariosFiltrados.map(usuario => {
            const isAdmin = (usuario.rol || '').toLowerCase() === 'administrador';
            const isActivo = (usuario.estado || '').toLowerCase() === 'activo';
            return `
                <tr>
                    <td>
                        <div class="user-info-cell">
                            <div class="user-avatar">${escapeHtml(getInitials(usuario.nombre_completo))}</div>
                            <div class="user-details">
                                <h4>${escapeHtml(usuario.nombre_completo || '-')}</h4>
                                <p>@${escapeHtml(usuario.usuario || '-')}</p>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(usuario.email || '-')}</td>
                    <td>
                        <span class="badge ${isAdmin ? 'badge-info' : 'badge-success'}">
                            ${escapeHtml(usuario.rol || '-')}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${isActivo ? 'badge-success' : 'badge-danger'}">
                            ${escapeHtml(usuario.estado || '-')}
                        </span>
                    </td>
                    <td>${formatDate(usuario.created_at)}</td>
                    <td>
                        <div class="actions-cell">
                            <button class="btn-icon" onclick="editarUsuario(${parseInt(usuario.id)})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-icon-danger" onclick="eliminarUsuario(${parseInt(usuario.id)})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function actualizarEstadisticas() {
        const total = usuarios.length;
        const admins = usuarios.filter(u => (u.rol || '').toLowerCase() === 'administrador').length;
        const regulares = usuarios.filter(u => (u.rol || '').toLowerCase() === 'usuario').length;

        document.getElementById('totalUsers').textContent = total;
        document.getElementById('totalAdmins').textContent = admins;
        document.getElementById('totalRegularUsers').textContent = regulares;
    }

    function filtrarUsuarios() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
        const roleFilter = document.getElementById('roleFilter').value;

        usuariosFiltrados = usuarios.filter(user => {
            const matchesSearch = !searchTerm ||
                (user.nombre_completo || '').toLowerCase().includes(searchTerm) ||
                (user.usuario || '').toLowerCase().includes(searchTerm) ||
                (user.email || '').toLowerCase().includes(searchTerm);

            const matchesRole = roleFilter === '' || (user.rol || '').toLowerCase() === roleFilter.toLowerCase();

            return matchesSearch && matchesRole;
        });

        renderizarTabla();
    }

    function editarUsuario(id) {
        const usuario = usuarios.find(u => parseInt(u.id) === id);
        if (!usuario) return;
        document.getElementById('editUserId').value = usuario.id;
        document.getElementById('editName').value = usuario.nombre_completo || '';
        document.getElementById('editUsername').value = usuario.usuario || '';
        document.getElementById('editEmail').value = usuario.email || '';
        document.getElementById('editRole').value = usuario.rol || 'Usuario';
        document.getElementById('editEstado').value = usuario.estado || 'activo';
        document.getElementById('editModal').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function mostrarFormularioNuevo() {
        document.getElementById('newUserModal').classList.add('active');
    }

    function cerrarModalNuevo() {
        document.getElementById('newUserModal').classList.remove('active');
        document.getElementById('newUserForm').reset();
    }

    function guardarCambios(event) {
        event.preventDefault();

        const userId = document.getElementById('editUserId').value;
        const formData = new FormData();
        formData.append('id', userId);
        formData.append('nombre_completo', document.getElementById('editName').value.trim());
        formData.append('usuario', document.getElementById('editUsername').value.trim());
        formData.append('email', document.getElementById('editEmail').value.trim());
        formData.append('rol', document.getElementById('editRole').value);
        formData.append('estado', document.getElementById('editEstado').value);

        fetch('api/update_usuario.php', { method: 'POST', body: formData })
            .then(safeJson)
            .then(data => {
                if (data.success) {
                    cerrarModal();
                    cargarUsuarios();
                    mostrarMensaje('exito', 'Usuario actualizado correctamente');
                } else {
                    mostrarMensaje('error', data.message || data.error || 'Error al actualizar el usuario');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('error', 'Error de conexión al servidor');
            });
    }

    function crearUsuario(event) {
        event.preventDefault();

        const formData = new FormData(event.target);

        fetch('api/create_usuario.php', { method: 'POST', body: formData })
            .then(safeJson)
            .then(data => {
                if (data.success) {
                    cerrarModalNuevo();
                    cargarUsuarios();
                    mostrarMensaje('exito', 'Usuario creado correctamente');
                } else {
                    mostrarMensaje('error', data.message || data.error || 'Error al crear el usuario');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('error', 'Error de conexión al servidor');
            });
    }

    function eliminarUsuario(id) {
        if (!confirm('¿Está seguro de que desea eliminar este usuario?')) return;

        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_usuario.php', { method: 'POST', body: formData })
            .then(safeJson)
            .then(data => {
                if (data.success) {
                    cargarUsuarios();
                    mostrarMensaje('exito', 'Usuario eliminado correctamente');
                } else {
                    mostrarMensaje('error', data.message || data.error || 'Error al eliminar el usuario');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('error', 'Error de conexión al servidor');
            });
    }

    function cerrarSesion() {
        if (confirm('¿Está seguro que desea cerrar sesión?')) {
            window.location.href = 'logout.php';
        }
    }

    // Event listeners
    document.getElementById('searchInput').addEventListener('input', filtrarUsuarios);
    document.getElementById('roleFilter').addEventListener('change', filtrarUsuarios);

    window.addEventListener('click', function (e) {
        if (e.target === document.getElementById('editModal')) cerrarModal();
        if (e.target === document.getElementById('newUserModal')) cerrarModalNuevo();
        if (e.target === document.getElementById('modal-mensaje')) cerrarModalMensaje();
    });

    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarModal();
            cerrarModalNuevo();
            cerrarModalMensaje();
        }
    });

    // Cargar usuarios al iniciar
    document.addEventListener('DOMContentLoaded', cargarUsuarios);
</script>

<script>
    // ============================================================
    // Bloqueo de autocompletado/sugerencias del navegador
    // (mismo mecanismo aplicado en index.php y operaciones.php)
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
            el.addEventListener('focus', function () { el.removeAttribute('readonly'); });
            el.addEventListener('mousedown', function () { el.removeAttribute('readonly'); });
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