<?php
session_start();
// Redirección si ya está logueado
// if (isset($_SESSION['nombre_usuario'])) {
//     header("Location: principal.php");
//     exit;
// }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ASEM - Plataforma Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* ========== ESTILOS GENERALES ========== */
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            color: #333;
            overflow-x: hidden;
            transition: all 0.5s ease;
        }

        html {
            scroll-behavior: smooth;
        }

        /* ========== PARTÍCULAS DE FONDO ========== */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .particle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-delay: -2s;
        }

        .particle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 40%;
            left: 70%;
            animation-delay: -4s;
        }

        .particle:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 80%;
            left: 20%;
            animation-delay: -1s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.7;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 1;
            }
        }

        /* ========== NAVBAR ESTILO CLARO ========== */
        .navbar {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%) !important;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
            transition: all 0.5s ease;
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            animation: navShine 4s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes navShine {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }

        .navbar-brand {
            position: relative;
            z-index: 2;
            animation: titlePulse 2s ease-in-out infinite alternate;
            transition: all 0.3s ease;
        }

        @keyframes titlePulse {
            from { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1); }
            to { text-shadow: 2px 2px 8px rgba(76, 175, 80, 0.3); }
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: rotate(360deg) scale(1.1);
        }

        /* ========== BOTONES ESTILO CLARO ========== */
        .btn-outline-success, .btn-success, .btn-outline-light, .btn-light {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border-radius: 25px !important;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 1;
        }

        .btn-outline-success::before, .btn-success::before, .btn-outline-light::before, .btn-light::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.3s ease;
            z-index: -1;
        }

        .btn-outline-success:hover, .btn-success:hover, .btn-outline-light:hover, .btn-light:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .btn-outline-success:hover::before, .btn-success:hover::before, .btn-outline-light:hover::before, .btn-light:hover::before {
            left: 0;
        }

        /* ========== HEADER ESTILO CLARO ========== */
        header {
            background: linear-gradient(135deg, #f8fff8 0%, #ffffff 100%) !important;
            position: relative;
            overflow: hidden;
            transition: all 0.5s ease;
        }

        header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(76, 175, 80, 0.1), transparent);
            animation: shimmer 3s infinite;
            pointer-events: none;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        header h1 {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 1s ease-out;
            transition: all 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== SECCIONES ESTILO CLARO ========== */
        section {
            position: relative;
            transition: all 0.4s ease;
            border-radius: 15px;
            overflow: hidden;
            animation: slideInLeft 0.6s ease-out;
            animation-fill-mode: both;
        }

        section:nth-child(2) { animation-delay: 0.2s; }
        section:nth-child(3) { animation-delay: 0.4s; }
        section:nth-child(4) { animation-delay: 0.6s; }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, #4CAF50, #45a049);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            transform-origin: bottom;
        }

        section:hover::before {
            transform: scaleY(1);
            transform-origin: top;
        }

        section:hover {
            transform: translateX(10px) scale(1.02);
            box-shadow: 0 15px 30px rgba(76, 175, 80, 0.2);
        }

        section h2 {
            position: relative;
            display: inline-block;
            transition: all 0.3s ease;
        }

        section h2::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            transition: width 0.3s ease;
        }

        section:hover h2::after {
            width: 100%;
        }

        /* ========== CARDS ESTILO CLARO ========== */
        .card {
            transition: all 0.4s ease;
            border: none !important;
            overflow: hidden;
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(76, 175, 80, 0.1), transparent);
            transition: left 0.5s ease;
            pointer-events: none;
        }

        .card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 40px rgba(76, 175, 80, 0.2) !important;
        }

        .card:hover::before {
            left: 0;
        }

        /* ========== IMAGEN Y VIDEO ESTILOS ========== */
        .img-fluid {
            transition: all 0.5s ease;
            filter: brightness(1.1) contrast(1.1);
        }

        .img-fluid:hover {
            transform: scale(1.05);
            filter: brightness(1.2) contrast(1.2);
        }

        .video-container {
            transition: all 0.5s ease;
            padding: 20px;
            border-radius: 10px;
        }

        .video-hover {
            width: 400px;
            height: 250px;
            display: block;
            margin: 20px auto;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .video-hover:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
        }

        /* ========== BOTÓN FLOTANTE ========== */
        .floating-accent {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.4);
            animation: floatUpDown 3s ease-in-out infinite;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
            text-decoration: none;
        }

        .floating-accent:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(76, 175, 80, 0.6);
            color: white;
        }

        @keyframes floatUpDown {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* ========== FOOTER ESTILO CLARO ========== */
        footer {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%) !important;
            position: relative;
            overflow: hidden;
            transition: all 0.5s ease;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        /* ========== MODO OSCURO MEJORADO ========== */
        body.bg-dark {
            background: linear-gradient(135deg, #0a0a0a 0%, #121212 100%) !important;
            color: #e0e0e0;
        }

        /* Partículas en modo oscuro */
        body.bg-dark .particle {
            background: rgba(102, 187, 106, 0.15);
            box-shadow: 0 0 20px rgba(102, 187, 106, 0.1);
        }

        /* Navbar modo oscuro */
        body.bg-dark .navbar {
            background: linear-gradient(135deg, #1a1a1a 0%, #212121 100%) !important;
            box-shadow: 0 4px 20px rgba(102, 187, 106, 0.2);
            border-bottom: 2px solid rgba(102, 187, 106, 0.3);
        }

        body.bg-dark .navbar::before {
            background: linear-gradient(45deg, transparent 30%, rgba(102, 187, 106, 0.1) 50%, transparent 70%);
        }

        body.bg-dark .navbar-brand {
            color: #fff !important;
            text-shadow: 0 0 10px rgba(102, 187, 106, 0.5);
        }

        body.bg-dark .navbar-brand h1 {
            background: linear-gradient(135deg, #66bb6a, #4caf50);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 8px rgba(102, 187, 106, 0.3));
        }

        body.bg-dark .navbar-nav .nav-link {
            color: #e0e0e0 !important;
            transition: all 0.3s ease;
            position: relative;
        }

        body.bg-dark .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #66bb6a, #4caf50);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        body.bg-dark .navbar-nav .nav-link:hover {
            color: #66bb6a !important;
            text-shadow: 0 0 8px rgba(102, 187, 106, 0.5);
        }

        body.bg-dark .navbar-nav .nav-link:hover::before {
            width: 100%;
        }

        /* Header modo oscuro */
        body.bg-dark header {
            background: linear-gradient(135deg, #0f0f0f 0%, #1c1c1c 100%) !important;
            border-bottom: 1px solid rgba(102, 187, 106, 0.2);
        }

        body.bg-dark header::before {
            background: linear-gradient(90deg, transparent, rgba(102, 187, 106, 0.15), transparent);
        }

        body.bg-dark header h1 {
            background: linear-gradient(135deg, #66bb6a, #4caf50);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 15px rgba(102, 187, 106, 0.4));
        }

        body.bg-dark .lead {
            color: #bdbdbd !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        }

        /* Secciones modo oscuro */
        body.bg-dark section {
            background: linear-gradient(135deg, #161616 0%, #1c1c1c 100%) !important;
            color: #e0e0e0 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
            border: 1px solid rgba(102, 187, 106, 0.1);
            backdrop-filter: blur(10px);
        }

        body.bg-dark section::before {
            background: linear-gradient(to bottom, #66bb6a, #4caf50);
            box-shadow: 0 0 10px rgba(102, 187, 106, 0.5);
        }

        body.bg-dark section:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 20px rgba(102, 187, 106, 0.1) !important;
            border-color: rgba(102, 187, 106, 0.3);
        }

        body.bg-dark section.bg-light {
            background: linear-gradient(135deg, #1a1a1a 0%, #202020 100%) !important;
        }

        body.bg-dark .text-success {
            color: #66bb6a !important;
            text-shadow: 0 0 10px rgba(102, 187, 106, 0.3);
        }

        /* Botones modo oscuro */
        body.bg-dark .btn-outline-light {
            color: #bdbdbd !important;
            border-color: #555 !important;
            background: transparent;
            transition: all 0.3s ease;
        }

        body.bg-dark .btn-outline-light::before {
            background: linear-gradient(135deg, rgba(102, 187, 106, 0.2), transparent);
        }

        body.bg-dark .btn-outline-light:hover {
            background: linear-gradient(135deg, #66bb6a, #4caf50) !important;
            color: #ffffff !important;
            border-color: #66bb6a !important;
            box-shadow: 0 8px 25px rgba(102, 187, 106, 0.4);
            transform: translateY(-3px);
        }

        body.bg-dark .btn-light {
            background: linear-gradient(135deg, #333, #404040) !important;
            color: #fff !important;
            border: 1px solid #555;
        }

        body.bg-dark .btn-light:hover {
            background: linear-gradient(135deg, #66bb6a, #4caf50) !important;
            border-color: #66bb6a !important;
            box-shadow: 0 8px 25px rgba(102, 187, 106, 0.4);
        }

        body.bg-dark .btn-success:hover {
            box-shadow: 0 8px 25px rgba(102, 187, 106, 0.4);
        }

        /* Cards modo oscuro */
        body.bg-dark .card {
            background: linear-gradient(135deg, #1e1e1e 0%, #2c2c2c 100%) !important;
            color: #e0e0e0 !important;
            border: 1px solid rgba(102, 187, 106, 0.2) !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        body.bg-dark .card::before {
            background: linear-gradient(90deg, transparent, rgba(102, 187, 106, 0.1), transparent);
        }

        body.bg-dark .card:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4), 0 0 20px rgba(102, 187, 106, 0.2) !important;
            border-color: rgba(102, 187, 106, 0.4) !important;
        }

        body.bg-dark .card-title {
            color: #66bb6a !important;
            text-shadow: 0 0 8px rgba(102, 187, 106, 0.3);
        }

        body.bg-dark .text-muted {
            color: #a0a0a0 !important;
        }

        /* Video container modo oscuro */
        body.bg-dark .video-container {
            background: linear-gradient(135deg, #0a0a0a 0%, #121212 100%) !important;
            border: 1px solid rgba(102, 187, 106, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        body.bg-dark .video-hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(102, 187, 106, 0.3);
        }

        body.bg-dark .video-hover:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.7), 0 0 20px rgba(102, 187, 106, 0.4);
            border-color: rgba(102, 187, 106, 0.6);
        }

        /* Footer modo oscuro */
        body.bg-dark footer {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 100%) !important;
            color: #e0e0e0 !important;
            border-top: 2px solid rgba(102, 187, 106, 0.3);
        }

        body.bg-dark footer::before {
            background: linear-gradient(90deg, #66bb6a, #4caf50);
            box-shadow: 0 0 10px rgba(102, 187, 106, 0.5);
        }

        /* Botón flotante modo oscuro */
        body.bg-dark .floating-accent {
            background: linear-gradient(135deg, #66bb6a, #4caf50);
            box-shadow: 0 4px 20px rgba(102, 187, 106, 0.4);
        }

        body.bg-dark .floating-accent:hover {
            box-shadow: 0 6px 30px rgba(102, 187, 106, 0.6);
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 768px) {
            .floating-accent {
                width: 50px;
                height: 50px;
                font-size: 20px;
                bottom: 20px;
                right: 20px;
            }

            .video-hover {
                width: 90%;
                height: auto;
                max-width: 400px;
            }

            section:hover {
                transform: translateX(5px) scale(1.01);
            }
        }
    </style>
</head>
<body <?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
    echo $isDark ? 'class="bg-dark"' : '';
?>>

<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<nav class="navbar navbar-expand-lg navbar-light shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="#">
            <img src="imagenes/logo-sena-blanco.png" alt="Logo" width="40" class="me-2">
            ASEM
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active text-white" href="#">Inicio</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#quienes-somos">Quiénes somos</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#funciona">Cómo funciona</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#objetivo">Objetivo</a></li>

                <!--SENA -->
                <li  class="nav-item">
                    <a class="nav-link text-white" 
                    href="https://portal.senasofiaplus.edu.co/"
                    target="_blank">SENA</a></li>
            </ul>
            <div class="d-flex">
                <a href="login.php" class="btn btn-outline-light me-2">Iniciar Sesión</a>
                <a href="registro.php" class="btn btn-light text-success fw-bold">Registrarse</a>
            </div>
        </div>
    </div>
</nav>

<header class="py-5 text-center">
    <div class="container">
        <h1 class="display-5 fw-bold">Bienvenidos al aplicativo ASEM</h1>
        <p class="lead text-muted">La plataforma que transforma la forma en que los aprendices del SENA gestionan sus bitácoras académicas.</p>
        <a href="registro.php" class="btn btn-success btn-lg px-4 mt-3">Comenzar Ahora</a>
    </div>
</header>

<section id="quienes-somos" class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-3 text-success">¿Quiénes somos?</h2>
                <p>Somos un grupo de estudiantes comprometidos con el desarrollo de un aplicativo innovador que busca transformar la manera en que los aprendices gestionan sus bitácoras académicas.</p>
            </div>
            <div class="col-lg-6 text-center">
                <img src="imagenes/images_2.webp" class="img-fluid rounded shadow-sm" alt="Imagen ASEM">
            </div>
        </div>
    </div>
</section>

<section id="funciona" class="py-5 bg-light">
    <div class="container text-center">
        <h2 class="fw-bold mb-4 text-success">¿Cómo funciona el proyecto?</h2>
        <p class="mb-4">ASEM es una plataforma web donde los aprendices pueden registrarse, iniciar sesión y subir sus bitácoras académicas de manera sencilla. Además, recibirán información sobre eventos y actividades del SENA.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-success">Registro fácil</h5>
                        <p class="card-text">Crea tu cuenta en minutos y accede al sistema desde cualquier dispositivo.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-success">Subida de bitácoras</h5>
                        <p class="card-text">Adjunta tus avances y evidencias en un entorno intuitivo y organizado.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-success">Validación automática</h5>
                        <p class="card-text">El sistema valida tus entregas para garantizar que estén completas y correctas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="objetivo" class="py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-3 text-success">¿Para qué es el proyecto?</h2>
        <p>El objetivo del aplicativo ASEM es optimizar el tiempo de los aprendices e instructores, garantizando una gestión clara y eficiente de las bitácoras académicas dentro del proceso formativo.</p>
    </div>
</section>

<section id="Justificación Del Proyecto" class="py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-3 text-success">Justificación Del Proyecto</h2>
        <p>La implementación de este aplicativo web, denominado ASEM, se justifica como una solución innovadora y necesaria para optimizar el proceso de creación de bitácoras
            para los aprendices, siendo un método que se encargará de validar automáticamente que las bitácoras están completas y correctas para poder enviarse 
            de una manera virtual y de esta forma optimizando el tiempo de tanto los aprendices como de los instructores.</p>
    </div>
</section>
    
<div class="video-container text-center">
    <video class="video-hover" controls>
        <source src="videos/ProyectoSena.mp4" type="video/mp4">
    </video>
</div>

<footer class="text-light py-4">
    <div class="container text-center">
        <p class="mb-1">© 2025 ASEM. Todos los derechos reservados.</p>
        <small>Desarrollado por estudiantes del SENA</small>
    </div>
</footer>

<a href="#" class="floating-accent">
    ↑
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Script para el botón flotante - scroll hacia arriba
document.querySelector('.floating-accent').addEventListener('click', function(e) {
    e.preventDefault();
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
</script>

</body>
</html>















































