<?php
session_start();
require 'database.php';

// Verificar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

$documento = $_SESSION['documento'];
$tipo_documento = $_SESSION['tipo_documento'];
$rol = $_SESSION['rol'];

// Datos del formulario
$contrasena_actual = $_POST['contrasena_actual'] ?? '';
$nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
$confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

// Validar que ambas nuevas contraseñas coincidan
if ($nueva_contrasena !== $confirmar_contrasena) {
    die("Error: La nueva contraseña y su confirmación no coinciden.");
}

// Verificar contraseña actual
$sql = "SELECT contrasena_hash FROM usuarios WHERE numero_documento=? AND tipo_documento=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $documento, $tipo_documento);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if ($usuario && password_verify($contrasena_actual, $usuario['contrasena_hash'])) {
    // Hashear la nueva contraseña
    $nueva_contrasena_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

    // Actualizar contraseña en la base de datos
    $sql_update = "UPDATE usuarios SET contrasena_hash=? WHERE numero_documento=? AND tipo_documento=?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sss", $nueva_contrasena_hash, $documento, $tipo_documento);

    if ($stmt_update->execute()) {
        // Registrar actividad: contraseña actualizada (solo para aprendiz)
        if ($rol === 'aprendiz') {
            // Obtener ID del usuario
            if ($stmt_uid = $conn->prepare("SELECT id FROM usuarios WHERE numero_documento=? AND tipo_documento=?")) {
                $stmt_uid->bind_param("ss", $documento, $tipo_documento);
                if ($stmt_uid->execute()) {
                    $res_uid = $stmt_uid->get_result();
                    if ($row_uid = $res_uid->fetch_assoc()) {
                        $usuario_id = (int)$row_uid['id'];
                        $conn->query("CREATE TABLE IF NOT EXISTS actividades (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            usuario_id INT NOT NULL,
                            tipo VARCHAR(50) NOT NULL,
                            titulo VARCHAR(255) NOT NULL,
                            descripcion TEXT NULL,
                            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        if ($log = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?,?,?,?)")) {
                            $tipo_evt = 'contrasena_actualizada';
                            $titulo_evt = 'Contraseña actualizada';
                            $descripcion_evt = '';
                            $log->bind_param('isss', $usuario_id, $tipo_evt, $titulo_evt, $descripcion_evt);
                            $log->execute();
                            $log->close();
                        }
                    }
                }
                $stmt_uid->close();
            }
        }

        // Redirigir según rol
        if ($rol === 'aprendiz') {
            header("Location: principal.php?msg=contrasena_actualizada");
        } else {
            header("Location: instructores.php?msg=contrasena_actualizada");
        }
        exit();
    } else {
        echo "Error al actualizar la contraseña: " . $conn->error;
    }
} else {
    echo "La contraseña actual es incorrecta.";
}
?>
