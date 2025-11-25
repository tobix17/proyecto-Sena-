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

// Validar que se reciba el ID de la notificación
if (!isset($_POST['id_notificacion']) || empty($_POST['id_notificacion'])) {
    echo json_encode(['success' => false, 'message' => 'ID de notificación no proporcionado']);
    exit();
}

$id_notificacion = intval($_POST['id_notificacion']);

try {
    // Obtener ID del usuario actual
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
    
    // Verificar que la notificación pertenece al usuario antes de eliminar
    $sql_check = "SELECT id FROM notificaciones WHERE id = ? AND id_usuario = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_notificacion, $id_usuario);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Notificación no encontrada o no autorizada']);
        exit();
    }
    $stmt_check->close();
    
    // Eliminar la notificación
    $sql_delete = "DELETE FROM notificaciones WHERE id = ? AND id_usuario = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $id_notificacion, $id_usuario);
    
    if ($stmt_delete->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Notificación eliminada correctamente',
            'id_notificacion' => $id_notificacion
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la notificación']);
    }
    
    $stmt_delete->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>