<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar si es administrador
if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    header("Location: index.php");
    exit();
}

require_once 'conexion/db.php';

$user_id = $_SESSION['usuario_id'] ?? 0;
$user_name = $_SESSION['usuario'] ?? 'Usuario';
$user_role = $_SESSION['rol'] ?? 'Usuario';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - FORZA GPS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, rgba(25, 32, 102, 0.64) 0%, rgba(28, 25, 94, 0.59) 50%, rgba(42, 31, 105, 1) 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(120, 120, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 120, 120, 0.1) 0%, transparent 50%);
            pointer-events: none;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { opacity: 0.3; transform: translateY(0px); }
            50% { opacity: 0.8; transform: translateY(-20px); }
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .admin-title {
            color: #2d3748;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .admin-subtitle {
            color: #718096;
            font-size: 0.95rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            color: #2d3748;
            font-weight: 600;
        }

        .user-role {
            color: #718096;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #2d3748;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .users-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .users-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 0.75rem 1rem;
            border-radius: 15px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border-radius: 15px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 15px;
        }

        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .users-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
            color: #2d3748;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .users-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            margin-right: 1rem;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
        }

        .user-details h4 {
            color: #2d3748;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-details p {
            color: #718096;
            font-size: 0.8rem;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: rgba(255, 65, 108, 0.1);
            color: #ff416c;
        }

        .role-user {
            background: rgba(17, 153, 142, 0.1);
            color: #00b894;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(17, 153, 142, 0.1);
            color: #00b894;
            border-left: 4px solid #00b894;
        }

        .alert-error {
            background: rgba(255, 65, 108, 0.1);
            color: #d63031;
            border-left: 4px solid #d63031;
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
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #2d3748;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 15px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                text-align: center;
            }

            .header-left {
                justify-content: center;
                margin-bottom: 1rem;
            }

            .users-table {
                font-size: 0.8rem;
            }

            .users-table th,
            .users-table td {
                padding: 0.5rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="header-left">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <h1 class="admin-title">Panel de Administración</h1>
                    <p class="admin-subtitle">FORZA GPS - Control y Gestión</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-name">Bienvenido, <?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-role">(<?php echo htmlspecialchars($user_role); ?>)</div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                    <i class="fas fa-arrow-left"></i> Ir al Dashboard
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarSesion()">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div style="font-size: 2rem; color: #667eea; margin-bottom: 0.5rem;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" id="totalUsers">0</div>
                <div class="stat-label">Total de Usuarios</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2rem; color: #ff416c; margin-bottom: 0.5rem;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number" id="totalAdmins">0</div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2rem; color: #00b894; margin-bottom: 0.5rem;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number" id="totalRegularUsers">0</div>
                <div class="stat-label">Usuarios Regulares</div>
            </div>
        </div>

        <div class="users-card">
            <div class="users-header">
                <h2 class="users-title">
                    <i class="fas fa-users"></i> Gestión de Usuarios
                </h2>
                <button class="btn" onclick="mostrarFormularioNuevo()">
                    <i class="fas fa-plus"></i> Nuevo Usuario
                </button>
            </div>

            <div id="alertContainer"></div>

            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Buscar por nombre, usuario o email...">
                <select class="filter-select" id="roleFilter">
                    <option value="">Todos los roles</option>
                    <option value="Administrador">Administradores</option>
                    <option value="Usuario">Usuarios</option>
                </select>
                <button class="btn" onclick="cargarUsuarios()" style="flex-shrink: 0;">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="users-table">
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
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #667eea;"></i>
                                <p style="margin-top: 1rem; color: #718096;">Cargando usuarios...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Usuario</h2>
                <button class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="editForm" onsubmit="guardarCambios(event)">
                <input type="hidden" id="editUserId">
                <div class="form-group">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" id="editName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input type="text" id="editUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="editEmail" class="form-input" required>
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
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn" style="flex: 1;">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()" style="flex: 1;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para nuevo usuario -->
    <div id="newUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Crear Nuevo Usuario</h2>
                <button class="close-btn" onclick="cerrarModalNuevo()">&times;</button>
            </div>
            <form id="newUserForm" onsubmit="crearUsuario(event)">
                <div class="form-group">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" name="nombre_completo" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-input" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select" required>
                        <option value="Usuario">Usuario</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn" style="flex: 1;">
                        <i class="fas fa-user-plus"></i> Crear Usuario
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalNuevo()" style="flex: 1;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let usuarios = [];
        let usuariosFiltrados = [];

        function getInitials(nombre) {
            if (!nombre) return 'U';
            return nombre.split(' ').map(w => w[0] || '').join('').toUpperCase().substring(0, 2);
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('es-HN', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            } catch (e) {
                return 'N/A';
            }
        }

        function cargarUsuarios() {
            fetch('api/get_usuarios.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        usuarios = data.usuarios;
                        usuariosFiltrados = [...usuarios];
                        renderizarTabla();
                        actualizarEstadisticas();
                    } else {
                        mostrarAlerta('Error al cargar usuarios: ' + (data.error || 'Desconocido'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarAlerta('Error de conexión al servidor', 'error');
                });
        }

        function renderizarTabla() {
            const tbody = document.getElementById('usersTableBody');

            if (usuariosFiltrados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: #718096;">
                            <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            No se encontraron usuarios
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = usuariosFiltrados.map(usuario => {
                const isAdmin = usuario.rol?.toLowerCase() === 'administrador';
                return `
                    <tr>
                        <td>
                            <div class="user-info-cell">
                                <div class="user-avatar">${getInitials(usuario.nombre_completo)}</div>
                                <div class="user-details">
                                    <h4>${escapeHtml(usuario.nombre_completo)}</h4>
                                    <p>@${escapeHtml(usuario.usuario)}</p>
                                </div>
                            </div>
                        </td>
                        <td>${escapeHtml(usuario.email)}</td>
                        <td>
                            <span class="role-badge ${isAdmin ? 'role-admin' : 'role-user'}">
                                ${escapeHtml(usuario.rol)}
                            </span>
                        </td>
                        <td>
                            <span class="role-badge" style="background: ${usuario.estado === 'activo' ? 'rgba(17, 153, 142, 0.1)' : 'rgba(255, 65, 108, 0.1)'}; color: ${usuario.estado === 'activo' ? '#00b894' : '#ff416c'};">
                                ${usuario.estado}
                            </span>
                        </td>
                        <td>${formatDate(usuario.created_at)}</td>
                        <td>
                            <div class="actions">
                                <button class="btn" onclick="editarUsuario(${usuario.id})" style="padding: 0.5rem 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-delete" onclick="eliminarUsuario(${usuario.id})" style="padding: 0.5rem 1rem;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        function actualizarEstadisticas() {
            const total = usuarios.length;
            const admins = usuarios.filter(u => u.rol?.toLowerCase() === 'administrador').length;
            const regulares = usuarios.filter(u => u.rol?.toLowerCase() === 'usuario').length;

            document.getElementById('totalUsers').textContent = total;
            document.getElementById('totalAdmins').textContent = admins;
            document.getElementById('totalRegularUsers').textContent = regulares;
        }

        function filtrarUsuarios() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;

            usuariosFiltrados = usuarios.filter(user => {
                const matchesSearch = (user.nombre_completo || '').toLowerCase().includes(searchTerm) ||
                    (user.usuario || '').toLowerCase().includes(searchTerm) ||
                    (user.email || '').toLowerCase().includes(searchTerm);

                const matchesRole = roleFilter === '' || user.rol?.toLowerCase() === roleFilter.toLowerCase();

                return matchesSearch && matchesRole;
            });

            renderizarTabla();
        }

        function editarUsuario(id) {
            const usuario = usuarios.find(u => u.id === id);
            if (usuario) {
                document.getElementById('editUserId').value = usuario.id;
                document.getElementById('editName').value = usuario.nombre_completo || '';
                document.getElementById('editUsername').value = usuario.usuario || '';
                document.getElementById('editEmail').value = usuario.email || '';
                document.getElementById('editRole').value = usuario.rol || 'Usuario';
                document.getElementById('editEstado').value = usuario.estado || 'activo';
                document.getElementById('editModal').style.display = 'flex';
            }
        }

        function cerrarModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function mostrarFormularioNuevo() {
            document.getElementById('newUserModal').style.display = 'flex';
        }

        function cerrarModalNuevo() {
            document.getElementById('newUserModal').style.display = 'none';
            document.getElementById('newUserForm').reset();
        }

        function guardarCambios(event) {
            event.preventDefault();

            const userId = document.getElementById('editUserId').value;
            const formData = new FormData();
            formData.append('id', userId);
            formData.append('nombre_completo', document.getElementById('editName').value);
            formData.append('usuario', document.getElementById('editUsername').value);
            formData.append('email', document.getElementById('editEmail').value);
            formData.append('rol', document.getElementById('editRole').value);
            formData.append('estado', document.getElementById('editEstado').value);

            fetch('api/update_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('Usuario actualizado correctamente', 'success');
                    cerrarModal();
                    cargarUsuarios();
                } else {
                    mostrarAlerta('Error al actualizar: ' + (data.error || 'Desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('Error de conexión', 'error');
            });
        }

        function crearUsuario(event) {
            event.preventDefault();

            const formData = new FormData(event.target);

            fetch('api/create_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('Usuario creado correctamente', 'success');
                    cerrarModalNuevo();
                    cargarUsuarios();
                } else {
                    mostrarAlerta('Error: ' + (data.error || 'Desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('Error de conexión', 'error');
            });
        }

        function eliminarUsuario(id) {
            if (confirm('¿Está seguro de que desea eliminar este usuario?')) {
                const formData = new FormData();
                formData.append('id', id);

                fetch('api/delete_usuario.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta('Usuario eliminado correctamente', 'success');
                        cargarUsuarios();
                    } else {
                        mostrarAlerta('Error: ' + (data.error || 'Desconocido'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarAlerta('Error de conexión', 'error');
                });
            }
        }

        function mostrarAlerta(mensaje, tipo) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${tipo}`;
            alert.innerHTML = `
                <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${mensaje}
            `;

            container.appendChild(alert);

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        function cerrarSesion() {
            if (confirm('¿Desea cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }

        // Event Listeners
        document.getElementById('searchInput').addEventListener('input', filtrarUsuarios);
        document.getElementById('roleFilter').addEventListener('change', filtrarUsuarios);

        window.addEventListener('click', function(e) {
            const editModal = document.getElementById('editModal');
            const newModal = document.getElementById('newUserModal');
            
            if (e.target === editModal) {
                cerrarModal();
            }
            if (e.target === newModal) {
                cerrarModalNuevo();
            }
        });

        // Cargar usuarios al iniciar
        document.addEventListener('DOMContentLoaded', cargarUsuarios);
    </script>
</body>
</html>