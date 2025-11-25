<?php
session_start();
require 'database.php';

// Verificar aprendiz
if (!isset($_SESSION['aprendiz_id'])) {
    die("❌ Debes iniciar sesión como aprendiz.");
}
$aprendiz_id = $_SESSION['aprendiz_id'];

// Ficha fija
$ficha_codigo = '2910309';
$stmt = $conn->prepare("SELECT id, programa FROM fichas WHERE codigo_ficha = ?");
$stmt->bind_param("s", $ficha_codigo);
$stmt->execute();
$result = $stmt->get_result();
if ($ficha = $result->fetch_assoc()) {
    $ficha_id = $ficha['id'];
    $programa = $ficha['programa'];
} else {
    die("❌ Ficha no encontrada.");
}
$stmt->close();

// Número de bitácora automático
$stmt = $conn->prepare("SELECT MAX(numero_bitacora) AS ultimo FROM bitacoras WHERE aprendiz_id = ? AND ficha_id = ?");
$stmt->bind_param("ii", $aprendiz_id, $ficha_id);
$stmt->execute();
$result = $stmt->get_result();
$ultimo_num = ($row = $result->fetch_assoc()) ? (int)$row['ultimo'] : 0;
$numero_bitacora = $ultimo_num + 1;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Bitácora</title>
</head>
<body <?php echo (!empty($_SESSION['tema_oscuro']) && intval($_SESSION['tema_oscuro']) === 1) ? 'class="bg-dark"' : ''; ?>>
<h2>Subir Bitácora - Ficha <?= htmlspecialchars($ficha_codigo) ?> (<?= htmlspecialchars($programa) ?>)</h2>

<form action="guardar_bitacora.php" method="POST" enctype="multipart/form-data">

    <label>Número de bitácora:</label>
    <input type="number" name="numero_bitacora" value="<?= $numero_bitacora ?>" readonly><br><br>

    <label>Fecha de entrega:</label>
    <input type="date" name="fecha_entrega"><br><br>

    <label>Nombre de la empresa:</label>
    <input type="text" name="nombre_empresa"><br><br>

    <label>NIT:</label>
    <input type="text" name="nit"><br><br>

    <label>Periodo:</label>
    <input type="text" name="periodo"><br><br>

    <label>Nombre del jefe:</label>
    <input type="text" name="nombre_jefe"><br><br>

    <label>Teléfono de contacto:</label>
    <input type="text" name="telefono_contacto"><br><br>

    <label>Correo de contacto:</label>
    <input type="email" name="correo_contacto"><br><br>

    <label>Modalidad / etapa:</label>
    <input type="text" name="modalidad_etapa"><br><br>

    <label>Descripción de la actividad:</label>
    <textarea name="descripcion_actividad"></textarea><br><br>

    <label>Fecha inicio:</label>
    <input type="date" name="fecha_inicio"><br><br>

    <label>Fecha fin:</label>
    <input type="date" name="fecha_fin"><br><br>

    <label>Evidencia de cumplimiento:</label>
    <textarea name="evidencia_cumplimiento"></textarea><br><br>

    <label>Observaciones:</label>
    <textarea name="observaciones"></textarea><br><br>

    <label>Archivo:</label>
    <input type="file" name="archivo"><br><br>

    <button type="submit">Guardar Bitácora</button>
</form>
</body>
</html>
