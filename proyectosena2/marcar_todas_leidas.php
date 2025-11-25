<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

require 'database.php';

try {
    $documento = $_SESSION['documento'];
    $tipo_documento = $_SESSION['tipo_documento'];
    
    // Obtener el ID del usuario
    $sql_user = "SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("ss", $documento, $tipo_documento);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    
    $id_usuario = $result_user->fetch_assoc()['id'];
    $stmt_user->close();
    
    // Eliminar todas las notificaciones del usuario
    $sql_delete = "DELETE FROM notificaciones WHERE id_usuario = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id_usuario);
    
    if ($stmt_delete->execute()) {
        $affected_rows = $stmt_delete->affected_rows;
        $stmt_delete->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Todas las notificaciones han sido marcadas como leídas',
            'deleted_count' => $affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar las notificaciones']);
    }
    
} catch (Exception $e) {
    error_log("Error al marcar notificaciones como leídas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

$conn->close();
?>
