<?php
session_start();
require "database.php";

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    $numero_documento = $_POST['documento'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    // Consulta que incluye el campo 'estado' para validación
    $sql = "SELECT nombre, numero_documento, contrasena_hash, rol, estado
            FROM usuarios
            WHERE tipo_documento = ? AND numero_documento = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $tipo_documento, $numero_documento);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($nombre_completo, $db_documento, $db_contrasena_hash, $db_rol, $db_estado);
        $stmt->fetch();

        if (password_verify($contrasena, $db_contrasena_hash)) {
            
            // VALIDACIÓN CRÍTICA: Verificar estado del usuario según rol
            if ($db_estado == 'pendiente') {
                $message = "<div class='alert alert-warning' role='alert'>
                    <i class='fas fa-clock me-2'></i>
                    <strong>Cuenta Pendiente de Aprobación</strong>
                    Tu cuenta como <strong>" . ucfirst($db_rol) . "</strong> está en espera de aprobación por un administrador. 
                    <small>Contacta al administrador del sistema para más información.</small>
                </div>";
            } elseif ($db_estado == 'rechazado') {
                $message = "<div class='alert alert-danger' role='alert'>
                    <i class='fas fa-times-circle me-2'></i>
                    <strong>Cuenta Rechazada</strong>
                    Tu cuenta ha sido rechazada por un administrador. 
                    <small>Contacta al administrador del sistema para más información.</small>
                </div>";
            } elseif ($db_estado == 'activo') {
                $_SESSION['documento'] = $db_documento;
                $_SESSION['tipo_documento'] = $tipo_documento;
                $_SESSION['nombre'] = $nombre_completo;
                $_SESSION['rol'] = $db_rol;

                $stmt_aprendiz = $conn->prepare("SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?");
                $stmt_aprendiz->bind_param("ss", $db_documento, $tipo_documento);
                $stmt_aprendiz->execute();
                $result_aprendiz = $stmt_aprendiz->get_result();
                if ($row_aprendiz = $result_aprendiz->fetch_assoc()) {
                    $_SESSION['aprendiz_id'] = $row_aprendiz['id'];
                }
                $stmt_aprendiz->close();

                // Registrar actividad de inicio de sesión
                if (isset($_SESSION['aprendiz_id'])) {
                    $usuario_id = (int)$_SESSION['aprendiz_id'];
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $titulo = 'Inicio de sesión';
                    $descripcion = 'Acceso desde: ' . substr($ua, 0, 250);
                    $conn->query("CREATE TABLE IF NOT EXISTS actividades (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        tipo VARCHAR(50) NOT NULL,
                        titulo VARCHAR(255) NOT NULL,
                        descripcion TEXT NULL,
                        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    if ($stmt_log = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?,?,?,?)")) {
                        $tipo_evento = 'login';
                        $stmt_log->bind_param('isss', $usuario_id, $tipo_evento, $titulo, $descripcion);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                }

                if ($db_rol == 'instructor' || $db_rol == 'administrador') {
                    header("Location: instructores.php");
                } else {
                    header("Location: principal.php");
                }
                exit();
            } else {
                $message = "<div class='alert alert-warning' role='alert'>
                    <i class='fas fa-exclamation-triangle me-2'></i>
                    <strong>Estado de Cuenta No Válido</strong>
                    Tu cuenta como <strong>" . ucfirst($db_rol) . "</strong> tiene un estado no reconocido. 
                    <small>Contacta al administrador del sistema para más información.</small>
                </div>";
            }
        } else {
            $message = "<div class='alert alert-danger' role='alert'>
                <i class='fas fa-exclamation-triangle me-2'></i>Documento o contraseña incorrectos.
            </div>";
        }
    } else {
        // ✅ NUEVO MENSAJE CLARO PARA USUARIOS NO EXISTENTES
        $message = "<div class='alert alert-danger' role='alert'>
            <i class='fas fa-user-slash me-2'></i>
            <strong>Usuario no encontrado</strong>
            No existe ningún usuario registrado con el tipo y número de documento ingresado.
            <small>Verifica los datos o regístrate si aún no tienes una cuenta.</small>
        </div>";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Aplicativo ASEM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: hsl(0, 0%, 100%);
            color: #333;
            transition: background-color 0.5s ease, color 0.5s ease;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            position: relative;
            z-index: 999;
            transition: background 0.5s ease;
        }
        .navbar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: navShine 4s ease-in-out infinite;
        }
        @keyframes navShine {
            0%,100% { transform: translateX(-100%); } 50% { transform: translateX(100%); }
        }
        .navbar-brand, .nav-link {
            color: #fff;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        .navbar-brand:hover, .nav-link:hover { color: #f0f0f0; }
        .navbar .btn {
            color: #fff;
            background: linear-gradient(135deg, #388E3C, #2E7D32);
            padding: 12px 20px;
            margin-left: 15px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .navbar .btn::before {
            content:''; position:absolute; top:0; left:-100%; width:100%; height:100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
            transition:left 0.3s ease; z-index:-1;
        }
        .navbar .btn:hover { transform: translateY(-3px); box-shadow:0 8px 25px rgba(76,175,80,0.4); }
        .navbar .btn:hover::before { left:0; }

        .contenedor-principal { position: relative; z-index: 10; padding: 50px 0; }
        .card-login {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.5s ease;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #28a745, #28a745);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            text-align: center;
            padding: 30px;
            transition: background 0.5s ease;
        }
        .form-control, .form-select { transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40,167,69,0.25);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #28a745, #28a745);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #218838, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.4);
        }
        .registro-link-text { color: #6c757d; transition: color 0.5s ease; }
        .fondo-animado {
            position: fixed;
            top:0; left:0; width:100%; height:100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -1;
            transition: background-image 1s ease-in-out;
        }
        .alert {
            padding: 0.5rem 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: .5rem;
            transition: all 0.5s ease;
            animation: slideInDown 0.5s ease-out;
        }
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffc107; border-width: 2px; }
        .alert-warning strong, .alert-danger strong, .alert-success strong {
            display: block; font-size: 0.9rem; margin-bottom: 0.2rem;
        }
        .alert-warning small, .alert-danger small, .alert-success small {
            display: block; margin-top: 0.2rem; opacity: 0.9; font-size: 0.7rem;
        }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .input-group-text { transition: all 0.5s ease; }
        .input-group-text, .form-control { background-color: #fff; color: #333; }

        body.bg-dark { background-color: #121212 !important; color: #e0e0e0; }
        .bg-dark .navbar { background: linear-gradient(135deg, #212121, #424242) !important; }
        .bg-dark .card-login { background: rgba(44,44,44,0.95) !important; color: #e0e0e0 !important; }
        .bg-dark .card-header-custom { background: linear-gradient(135deg, #444, #666) !important; color: #e0e0e0 !important; }
        .bg-dark .form-label { color: #e0e0e0 !important; }
        .bg-dark .form-control, .bg-dark .form-select, .bg-dark .input-group-text {
            background-color: #2c2c2c !important; color: #e0e0e0 !important; border-color: #444 !important;
        }
        .bg-dark .form-control::placeholder { color: #a0a0a0; }
        .bg-dark .btn-outline-secondary { background-color: #2c2c2c !important; border-color: #444 !important; color: #e0e0e0 !important; }
        .bg-dark .btn-outline-secondary:hover { background-color: #444 !important; color: #fff !important; }
        .bg-dark .btn-primary-custom { background: linear-gradient(135deg, #66bb6a, #4CAF50) !important; }
        .bg-dark .alert-danger { background-color: #721c24 !important; color: #f8d7da !important; border-color: #f5c6cb !important; }
        .bg-dark .alert-success { background-color: #1a5632 !important; color: #c8e5d3 !important; border-color: #218838 !important; }
        .bg-dark .alert-warning { background-color: #664d03 !important; color: #ffecb5 !important; border-color: #856404 !important; }
        .bg-dark .registro-link-text { color: #b0b0b0 !important; }
        .bg-dark .registro-link-text a { color: #66bb6a !important; }
    </style>
</head>
<body <?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
    echo $isDark ? 'class="bg-dark"' : '';
?>>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fas fa-graduation-cap me-2"></i>ASEM</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Inicio</a>
                <a class="nav-link" href="registro.php"><i class="fas fa-user-plus me-1"></i>Registrarse</a>
            </div>
        </div>
    </div>
</nav>

<div class="contenedor-principal">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <div class="card card-login">
                    <div class="card-header card-header-custom text-center">
                        <i class="fas fa-user-circle fa-3x mb-3"></i>
                        <h2 class="mb-2">Bienvenido al aplicativo ASEM</h2>
                        <p class="mb-0">Por favor, inicia sesión para continuar.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $message; ?>
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="tipo_documento" class="form-label"><i class="fas fa-id-card me-2"></i>Tipo de Documento:</label>
                                <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="tarjeta_identidad">Tarjeta de Identidad</option>
                                    <option value="cedula_ciudadania">Cédula de Ciudadanía</option>
                                    <option value="cedula_extranjeria">Cédula de Extranjería</option>
                                    <option value="pep">PEP</option>
                                    <option value="permiso_proteccion_temporal">Permiso de Protección Temporal</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="documento" class="form-label">
                                    <i class="fas fa-hashtag me-2"></i>Número de Documento:
                                </label>
                                <input type="text" id="documento" name="documento" class="form-control" placeholder="Ingresa tu número de documento" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Contraseña:
                                </label>
                                <div class="input-group">
                                    <input type="password" id="contrasena" name="contrasena" class="form-control" placeholder="Ingresa tu contraseña" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('contrasena')">
                                        <i class="fas fa-eye" id="eye-contrasena"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-custom btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted registro-link-text">
                                    ¿No tienes una cuenta? <a href="registro.php" class="text-decoration-none">Regístrate aquí</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const eyeIcon = document.getElementById('eye-' + fieldId);
        if (field.type === 'password') {
            field.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }

    const imagenesFondo = [
        'imagenes/123456789.jpg',
        'imagenes/sena-2.webp',
        'imagenes/foto1.jpeg',
        'imagenes/sena-cursos-convocatoria.webp'
    ];
    let indice = 0;
    const fondo = document.createElement('div');
    fondo.className = 'fondo-animado';
    document.body.prepend(fondo);

    function cambiarFondo() {
        fondo.style.backgroundImage = `url('${imagenesFondo[indice]}')`;
        indice = (indice + 1) % imagenesFondo.length;
    }

    cambiarFondo();
    setInterval(cambiarFondo, 3000);

    document.addEventListener('DOMContentLoaded', function() {
        const card = document.querySelector('.card-login');
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';

        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200);

        // Tema aplicado por sesión
    });
</script>
</body>
</html>
