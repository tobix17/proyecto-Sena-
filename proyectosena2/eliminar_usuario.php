<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario esté autenticado y sea administrador
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Debes ser administrador para realizar esta acción.']);
    exit();
}

require 'database.php';

// Verificar que el usuario existe en la base de datos
$sql_verificar_admin = "SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ? AND rol = 'administrador' AND estado = 'activo'";
$stmt_verificar = $conn->prepare($sql_verificar_admin);
$stmt_verificar->bind_param("ss", $_SESSION['documento'], $_SESSION['tipo_documento']);
$stmt_verificar->execute();
$result_verificar = $stmt_verificar->get_result();

if ($result_verificar->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
    exit();
}

$admin_data = $result_verificar->fetch_assoc();
$_SESSION['usuario_id'] = $admin_data['id']; // Asegurarse de que el ID del administrador esté en la sesión

try {
    // Verificar que se haya proporcionado un ID de usuario
    if (!isset($_POST['usuario_id'])) {
        throw new Exception('ID de usuario no proporcionado');
    }

    $usuario_id = intval($_POST['usuario_id']);
    
    // Verificar que el usuario no se esté eliminando a sí mismo
    if ($_SESSION['usuario_id'] == $usuario_id) {
        throw new Exception('No puedes eliminarte a ti mismo');
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Primero, obtener el rol del usuario para determinar qué tablas actualizar
        $sql_rol = "SELECT rol FROM usuarios WHERE id = ?";
        $stmt_rol = $conn->prepare($sql_rol);
        $stmt_rol->bind_param("i", $usuario_id);
        $stmt_rol->execute();
        $result_rol = $stmt_rol->get_result();
        
        if ($result_rol->num_rows === 0) {
            throw new Exception('Usuario no encontrado');
        }
        
        $usuario = $result_rol->fetch_assoc();
        $rol = strtolower($usuario['rol']);
        $stmt_rol->close();

        // Primero, verificar si el usuario existe
        $sql_check = "SELECT id FROM usuarios WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $usuario_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows === 0) {
            throw new Exception('El usuario que intentas eliminar no existe');
        }
        $stmt_check->close();

        // Eliminar el usuario de la tabla de usuarios
        $sql_delete = "DELETE FROM usuarios WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $usuario_id);
        
        if (!$stmt_delete->execute()) {
            // Si hay un error de clave foránea, podríamos querer manejarlo de manera diferente
            if (strpos($stmt_delete->error, 'foreign key constraint') !== false) {
                throw new Exception('No se puede eliminar el usuario porque tiene registros asociados. Primero elimina sus registros relacionados.');
            } else {
                throw new Exception('Error al eliminar el usuario: ' . $stmt_delete->error);
            }
        }
        
        $stmt_delete->close();

        // Si todo salió bien, confirmar la transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Usuario eliminado correctamente',
            'rol' => $rol
        ]);
        
    } catch (Exception $e) {
        // Si hay algún error, revertir la transacción
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Revertir la transacción si hay un error
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    error_log("Error al eliminar usuario [Usuario ID: $usuario_id, Admin ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . "]: " . $e->getMessage());
    
    $error_message = 'Error al eliminar el usuario';
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $error_message = 'No se puede eliminar el usuario porque tiene registros asociados (como bitácoras, notificaciones, etc.).';
    } else if (strpos($e->getMessage(), 'No autorizado') !== false) {
        $error_message = $e->getMessage();
    } else if (strpos($e->getMessage(), 'No puedes eliminarte a ti mismo') !== false) {
        $error_message = $e->getMessage();
    } else if (strpos($e->getMessage(), 'no existe') !== false) {
        $error_message = $e->getMessage();
    } else {
        // Para depuración en desarrollo
        if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'development') {
            $error_message .= ': ' . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $error_message
    ]);
}

$conn->close();
?>
