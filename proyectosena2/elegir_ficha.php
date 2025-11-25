<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'asem_db');

// Verificar conexión a la base de datos
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Verificar que el usuario esté autenticado y sea instructor o administrador
if (!isset($_SESSION['documento']) || !isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['instructor', 'administrador'])) {
    header("Location: login.php");
    exit();
}

// Obtener fichas desde la base de datos
$sql = "SELECT id, codigo_ficha, programa FROM fichas";
$result = $conn->query($sql);

// Procesar selección de ficha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ficha'])) {
    $ficha_id = $_POST['ficha'];
    $_SESSION['ficha_seleccionada'] = $ficha_id; // Guardar en sesión

    // Redirigir con ruta absoluta para evitar errores de URL
    header("Location: ficha_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Elegir Ficha</title>
</head>
<body <?php echo (!empty($_SESSION['tema_oscuro']) && intval($_SESSION['tema_oscuro']) === 1) ? 'class="bg-dark"' : ''; ?>>
    <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['rol']); ?>)</h2>
    <form method="POST">
        <label for="ficha">Seleccione una ficha:</label>
        <select name="ficha" id="ficha" required>
            <option value="">-- Seleccione --</option>
            <?php while ($row = $result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['id']); ?>">
                    <?= htmlspecialchars($row['codigo_ficha']); ?> - <?= htmlspecialchars($row['programa']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
