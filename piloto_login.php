<?php
session_start();
require_once __DIR__ . '/auth_recordar.php';
// Si ya tiene sesión de piloto activa (o se acaba de restaurar), mándalo directo a sus servicios
if (isset($_SESSION['patrullero_id'])) {
    header("Location: mis_servicios.php");
    exit;
}

// Evita que el navegador guarde esta página en caché: si el piloto da "atrás"
// después de salir, no debe ver el formulario con datos previos ya cargados.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>FORZA - Ingreso Piloto</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        :root {
            /* Paleta oficial FORZA: rojo de marca + gris carbón, tomados del logo */
            --primary-gradient: linear-gradient(135deg, #7a0d13 0%, #c8161f 55%, #ef2b2b 100%);
            --accent-gradient: linear-gradient(135deg, #4b4b52 0%, #2a2a2f 100%);
            --gold-accent: #000000;
            --panel-bg: #1c1c20;
            --panel-bg-2: #2a2a2f;
            --text-primary: #f5f5f7;
            --text-secondary: #9a9aa2;
            --icon-color: #ef4b4b;
            --shadow-soft: 0 25px 70px rgba(199, 22, 25, 0.35);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        html {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--panel-bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        body.light-mode {
            --panel-bg: #f7f4f4;
            --panel-bg-2: #efe6e6;
            --text-primary: #201f22;
            --text-secondary: #6b6870;
            --icon-color: #c8161f;
        }

        body.light-mode .form-input {
            background: rgba(199, 22, 25, 0.05);
            border-color: rgba(199, 22, 25, 0.18);
        }

        body.light-mode .form-input::placeholder { color: #9a9aa2; }

        body.light-mode .footer-text { border-top-color: rgba(199, 22, 25, 0.12); }

        /* ===== Layout dividido ===== */
        .login-page {
            display: flex;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ---- Panel izquierdo: formulario ---- */
        .login-panel {
            flex: 0 0 480px;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3.5rem;
            background: linear-gradient(180deg, var(--panel-bg) 0%, var(--panel-bg-2) 100%);
            position: relative;
            z-index: 5;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 2.75rem;
        }

        .logo-icon {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            box-shadow: var(--shadow-soft);
            position: relative;
        }

        .logo-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 17px;
            border: 1px solid rgba(239, 43, 43, 0.5);
            pointer-events: none;
        }

        .logo-text {
            color: var(--text-primary);
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            line-height: 1;
        }

        .logo-subtitle {
            color: var(--text-primary);
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            margin-top: 0.2rem;
        }

        .form-heading { margin-bottom: 2rem; }

        .form-heading h2 {
            color: var(--text-primary);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.4rem;
        }

        .form-heading p {
            color: var(--text-secondary);
            font-size: 0.92rem;
        }

        .form-group { margin-bottom: 1.4rem; position: relative; }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.88rem;
        }

        .form-input-wrapper { position: relative; }

        .form-input {
            width: 100%;
            padding: 0.95rem 1rem 0.95rem 3rem;
            border: 1.5px solid rgba(154, 154, 162, 0.25);
            border-radius: 12px;
            font-size: 0.98rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
        }

        .form-input::placeholder { color: #6b6870; }

        .form-input:focus {
            outline: none;
            border-color: #ef4b4b;
            box-shadow: 0 0 0 4px rgba(239, 75, 75, 0.15);
            background: rgba(255, 255, 255, 0.07);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--icon-color);
            font-size: 1.02rem;
            transition: all 0.3s ease;
        }

        .form-input:focus + .form-icon {
            color: #ff8080;
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--icon-color);
            cursor: pointer;
            font-size: 1.02rem;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #ff8080;
            transform: translateY(-50%) scale(1.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.85rem;
            font-size: 0.86rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }

        .form-checkbox {
            width: 17px;
            height: 17px;
            accent-color: #c8161f;
        }

        .helper-note {
            color: var(--text-secondary);
            font-size: 0.82rem;
        }

        .btn {
            width: 100%;
            padding: 0.95rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .btn-primary { background: var(--primary-gradient); color: white; }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }

        .btn:hover::before { left: 0; }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(199, 22, 25, 0.4);
        }

        .btn:active { transform: translateY(-1px); }

        .alert {
            padding: 0.9rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.4rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.12);
            color: #f87171;
            border: 1px solid rgba(220, 38, 38, 0.35);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .btn.loading { pointer-events: none; opacity: 0.8; }
        .btn.loading .loading-spinner { display: inline-block; }
        .btn.loading .btn-text { display: none; }

        .footer-text {
            text-align: center;
            color: #6b6870;
            font-size: 0.76rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(154, 154, 162, 0.15);
        }

        .footer-text strong { color: var(--text-secondary); }

        /* ---- Panel derecho: visual interactivo (red de nodos + stats) ---- */
        .visual-panel {
            flex: 1;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            cursor: default;
            background:
                radial-gradient(circle at 25% 25%, rgba(239, 75, 75, 0.55), transparent 55%),
                radial-gradient(circle at 75% 70%, rgba(74, 74, 82, 0.45), transparent 50%),
                linear-gradient(135deg, #7a0d13 0%, #c8161f 50%, #1c1c20 100%);
        }

        /* Canvas de red de nodos: reacciona al cursor, da vida y sensación
           de "sistema en tiempo real" sin depender de ninguna imagen. */
        .visual-panel .network-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            display: block;
        }

        .visual-panel .grid-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(circle at 60% 50%, black 0%, transparent 75%);
            z-index: 1;
            pointer-events: none;
        }

        .visual-panel .glow-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            z-index: 1;
        }

        .visual-panel .glow-1 {
            width: 320px;
            height: 320px;
            top: -60px;
            right: -60px;
            background: rgba(239, 43, 43, 0.35);
            animation: drift 12s ease-in-out infinite;
        }

        .visual-panel .glow-2 {
            width: 260px;
            height: 260px;
            bottom: -40px;
            left: 10%;
            background: rgba(74, 74, 82, 0.45);
            animation: drift 14s ease-in-out infinite reverse;
        }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-25px, 25px) scale(1.1); }
        }

        /* Badge "en vivo": punto pulsante + texto, arriba del panel */
        .visual-panel .live-badge {
            position: absolute;
            top: 2.5rem;
            left: 4rem;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            cursor: pointer;
            user-select: none;
        }

        .visual-panel .live-badge:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .visual-panel .live-dot {
            position: relative;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 10px rgba(52, 211, 153, 0.9);
            flex-shrink: 0;
        }

        .visual-panel .live-dot::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 1.5px solid rgba(52, 211, 153, 0.65);
            animation: radarPing 2.4s ease-out infinite;
        }

        @keyframes radarPing {
            0%   { transform: scale(0.5); opacity: 0.9; }
            100% { transform: scale(3.6); opacity: 0; }
        }

        .visual-panel .live-badge-text {
            display: inline-block;
            animation: badgeSwap 0.35s ease;
        }

        @keyframes badgeSwap {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Contenedor con inclinación 3D suave que sigue al cursor */
        .visual-panel .visual-content {
            position: relative;
            z-index: 4;
            padding: 4rem;
            width: 100%;
            max-width: 560px;
            transform-style: preserve-3d;
            transition: transform 0.25s ease-out;
            will-change: transform;
        }

        .visual-panel .caption h3 {
            color: white;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.45);
        }

        .visual-panel .caption h3 span { color: var(--gold-accent); }

        .visual-panel .caption p {
            color: rgba(255, 255, 255, 0.82);
            font-size: 1rem;
            line-height: 1.5;
            text-shadow: 0 2px 10px rgba(0,0,0,0.4);
            margin-bottom: 2.25rem;
        }

        /* Tarjetas de estadísticas: cristal esmerilado, se elevan al hover */
        .visual-panel .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.9rem;
            perspective: 700px;
        }

        .visual-panel .stat-card {
            position: relative;
            padding: 1rem 0.9rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(10px);
            text-align: left;
            transition: transform 0.15s ease-out, background 0.3s ease, border-color 0.3s ease;
            cursor: pointer;
            will-change: transform;
        }

        .visual-panel .stat-card:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(239, 43, 43, 0.55);
        }

        .visual-panel .stat-num {
            display: block;
            color: white;
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.35);
        }

        .visual-panel .stat-num .stat-suffix {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gold-accent);
        }

        .visual-panel .stat-label {
            display: block;
            margin-top: 0.4rem;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.74rem;
            font-weight: 500;
            line-height: 1.3;
        }

        .visual-panel .stat-card .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.45);
            transform: scale(0);
            animation: rippleExpand 0.6s ease-out forwards;
            pointer-events: none;
        }

        @keyframes rippleExpand {
            to { transform: scale(1); opacity: 0; }
        }

        /* Íconos flotantes con parallax: capa completa, cada ícono se
           mueve según data-depth al mover el cursor sobre el panel. */
        .visual-panel .parallax-layer {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
        }

        .visual-panel .parallax-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.14);
            transition: transform 0.2s ease-out;
            will-change: transform;
        }

        .visual-panel .pi-1 { top: 14%; left: 62%; font-size: 2.6rem; }
        .visual-panel .pi-2 { top: 62%; left: 8%;  font-size: 3.4rem; }
        .visual-panel .pi-3 { top: 8%;  left: 12%; font-size: 2.1rem; }
        .visual-panel .pi-4 { top: 40%; left: 80%; font-size: 2.3rem; }
        .visual-panel .pi-5 { top: 78%; left: 70%; font-size: 1.9rem; }

        /* Puntos indicadores del mensaje rotativo: clicables para saltar
           a un mensaje específico */
        .visual-panel .caption-dots {
            display: flex;
            gap: 0.5rem;
            margin-top: -1rem;
            margin-bottom: 1.75rem;
        }

        .visual-panel .caption-dot {
            width: 22px;
            height: 4px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.28);
            border: none;
            cursor: pointer;
            padding: 0;
            transition: background 0.3s ease, width 0.3s ease;
        }

        .visual-panel .caption-dot.active {
            background: var(--gold-accent);
            width: 34px;
        }

        .visual-panel .caption h3,
        .visual-panel .caption p {
            animation: captionFade 0.45s ease;
        }

        @keyframes captionFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tarjeta de ranking "#1 en seguridad" */
        .visual-panel .rank-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .visual-panel .rank-icon {
            color: var(--gold-accent);
            font-size: 1rem;
            margin-bottom: 0.4rem;
            filter: drop-shadow(0 0 6px rgba(239, 43, 43, 0.6));
            transition: transform 0.3s ease;
        }

        .visual-panel .stat-card:hover .rank-icon {
            transform: scale(1.15) rotate(-6deg);
        }

        /* Halo que sigue al cursor por todo el panel: agrega profundidad
           y una sensación de "luz" que reacciona a cada movimiento. */
        .visual-panel .cursor-spotlight {
            position: absolute;
            inset: 0;
            z-index: 3;
            pointer-events: none;
            background: radial-gradient(circle 220px at var(--sx, 50%) var(--sy, 50%), rgba(255, 255, 255, 0.10), transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .visual-panel.cursor-active .cursor-spotlight {
            opacity: 1;
        }

        /* Botón de tema: fondo sólido en degradado de marca + borde e icono
           blancos, para que sea claramente visible sobre CUALQUIER fondo. */
        .theme-toggle-btn {
            position: fixed;
            top: calc(20px + env(safe-area-inset-top));
            right: calc(20px + env(safe-area-inset-right));
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--primary-gradient);
            border: 2px solid rgba(255, 255, 255, 0.75);
            color: #ffffff;
            font-size: 1.05rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 8px 22px rgba(199, 22, 25, 0.45), 0 2px 8px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 10px 28px rgba(199, 22, 25, 0.55), 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .theme-toggle-btn:focus-visible {
            outline: 3px solid #ef4b4b;
            outline-offset: 3px;
        }

        body.light-mode .theme-toggle-btn {
            border-color: rgba(28, 28, 32, 0.18);
            box-shadow: 0 8px 22px rgba(199, 22, 25, 0.3), 0 2px 8px rgba(0, 0, 0, 0.12);
        }

        /* ===== Responsive: móvil / tablet / desktop, vertical y horizontal ===== */
        @media (max-width: 900px) {
            .visual-panel { display: none; }
            .login-panel {
                flex: 1 1 auto;
                width: 100%;
                padding: 2.5rem 1.5rem;
                justify-content: flex-start;
                padding-top: 4.5rem;
                padding-left: calc(1.5rem + env(safe-area-inset-left));
                padding-right: calc(1.5rem + env(safe-area-inset-right));
                padding-bottom: calc(2.5rem + env(safe-area-inset-bottom));
            }
        }

        @media (max-width: 480px) {
            .login-panel {
                padding: 2rem 1.25rem;
                padding-top: 4.5rem;
                padding-left: calc(1.25rem + env(safe-area-inset-left));
                padding-right: calc(1.25rem + env(safe-area-inset-right));
            }
            .logo-text { font-size: 1.15rem; }
        }

        @media (max-height: 560px) and (orientation: landscape) {
            .login-panel {
                padding-top: 3.25rem;
                padding-bottom: 1.5rem;
                justify-content: flex-start;
            }
            .brand-row { margin-bottom: 1.25rem; }
            .form-heading { margin-bottom: 1.25rem; }
            .form-group { margin-bottom: 0.9rem; }
            .form-options { margin-bottom: 1.25rem; }
            .footer-text { margin-top: 1.25rem; padding-top: 1rem; }
            .theme-toggle-btn {
                top: calc(12px + env(safe-area-inset-top));
                right: calc(12px + env(safe-area-inset-right));
                width: 38px;
                height: 38px;
                font-size: 0.9rem;
            }
        }

        @media (max-height: 700px) and (orientation: portrait) and (max-width: 480px) {
            .brand-row { margin-bottom: 2rem; }
            .form-heading { margin-bottom: 1.5rem; }
            .footer-text { margin-top: 1.5rem; }
        }

        @media (min-width: 1600px) {
            .login-panel { flex-basis: 540px; padding: 4rem; }
        }
    </style>
</head>

<body>
    <button class="theme-toggle-btn" onclick="toggleTheme()" aria-label="Cambiar tema">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="login-page">
        <!-- Panel izquierdo: formulario -->
        <div class="login-panel">
            <div class="brand-row">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <div class="logo-text">FORZA</div>
                    <div class="logo-subtitle">Portal del Piloto</div>
                </div>
            </div>

            <div class="form-heading">
                <h2>Bienvenido, piloto</h2>
                <p>Ingresa con tu teléfono y tu PIN para ver tus servicios.</p>
            </div>

            <div class="alert alert-error" id="errorBox" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="errorText"></span>
            </div>

            <form id="loginForm" onsubmit="ingresar(event)" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <div class="form-input-wrapper">
                        <input type="tel" class="form-input" name="telefono" id="telefono" required
                            placeholder="9999-9999" inputmode="numeric" autocomplete="off" autofocus>
                        <i class="form-icon fas fa-phone"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">PIN (4 dígitos)</label>
                    <div class="form-input-wrapper">
                        <input type="password" class="form-input" name="pin" id="pin" required
                            placeholder="••••" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password">
                        <i class="form-icon fas fa-lock"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword('pin')" aria-label="Mostrar PIN">
                            <i class="fas fa-eye" id="pin-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" class="form-checkbox" name="remember" id="remember">
                        <label for="remember">Recordar mi teléfono</label>
                    </div>
                    <span class="helper-note">¿Olvidaste tu PIN? Contacta a un administrador.</span>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt"></i> Ingresar
                    </span>
                    <div class="loading-spinner"></div>
                </button>
            </form>

            <div class="footer-text">
                © <?php echo date('Y'); ?> <strong>FORZA Secure Logistic</strong>. Todos los derechos reservados.<br>
                <strong>Versión:</strong> 1.0.0
            </div>
        </div>

        <!-- Panel derecho: visual interactivo (red de nodos, íconos con
             parallax, texto rotativo, tarjetas magnéticas, spotlight,
             chispas al click, insignia interactiva) -->
        <div class="visual-panel" id="visualPanel">
            <canvas class="network-canvas" id="networkCanvas"></canvas>
            <div class="grid-overlay"></div>
            <div class="cursor-spotlight" id="cursorSpotlight"></div>
            <div class="glow-shape glow-1"></div>
            <div class="glow-shape glow-2"></div>

            <div class="parallax-layer" id="parallaxLayer">
                <i class="fas fa-truck-fast parallax-icon pi-1" data-depth="18"></i>
                <i class="fas fa-route parallax-icon pi-2" data-depth="30"></i>
                <i class="fas fa-gauge-high parallax-icon pi-3" data-depth="12"></i>
                <i class="fas fa-satellite-dish parallax-icon pi-4" data-depth="24"></i>
                <i class="fas fa-shield-halved parallax-icon pi-5" data-depth="16"></i>
            </div>

            <div class="live-badge" id="liveBadge">
                <span class="live-dot"></span>
                <span class="live-badge-text" id="liveBadgeText">Sistema en línea</span>
            </div>

            <div class="visual-content" id="visualContent">
                <div class="caption">
                    <h3 id="captionTitle">Tu ruta, <span>siempre contigo.</span></h3>
                    <p id="captionText">Consulta tus servicios asignados y repórtalos en tiempo real desde cualquier lugar.</p>
                    <div class="caption-dots" id="captionDots">
                        <button type="button" class="caption-dot active" data-i="0" aria-label="Mensaje 1"></button>
                        <button type="button" class="caption-dot" data-i="1" aria-label="Mensaje 2"></button>
                        <button type="button" class="caption-dot" data-i="2" aria-label="Mensaje 3"></button>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-card rank-card">
                        <i class="fas fa-trophy rank-icon"></i>
                        <span class="stat-num"><span class="stat-count" data-target="1">0</span><span class="stat-suffix">°</span></span>
                        <span class="stat-label">En seguridad</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-num"><span class="stat-count" data-target="5">0</span></span>
                        <span class="stat-label">Países con cobertura</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-num"><span class="stat-count" data-target="24">0</span><span class="stat-suffix">/7</span></span>
                        <span class="stat-label">Monitoreo activo</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Si el navegador restaura esta página desde su caché de historial (por
        // ejemplo, al presionar "atrás" después de iniciar sesión), limpiamos el
        // formulario para que nunca se vea el teléfono o PIN de una sesión anterior.
        window.addEventListener('pageshow', function (event) {
            document.getElementById('loginForm').reset();
            document.getElementById('errorBox').style.display = 'none';
        });

        // Modo oscuro / claro
        (function() {
            const currentTheme = localStorage.getItem('forza-theme');
            const themeIcon = document.getElementById('themeIcon');

            if (currentTheme === 'light') {
                document.body.classList.add('light-mode');
                if (themeIcon) themeIcon.className = 'fas fa-moon';
            } else if (themeIcon) {
                themeIcon.className = 'fas fa-sun';
            }
        })();

        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const themeIcon = document.getElementById('themeIcon');

            if (document.body.classList.contains('light-mode')) {
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('forza-theme', 'light');
            } else {
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('forza-theme', 'dark');
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');

            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // "Recordarme": guarda/recupera el teléfono en este navegador (solo
        // autocompleta el campo; la sesión persistente real la maneja
        // auth_recordar.php del lado del servidor).
        (function() {
            const telField = document.getElementById('telefono');
            const rememberBox = document.getElementById('remember');
            const savedTel = localStorage.getItem('forza-piloto-remember-tel');

            if (savedTel) {
                telField.value = savedTel;
                rememberBox.checked = true;
            }

            rememberBox.addEventListener('change', function() {
                if (rememberBox.checked) {
                    localStorage.setItem('forza-piloto-remember-tel', telField.value);
                } else {
                    localStorage.removeItem('forza-piloto-remember-tel');
                }
            });
        })();

        // Auto-focus: si ya hay teléfono recordado, enfoca el PIN
        document.addEventListener('DOMContentLoaded', function() {
            const telField = document.getElementById('telefono');
            if (telField && telField.value) {
                document.getElementById('pin').focus();
            } else if (telField) {
                telField.focus();
            }
        });

        // Envío del login vía AJAX, igual que antes: sin recargar la página,
        // con spinner en el botón y mensaje de error inline si falla.
        async function ingresar(event) {
            event.preventDefault();
            const btn = document.getElementById('loginBtn');
            const errorBox = document.getElementById('errorBox');
            const errorText = document.getElementById('errorText');
            errorBox.style.display = 'none';

            const telefono = document.getElementById('telefono').value.trim();
            const pin = document.getElementById('pin').value.trim();
            const rememberBox = document.getElementById('remember');

            if (rememberBox.checked) {
                localStorage.setItem('forza-piloto-remember-tel', telefono);
            } else {
                localStorage.removeItem('forza-piloto-remember-tel');
            }

            btn.classList.add('loading');

            try {
                const fd = new FormData();
                fd.append('telefono', telefono);
                fd.append('pin', pin);

                const res = await fetch('api/piloto_auth.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'mis_servicios.php';
                } else {
                    errorText.textContent = data.message || 'Teléfono o PIN incorrecto';
                    errorBox.style.display = 'flex';
                    document.getElementById('pin').value = '';
                    btn.classList.remove('loading');
                }
            } catch (err) {
                errorText.textContent = 'Error de conexión. Intenta de nuevo.';
                errorBox.style.display = 'flex';
                document.getElementById('pin').value = '';
                btn.classList.remove('loading');
            }
        }

        // ===== Panel derecho interactivo =====

        // 1) Red de nodos en <canvas> que reacciona al cursor: los puntos
        //    cercanos al mouse se alejan un poco y las líneas hacia el
        //    cursor se resaltan en rojo, dando sensación de "red viva".
        (function() {
            const panel = document.getElementById('visualPanel');
            const canvas = document.getElementById('networkCanvas');
            if (!panel || !canvas || !canvas.getContext) return;

            const ctx = canvas.getContext('2d');
            let width = 0, height = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
            let particles = [];
            let bursts = [];
            let mouse = { x: null, y: null, active: false };
            let rafId = null;
            let running = false;

            function isDesktop() {
                return window.matchMedia('(min-width: 901px)').matches;
            }

            function resize() {
                const rect = panel.getBoundingClientRect();
                width = rect.width;
                height = rect.height;
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                const count = Math.max(28, Math.min(60, Math.round((width * height) / 22000)));
                particles = [];
                for (let i = 0; i < count; i++) {
                    particles.push({
                        x: Math.random() * width,
                        y: Math.random() * height,
                        vx: (Math.random() - 0.5) * 0.35,
                        vy: (Math.random() - 0.5) * 0.35,
                        r: Math.random() * 1.6 + 1.2
                    });
                }
            }

            function step() {
                ctx.clearRect(0, 0, width, height);

                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];

                    if (mouse.active) {
                        const dx = p.x - mouse.x, dy = p.y - mouse.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        const radius = 130;
                        if (dist < radius && dist > 0.01) {
                            const force = (radius - dist) / radius;
                            p.x += (dx / dist) * force * 1.6;
                            p.y += (dy / dist) * force * 1.6;
                        }
                    }

                    p.x += p.vx;
                    p.y += p.vy;

                    if (p.x < 0 || p.x > width) p.vx *= -1;
                    if (p.y < 0 || p.y > height) p.vy *= -1;
                    p.x = Math.max(0, Math.min(width, p.x));
                    p.y = Math.max(0, Math.min(height, p.y));
                }

                // Líneas entre nodos cercanos
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const a = particles[i], b = particles[j];
                        const dx = a.x - b.x, dy = a.y - b.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        const maxDist = 130;
                        if (dist < maxDist) {
                            ctx.strokeStyle = 'rgba(245, 245, 247,' + (0.14 * (1 - dist / maxDist)) + ')';
                            ctx.lineWidth = 1;
                            ctx.beginPath();
                            ctx.moveTo(a.x, a.y);
                            ctx.lineTo(b.x, b.y);
                            ctx.stroke();
                        }
                    }
                }

                // Líneas del cursor hacia nodos cercanos, resaltadas en rojo
                if (mouse.active) {
                    for (let i = 0; i < particles.length; i++) {
                        const p = particles[i];
                        const dx = p.x - mouse.x, dy = p.y - mouse.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        const maxDist = 160;
                        if (dist < maxDist) {
                            ctx.strokeStyle = 'rgba(239, 43, 43,' + (0.5 * (1 - dist / maxDist)) + ')';
                            ctx.lineWidth = 1.2;
                            ctx.beginPath();
                            ctx.moveTo(mouse.x, mouse.y);
                            ctx.lineTo(p.x, p.y);
                            ctx.stroke();
                        }
                    }
                }

                // Nodos
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(245, 245, 247, 0.75)';
                    ctx.fill();
                }

                // Ráfaga de chispas al hacer click: se expanden y se apagan
                if (bursts.length) {
                    for (let i = bursts.length - 1; i >= 0; i--) {
                        const b = bursts[i];
                        b.x += b.vx;
                        b.y += b.vy;
                        b.vx *= 0.96;
                        b.vy *= 0.96;
                        b.life -= 0.02;
                        if (b.life <= 0) {
                            bursts.splice(i, 1);
                            continue;
                        }
                        ctx.beginPath();
                        ctx.arc(b.x, b.y, b.r * b.life, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(239, 43, 43,' + b.life + ')';
                        ctx.fill();
                    }
                }

                rafId = requestAnimationFrame(step);
            }

            function start() {
                if (running) return;
                running = true;
                resize();
                rafId = requestAnimationFrame(step);
            }

            function stop() {
                running = false;
                if (rafId) cancelAnimationFrame(rafId);
                rafId = null;
            }

            function syncWithViewport() {
                if (isDesktop()) start(); else stop();
            }

            panel.addEventListener('mousemove', function(e) {
                const rect = panel.getBoundingClientRect();
                mouse.x = e.clientX - rect.left;
                mouse.y = e.clientY - rect.top;
                mouse.active = true;
            });

            panel.addEventListener('mouseleave', function() {
                mouse.active = false;
            });

            panel.addEventListener('click', function(e) {
                if (!running) return;
                if (e.target.closest('.stat-card') || e.target.closest('.live-badge')) return;

                const rect = panel.getBoundingClientRect();
                const cx = e.clientX - rect.left;
                const cy = e.clientY - rect.top;
                const sparkCount = 16;
                for (let i = 0; i < sparkCount; i++) {
                    const angle = (Math.PI * 2 * i) / sparkCount + Math.random() * 0.3;
                    const speed = 1.5 + Math.random() * 2.5;
                    bursts.push({
                        x: cx,
                        y: cy,
                        vx: Math.cos(angle) * speed,
                        vy: Math.sin(angle) * speed,
                        r: 2 + Math.random() * 2,
                        life: 1
                    });
                }
            });

            window.addEventListener('resize', function() {
                if (isDesktop()) resize();
                syncWithViewport();
            });

            syncWithViewport();
        })();

        // 2) Inclinación 3D suave del contenido (caption + stats) siguiendo
        //    al cursor, para dar profundidad y sensación "premium".
        (function() {
            const panel = document.getElementById('visualPanel');
            const content = document.getElementById('visualContent');
            if (!panel || !content) return;

            panel.addEventListener('mousemove', function(e) {
                const rect = panel.getBoundingClientRect();
                const px = (e.clientX - rect.left) / rect.width;
                const py = (e.clientY - rect.top) / rect.height;
                const rotateY = (px - 0.5) * 6;
                const rotateX = (0.5 - py) * 4;
                content.style.transform = 'perspective(900px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
            });

            panel.addEventListener('mouseleave', function() {
                content.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
            });
        })();

        // 3) Contadores animados para las tarjetas de estadísticas: suman
        //    hasta su valor objetivo apenas carga la página.
        (function() {
            const counters = document.querySelectorAll('.stat-count');
            if (!counters.length) return;

            counters.forEach(function(el) {
                const target = parseInt(el.getAttribute('data-target'), 10) || 0;
                const duration = 1400;
                const start = performance.now();

                function tick(now) {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.round(eased * target);
                    if (progress < 1) requestAnimationFrame(tick);
                }

                requestAnimationFrame(tick);
            });
        })();

        // 4) Halo ("spotlight") que sigue al cursor por todo el panel,
        //    dando sensación de luz reaccionando al movimiento.
        (function() {
            const panel = document.getElementById('visualPanel');
            const spotlight = document.getElementById('cursorSpotlight');
            if (!panel || !spotlight) return;

            panel.addEventListener('mousemove', function(e) {
                const rect = panel.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                panel.style.setProperty('--sx', x + '%');
                panel.style.setProperty('--sy', y + '%');
                panel.classList.add('cursor-active');
            });

            panel.addEventListener('mouseleave', function() {
                panel.classList.remove('cursor-active');
            });
        })();

        // 5) Ripple de click en las tarjetas de estadísticas: un círculo
        //    se expande desde el punto exacto donde se hace click.
        (function() {
            const cards = document.querySelectorAll('.visual-panel .stat-card');
            cards.forEach(function(card) {
                card.addEventListener('click', function(e) {
                    const rect = card.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height) * 1.6;
                    const ripple = document.createElement('span');
                    ripple.className = 'ripple';
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    card.appendChild(ripple);
                    ripple.addEventListener('animationend', function() {
                        ripple.remove();
                    });
                });
            });
        })();

        // 6) Badge "en vivo": al hacer click alterna entre el mensaje fijo
        //    y una marca de hora simulada, con una pequeña animación.
        (function() {
            const badge = document.getElementById('liveBadge');
            const text = document.getElementById('liveBadgeText');
            if (!badge || !text) return;

            let showingTime = false;

            badge.addEventListener('click', function() {
                showingTime = !showingTime;
                if (showingTime) {
                    const now = new Date();
                    const hh = String(now.getHours()).padStart(2, '0');
                    const mm = String(now.getMinutes()).padStart(2, '0');
                    text.textContent = 'Última sync ' + hh + ':' + mm;
                } else {
                    text.textContent = 'Sistema en línea';
                }
                text.style.animation = 'none';
                void text.offsetWidth;
                text.style.animation = '';
            });
        })();

        // 7) Íconos flotantes con parallax: se mueven según su profundidad
        //    (data-depth) al mover el cursor sobre el panel.
        (function() {
            const panel = document.getElementById('visualPanel');
            const layer = document.getElementById('parallaxLayer');
            if (!panel || !layer) return;

            const icons = layer.querySelectorAll('.parallax-icon');

            panel.addEventListener('mousemove', function(e) {
                const rect = panel.getBoundingClientRect();
                const px = (e.clientX - rect.left) / rect.width - 0.5;
                const py = (e.clientY - rect.top) / rect.height - 0.5;

                icons.forEach(function(icon) {
                    const depth = parseFloat(icon.getAttribute('data-depth')) || 15;
                    const x = px * depth;
                    const y = py * depth;
                    icon.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                });
            });

            panel.addEventListener('mouseleave', function() {
                icons.forEach(function(icon) {
                    icon.style.transform = 'translate(0, 0)';
                });
            });
        })();

        // 8) Mensajes rotativos del panel derecho: cambian solos cada
        //    4.5s y también se pueden elegir con los puntos indicadores.
        (function() {
            const titleEl = document.getElementById('captionTitle');
            const textEl = document.getElementById('captionText');
            const dots = document.querySelectorAll('.caption-dot');
            if (!titleEl || !textEl || !dots.length) return;

            const messages = [
                {
                    title: 'Tu ruta, <span>siempre contigo.</span>',
                    text: 'Consulta tus servicios asignados y repórtalos en tiempo real desde cualquier lugar.'
                },
                {
                    title: 'La fuerza detrás <span>de cada envío.</span>',
                    text: 'Gracias a tu trabajo, la carga llega segura a su destino.'
                },
                {
                    title: 'Reporta tu <span>kilometraje al instante.</span>',
                    text: 'Mantén tu vehículo al día y evita alertas de mantenimiento.'
                }
            ];

            let current = 0;
            let timer = null;

            function render(i) {
                current = i;
                titleEl.innerHTML = messages[i].title;
                textEl.textContent = messages[i].text;

                titleEl.style.animation = 'none';
                textEl.style.animation = 'none';
                void titleEl.offsetWidth;
                titleEl.style.animation = '';
                textEl.style.animation = '';

                dots.forEach(function(dot, idx) {
                    dot.classList.toggle('active', idx === i);
                });
            }

            function scheduleNext() {
                if (timer) clearTimeout(timer);
                timer = setTimeout(function() {
                    render((current + 1) % messages.length);
                    scheduleNext();
                }, 4500);
            }

            dots.forEach(function(dot) {
                dot.addEventListener('click', function() {
                    const i = parseInt(dot.getAttribute('data-i'), 10) || 0;
                    render(i);
                    scheduleNext();
                });
            });

            scheduleNext();
        })();

        // 9) Efecto magnético en las tarjetas: cada tarjeta se inclina y
        //    se acerca ligeramente hacia el punto exacto del cursor.
        (function() {
            const cards = document.querySelectorAll('.visual-panel .stat-card');

            cards.forEach(function(card) {
                card.addEventListener('mousemove', function(e) {
                    const rect = card.getBoundingClientRect();
                    const px = (e.clientX - rect.left) / rect.width - 0.5;
                    const py = (e.clientY - rect.top) / rect.height - 0.5;
                    card.style.transform =
                        'translateY(-4px) translate(' + (px * 6) + 'px,' + (py * 6) + 'px) ' +
                        'rotateX(' + (py * -6) + 'deg) rotateY(' + (px * 6) + 'deg)';
                });

                card.addEventListener('mouseleave', function() {
                    card.style.transform = 'translateY(0) translate(0, 0) rotateX(0) rotateY(0)';
                });
            });
        })();
    </script>
</body>

</html>