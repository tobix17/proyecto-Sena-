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

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $directorio = "uploads/";
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $nombreArchivo = $documento . "_" . time() . "_" . basename($_FILES["foto"]["name"]);
    $rutaArchivo = $directorio . $nombreArchivo;

    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $rutaArchivo)) {
        $sql = "UPDATE usuarios SET foto_perfil=? WHERE numero_documento=? AND tipo_documento=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $rutaArchivo, $documento, $tipo_documento);
        if ($stmt->execute()) {
            // Registrar actividad (solo para aprendiz)
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
                                $tipo_evt = 'foto_actualizada';
                                $titulo_evt = 'Foto de perfil actualizada';
                                $descripcion_evt = basename($rutaArchivo);
                                $log->bind_param('isss', $usuario_id, $tipo_evt, $titulo_evt, $descripcion_evt);
                                $log->execute();
                                $log->close();
                            }
                        }
                    }
                    $stmt_uid->close();
                }
            }
        }
        $stmt->close();
    }
}

// Redirigir según el rol
if ($rol == 'aprendiz') {
    header("Location: principal.php?msg=foto_actualizada");
} else {
    header("Location: instructores.php?msg=foto_actualizada");
}
exit();
?>
