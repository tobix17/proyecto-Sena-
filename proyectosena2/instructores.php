<?php
session_start();
require 'database.php';

// Validar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento'])) {
    header("Location: login.php");
    exit();
}

// --- LÓGICA PARA ACTUALIZAR/QUITAR FICHA A INSTRUCTORES APROBADOS (SOLO ADMINISTRADORES) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['actualizar_ficha_instructor'])) {
    $rolSesion = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';
    if ($rolSesion === 'administrador') {
        $usuario_id = intval($_POST['usuario_id'] ?? 0);
        $quitar_ficha = isset($_POST['quitar_ficha']) && $_POST['quitar_ficha'] == '1';
        $nueva_ficha = isset($_POST['nueva_ficha']) && $_POST['nueva_ficha'] !== '' ? intval($_POST['nueva_ficha']) : null;

        try {
            if ($usuario_id > 0) {
                if ($quitar_ficha) {
                    $stmt_upd = $conn->prepare("UPDATE usuarios SET ficha_id = NULL WHERE id = ? AND LOWER(TRIM(rol)) = 'instructor'");
                    $stmt_upd->bind_param("i", $usuario_id);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                    $_SESSION['message'] = "Ficha quitada del instructor.";
                    $_SESSION['message_type'] = "warning";
                } else {
                    $stmt_upd = $conn->prepare("UPDATE usuarios SET ficha_id = ? WHERE id = ? AND LOWER(TRIM(rol)) = 'instructor'");
                    if ($nueva_ficha === null) {
                        // Si no se seleccionó ficha y no es quitar explícito, se deja en NULL
                        $stmt_upd = $conn->prepare("UPDATE usuarios SET ficha_id = NULL WHERE id = ? AND LOWER(TRIM(rol)) = 'instructor'");
                        $stmt_upd->bind_param("i", $usuario_id);
                    } else {
                        $stmt_upd->bind_param("ii", $nueva_ficha, $usuario_id);
                    }
                    $stmt_upd->execute();
                    $stmt_upd->close();
                    $_SESSION['message'] = "Ficha del instructor actualizada.";
                    $_SESSION['message_type'] = "success";
                }
            }
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = "Error al actualizar la ficha del instructor: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "No tienes permisos para realizar esta acción.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#validacion");
    exit();
}

$documento = $_SESSION['documento'];
$tipo_documento = $_SESSION['tipo_documento'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$actividades = [];

$datos_usuario = [];
$saludo = "Bienvenid@";

try {
    // Obtener datos y rol
    $sql = "SELECT nombre, apellido, tipo_documento, numero_documento, genero, fecha_nacimiento, correo, fecha_registro, rol, foto_perfil
            FROM usuarios
            WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $documento, $tipo_documento);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $datos_usuario = $resultado->fetch_assoc();
    $stmt->close();

    if (!in_array(strtolower(trim($datos_usuario['rol'])), ['instructor', 'administrador'])) {
        header("Location: login.php");
        exit();
    }

    // Saludo según género
    if ($datos_usuario['genero'] === 'masculino') $saludo = "Bienvenido";
    elseif ($datos_usuario['genero'] === 'femenino') $saludo = "Bienvenida";

    // Obtener ID del usuario para cargar preferencias
    $sql_id = "SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt_id = $conn->prepare($sql_id);
    $stmt_id->bind_param("ss", $documento, $tipo_documento);
    $stmt_id->execute();
    $result_id = $stmt_id->get_result();
    $id_usuario_actual = 0;
    if ($row_id = $result_id->fetch_assoc()) {
        $id_usuario_actual = $row_id['id'];
    }
    $stmt_id->close();

    // Conteo de fichas asignadas para tarjeta de Inicio (solo rol instructor)
    $fichas_asignadas_instructor = 0;
    if (isset($datos_usuario['rol']) && strtolower(trim($datos_usuario['rol'])) === 'instructor' && $id_usuario_actual > 0) {
        if ($stmt_fi = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE id = ? AND ficha_id IS NOT NULL")) {
            $stmt_fi->bind_param("i", $id_usuario_actual);
            if ($stmt_fi->execute()) {
                $res_fi = $stmt_fi->get_result();
                $row_fi = $res_fi ? $res_fi->fetch_assoc() : ["total" => 0];
                $fichas_asignadas_instructor = (int)($row_fi['total'] ?? 0);
            }
            $stmt_fi->close();
        }
    }

    // Cargar preferencias del usuario (incluyendo tema)
    $user_prefs = [
        'notificar_vencidas' => true,
        'notificar_proximas' => true,
        'notificar_faltantes' => true,
        'tema_oscuro' => false
    ];

    if ($id_usuario_actual > 0) {
        $sql_prefs = "SELECT notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro 
                      FROM preferencias_notificaciones 
                      WHERE id_usuario = ?";
        $stmt_prefs = $conn->prepare($sql_prefs);
        $stmt_prefs->bind_param("i", $id_usuario_actual);
        $stmt_prefs->execute();
        $result_prefs = $stmt_prefs->get_result();
        
        if ($row_prefs = $result_prefs->fetch_assoc()) {
            $user_prefs = [
                'notificar_vencidas' => (bool)$row_prefs['notificar_vencidas'],
                'notificar_proximas' => (bool)$row_prefs['notificar_proximas'],
                'notificar_faltantes' => (bool)$row_prefs['notificar_faltantes'],
                'tema_oscuro' => (bool)$row_prefs['tema_oscuro']
            ];
        } else {
            // Insertar preferencias por defecto si no existen
            $sql_insert_prefs = "INSERT INTO preferencias_notificaciones (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro) VALUES (?, 1, 1, 1, 0)";
            $stmt_insert = $conn->prepare($sql_insert_prefs);
            $stmt_insert->bind_param("i", $id_usuario_actual);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_prefs->close();

        // Asegurar tabla de actividades
        $conn->query("CREATE TABLE IF NOT EXISTS actividades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Registrar inicio de sesión por sesión para el rol actual
        if (empty($_SESSION['actividad_login_registrada_instructores'])) {
            $tipo_login = strtolower($datos_usuario['rol']) === 'administrador' ? 'admin_login' : 'instructor_login';
            $titulo_login = 'Inicio de sesión';
            $desc_login = ucfirst($datos_usuario['rol']) . ' inició sesión';
            if ($stmt_ins_login = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?, ?, ?, ?)")) {
                $stmt_ins_login->bind_param("isss", $id_usuario_actual, $tipo_login, $titulo_login, $desc_login);
                $stmt_ins_login->execute();
                $stmt_ins_login->close();
            }
            $_SESSION['actividad_login_registrada_instructores'] = true;
        }

        // Obtener últimas 10 actividades del usuario
        if ($stmt_acts = $conn->prepare("SELECT id, tipo, titulo, descripcion, fecha FROM actividades WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 10")) {
            $stmt_acts->bind_param("i", $id_usuario_actual);
            if ($stmt_acts->execute()) {
                $res_acts = $stmt_acts->get_result();
                while ($row = $res_acts->fetch_assoc()) { $actividades[] = $row; }
            }
            $stmt_acts->close();
        }
    }

} catch (Exception $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    $datos_usuario = [
        'nombre' => $nombre,
        'apellido' => '',
        'tipo_documento' => $tipo_documento,
        'numero_documento' => $documento,
        'genero' => '',
        'fecha_nacimiento' => '',
        'correo' => '',
        'fecha_registro' => '',
        'rol' => 'usuario',
        'foto_perfil' => ''
    ];
    $user_prefs = [
        'notificar_vencidas' => true,
        'notificar_proximas' => true,
        'notificar_faltantes' => true,
        'tema_oscuro' => false
    ];
}

// Lógica para manejar el cambio de tema desde el formulario de preferencias
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tema"])) {
    // Aquí no hacemos nada en PHP, la lógica de guardado y carga se manejará en JavaScript
}

// --- INICIO DE CÓDIGO PARA LA LÓGICA DE BORRADO DE FICHA ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_ficha_id'])) {
    if ($datos_usuario['rol'] === 'administrador') {
        $ficha_id_to_delete = $_POST['delete_ficha_id'];
        // Obtener datos de la ficha para registrar actividad
        $ficha_info = null;
        if ($stmt_fi = $conn->prepare("SELECT codigo_ficha, programa FROM fichas WHERE id = ?")) {
            $stmt_fi->bind_param("i", $ficha_id_to_delete);
            $stmt_fi->execute();
            $ficha_info = $stmt_fi->get_result()->fetch_assoc();
            $stmt_fi->close();
        }
        // Usar transacciones para asegurar la integridad de los datos
        $conn->begin_transaction();

        try {
            // Eliminar la ficha
            $sql_delete_ficha = "DELETE FROM fichas WHERE id = ?";
            $stmt_ficha = $conn->prepare($sql_delete_ficha);
            $stmt_ficha->bind_param("i", $ficha_id_to_delete);
            $stmt_ficha->execute();
            $stmt_ficha->close();

            $conn->commit();
            $_SESSION['message'] = "Ficha eliminada exitosamente.";
            $_SESSION['message_type'] = "success";

            // Registrar actividad de administrador: ficha eliminada
            if ($id_usuario_actual > 0) {
                $tipo = 'ficha_eliminada';
                $titulo = 'Ficha eliminada';
                $desc = 'Se eliminó la ficha ' . ($ficha_info ? ($ficha_info['codigo_ficha'] . ' - ' . $ficha_info['programa']) : ('ID ' . $ficha_id_to_delete));
                if ($stmt_ai = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?, ?, ?, ?)")) {
                    $stmt_ai->bind_param("isss", $id_usuario_actual, $tipo, $titulo, $desc);
                    $stmt_ai->execute();
                    $stmt_ai->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error al eliminar la ficha: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }

    } else {
        $_SESSION['message'] = "No tienes permiso para realizar esta acción.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#fichas");
    exit();
}

// --- LÓGICA PARA VALIDACIÓN DE USUARIOS (SOLO ADMINISTRADORES) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['validar_usuario'])) {
    if (isset($datos_usuario['rol']) && strtolower(trim($datos_usuario['rol'])) === 'administrador') {
        $usuario_id = intval($_POST['usuario_id'] ?? 0);
        $accion = $_POST['validar_usuario']; // 'aprobar' o 'rechazar'
        $ficha_asignada = isset($_POST['ficha_asignada']) ? $_POST['ficha_asignada'] : null;

        $conn->begin_transaction();
        try {
            if ($accion === 'aprobar') {
                // Actualizar estado a 'activo'
                $sql_aprobar = "UPDATE usuarios SET estado = 'activo' WHERE id = ?";
                $stmt_aprobar = $conn->prepare($sql_aprobar);
                $stmt_aprobar->bind_param("i", $usuario_id);
                $stmt_aprobar->execute();
                $stmt_aprobar->close();

                // Si es instructor y se asignó una ficha, actualizar la ficha
                if ($ficha_asignada && $ficha_asignada !== '') {
                    $sql_asignar_ficha = "UPDATE usuarios SET ficha_id = ? WHERE id = ?";
                    $stmt_asignar = $conn->prepare($sql_asignar_ficha);
                    $stmt_asignar->bind_param("ii", $ficha_asignada, $usuario_id);
                    $stmt_asignar->execute();
                    $stmt_asignar->close();
                }

                $_SESSION['message'] = "Usuario aprobado exitosamente.";
                $_SESSION['message_type'] = "success";
            } elseif ($accion === 'rechazar') {
                // Debug: Log the rejection attempt
                error_log("DEBUG: Intentando rechazar usuario ID: $usuario_id");
                
                // Actualizar estado a 'rechazado' y limpiar ficha por consistencia
                $sql_rechazar = "UPDATE usuarios SET estado = 'rechazado', ficha_id = NULL WHERE id = ?";
                $stmt_rechazar = $conn->prepare($sql_rechazar);
                $stmt_rechazar->bind_param("i", $usuario_id);
                $stmt_rechazar->execute();
                $affected_rows = $stmt_rechazar->affected_rows;
                $stmt_rechazar->close();

                error_log("DEBUG: Filas afectadas al rechazar: $affected_rows");

                if ($affected_rows > 0) {
                    $_SESSION['message'] = "Usuario rechazado correctamente.";
                    $_SESSION['message_type'] = "warning";
                    error_log("DEBUG: Usuario $usuario_id rechazado exitosamente");
                } else {
                    $_SESSION['message'] = "No se pudo rechazar el usuario. ID: $usuario_id";
                    $_SESSION['message_type'] = "danger";
                    error_log("DEBUG: Falló el rechazo del usuario $usuario_id");
                }
            }

            $conn->commit();

            // Registrar actividad de administrador: aprobar/rechazar usuario
            if ($id_usuario_actual > 0) {
                $info_user = null;
                if ($stmt_u = $conn->prepare("SELECT nombre, apellido, rol FROM usuarios WHERE id = ?")) {
                    $stmt_u->bind_param("i", $usuario_id);
                    $stmt_u->execute();
                    $info_user = $stmt_u->get_result()->fetch_assoc();
                    $stmt_u->close();
                }
                $tipo = ($accion === 'aprobar') ? 'usuario_aprobado' : 'usuario_rechazado';
                $titulo = ($accion === 'aprobar') ? 'Usuario aprobado' : 'Usuario rechazado';
                $desc = ($info_user ? ($info_user['nombre'] . ' ' . $info_user['apellido'] . ' (' . $info_user['rol'] . ')') : ('Usuario ID ' . $usuario_id));
                if ($stmt_ai = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?, ?, ?, ?)")) {
                    $stmt_ai->bind_param("isss", $id_usuario_actual, $tipo, $titulo, $desc);
                    $stmt_ai->execute();
                    $stmt_ai->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error al procesar la validación: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }

    } else {
        $_SESSION['message'] = "No tienes permisos para realizar esta acción.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#validacion");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal | ASEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.20/dist/sweetalert2.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        /* Navbar */
        header.navbar {
            background: #4CAF50;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand h1 {
            font-weight: 600;
            color: #fff;
        }
        
        /* Botón menú */
        .material-symbols-outlined[type="button"] {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .material-symbols-outlined[type="button"]:hover {
            color: #FFC107;
        }
        
        /* Menú lateral */
        .offcanvas .nav-link {
            transition: all 0.3s ease;
            color: #495057;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .offcanvas .nav-link:hover {
            background-color: #e6f4ea;
            color: #2E7D32 !important;
        }
        .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
            font-weight: bold;
        }

        /* Sección bienvenida */
        .welcome-section {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .welcome-section::after {
            content: "";
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(25deg);
        }

        /* Tarjetas de estadísticas */
        .stats-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 4px solid #4CAF50;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .stats-card span {
            font-size: 2.5rem;
            color: #4CAF50;
        }

        /* Botones */
        .btn-success-custom {
            background-color: #4CAF50;
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background-color: #2E7D32;
            transform: translateY(-2px);
        }
        
        .btn-danger-custom {
            background-color: #dc3545;
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-danger-custom:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        /* ESTILOS PARA MODO OSCURO */
        body.bg-dark {
            background-color: #121212 !important;
            color: #e0e0e0;
        }
        .bg-dark .navbar {
            background-color: #212121 !important;
            box-shadow: 0 3px 8px rgba(255,255,255,0.1);
        }
        .bg-dark .navbar-brand h1 {
            color: #fff;
        }
        .bg-dark .offcanvas {
            background-color: #212121 !important;
            color: #e0e0e0;
        }
        .bg-dark .offcanvas-header .btn-close {
            filter: invert(1);
        }
        .bg-dark .offcanvas .nav-link {
            color: #bdbdbd;
        }
        .bg-dark .offcanvas .nav-link:hover {
            background-color: #333;
            color: #e0e0e0 !important;
        }
        .bg-dark .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .bg-dark .welcome-section {
            background: linear-gradient(135deg, #2e8700, #4CAF50) !important;
            box-shadow: 0 10px 25px rgba(255,255,255,0.15);
        }
        .bg-dark .card,
        .bg-dark .list-group-item,
        .bg-dark .form-control,
        .bg-dark .form-select,
        .bg-dark .input-group-text,
        .bg-dark .perfil-info {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        .bg-dark .card-title,
        .bg-dark h2,
        .bg-dark h4 {
            color: #e0e0e0 !important;
        }
        .bg-dark .text-muted {
            color: #a0a0a0 !important;
        }
        .bg-dark .text-success {
            color: #66bb6a !important;
        }
        .bg-dark .nav-link.active,
        .bg-dark .nav-link:hover {
            background-color: #4CAF50 !important;
            color: #fff !important;
        }
        .bg-dark .list-group-item:hover {
            background: rgba(57,169,0,0.1) !important;
        }
        .bg-dark .btn-outline-dark {
            color: #bdbdbd !important;
            border-color: #bdbdbd !important;
        }
        .bg-dark .btn-outline-dark:hover {
            background-color: #bdbdbd !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-danger {
            color: #ef5350 !important;
            border-color: #ef5350 !important;
        }
        .bg-dark .btn-outline-danger:hover {
            background-color: #ef5350 !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-success {
            color: #66bb6a !important;
            border-color: #66bb6a !important;
        }
        .bg-dark .btn-outline-success:hover {
            background-color: #66bb6a !important;
            color: #212121 !important;
        }
        .bg-dark .stats-card {
            background-color: #2c2c2c !important;
            border-left-color: #444 !important;
            color: #e0e0e0 !important;
        }
        .bg-dark .stats-card h4 {
            color: #e0e0e0 !important;
        }
        .bg-dark .stats-card p {
            color: #a0a0a0 !important;
        }
        .bg-dark .stats-card span {
            color: #66bb6a !important;
        }
        .bg-dark .table {
            color: #e0e0e0;
            border-color: #444;
        }
        .bg-dark .table thead {
            color: #fff;
            background-color: #333;
            border-bottom: 2px solid #666;
        }
        /* Forzar encabezados (títulos) oscuros incluso si usan .table-light */
        .bg-dark .table thead th {
            background-color: #2b2b2b !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .bg-dark .table thead.table-light,
        .bg-dark .table .table-light thead,
        .bg-dark .table .table-light th {
            background-color: #2b2b2b !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .bg-dark .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: #2c2c2c;
            color: #e0e0e0;
        }
        .bg-dark .table-striped>tbody>tr:nth-of-type(even)>* {
            background-color: #212121;
            color: #e0e0e0;
        }
        .bg-dark .table-hover>tbody>tr:hover>* {
            background-color: #383838;
            color: #fff;
        }

        /* ======================= ESTILOS PARA VALIDACIÓN DE USUARIOS ======================= */
        .validation-header {
            background: linear-gradient(135deg, #FF5722 0%, #D84315 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(255, 87, 34, 0.3);
        }
        
        .bg-dark .validation-header {
            background: linear-gradient(135deg, #BF360C 0%, #8D2F1B 100%);
        }
        
        .validation-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #FF5722;
            transition: all 0.3s ease;
        }
        
        .validation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .bg-dark .validation-card {
            background: #2c2c2c;
            border-left-color: #FF5722;
            color: #e0e0e0;
        }
        
        .user-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pendiente {
            background-color: #FFF3E0;
            color: #E65100;
            border: 1px solid #FFB74D;
        }
        
        .bg-dark .status-pendiente {
            background-color: #332212;
            color: #FFB74D;
        }
        
        .status-activo {
            background-color: #E8F5E8;
            color: #2E7D32;
            border: 1px solid #66BB6A;
        }
        
        .bg-dark .status-activo {
            background-color: #1B4332;
            color: #66BB6A;
        }
        
        .status-rechazado {
            background-color: #FFEBEE;
            color: #C62828;
            border: 1px solid #EF5350;
        }
        
        .bg-dark .status-rechazado {
            background-color: #332020;
            color: #EF5350;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 8px;
            border-left: 3px solid #4CAF50;
        }
        
        .bg-dark .info-item {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .info-item strong {
            color: #2E7D32;
        }
        
        .bg-dark .info-item strong {
            color: #66bb6a;
        }
        
        .validation-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn-aprobar {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-aprobar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
            color: white;
        }
        
        .btn-rechazar {
            background: linear-gradient(135deg, #f44336, #c62828);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-rechazar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
            color: white;
        }
        
        .ficha-select {
            min-width: 200px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.5rem;
        }
        
        .bg-dark .ficha-select {
            background: #2c2c2c;
            color: #e0e0e0;
            border-color: #444;
        }

        /* ======================= ESTILOS MEJORADOS PARA CONFIGURACIÓN ======================= */
        #configuracion {
            background: transparent;
            min-height: 100vh;
        }
        
        .config-header {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 3rem;
            text-align: center;
            box-shadow: 0 15px 35px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .config-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .config-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .config-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .bg-dark .config-header {
            background: linear-gradient(135deg, #2e8700 0%, #1B5E20 100%);
            box-shadow: 0 15px 35px rgba(46, 135, 0, 0.4);
        }
        
        .config-card {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .bg-dark .config-card {
            background: #1e1e1e;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .bg-dark .config-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        .config-card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .bg-dark .config-card-header {
            background: linear-gradient(135deg, #2c2c2c 0%, #333333 100%);
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .config-card-header h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .bg-dark .config-card-header h4 {
            color: #ecf0f1;
        }
        
        .config-card-header .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .config-card-body {
            padding: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-label-enhanced {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bg-dark .form-label-enhanced {
            color: #bdc3c7;
        }
        
        .form-control-enhanced {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control-enhanced:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.15);
            background: #ffffff;
        }
        
        .bg-dark .form-control-enhanced {
            background: #2c2c2c;
            border-color: #444;
            color: #e0e0e0;
        }
        
        .bg-dark .form-control-enhanced:focus {
            background: #333;
            border-color: #66bb6a;
            box-shadow: 0 0 0 0.2rem rgba(102, 187, 106, 0.15);
        }


         /* ======================= PLACEHOLDERS PERSONALIZADOS ======================= */

        /* Modo claro */
        .form-control-enhanced::placeholder {
            color: #7f8c8d; /* gris suave */
            opacity: 1;
            transition: color 0.3s ease;
        }

/* Modo oscuro */
        .bg-dark .form-control-enhanced::placeholder {
            color: #b0b0b0; /* gris claro */
            opacity: 1;
        }





        
        .btn-enhanced {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-enhanced::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.5s ease;
        }
        
        .btn-enhanced:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-primary-enhanced {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
        }
        
        .btn-primary-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }
        
        .btn-danger-enhanced {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }
        
        .btn-warning-enhanced {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }
        
        .profile-section {
            text-align: center;
            padding: 2rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .bg-dark .profile-section {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .profile-image-enhanced {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .profile-image-enhanced:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .bg-dark .profile-image-enhanced {
            border-color: #2c2c2c;
        }
        
        .image-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(76, 175, 80, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-upload-overlay:hover {
            opacity: 1;
        }
        
        .image-upload-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        .preferences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .preference-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .preference-card:hover {
            border-color: #4CAF50;
            background: #e8f5e8;
            transform: translateY(-3px);
        }
        
        .bg-dark .preference-card {
            background: #2c2c2c;
            border-color: #444;
        }
        
        .bg-dark .preference-card:hover {
            border-color: #66bb6a;
            background: #333;
        }
        
        .preference-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .preference-card h5 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .bg-dark .preference-card h5 {
            color: #ecf0f1;
        }
        
        .preference-card p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .bg-dark .preference-card p {
            color: #95a5a6;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        input:checked + .toggle-slider {
            background: #4CAF50;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .fade-in-left {
            animation: fadeInLeft 0.8s ease-out;
        }
        
        .fade-in-right {
            animation: fadeInRight 0.8s ease-out;
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
        
        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #4CAF50, transparent);
            margin: 3rem 0;
            border: none;
        }
        
        .bg-dark .section-divider {
            background: linear-gradient(90deg, transparent, #66bb6a, transparent);
        }
        
        @media (max-width: 768px) {
            .config-header {
                padding: 2rem 1rem;
            }
            
            .config-header h1 {
                font-size: 2rem;
            }
            
            .config-card-body {
                padding: 1.5rem;
            }
            
            .preferences-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .validation-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .user-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body <?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
    ?> class="<?php echo ($isDark ? 'bg-dark text-light' : ''); ?>">
<style>
  .swal2-popup { background: #2b2b2b !important; color: #f1f1f1 !important; }
  .swal2-title, .swal2-html-container { color: #f1f1f1 !important; }
  .swal2-actions .swal2-styled.swal2-confirm { background: #4CAF50 !important; border: 0 !important; }
  .swal2-actions .swal2-styled.swal2-cancel { background: #555 !important; border: 0 !important; }
  .swal2-toast { background: #2b2b2b !important; color: #f1f1f1 !important; }
</style>

<header class="navbar navbar-light sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <span class="material-symbols-outlined me-2 text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideMenu" aria-controls="sideMenu">menu</span>
            <h1 class="h4 mb-0">
                <?php echo $saludo . ", " . htmlspecialchars($datos_usuario['nombre']); ?>
                <span class="badge bg-light text-dark ms-2"><?php echo ucfirst($datos_usuario['rol']); ?></span>
            </h1>
        </a>
    </div>
</header>

<div class="offcanvas offcanvas-start bg-light" tabindex="-1" id="sideMenu" aria-labelledby="sideMenuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sideMenuLabel">Menú Principal</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('inicio')"><i class="fas fa-home me-2"></i>Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('perfil')"><i class="fas fa-user-circle me-2"></i>Perfil</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('fichas')"><i class="fas fa-folder-open me-2"></i>Fichas & Bitácoras</a></li>
            <?php if (in_array(strtolower(trim($datos_usuario['rol'])), ['administrador'])): ?>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('validacion')"><i class="fas fa-user-check me-2"></i>Validar Usuarios</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('configuracion')"><i class="fas fa-cogs me-2"></i>Configuración</a></li>
            <li class="nav-item mt-auto"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<main class="container py-4">
    <div id="inicio">
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-3">¡Bienvenido a la página principal!</h2>
                    <p class="lead mb-0">Gestiona las fichas, bitácoras y perfil de los aprendices.</p>
                </div>
                <div class="col-md-4 text-center d-none d-md-block">
                    <span class="material-symbols-outlined" style="font-size: 4rem;">dashboard</span>
                </div>
            </div>
        </div>
        <?php if (strtolower(trim($datos_usuario['rol'])) === 'instructor'): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-primary" style="font-size: 2rem;">person</span>
                    <h4 class="mt-2 mb-1">Perfil</h4>
                    <p class="text-muted mb-0">Completo</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-info" style="font-size: 2rem;">book</span>
                    <h4 class="mt-2 mb-1">Fichas</h4>
                    <p class="text-muted mb-0">
                        <?php echo $fichas_asignadas_instructor; ?> Asignada<?php echo ($fichas_asignadas_instructor == 1 ? '' : 's'); ?>
                    </p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-warning" style="font-size: 2rem;">schedule</span>
                    <h4 class="mt-2 mb-1">Última Sesión</h4>
                    <p class="text-muted mb-0">Hoy</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-danger" style="font-size: 2rem;">verified</span>
                    <h4 class="mt-2 mb-1">Estado</h4>
                    <p class="text-muted mb-0">Activo</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-primary" style="font-size: 2rem;">person</span>
                    <h4 class="mt-2 mb-1">Perfil</h4>
                    <p class="text-muted mb-0">Completo</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-warning" style="font-size: 2rem;">schedule</span>
                    <h4 class="mt-2 mb-1">Última Sesión</h4>
                    <p class="text-muted mb-0">Hoy</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-danger" style="font-size: 2rem;">verified</span>
                    <h4 class="mt-2 mb-1">Estado</h4>
                    <p class="text-muted mb-0">Activo</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <span class="material-symbols-outlined text-primary" style="font-size: 3rem;">person_outline</span>
                        <h5 class="card-title">Mi Perfil</h5>
                        <p class="card-text">Visualiza y edita tu información personal</p>
                        <button class="btn btn-primary" onclick="mostrarSeccion('perfil')">Ver Perfil</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <span class="material-symbols-outlined text-success" style="font-size: 3rem;">description</span>
                        <h5 class="card-title">Fichas & Bitácoras</h5>
                        <p class="card-text">Revisa las bitácoras y gestiona las fichas de aprendices</p>
                        <button class="btn btn-success" onclick="mostrarSeccion('fichas')">Ver Fichas</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <span class="material-symbols-outlined text-warning" style="font-size: 3rem;">settings</span>
                        <h5 class="card-title">Configuración</h5>
                        <p class="card-text">Personaliza tu cuenta y preferencias</p>
                        <button class="btn btn-warning" onclick="mostrarSeccion('configuracion')">Configurar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>








































































    <style>
/* ===== ESTILOS MODO CLARO ===== */

/* Banner superior con degradado */
.profile-banner {
    background: linear-gradient(135deg, #39a900 0%, #2d8400 100%);
    position: relative;
}

.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 Q300,60 600,30 T1200,0 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.1)"/></svg>') no-repeat bottom;
    background-size: cover;
}

.banner-content {
    z-index: 1;
}

/* Animación de la foto de perfil */
.profile-picture {
    transition: all 0.3s ease;
    animation: fadeInScale 0.6s ease-out;
}

.profile-picture:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(57, 169, 0, 0.3) !important;
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Badge de rol */
.profile-badge {
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}

.badge-aprendiz {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
}

.badge-instructor {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.badge-admin {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
}

/* Botones con efecto hover */
.btn-hover {
    transition: all 0.3s ease;
    border-radius: 8px;
    font-weight: 600;
}

.btn-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-success {
    background: linear-gradient(135deg, #39a900 0%, #2d8400 100%);
    border: none;
}

.btn-success:hover {
    background: linear-gradient(135deg, #2d8400 0%, #1f5a00 100%);
}

/* Pestañas personalizadas */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link:hover {
    color: #39a900;
    border-bottom-color: #39a900;
    background-color: rgba(57, 169, 0, 0.05);
}

.nav-tabs .nav-link.active {
    color: #39a900;
    border-bottom-color: #39a900;
    background-color: rgba(57, 169, 0, 0.05);
    font-weight: 600;
}

/* Tarjetas de información */
.info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.info-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.info-item {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-icon {
    width: 40px;
    text-align: center;
    font-size: 1.25rem;
    margin-right: 1rem;
}

/* Tarjetas de estadísticas */
.stat-card, .stat-box {
    background: white;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.stat-card:hover, .stat-box:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-5px);
    border-color: #39a900;
}

.stat-icon i {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Timeline de actividad */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #39a900, #e9ecef);
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
    animation: slideInLeft 0.5s ease-out;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.timeline-marker {
    position: absolute;
    left: -26px;
    top: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    z-index: 1;
}

.timeline-content {
    background: white;
    padding: 1.25rem;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
}

.timeline-content:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transform: translateX(5px);
    border-color: #39a900;
}

.timeline-item .activity-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: transparent;
    border: 0;
    color: #999;
    line-height: 1;
    padding: 4px;
    cursor: pointer;
}

.timeline-item .activity-close:hover {
    color: #e53935;
}

/* Tabla personalizada */
.table-hover tbody tr {
    transition: all 0.3s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(57, 169, 0, 0.05);
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Badges personalizados */
.badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.4em 0.8em;
}

/* Alertas personalizadas */
.alert {
    border-radius: 10px;
    border: none;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    color: #856404;
}

/* Barra de progreso personalizada */
.progress {
    border-radius: 10px;
    background-color: #e9ecef;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.progress-bar {
    border-radius: 10px;
    transition: width 1s ease;
}

/* Animaciones de entrada */
.card {
    animation: fadeInUp 0.6s ease-out;
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

/* Efectos de hover suaves en íconos */
.fas, .far {
    transition: all 0.3s ease;
}

.info-item:hover .fas,
.timeline-content:hover .fas {
    transform: scale(1.1);
    color: #39a900 !important;
}

/* Sombras suaves para las tarjetas */
.card, .info-card, .stat-card, .stat-box {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* Scrollbar */
.tab-content::-webkit-scrollbar {
    width: 8px;
}
.tab-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.tab-content::-webkit-scrollbar-thumb {
    background: #39a900;
    border-radius: 10px;
}
.tab-content::-webkit-scrollbar-thumb:hover {
    background: #2d8400;
}

/* Textos */
.text-success { color: #39a900 !important; }
.bg-success { background-color: #39a900 !important; }
.border-success { border-color: #39a900 !important; }
.btn-outline-success {
    color: #39a900;
    border-color: #39a900;
}
.btn-outline-success:hover {
    background-color: #39a900;
    color: white;
}

/* Resplandor */
.badge-danger {
    animation: glow 2s ease-in-out infinite alternate;
}
@keyframes glow {
    from { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
    to { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8); }
}

/* Responsive */
@media (max-width: 768px) {
    .profile-picture {
        width: 120px !important;
        height: 120px !important;
    }
    
    .banner-content {
        padding: 2rem 1rem !important;
    }
    
    .nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .timeline {
        padding-left: 20px;
    }
    
    .timeline-marker {
        left: -21px;
        width: 14px;
        height: 14px;
    }
    
    .stat-box h3 {
        font-size: 1.5rem;
    }
}

/* ===== MODO OSCURO ===== */

/* Banner y fondo general */
body.bg-dark {
    background-color: #121212 !important;
    color: #e0e0e0;
}

body.bg-dark .profile-banner {
    background: linear-gradient(135deg, #256b00 0%, #173f00 100%);
}

body.bg-dark .banner-overlay {
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 Q300,60 600,30 T1200,0 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.04)"/></svg>') no-repeat bottom;
}

/* Card Header - Sección de foto de perfil */
body.bg-dark .card-header {
    background: #1a1a1a !important;
    border-bottom: 1px solid #2d2d2d !important;
}

/* Nombre del perfil */
body.bg-dark .profile-name {
    color: #f5f5f5 !important;
}

/* Email del perfil */
body.bg-dark .profile-email {
    color: #b0b0b0 !important;
}

body.bg-dark .profile-email .fa-envelope {
    color: #39a900 !important;
}

/* Badges de estado (Cuenta Aprobada, Última conexión, Miembro desde) */
body.bg-dark .status-badge.bg-success-subtle {
    background: rgba(57, 169, 0, 0.2) !important;
    color: #4ade80 !important;
    border-color: #39a900 !important;
}

body.bg-dark .status-badge.bg-info-subtle {
    background: rgba(59, 130, 246, 0.2) !important;
    color: #60a5fa !important;
    border-color: #3b82f6 !important;
}

body.bg-dark .status-badge.bg-primary-subtle {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #818cf8 !important;
    border-color: #6366f1 !important;
}

/* Borde de la foto de perfil */
body.bg-dark .profile-picture {
    border-color: #1a1a1a !important;
}

/* Tarjetas principales */
body.bg-dark .card,
body.bg-dark .info-card,
body.bg-dark .stat-card,
body.bg-dark .stat-box {
    background: #1a1a1a;
    border-color: #2d2d2d;
    box-shadow: 0 2px 10px rgba(0,0,0,0.6);
}

body.bg-dark .info-card {
    background: linear-gradient(135deg, #1a1a1a 0%, #202020 100%);
}

body.bg-dark .card:hover,
body.bg-dark .info-card:hover,
body.bg-dark .stat-card:hover,
body.bg-dark .stat-box:hover {
    border-color: #39a900;
    box-shadow: 0 8px 25px rgba(0,0,0,0.7);
}

/* Títulos y encabezados */
body.bg-dark h1,
body.bg-dark h2,
body.bg-dark h3,
body.bg-dark h4,
body.bg-dark h5,
body.bg-dark h6 {
    color: #f5f5f5;
}

/* Labels de información (Nombre Completo, Género, etc.) */
body.bg-dark .info-label {
    color: #9ca3af !important;
}

/* Valores de información (datos en negrita) */
body.bg-dark .info-value {
    color: #e5e7eb !important;
}

/* Texto muted general */
body.bg-dark .text-muted {
    color: #b0b0b0 !important;
}

/* Items de información */
body.bg-dark .info-item {
    border-bottom-color: #2d2d2d;
}

/* Timeline */
body.bg-dark .timeline::before {
    background: linear-gradient(to bottom, #39a900, #2d2d2d);
}

body.bg-dark .timeline-marker {
    border-color: #1a1a1a;
}

body.bg-dark .timeline-content {
    background: #1a1a1a;
    border-color: #2d2d2d;
}

body.bg-dark .timeline-content:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.5);
}

/* Tabs */
body.bg-dark .nav-tabs {
    border-bottom-color: #2d2d2d;
}

body.bg-dark .nav-tabs .nav-link {
    color: #b0b0b0;
}

body.bg-dark .nav-tabs .nav-link.active,
body.bg-dark .nav-tabs .nav-link:hover {
    color: #39a900;
    background-color: rgba(57,169,0,0.1);
}

/* Tablas */
body.bg-dark .table {
    color: #e0e0e0;
}

body.bg-dark .table-hover tbody tr:hover {
    background-color: rgba(57,169,0,0.1);
}

body.bg-dark .table td,
body.bg-dark .table th {
    border-color: #2d2d2d;
}

body.bg-dark .table thead th {
    color: #f5f5f5;
    background-color: #2d2d2d;
}

body.bg-dark .table tbody td {
    color: #e0e0e0;
}

body.bg-dark .table-light {
    background-color: #2d2d2d !important;
    color: #f5f5f5 !important;
}

body.bg-dark .table thead {
    background-color: #2d2d2d;
    border-bottom: 2px solid #444;
}

body.bg-dark .table tbody tr {
    background-color: #1a1a1a;
    border-bottom: 1px solid #2d2d2d;
}

body.bg-dark .table tbody tr:nth-of-type(even) {
    background-color: #212121;
}

body.bg-dark .table tbody tr:hover {
    background-color: #2a2a2a !important;
}

/* Textos dentro de las tablas en modo oscuro */
body.bg-dark .table td strong {
    color: #e0e0e0;
}

body.bg-dark .table td small {
    color: #9ca3af;
}

/* Badges dentro de tablas en modo oscuro */
body.bg-dark .table .badge.bg-primary {
    background-color: #3b82f6 !important;
    color: #fff !important;
}

body.bg-dark .table .badge.rounded-pill {
    background-color: #3b82f6 !important;
}

body.bg-dark .table .badge.bg-success {
    background-color: #39a900 !important;
}

body.bg-dark .table .badge.bg-info {
    background-color: #3b82f6 !important;
}

body.bg-dark .table .badge.bg-warning {
    background-color: #fbbf24 !important;
    color: #1a1a1a !important;
}

body.bg-dark .table .badge.bg-danger {
    background-color: #ef4444 !important;
}

/* Badges de estado en tablas modo oscuro */
body.bg-dark .table .badge.bg-success-subtle {
    background-color: rgba(57, 169, 0, 0.2) !important;
    color: #4ade80 !important;
    border: 1px solid #39a900;
}

body.bg-dark .table .badge.bg-info-subtle {
    background-color: rgba(59, 130, 246, 0.2) !important;
    color: #60a5fa !important;
    border: 1px solid #3b82f6;
}

body.bg-dark .table .badge.bg-warning-subtle {
    background-color: rgba(251, 191, 36, 0.2) !important;
    color: #fbbf24 !important;
    border: 1px solid #fbbf24;
}

body.bg-dark .table .badge.bg-danger-subtle {
    background-color: rgba(239, 68, 68, 0.2) !important;
    color: #ef4444 !important;
    border: 1px solid #ef4444;
}

/* Colores de texto en tablas modo oscuro */
body.bg-dark .table .text-primary {
    color: #60a5fa !important;
}

body.bg-dark .table .text-success {
    color: #4ade80 !important;
}

body.bg-dark .table .text-warning {
    color: #fbbf24 !important;
}

body.bg-dark .table .text-muted {
    color: #9ca3af !important;
}

/* Iconos en tablas modo oscuro */
body.bg-dark .table .fa-check-circle {
    color: #4ade80 !important;
}

body.bg-dark .table .fa-clock {
    color: #fbbf24 !important;
}

body.bg-dark .table .fa-folder,
body.bg-dark .table .fa-users,
body.bg-dark .table .fa-file-alt,
body.bg-dark .table .fa-chart-line,
body.bg-dark .table .fa-flag {
    color: #9ca3af !important;
}

/* Alerta dentro de info-card en modo oscuro */
body.bg-dark .info-card .alert-info {
    background: linear-gradient(135deg, #1a2332 0%, #1f2937 100%);
    color: #60a5fa;
    border: 1px solid #3b82f6;
}

body.bg-dark .info-card .alert-info .fa-folder,
body.bg-dark .info-card .alert-info .fa-users,
body.bg-dark .info-card .alert-info .fa-file-alt,
body.bg-dark .info-card .alert-info .fa-chart-line {
    color: #60a5fa !important;
}

body.bg-dark .info-card .alert-info h5 {
    color: #e0e0e0 !important;
}

body.bg-dark .info-card .alert-info small {
    color: #9ca3af !important;
}

/* Alertas */
body.bg-dark .alert-warning {
    background: linear-gradient(135deg, #3d3520 0%, #4a4028 100%);
    color: #ffc107;
}

body.bg-dark .alert-info {
    background: linear-gradient(135deg, #1a2332 0%, #1f2937 100%);
    color: #60a5fa;
}

body.bg-dark .alert-success {
    background: linear-gradient(135deg, #1a3320 0%, #1f4028 100%);
    color: #4ade80;
}

/* Botones */
body.bg-dark .btn-outline-success {
    color: #39a900;
    border-color: #39a900;
}

body.bg-dark .btn-outline-success:hover {
    background-color: #39a900;
    color: white;
}

body.bg-dark .btn-outline-secondary {
    color: #b0b0b0;
    border-color: #2d2d2d;
}

body.bg-dark .btn-outline-secondary:hover {
    background-color: #2d2d2d;
    color: #e0e0e0;
}

body.bg-dark .btn-secondary {
    background-color: #2d2d2d;
    border-color: #444;
    color: #e0e0e0;
}

/* Scrollbar */
body.bg-dark .tab-content::-webkit-scrollbar-track {
    background: #2d2d2d;
}

body.bg-dark .tab-content::-webkit-scrollbar-thumb {
    background: #39a900;
}

body.bg-dark .tab-content::-webkit-scrollbar-thumb:hover {
    background: #2d8400;
}

/* Iconos hover */
body.bg-dark .fas:hover,
body.bg-dark .info-item:hover .fas {
    color: #39a900 !important;
}

/* Listas */
body.bg-dark .list-group-item {
    background-color: #1a1a1a;
    border-color: #2d2d2d;
    color: #e0e0e0;
}

body.bg-dark .list-group-item:hover {
    background-color: #252525;
}

/* Progress bars en modo oscuro */
body.bg-dark .progress {
    background-color: #2d2d2d;
}

/* Badges en modo oscuro */
body.bg-dark .badge.bg-primary {
    background-color: #3b82f6 !important;
}

body.bg-dark .badge.bg-success {
    background-color: #39a900 !important;
}

body.bg-dark .badge.bg-warning {
    background-color: #fbbf24 !important;
    color: #1a1a1a !important;
}

body.bg-dark .badge.bg-danger {
    background-color: #ef4444 !important;
}

body.bg-dark .badge.bg-info {
    background-color: #3b82f6 !important;
}

/* Textos de colores en modo oscuro */
body.bg-dark .text-primary {
    color: #60a5fa !important;
}

body.bg-dark .text-success {
    color: #4ade80 !important;
}

body.bg-dark .text-warning {
    color: #fbbf24 !important;
}

body.bg-dark .text-danger {
    color: #ef4444 !important;
}

body.bg-dark .text-info {
    color: #60a5fa !important;
}

/* Card body en modo oscuro */
body.bg-dark .card-body {
    background-color: #1a1a1a;
    color: #e0e0e0;
}
</style><div id="perfil" style="display: none;">
    <!-- Banner superior con degradado -->
    <div class="profile-banner mb-4 position-relative overflow-hidden rounded-4 shadow">
        <div class="banner-overlay"></div>
        <div class="banner-content text-center text-white py-5 position-relative">
            <h2 class="fw-bold mb-2">
                <i class="fas fa-user-circle me-2"></i>
                Mi Perfil
            </h2>
            <p class="mb-0 opacity-75">Gestiona tu información personal y configuración</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <!-- Tarjeta principal del perfil -->
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
                <!-- Sección de encabezado con foto de perfil -->
                <div class="card-header bg-white border-0 py-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div class="position-relative d-inline-block">
                                <img src="<?php echo !empty($datos_usuario['foto_perfil']) ? htmlspecialchars($datos_usuario['foto_perfil']) : 'imagenes/perfil_default.png'; ?>"
                                    alt="Foto de perfil"
                                    class="img-fluid rounded-circle shadow-lg profile-picture"
                                    style="width: 160px; height: 160px; object-fit: cover; border: 5px solid #fff;">
                                <div class="profile-badge">
                                    <?php 
                                    $rol = strtolower($datos_usuario['rol']);
                                    $icon = '';
                                    $badge_class = '';
                                    
                                    if ($rol === 'aprendiz') {
                                        $icon = '🎓';
                                        $badge_class = 'badge-aprendiz';
                                    } elseif ($rol === 'instructor') {
                                        $icon = '🧑‍🏫';
                                        $badge_class = 'badge-instructor';
                                    } elseif ($rol === 'administrador') {
                                        $icon = '👨‍💼';
                                        $badge_class = 'badge-admin';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> rounded-pill px-3 py-2 shadow-sm">
                                        <?php echo $icon . ' ' . ucfirst($datos_usuario['rol']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <div class="ps-md-4">
                                <h3 class="fw-bold text-dark profile-name mb-2">
                                    <?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?>
                                </h3>
                                <p class="text-muted profile-email mb-3">
                                    <i class="fas fa-envelope me-2 text-success"></i>
                                    <?php echo htmlspecialchars($datos_usuario['correo']); ?>
                                </p>
                                
                                <!-- Badges de estado -->
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 status-badge">
                                        <i class="fas fa-check-circle me-1"></i> Cuenta Aprobada
                                    </span>
                                    <span class="badge bg-info-subtle text-info border border-info px-3 py-2 status-badge">
                                        <i class="fas fa-clock me-1"></i> Última conexión: <?php echo date("d/m/Y H:i"); ?>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 status-badge">
                                        <i class="fas fa-calendar-alt me-1"></i> Miembro desde <?php echo date("d/m/Y", strtotime($datos_usuario['fecha_registro'])); ?>
                                    </span>
                                </div>

                                <!-- Botones de acción -->
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-success btn-hover px-4 py-2" onclick="mostrarSeccion('configuracion');">
                                        <i class="fas fa-camera me-2"></i> Cambiar Foto
                                    </button>
                                    <button class="btn btn-outline-success btn-hover px-4 py-2" onclick="mostrarSeccion('configuracion');">
                                        <i class="fas fa-cog me-2"></i> Configuración
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de navegación -->
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-fill border-bottom-0" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-personal" type="button" role="tab">
                                <i class="fas fa-user me-2"></i> Información Personal
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="estadisticas-tab" data-bs-toggle="tab" data-bs-target="#estadisticas" type="button" role="tab">
                                <i class="fas fa-chart-line me-2"></i> Estadísticas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="actividad-tab" data-bs-toggle="tab" data-bs-target="#actividad" type="button" role="tab">
                                <i class="fas fa-history me-2"></i> Actividad Reciente
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4" id="perfilTabsContent">
                        <!-- Tab 1: Información Personal -->
                        <div class="tab-pane fade show active" id="info-personal" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3 h-100">
                                        <h5 class="fw-bold text-success mb-4">
                                            <i class="fas fa-id-card me-2"></i> Datos de Identificación
                                        </h5>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-user-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Nombre Completo</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-id-card-alt text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Tipo de Documento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['tipo_documento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-id-badge text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Número de Documento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['numero_documento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3 h-100">
                                        <h5 class="fw-bold text-success mb-4">
                                            <i class="fas fa-info-circle me-2"></i> Información Adicional
                                        </h5>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-venus-mars text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Género</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars(ucfirst($datos_usuario['genero'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-birthday-cake text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Fecha de Nacimiento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['fecha_nacimiento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-envelope text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Correo Electrónico</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['correo']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-user-shield fa-2x text-success"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Rol en el Sistema</h6>
                                        <h4 class="fw-bold text-dark info-value mb-0"><?php echo ucfirst($datos_usuario['rol']); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-calendar-check fa-2x text-info"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Fecha de Registro</h6>
                                        <h4 class="fw-bold text-dark info-value mb-0"><?php echo date("d/m/Y", strtotime($datos_usuario['fecha_registro'])); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-clock fa-2x text-warning"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Estado de Cuenta</h6>
                                        <h4 class="fw-bold text-success mb-0">Activa</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Estadísticas según el rol -->
                        <div class="tab-pane fade" id="estadisticas" role="tabpanel">
                            <h5 class="fw-bold text-success mb-4">
                                <i class="fas fa-chart-bar me-2"></i> Panel de Estadísticas
                            </h5>

                            <?php if (strtolower($datos_usuario['rol']) === 'aprendiz'): ?>
                            <!-- Estadísticas para Aprendiz -->
                            <?php
                            // ==================== CONSULTAS PARA ESTADÍSTICAS DEL APRENDIZ ====================
                            
                            // Total de bitácoras requeridas (fijo en 12)
                            $total_bitacoras_requeridas = 12;
                            
                            // 1. Contar bitácoras completadas (estado 'completada')
                            $sql_completadas = "SELECT COUNT(*) as total 
                                               FROM bitacoras 
                                               WHERE aprendiz_id = ? AND estado = 'completada'";
                            $stmt_completadas = $conn->prepare($sql_completadas);
                            $stmt_completadas->bind_param("i", $id_usuario_actual);
                            $stmt_completadas->execute();
                            $result_completadas = $stmt_completadas->get_result();
                            $total_bitacoras_completadas = $result_completadas->fetch_assoc()['total'];
                            $stmt_completadas->close();
                            
                            // 2. Contar bitácoras aprobadas (estado 'aprobada')
                            $sql_aprobadas = "SELECT COUNT(*) as total 
                                             FROM bitacoras 
                                             WHERE aprendiz_id = ? AND estado = 'aprobada'";
                            $stmt_aprobadas = $conn->prepare($sql_aprobadas);
                            $stmt_aprobadas->bind_param("i", $id_usuario_actual);
                            $stmt_aprobadas->execute();
                            $result_aprobadas = $stmt_aprobadas->get_result();
                            $total_bitacoras_aprobadas = $result_aprobadas->fetch_assoc()['total'];
                            $stmt_aprobadas->close();
                            
                            // 3. Contar bitácoras pendientes (estado 'borrador' o 'completada' pero sin aprobar)
                            $sql_pendientes = "SELECT COUNT(*) as total 
                                              FROM bitacoras 
                                              WHERE aprendiz_id = ? AND estado IN ('borrador', 'completada', 'revisada')";
                            $stmt_pendientes = $conn->prepare($sql_pendientes);
                            $stmt_pendientes->bind_param("i", $id_usuario_actual);
                            $stmt_pendientes->execute();
                            $result_pendientes = $stmt_pendientes->get_result();
                            $total_bitacoras_pendientes = $result_pendientes->fetch_assoc()['total'];
                            $stmt_pendientes->close();
                            
                            // 4. Calcular porcentaje de progreso (bitácoras aprobadas / total requerido)
                            $porcentaje_progreso = $total_bitacoras_requeridas > 0 
                                ? round(($total_bitacoras_aprobadas / $total_bitacoras_requeridas) * 100) 
                                : 0;
                            
                            // 5. Calcular porcentaje faltante
                            $porcentaje_faltante = 100 - $porcentaje_progreso;
                            
                            // 6. Bitácoras faltantes
                            $bitacoras_faltantes = $total_bitacoras_requeridas - $total_bitacoras_aprobadas;
                            $bitacoras_faltantes = $bitacoras_faltantes < 0 ? 0 : $bitacoras_faltantes;
                            ?>
                            
                            <div class="row g-4">
                                <!-- Bitácoras Completadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_completadas; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras Completadas</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras Aprobadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-clipboard-check fa-3x text-success mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_aprobadas; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras Aprobadas</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras Pendientes -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_pendientes; ?></h3>
                                        <p class="text-muted mb-0">Pendientes de Aprobación</p>
                                    </div>
                                </div>
                                
                                <!-- Porcentaje Faltante -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-percentage fa-3x text-info mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $porcentaje_faltante; ?>%</h3>
                                        <p class="text-muted mb-0">Por Completar</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <!-- Progreso General -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-chart-line me-2"></i>
                                            Progreso General
                                        </h6>
                                        <div class="progress mb-2" style="height: 25px;">
                                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $porcentaje_progreso; ?>%;">
                                                <?php echo $porcentaje_progreso; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $total_bitacoras_aprobadas; ?> de <?php echo $total_bitacoras_requeridas; ?> bitácoras aprobadas
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Resumen de Estado -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Resumen de Estado
                                        </h6>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><i class="fas fa-check-circle text-success me-2"></i>Aprobadas</span>
                                            <strong class="text-success"><?php echo $total_bitacoras_aprobadas; ?></strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><i class="fas fa-clock text-warning me-2"></i>Pendientes</span>
                                            <strong class="text-warning"><?php echo $total_bitacoras_pendientes; ?></strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-times-circle text-danger me-2"></i>Faltantes</span>
                                            <strong class="text-danger"><?php echo $bitacoras_faltantes; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php elseif (strtolower($datos_usuario['rol']) === 'instructor'): ?>
                            
                            <!-- Estadísticas para Instructor -->
                            <?php
                            // ==================== CONSULTAS PARA ESTADÍSTICAS DEL INSTRUCTOR ====================
                            
                            // Obtener el ID del instructor actual
                            $id_instructor_actual = $id_usuario_actual;
                            
                            // 1. Contar fichas asignadas al instructor
                            if ($stmt_fichas = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id = ? AND ficha_id IS NOT NULL")) {
                                $stmt_fichas->bind_param("i", $id_instructor_actual);
                                $stmt_fichas->execute();
                                $res_fichas = $stmt_fichas->get_result();
                                $row_fichas = $res_fichas ? $res_fichas->fetch_assoc() : ["total" => 0];
                                $total_fichas_asignadas = (int)($row_fichas['total'] ?? 0);
                                $stmt_fichas->close();
                            } else {
                                $total_fichas_asignadas = 0;
                            }
                            
                            // 2. Contar aprendices a cargo (aprendices activos en fichas donde el instructor está asignado)
                            $sql_aprendices_cargo = "SELECT COUNT(DISTINCT u.id) as total 
                                                      FROM usuarios u
                                                      INNER JOIN fichas f ON u.ficha_id = f.id
                                                      WHERE u.rol = 'aprendiz' AND u.estado = 'activo'";
                            $result_aprendices_cargo = $conn->query($sql_aprendices_cargo);
                            $total_aprendices_cargo = $result_aprendices_cargo->fetch_assoc()['total'];
                            
                            // 3. Contar bitácoras por revisar (estado 'completada' o 'borrador')
                            $sql_bitacoras_revisar = "SELECT COUNT(*) as total 
                                                       FROM bitacoras 
                                                       WHERE estado IN ('completada', 'borrador')";
                            $result_bitacoras_revisar = $conn->query($sql_bitacoras_revisar);
                            $total_bitacoras_revisar = $result_bitacoras_revisar->fetch_assoc()['total'];
                            
                            // 4. Contar bitácoras revisadas (estado 'revisada' o 'aprobada')
                            $sql_bitacoras_revisadas = "SELECT COUNT(*) as total 
                                                         FROM bitacoras 
                                                         WHERE estado IN ('revisada', 'aprobada')";
                            $result_bitacoras_revisadas = $conn->query($sql_bitacoras_revisadas);
                            $total_bitacoras_revisadas = $result_bitacoras_revisadas->fetch_assoc()['total'];
                            
                            // ==================== PROMEDIO DE BITÁCORAS POR FICHA ====================
                            
                            // Obtener estadísticas detalladas por ficha
                            $sql_fichas_detalle = "SELECT 
                                                    f.id,
                                                    f.codigo_ficha,
                                                    f.programa,
                                                    COUNT(DISTINCT u.id) as total_aprendices,
                                                    COUNT(DISTINCT b.id) as total_bitacoras,
                                                    SUM(CASE WHEN b.estado IN ('revisada', 'aprobada') THEN 1 ELSE 0 END) as bitacoras_completadas,
                                                    SUM(CASE WHEN b.estado IN ('borrador', 'completada') THEN 1 ELSE 0 END) as bitacoras_pendientes
                                                FROM fichas f
                                                LEFT JOIN usuarios u ON f.id = u.ficha_id AND u.rol = 'aprendiz' AND u.estado = 'activo'
                                                LEFT JOIN bitacoras b ON u.id = b.aprendiz_id
                                                GROUP BY f.id, f.codigo_ficha, f.programa
                                                HAVING total_aprendices > 0
                                                ORDER BY f.codigo_ficha ASC";
                            $result_fichas_detalle = $conn->query($sql_fichas_detalle);
                            ?>
                            
                            <div class="row g-4">
                                <!-- Fichas Asignadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_fichas_asignadas; ?></h3>
                                        <p class="text-muted mb-0">Fichas Asignadas</p>
                                    </div>
                                </div>
                                
                                <!-- Aprendices a Cargo -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-user-graduate fa-3x text-success mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_aprendices_cargo; ?></h3>
                                        <p class="text-muted mb-0">Aprendices a Cargo</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras por Revisar -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <?php if ($total_bitacoras_revisar > 0): ?>
                                            <span class="badge bg-warning position-absolute top-0 end-0 m-2">
                                                <?php echo $total_bitacoras_revisar; ?>
                                            </span>
                                        <?php endif; ?>
                                        <i class="fas fa-clipboard-list fa-3x text-warning mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_revisar; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras por Revisar</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras Revisadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-check-double fa-3x text-info mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_revisadas; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras Revisadas</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-md-12">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-chart-bar text-primary me-2"></i>
                                            Promedio de Bitácoras por Ficha
                                        </h6>
                                        
                                        <?php if ($result_fichas_detalle && $result_fichas_detalle->num_rows > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><i class="fas fa-folder me-2"></i>Ficha</th>
                                                            <th class="text-center"><i class="fas fa-users me-2"></i>Aprendices</th>
                                                            <th class="text-center"><i class="fas fa-file-alt me-2"></i>Total Bitácoras</th>
                                                            <th class="text-center"><i class="fas fa-check-circle me-2"></i>Completadas</th>
                                                            <th class="text-center"><i class="fas fa-clock me-2"></i>Pendientes</th>
                                                            <th class="text-center"><i class="fas fa-chart-line me-2"></i>Promedio</th>
                                                            <th class="text-center"><i class="fas fa-flag me-2"></i>Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        while($ficha = $result_fichas_detalle->fetch_assoc()): 
                                                            $total_bitacoras = $ficha['total_bitacoras'];
                                                            $total_aprendices = $ficha['total_aprendices'];
                                                            $bitacoras_completadas = $ficha['bitacoras_completadas'];
                                                            $bitacoras_pendientes = $ficha['bitacoras_pendientes'];
                                                            
                                                            // Calcular promedio de bitácoras por aprendiz
                                                            $promedio = $total_aprendices > 0 ? round($total_bitacoras / $total_aprendices, 1) : 0;
                                                            
                                                            // Calcular porcentaje de completadas
                                                            $porcentaje_completadas = $total_bitacoras > 0 ? round(($bitacoras_completadas / $total_bitacoras) * 100) : 0;
                                                            
                                                            // Determinar estado según porcentaje
                                                            if ($porcentaje_completadas >= 80) {
                                                                $estado_clase = 'bg-success-subtle text-success';
                                                                $estado_texto = 'Excelente';
                                                                $estado_icono = 'fa-star';
                                                            } elseif ($porcentaje_completadas >= 60) {
                                                                $estado_clase = 'bg-info-subtle text-info';
                                                                $estado_texto = 'Bueno';
                                                                $estado_icono = 'fa-thumbs-up';
                                                            } elseif ($porcentaje_completadas >= 40) {
                                                                $estado_clase = 'bg-warning-subtle text-warning';
                                                                $estado_texto = 'Regular';
                                                                $estado_icono = 'fa-exclamation-triangle';
                                                            } else {
                                                                $estado_clase = 'bg-danger-subtle text-danger';
                                                                $estado_texto = 'Necesita Atención';
                                                                $estado_icono = 'fa-times-circle';
                                                            }
                                                            
                                                            // Badge para promedio
                                                            if ($promedio >= 10) {
                                                                $badge_promedio = 'bg-success';
                                                            } elseif ($promedio >= 7) {
                                                                $badge_promedio = 'bg-info';
                                                            } elseif ($promedio >= 4) {
                                                                $badge_promedio = 'bg-warning';
                                                            } else {
                                                                $badge_promedio = 'bg-danger';
                                                            }
                                                        ?>
                                                            <tr>
                                                                <td>
                                                                    <strong class="text-primary">
                                                                        <?php echo htmlspecialchars($ficha['codigo_ficha']); ?>
                                                                    </strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php echo htmlspecialchars(substr($ficha['programa'], 0, 30)) . (strlen($ficha['programa']) > 30 ? '...' : ''); ?>
                                                                    </small>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-primary rounded-pill">
                                                                        <?php echo $total_aprendices; ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <strong><?php echo $total_bitacoras; ?></strong>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="text-success">
                                                                        <i class="fas fa-check-circle me-1"></i>
                                                                        <?php echo $bitacoras_completadas; ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="text-warning">
                                                                        <i class="fas fa-clock me-1"></i>
                                                                        <?php echo $bitacoras_pendientes; ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?php echo $badge_promedio; ?> rounded-pill">
                                                                        <?php echo $promedio; ?> por aprendiz
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?php echo $estado_clase; ?>">
                                                                        <i class="fas <?php echo $estado_icono; ?> me-1"></i>
                                                                        <?php echo $estado_texto; ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted"><?php echo $porcentaje_completadas; ?>% completadas</small>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <!-- Resumen general -->
                                            <div class="row mt-4">
                                                <div class="col-md-12">
                                                    <div class="alert alert-info border-0">
                                                        <div class="row text-center">
                                                            <div class="col-md-3">
                                                                <i class="fas fa-folder fa-2x text-primary mb-2"></i>
                                                                <h5 class="mb-0"><?php echo $total_fichas_asignadas; ?></h5>
                                                                <small>Fichas Totales</small>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <i class="fas fa-users fa-2x text-success mb-2"></i>
                                                                <h5 class="mb-0"><?php echo $total_aprendices_cargo; ?></h5>
                                                                <small>Aprendices Totales</small>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                                                                <h5 class="mb-0"><?php echo ($total_bitacoras_revisadas + $total_bitacoras_revisar); ?></h5>
                                                                <small>Bitácoras Totales</small>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                                                                <h5 class="mb-0">
                                                                    <?php 
                                                                    $promedio_general = $total_aprendices_cargo > 0 
                                                                        ? round(($total_bitacoras_revisadas + $total_bitacoras_revisar) / $total_aprendices_cargo, 1) 
                                                                        : 0;
                                                                    echo $promedio_general;
                                                                    ?>
                                                                </h5>
                                                                <small>Promedio General</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                        <?php else: ?>
                                            <div class="alert alert-info text-center mb-0">
                                                <i class="fas fa-info-circle fa-3x mb-3 d-block"></i>
                                                <h5>No hay fichas asignadas</h5>
                                                <p class="mb-0">Aún no tienes fichas con aprendices asignados para mostrar estadísticas.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php elseif (strtolower($datos_usuario['rol']) === 'administrador'): ?>
                            
                            <!-- Estadísticas para Administrador -->
                            <?php
                            // ==================== CONSULTAS PARA ESTADÍSTICAS DEL ADMINISTRADOR ====================
                            
                            // 1. Contar fichas creadas
                            $sql_fichas_count = "SELECT COUNT(*) as total FROM fichas";
                            $result_fichas_count = $conn->query($sql_fichas_count);
                            $total_fichas = $result_fichas_count->fetch_assoc()['total'];
                            
                            // 2. Contar usuarios registrados (todos los usuarios activos)
                            $sql_usuarios_count = "SELECT COUNT(*) as total FROM usuarios WHERE LOWER(TRIM(estado)) = 'activo'";
                            $result_usuarios_count = $conn->query($sql_usuarios_count);
                            $total_usuarios = $result_usuarios_count->fetch_assoc()['total'];
                            
                            // 3. Contar solicitudes pendientes (usuarios con estado pendiente y rol instructor o administrador)
                            $sql_pendientes_count = "SELECT COUNT(*) as total FROM usuarios 
                                                      WHERE (
                                                        estado IS NULL OR TRIM(estado) = '' OR LOWER(TRIM(estado)) = 'pendiente'
                                                      )
                                                      AND LOWER(TRIM(rol)) IN ('instructor', 'administrador')";
                            $result_pendientes_count = $conn->query($sql_pendientes_count);
                            $total_pendientes = $result_pendientes_count->fetch_assoc()['total'];
                            
                            // 4. Contar total de administradores (activos)
                            $sql_admins_count = "SELECT COUNT(*) as total FROM usuarios 
                                                 WHERE LOWER(TRIM(rol)) = 'administrador' AND LOWER(TRIM(estado)) = 'activo'";
                            $result_admins_count = $conn->query($sql_admins_count);
                            $total_administradores = $result_admins_count->fetch_assoc()['total'];
                            
                            // ==================== DISTRIBUCIÓN DE USUARIOS ====================
                            
                            // Contar aprendices activos
                            $sql_aprendices = "SELECT COUNT(*) as total FROM usuarios 
                                               WHERE LOWER(TRIM(rol)) = 'aprendiz' AND LOWER(TRIM(estado)) = 'activo'";
                            $result_aprendices = $conn->query($sql_aprendices);
                            $total_aprendices = $result_aprendices->fetch_assoc()['total'];
                            
                            // Contar instructores activos
                            $sql_instructores = "SELECT COUNT(*) as total FROM usuarios 
                                                 WHERE LOWER(TRIM(rol)) = 'instructor' AND LOWER(TRIM(estado)) = 'activo'";
                            $result_instructores = $conn->query($sql_instructores);
                            $total_instructores = $result_instructores->fetch_assoc()['total'];
                            
                            // Calcular porcentajes
                            $total_usuarios_distribucion = $total_aprendices + $total_instructores + $total_administradores;
                            
                            if ($total_usuarios_distribucion > 0) {
                                $porcentaje_aprendices = round(($total_aprendices / $total_usuarios_distribucion) * 100);
                                $porcentaje_instructores = round(($total_instructores / $total_usuarios_distribucion) * 100);
                                $porcentaje_administradores = round(($total_administradores / $total_usuarios_distribucion) * 100);
                            } else {
                                $porcentaje_aprendices = 0;
                                $porcentaje_instructores = 0;
                                $porcentaje_administradores = 0;
                            }
                            ?>
                            
                            <div class="row g-4">
                                <!-- Fichas Creadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-folder fa-3x text-primary mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_fichas; ?></h3>
                                        <p class="text-muted mb-0">Fichas Creadas</p>
                                    </div>
                                </div>
                                
                                <!-- Usuarios Registrados -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-users-cog fa-3x text-success mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_usuarios; ?></h3>
                                        <p class="text-muted mb-0">Usuarios Registrados</p>
                                    </div>
                                </div>
                                
                                <!-- Solicitudes Pendientes -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center position-relative">
                                        <?php if ($total_pendientes > 0): ?>
                                            <span class="badge bg-danger position-absolute top-0 end-0 m-2">Nuevo</span>
                                        <?php endif; ?>
                                        <i class="fas fa-user-clock fa-3x text-danger mb-3"></i>
                                        <h3 class="fw-bold mb-2 text-danger"><?php echo $total_pendientes; ?></h3>
                                        <p class="text-muted mb-0">Solicitudes Pendientes</p>
                                    </div>
                                </div>
                                
                                <!-- Total de Administradores -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-user-shield fa-3x text-info mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_administradores; ?></h3>
                                        <p class="text-muted mb-0">Total de Administradores</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <!-- Solicitudes Pendientes de Aprobación -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                            Solicitudes Pendientes de Aprobación
                                        </h6>
                                        <?php if ($total_pendientes > 0): ?>
                                            <div class="alert alert-warning mb-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Tienes <strong><?php echo $total_pendientes; ?> solicitud<?php echo $total_pendientes > 1 ? 'es' : ''; ?></strong> esperando tu revisión
                                            </div>
                                            <button class="btn btn-danger w-100" onclick="mostrarSeccion('validacion');">
                                                <i class="fas fa-tasks me-2"></i> Ver Solicitudes Pendientes
                                            </button>
                                        <?php else: ?>
                                            <div class="alert alert-success mb-3">
                                                <i class="fas fa-check-circle me-2"></i>
                                                No hay solicitudes pendientes en este momento
                                            </div>
                                            <button class="btn btn-secondary w-100" disabled>
                                                <i class="fas fa-check me-2"></i> Sin Solicitudes Pendientes
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Distribución de Usuarios -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-chart-pie text-primary me-2"></i>
                                            Distribución de Usuarios
                                        </h6>
                                        
                                        <?php if ($total_usuarios_distribucion > 0): ?>
                                            <!-- Aprendices -->
                                            <div class="d-flex justify-content-between mb-2">
                                                <span><i class="fas fa-user-graduate me-2 text-success"></i>Aprendices</span>
                                                <strong><?php echo $total_aprendices; ?> (<?php echo $porcentaje_aprendices; ?>%)</strong>
                                            </div>
                                            <div class="progress mb-3" style="height: 10px;">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo $porcentaje_aprendices; ?>%;"
                                                     role="progressbar" 
                                                     aria-valuenow="<?php echo $porcentaje_aprendices; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            
                                            <!-- Instructores -->
                                            <div class="d-flex justify-content-between mb-2">
                                                <span><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Instructores</span>
                                                <strong><?php echo $total_instructores; ?> (<?php echo $porcentaje_instructores; ?>%)</strong>
                                            </div>
                                            <div class="progress mb-3" style="height: 10px;">
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?php echo $porcentaje_instructores; ?>%;"
                                                     role="progressbar" 
                                                     aria-valuenow="<?php echo $porcentaje_instructores; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            
                                            <!-- Administradores -->
                                            <div class="d-flex justify-content-between mb-2">
                                                <span><i class="fas fa-user-shield me-2 text-warning"></i>Administradores</span>
                                                <strong><?php echo $total_administradores; ?> (<?php echo $porcentaje_administradores; ?>%)</strong>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-warning" 
                                                     style="width: <?php echo $porcentaje_administradores; ?>%;"
                                                     role="progressbar" 
                                                     aria-valuenow="<?php echo $porcentaje_administradores; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            
                                            <!-- Total -->
                                            <div class="mt-3 pt-3 border-top">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-bold"><i class="fas fa-users me-2"></i>Total</span>
                                                    <strong class="text-primary"><?php echo $total_usuarios_distribucion; ?> usuarios</strong>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No hay usuarios registrados en el sistema
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php endif; ?>
                        </div>

                        <!-- Tab 3: Actividad Reciente -->
                        <div class="tab-pane fade" id="actividad" role="tabpanel">
                            <h5 class="fw-bold text-success mb-4">
                                <i class="fas fa-history me-2"></i> Historial de Actividad
                            </h5>

                            <?php
                            function mapActividadIconoClase($tipo) {
                                switch ($tipo) {
                                    case 'admin_login':
                                    case 'instructor_login': return ['bg' => 'bg-primary', 'icon' => 'fas fa-sign-in-alt text-primary'];
                                    case 'login': return ['bg' => 'bg-primary', 'icon' => 'fas fa-sign-in-alt text-primary'];
                                    case 'logout': return ['bg' => 'bg-secondary', 'icon' => 'fas fa-sign-out-alt text-secondary'];
                                    case 'bitacora_creada': return ['bg' => 'bg-success', 'icon' => 'fas fa-upload text-success'];
                                    case 'bitacora_aprobada': return ['bg' => 'bg-success', 'icon' => 'fas fa-check-circle text-success'];
                                    case 'bitacora_rechazada': return ['bg' => 'bg-warning', 'icon' => 'fas fa-times-circle text-warning'];
                                    case 'perfil_actualizado': return ['bg' => 'bg-info', 'icon' => 'fas fa-edit text-info'];
                                    case 'foto_actualizada': return ['bg' => 'bg-info', 'icon' => 'fas fa-camera text-info'];
                                    case 'contrasena_actualizada': return ['bg' => 'bg-warning', 'icon' => 'fas fa-key text-warning'];
                                    case 'ficha_creada': return ['bg' => 'bg-success', 'icon' => 'fas fa-folder-plus text-success'];
                                    case 'ficha_eliminada': return ['bg' => 'bg-danger', 'icon' => 'fas fa-folder-minus text-danger'];
                                    case 'usuario_aprobado': return ['bg' => 'bg-success', 'icon' => 'fas fa-user-check text-success'];
                                    case 'usuario_rechazado': return ['bg' => 'bg-warning', 'icon' => 'fas fa-user-times text-warning'];
                                    case 'aprendiz_eliminado': return ['bg' => 'bg-danger', 'icon' => 'fas fa-user-minus text-danger'];
                                    default: return ['bg' => 'bg-light', 'icon' => 'fas fa-info-circle text-muted'];
                                }
                            }
                            ?>

                            <div class="timeline">
                                <?php if (!empty($actividades)): ?>
                                    <?php foreach ($actividades as $act): 
                                        $m = mapActividadIconoClase(strtolower($act['tipo']));
                                        $fechaFmt = date('d/m/Y H:i', strtotime($act['fecha']));
                                        $titulo = htmlspecialchars($act['titulo'] ?? ucfirst($act['tipo']));
                                        $desc = htmlspecialchars($act['descripcion'] ?? '');
                                    ?>
                                    <div class="timeline-item" id="actividad-<?= $act['id']; ?>" data-actividad-id="<?= $act['id']; ?>">
                                        <div class="timeline-marker <?= $m['bg']; ?>"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="fw-bold mb-0">
                                                    <i class="<?= $m['icon']; ?> me-2"></i>
                                                    <?= $titulo; ?>
                                                </h6>
                                                <div class="d-flex align-items-center gap-2">
                                                    <small class="text-muted me-2 mb-0"><?= $fechaFmt; ?></small>
                                                    <button type="button" class="btn-close" aria-label="Eliminar actividad" title="Eliminar actividad" onclick="eliminarActividad(<?= $act['id']; ?>)"></button>
                                                </div>
                                            </div>
                                            <?php if (!empty($desc)): ?>
                                                <p class="mb-0 text-muted"><?= $desc; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-bell-slash fa-2x d-block mb-2"></i>
                                        <small>No hay actividad reciente.</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="text-center mt-4">
                                <button class="btn btn-outline-success">
                                    <i class="fas fa-history me-2"></i>
                                    Ver historial completo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



    



















































































    










    
    


    <div id="fichas" style="display:none;">
    <h2 class="text-center mb-3 fw-bold" style="color:#4caf50;">
        <i class="fas fa-folder-open me-2"></i>Fichas & Bitácoras
    </h2>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow border-0" style="border-radius:12px;overflow:hidden;">
                <div class="card-header text-white text-center py-3" style="background:linear-gradient(135deg,#66bb6a 0%,#388e3c 100%);">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-folder me-2"></i>Gestión de Fichas</h5>
                </div>

                <div class="card-body p-3 ficha-body">
                    <?php if(isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mb-3" role="alert" style="border-radius:8px;border-left:4px solid #4caf50;">
                            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

                    <p class="text-center mb-3" style="color:#388e3c;font-size:1rem;">
                        <i class="fas fa-info-circle me-2"></i>Seleccione una ficha para ver los aprendices y sus bitácoras.
                    </p>

                    <?php $sql_fichas="SELECT id,codigo_ficha,programa FROM fichas ORDER BY codigo_ficha ASC"; 
                    $result_fichas=$conn->query($sql_fichas); ?>

                    <form method="POST" action="ficha_dashboard.php" class="text-center">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9">
                                <label for="ficha" class="form-label fw-bold" style="color:#4caf50;">
                                    <i class="fas fa-folder me-2"></i>Seleccionar Ficha:
                                </label>
                                <select name="ficha" id="ficha" class="form-select" required style="border:2px solid #4caf50;border-radius:8px;">
                                    <option value="">-- Seleccione una ficha --</option>
                                    <?php while($ficha=$result_fichas->fetch_assoc()): ?>
                                        <option value="<?= $ficha['id']; ?>">
                                            <?= $ficha['codigo_ficha']." - ".$ficha['programa']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn w-100 text-white fw-bold" style="background:linear-gradient(135deg,#66bb6a 0%,#388e3c 100%);border:none;border-radius:8px;padding:10px;">
                                    <i class="fas fa-sign-in-alt me-1"></i>Ingresar
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if($datos_usuario['rol']=='administrador'): ?>
                    <div class="mt-4">
                        <div class="card border-0 shadow-sm ficha-card">
                            <div class="card-header text-white text-center py-2" style="background:linear-gradient(135deg,#388e3c 0%,#2e7d32 100%);border-radius:8px 8px 0 0;">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-user-shield me-2"></i>Gestión de Fichas (Administradores)</h6>
                            </div>

                            <div class="card-body p-3">
                                <form method="POST" action="agregar_ficha.php" class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" style="color:#388e3c;"><i class="fas fa-barcode me-1"></i>Código de Ficha:</label>
                                            <input type="text" name="codigo_ficha" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" style="color:#388e3c;"><i class="fas fa-graduation-cap me-1"></i>Programa:</label>
                                            <input type="text" name="programa" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" style="color:#388e3c;"><i class="fas fa-calendar-alt me-1"></i>Inicio:</label>
                                            <input type="date" name="fecha_inicio" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" style="color:#388e3c;"><i class="fas fa-calendar-check me-1"></i>Fin:</label>
                                            <input type="date" name="fecha_fin" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn text-white fw-bold" style="background:linear-gradient(135deg,#66bb6a 0%,#388e3c 100%);border:none;border-radius:8px;padding:10px;">
                                            <i class="fas fa-plus-circle me-1"></i>Guardar
                                        </button>
                                    </div>
                                </form>

                                <hr style="border-top:2px solid #4caf50;margin:1rem 0;">

                                <h6 class="text-center mb-3 fw-bold" style="color:#4caf50;">
                                    <i class="fas fa-list me-2"></i>Fichas Existentes
                                </h6>

                                <div class="table-responsive" style="border-radius:8px;overflow:hidden;">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr class="text-white text-center" style="background:linear-gradient(135deg,#66bb6a 0%,#388e3c 100%);">
                                                <th class="py-2"><i class="fas fa-barcode me-1"></i>Código</th>
                                                <th class="py-2"><i class="fas fa-book me-1"></i>Programa</th>
                                                <th class="py-2"><i class="fas fa-cog me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $result_fichas->data_seek(0);
                                            while($ficha=$result_fichas->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-bold" style="color:#388e3c;"><?= htmlspecialchars($ficha['codigo_ficha']); ?></td>
                                                <td><?= htmlspecialchars($ficha['programa']); ?></td>
                                                <td class="text-center">
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="delete_ficha_id" value="<?= $ficha['id']; ?>">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteFicha(this)" style="border-radius:6px;padding:5px 12px;">
                                                            <i class="fas fa-trash-alt me-1"></i>Borrar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ======= MODO CLARO ======= */
body:not(.bg-dark) .ficha-body { background:#f8f9fa; }
body:not(.bg-dark) .ficha-card, body:not(.bg-dark) .ficha-tabla { background:white; }

/* ======= MODO OSCURO ======= */
.bg-dark .ficha-body { background:#121212!important; color:#e0e0e0; }
.bg-dark .ficha-card, .bg-dark .ficha-tabla { background:#1e1e1e!important; color:#e0e0e0; }
.bg-dark .table-hover tbody tr:hover { background:rgba(76,175,80,0.15)!important; }
.bg-dark .form-control, .bg-dark select.form-select { background:#222!important; color:#e0e0e0; border-color:#333!important; }

/* Ajustes específicos de tabla en modo oscuro (Fichas existentes) */
.bg-dark .ficha-body .table { background-color:#1e1e1e; color:#e0e0e0; border-color:#444; }
.bg-dark .ficha-body .table thead tr { background-color:#2c2c2c !important; background-image:none !important; }
.bg-dark .ficha-body .table thead th { color:#e0e0e0; border-color:#444; }
.bg-dark .ficha-body .table tbody tr > * { background-color:#1e1e1e; color:#e0e0e0; border-color:#333; }

#ficha:focus, .form-control:focus {
    border-color:#4caf50!important;
    box-shadow:0 0 0 0.2rem rgba(76,175,80,0.25)!important;
}

.btn:hover {
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(76,175,80,0.3);
}
.btn-danger:hover { box-shadow:0 4px 12px rgba(220,53,69,0.3); }
</style>

























   
<!-- ======================= NUEVA SECCIÓN DE VALIDACIÓN DE USUARIOS ======================= -->
<?php if ($datos_usuario['rol'] === 'administrador'): ?>
<div id="validacion" style="display:none;">
    <div class="validation-header fade-in-up" style="background: #388E3C; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h1><i class="fas fa-user-check me-3"></i>Validación de Usuarios</h1>
        <p>Gestiona las solicitudes de registro de instructores y administradores</p>
    </div>

    <div class="container-fluid">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4><div class="icon"><i class="fas fa-users"></i></div>Usuarios Pendientes de Aprobación</h4>
                    </div>
                    <div class="config-card-body">
                        <?php
                        $sql_pendientes = "SELECT u.*, f.codigo_ficha, f.programa 
                                           FROM usuarios u 
                                           LEFT JOIN fichas f ON u.ficha_id = f.id 
                                           WHERE (
                                               u.estado IS NULL OR TRIM(u.estado) = '' OR LOWER(TRIM(u.estado)) = 'pendiente'
                                           )
                                           AND LOWER(TRIM(u.rol)) IN ('instructor', 'administrador') 
                                           ORDER BY u.fecha_registro DESC";
                        $result_pendientes = $conn->query($sql_pendientes);
                        if ($result_pendientes === false) {
                            echo '<div class="alert alert-danger">Error al cargar pendientes: '.htmlspecialchars($conn->error).'</div>';
                        }
                        if ($result_pendientes && $result_pendientes->num_rows > 0):
                        ?>
                            <?php while($usuario = $result_pendientes->fetch_assoc()): ?>
                                <div class="validation-card fade-in-left mb-4 p-3" style="border-radius: 10px; background: var(--bs-card-bg); box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-2"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h5>
                                            <span class="user-status status-pendiente"><i class="fas fa-clock me-1"></i>Pendiente</span>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><i class="fas fa-calendar me-1"></i>Registrado: <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></small>
                                        </div>
                                    </div>

                                    <div class="user-info mb-3">
                                        <div class="info-item"><strong>Rol:</strong> <span class="badge bg-primary ms-2"><?php echo ucfirst($usuario['rol']); ?></span></div>
                                        <div class="info-item"><strong>Documento:</strong> <?php echo htmlspecialchars($usuario['tipo_documento'] . ' - ' . $usuario['numero_documento']); ?></div>
                                        <div class="info-item"><strong>Email:</strong> <?php echo htmlspecialchars($usuario['correo']); ?></div>
                                        <div class="info-item"><strong>Género:</strong> <?php echo htmlspecialchars(ucfirst($usuario['genero'])); ?></div>
                                        <?php if ($usuario['codigo_ficha']): ?>
                                            <div class="info-item"><strong>Ficha Actual:</strong> <?php echo htmlspecialchars($usuario['codigo_ficha'] . ' - ' . $usuario['programa']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST" action="" class="validation-actions d-flex flex-wrap gap-2">
                                        <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">

                                        <?php if (in_array(strtolower(trim($usuario['rol'])), ['instructor'])): ?>
                                            <div class="form-group me-3 flex-grow-1">
                                                <label for="ficha_<?php echo $usuario['id']; ?>" class="form-label-enhanced">
                                                    <i class="fas fa-folder me-2"></i>Asignar Ficha:
                                                </label>
                                                <select name="ficha_asignada" id="ficha_<?php echo $usuario['id']; ?>" class="form-select ficha-select">
                                                    <option value="">Sin ficha asignada</option>
                                                    <?php
                                                    $sql_fichas_disponibles = "SELECT id, codigo_ficha, programa FROM fichas ORDER BY codigo_ficha ASC";
                                                    $result_fichas_disponibles = $conn->query($sql_fichas_disponibles);
                                                    while($ficha = $result_fichas_disponibles->fetch_assoc()):
                                                    ?>
                                                        <option value="<?php echo $ficha['id']; ?>" <?php echo ($usuario['ficha_id'] == $ficha['id'] ? 'selected' : ''); ?>>
                                                            <?php echo htmlspecialchars($ficha['codigo_ficha'] . ' - ' . $ficha['programa']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="submit" name="validar_usuario" value="aprobar" class="btn btn-aprobar btn-validar" data-accion="aprobar" data-usuario-id="<?php echo $usuario['id']; ?>">
                                                <i class="fas fa-check me-2"></i>Aprobar
                                            </button>
                                            <button type="submit" name="validar_usuario" value="rechazar" class="btn btn-rechazar btn-validar" data-accion="rechazar" data-usuario-id="<?php echo $usuario['id']; ?>">
                                                <i class="fas fa-times me-2"></i>Rechazar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                <h4>¡No hay usuarios pendientes!</h4>
                                <p class="text-muted">Todas las solicitudes han sido procesadas.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <div class="row">
                <!-- Usuarios Aprobados -->
                <div class="col-lg-6">
                    <div class="config-card fade-in-left">
                        <div class="config-card-header">
                            <h4><div class="icon" style="background: linear-gradient(135deg, #388E3C, #2E7D32);"><i class="fas fa-user-check"></i></div>Usuarios Aprobados</h4>
                        </div>
                        <div class="config-card-body" id="lista-aprobados">
                            <?php
                            $sql_aprobados = "SELECT u.*, f.codigo_ficha, f.id AS ficha_id_actual 
                                               FROM usuarios u 
                                               LEFT JOIN fichas f ON u.ficha_id = f.id 
                                               WHERE LOWER(TRIM(u.estado)) = 'activo' 
                                               AND LOWER(TRIM(u.rol)) IN ('instructor', 'administrador') 
                                               ORDER BY u.fecha_registro DESC LIMIT 5";
                            $result_aprobados = $conn->query($sql_aprobados);
                            if ($result_aprobados === false) {
                                echo '<div class="alert alert-danger">Error al cargar aprobados: '.htmlspecialchars($conn->error).'</div>';
                            }
                            if ($result_aprobados && $result_aprobados->num_rows > 0):
                            ?>
                                <?php while($usuario = $result_aprobados->fetch_assoc()): ?>
                                    <div class="user-item mb-3 p-2 rounded" data-id="<?php echo $usuario['id']; ?>" style="background: var(--bs-card-bg); box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                                <br><small><?php echo ucfirst($usuario['rol']); ?><?php if ($usuario['codigo_ficha']): ?> - Ficha: <?php echo htmlspecialchars($usuario['codigo_ficha']); ?><?php endif; ?></small>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <button class="btn btn-sm btn-outline-danger eliminar-usuario"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                        <?php if (strtolower(trim($datos_usuario['rol'])) === 'administrador' && strtolower(trim($usuario['rol'])) === 'instructor'): ?>
                                            <form method="POST" action="" class="mt-2 d-flex flex-wrap align-items-end gap-2">
                                                <input type="hidden" name="actualizar_ficha_instructor" value="1">
                                                <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                                                <div class="flex-grow-1" style="min-width:240px;">
                                                    <label class="form-label mb-1">Cambiar ficha</label>
                                                    <select name="nueva_ficha" class="form-select form-select-sm">
                                                        <option value="">Sin ficha</option>
                                                        <?php 
                                                        $sql_fichas_disponibles = "SELECT id, codigo_ficha, programa FROM fichas ORDER BY codigo_ficha ASC";
                                                        $result_fichas_disponibles = $conn->query($sql_fichas_disponibles);
                                                        if ($result_fichas_disponibles) {
                                                            while($f = $result_fichas_disponibles->fetch_assoc()): ?>
                                                                <option value="<?php echo (int)$f['id']; ?>" <?php echo ((int)$usuario['ficha_id_actual'] === (int)$f['id'] ? 'selected' : ''); ?>>
                                                                    <?php echo htmlspecialchars($f['codigo_ficha'] . ' - ' . $f['programa']); ?>
                                                                </option>
                                                            <?php endwhile; 
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="confirmQuitarFicha(this)"><i class="fas fa-ban me-1"></i>Quitar</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">No hay usuarios aprobados aún.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Usuarios Rechazados -->
                <div class="col-lg-6">
                    <div class="config-card fade-in-right">
                        <div class="config-card-header">
                            <h4><div class="icon" style="background: linear-gradient(135deg, #f44336, #c62828);"><i class="fas fa-user-times"></i></div>Usuarios Rechazados</h4>
                        </div>
                        <div class="config-card-body" id="lista-rechazados">
                            <?php
                            $sql_rechazados = "SELECT * FROM usuarios WHERE LOWER(TRIM(estado)) = 'rechazado' AND LOWER(TRIM(rol)) IN ('instructor', 'administrador') ORDER BY fecha_registro DESC";
                            $result_rechazados = $conn->query($sql_rechazados);
                            if ($result_rechazados === false) {
                                echo '<div class="alert alert-danger">Error al cargar rechazados: '.htmlspecialchars($conn->error).'</div>';
                            }
                            if ($result_rechazados && $result_rechazados->num_rows > 0):
                            ?>
                                <?php while($usuario = $result_rechazados->fetch_assoc()): ?>
                                    <div class="user-item d-flex justify-content-between align-items-center mb-2 p-2 rounded" data-id="<?php echo $usuario['id']; ?>" style="background: var(--bs-card-bg); box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div>
                                            <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                            <br><small><?php echo ucfirst($usuario['rol']); ?></small>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-danger eliminar-usuario"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">No hay usuarios rechazados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================= SWEETALERT Y SCRIPT ======================= -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Confirmar aprobar/rechazar
    function confirmAction(button, usuarioId, accion) {
        console.log("DEBUG: Entrando a confirmAction con:", accion, usuarioId);
        
        const mensaje = accion === 'aprobar'
            ? '¿Estás seguro de aprobar a este usuario?'
            : '¿Estás seguro de rechazar a este usuario?';
        const icono = accion === 'aprobar' ? 'success' : 'warning';
        const colorBoton = accion === 'aprobar' ? '#4CAF50' : '#e53935';

        Swal.fire({
            title: mensaje,
            icon: icono,
            showCancelButton: true,
            confirmButtonText: accion === 'aprobar' ? 'Sí, aprobar' : 'Sí, rechazar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: colorBoton,
            background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
            color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
        }).then((result) => {
            console.log("DEBUG: Resultado de Swal:", result);
            if (result.isConfirmed) {
                console.log("DEBUG: Confirmación recibida para acción:", accion);
                const form = button.closest('form');
                console.log("DEBUG: Formulario encontrado:", form);
                if (!form) {
                    console.error("DEBUG: No se encontró el formulario");
                    return;
                }
                
                // Eliminar cualquier input hidden existente con el mismo nombre para evitar conflictos
                const existingHidden = form.querySelector('input[name="validar_usuario"][type="hidden"]');
                if (existingHidden) {
                    console.log("DEBUG: Eliminando input hidden existente");
                    existingHidden.remove();
                }
                
                // Crear un nuevo input hidden para asegurar que el valor correcto se envíe
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'validar_usuario';
                hidden.value = accion;
                form.appendChild(hidden);
                
                console.log("DEBUG: Input hidden creado con valor:", accion);
                console.log("DEBUG: Enviando formulario...");
                
                // Enviar el formulario
                form.submit();
            }
        });
        return false;
    }

    // Confirmar quitar ficha a un instructor (Usuarios Aprobados)
    function confirmQuitarFicha(button) {
        const form = button.closest('form');
        if (!form) return;

        Swal.fire({
            title: '¿Quitar ficha al instructor?',
            text: 'Esta acción quitará la ficha asignada. ¿Deseas continuar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
            background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
            color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
        }).then((result) => {
            if (result.isConfirmed) {
                const inputQuitar = document.createElement('input');
                inputQuitar.type = 'hidden';
                inputQuitar.name = 'quitar_ficha';
                inputQuitar.value = '1';
                form.appendChild(inputQuitar);
                form.submit();
            }
        });
    }

    // ====== BORRADO VISUAL CON SWEETALERT Y LOCALSTORAGE ======
    document.querySelectorAll('.eliminar-usuario').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = this.closest('.user-item');
            const lista = item.closest('.config-card-body');
            const usuarioId = item.dataset.id;
            const tipo = lista.id === 'lista-aprobados' ? 'aprobados' : 'rechazados';

            Swal.fire({
                title: '¿Eliminar usuario visualmente?',
                text: 'Este usuario se ocultará de la lista, pero no se eliminará de la base de datos.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#e53935',
                background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
                color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
            }).then((result) => {
                if (result.isConfirmed) {
                    item.remove();
                    // Guardar en localStorage para ocultar permanentemente
                    let ocultos = JSON.parse(localStorage.getItem('usuarios_ocultos_' + tipo)) || [];
                    ocultos.push(usuarioId);
                    localStorage.setItem('usuarios_ocultos_' + tipo, JSON.stringify(ocultos));

                    Swal.fire({
                        icon: 'info',
                        title: 'Usuario eliminado',
                        text: 'El usuario fue eliminado visualmente.',
                        timer: 1800,
                        showConfirmButton: false,
                        background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
                        color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
                    });

                    // Si no hay más usuarios
                    if (!lista.querySelector('.user-item')) {
                        lista.innerHTML = `<p class="text-center text-muted">No hay usuarios ${tipo}.</p>`;
                    }
                }
            });
        });
    });

    // Ocultar usuarios guardados como eliminados
    window.addEventListener('DOMContentLoaded', () => {
        ['aprobados', 'rechazados'].forEach(tipo => {
            const lista = document.getElementById('lista-' + tipo);
            if (!lista) return;
            const ocultos = JSON.parse(localStorage.getItem('usuarios_ocultos_' + tipo)) || [];
            ocultos.forEach(id => {
                const user = lista.querySelector(`[data-id="${id}"]`);
                if (user) user.remove();
            });
            if (!lista.querySelector('.user-item')) {
                lista.innerHTML = `<p class="text-center text-muted">No hay usuarios ${tipo}.</p>`;
            }
        });
    });
    </script>
</div>
<?php endif; ?>
























    <!-- ======================= SECCIÓN DE CONFIGURACIÓN COMPLETAMENTE REDISEÑADA ======================= -->
    <div id="configuracion" class="py-5" style="display: none;">
        <!-- Header principal -->
        <div class="config-header fade-in-up">
            <h1><i class="fas fa-cogs me-3"></i>Configuración de Cuenta</h1>
            <p>Personaliza y gestiona tu cuenta de forma segura y profesional</p>
        </div>

        <div class="container-fluid">
            <div class="row g-4">
                
                <!-- Card 1: Información Personal -->
                <div class="col-lg-6 col-12">
                    <div class="config-card fade-in-left">
                        <div class="config-card-header">
                            <h4>
                                <div class="icon">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                Información Personal
                            </h4>
                        </div>
                        <div class="config-card-body">
                            <form action="actualizar_perfil.php" method="POST" class="form-section">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="nombre" class="form-label form-label-enhanced">
                                            <i class="fas fa-user me-2"></i>Nombre Completo
                                        </label>
                                        <input type="text" 
                                               name="nombre" 
                                               id="nombre" 
                                               class="form-control form-control-enhanced" 
                                               value="<?php echo htmlspecialchars($datos_usuario['nombre'].' '.$datos_usuario['apellido']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-12">
                                        <label for="correo" class="form-label form-label-enhanced">
                                            <i class="fas fa-envelope me-2"></i>Correo Electrónico
                                        </label>
                                        <input type="email" 
                                               name="correo" 
                                               id="correo" 
                                               class="form-control form-control-enhanced" 
                                               value="<?php echo htmlspecialchars($datos_usuario['correo']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fecha_nacimiento" class="form-label form-label-enhanced">
                                            <i class="fas fa-calendar me-2"></i>Fecha de Nacimiento
                                        </label>
                                        <input type="date" 
                                               name="fecha_nacimiento" 
                                               id="fecha_nacimiento" 
                                               class="form-control form-control-enhanced" 
                                               value="<?php echo htmlspecialchars($datos_usuario['fecha_nacimiento']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="genero" class="form-label form-label-enhanced">
                                            <i class="fas fa-venus-mars me-2"></i>Género
                                        </label>
                                        <select name="genero" 
                                                id="genero" 
                                                class="form-select form-control-enhanced" 
                                                required>
                                            <option value="masculino" <?php echo ($datos_usuario['genero']=='masculino'?'selected':''); ?>>Masculino</option>
                                            <option value="femenino" <?php echo ($datos_usuario['genero']=='femenino'?'selected':''); ?>>Femenino</option>
                                            <option value="otro" <?php echo ($datos_usuario['genero']=='otro'?'selected':''); ?>>Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-enhanced btn-primary-enhanced w-100">
                                            <i class="fas fa-save me-2"></i>Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Foto de Perfil -->
                <div class="col-lg-6 col-12">
                    <div class="config-card fade-in-right">
                        <div class="config-card-header">
                            <h4>
                                <div class="icon">
                                    <i class="fas fa-camera"></i>
                                </div>
                                Foto de Perfil
                            </h4>
                        </div>
                        <div class="config-card-body">
                            <div class="profile-section">
                                <div class="profile-image-container">
                                    <img id="previewFoto" 
                                         src="<?php echo !empty($datos_usuario['foto_perfil']) ? htmlspecialchars($datos_usuario['foto_perfil']) : 'imagenes/perfil_default.png'; ?>" 
                                         alt="Foto actual" 
                                         class="profile-image-enhanced">
                                    <div class="image-upload-overlay" onclick="document.getElementById('foto').click()">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </div>
                                <h5 class="text-center mb-3"><?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?></h5>
                            </div>
                            
                            <form action="subir_foto.php" method="POST" enctype="multipart/form-data">
                                <div class="form-section">
                                    <label for="foto" class="form-label form-label-enhanced">
                                        <i class="fas fa-upload me-2"></i>Seleccionar Nueva Imagen
                                    </label>
                                    <input type="file" 
                                           name="foto" 
                                           id="foto" 
                                           accept="image/*" 
                                           class="form-control form-control-enhanced" 
                                           onchange="previewImagen(event)" 
                                           required>
                                    <small class="form-text text-muted mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Formatos aceptados: JPG, PNG, GIF (máx. 2MB)
                                    </small>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-enhanced btn-primary-enhanced w-100">
                                        <i class="fas fa-upload me-2"></i>Actualizar Foto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Separador -->
            <hr class="section-divider">

            <div class="row g-4">































                <!-- Card 3: Seguridad -->
                <div class="col-lg-8 col-12">
                    <div class="config-card fade-in-up">
                        <div class="config-card-header">
                            <h4>
                                <div class="icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                Seguridad y Contraseña
                            </h4>
                        </div>
                        <div class="config-card-body">
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="alert alert-info border-0" role="alert">
                                        <i class="fas fa-lock me-2"></i>
                                        <strong>Seguridad:</strong> Actualiza tu contraseña regularmente para mantener tu cuenta segura.
                                    </div>
                                </div>
                            </div>
                            
                            <form action="cambiar_contrasena.php" method="POST" class="form-section">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="contrasena_actual" class="form-label form-label-enhanced">
                                            <i class="fas fa-key me-2"></i>Contraseña Actual
                                        </label>
                                        <input type="password" 
                                               name="contrasena_actual" 
                                               id="contrasena_actual" 
                                               class="form-control form-control-enhanced" 
                                               required
                                               placeholder="Ingresa tu contraseña actual">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nueva_contrasena" class="form-label form-label-enhanced">
                                            <i class="fas fa-lock me-2"></i>Nueva Contraseña
                                        </label>
                                        <input type="password" 
                                               name="nueva_contrasena" 
                                               id="nueva_contrasena" 
                                               class="form-control form-control-enhanced" 
                                               required
                                               placeholder="Mínimo 8 caracteres">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirmar_contrasena" class="form-label form-label-enhanced">
                                            <i class="fas fa-check-circle me-2"></i>Confirmar Contraseña
                                        </label>
                                        <input type="password" 
                                               name="confirmar_contrasena" 
                                               id="confirmar_contrasena" 
                                               class="form-control form-control-enhanced" 
                                               required
                                               placeholder="Repite la nueva contraseña">
                                    </div>
                                    <div class="col-12">
                                        <div class="password-strength mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-enhanced btn-danger-enhanced">
                                            <i class="fas fa-shield-alt me-2"></i>Actualizar Contraseña
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Preferencias -->
                <div class="col-lg-4 col-12">
                    <div class="config-card fade-in-up">
                        <div class="config-card-header">
                            <h4>
                                <div class="icon">
                                    <i class="fas fa-palette"></i>
                                </div>
                                Preferencias
                            </h4>
                        </div>
                        <div class="config-card-body">
                            <div class="preferences-grid">
                                <!-- Tema de la aplicación -->
                                <div class="preference-card" onclick="toggleDarkMode()">
                                    <div class="preference-icon">
                                        <i class="fas fa-moon"></i>
                                    </div>
                                    <h5>Tema Visual</h5>
                                    <p>Cambia entre modo claro y oscuro</p>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="dark-mode-toggle">
                                        <span class="toggle-slider"></span>
                                    </div>
                                </div>
                                
                                <!-- Notificaciones -->
                                <div class="preference-card">
                                    <div class="preference-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <h5>Notificaciones</h5>
                                    <p>Gestiona tus alertas y mensajes</p>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                            <i class="fas fa-cog me-1"></i>Proximamente
                                        </button>
                                    </div>
                                </div>
                            </div>

























                        <div class="mt-4 p-3" style="background: #f8f9fa; border-radius: 10px;">
                            <h6 class="mb-2" style="color: #212529 !important;"><i class="fas fa-info-circle me-2 text-primary"></i>Información de Cuenta</h6>
                            <small class="text-muted" style="color: #495057 !important;">
                                <strong style="color: #212529 !important;">Rol:</strong> <?php echo ucfirst($datos_usuario['rol']); ?><br>
                                <strong style="color: #212529 !important;">Último acceso:</strong> <?php echo date("d/m/Y H:i"); ?><br>
                                <strong style="color: #212529 !important;">Estado:</strong> <span class="text-success">Activo</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

            <!-- Separador -->
        <hr class="section-divider">
























































<!-- ==================== ESTADÍSTICAS DE CUENTA ==================== -->
<div class="row g-4">
    <div class="col-12">
        <div class="config-card fade-in-up">
            <div class="config-card-header">
                <h4>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    Estadísticas de Cuenta
                </h4>
            </div>

            <div class="config-card-body">
                <div class="row g-4 text-center justify-content-center">

                    <!-- Fecha de registro -->
                    <div class="col-lg-4 col-md-6">
                        <div class="stat-card shadow-sm"
                             style="background: linear-gradient(135deg, #4CAF50, #2E7D32);
                                    border-radius: 15px;
                                    color: white;
                                    padding: 15px;
                                    min-height: 160px;
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    align-items: center;">
                            <i class="fas fa-calendar-check mb-2" style="font-size: 1.8rem;"></i>
                            <h5 class="mb-1">Registrado</h5>
                            <p class="mb-0" style="font-size: 1rem;">
                                <?= date("d/m/Y", strtotime($datos_usuario['fecha_registro'])); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Tarjeta dinámica: Usuarios validados (admin) / Fichas asignadas (instructor) -->
                    <div class="col-lg-4 col-md-6">
                        <div class="stat-card shadow-sm"
                             style="background: linear-gradient(135deg, #2196F3, #1976D2);
                                    border-radius: 15px;
                                    color: white;
                                    padding: 15px;
                                    min-height: 160px;
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    align-items: center;">
                            <i class="fas fa-folder mb-2" style="font-size: 1.8rem;"></i>
                            <h5 class="mb-1">
                                <?= ($datos_usuario['rol'] === 'administrador')
                                    ? 'Usuarios validados'
                                    : (($datos_usuario['rol'] === 'instructor')
                                        ? 'Fichas asignadas'
                                        : 'Usuarios validados'); ?>
                            </h5>

                            <?php  
                            // ===================== CÁLCULO DIRECTO =====================
                            if ($datos_usuario['rol'] === 'administrador') {
                                $consulta_validados = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE estado = 'activo' AND rol IN ('instructor', 'administrador')");
                                $fila_validados = $consulta_validados->fetch_assoc();
                                $usuarios_validados_total = $fila_validados['total'] ?? 0;
                            }

                            if ($datos_usuario['rol'] === 'instructor') {
                                $consulta_fichas = $conn->query("SELECT COUNT(*) AS total FROM fichas WHERE id IN (SELECT ficha_id FROM usuarios WHERE ficha_id IS NOT NULL)");
                                $fila_fichas = $consulta_fichas->fetch_assoc();
                                $fichas_asignadas_total = $fila_fichas['total'] ?? 0;
                            }
                            ?>

                            <p class="mb-0" id="usuariosValidadosConteo" style="font-size: 1.2rem; font-weight: bold;">
                                <?= ($datos_usuario['rol'] === 'administrador') 
                                    ? $usuarios_validados_total 
                                    : (($datos_usuario['rol'] === 'instructor') 
                                        ? $fichas_asignadas_total 
                                        : '0'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Rol del usuario -->
                    <div class="col-lg-4 col-md-6">
                        <div class="stat-card shadow-sm"
                             style="background: linear-gradient(135deg, #9C27B0, #7B1FA2);
                                    border-radius: 15px;
                                    color: white;
                                    padding: 15px;
                                    min-height: 160px;
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    align-items: center;">
                            <i class="fas fa-user-graduate mb-2" style="font-size: 1.8rem;"></i>
                            <h5 class="mb-1">Rol</h5>
                            <p class="mb-0" style="font-size: 1rem;">
                                <?= htmlspecialchars($datos_usuario['rol']); ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SCRIPT: CONTADOR DINÁMICO DE USUARIOS APROBADOS ==================== -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const contador = document.getElementById("usuariosValidadosConteo");

    <?php if ($datos_usuario['rol'] === 'administrador'): ?>
        let cantidadAprobados = <?= (int)$usuarios_validados_total ?>;
        let start = 0;
        const interval = setInterval(() => {
            start++;
            contador.textContent = start;
            if (start >= cantidadAprobados) clearInterval(interval);
        }, 50);
    <?php endif; ?>
});
</script>
















        <!-- Card 6: Acciones de Cuenta -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="config-card fade-in-up">
            <div class="config-card-header">
                <h4>
                    <div class="icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    Gestión de Cuenta
                </h4>
            </div>

            <div class="config-card-body">
                <div class="row g-3 justify-content-center">
                    <div class="col-lg-5 col-md-6">
                        <div class="d-grid">
                            <button class="btn btn-enhanced btn-primary-enhanced" onclick="showHelpModal()">
                                <i class="fas fa-question-circle me-2"></i>Centro de Ayuda
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-5 col-md-6">
                        <div class="d-grid">
                            <button class="btn btn-enhanced btn-danger-enhanced" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-warning border-0" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Mantén tu información actualizada para recibir notificaciones importantes sobre tu cuenta y actividades académicas.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>
</div>
</div>

<!-- ======================= FIN SECCIÓN DE CONFIGURACIÓN ======================= -->





























</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.20/dist/sweetalert2.all.min.js"></script>
<script>
    function mostrarSeccion(id) {
        const secciones = ['inicio', 'perfil', 'fichas', 'validacion', 'configuracion'];
        secciones.forEach(sec => {
            const element = document.getElementById(sec);
            if (element) {
                element.style.display = 'none';
            }
        });
        const activeElement = document.getElementById(id);
        if (activeElement) {
            activeElement.style.display = 'block';
        }

        const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('sideMenu'));
        if (offcanvas) {
            offcanvas.hide();
        }
       
        document.querySelectorAll('.offcanvas-body .nav-link').forEach(link => link.classList.remove('active'));
        const linkToShow = document.querySelector(`.offcanvas-body a[onclick="mostrarSeccion('${id}')"]`);
        if (linkToShow) {
            linkToShow.classList.add('active');
        }
    }

    function previewImagen(event) {
        const reader = new FileReader();
        reader.onload = function() {
            document.getElementById('previewFoto').src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }











    
    function toggleDarkMode() {
        const body = document.body;
        const toggle = document.getElementById('dark-mode-toggle');
        
        body.classList.toggle('bg-dark');
        const isDark = body.classList.contains('bg-dark');
        
        if (toggle) toggle.checked = isDark;
        
        const formData = new FormData();
        formData.append('tema_oscuro', isDark ? '1' : '0');
        
        fetch('guardar_tema.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) {
                console.error('Error al guardar tema:', data && data.message);
            }
        })
        .catch(err => console.error('Error:', err))
        .finally(() => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                icon: 'success',
                title: `Tema ${isDark ? 'oscuro' : 'claro'} activado`
            });
            setTimeout(() => location.reload(), 400);
        });
    }






















  
   function showHelpModal() {
        Swal.fire({
            title: 'Centro de Ayuda',
            html: `
                <div class="text-start">
                    <h6>
                        <i class="fas fa-question-circle me-2 text-primary"></i>
                        Preguntas Frecuentes
                    </h6>
                    <p>
                        <strong>¿Cómo cambio mi foto de perfil?</strong><br>
                        Ve a la sección <strong>Configuración > Foto de Perfil</strong> y sube una nueva imagen.
                    </p>

                    <p>
                        <strong>¿Cómo actualizo mi información?</strong><br>
                        En <strong>Configuración > Información Personal</strong> puedes editar tus datos.
                    </p>

                    <p>
                        <strong>¿Necesitas más ayuda?</strong><br>
                        Contacta al administrador del sistema o revisa los manuales disponibles en la plataforma.
                    </p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#1e88e5',
            showCloseButton: true,
            focusConfirm: false,
            background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#ffffff',
            color: matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#000000',
            didOpen: () => {
                const popup = Swal.getPopup();
                popup.style.borderRadius = '15px';
                popup.style.boxShadow = matchMedia('(prefers-color-scheme: dark)').matches 
                    ? '0 0 15px rgba(0, 0, 0, 0.7)' 
                    : '0 0 15px rgba(0, 0, 0, 0.1)';
            }
        });
    }






















  
function confirmLogout() {
    Swal.fire({
        title: '¿Cerrar Sesión?',
        text: 'Se cerrará tu sesión actual.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e53935',
        background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
        color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'info',
                title: 'Cerrando sesión...',
                text: 'Tu sesión se está cerrando.',
                timer: 1800,
                showConfirmButton: false,
                background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
                color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
            });

            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 1800);
        }
    });
}

















    
    
    function confirmDeleteFicha(button) {
        const form = button.closest('form');

        Swal.fire({
            title: '¿Eliminar ficha?',
            text: 'Se eliminarán todas las bitácoras y relaciones con aprendices asociadas a esta ficha.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e53935',
            background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
            color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
        }).then((result) => {
            if (result.isConfirmed) {
                // Envío del formulario al confirmar
                form.submit();

                Swal.fire({
                    icon: 'info',
                    title: 'Ficha eliminada',
                    text: 'La ficha y sus datos asociados fueron eliminados correctamente.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: matchMedia('(prefers-color-scheme: dark)').matches ? '#2b2b2b' : '#fff',
                    color: matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#000'
                });
            }
        });
    }






    document.addEventListener('DOMContentLoaded', () => {
        mostrarSeccion('inicio');

        // Inicializar toggle según el estado actual del body (servidor)
        const toggle = document.getElementById('dark-mode-toggle');
        if (toggle) {
            toggle.checked = document.body.classList.contains('bg-dark');
            toggle.addEventListener('change', toggleDarkMode);
        }

        // Event listeners para botones de validación
        document.querySelectorAll('.btn-validar').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const accion = this.dataset.accion;
                const usuarioId = this.dataset.usuarioId;
                console.log("DEBUG: Botón clickeado - Acción:", accion, "Usuario ID:", usuarioId);
                confirmAction(this, usuarioId, accion);
            });
        });

        const hash = window.location.hash.substring(1);
        if (hash) {
            mostrarSeccion(hash);
        }

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });








        }, observerOptions);

        document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.8s ease-out';
            observer.observe(el);
        });

        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                title: "<?php echo $_SESSION['message_type'] === 'success' ? '¡Éxito!' : ($_SESSION['message_type'] === 'warning' ? 'Aviso' : '¡Error!'); ?>",
                text: "<?php echo $_SESSION['message']; ?>",
                icon: "<?php echo $_SESSION['message_type']; ?>",
                confirmButtonText: 'Ok'
            });
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        const nuevaPassword = document.getElementById('nueva_contrasena');
        const confirmarPassword = document.getElementById('confirmar_contrasena');
        
        if (nuevaPassword && confirmarPassword) {
            function validatePasswords() {
                if (confirmarPassword.value !== '' && nuevaPassword.value !== confirmarPassword.value) {
                    confirmarPassword.setCustomValidity('Las contraseñas no coinciden');
                    confirmarPassword.classList.add('is-invalid');
                } else {
                    confirmarPassword.setCustomValidity('');
                    confirmarPassword.classList.remove('is-invalid');
                }
            }
            
            nuevaPassword.addEventListener('input', validatePasswords);
            confirmarPassword.addEventListener('input', validatePasswords);
        }
    });

    function eliminarActividad(idActividad) {
        const actElement = document.getElementById('actividad-' + idActividad);
        if (!actElement) { return; }
        actElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        actElement.style.opacity = '0.2';
        actElement.style.transform = 'translateX(10px)';
        fetch('eliminar_actividad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_actividad=' + encodeURIComponent(idActividad)
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                setTimeout(() => {
                    actElement.remove();
                    const restantes = document.querySelectorAll('#actividad .timeline .timeline-item');
                    if (restantes.length === 0) {
                        const contenedor = document.querySelector('#actividad .timeline');
                        if (contenedor) {
                            contenedor.innerHTML = `
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-bell-slash fa-2x d-block mb-2"></i>
                                    <small>No hay actividad reciente.</small>
                                </div>
                            `;
                        }
                    }
                }, 300);
            } else {
                actElement.style.opacity = '';
                actElement.style.transform = '';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar la actividad' + (data && data.message ? ': ' + data.message : '') });
            }
        })
        .catch(() => {
            actElement.style.opacity = '';
            actElement.style.transform = '';
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar la actividad' });
        });
    }
</script>
</body>
</html>