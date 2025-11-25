<?php
session_start();
require 'database.php';

header('Content-Type: application/json');

// Validar sesión
if (!isset($_SESSION['documento'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

// Validar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    // Obtener ID del usuario
    $documento = $_SESSION['documento'];
    $tipo_documento = $_SESSION['tipo_documento'];
    
    $sql_user = "SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("ss", $documento, $tipo_documento);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    
    $user_data = $result_user->fetch_assoc();
    $id_usuario = $user_data['id'];
    $stmt_user->close();
    
    // Obtener valores de los checkboxes (1 si está marcado, 0 si no)
    $notificar_vencidas = isset($_POST['notificar_vencidas']) && $_POST['notificar_vencidas'] === 'on' ? 1 : 0;
    $notificar_proximas = isset($_POST['notificar_proximas']) && $_POST['notificar_proximas'] === 'on' ? 1 : 0;
    $notificar_faltantes = isset($_POST['notificar_faltantes']) && $_POST['notificar_faltantes'] === 'on' ? 1 : 0;
    
    // Verificar si ya existen preferencias
    $sql_check = "SELECT id FROM preferencias_notificaciones WHERE id_usuario = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_usuario);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // Actualizar preferencias existentes
        $sql_update = "UPDATE preferencias_notificaciones 
                       SET notificar_vencidas = ?, notificar_proximas = ?, notificar_faltantes = ? 
                       WHERE id_usuario = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iiii", $notificar_vencidas, $notificar_proximas, $notificar_faltantes, $id_usuario);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Insertar nuevas preferencias
        $sql_insert = "INSERT INTO preferencias_notificaciones (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes) 
                       VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iiii", $id_usuario, $notificar_vencidas, $notificar_proximas, $notificar_faltantes);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $stmt_check->close();
    $conn->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Preferencias guardadas correctamente',
        'preferencias' => [
            'vencidas' => $notificar_vencidas,
            'proximas' => $notificar_proximas,
            'faltantes' => $notificar_faltantes
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>