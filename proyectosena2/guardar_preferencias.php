<?php
session_start();
require 'database.php'; // conexión a la base de datos

// Validar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento'])) {
    header("Location: login.php");
    exit();
}

$documento = $_SESSION['documento'];
$tipo_documento = $_SESSION['tipo_documento'];

// Obtener valores de los checkboxes
$notificar_vencidas  = isset($_POST['notificar_vencidas']) ? 1 : 0;
$notificar_proximas  = isset($_POST['notificar_proximas']) ? 1 : 0;
$notificar_faltantes = isset($_POST['notificar_faltantes']) ? 1 : 0;

try {
    // Crear tabla preferencias si no existe
    $sql_tabla = "CREATE TABLE IF NOT EXISTS preferencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_documento VARCHAR(50) NOT NULL,
        tipo_documento VARCHAR(20) NOT NULL,
        notificar_vencidas TINYINT(1) DEFAULT 1,
        notificar_proximas TINYINT(1) DEFAULT 1,
        notificar_faltantes TINYINT(1) DEFAULT 1,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE (numero_documento, tipo_documento)
    ) ENGINE=InnoDB";
    $conn->query($sql_tabla);

    // Insertar o actualizar preferencias
    $sql = "INSERT INTO preferencias (numero_documento, tipo_documento, notificar_vencidas, notificar_proximas, notificar_faltantes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                notificar_vencidas = VALUES(notificar_vencidas),
                notificar_proximas = VALUES(notificar_proximas),
                notificar_faltantes = VALUES(notificar_faltantes),
                actualizado = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiii", $documento, $tipo_documento, $notificar_vencidas, $notificar_proximas, $notificar_faltantes);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "✅ Preferencias actualizadas correctamente.";
    } else {
        $_SESSION['msg'] = "❌ Error al actualizar preferencias.";
    }

    $stmt->close();
    $conn->close();

    // Redirigir de nuevo al dashboard
    header("Location: principal.php");
    exit();
} catch (Exception $e) {
    error_log("Error en guardar_preferencias: " . $e->getMessage());
    $_SESSION['msg'] = "❌ Error en el servidor.";
    header("Location: principal.php");
    exit();
}
?>