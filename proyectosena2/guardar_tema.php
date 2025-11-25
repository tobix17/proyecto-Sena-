<?php
session_start();
require 'database.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay sesión activa'
    ]);
    exit();
}

try {
    // Asegurar tabla de preferencias con columna tema_oscuro
    $conn->query("CREATE TABLE IF NOT EXISTS preferencias_notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        notificar_vencidas TINYINT(1) NOT NULL DEFAULT 1,
        notificar_proximas TINYINT(1) NOT NULL DEFAULT 1,
        notificar_faltantes TINYINT(1) NOT NULL DEFAULT 1,
        tema_oscuro TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_user (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Obtener ID del usuario
    $documento = $_SESSION['documento'];
    $tipo_documento = $_SESSION['tipo_documento'];
    
    $sql_user = "SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("ss", $documento, $tipo_documento);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ]);
        exit();
    }
    
    $user = $result_user->fetch_assoc();
    $id_usuario = $user['id'];
    $stmt_user->close();
    
    // Obtener valor del tema
    $tema_oscuro = isset($_POST['tema_oscuro']) && $_POST['tema_oscuro'] === '1' ? 1 : 0;
    
    // Verificar si ya existen preferencias
    $sql_check = "SELECT id FROM preferencias_notificaciones WHERE id_usuario = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_usuario);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // Actualizar tema existente
        $sql_update = "UPDATE preferencias_notificaciones 
                       SET tema_oscuro = ? 
                       WHERE id_usuario = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $tema_oscuro, $id_usuario);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Insertar nuevas preferencias con tema
        $sql_insert = "INSERT INTO preferencias_notificaciones 
                       (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro) 
                       VALUES (?, 1, 1, 1, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ii", $id_usuario, $tema_oscuro);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $stmt_check->close();
    // Actualizar la sesión y cookie para reflejar el tema inmediatamente en todas las páginas
    $_SESSION['tema_oscuro'] = $tema_oscuro;
    setcookie('tema_oscuro', (string)$tema_oscuro, time() + 31536000, '/');
    
    echo json_encode([
        'success' => true,
        'message' => 'Tema guardado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar: ' . $e->getMessage()
    ]);
}

$conn->close();
?>