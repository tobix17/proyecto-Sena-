<?php
// Iniciar sesión
session_start();

// Establecer el tipo de contenido al principio
header('Content-Type: application/json; charset=utf-8');

// Función para enviar respuesta JSON y terminar la ejecución
function sendJsonResponse($success, $message = '', $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Verificar si es una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    sendJsonResponse(false, 'Acceso no autorizado');
}

// Depuración: Registrar todos los datos POST recibidos
error_log("DEBUG: Datos POST recibidos en guardar_preferencias_avanzadas.php: " . print_r($_POST, true));

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento'])) {
    sendJsonResponse(false, 'No autenticado. Por favor, inicia sesión nuevamente.');
}

// Incluir el archivo de base de datos
require 'database.php';

// Verificar la conexión a la base de datos
if ($conn->connect_error) {
    sendJsonResponse(false, 'Error de conexión a la base de datos: ' . $conn->connect_error);
}

// Configurar el manejo de errores de MySQL
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
        sendJsonResponse(false, 'Usuario no encontrado en la base de datos');
    }
    
    $id_usuario = $result_user->fetch_assoc()['id'];
    $stmt_user->close();
    
    // Crear tabla de preferencias avanzadas si no existe
    $sql_create_table = "CREATE TABLE IF NOT EXISTS preferencias_avanzadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        no_molestar_activo TINYINT(1) DEFAULT 0,
        hora_inicio_nm TIME DEFAULT '22:00:00',
        hora_fin_nm TIME DEFAULT '08:00:00',
        dias_recordatorio INT DEFAULT 3,
        recordatorios_multiples TINYINT(1) DEFAULT 1,
        email_vencidas TINYINT(1) DEFAULT 0,
        email_proximas TINYINT(1) DEFAULT 0,
        email_logros TINYINT(1) DEFAULT 1,
        email_resumen TINYINT(1) DEFAULT 1,
        resumen_semanal_activo TINYINT(1) DEFAULT 1,
        dia_resumen INT DEFAULT 1,
        incluir_estadisticas TINYINT(1) DEFAULT 1,
        incluir_sugerencias TINYINT(1) DEFAULT 1,
        mensajes_motivacionales TINYINT(1) DEFAULT 1,
        frecuencia_motivacion VARCHAR(20) DEFAULT 'semanal',
        sonido_notificaciones TINYINT(1) DEFAULT 0,
        notif_navegador TINYINT(1) DEFAULT 0,
        badge_contador TINYINT(1) DEFAULT 1,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user (id_usuario),
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_create_table);
    
    // Recopilar datos del formulario
    $no_molestar_activo = isset($_POST['no_molestar_activo']) ? 1 : 0;
    $hora_inicio_nm = $_POST['hora_inicio_nm'] ?? '22:00';
    $hora_fin_nm = $_POST['hora_fin_nm'] ?? '08:00';
    $dias_recordatorio = intval($_POST['dias_recordatorio'] ?? 3);
    $recordatorios_multiples = isset($_POST['recordatorios_multiples']) ? 1 : 0;
    $email_vencidas = isset($_POST['email_vencidas']) ? 1 : 0;
    $email_proximas = isset($_POST['email_proximas']) ? 1 : 0;
    $email_logros = isset($_POST['email_logros']) ? 1 : 0;
    $email_resumen = isset($_POST['email_resumen']) ? 1 : 0;
    $resumen_semanal_activo = isset($_POST['resumen_semanal_activo']) ? 1 : 0;
    $dia_resumen = intval($_POST['dia_resumen'] ?? 1);
    $incluir_estadisticas = isset($_POST['incluir_estadisticas']) ? 1 : 0;
    $incluir_sugerencias = isset($_POST['incluir_sugerencias']) ? 1 : 0;
    $mensajes_motivacionales = isset($_POST['mensajes_motivacionales']) ? 1 : 0;
    $frecuencia_motivacion = $_POST['frecuencia_motivacion'] ?? 'semanal';
    $sonido_notificaciones = isset($_POST['sonido_notificaciones']) ? 1 : 0;
    $notif_navegador = isset($_POST['notif_navegador']) ? 1 : 0;
    $badge_contador = isset($_POST['badge_contador']) ? 1 : 0;
    
    // Verificar si ya existen preferencias para este usuario
    $sql_check = "SELECT id FROM preferencias_avanzadas WHERE id_usuario = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_usuario);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // Actualizar preferencias existentes
        $sql_update = "UPDATE preferencias_avanzadas SET 
                       no_molestar_activo = ?,
                       hora_inicio_nm = ?,
                       hora_fin_nm = ?,
                       dias_recordatorio = ?,
                       recordatorios_multiples = ?,
                       email_vencidas = ?,
                       email_proximas = ?,
                       email_logros = ?,
                       email_resumen = ?,
                       resumen_semanal_activo = ?,
                       dia_resumen = ?,
                       incluir_estadisticas = ?,
                       incluir_sugerencias = ?,
                       mensajes_motivacionales = ?,
                       frecuencia_motivacion = ?,
                       sonido_notificaciones = ?,
                       notif_navegador = ?,
                       badge_contador = ?
                       WHERE id_usuario = ?";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("issiiiiiiiiiiisiiii", 
            $no_molestar_activo,
            $hora_inicio_nm,
            $hora_fin_nm,
            $dias_recordatorio,
            $recordatorios_multiples,
            $email_vencidas,
            $email_proximas,
            $email_logros,
            $email_resumen,
            $resumen_semanal_activo,
            $dia_resumen,
            $incluir_estadisticas,
            $incluir_sugerencias,
            $mensajes_motivacionales,
            $frecuencia_motivacion,
            $sonido_notificaciones,
            $notif_navegador,
            $badge_contador,
            $id_usuario
        );
        
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                sendJsonResponse(true, 'Preferencias avanzadas actualizadas correctamente');
            } else {
                // No se actualizó ninguna fila, pero tampoco hubo error
                sendJsonResponse(true, 'No se realizaron cambios en las preferencias');
            }
        }
        $stmt_update->close();
        
    } else {
        // Insertar nuevas preferencias
        $sql_insert = "INSERT INTO preferencias_avanzadas (
                       id_usuario, no_molestar_activo, hora_inicio_nm, hora_fin_nm,
                       dias_recordatorio, recordatorios_multiples, email_vencidas,
                       email_proximas, email_logros, email_resumen, resumen_semanal_activo,
                       dia_resumen, incluir_estadisticas, incluir_sugerencias,
                       mensajes_motivacionales, frecuencia_motivacion, sonido_notificaciones,
                       notif_navegador, badge_contador
                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iissiiiiiiiiiiisii",
            $id_usuario,
            $no_molestar_activo,
            $hora_inicio_nm,
            $hora_fin_nm,
            $dias_recordatorio,
            $recordatorios_multiples,
            $email_vencidas,
            $email_proximas,
            $email_logros,
            $email_resumen,
            $resumen_semanal_activo,
            $dia_resumen,
            $incluir_estadisticas,
            $incluir_sugerencias,
            $mensajes_motivacionales,
            $frecuencia_motivacion,
            $sonido_notificaciones,
            $notif_navegador,
            $badge_contador
        );
        
        if ($stmt_insert->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Preferencias avanzadas guardadas correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al guardar las preferencias'
            ]);
        }
        $stmt_insert->close();
    }
    
    $stmt_check->close();
    
} catch (Exception $e) {
    // Registrar el error en el log del servidor
    error_log("Error en guardar_preferencias_avanzadas.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Enviar respuesta de error al cliente
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    
    sendJsonResponse(false, 'Error al procesar tu solicitud. Por favor, inténtalo de nuevo más tarde.');
}

// Cerrar la conexión a la base de datos
if (isset($conn) && $conn) {
    $conn->close();
}

$conn->close();
