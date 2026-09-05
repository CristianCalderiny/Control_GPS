<?php
session_start();
require_once __DIR__ . '/auth_recordar.php'; // intenta restaurar sesión si venía con cookie "recordarme"

// Si no hay sesión de piloto (ni se pudo restaurar), lo mandamos al login
if (!isset($_SESSION['patrullero_id'])) {
    header('Location: piloto_login.php');
    exit;
}

$nombrePiloto = $_SESSION['patrullero_nombre'] ?? 'Piloto';
// Solo el primer nombre para el saludo, se ve mejor en el header
$primerNombre = trim(explode(' ', $nombrePiloto)[0] ?? $nombrePiloto);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>FORZA - Mis Servicios</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root{
    --primary:#e31b23; --primary-dark:#a90f15; --danger:#ef4444; --warning:#f59e0b; --success:#16a34a;
    --bg:#f1f5f9; --card:#ffffff; --text:#0f172a; --text-secondary:#64748b; --border:#e2e8f0;
}
body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; background:var(--bg); color:var(--text);
    min-height:100vh; padding-bottom:2rem;
}

/* ---------- Pantalla de bienvenida ---------- */
.bienvenida-overlay{
    position:fixed; inset:0; z-index:9999; display:flex; flex-direction:column; align-items:center; justify-content:center;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; text-align:center; padding:2rem;
    opacity:1; transition:opacity .45s ease; gap:0.35rem;
}
.bienvenida-overlay.oculto{ opacity:0; pointer-events:none; }
.bienvenida-icono{
    width:72px; height:72px; border-radius:20px; background:rgba(255,255,255,0.15);
    display:flex; align-items:center; justify-content:center; font-size:1.9rem; margin-bottom:0.75rem;
}
.bienvenida-titulo{ font-size:1rem; font-weight:600; opacity:0.85; letter-spacing:0.02em; }
.bienvenida-nombre{ font-size:1.7rem; font-weight:800; margin:0.15rem 0 0.5rem; line-height:1.2; }
.bienvenida-sub{ font-size:0.85rem; opacity:0.8; }

/* ---------- Header ---------- */
header{
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff;
    padding:1.5rem 1.25rem 1.75rem; border-radius:0 0 22px 22px;
    display:flex; align-items:center; justify-content:space-between; gap:0.75rem;
}
.header-left{ display:flex; align-items:center; gap:0.85rem; min-width:0; }
.btn-menu{
    background:rgba(255,255,255,0.18); border:none; color:#fff; width:40px; height:40px;
    border-radius:10px; display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; cursor:pointer; flex-shrink:0;
}
header .saludo-bloque{ min-width:0; }
header .saludo{ font-size:0.8rem; opacity:0.85; margin-bottom:0.15rem; }
header .nombre{ font-size:1.2rem; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.btn-logout{
    background:rgba(255,255,255,0.18); border:none; color:#fff; width:38px; height:38px;
    border-radius:10px; display:flex; align-items:center; justify-content:center;
    text-decoration:none; font-size:1rem; flex-shrink:0;
}

main{ max-width:520px; margin:0 auto; padding:1.25rem; }
@media (min-width:700px){ main{ max-width:1180px; } }
.section-title{ font-size:0.9rem; font-weight:700; color:var(--text-secondary); margin:0.25rem 0 0.75rem; text-transform:uppercase; letter-spacing:0.03em; display:flex; align-items:center; justify-content:space-between; gap:0.5rem; }
.sync-status{ display:flex; align-items:center; gap:0.4rem; font-size:0.7rem; font-weight:600; text-transform:none; letter-spacing:normal; color:var(--text-secondary); }
.sync-status .sync-dot{ width:7px; height:7px; border-radius:50%; background:#94a3b8; flex-shrink:0; }
.sync-status.en-linea .sync-dot{ background:var(--success); }
.sync-status.sin-conexion .sync-dot{ background:var(--danger); }
.sync-status.sin-conexion{ color:var(--danger); }

/* ---------- Selector de vista (Tarjetas / Tabla) ---------- */
.vista-toggle{ display:flex; gap:0.4rem; margin-bottom:1rem; }
.vista-btn{
    flex:1; display:flex; align-items:center; justify-content:center; gap:0.4rem;
    background:var(--card); border:1px solid var(--border); color:var(--text-secondary);
    padding:0.55rem 0.75rem; border-radius:10px; font-size:0.82rem; font-weight:700; cursor:pointer;
    transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.vista-btn i{ font-size:0.85rem; }
.vista-btn.activo{ background:var(--primary); border-color:var(--primary); color:#fff; }
@media (min-width:700px){ .vista-toggle{ max-width:300px; } }

/* ---------- Alerta de aceite (banner) ---------- */
.alerta-banner{
    display:none; align-items:flex-start; gap:0.7rem; border-radius:14px; padding:0.9rem 1rem;
    margin-bottom:1rem; font-size:0.85rem; line-height:1.4;
}
.alerta-banner.visible{ display:flex; }
.alerta-banner i{ font-size:1.1rem; margin-top:0.1rem; }
.alerta-banner.nivel-1{ background:#fef3c7; color:#92400e; }
.alerta-banner.nivel-2{ background:#ffedd5; color:#9a3412; }
.alerta-banner.nivel-3{ background:#fee2e2; color:#991b1b; }
.alerta-banner strong{ display:block; margin-bottom:0.1rem; }

/* ---------- Servicios ---------- */
.servicio-card{
    background:var(--card); border-radius:16px; padding:0; margin-bottom:1rem;
    box-shadow:0 2px 10px rgba(0,0,0,0.06); border:1px solid var(--border);
    border-left:5px solid var(--border); overflow:hidden;
}
.servicio-card.estado-pendiente{ border-left-color:var(--warning); }
.servicio-card.estado-en_ruta{ border-left-color:var(--primary); }
.servicio-card.estado-finalizado{ border-left-color:var(--success); }
.servicio-card.estado-cancelado{ border-left-color:var(--text-secondary); }

.servicio-card-header{
    display:flex; justify-content:space-between; align-items:flex-start; gap:0.6rem;
    padding:1rem 1.1rem 0.85rem; border-bottom:1px solid #f1f5f9;
}
.servicio-cliente{ font-weight:800; font-size:1.05rem; line-height:1.2; }
.servicio-fecha{ font-size:0.78rem; color:var(--text-secondary); margin-top:0.3rem; display:flex; align-items:center; gap:0.35rem; }
.servicio-badges{ display:flex; flex-direction:column; align-items:flex-end; gap:0.35rem; flex-shrink:0; }

.badge{
    font-size:0.72rem; font-weight:700; padding:0.3rem 0.65rem; border-radius:20px; white-space:nowrap;
    display:inline-flex; align-items:center; gap:0.3rem;
}
.badge-pendiente{ background:#fef3c7; color:#92400e; }
.badge-en_ruta{ background:#dbeafe; color:#1e40af; }
.badge-finalizado{ background:#dcfce7; color:#166534; }
.badge-cancelado{ background:#f1f5f9; color:#64748b; }
.badge-default{ background:#e2e8f0; color:#475569; }
.badge-gps{ background:#eef2ff; color:#3730a3; }

.servicio-card-body{ padding:0.95rem 1.1rem 1.1rem; }

.servicio-piloto{
    display:flex; align-items:center; gap:0.5rem; font-size:0.86rem; font-weight:700; color:var(--text);
    background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:0.55rem 0.75rem; margin-bottom:0.75rem;
}
.servicio-piloto i{ color:var(--primary); font-size:0.85rem; }

.servicio-hora-box{
    display:flex; align-items:center; justify-content:space-between;
    background:#fef2f2; border:1px solid #fecaca; border-radius:12px;
    padding:0.6rem 0.9rem; margin-bottom:0.75rem;
}
.servicio-hora-label{
    font-size:0.72rem; text-transform:uppercase; letter-spacing:0.4px; color:var(--primary-dark);
    font-weight:700; display:flex; align-items:center; gap:0.35rem;
}
.servicio-hora-valor{ font-size:1.3rem; font-weight:800; color:var(--primary-dark); }

.ruta-box{
    background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:0.7rem 0.85rem;
    margin-bottom:0.75rem; display:flex; flex-direction:column; gap:0.5rem;
}
.ruta-punto{ display:flex; align-items:flex-start; gap:0.55rem; font-size:0.88rem; }
.ruta-punto .punto-icono{ width:20px; text-align:center; margin-top:0.1rem; flex-shrink:0; }
.ruta-punto.origen .punto-icono{ color:var(--success); }
.ruta-punto.destino .punto-icono{ color:var(--danger); }
.ruta-punto .punto-texto{ display:flex; flex-direction:column; }
.ruta-punto .punto-label{ font-size:0.65rem; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-secondary); font-weight:700; }
.ruta-punto .punto-lugar{ font-weight:600; }
.ruta-linea{ width:1px; height:10px; background:var(--border); margin-left:9.5px; }

.seccion-label{
    font-size:0.68rem; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-secondary);
    font-weight:700; margin-bottom:0.4rem; display:flex; align-items:center; gap:0.35rem;
}

.tipos-pills{ display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.75rem; }
.tipo-pill{
    display:inline-block; padding:0.28rem 0.65rem; border-radius:8px; font-size:0.78rem; font-weight:600;
    background:#f8fafc; border:1px solid var(--border); color:var(--text);
}

.personal-lista{ display:grid; grid-template-columns:repeat(auto-fill, minmax(130px,1fr)); gap:0.45rem; margin-bottom:0.75rem; }
.personal-item{
    display:flex; align-items:center; justify-content:space-between; gap:0.35rem; flex-wrap:wrap;
    background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:0.5rem 0.6rem; font-size:0.8rem;
}
.personal-item-left{ display:flex; align-items:center; gap:0.4rem; min-width:0; }
.personal-item-left i{ color:var(--text-secondary); flex-shrink:0; font-size:0.85rem; }
.personal-item.personal-item-gps{ background:#fef2f2; border-color:#fecaca; }
.personal-item.personal-item-gps .personal-item-left i{ color:var(--primary-dark); }
.personal-nombre{ font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chip-gps{
    background:#eef2ff; color:#3730a3; font-size:0.65rem; font-weight:700; padding:0.15rem 0.45rem;
    border-radius:10px; display:inline-flex; align-items:center; gap:0.25rem; white-space:nowrap; flex-shrink:0;
}

/* ---------- Datos del transporte: grid compacto de 2 columnas, siempre visible ---------- */
.transporte-grid{ display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; }
.transporte-item{ display:flex; align-items:center; gap:0.5rem; min-width:0; }
.transporte-item i{ color:var(--primary); font-size:0.8rem; flex-shrink:0; width:16px; text-align:center; }
.transporte-texto{ display:flex; flex-direction:column; min-width:0; }
.transporte-label{ font-size:0.62rem; text-transform:uppercase; letter-spacing:0.3px; color:var(--text-secondary); font-weight:700; }
.transporte-valor{ font-size:0.83rem; font-weight:700; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.transporte-valor.vacio{ color:#cbd5e1; font-weight:500; font-style:italic; }

.obs{ margin-top:0.2rem; font-size:0.83rem; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:0.65rem 0.8rem; color:var(--text-secondary); display:flex; gap:0.5rem; align-items:flex-start; }
.obs i{ margin-top:0.15rem; flex-shrink:0; }

.contador-operaciones{ font-size:0.78rem; color:var(--text-secondary); margin-bottom:0.6rem; }

/* ---------- Grid responsivo de tarjetas: 1 columna en celular, 2-3 en pantallas anchas ---------- */
.servicios-grid{ display:grid; grid-template-columns:1fr; gap:1rem; }
@media (min-width:700px){ .servicios-grid{ grid-template-columns:repeat(2,1fr); } }
@media (min-width:1000px){ .servicios-grid{ grid-template-columns:repeat(3,1fr); } }
.servicios-grid .servicio-card{ margin-bottom:0; }

/* ---------- Vista de tabla (estilo panel de Operaciones) ---------- */
.tabla-wrap{
    overflow-x:auto; background:var(--card); border-radius:16px; border:1px solid var(--border);
    box-shadow:0 2px 10px rgba(0,0,0,0.06); -webkit-overflow-scrolling:touch;
}
.tabla-servicios{ width:100%; min-width:965px; border-collapse:collapse; font-size:0.78rem; table-layout:fixed; }
.tabla-servicios th:nth-child(1), .tabla-servicios td:nth-child(1){ width:80px; }
.tabla-servicios th:nth-child(2), .tabla-servicios td:nth-child(2){ width:75px; }
.tabla-servicios th:nth-child(3), .tabla-servicios td:nth-child(3){ width:95px; }
.tabla-servicios th:nth-child(4), .tabla-servicios td:nth-child(4){ width:165px; }
.tabla-servicios th:nth-child(5), .tabla-servicios td:nth-child(5){ width:65px; }
.tabla-servicios th:nth-child(6), .tabla-servicios td:nth-child(6){ width:120px; }
.tabla-servicios th:nth-child(7), .tabla-servicios td:nth-child(7){ width:120px; }
.tabla-servicios th:nth-child(8), .tabla-servicios td:nth-child(8){ width:145px; }
.tabla-servicios th:nth-child(9), .tabla-servicios td:nth-child(9){ width:100px; }
/* En pantallas grandes, la tabla vuelve a repartirse en 100% del ancho
   disponible (como antes) en vez de quedarse en su ancho mínimo fijo. */
@media (min-width:1000px){
    .tabla-servicios{ min-width:0; }
    .tabla-servicios th:nth-child(1), .tabla-servicios td:nth-child(1){ width:8%; }
    .tabla-servicios th:nth-child(2), .tabla-servicios td:nth-child(2){ width:8%; }
    .tabla-servicios th:nth-child(3), .tabla-servicios td:nth-child(3){ width:10%; }
    .tabla-servicios th:nth-child(4), .tabla-servicios td:nth-child(4){ width:16%; }
    .tabla-servicios th:nth-child(5), .tabla-servicios td:nth-child(5){ width:7%; }
    .tabla-servicios th:nth-child(6), .tabla-servicios td:nth-child(6){ width:13%; }
    .tabla-servicios th:nth-child(7), .tabla-servicios td:nth-child(7){ width:13%; }
    .tabla-servicios th:nth-child(8), .tabla-servicios td:nth-child(8){ width:15%; }
    .tabla-servicios th:nth-child(9), .tabla-servicios td:nth-child(9){ width:10%; }
}
.tabla-servicios thead th{
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; text-align:left;
    padding:0.65rem 0.6rem; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.3px;
    white-space:normal; line-height:1.25;
}
.tabla-servicios tbody td{
    padding:0.65rem 0.6rem; border-bottom:1px solid var(--border); vertical-align:middle;
    word-break:break-word; overflow-wrap:break-word;
}
.tabla-servicios tbody tr:last-child td{ border-bottom:none; }
.tabla-servicios tbody tr:nth-child(even){ background:#f8fafc4d; }
.tabla-servicios tbody tr:hover{ background:#fef2f2; }
.tabla-fecha{ color:var(--danger); font-weight:700; white-space:nowrap; }
.tabla-hora{ font-weight:700; color:var(--primary-dark); white-space:nowrap; }
.tabla-cliente{ font-weight:800; }
.tabla-lugar{ line-height:1.3; }

.tabla-datos{ display:flex; flex-direction:column; gap:0.25rem; }
.tabla-datos-fila{
    display:flex; align-items:flex-start; gap:0.3rem; font-size:0.73rem; color:var(--text-secondary);
    line-height:1.3;
}
.tabla-datos-fila i{ color:var(--primary); font-size:0.65rem; width:11px; text-align:center; flex-shrink:0; margin-top:0.2em; }
.tabla-datos-fila b{ color:var(--text); font-weight:700; }
.tabla-datos-fila .sep{ color:#cbd5e1; }

.tabla-custodios{ display:flex; flex-direction:column; gap:0.3rem; }
.tabla-custodio-fila{
    display:flex; align-items:flex-start; gap:0.3rem; font-size:0.73rem; font-weight:700; color:var(--success);
    line-height:1.3;
}
.tabla-custodio-fila i{ font-size:0.65rem; flex-shrink:0; margin-top:0.2em; }
.tabla-custodio-fila .gps-nota{ font-weight:600; color:var(--primary-dark); font-size:0.68rem; }
.tabla-custodio-fila.solo-gps{ color:var(--primary-dark); }

.tabla-tipos{ display:flex; flex-wrap:wrap; gap:0.3rem; }
.tabla-tipos .tipo-pill{ font-size:0.66rem; padding:0.2rem 0.45rem; line-height:1.3; }
.tabla-vacio{ color:#cbd5e1; }

.empty-state{ text-align:center; padding:3rem 1.5rem; color:var(--text-secondary); }
.empty-state i{ font-size:2.5rem; margin-bottom:1rem; opacity:0.4; }
.empty-state p{ font-size:0.9rem; }
.loading{ text-align:center; padding:2.5rem; color:var(--text-secondary); font-size:0.85rem; }

/* ---------- Panel lateral ---------- */
.overlay{
    position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:200; opacity:0; pointer-events:none;
    transition:opacity .2s ease;
}
.overlay.abierto{ opacity:1; pointer-events:auto; }
.panel-lateral{
    position:fixed; top:0; left:0; width:88%; max-width:360px; height:100%; background:var(--bg);
    z-index:201; transform:translateX(-100%); transition:transform .25s ease; overflow-y:auto;
    box-shadow:4px 0 20px rgba(0,0,0,0.15);
}
.panel-lateral.abierto{ transform:translateX(0); }
.panel-header{
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff;
    padding:1.5rem 1.25rem; display:flex; align-items:center; justify-content:space-between;
}
.panel-header h2{ font-size:1.05rem; font-weight:800; }
.btn-cerrar-panel{ background:rgba(255,255,255,0.18); border:none; color:#fff; width:34px; height:34px; border-radius:9px; cursor:pointer; }
.panel-body{ padding:1.25rem; }

.vehiculo-card{
    background:var(--card); border-radius:16px; padding:1.1rem; margin-bottom:1.25rem;
    box-shadow:0 2px 10px rgba(0,0,0,0.06); border:1px solid var(--border);
}
.vehiculo-card-title{ display:flex; align-items:center; gap:0.5rem; font-weight:800; font-size:0.95rem; margin-bottom:0.65rem; }
.vehiculo-card-title i{ color:var(--primary); }
.vehiculo-placa{ font-size:1.3rem; font-weight:800; letter-spacing:0.02em; }
.vehiculo-detalle{ font-size:0.85rem; color:var(--text-secondary); margin-top:0.15rem; }
.vehiculo-km-actual{ font-size:0.8rem; color:var(--text-secondary); margin-top:0.6rem; padding-top:0.6rem; border-top:1px dashed var(--border); }
.vehiculo-km-actual strong{ color:var(--text); }
.vehiculo-sin-asignar{ font-size:0.85rem; color:var(--text-secondary); text-align:center; padding:0.5rem 0; }

.km-card{
    background:var(--card); border-radius:16px; padding:1.1rem; margin-bottom:1.25rem;
    box-shadow:0 2px 10px rgba(0,0,0,0.06); border:1px solid var(--border);
}
.km-card-title{ display:flex; align-items:center; gap:0.5rem; font-weight:800; font-size:0.95rem; margin-bottom:0.85rem; }
.km-card-title i{ color:var(--primary); }
.km-field{ margin-bottom:0.75rem; }
.km-field label{ display:block; font-size:0.78rem; font-weight:600; color:var(--text-secondary); margin-bottom:0.3rem; }
.km-field input{
    width:100%; padding:0.65rem 0.75rem; border:1px solid var(--border); border-radius:10px;
    font-size:0.9rem; font-family:inherit; background:#fff; color:var(--text);
}
.km-btn{
    width:100%; background:var(--primary); color:#fff; border:none; border-radius:10px;
    padding:0.75rem; font-size:0.9rem; font-weight:700; cursor:pointer; margin-top:0.25rem;
}
.km-btn:disabled{ opacity:0.6; cursor:not-allowed; }
.km-msg{ font-size:0.8rem; margin-top:0.6rem; padding:0.5rem 0.65rem; border-radius:8px; display:none; }
.km-msg.ok{ display:block; background:#dcfce7; color:#166534; }
.km-msg.error{ display:block; background:#fee2e2; color:#991b1b; }
.km-msg.alerta{ display:block; }

/* Botón flotante para reportar kilometraje rápido, siempre visible en la pantalla principal */
.btn-flotante-km{
    position:fixed; bottom:1.25rem; right:1.25rem; z-index:50;
    background:var(--primary); color:#fff; border:none; border-radius:50px;
    padding:0.85rem 1.25rem; font-size:0.85rem; font-weight:700; cursor:pointer;
    display:flex; align-items:center; gap:0.5rem; box-shadow:0 6px 20px rgba(227,27,35,0.4);
}

/* ---------- Pantalla emergente de eventos: asignado / finalizado / cancelado ---------- */
.evento-overlay{
    position:fixed; inset:0; z-index:500; display:flex; align-items:center; justify-content:center;
    padding:1.5rem; background:rgba(15,23,42,0.55); opacity:0; pointer-events:none; transition:opacity .25s ease;
}
.evento-overlay.visible{ opacity:1; pointer-events:auto; }
.evento-caja{
    background:#fff; border-radius:20px; padding:2rem 1.75rem; max-width:380px; width:100%; text-align:center;
    box-shadow:0 20px 50px rgba(0,0,0,0.3); border-top:6px solid var(--primary);
    transform:translateY(12px); transition:transform .25s ease;
}
.evento-overlay.visible .evento-caja{ transform:translateY(0); }
.evento-icono{
    width:64px; height:64px; border-radius:50%; margin:0 auto 1rem; display:flex; align-items:center; justify-content:center;
    font-size:1.8rem; color:#fff; background:var(--primary);
}
.evento-titulo{ font-size:1.2rem; font-weight:800; margin-bottom:0.5rem; color:var(--text); }
.evento-detalle{ font-size:0.9rem; color:var(--text-secondary); margin-bottom:1.5rem; line-height:1.4; }
.evento-cerrar{
    background:var(--primary); color:#fff; border:none; border-radius:10px; padding:0.75rem 1.5rem;
    font-size:0.9rem; font-weight:700; cursor:pointer; width:100%;
}
.evento-overlay.evento-finalizado .evento-caja{ border-top-color:var(--success); }
.evento-overlay.evento-finalizado .evento-icono{ background:var(--success); }
.evento-overlay.evento-finalizado .evento-cerrar{ background:var(--success); }
.evento-overlay.evento-cancelado .evento-caja{ border-top-color:var(--text-secondary); }
.evento-overlay.evento-cancelado .evento-icono{ background:var(--text-secondary); }
.evento-overlay.evento-cancelado .evento-cerrar{ background:var(--text-secondary); }
.evento-overlay.evento-asignado .evento-caja{ border-top-color:var(--primary); }
.evento-overlay.evento-asignado .evento-icono{ background:var(--primary); }
</style>
</head>
<body>

<div class="bienvenida-overlay" id="bienvenida-overlay">
    <div class="bienvenida-icono"><i class="fas fa-shield-halved"></i></div>
    <div class="bienvenida-titulo">¡Bienvenido!</div>
    <div class="bienvenida-nombre"><?= htmlspecialchars($nombrePiloto) ?></div>
    <div class="bienvenida-sub">Estos son tus servicios de hoy</div>
</div>

<div class="evento-overlay" id="evento-overlay"></div>

<div class="overlay" id="overlay" onclick="cerrarPanel()"></div>

<div class="panel-lateral" id="panel-lateral">
    <div class="panel-header">
        <h2><i class="fas fa-car"></i> Mi vehículo</h2>
        <button class="btn-cerrar-panel" onclick="cerrarPanel()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="panel-body">
        <div id="vehiculo-info">
            <div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
        </div>

        <div class="alerta-banner" id="alerta-banner-panel"></div>

        <div class="km-card">
            <div class="km-card-title"><i class="fas fa-gauge-high"></i> Reportar kilometraje</div>
            <form id="form-km">
                <div class="km-field">
                    <label for="km-valor">Kilometraje actual</label>
                    <input type="number" id="km-valor" inputmode="numeric" min="0" placeholder="Ej. 45230" required>
                </div>
                <div class="km-field">
                    <label for="km-fecha">Fecha</label>
                    <input type="date" id="km-fecha" required>
                </div>
                <div class="km-field">
                    <label for="km-obs">Observaciones (opcional)</label>
                    <input type="text" id="km-obs" placeholder="Ej. tanque lleno, unidad sin novedad">
                </div>
                <button type="submit" class="km-btn" id="km-btn">Guardar kilometraje</button>
                <div class="km-msg" id="km-msg"></div>
            </form>
        </div>
    </div>
</div>

<header>
    <div class="header-left">
        <button class="btn-menu" onclick="abrirPanel()"><i class="fas fa-bars"></i></button>
        <div class="saludo-bloque">
            <div class="saludo">Bienvenido,</div>
            <div class="nombre"><?php echo htmlspecialchars($nombrePiloto); ?></div>
        </div>
    </div>
    <a href="piloto_logout.php" class="btn-logout" onclick="return confirm('¿Cerrar sesión?');">
        <i class="fas fa-arrow-right-from-bracket"></i>
    </a>
</header>

<main>
    <div class="alerta-banner" id="alerta-banner-main"></div>

    <div class="section-title">
        <span>Mis servicios</span>
        <span class="sync-status" id="sync-status"><span class="sync-dot"></span><span id="sync-status-texto">Cargando...</span></span>
    </div>

    <div class="vista-toggle" id="vista-toggle">
        <button type="button" class="vista-btn" data-vista="tarjetas" onclick="cambiarVista('tarjetas')">
            <i class="fas fa-id-card"></i> Tarjetas
        </button>
        <button type="button" class="vista-btn" data-vista="tabla" onclick="cambiarVista('tabla')">
            <i class="fas fa-table"></i> Tabla
        </button>
    </div>

    <div id="lista-servicios">
        <div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando tus servicios...</div>
    </div>
</main>

<button class="btn-flotante-km" onclick="abrirPanel()">
    <i class="fas fa-gauge-high"></i> Reportar km
</button>

<script>
const NOMBRE_PILOTO_COMPLETO = <?= json_encode($nombrePiloto) ?>;

// ------------------------------------------------------------
// Pantalla de bienvenida: se muestra cada vez que se entra a la
// página, con el nombre completo del piloto tal como está
// registrado. Se oculta sola o al tocarla.
// ------------------------------------------------------------
(function mostrarBienvenida() {
    const overlay = document.getElementById('bienvenida-overlay');
    if (!overlay) return;

    const ocultar = () => {
        overlay.classList.add('oculto');
        setTimeout(() => overlay.remove(), 500);
    };
    overlay.addEventListener('click', ocultar);
    setTimeout(ocultar, 2200);
})();

const ESTADOS = {
    pendiente:  { label: 'Pendiente',  clase: 'badge-pendiente' },
    en_ruta:    { label: 'En ruta',    clase: 'badge-en_ruta' },
    finalizado: { label: 'Finalizado', clase: 'badge-finalizado' },
    cancelado:  { label: 'Cancelado',  clase: 'badge-cancelado' },
};

const ICONOS_ESTADO = {
    pendiente: 'fa-clock', en_ruta: 'fa-truck-fast', finalizado: 'fa-circle-check', cancelado: 'fa-ban',
};

function badgeEstado(estado) {
    const info = ESTADOS[estado] || { label: estado, clase: 'badge-default' };
    const icono = ICONOS_ESTADO[estado] || 'fa-circle';
    return `<span class="badge ${info.clase}"><i class="fas ${icono}"></i> ${info.label}</span>`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatearFecha(fecha) {
    if (!fecha) return '';
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}`;
}

function formatearHora12(hora) {
    if (!hora) return '';
    const [hStr, mStr] = hora.substring(0, 5).split(':');
    let h = parseInt(hStr, 10);
    const sufijo = h >= 12 ? 'p.m.' : 'a.m.';
    h = h % 12;
    if (h === 0) h = 12;
    return `${h}:${mStr} ${sufijo}`;
}

// ------------------------------------------------------------
// Panel lateral
// ------------------------------------------------------------
function abrirPanel() {
    document.getElementById('panel-lateral').classList.add('abierto');
    document.getElementById('overlay').classList.add('abierto');
}
function cerrarPanel() {
    document.getElementById('panel-lateral').classList.remove('abierto');
    document.getElementById('overlay').classList.remove('abierto');
}

// ------------------------------------------------------------
// Vista: Tarjetas / Tabla
// ------------------------------------------------------------
const CACHE_KEY_VISTA = 'forza_ms_vista';
let VISTA_ACTUAL = (function () {
    try {
        const guardada = localStorage.getItem(CACHE_KEY_VISTA);
        return guardada === 'tabla' ? 'tabla' : 'tarjetas';
    } catch (err) {
        return 'tarjetas';
    }
})();
let ULTIMOS_SERVICIOS = [];

function marcarBotonVista() {
    document.querySelectorAll('.vista-btn').forEach(btn => {
        btn.classList.toggle('activo', btn.dataset.vista === VISTA_ACTUAL);
    });
}

function cambiarVista(vista) {
    if (vista === VISTA_ACTUAL) return;
    VISTA_ACTUAL = vista;
    try { localStorage.setItem(CACHE_KEY_VISTA, vista); } catch (err) { /* sin almacenamiento disponible */ }
    marcarBotonVista();
    pintarServicios(ULTIMOS_SERVICIOS);
}
marcarBotonVista();

// ------------------------------------------------------------
// Pantalla emergente + notificaciones del navegador: se disparan
// cuando un servicio se asigna, se finaliza o se cancela. Se
// detectan comparando el estado de cada operación contra el
// último estado conocido, guardado en localStorage.
// ------------------------------------------------------------
const CACHE_KEY_ESTADOS = 'forza_ms_estados_conocidos';

function leerEstadosConocidos() {
    try {
        const raw = localStorage.getItem(CACHE_KEY_ESTADOS);
        return raw ? JSON.parse(raw) : null;
    } catch (err) { return null; }
}
function guardarEstadosConocidos(mapa) {
    try { localStorage.setItem(CACHE_KEY_ESTADOS, JSON.stringify(mapa)); } catch (err) { /* sin almacenamiento */ }
}

// Pide permiso de notificaciones del navegador apenas carga la página.
// Solo funcionan mientras esta pestaña siga abierta (aunque esté minimizada
// o en segundo plano); si el piloto cierra el navegador por completo no
// llegarán, ya que esto no usa un service worker de notificaciones push.
if (window.Notification && Notification.permission === 'default') {
    Notification.requestPermission();
}

function enviarNotificacionNavegador(titulo, cuerpo) {
    if (!window.Notification || Notification.permission !== 'granted') return;
    try {
        const n = new Notification(titulo, { body: cuerpo });
        n.onclick = () => { window.focus(); n.close(); };
    } catch (err) { /* algunos navegadores móviles no soportan new Notification() directo */ }
}

function descripcionServicio(op) {
    const partes = [
        op.cliente,
        formatearFecha(op.fecha_servicio),
        op.hora_servicio ? formatearHora12(op.hora_servicio) : null,
        (op.origen && op.destino) ? (op.origen + ' → ' + op.destino) : null,
    ];
    return partes.filter(Boolean).join(' · ');
}

const EVENTOS_NOTIFICACION = {
    asignado:   { icono: 'fa-clipboard-list', clase: 'evento-asignado',   titulo: 'Nuevo servicio asignado' },
    finalizado: { icono: 'fa-circle-check',    clase: 'evento-finalizado', titulo: 'Servicio finalizado' },
    cancelado:  { icono: 'fa-ban',              clase: 'evento-cancelado', titulo: 'Servicio cancelado' },
};

// Texto de la notificación del navegador: a propósito genérico y sin datos
// del servicio (cliente, ruta, hora), ya que puede aparecer sobre cualquier
// pantalla en cualquier momento. El detalle completo solo se ve dentro de
// la pantalla emergente, adentro de la app.
const NOTIFICACION_NAVEGADOR_TEXTO = {
    asignado:   { titulo: 'Nuevo servicio',  cuerpo: 'Tienes un servicio asignado' },
    finalizado: { titulo: 'Actualización',    cuerpo: 'Uno de tus servicios fue finalizado' },
    cancelado:  { titulo: 'Actualización',    cuerpo: 'Uno de tus servicios fue cancelado' },
};

let colaNotificaciones = [];
let mostrandoNotificacion = false;

function encolarNotificacion(tipo, op) {
    const info = EVENTOS_NOTIFICACION[tipo];
    if (!info) return;

    colaNotificaciones.push({ tipo, op });
    if (!mostrandoNotificacion) mostrarSiguienteNotificacion();

    const notifGenerica = NOTIFICACION_NAVEGADOR_TEXTO[tipo];
    if (notifGenerica) enviarNotificacionNavegador(notifGenerica.titulo, notifGenerica.cuerpo);
}

function mostrarSiguienteNotificacion() {
    const overlay = document.getElementById('evento-overlay');
    if (colaNotificaciones.length === 0) { mostrandoNotificacion = false; return; }
    mostrandoNotificacion = true;

    const { tipo, op } = colaNotificaciones.shift();
    const info = EVENTOS_NOTIFICACION[tipo];

    overlay.className = 'evento-overlay ' + info.clase;
    overlay.innerHTML = `
        <div class="evento-caja">
            <div class="evento-icono"><i class="fas ${info.icono}"></i></div>
            <div class="evento-titulo">${info.titulo}</div>
            <div class="evento-detalle">${escapeHtml(descripcionServicio(op))}</div>
            <button type="button" class="evento-cerrar" onclick="cerrarNotificacionActual()">Entendido</button>
        </div>`;
    // Forzamos un reflow antes de agregar "visible" para que la transición se vea
    requestAnimationFrame(() => overlay.classList.add('visible'));
}

function cerrarNotificacionActual() {
    const overlay = document.getElementById('evento-overlay');
    overlay.classList.remove('visible');
    setTimeout(mostrarSiguienteNotificacion, 300);
}

// Compara el estado recién recibido de cada operación contra el último
// conocido. La primera vez que corre (sin nada guardado todavía) solo
// guarda el estado inicial, para no bombardear con notificaciones de
// servicios que ya estaban asignados desde antes.
function detectarCambios(dataCompleta) {
    const anterior = leerEstadosConocidos();
    const actual = {};
    dataCompleta.forEach(op => { actual[String(op.id)] = op.estado; });

    if (anterior !== null) {
        dataCompleta.forEach(op => {
            const id = String(op.id);
            if (!(id in anterior)) {
                encolarNotificacion('asignado', op);
            } else if (anterior[id] !== op.estado) {
                if (op.estado === 'finalizado') encolarNotificacion('finalizado', op);
                else if (op.estado === 'cancelado') encolarNotificacion('cancelado', op);
            }
        });
    }
    guardarEstadosConocidos(actual);
}

// ------------------------------------------------------------
// Servicios
// ------------------------------------------------------------
const CACHE_KEY_SERVICIOS = 'forza_ms_servicios_cache';
const CACHE_KEY_SERVICIOS_TS = 'forza_ms_servicios_cache_ts';

function guardarCacheServicios(data) {
    try {
        localStorage.setItem(CACHE_KEY_SERVICIOS, JSON.stringify(data));
        localStorage.setItem(CACHE_KEY_SERVICIOS_TS, String(Date.now()));
    } catch (err) { /* almacenamiento no disponible, seguimos sin caché */ }
}

function leerCacheServicios() {
    try {
        const raw = localStorage.getItem(CACHE_KEY_SERVICIOS);
        return raw ? JSON.parse(raw) : null;
    } catch (err) {
        return null;
    }
}

function formatoHora(ts) {
    const d = new Date(ts);
    return d.toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit' });
}

function marcarEstadoSync(estado) {
    const badge = document.getElementById('sync-status');
    const texto = document.getElementById('sync-status-texto');
    if (!badge || !texto) return;
    badge.classList.remove('en-linea', 'sin-conexion');
    if (estado === 'en-linea') {
        badge.classList.add('en-linea');
        texto.textContent = 'Actualizado · ' + formatoHora(Date.now());
    } else if (estado === 'sin-conexion') {
        badge.classList.add('sin-conexion');
        const ts = localStorage.getItem(CACHE_KEY_SERVICIOS_TS);
        texto.textContent = ts ? ('Sin conexión · datos de ' + formatoHora(parseInt(ts, 10))) : 'Sin conexión';
    } else {
        texto.textContent = 'Cargando...';
    }
}

function pintarServicios(data) {
    ULTIMOS_SERVICIOS = Array.isArray(data) ? data : [];
    const cont = document.getElementById('lista-servicios');

    if (ULTIMOS_SERVICIOS.length === 0) {
        cont.innerHTML = `<div class="empty-state"><i class="fas fa-calendar-xmark"></i><p>No tienes servicios asignados por ahora</p></div>`;
        return;
    }

    const contadorHtml = `<div class="contador-operaciones">${ULTIMOS_SERVICIOS.length} operaci${ULTIMOS_SERVICIOS.length === 1 ? 'ón' : 'ones'}</div>`;

    if (VISTA_ACTUAL === 'tabla') {
        cont.innerHTML = contadorHtml + renderTabla(ULTIMOS_SERVICIOS);
    } else {
        const cardsHtml = ULTIMOS_SERVICIOS.map(renderCard).join('');
        cont.innerHTML = `${contadorHtml}<div class="servicios-grid">${cardsHtml}</div>`;
    }
}

// Se sincroniza en segundo plano: si hay internet, actualiza y guarda en caché.
// Si no hay internet (o falla la petición), NUNCA borra lo que ya se ve en pantalla;
// muestra la última copia guardada localmente y avisa que está desactualizada.
// En cuanto vuelve la señal, esta misma función se vuelve a llamar y sincroniza sola.
async function cargarServicios() {
    const cont = document.getElementById('lista-servicios');
    const cachePrevia = leerCacheServicios();
    const esPrimeraCarga = cont.querySelector('.loading') !== null;

    // Primer pintado instantáneo desde caché (por si no hay señal desde el inicio)
    if (esPrimeraCarga && cachePrevia) {
        pintarServicios(cachePrevia);
    }

    try {
        const res = await fetch('api/piloto_mis_servicios.php');

        if (res.status === 401) {
            window.location.href = 'piloto_login.php';
            return;
        }

        const data = await res.json();

        if (!Array.isArray(data)) {
            throw new Error(data.message || 'Respuesta inválida');
        }

        // Detecta asignaciones nuevas y cambios a finalizado/cancelado
        // usando la lista completa (el backend incluye esos dos estados
        // por un par de días para poder compararlos).
        detectarCambios(data);

        // Lo que se pinta en pantalla nunca incluye finalizados ni cancelados.
        const visibles = data.filter(s => s.estado !== 'finalizado' && s.estado !== 'cancelado');

        guardarCacheServicios(visibles);
        pintarServicios(visibles);
        marcarEstadoSync('en-linea');
    } catch (err) {
        // Sin conexión (o error de servidor): conservamos lo que ya está en pantalla.
        if (esPrimeraCarga) {
            if (cachePrevia) {
                pintarServicios(cachePrevia);
            } else {
                cont.innerHTML = `<div class="empty-state"><i class="fas fa-wifi"></i><p>Sin conexión. En cuanto tengas señal, tus servicios se cargarán automáticamente.</p></div>`;
            }
        }
        marcarEstadoSync('sin-conexion');
    }
}

// Detecta si el servicio lleva GPS: por dispositivo asignado o por tipo de servicio
function servicioLlevaGPS(custodios) {
    if (!Array.isArray(custodios)) return false;
    return custodios.some(c => c.gps_imei || c.gps_id || (c.tipo_servicio && /gps|marchamo|ruta segura/i.test(c.tipo_servicio)));
}

function listaTiposServicio(s, custodios) {
    const tiposPresentes = [...new Set(custodios.map(c => c.tipo_servicio).filter(Boolean))];
    return tiposPresentes.length ? tiposPresentes : (s.tipo_servicio ? [s.tipo_servicio] : []);
}

function renderCard(s) {
    const custodios = Array.isArray(s.custodios) ? s.custodios : [];
    const llevaGPS = servicioLlevaGPS(custodios);
    const listaTipos = listaTiposServicio(s, custodios);
    const tiposHtml = listaTipos.length
        ? `<div class="tipos-pills">${listaTipos.map(t => `<span class="tipo-pill">${escapeHtml(t)}</span>`).join('')}</div>`
        : '';

    const itemTransporte = (icono, label, valor) => `
        <div class="transporte-item">
            <i class="fas ${icono}"></i>
            <div class="transporte-texto">
                <span class="transporte-label">${label}</span>
                <span class="transporte-valor${valor ? '' : ' vacio'}">${valor ? escapeHtml(valor) : 'Sin dato'}</span>
            </div>
        </div>`;

    const transporteHtml = `
        <div class="ruta-box">
            <div class="seccion-label"><i class="fas fa-truck"></i> Datos del transporte</div>
            <div class="transporte-grid">
                ${itemTransporte('fa-user', 'Conductor', s.conductor)}
                ${itemTransporte('fa-phone', 'Teléfono', s.telefono)}
                ${itemTransporte('fa-box', 'Furgón', s.furgon)}
                ${itemTransporte('fa-id-card', 'Placa', s.placa)}
            </div>
        </div>`;

    return `
    <div class="servicio-card estado-${s.estado || ''}">
        <div class="servicio-card-header">
            <div>
                <div class="servicio-cliente">${escapeHtml(s.cliente)}</div>
                <div class="servicio-fecha"><i class="fas fa-calendar-day"></i> ${formatearFecha(s.fecha_servicio)}</div>
            </div>
            <div class="servicio-badges">
                ${badgeEstado(s.estado)}
                ${llevaGPS ? `<span class="badge badge-gps"><i class="fas fa-satellite-dish"></i> Con GPS</span>` : ''}
            </div>
        </div>

        <div class="servicio-card-body">

            ${s.hora_servicio ? `
            <div class="servicio-hora-box">
                <span class="servicio-hora-label"><i class="fas fa-clock"></i> Hora</span>
                <span class="servicio-hora-valor">${formatearHora12(s.hora_servicio)}</span>
            </div>` : ''}

            ${transporteHtml}

            <div class="ruta-box">
                <div class="ruta-punto origen">
                    <div class="punto-icono"><i class="fas fa-circle" style="font-size:0.55rem;"></i></div>
                    <div class="punto-texto"><span class="punto-label">Origen</span><span class="punto-lugar">${escapeHtml(s.origen)}</span></div>
                </div>
                <div class="ruta-linea"></div>
                <div class="ruta-punto destino">
                    <div class="punto-icono"><i class="fas fa-location-dot"></i></div>
                    <div class="punto-texto"><span class="punto-label">Destino</span><span class="punto-lugar">${escapeHtml(s.destino)}</span></div>
                </div>
            </div>

            ${tiposHtml}

            ${renderCustodios(custodios)}

            ${s.observaciones ? `<div class="obs"><i class="fas fa-note-sticky"></i><span>${escapeHtml(s.observaciones)}</span></div>` : ''}
        </div>
    </div>`;
}

function renderCustodios(custodios) {
    if (!Array.isArray(custodios) || custodios.length === 0) return '';

    const filas = custodios.map(c => {
        const tienePersona = !!c.nombre;
        const gpsTexto = c.gps_imei ? c.gps_imei : (c.gps_id ? ('GPS #' + c.gps_id) : '');
        const tieneGPS = !!gpsTexto;

        if (!tienePersona && !tieneGPS) return '';

        // Dispositivo GPS sin custodio (ej. Marchamo Electrónico, Ruta Segura): fila propia,
        // sin mezclar "sin custodio" con el dato del GPS.
        if (!tienePersona && tieneGPS) {
            return `<div class="personal-item personal-item-gps">
                <div class="personal-item-left">
                    <i class="fas fa-satellite-dish"></i>
                    <span class="personal-nombre">${c.tipo_servicio ? escapeHtml(c.tipo_servicio) : 'Dispositivo GPS'}</span>
                </div>
                <span class="chip-gps"><i class="fas fa-hashtag"></i>${escapeHtml(gpsTexto)}</span>
            </div>`;
        }

        // Custodio (persona), con GPS adicional si aplica
        return `<div class="personal-item">
            <div class="personal-item-left">
                <i class="fas fa-user-shield"></i>
                <span class="personal-nombre">${escapeHtml(c.nombre)}</span>
            </div>
            ${tieneGPS ? `<span class="chip-gps"><i class="fas fa-satellite-dish"></i>${escapeHtml(gpsTexto)}</span>` : ''}
        </div>`;
    }).filter(Boolean).join('');

    if (!filas) return '';

    return `<div style="margin-bottom:0.75rem;">
        <div class="seccion-label"><i class="fas fa-users"></i> Personal / GPS asignado</div>
        <div class="personal-lista">${filas}</div>
    </div>`;
}

// ------------------------------------------------------------
// Vista de tabla (mismo espíritu que el panel de Operaciones)
// ------------------------------------------------------------
function renderCustodiosTabla(custodios) {
    if (!Array.isArray(custodios) || custodios.length === 0) return '<span class="tabla-vacio">—</span>';

    const items = custodios.map(c => {
        const gpsTexto = c.gps_imei ? c.gps_imei : (c.gps_id ? ('GPS #' + c.gps_id) : '');

        if (c.nombre) {
            return `<div class="tabla-custodio-fila">
                <i class="fas fa-user-shield"></i>
                <span>${escapeHtml(c.nombre)}${gpsTexto ? ` <span class="gps-nota">· ${escapeHtml(gpsTexto)}</span>` : ''}</span>
            </div>`;
        }
        if (gpsTexto) {
            return `<div class="tabla-custodio-fila solo-gps">
                <i class="fas fa-satellite-dish"></i>
                <span>${c.tipo_servicio ? escapeHtml(c.tipo_servicio) + ' · ' : ''}${escapeHtml(gpsTexto)}</span>
            </div>`;
        }
        return '';
    }).filter(Boolean);

    return items.length ? `<div class="tabla-custodios">${items.join('')}</div>` : '<span class="tabla-vacio">—</span>';
}

function renderFilaTabla(s) {
    const custodios = Array.isArray(s.custodios) ? s.custodios : [];
    const listaTipos = listaTiposServicio(s, custodios);
    const tiposHtml = listaTipos.length
        ? `<div class="tabla-tipos">${listaTipos.map(t => `<span class="tipo-pill">${escapeHtml(t)}</span>`).join('')}</div>`
        : '<span class="tabla-vacio">—</span>';

    const filaConductor = (s.conductor || s.telefono)
        ? `<div class="tabla-datos-fila">
               <i class="fas fa-user"></i>
               <span>
               ${s.conductor ? `<b>${escapeHtml(s.conductor)}</b>` : ''}
               ${s.conductor && s.telefono ? `<span class="sep">·</span>` : ''}
               ${s.telefono ? escapeHtml(s.telefono) : ''}
               </span>
           </div>` : '';

    const filaVehiculo = (s.furgon || s.placa)
        ? `<div class="tabla-datos-fila">
               <i class="fas fa-truck"></i>
               <span>
               ${s.furgon ? escapeHtml(s.furgon) : ''}
               ${s.furgon && s.placa ? `<span class="sep">·</span>` : ''}
               ${s.placa ? escapeHtml(s.placa) : ''}
               </span>
           </div>` : '';

    const datosHtml = (filaConductor || filaVehiculo)
        ? `<div class="tabla-datos">${filaConductor}${filaVehiculo}</div>`
        : '<span class="tabla-vacio">—</span>';

    return `
        <tr>
            <td>${tiposHtml}</td>
            <td class="tabla-fecha">${formatearFecha(s.fecha_servicio)}</td>
            <td class="tabla-cliente">${escapeHtml(s.cliente)}</td>
            <td>${datosHtml}</td>
            <td class="tabla-hora">${s.hora_servicio ? formatearHora12(s.hora_servicio) : '<span class="tabla-vacio">—</span>'}</td>
            <td class="tabla-lugar">${escapeHtml(s.origen)}</td>
            <td class="tabla-lugar">${escapeHtml(s.destino)}</td>
            <td>${renderCustodiosTabla(custodios)}</td>
            <td>${badgeEstado(s.estado)}</td>
        </tr>`;
}

function renderTabla(data) {
    const filas = data.map(renderFilaTabla).join('');
    return `
    <div class="tabla-wrap">
        <table class="tabla-servicios">
            <colgroup>
                <col class="col-servicio"><col class="col-fecha"><col class="col-cliente">
                <col class="col-datos"><col class="col-hora"><col class="col-origen">
                <col class="col-destino"><col class="col-custodio"><col class="col-estado">
            </colgroup>
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Datos</th>
                    <th>Hora</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Custodio / GPS</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>${filas}</tbody>
        </table>
    </div>`;
}

cargarServicios();
// Refresca la lista cada 30 segundos por si le asignan algo nuevo mientras tiene la app abierta
setInterval(cargarServicios, 30000);
// Refresca el vehículo asignado / alerta de aceite cada 30 segundos, igual que los servicios,
// para que se refleje solo si un administrador le asigna (o cambia) el vehículo.
setInterval(cargarVehiculoAsignado, 30000);

// En cuanto el navegador detecta que volvió la señal, sincroniza de inmediato
// (sin esperar a los 30s del intervalo). También reflejamos el "sin conexión"
// apenas se pierde, sin esperar a que falle la siguiente petición.
window.addEventListener('online', () => { cargarServicios(); cargarVehiculoAsignado(); });
window.addEventListener('offline', () => marcarEstadoSync('sin-conexion'));

// ------------------------------------------------------------
// Vehículo asignado + alerta de aceite
// ------------------------------------------------------------
function pintarAlerta(alerta) {
    const bannerMain = document.getElementById('alerta-banner-main');
    const bannerPanel = document.getElementById('alerta-banner-panel');
    [bannerMain, bannerPanel].forEach(b => { b.className = 'alerta-banner'; b.innerHTML = ''; });

    if (!alerta) return;

    const iconos = { aviso: 'fa-circle-exclamation', urgente: 'fa-triangle-exclamation', critico: 'fa-oil-can' };
    const titulos = { aviso: 'Cambio de aceite pendiente', urgente: 'Cambio de aceite urgente', critico: 'Cambio de aceite crítico' };
    const icono = iconos[alerta.tipo] || 'fa-circle-exclamation';
    const titulo = titulos[alerta.tipo] || 'Alerta';

    const html = `<i class="fas ${icono}"></i><div><strong>${titulo}</strong>${escapeHtml(alerta.mensaje)}</div>`;
    [bannerMain, bannerPanel].forEach(b => {
        b.classList.add('visible', 'nivel-' + alerta.nivel);
        b.innerHTML = html;
    });
}

const CACHE_KEY_VEHICULO = 'forza_ms_vehiculo_cache';

function guardarCacheVehiculo(data) {
    try { localStorage.setItem(CACHE_KEY_VEHICULO, JSON.stringify(data)); } catch (err) { /* sin almacenamiento disponible */ }
}
function leerCacheVehiculo() {
    try { const raw = localStorage.getItem(CACHE_KEY_VEHICULO); return raw ? JSON.parse(raw) : null; } catch (err) { return null; }
}

function pintarVehiculo(data, esCache) {
    const cont = document.getElementById('vehiculo-info');

    if (!data.asignado) {
        cont.innerHTML = `<div class="vehiculo-card"><div class="vehiculo-sin-asignar"><i class="fas fa-circle-exclamation"></i> Todavía no tienes un vehículo asignado. Contacta a un administrador.</div></div>`;
        document.getElementById('km-btn').disabled = true;
        return;
    }
    document.getElementById('km-btn').disabled = false;

    const v = data.vehiculo;
    const etiqueta = [v.marca, v.modelo].filter(Boolean).join(' ');
    const ultimoKm = data.ultimo_kilometraje
        ? `<div class="vehiculo-km-actual">Último reporte: <strong>${data.ultimo_kilometraje.kilometraje.toLocaleString('es-HN')} km</strong> · ${formatearFecha(data.ultimo_kilometraje.fecha)}</div>`
        : `<div class="vehiculo-km-actual">Aún no has reportado kilometraje de este vehículo.</div>`;

    cont.innerHTML = `
        <div class="vehiculo-card">
            <div class="vehiculo-card-title"><i class="fas fa-car-side"></i> Vehículo asignado${esCache ? ' <span style="font-weight:600;color:var(--text-secondary);font-size:0.7rem;">(sin conexión)</span>' : ''}</div>
            <div class="vehiculo-placa">${escapeHtml(v.placa)}</div>
            <div class="vehiculo-detalle">${escapeHtml(etiqueta || '—')}${v.color ? ' · ' + escapeHtml(v.color) : ''}</div>
            ${ultimoKm}
        </div>`;

    pintarAlerta(data.alerta_aceite);
}

// Igual que con los servicios: si falla la conexión, se muestra la última
// copia guardada del vehículo/alerta en vez de un mensaje de error.
async function cargarVehiculoAsignado() {
    try {
        const res = await fetch('api/piloto_vehiculo_actual.php');
        if (res.status === 401) {
            window.location.href = 'piloto_login.php';
            return;
        }
        const data = await res.json();
        guardarCacheVehiculo(data);
        pintarVehiculo(data, false);
    } catch (err) {
        const cache = leerCacheVehiculo();
        if (cache) {
            pintarVehiculo(cache, true);
        } else {
            document.getElementById('vehiculo-info').innerHTML = `<div class="vehiculo-card"><div class="vehiculo-sin-asignar"><i class="fas fa-wifi"></i> Sin conexión. Se actualizará automáticamente al recuperar señal.</div></div>`;
        }
    }
}

// ------------------------------------------------------------
// Registro de kilometraje
// ------------------------------------------------------------
document.getElementById('km-fecha').value = new Date().toISOString().slice(0, 10);

document.getElementById('form-km').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = document.getElementById('km-btn');
    const msg = document.getElementById('km-msg');
    msg.className = 'km-msg';
    msg.textContent = '';

    const kilometraje = document.getElementById('km-valor').value;
    const fecha = document.getElementById('km-fecha').value;
    const observaciones = document.getElementById('km-obs').value;

    const formData = new FormData();
    // El vehículo ya NO se elige: el backend lo determina por el piloto en sesión.
    formData.append('kilometraje', kilometraje);
    formData.append('fecha', fecha);
    formData.append('observaciones', observaciones);

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        const res = await fetch('api/vehiculo_kilometraje_guardar.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            msg.className = 'km-msg ok';
            msg.textContent = data.message || 'Kilometraje guardado correctamente';
            document.getElementById('km-valor').value = '';
            document.getElementById('km-obs').value = '';
            // Refresca el vehículo/alerta con el nuevo kilometraje reflejado
            cargarVehiculoAsignado();
            if (data.alerta_aceite) pintarAlerta(data.alerta_aceite);
        } else {
            msg.className = 'km-msg error';
            msg.textContent = data.message || 'No se pudo guardar el kilometraje';
        }
    } catch (err) {
        msg.className = 'km-msg error';
        msg.textContent = 'Error de conexión, intenta de nuevo';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar kilometraje';
    }
});

cargarVehiculoAsignado();
</script>

</body>
</html>