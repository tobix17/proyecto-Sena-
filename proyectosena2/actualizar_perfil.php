<?php
session_start();
require 'database.php';

// Verificar sesión activa
if (!isset($_SESSION['documento'], $_SESSION['tipo_documento'], $_SESSION['rol'])) {
    die("Error: No hay sesión activa.");
}

$documento = $_SESSION['documento'];
$tipo_documento = $_SESSION['tipo_documento'];
$rol = $_SESSION['rol'];

// Obtener datos actuales del usuario para mostrarlos en el formulario
$sql = "SELECT nombre, apellido, correo, genero, fecha_nacimiento 
        FROM usuarios 
        WHERE numero_documento=? AND tipo_documento=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $documento, $tipo_documento);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $genero = $_POST['genero'];

    // Separar nombre y apellido
    $partes_nombre = explode(' ', $nombre_completo, 2);
    $nombre = $partes_nombre[0];
    $apellido = $partes_nombre[1] ?? '';

    // Actualizar en BD
    $sql_update = "UPDATE usuarios 
                   SET nombre=?, apellido=?, correo=?, fecha_nacimiento=?, genero=?
                   WHERE numero_documento=? AND tipo_documento=?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sssssss", $nombre, $apellido, $correo, $fecha_nacimiento, $genero, $documento, $tipo_documento);

    if ($stmt_update->execute()) {
        $_SESSION['nombre'] = $nombre . ' ' . $apellido;

        // Registrar actividad: perfil actualizado (para aprendices)
        // Obtener ID del usuario (aprendiz)
        if ($rol === 'aprendiz') {
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
                            $tipo_evt = 'perfil_actualizado';
                            $titulo_evt = 'Perfil actualizado';
                            $descripcion_evt = 'Se actualizaron datos personales.';
                            $log->bind_param('isss', $usuario_id, $tipo_evt, $titulo_evt, $descripcion_evt);
                            $log->execute();
                            $log->close();
                        }
                    }
                }
                $stmt_uid->close();
            }
        }

        // Redirigir según el rol
        if ($rol === 'aprendiz') {
            header("Location: principal.php?mensaje=Perfil actualizado correctamente");
        } elseif ($rol === 'instructor' || $rol === 'administrador') {
            header("Location: instructores.php?mensaje=Perfil actualizado correctamente");
        }
        exit();
    } else {
        echo "Error al actualizar el perfil: " . $conn->error;
    }

    $stmt_update->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizar Perfil</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body <?php echo (!empty($_SESSION['tema_oscuro']) && intval($_SESSION['tema_oscuro']) === 1) ? 'class="bg-dark"' : ''; ?>>
    <h2>Actualizar Perfil</h2>
    <form action="actualizar_perfil.php" method="POST">
        <label for="nombre">Nombre completo:</label>
        <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>" required>

        <label for="correo">Correo electrónico:</label>
        <input type="email" name="correo" id="correo" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>

        <label for="genero">Género:</label>
        <select name="genero" id="genero" required>
            <option value="">Seleccione...</option>
            <option value="masculino" <?php if ($usuario['genero'] === 'masculino') echo 'selected'; ?>>Masculino</option>
            <option value="femenino" <?php if ($usuario['genero'] === 'femenino') echo 'selected'; ?>>Femenino</option>
            <option value="otro" <?php if ($usuario['genero'] === 'otro') echo 'selected'; ?>>Otro</option>
        </select>

        <label for="fecha_nacimiento">Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="<?php echo $usuario['fecha_nacimiento']; ?>" required>

        <button type="submit">Guardar Cambios</button>
    </form>
</body>
</html>
