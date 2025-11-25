<?php
session_start();

// Conexión a la base de datos
$conn = @new mysqli('localhost', 'root', '', 'bitacoras_db');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $conn && !$conn->connect_error) {
    // Recopilar datos del formulario
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $apellido = htmlspecialchars(trim($_POST['apellido']));
    $tipo_documento = htmlspecialchars(trim($_POST['tipo_documento']));
    $numero_documento = htmlspecialchars(trim($_POST['numero_documento']));
    $rol = htmlspecialchars(trim($_POST['rol']));
    $genero = htmlspecialchars(trim($_POST['genero']));
    $fecha_nacimiento = htmlspecialchars(trim($_POST['fecha_nacimiento']));
    $correo = htmlspecialchars(trim($_POST['correo']));
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $codigo_ficha = isset($_POST['codigo_ficha']) ? trim($_POST['codigo_ficha']) : null;

    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($tipo_documento) || empty($numero_documento) || empty($rol) || empty($genero) || empty($fecha_nacimiento) || empty($correo) || empty($contrasena) || empty($confirmar_contrasena)) {
        $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>Todos los campos son obligatorios.</div>";
    } elseif ($rol == 'aprendiz' && empty($codigo_ficha)) {
        $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>Debes ingresar el código de ficha para los aprendices.</div>";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>El formato del correo electrónico no es válido.</div>";
    } elseif ($contrasena !== $confirmar_contrasena) {
        $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>Las contraseñas no coinciden.</div>";
    } elseif (strlen($contrasena) < 6) {
        $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>La contraseña debe tener al menos 6 caracteres.</div>";
    } else {
        // Verificar si el correo o el documento ya existen
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? OR numero_documento = ?");
        $stmt_check->bind_param("ss", $correo, $numero_documento);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>El correo electrónico o el número de documento ya están registrados.</div>";
        } else {
            // Si es aprendiz, buscar la ficha en la BD
            $ficha_id = null;
            if ($rol == 'aprendiz') {
                $stmt_ficha = $conn->prepare("SELECT id FROM fichas WHERE codigo_ficha = ?");
                $stmt_ficha->bind_param("s", $codigo_ficha);
                $stmt_ficha->execute();
                $resultado_ficha = $stmt_ficha->get_result();

                if ($resultado_ficha->num_rows > 0) {
                    $fila_ficha = $resultado_ficha->fetch_assoc();
                    $ficha_id = $fila_ficha['id'];
                } else {
                    $mensaje = "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle me-2'></i>El código de ficha ingresado no existe.</div>";
                }
                $stmt_ficha->close();
            }

            // Si no hay errores y la ficha es válida (para aprendices)
            if ($rol != 'aprendiz' || $ficha_id !== null) {
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                
                // NUEVA LÓGICA: Determinar el estado según el rol
                $estado = 'activo'; // Por defecto activo para aprendices
                if ($rol == 'instructor' || $rol == 'administrador') {
                    $estado = 'pendiente'; // Los instructores y admins quedan pendientes
                }

                $stmt = $conn->prepare("INSERT INTO usuarios 
    (nombre, apellido, tipo_documento, numero_documento, rol, genero, fecha_nacimiento, correo, contrasena_hash, ficha_id, estado) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if ($stmt) {
    // ficha_id puede ser NULL, bind_param necesita variable
    $ficha_id_param = $ficha_id ?? null;

    // Tipos: s = string, i = integer
    // nombre, apellido, tipo_documento, numero_documento, rol, genero, fecha_nacimiento, correo, contrasena_hash, ficha_id, estado
    $stmt->bind_param(
        "sssssssssis",
        $nombre,
        $apellido,
        $tipo_documento,
        $numero_documento,
        $rol,
        $genero,
        $fecha_nacimiento,
        $correo,
        $contrasena_hash,
        $ficha_id_param,
        $estado
    );

    if ($stmt->execute()) {
        if ($estado == 'pendiente') {
            $mensaje = "<div class='alert alert-warning' role='alert'>
                <i class='fas fa-clock me-2'></i>¡Registro exitoso! Tu cuenta como $rol está pendiente de aprobación.
            </div>";
        } else {
            $mensaje = "<div class='alert alert-success' role='alert'>
                <i class='fas fa-check-circle me-2'></i>¡Registro exitoso! Ahora puedes <a href='login.php'>iniciar sesión</a>.
            </div>";
        }
        $_POST = array(); // limpiar formulario
    } else {
        $mensaje = "<div class='alert alert-danger' role='alert'>Error al registrar el usuario: " . $stmt->error . "</div>";
    }
    $stmt->close();
} else {
    $mensaje = "<div class='alert alert-danger' role='alert'>Error al preparar la consulta: " . $conn->error . "</div>";
}

            }
        }
        $stmt_check->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro - Aplicativo ASEM</title>
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

/* ================= Navbar ================= */
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
.navbar-brand:hover, .nav-link:hover {
    color: #f0f0f0;
}
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

/* ================= Contenedor principal ================= */
.contenedor-principal {
    position: relative;
    z-index: 10;
    padding: 50px 0;
}
.card-registro {
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
.form-control, .form-select {
    transition: all 0.3s ease;
}
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
.radio-group input[type="radio"] {
    display: none;
}
.radio-group label {
    background-color: #f0f0f0;
    color: #555;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}
.radio-group input[type="radio"]:checked + label {
    background-color: #28a745;
    color: white;
    box-shadow: 0 4px 8px rgba(40,167,69,0.2);
    transform: translateY(-2px);
}

/* ================= Fondo animado ================= */
.fondo-animado {
    position: fixed;
    top:0; left:0; width:100%; height:100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    z-index: -1;
    transition: background-image 1s ease-in-out;
}
.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}
.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffecb5;
}
.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
.alert {
    padding: 1rem 1rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: .25rem;
    transition: all 0.5s ease;
}
.input-group-text {
    transition: all 0.5s ease;
}
.input-group-text, .form-control {
    background-color: #fff;
    color: #333;
}

/* ================= ESTILOS PARA MODO OSCURO MEJORADO ================= */
body.bg-dark {
    background-color: #121212 !important;
    color: #e0e0e0;
}
.bg-dark .navbar {
    background: linear-gradient(135deg, #212121, #424242) !important;
    box-shadow: 0 3px 8px rgba(255,255,255,0.1);
}
.bg-dark .card-registro {
    background: rgba(44, 44, 44, 0.95) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    color: #e0e0e0 !important;
}
.bg-dark .card-header-custom {
    background: linear-gradient(135deg, #444, #666) !important;
    color: #e0e0e0 !important;
}
.bg-dark .form-label {
    color: #e0e0e0 !important;
}
.bg-dark .form-control,
.bg-dark .form-select,
.bg-dark .input-group-text {
    background-color: #2c2c2c !important;
    color: #e0e0e0 !important;
    border-color: #444 !important;
}
.bg-dark .form-control::placeholder {
    color: #a0a0a0;
}
.bg-dark .btn-outline-secondary {
    background-color: #2c2c2c !important;
    border-color: #444 !important;
    color: #e0e0e0 !important;
}
.bg-dark .btn-outline-secondary:hover {
    background-color: #444 !important;
    color: #fff !important;
}
.bg-dark .btn-primary-custom {
    background: linear-gradient(135deg, #66bb6a, #4CAF50) !important;
}

/* Mejoras de contraste en alertas */
.bg-dark .alert-danger {
    background-color: #721c24 !important;
    color: #f8d7da !important;
    border-color: #f5c6cb !important;
}
.bg-dark .alert-success {
    background-color: #1a5632 !important;
    color: #c8e5d3 !important;
    border-color: #218838 !important;
}
.bg-dark .alert-success .alert-link {
    color: #fff !important;
    text-decoration: underline;
}
.bg-dark .alert-warning {
    background-color: #664d03 !important;
    color: #ffecb5 !important;
    border-color: #856404 !important;
}
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
                <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión</a>
            </div>
        </div>
    </div>
</nav>

<div class="contenedor-principal">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9 col-sm-11">
                <div class="card card-registro">
                    <div class="card-header card-header-custom text-center">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h2 class="mb-2">Registro de Usuario</h2>
                        <p class="mb-0">Completa los campos para crear tu cuenta.</p>
                        <small class="text-light mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            Los instructores y administradores requieren aprobación
                        </small>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $mensaje; ?>
                        <form action="registro.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label"><i class="fas fa-user me-2"></i>Nombre:</label>
                                    <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="apellido" class="form-label"><i class="fas fa-user me-2"></i>Apellido:</label>
                                    <input type="text" id="apellido" name="apellido" class="form-control" value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="tipo_documento" class="form-label"><i class="fas fa-id-card me-2"></i>Tipo de Documento:</label>
                                    <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="tarjeta_identidad" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] == 'tarjeta_identidad') ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                                        <option value="cedula_ciudadania" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] == 'cedula_ciudadania') ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                                        <option value="cedula_extranjeria" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] == 'cedula_extranjeria') ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                                        <option value="pep" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] == 'pep') ? 'selected' : ''; ?>>PEP</option>
                                        <option value="permiso_proteccion_temporal" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] == 'permiso_proteccion_temporal') ? 'selected' : ''; ?>>Permiso de Protección Temporal</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="numero_documento" class="form-label"><i class="fas fa-hashtag me-2"></i>Número de Documento:</label>
                                    <input type="text" id="numero_documento" name="numero_documento" class="form-control" value="<?php echo htmlspecialchars($_POST['numero_documento'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rol" class="form-label"><i class="fas fa-user-tag me-2"></i>Rol:</label>
                                    <select id="rol" name="rol" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="aprendiz" <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'aprendiz') ? 'selected' : ''; ?>>Aprendiz</option>
                                        <option value="instructor" <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                                        <option value="administrador" <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="campo-ficha" style="display:none;">
                                    <label for="codigo_ficha" class="form-label"><i class="fas fa-chalkboard-teacher me-2"></i>Código de Ficha:</label>
                                    <input type="text" id="codigo_ficha" name="codigo_ficha" class="form-control" value="<?php echo htmlspecialchars($_POST['codigo_ficha'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="genero" class="form-label"><i class="fas fa-venus-mars me-2"></i>Género:</label>
                                    <select id="genero" name="genero" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="masculino" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="femenino" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="otro" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'otro') ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="fecha_nacimiento" class="form-label"><i class="fas fa-calendar-alt me-2"></i>Fecha de Nacimiento:</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="correo" class="form-label"><i class="fas fa-envelope me-2"></i>Correo Electrónico:</label>
                                <input type="email" id="correo" name="correo" class="form-control" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="contrasena" class="form-label"><i class="fas fa-lock me-2"></i>Contraseña:</label>
                                    <div class="input-group">
                                        <input type="password" id="contrasena" name="contrasena" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('contrasena')">
                                            <i class="fas fa-eye" id="eye-contrasena"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmar_contrasena" class="form-label"><i class="fas fa-lock me-2"></i>Confirmar Contraseña:</label>
                                    <div class="input-group">
                                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmar_contrasena')">
                                            <i class="fas fa-eye" id="eye-confirmar_contrasena"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-custom btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Registrarse
                                </button>
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

    const rolSelect = document.getElementById('rol');
    const campoFicha = document.getElementById('campo-ficha');
    
    function mostrarOcultarFicha() {
        campoFicha.style.display = (rolSelect.value === 'aprendiz') ? 'block' : 'none';
    }
    rolSelect.addEventListener('change', mostrarOcultarFicha);
    document.addEventListener('DOMContentLoaded', mostrarOcultarFicha);

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
        const card = document.querySelector('.card-registro');
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';

        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200);
    });
</script>
</body>
</html>