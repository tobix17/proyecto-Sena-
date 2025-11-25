<?php
session_start();
require 'database.php'; // conexión a la base de datos

// Validar sesión
if (!isset($_SESSION['documento'])) {
    header("Location: login.php");
    exit();
}

// Endpoint interno: guardar tema visual (sin crear nuevos archivos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tema_oscuro'])) {
    header('Content-Type: application/json');
    try {
        // Asegurar tabla de preferencias
        $conn->query("CREATE TABLE IF NOT EXISTS preferencias_notificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            notificar_vencidas TINYINT(1) NOT NULL DEFAULT 1,
            notificar_proximas TINYINT(1) NOT NULL DEFAULT 1,
            notificar_faltantes TINYINT(1) NOT NULL DEFAULT 1,
            tema_oscuro TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_user (id_usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $documento_post = $_SESSION['documento'];
        $tipo_documento_post = $_SESSION['tipo_documento'];

        $stmt_user_ep = $conn->prepare("SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?");
        $stmt_user_ep->bind_param("ss", $documento_post, $tipo_documento_post);
        $stmt_user_ep->execute();
        $res_user_ep = $stmt_user_ep->get_result();
        if ($res_user_ep->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit();
        }
        $id_usuario_ep = (int)$res_user_ep->fetch_assoc()['id'];
        $stmt_user_ep->close();

        $tema_oscuro_val = ($_POST['tema_oscuro'] === '1') ? 1 : 0;

        // Upsert de preferencia
        $stmt_check_ep = $conn->prepare("SELECT id FROM preferencias_notificaciones WHERE id_usuario = ?");
        $stmt_check_ep->bind_param("i", $id_usuario_ep);
        $stmt_check_ep->execute();
        $res_check_ep = $stmt_check_ep->get_result();

        if ($res_check_ep->num_rows > 0) {
            $stmt_upd_ep = $conn->prepare("UPDATE preferencias_notificaciones SET tema_oscuro = ? WHERE id_usuario = ?");
            $stmt_upd_ep->bind_param("ii", $tema_oscuro_val, $id_usuario_ep);
            $stmt_upd_ep->execute();
            $stmt_upd_ep->close();
        } else {
            $stmt_ins_ep = $conn->prepare("INSERT INTO preferencias_notificaciones (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro) VALUES (?, 1, 1, 1, ?)");
            $stmt_ins_ep->bind_param("ii", $id_usuario_ep, $tema_oscuro_val);
            $stmt_ins_ep->execute();
            $stmt_ins_ep->close();
        }
        $stmt_check_ep->close();

        // Persistir en sesión y cookie para efecto inmediato en todo el sitio
        $_SESSION['tema_oscuro'] = $tema_oscuro_val;
        setcookie('tema_oscuro', (string)$tema_oscuro_val, time() + 31536000, '/');

        echo json_encode(['success' => true, 'message' => 'Tema guardado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit();
}

// 🔹 Validar que el rol sea aprendiz
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'aprendiz') {
    header("Location: login.php");
    exit();
}

// Datos de sesión
$documento = $_SESSION['documento'];
$tipo_documento = $_SESSION['tipo_documento'];
$nombre_sesion = $_SESSION['nombre'] ?? 'Usuario';

$datos_usuario = [];
$saludo = "Bienvenid@";
$total_bitacoras = 0;
$notificaciones_usuario = [];
$actividades = [];

try {
    // Obtener datos del usuario
    $sql = "SELECT id, nombre, apellido, tipo_documento, numero_documento, genero, fecha_nacimiento, correo, fecha_registro, foto_perfil
            FROM usuarios WHERE numero_documento = ? AND tipo_documento = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $documento, $tipo_documento);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $datos_usuario = $resultado->fetch_assoc();
        if ($datos_usuario['genero'] === 'masculino') $saludo = "Bienvenido";
        elseif ($datos_usuario['genero'] === 'femenino') $saludo = "Bienvenida";
    } else {
        $datos_usuario = [
            'id' => 0,
            'nombre' => $nombre_sesion,
            'apellido' => '',
            'tipo_documento' => $tipo_documento,
            'numero_documento' => $documento,
            'genero' => '',
            'fecha_nacimiento' => '',
            'correo' => '',
            'fecha_registro' => '',
            'foto_perfil' => ''
        ];
    }
    $stmt->close();

    // 🔹 Asignar rol fijo de aprendiz
    $datos_usuario['rol'] = 'aprendiz';
    $rolText = 'Aprendiz';

    // Contar bitácoras del usuario actual usando el ID del usuario
    $id_usuario_actual = $datos_usuario['id'];
    if ($id_usuario_actual > 0) {
        $sql_bitacoras = "SELECT COUNT(*) AS total FROM bitacoras WHERE aprendiz_id = ?";
        $stmt_bitacoras = $conn->prepare($sql_bitacoras);
        $stmt_bitacoras->bind_param("i", $id_usuario_actual);
        $stmt_bitacoras->execute();
        $res_bitacoras = $stmt_bitacoras->get_result()->fetch_assoc();
        $total_bitacoras = $res_bitacoras['total'] ?? 0;
        $stmt_bitacoras->close();
    }

    // ================================
    // 🔔 SISTEMA DE NOTIFICACIONES
    // ================================
    if ($id_usuario_actual > 0) {
        // Obtener preferencias del usuario desde la BD
        $sql_prefs = "SELECT notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro 
                      FROM preferencias_notificaciones 
                      WHERE id_usuario = ?";
        $stmt_prefs = $conn->prepare($sql_prefs);
        $stmt_prefs->bind_param("i", $id_usuario_actual);
        $stmt_prefs->execute();
        $res_prefs = $stmt_prefs->get_result();
        
        if ($res_prefs->num_rows > 0) {
            $prefs_row = $res_prefs->fetch_assoc();
            $user_prefs = [
                'notificar_vencidas' => (bool)$prefs_row['notificar_vencidas'],
                'notificar_proximas' => (bool)$prefs_row['notificar_proximas'],
                'notificar_faltantes' => (bool)$prefs_row['notificar_faltantes'],
                'tema_oscuro' => (bool)$prefs_row['tema_oscuro']
            ];
        } else {
            // Valores por defecto si no existen preferencias
            $user_prefs = [
                'notificar_vencidas' => true,
                'notificar_proximas' => true,
                'notificar_faltantes' => true,
                'tema_oscuro' => false
            ];
            // Insertar preferencias por defecto
            $sql_insert_prefs = "INSERT INTO preferencias_notificaciones (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro) VALUES (?, 1, 1, 1, 0)";
            $stmt_insert = $conn->prepare($sql_insert_prefs);
            $stmt_insert->bind_param("i", $id_usuario_actual);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_prefs->close();

        // Si la sesión ya tiene un tema establecido recientemente, priorizarlo
        if (isset($_SESSION['tema_oscuro'])) {
            $user_prefs['tema_oscuro'] = (bool)$_SESSION['tema_oscuro'];
        }
       
        $prioridades = [
            'critica' => ['color' => 'danger'],
            'alta'    => ['color' => 'warning'],
            'media'   => ['color' => 'info']
        ];

        $total_bitacoras_requeridas = 12;
        $dias_alerta_vencimiento = 5;

        // Bitácoras creadas
        $bitacoras_creadas = $total_bitacoras; // Ya tenemos este valor
        $bitacoras_faltantes = max(0, $total_bitacoras_requeridas - $bitacoras_creadas);

        // Bitácoras próximas a vencer
        $sql_proximas = "SELECT COUNT(*) as por_vencer FROM bitacoras 
                         WHERE aprendiz_id = ? 
                         AND fecha_entrega BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $stmt_proximas = $conn->prepare($sql_proximas);
        $stmt_proximas->bind_param("ii", $id_usuario_actual, $dias_alerta_vencimiento);
        $stmt_proximas->execute();
        $res_proximas = $stmt_proximas->get_result()->fetch_assoc();
        $bitacoras_por_vencer = $res_proximas['por_vencer'];
        $stmt_proximas->close();

        // Bitácoras vencidas
        $sql_vencidas = "SELECT COUNT(*) as vencidas FROM bitacoras 
                         WHERE aprendiz_id = ? 
                         AND fecha_entrega < CURDATE() AND estado != 'completada'";
        $stmt_vencidas = $conn->prepare($sql_vencidas);
        $stmt_vencidas->bind_param("i", $id_usuario_actual);
        $stmt_vencidas->execute();
        $res_vencidas = $stmt_vencidas->get_result()->fetch_assoc();
        $bitacoras_vencidas = $res_vencidas['vencidas'];
        $stmt_vencidas->close();

        // Insertar notificaciones
        $sql_insertar = "INSERT INTO notificaciones (id_usuario, mensaje, tipo, icono) VALUES (?, ?, ?, ?)";

        if ($user_prefs['notificar_vencidas'] && $bitacoras_vencidas > 0) {
            $mensaje = "¡URGENTE! Tienes $bitacoras_vencidas bitácora(s) vencida(s).";
            $tipo = $prioridades['critica']['color'];
            $icono = 'fas fa-exclamation-circle';
            $sql_exists = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje = ? AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists = $conn->prepare($sql_exists);
            $stmt_exists->bind_param("is", $id_usuario_actual, $mensaje);
            $stmt_exists->execute();
            $exists = $stmt_exists->get_result()->num_rows > 0;
            $stmt_exists->close();
            if (!$exists) {
                $stmt_insertar = $conn->prepare($sql_insertar);
                $stmt_insertar->bind_param("isss", $id_usuario_actual, $mensaje, $tipo, $icono);
                $stmt_insertar->execute();
                $stmt_insertar->close();
            }
        }

        if ($user_prefs['notificar_proximas'] && $bitacoras_por_vencer > 0) {
            $mensaje = "¡Atención! $bitacoras_por_vencer bitácora(s) vencen en $dias_alerta_vencimiento días.";
            $tipo = $prioridades['alta']['color'];
            $icono = 'fas fa-clock';
            $sql_exists = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje = ? AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists = $conn->prepare($sql_exists);
            $stmt_exists->bind_param("is", $id_usuario_actual, $mensaje);
            $stmt_exists->execute();
            $exists = $stmt_exists->get_result()->num_rows > 0;
            $stmt_exists->close();
            if (!$exists) {
                $stmt_insertar = $conn->prepare($sql_insertar);
                $stmt_insertar->bind_param("isss", $id_usuario_actual, $mensaje, $tipo, $icono);
                $stmt_insertar->execute();
                $stmt_insertar->close();
            }
        }

        if ($user_prefs['notificar_faltantes'] && $bitacoras_faltantes > 0) {
            $mensaje = "Te faltan $bitacoras_faltantes bitácora(s) por completar del total ($total_bitacoras_requeridas).";
            $tipo = $prioridades['media']['color'];
            $icono = 'fas fa-info-circle';
            $sql_exists = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje = ? AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists = $conn->prepare($sql_exists);
            $stmt_exists->bind_param("is", $id_usuario_actual, $mensaje);
            $stmt_exists->execute();
            $exists = $stmt_exists->get_result()->num_rows > 0;
            $stmt_exists->close();
            if (!$exists) {
                $stmt_insertar = $conn->prepare($sql_insertar);
                $stmt_insertar->bind_param("isss", $id_usuario_actual, $mensaje, $tipo, $icono);
                $stmt_insertar->execute();
                $stmt_insertar->close();
            }
        }

        // ============================================
        // 🧠 NOTIFICACIONES INTELIGENTES
        // ============================================
        
        // 1. RECORDATORIOS PROGRAMADOS (3, 7 y 14 días antes del vencimiento)
        $dias_recordatorio = [14, 7, 3, 1]; // Recordatorios escalonados
        
        foreach ($dias_recordatorio as $dias) {
            $sql_recordatorio = "SELECT b.id, b.bitacora_num, b.fecha_entrega, 
                                DATEDIFF(b.fecha_entrega, CURDATE()) as dias_restantes
                                FROM bitacoras b
                                WHERE b.aprendiz_id = ? 
                                AND b.estado != 'completada'
                                AND DATEDIFF(b.fecha_entrega, CURDATE()) = ?";
            $stmt_rec = $conn->prepare($sql_recordatorio);
            $stmt_rec->bind_param("ii", $id_usuario_actual, $dias);
            $stmt_rec->execute();
            $res_rec = $stmt_rec->get_result();
            
            while ($bitacora = $res_rec->fetch_assoc()) {
                $mensaje_rec = "";
                $icono_rec = "";
                $tipo_rec = "";
                
                if ($dias == 14) {
                    $mensaje_rec = "📅 Recordatorio: La bitácora #{$bitacora['bitacora_num']} vence en 2 semanas. ¡Planifica tu tiempo!";
                    $tipo_rec = 'info';
                    $icono_rec = 'fas fa-calendar-alt';
                } elseif ($dias == 7) {
                    $mensaje_rec = "⏰ Recordatorio: La bitácora #{$bitacora['bitacora_num']} vence en 1 semana. ¡Es momento de avanzar!";
                    $tipo_rec = 'warning';
                    $icono_rec = 'fas fa-clock';
                } elseif ($dias == 3) {
                    $mensaje_rec = "⚠️ Recordatorio urgente: La bitácora #{$bitacora['bitacora_num']} vence en 3 días. ¡Apresúrate!";
                    $tipo_rec = 'warning';
                    $icono_rec = 'fas fa-exclamation-triangle';
                } elseif ($dias == 1) {
                    $mensaje_rec = "🚨 ¡ÚLTIMA OPORTUNIDAD! La bitácora #{$bitacora['bitacora_num']} vence mañana. ¡Complétala hoy!";
                    $tipo_rec = 'danger';
                    $icono_rec = 'fas fa-exclamation-circle';
                }
                
                // Verificar si ya existe esta notificación hoy
                $sql_exists_rec = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje = ? AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
                $stmt_exists_rec = $conn->prepare($sql_exists_rec);
                $stmt_exists_rec->bind_param("is", $id_usuario_actual, $mensaje_rec);
                $stmt_exists_rec->execute();
                $exists_rec = $stmt_exists_rec->get_result()->num_rows > 0;
                $stmt_exists_rec->close();
                
                if (!$exists_rec) {
                    $stmt_ins_rec = $conn->prepare($sql_insertar);
                    $stmt_ins_rec->bind_param("isss", $id_usuario_actual, $mensaje_rec, $tipo_rec, $icono_rec);
                    $stmt_ins_rec->execute();
                    $stmt_ins_rec->close();
                }
            }
            $stmt_rec->close();
        }
        
        // 2. NOTIFICACIONES DE LOGROS Y FELICITACIONES
        
        // Logro: Primera bitácora completada
        $sql_primera = "SELECT COUNT(*) as completadas FROM bitacoras WHERE aprendiz_id = ? AND estado = 'completada'";
        $stmt_primera = $conn->prepare($sql_primera);
        $stmt_primera->bind_param("i", $id_usuario_actual);
        $stmt_primera->execute();
        $res_primera = $stmt_primera->get_result()->fetch_assoc();
        $bitacoras_completadas = $res_primera['completadas'];
        $stmt_primera->close();
        
        if ($bitacoras_completadas == 1) {
            $mensaje_logro = "🎉 ¡Felicitaciones! Has completado tu primera bitácora. ¡Excelente inicio!";
            $tipo_logro = 'success';
            $icono_logro = 'fas fa-trophy';
            $sql_exists_logro = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%primera bitácora%' LIMIT 1";
            $stmt_exists_logro = $conn->prepare($sql_exists_logro);
            $stmt_exists_logro->bind_param("i", $id_usuario_actual);
            $stmt_exists_logro->execute();
            if ($stmt_exists_logro->get_result()->num_rows == 0) {
                $stmt_logro = $conn->prepare($sql_insertar);
                $stmt_logro->bind_param("isss", $id_usuario_actual, $mensaje_logro, $tipo_logro, $icono_logro);
                $stmt_logro->execute();
                $stmt_logro->close();
            }
            $stmt_exists_logro->close();
        }
        
        // Logro: Mitad del camino (6 bitácoras)
        if ($bitacoras_completadas == 6) {
            $mensaje_logro = "🏆 ¡Increíble! Has completado la mitad de tus bitácoras. ¡Sigue así!";
            $tipo_logro = 'success';
            $icono_logro = 'fas fa-medal';
            $sql_exists_logro = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%mitad de tus bitácoras%' LIMIT 1";
            $stmt_exists_logro = $conn->prepare($sql_exists_logro);
            $stmt_exists_logro->bind_param("i", $id_usuario_actual);
            $stmt_exists_logro->execute();
            if ($stmt_exists_logro->get_result()->num_rows == 0) {
                $stmt_logro = $conn->prepare($sql_insertar);
                $stmt_logro->bind_param("isss", $id_usuario_actual, $mensaje_logro, $tipo_logro, $icono_logro);
                $stmt_logro->execute();
                $stmt_logro->close();
            }
            $stmt_exists_logro->close();
        }
        
        // Logro: Todas las bitácoras completadas
        if ($bitacoras_completadas >= 12) {
            $mensaje_logro = "🌟 ¡FELICITACIONES! Has completado todas tus bitácoras. ¡Eres un campeón!";
            $tipo_logro = 'success';
            $icono_logro = 'fas fa-star';
            $sql_exists_logro = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%completado todas tus bitácoras%' LIMIT 1";
            $stmt_exists_logro = $conn->prepare($sql_exists_logro);
            $stmt_exists_logro->bind_param("i", $id_usuario_actual);
            $stmt_exists_logro->execute();
            if ($stmt_exists_logro->get_result()->num_rows == 0) {
                $stmt_logro = $conn->prepare($sql_insertar);
                $stmt_logro->bind_param("isss", $id_usuario_actual, $mensaje_logro, $tipo_logro, $icono_logro);
                $stmt_logro->execute();
                $stmt_logro->close();
            }
            $stmt_exists_logro->close();
        }
        
        // Logro: Racha sin bitácoras vencidas
        if ($bitacoras_vencidas == 0 && $bitacoras_completadas > 0) {
            $mensaje_racha = "🔥 ¡Racha perfecta! No tienes bitácoras vencidas. ¡Mantén el ritmo!";
            $tipo_racha = 'success';
            $icono_racha = 'fas fa-fire';
            $sql_exists_racha = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%Racha perfecta%' AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists_racha = $conn->prepare($sql_exists_racha);
            $stmt_exists_racha->bind_param("i", $id_usuario_actual);
            $stmt_exists_racha->execute();
            if ($stmt_exists_racha->get_result()->num_rows == 0 && rand(1, 7) == 1) { // 1 vez por semana aprox
                $stmt_racha = $conn->prepare($sql_insertar);
                $stmt_racha->bind_param("isss", $id_usuario_actual, $mensaje_racha, $tipo_racha, $icono_racha);
                $stmt_racha->execute();
                $stmt_racha->close();
            }
            $stmt_exists_racha->close();
        }
        
        // 3. SUGERENCIAS PERSONALIZADAS BASADAS EN COMPORTAMIENTO
        
        // Sugerencia: Si tiene muchas bitácoras pendientes
        if ($bitacoras_faltantes >= 6) {
            $mensaje_sug = "💡 Consejo: Tienes varias bitácoras pendientes. Te recomendamos crear un calendario para organizarte mejor.";
            $tipo_sug = 'info';
            $icono_sug = 'fas fa-lightbulb';
            $sql_exists_sug = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%calendario para organizarte%' AND DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) LIMIT 1";
            $stmt_exists_sug = $conn->prepare($sql_exists_sug);
            $stmt_exists_sug->bind_param("i", $id_usuario_actual);
            $stmt_exists_sug->execute();
            if ($stmt_exists_sug->get_result()->num_rows == 0) {
                $stmt_sug = $conn->prepare($sql_insertar);
                $stmt_sug->bind_param("isss", $id_usuario_actual, $mensaje_sug, $tipo_sug, $icono_sug);
                $stmt_sug->execute();
                $stmt_sug->close();
            }
            $stmt_exists_sug->close();
        }
        
        // Sugerencia: Si no ha creado bitácoras recientemente
        $sql_ultima_bitacora = "SELECT MAX(fecha_registro) as ultima FROM bitacoras WHERE aprendiz_id = ?";
        $stmt_ultima = $conn->prepare($sql_ultima_bitacora);
        $stmt_ultima->bind_param("i", $id_usuario_actual);
        $stmt_ultima->execute();
        $res_ultima = $stmt_ultima->get_result()->fetch_assoc();
        $stmt_ultima->close();
        
        if ($res_ultima['ultima']) {
            $dias_sin_crear = (strtotime('now') - strtotime($res_ultima['ultima'])) / (60 * 60 * 24);
            if ($dias_sin_crear >= 7 && $bitacoras_faltantes > 0) {
                $mensaje_sug = "📝 Recordatorio amigable: Hace " . floor($dias_sin_crear) . " días que no creas una bitácora. ¿Qué tal si avanzas hoy?";
                $tipo_sug = 'info';
                $icono_sug = 'fas fa-pen';
                $sql_exists_sug = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%días que no creas una bitácora%' AND DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) LIMIT 1";
                $stmt_exists_sug = $conn->prepare($sql_exists_sug);
                $stmt_exists_sug->bind_param("i", $id_usuario_actual);
                $stmt_exists_sug->execute();
                if ($stmt_exists_sug->get_result()->num_rows == 0) {
                    $stmt_sug = $conn->prepare($sql_insertar);
                    $stmt_sug->bind_param("isss", $id_usuario_actual, $mensaje_sug, $tipo_sug, $icono_sug);
                    $stmt_sug->execute();
                    $stmt_sug->close();
                }
                $stmt_exists_sug->close();
            }
        }
        
        // 4. RESUMEN SEMANAL (Los lunes)
        $dia_semana = date('N'); // 1 = Lunes, 7 = Domingo
        if ($dia_semana == 1) { // Solo los lunes
            $mensaje_resumen = "📊 Resumen semanal: Tienes $bitacoras_completadas bitácoras completadas de $total_bitacoras_requeridas. ";
            if ($bitacoras_por_vencer > 0) {
                $mensaje_resumen .= "$bitacoras_por_vencer vencen pronto. ";
            }
            $mensaje_resumen .= "¡Que tengas una excelente semana!";
            $tipo_resumen = 'info';
            $icono_resumen = 'fas fa-chart-line';
            
            $sql_exists_resumen = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje LIKE '%Resumen semanal%' AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists_resumen = $conn->prepare($sql_exists_resumen);
            $stmt_exists_resumen->bind_param("i", $id_usuario_actual);
            $stmt_exists_resumen->execute();
            if ($stmt_exists_resumen->get_result()->num_rows == 0) {
                $stmt_resumen = $conn->prepare($sql_insertar);
                $stmt_resumen->bind_param("isss", $id_usuario_actual, $mensaje_resumen, $tipo_resumen, $icono_resumen);
                $stmt_resumen->execute();
                $stmt_resumen->close();
            }
            $stmt_exists_resumen->close();
        }
        
        // Motivación diaria aleatoria (1 vez por semana aprox)
        $mensajes_motivacion = [
            "💪 ¡Tú puedes! Cada bitácora completada es un paso hacia tu éxito profesional.",
            "🌟 Recuerda: La constancia es la clave del éxito. ¡Sigue adelante!",
            "🎯 Enfócate en tus metas. Cada esfuerzo cuenta para tu formación.",
            "✨ ¡Eres capaz de lograr grandes cosas! Confía en tu proceso de aprendizaje.",
            "🚀 El éxito es la suma de pequeños esfuerzos repetidos día tras día."
        ];
        
        if (rand(1, 7) == 1) { // 1 vez por semana aproximadamente
            $mensaje_motivacion = $mensajes_motivacion[array_rand($mensajes_motivacion)];
            $tipo_motiv = 'success';
            $icono_motiv = 'fas fa-heart';
            $sql_exists_motiv = "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND mensaje = ? AND DATE(fecha_creacion) = CURDATE() LIMIT 1";
            $stmt_exists_motiv = $conn->prepare($sql_exists_motiv);
            $stmt_exists_motiv->bind_param("is", $id_usuario_actual, $mensaje_motivacion);
            $stmt_exists_motiv->execute();
            if ($stmt_exists_motiv->get_result()->num_rows == 0) {
                $stmt_motiv = $conn->prepare($sql_insertar);
                $stmt_motiv->bind_param("isss", $id_usuario_actual, $mensaje_motivacion, $tipo_motiv, $icono_motiv);
                $stmt_motiv->execute();
                $stmt_motiv->close();
            }
            $stmt_exists_motiv->close();
        }

        // Obtener notificaciones
        $sql_get_notif = "SELECT id, mensaje, tipo, icono, fecha_creacion 
                          FROM notificaciones 
                          WHERE id_usuario = ? 
                          ORDER BY fecha_creacion DESC LIMIT 10";
        $stmt_notif = $conn->prepare($sql_get_notif);
        $stmt_notif->bind_param("i", $id_usuario_actual);
        $stmt_notif->execute();
        $res_notif = $stmt_notif->get_result();

        while ($fila = $res_notif->fetch_assoc()) {
            $notificaciones_usuario[] = $fila;
        }
        $stmt_notif->close();

        // Asegurar tabla y obtener actividad reciente del usuario (máx 10)
        $conn->query("CREATE TABLE IF NOT EXISTS actividades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $sql_get_acts = "SELECT id, tipo, titulo, descripcion, fecha
                          FROM actividades
                          WHERE usuario_id = ?
                          ORDER BY fecha DESC
                          LIMIT 10";
        if ($stmt_acts = $conn->prepare($sql_get_acts)) {
            $stmt_acts->bind_param("i", $id_usuario_actual);
            if ($stmt_acts->execute()) {
                $res_acts = $stmt_acts->get_result();
                while ($row = $res_acts->fetch_assoc()) {
                    $actividades[] = $row;
                }
            }
            $stmt_acts->close();
        }
    }

} catch (Exception $e) {
    error_log("Error de base de datos: " . $e->getMessage());
}

// Lógica para manejar el cambio de tema desde el formulario de preferencias
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tema"])) {
    // Aquí no hacemos nada en PHP, la lógica de guardado y carga se manejará en JavaScript
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal | ASEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        /* Navbar */
        header.navbar {
            background: #4CAF50;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand h1 {
            font-weight: 600;
            color: #fff;
        }

        /* Botón menú */
        .material-symbols-outlined[type="button"] {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .material-symbols-outlined[type="button"]:hover {
            color: #FFC107;
        }

        /* Menú lateral */
        .offcanvas .nav-link {
            transition: all 0.3s ease;
            color: #495057;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .offcanvas .nav-link:hover {
            background-color: #e6f4ea;
            color: #2E7D32 !important;
        }
        .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
            font-weight: bold;
        }

        /* Sección bienvenida estilo hero */
        .welcome-section {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .welcome-section::after {
            content: "";
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(25deg);
        }

        /* Tarjetas de estadísticas */
        .stats-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 4px solid #4CAF50;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .stats-card span {
            font-size: 2.5rem;
            color: #4CAF50;
        }

        /* FIX PARA MODO OSCURO - Mantener texto negro en stats-card */
        .bg-dark .stats-card {
            color: #333 !important;
        }
        .bg-dark .stats-card h4 {
            color: #333 !important;
        }
        .bg-dark .stats-card p {
            color: #666 !important;
        }

        /* Botones */
        .btn-success-custom {
            background-color: #4CAF50;
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background-color: #2E7D32;
            transform: translateY(-2px);
        }
        /* Botones de exportación */
        .btn-export-pdf, .btn-export-excel, .btn-export-word {
            background-color: #dc3545; /* Rojo para PDF */
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-export-pdf:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .btn-export-excel {
            background-color: #28a745; /* Verde para Excel */
        }
        .btn-export-excel:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .btn-export-word {
            background-color: #2b579a; /* Azul para Word */
        }
        .btn-export-word:hover {
            background-color: #1e4278;
            transform: translateY(-2px);
        }

        /* 🔔 NOTIFICACIONES MEJORADAS */
        
        /* Badge de contador con animación de pulso */
        .notification-badge {
            animation: pulse 2s infinite;
            font-size: 0.7rem;
            padding: 0.35em 0.5em;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .pulse-badge {
            animation: pulse 2s infinite;
        }
        
        /* Botón de notificaciones en header */
        #notif-bell-btn {
            transition: all 0.3s ease;
            border: none;
            background: transparent;
        }
        
        #notif-bell-btn:hover {
            transform: scale(1.1);
        }
        
        #notif-bell-btn:hover .material-symbols-outlined {
            animation: ring 0.5s ease;
        }
        
        @keyframes ring {
            0%, 100% { transform: rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
            20%, 40%, 60%, 80% { transform: rotate(10deg); }
        }
        
        /* Contenedor de notificaciones con scroll personalizado */
        #notificaciones-container::-webkit-scrollbar {
            width: 6px;
        }
        
        #notificaciones-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #notificaciones-container::-webkit-scrollbar-thumb {
            background: #4CAF50;
            border-radius: 10px;
        }
        
        #notificaciones-container::-webkit-scrollbar-thumb:hover {
            background: #2E7D32;
        }
        
        /* Items de notificación mejorados */
        .notification-item {
            border-radius: 12px;
            margin-bottom: 0.75rem;
            border-left: 4px solid;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .notification-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: width 0.3s ease;
        }
        
        .notification-item:hover::before {
            width: 100%;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Iconos de notificación animados */
        .notification-icon {
            font-size: 1.2rem;
            animation: iconBounce 0.5s ease;
        }
        
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Animación de entrada para notificaciones */
        .notification-slide-in {
            animation: slideInRight 0.4s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Animación de salida */
        .notification-slide-out {
            animation: slideOutRight 0.4s ease-out forwards;
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }
        
        /* Botón cerrar mejorado */
        .btn-close-notif {
            opacity: 0.5;
            transition: all 0.3s ease;
            background-size: 50%;
        }
        
        .btn-close-notif:hover {
            opacity: 1;
            transform: rotate(90deg) scale(1.2);
        }
        
        /* Colores por tipo de notificación */
        .notification-danger {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .notification-warning {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        .notification-info {
            border-left-color: #17a2b8;
            background-color: #d1ecf1;
        }
        .notification-success {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
        
        /* SISTEMA DE NOTIFICACIONES TOAST */
        #toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .toast-notification {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-left: 4px solid;
            animation: toastSlideIn 0.4s ease-out;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .toast-notification.toast-danger { border-left-color: #dc3545; }
        .toast-notification.toast-warning { border-left-color: #ffc107; }
        .toast-notification.toast-info { border-left-color: #17a2b8; }
        .toast-notification.toast-success { border-left-color: #28a745; }
        
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(400px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .toast-notification.toast-removing {
            animation: toastSlideOut 0.4s ease-out forwards;
        }
        
        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(400px);
            }
        }
        
        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .toast-content {
            flex-grow: 1;
        }
        
        .toast-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.3s;
            padding: 0;
            line-height: 1;
        }
        
        .toast-close:hover {
            opacity: 1;
        }

        /* ESTILOS PARA MODO OSCURO */
        body.bg-dark {
            background-color: #121212 !important;
            color: #e0e0e0;
        }
        .bg-dark .navbar {
            background-color: #212121 !important;
            box-shadow: 0 3px 8px rgba(255,255,255,0.1);
        }
        .bg-dark .navbar-brand h1 {
            color: #fff;
        }
        .bg-dark .offcanvas {
            background-color: #212121 !important;
            color: #e0e0e0;
        }
        .bg-dark .offcanvas-header .btn-close {
            filter: invert(1);
        }
        .bg-dark .offcanvas .nav-link {
            color: #bdbdbd;
        }
        .bg-dark .offcanvas .nav-link:hover {
            background-color: #333;
            color: #e0e0e0 !important;
        }
        .bg-dark .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .bg-dark .welcome-section {
            background: linear-gradient(135deg, #2e8700, #4CAF50) !important;
            box-shadow: 0 10px 25px rgba(255,255,255,0.15);
        }
        .bg-dark .card,
        .bg-dark .list-group-item,
        .bg-dark .form-control,
        .bg-dark .form-select,
        .bg-dark .input-group-text,
        .bg-dark .perfil-info {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        /* CAMBIOS APLICADOS */
        .bg-dark .stats-card, .bg-dark .dashboard-card {
            background-color: #333 !important; /* Gris oscuro para las tarjetas de inicio */
            color: #f8f9fa !important;
            border-color: #555 !important;
        }
        .bg-dark .stats-card span {
             color: #bdbdbd !important; /* Iconos en gris claro */
        }
        .bg-dark .stats-card h4, .bg-dark .stats-card p {
            color: #e0e0e0 !important;
        }
        .bg-dark #bitacora .card {
            background-color: #333 !important; /* Gris oscuro para toda la tarjeta de bitácora */
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .bg-dark #bitacora .form-control, .bg-dark #bitacora .form-select {
            background-color: #3d3d3d !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .bg-dark #bitacora .table {
            background-color: #333 !important;
            color: #e0e0e0 !important;
        }
        .bg-dark #bitacora .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: #444 !important;
        }
        /* CAMBIOS ADICIONALES PARA LA SECCIÓN DE BITÁCORA */
        .bg-dark #bitacora .bg-light {
            background-color: #2c2c2c !important; /* Asegura que el contenedor de la modalidad sea gris oscuro */
        }
        .bg-dark #bitacora .table thead, .bg-dark #bitacora .table .bg-secondary {
            background-color: #444 !important; /* Asegura que el encabezado de la tabla sea gris oscuro */
            color: #fff !important;
        }
        .bg-dark #bitacora .table tbody tr td,
        .bg-dark #bitacora .table tbody tr td input {
            background-color: #444 !important; /* Asegura que las celdas y los inputs sean grises */
        }
        /* FIN DE CAMBIOS */

        .bg-dark .card-title,
        .bg-dark h2,
        .bg-dark h4 {
            color: #e0e0e0 !important;
        }
        .bg-dark .text-muted {
            color: #a0a0a0 !important;
        }
        .bg-dark .text-success {
            color: #66bb6a !important;
        }
        .bg-dark .nav-link.active,
        .bg-dark .nav-link:hover {
            background-color: #4CAF50 !important;
            color: #fff !important;
        }
        .bg-dark .list-group-item:hover {
            background: rgba(57,169,0,0.1) !important;
        }

        /* Nuevos estilos para los botones de preferencias en modo oscuro */
        .bg-dark .btn-outline-dark {
            color: #bdbdbd !important;
            border-color: #bdbdbd !important;
        }
        .bg-dark .btn-outline-dark:hover {
            background-color: #bdbdbd !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-danger {
            color: #ef5350 !important;
            border-color: #ef5350 !important;
        }
        .bg-dark .btn-outline-danger:hover {
            background-color: #ef5350 !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-success {
            color: #66bb6a !important;
            border-color: #66bb6a !important;
        }
        .bg-dark .btn-outline-success:hover {
            background-color: #66bb6a !important;
            color: #212121 !important;
        }
        .bg-dark .table {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
        }
        .bg-dark .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: #333 !important;
        }
        .bg-dark .table-bordered,
        .bg-dark .table-bordered td,
        .bg-dark .table-bordered th {
            border-color: #444 !important;
        }
        .bg-dark .alert {
            background-color: #2c2c2c !important;
            border-color: #444 !important;
            color: #e0e0e0 !important;
        }
        /* Nuevos estilos para el modo oscuro en bitácoras */
        .bg-dark #bitacora .bg-light {
            background-color: #2c2c2c !important; /* Mantiene un gris oscuro en el fondo del formulario */
        }
        .bg-dark #bitacora .form-label,
        .bg-dark #bitacora input[type="text"],
        .bg-dark #bitacora input[type="email"],
        .bg-dark #bitacora input[type="date"] {
            color: #bdbdbd !important; /* Color gris para el texto */
        }
        .bg-dark #bitacora .table .form-control {
            background-color: #3d3d3d !important;
            color: #e0e0e0 !important;
        }

        /* ESTILOS PARA LA ANIMACIÓN DE ÉXITO */
        #success-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(76, 175, 80, 0.9);
            color: white;
            padding: 20px 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1050;
            display: none;
            opacity: 0;
            animation: fadeInScale 0.5s forwards;
        }

        @keyframes fadeInScale {
            from {
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
            to {
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 0;
            }
        }
    
    </style>


</head>
<?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
?>
<body class="<?php echo ($isDark ? 'bg-dark text-light' : ''); ?>">
<style>
  .swal2-popup { background: #2b2b2b !important; color: #f1f1f1 !important; }
  .swal2-title, .swal2-html-container { color: #f1f1f1 !important; }
  .swal2-actions .swal2-styled.swal2-confirm { background: #4CAF50 !important; border: 0 !important; }
  .swal2-actions .swal2-styled.swal2-cancel { background: #555 !important; border: 0 !important; }
  .swal2-toast { background: #2b2b2b !important; color: #f1f1f1 !important; }
</style>

<header class="navbar navbar-light sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <span class="material-symbols-outlined me-2 text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideMenu" aria-controls="sideMenu">menu</span>
            <h1 class="h4 mb-0"><?php echo $saludo . ", " . htmlspecialchars($datos_usuario['nombre']); ?></h1>
        </a>
        <div class="d-flex align-items-center gap-3">
            <!-- Botón de notificaciones con contador -->
            <button class="btn btn-link position-relative p-0" onclick="toggleNotificationPanel()" id="notif-bell-btn" title="Notificaciones">
                <span class="material-symbols-outlined text-white" style="font-size: 28px;">notifications</span>
                <?php if (count($notificaciones_usuario) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" id="notif-count">
                        <?php echo count($notificaciones_usuario); ?>
                        <span class="visually-hidden">notificaciones sin leer</span>
                    </span>
                <?php endif; ?>
            </button>
        </div>
    </div>
</header>

<script>
// Reusable toggle for pages that include a theme switch
function toggleDarkMode() {
  const body = document.body;
  const willBeDark = !body.classList.contains('bg-dark');
  const formData = new FormData();
  formData.append('tema_oscuro', willBeDark ? '1' : '0');
  fetch('guardar_tema.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) {
        console.error('Error al guardar tema', data && data.message);
      }
    })
    .catch(err => console.error('Error tema:', err))
    .finally(() => { location.reload(); });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('dark-mode-toggle');
  if (toggle) {
    toggle.checked = document.body.classList.contains('bg-dark');
    toggle.addEventListener('change', toggleDarkMode);
  }
});
</script>

<div class="offcanvas offcanvas-start bg-light" tabindex="-1" id="sideMenu" aria-labelledby="sideMenuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sideMenuLabel">Menú Principal</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('inicio')"><i class="fas fa-home me-2"></i>Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('perfil')"><i class="fas fa-user-circle me-2"></i>Perfil</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('bitacora')"><i class="fas fa-book me-2"></i>Bitácora</a></li>
            <li class="nav-item"><a class="nav-link" href="#" onclick="mostrarSeccion('configuracion')"><i class="fas fa-cogs me-2"></i>Configuración</a></li>
            <li class="nav-item mt-auto"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<!-- Contenedor de notificaciones Toast -->
<div id="toast-container"></div>

<main class="container py-4">
    <div id="inicio" class="app-section d-block">
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-3">¡Bienvenido a la página principal!</h2>
                    <p class="lead mb-0">Gestiona tu perfil, bitácoras y configuración desde un solo lugar.</p>
                </div>
                <div class="col-md-4 text-center d-none d-md-block">
                    <span class="material-symbols-outlined" style="font-size: 4rem;">dashboard</span>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-success">person</span>
                    <h4 class="mt-2 mb-1">Perfil</h4>
                    <p class="text-muted mb-0">Completo</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-info">book</span>
                    <h4 class="mt-2 mb-1">Bitácoras</h4>
                    <p class="text-muted mb-0"><?php echo $total_bitacoras; ?> de 12 Creadas</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-warning">schedule</span>
                    <h4 class="mt-2 mb-1">Última Sesión</h4>
                    <p class="text-muted mb-0">Hoy</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <span class="material-symbols-outlined text-danger">verified</span>
                    <h4 class="mt-2 mb-1">Estado</h4>
                    <p class="text-muted mb-0">Activo</p>
                </div>
            </div>
        </div>
             <div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body text-center">
                <span class="material-symbols-outlined card-icon text-primary">person_outline</span>
                <h5 class="card-title fw-bold">Mi Perfil</h5>
                <p class="card-text text-muted">Visualiza y edita tu información personal</p>
                <button class="btn btn-primary quick-action-btn" onclick="mostrarSeccion('perfil')">
                    <i class="fas fa-user-edit me-2"></i>Ver Perfil
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body text-center">
                <span class="material-symbols-outlined card-icon text-success">description</span>
                <h5 class="card-title fw-bold">Bitácora</h5>
                <p class="card-text text-muted">Crea y gestiona tus bitácoras de seguimiento</p>
                <button class="btn btn-success quick-action-btn" onclick="mostrarSeccion('bitacora')">
                    <i class="fas fa-book me-2"></i>Nueva Bitácora
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body text-center">
                <span class="material-symbols-outlined card-icon text-warning">settings</span>
                <h5 class="card-title fw-bold">Configuración</h5>
                <p class="card-text text-muted">Personaliza tu cuenta y preferencias</p>
                <button class="btn btn-warning quick-action-btn" onclick="mostrarSeccion('configuracion')">
                    <i class="fas fa-cogs me-2"></i>Configurar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="col-md-4">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-info text-white rounded-top d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined me-2">notifications</span>Notificaciones
                <?php if (count($notificaciones_usuario) > 0): ?>
                    <span class="badge bg-danger ms-2 pulse-badge"><?php echo count($notificaciones_usuario); ?></span>
                <?php endif; ?>
            </h5>
            <?php if (count($notificaciones_usuario) > 0): ?>
                <button class="btn btn-sm btn-light" onclick="marcarTodasLeidas()" title="Marcar todas como leídas">
                    <i class="fas fa-check-double"></i>
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body" id="notificaciones-container" style="max-height: 400px; overflow-y: auto;">
            <?php if (!empty($notificaciones_usuario)): ?>
                <?php foreach ($notificaciones_usuario as $n): ?>
                    <?php
                        $tipo = htmlspecialchars($n['tipo']); // danger, warning, info, success
                        $icono = htmlspecialchars($n['icono']);
                        $mensaje = htmlspecialchars($n['mensaje']);
                        $fecha = date('d/m/Y H:i', strtotime($n['fecha_creacion']));
                    ?>
                  <div class="alert alert-<?= $tipo ?> mb-2 shadow-sm rounded-3 d-flex align-items-start position-relative notificacion-item notification-slide-in" 
                         role="alert" 
                         id="notificacion-<?= $n['id'] ?>"
                         data-notif-id="<?= $n['id'] ?>">
                        <i class="<?= $icono ?> me-2 mt-1 notification-icon"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= $mensaje ?></div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-clock me-1"></i><?= $fecha ?>
                            </small>
                        </div>
                        <button type="button" 
                                class="btn-close btn-close-notif" 
                                aria-label="Eliminar notificación"
                                onclick="eliminarNotificacion(<?= $n['id'] ?>)"
                                title="Eliminar notificación">
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted text-center py-5" id="no-notifications-msg">
                    <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No hay notificaciones por ahora.</p>
                    <small>Te notificaremos cuando haya novedades</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>


<style>
    /* Colores institucionales SENA */
    :root {
        --sena-green: #4CAF50;
        --sena-green-dark: #2e8700;
        --sena-white: #ffffff;
    }

    /* Título */
    #perfil h2 {
        color: var(--sena-green-dark);
        text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
        animation: fadeInDown 1s ease;
    }

    /* Foto de perfil */
    #perfil img {
        border: 4px solid var(--sena-green);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    #perfil img:hover {
        transform: scale(1.1);
        box-shadow: 0px 8px 20px rgba(0,0,0,0.3);
    }

    /* Botón Cambiar Foto */
    .btn-success-custom {
        background-color: var(--sena-green);
        border: none;
        color: white;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    .btn-success-custom i {
        color: var(--sena-green-dark); /* ícono más oscuro */
    }
    .btn-success-custom:hover {
        background-color: var(--sena-green-dark);
        transform: scale(1.05);
        box-shadow: 0px 6px 15px rgba(0,0,0,0.2);
    }

    /* Card principal */
    #perfil .card {
        background: var(--sena-white);
        border: 2px solid var(--sena-green);
        animation: fadeInUp 1s ease;
    }

    /* Info personal */
    .list-group-item {
        transition: background 0.3s ease, transform 0.2s ease;
    }
    .list-group-item:hover {
        background: rgba(57,169,0,0.1);
        transform: translateX(5px);
    }

    /* Bloques de información (rol, estado, etc.) */
    .perfil-info {
        background: var(--sena-green);
        color: white;
        border-radius: 12px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .perfil-info:hover {
        transform: scale(1.05);
        box-shadow: 0px 6px 15px rgba(0,0,0,0.2);
    }

    /* Animaciones */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>


































































































<style>
/* ===== ESTILOS MODO CLARO ===== */

/* Banner superior con degradado */
.profile-banner {
    background: linear-gradient(135deg, #39a900 0%, #2d8400 100%);
    position: relative;
}

.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 Q300,60 600,30 T1200,0 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.1)"/></svg>') no-repeat bottom;
    background-size: cover;
}

.banner-content {
    z-index: 1;
}

/* Animación de la foto de perfil */
.profile-picture {
    transition: all 0.3s ease;
    animation: fadeInScale 0.6s ease-out;
}

.profile-picture:hover {
    transform: scale(1.05);
    box-shadow: 0px 8px 20px rgba(0,0,0,0.3);
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Badge de rol */
.profile-badge {
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}

.badge-aprendiz {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
}

.badge-instructor {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.badge-admin {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
}

/* Botones con efecto hover */
.btn-hover {
    transition: all 0.3s ease;
    border-radius: 8px;
    font-weight: 600;
}

.btn-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-success {
    background: linear-gradient(135deg, #39a900 0%, #2d8400 100%);
    border: none;
}

.btn-success:hover {
    background: linear-gradient(135deg, #2d8400 0%, #1f5a00 100%);
}

/* Pestañas personalizadas */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link:hover {
    color: #39a900;
    border-bottom-color: #39a900;
    background-color: rgba(57, 169, 0, 0.05);
}

.nav-tabs .nav-link.active {
    color: #39a900;
    border-bottom-color: #39a900;
    background-color: rgba(57, 169, 0, 0.05);
    font-weight: 600;
}

/* Tarjetas de información */
.info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.info-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.info-item {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-icon {
    width: 40px;
    text-align: center;
    font-size: 1.25rem;
    margin-right: 1rem;
}

/* Tarjetas de estadísticas */
.stat-card, .stat-box {
    background: white;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}
.bg-dark .stat-box {
    background: #2c2c2c !important;
    border-color: #444 !important;
    color: #e0e0e0 !important;
}

.stat-card:hover, .stat-box:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-5px);
    border-color: #39a900;
}

.stat-icon i {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Timeline de actividad */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #39a900, #e9ecef);
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
    animation: slideInLeft 0.5s ease-out;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.timeline-marker {
    position: absolute;
    left: -26px;
    top: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    z-index: 1;
}

.timeline-content {
    background: white;
    padding: 1.25rem;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.timeline-content:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transform: translateX(5px);
    border-color: #39a900;
}

/* Tabla personalizada */
.table-hover tbody tr {
    transition: all 0.3s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(57, 169, 0, 0.05);
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Badges personalizados */
.badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.4em 0.8em;
}

/* Alertas personalizadas */
.alert {
    border-radius: 10px;
    border: none;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    color: #856404;
}

/* Barra de progreso personalizada */
.progress {
    border-radius: 10px;
    background-color: #e9ecef;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.progress-bar {
    border-radius: 10px;
    transition: width 1s ease;
}

/* Animaciones de entrada */
.card {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Efectos de hover suaves en íconos */
.fas, .far {
    transition: all 0.3s ease;
}

.info-item:hover .fas,
.timeline-content:hover .fas {
    transform: scale(1.1);
    color: #39a900 !important;
}

/* Sombras suaves para las tarjetas */
.card, .info-card, .stat-card, .stat-box {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* Scrollbar */
.tab-content::-webkit-scrollbar {
    width: 8px;
}
.tab-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.tab-content::-webkit-scrollbar-thumb {
    background: #39a900;
    border-radius: 10px;
}
.tab-content::-webkit-scrollbar-thumb:hover {
    background: #2d8400;
}

/* Textos */
.text-success { color: #39a900 !important; }
.bg-success { background-color: #39a900 !important; }
.border-success { border-color: #39a900 !important; }
.btn-outline-success {
    color: #39a900;
    border-color: #39a900;
}
.btn-outline-success:hover {
    background-color: #39a900;
    color: white;
}

/* Resplandor */
.badge-danger {
    animation: glow 2s ease-in-out infinite alternate;
}
@keyframes glow {
    from { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
    to { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8); }
}

/* Responsive */
@media (max-width: 768px) {
    .profile-picture {
        width: 120px !important;
        height: 120px !important;
    }
    
    .banner-content {
        padding: 2rem 1rem !important;
    }
    
    .nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .timeline {
        padding-left: 20px;
    }
    
    .timeline-marker {
        left: -21px;
        width: 14px;
        height: 14px;
    }
    
    .stat-box h3 {
        font-size: 1.5rem;
    }
}

/* ========== MODO OSCURO EXCLUSIVO PARA #perfil ========== */
.bg-dark #perfil .profile-banner {
    background: linear-gradient(135deg, #2e8700, #4CAF50) !important;
}
.bg-dark #perfil .banner-overlay {
    background: radial-gradient(circle at 50% 30%, rgba(255,255,255,0.06), transparent 60%);
}
.bg-dark #perfil .banner-content h2,
.bg-dark #perfil .banner-content p {
    color: #e0e0e0 !important; /* Igual que instructores: texto claro sobre header oscuro */
}

/* Foto de perfil destacada sobre fondos oscuros */
.bg-dark #perfil .profile-picture {
    box-shadow: 0 10px 28px rgba(0,0,0,.55);
    border: 5px solid #2c2c2c !important;
}

/* Nombre, correo y badges */
.bg-dark #perfil .profile-name { color: #f0f0f0 !important; }
.bg-dark #perfil .profile-email { color: #cfcfcf !important; }
.bg-dark #perfil .status-badge { filter: brightness(0.95); }

/* Tarjetas y contenedores */
.bg-dark #perfil .card,
.bg-dark #perfil .info-card,
.bg-dark #perfil .stat-card,
.bg-dark #perfil .stat-box,
.bg-dark #perfil .timeline-content {
    background: #1e1e1e !important;
    border-color: #2f2f2f !important;
    color: #e6e6e6 !important;
    box-shadow: 0 10px 24px rgba(0,0,0,.35) !important;
}
.bg-dark #perfil .list-group-item {
    background: #222 !important;
    border-color: #2f2f2f !important;
    color: #ddd !important;
}
/* Forzar cabecera del perfil (tiene bg-white en HTML) a oscuro */
.bg-dark #perfil .card-header.bg-white { background: #1e1e1e !important; }
/* Forzar textos con .text-dark a claro dentro de #perfil en oscuro */
.bg-dark #perfil .text-dark { color: #eaeaea !important; }
/* Ajustes de badges sutiles dentro del perfil en oscuro */
.bg-dark #perfil .badge.bg-success-subtle { background-color: rgba(102,187,106,0.12) !important; border-color: #2e7d32 !important; }
.bg-dark #perfil .badge.bg-success-subtle.text-success { color: #a5d6a7 !important; }
.bg-dark #perfil .badge.bg-info-subtle { background-color: rgba(129,212,250,0.12) !important; border-color: #0288d1 !important; }
.bg-dark #perfil .badge.bg-info-subtle.text-info { color: #81d4fa !important; }
.bg-dark #perfil .badge.bg-primary-subtle { background-color: rgba(144,202,249,0.12) !important; border-color: #1976d2 !important; }
.bg-dark #perfil .badge.bg-primary-subtle.text-primary { color: #90caf9 !important; }
.bg-dark #perfil .form-label,
.bg-dark #perfil .form-label-enhanced { color: #cfd8dc !important; }
.bg-dark #perfil .form-control,
.bg-dark #perfil .form-select {
    background: #262626 !important;
    border-color: #3a3a3a !important;
    color: #e6e6e6 !important;
}
.bg-dark #perfil .form-control:focus,
.bg-dark #perfil .form-select:focus {
    background: #2b2b2b !important;
    border-color: #66bb6a !important;
    box-shadow: 0 0 0 0.2rem rgba(102,187,106,0.15) !important;
}
.bg-dark #perfil .info-item { border-bottom-color: #2f2f2f !important; }

/* Pestañas del perfil */
.bg-dark #perfil .nav-tabs { border-bottom-color: #2f2f2f !important; }
.bg-dark #perfil .nav-tabs .nav-link { color: #cfcfcf !important; }
.bg-dark #perfil .nav-tabs .nav-link:hover,
.bg-dark #perfil .nav-tabs .nav-link.active {
    color: #66bb6a !important;
    background-color: rgba(102,187,106,0.08) !important;
    border-bottom-color: #66bb6a !important;
}

/* Timeline */
.bg-dark #perfil .timeline::before { background: linear-gradient(to bottom, #2e7d32, #2f2f2f) !important; }
.bg-dark #perfil .timeline-marker { box-shadow: 0 2px 8px rgba(0,0,0,.4); }
.bg-dark #perfil .timeline-content h6 { color: #eaeaea !important; }
.bg-dark #perfil .timeline-content .text-muted { color: #b8b8b8 !important; }
.bg-dark #perfil .info-item strong { color: #66bb6a !important; }

/* Botones dentro del perfil */
.bg-dark #perfil .btn-hover { color: #eaeaea; }
.bg-dark #perfil .btn-success { background: linear-gradient(135deg, #2e7d32, #1b5e20) !important; }
.bg-dark #perfil .btn-success:hover { background: linear-gradient(135deg, #388e3c, #2e7d32) !important; }
.bg-dark #perfil .btn-outline-success {
    color: #66bb6a !important;
    border-color: #2f2f2f !important;
    background-color: #242424 !important;
}
.bg-dark #perfil .btn-outline-success:hover {
    color: #1b5e20 !important;
    background-color: #66bb6a !important;
    border-color: #66bb6a !important;
}

/* Separadores y textos generales */
.bg-dark #perfil .text-muted { color: #bdbdbd !important; }
.bg-dark #perfil .border,
.bg-dark #perfil hr { border-color: #2f2f2f !important; }


</style>






                  



<div id="perfil" class="app-section d-none">
    <!-- Banner superior con degradado -->
    <div class="profile-banner mb-4 position-relative overflow-hidden rounded-4 shadow">
        <div class="banner-overlay"></div>
        <div class="banner-content text-center text-white py-5 position-relative">
            <h2 class="fw-bold mb-2">
                <i class="fas fa-user-circle me-2"></i>
                Mi Perfil
            </h2>
            <p class="mb-0 opacity-75">Gestiona tu información personal y configuración</p>
        </div>
    </div>





































    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <!-- Tarjeta principal del perfil -->
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
                <!-- Sección de encabezado con foto de perfil -->
                <div class="card-header bg-white border-0 py-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div class="position-relative d-inline-block">
                                <img src="<?php echo !empty($datos_usuario['foto_perfil']) ? htmlspecialchars($datos_usuario['foto_perfil']) : 'imagenes/perfil_default.png'; ?>"
                                    alt="Foto de perfil"
                                    class="img-fluid rounded-circle shadow-lg profile-picture"
                                    style="width: 160px; height: 160px; object-fit: cover; border: 5px solid #fff;">
                                <div class="profile-badge">
                                    <?php 
                                    $rol = strtolower($datos_usuario['rol']);
                                    $icon = '';
                                    $badge_class = '';
                                    
                                    if ($rol === 'aprendiz') {
                                        $icon = '🎓';
                                        $badge_class = 'badge-aprendiz';
                                    } elseif ($rol === 'instructor') {
                                        $icon = '🧑‍🏫';
                                        $badge_class = 'badge-instructor';
                                    } elseif ($rol === 'administrador') {
                                        $icon = '👨‍💼';
                                        $badge_class = 'badge-admin';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> rounded-pill px-3 py-2 shadow-sm">
                                        <?php echo $icon . ' ' . ucfirst($datos_usuario['rol']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        


















                        <div class="col-md-9">
                            <div class="ps-md-4">
                                <h3 class="fw-bold text-dark profile-name mb-2">
                                    <?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?>
                                </h3>
                                <p class="text-muted profile-email mb-3">
                                    <i class="fas fa-envelope me-2 text-success"></i>
                                    <?php echo htmlspecialchars($datos_usuario['correo']); ?>
                                </p>
                                







                                <!-- Badges de estado -->
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 status-badge">
                                        <i class="fas fa-check-circle me-1"></i> Cuenta Aprobada
                                    </span>
                                    <span class="badge bg-info-subtle text-info border border-info px-3 py-2 status-badge">
                                        <i class="fas fa-clock me-1"></i> Última conexión: <?php echo date("d/m/Y H:i"); ?>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 status-badge">
                                        <i class="fas fa-calendar-alt me-1"></i> Miembro desde <?php echo date("d/m/Y", strtotime($datos_usuario['fecha_registro'])); ?>
                                    </span>
                                </div>








                                <!-- Botones de acción -->
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-success btn-hover px-4 py-2" onclick="mostrarSeccion('configuracion');">
                                        <i class="fas fa-camera me-2"></i> Cambiar Foto
                                    </button>
                                    <button class="btn btn-outline-success btn-hover px-4 py-2" onclick="mostrarSeccion('configuracion');">
                                        <i class="fas fa-cog me-2"></i> Configuración
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>





                
                <!-- Pestañas de navegación -->
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-fill border-bottom-0" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-personal" type="button" role="tab">
                                <i class="fas fa-user me-2"></i> Información Personal
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="estadisticas-tab" data-bs-toggle="tab" data-bs-target="#estadisticas" type="button" role="tab">
                                <i class="fas fa-chart-line me-2"></i> Estadísticas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="actividad-tab" data-bs-toggle="tab" data-bs-target="#actividad" type="button" role="tab">
                                <i class="fas fa-history me-2"></i> Actividad Reciente
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4" id="perfilTabsContent">
                        <!-- Tab 1: Información Personal -->
                        <div class="tab-pane fade show active" id="info-personal" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3 h-100">
                                        <h5 class="fw-bold text-success mb-4">
                                            <i class="fas fa-id-card me-2"></i> Datos de Identificación
                                        </h5>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-user-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Nombre Completo</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-id-card-alt text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Tipo de Documento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['tipo_documento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-id-badge text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Número de Documento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['numero_documento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3 h-100">
                                        <h5 class="fw-bold text-success mb-4">
                                            <i class="fas fa-info-circle me-2"></i> Información Adicional
                                        </h5>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-venus-mars text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Género</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars(ucfirst($datos_usuario['genero'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-birthday-cake text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Fecha de Nacimiento</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['fecha_nacimiento']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="info-icon">
                                                    <i class="fas fa-envelope text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block info-label">Correo Electrónico</small>
                                                    <strong class="text-dark info-value"><?php echo htmlspecialchars($datos_usuario['correo']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-user-shield fa-2x text-success"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Rol en el Sistema</h6>
                                        <h4 class="fw-bold text-dark info-value mb-0"><?php echo ucfirst($datos_usuario['rol']); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-calendar-check fa-2x text-info"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Fecha de Registro</h6>
                                        <h4 class="fw-bold text-dark info-value mb-0"><?php echo date("d/m/Y", strtotime($datos_usuario['fecha_registro'])); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center p-4 rounded-3 shadow-sm">
                                        <div class="stat-icon mb-3">
                                            <i class="fas fa-clock fa-2x text-warning"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">Estado de Cuenta</h6>
                                        <h4 class="fw-bold text-success mb-0">Activa</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Estadísticas según el rol -->
                        <div class="tab-pane fade" id="estadisticas" role="tabpanel">
                            <h5 class="fw-bold text-success mb-4">
                                <i class="fas fa-chart-bar me-2"></i> Panel de Estadísticas
                            </h5>

                            <?php if (strtolower($datos_usuario['rol']) === 'aprendiz'): ?>
                            <!-- Estadísticas para Aprendiz -->
                            <?php
                            // ==================== CONSULTAS PARA ESTADÍSTICAS DEL APRENDIZ ====================
                            
                            // Total de bitácoras requeridas (fijo en 12)
                            $total_bitacoras_requeridas = 12;
                            
                            // 1. Contar bitácoras completadas (estado 'completada')
                            $sql_completadas = "SELECT COUNT(*) as total 
                                               FROM bitacoras 
                                               WHERE aprendiz_id = ? AND estado = 'completada'";
                            $stmt_completadas = $conn->prepare($sql_completadas);
                            $stmt_completadas->bind_param("i", $id_usuario_actual);
                            $stmt_completadas->execute();
                            $result_completadas = $stmt_completadas->get_result();
                            $total_bitacoras_completadas = $result_completadas->fetch_assoc()['total'];
                            $stmt_completadas->close();
                            
                            // 2. Contar bitácoras aprobadas (estado 'aprobada')
                            $sql_aprobadas = "SELECT COUNT(*) as total 
                                             FROM bitacoras 
                                             WHERE aprendiz_id = ? AND estado = 'aprobada'";
                            $stmt_aprobadas = $conn->prepare($sql_aprobadas);
                            $stmt_aprobadas->bind_param("i", $id_usuario_actual);
                            $stmt_aprobadas->execute();
                            $result_aprobadas = $stmt_aprobadas->get_result();
                            $total_bitacoras_aprobadas = $result_aprobadas->fetch_assoc()['total'];
                            $stmt_aprobadas->close();
                            
                            // 3. Contar bitácoras pendientes (estado 'borrador' o 'completada' pero sin aprobar)
                            $sql_pendientes = "SELECT COUNT(*) as total 
                                              FROM bitacoras 
                                              WHERE aprendiz_id = ? AND estado IN ('borrador', 'completada', 'revisada')";
                            $stmt_pendientes = $conn->prepare($sql_pendientes);
                            $stmt_pendientes->bind_param("i", $id_usuario_actual);
                            $stmt_pendientes->execute();
                            $result_pendientes = $stmt_pendientes->get_result();
                            $total_bitacoras_pendientes = $result_pendientes->fetch_assoc()['total'];
                            $stmt_pendientes->close();
                            
                            // 4. Calcular porcentaje de progreso (bitácoras aprobadas / total requerido)
                            $porcentaje_progreso = $total_bitacoras_requeridas > 0 
                                ? round(($total_bitacoras_aprobadas / $total_bitacoras_requeridas) * 100) 
                                : 0;
                            
                            // 5. Calcular porcentaje faltante
                            $porcentaje_faltante = 100 - $porcentaje_progreso;
                            
                            // 6. Bitácoras faltantes
                            $bitacoras_faltantes = $total_bitacoras_requeridas - $total_bitacoras_aprobadas;
                            $bitacoras_faltantes = $bitacoras_faltantes < 0 ? 0 : $bitacoras_faltantes;
                            ?>
                            
                            <div class="row g-4">
                                <!-- Bitácoras Completadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_completadas; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras Completadas</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras Aprobadas -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-clipboard-check fa-3x text-success mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_aprobadas; ?></h3>
                                        <p class="text-muted mb-0">Bitácoras Aprobadas</p>
                                    </div>
                                </div>
                                
                                <!-- Bitácoras Pendientes -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $total_bitacoras_pendientes; ?></h3>
                                        <p class="text-muted mb-0">Pendientes de Aprobación</p>
                                    </div>
                                </div>
                                
                                <!-- Porcentaje Faltante -->
                                <div class="col-md-3">
                                    <div class="stat-box p-4 rounded-3 text-center">
                                        <i class="fas fa-percentage fa-3x text-info mb-3"></i>
                                        <h3 class="fw-bold mb-2"><?php echo $porcentaje_faltante; ?>%</h3>
                                        <p class="text-muted mb-0">Por Completar</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <!-- Progreso General -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-chart-line me-2"></i>
                                            Progreso General
                                        </h6>
                                        <div class="progress mb-2" style="height: 25px;">
                                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $porcentaje_progreso; ?>%;">
                                                <?php echo $porcentaje_progreso; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $total_bitacoras_aprobadas; ?> de <?php echo $total_bitacoras_requeridas; ?> bitácoras aprobadas
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Resumen de Estado -->
                                <div class="col-md-6">
                                    <div class="info-card p-4 rounded-3">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Resumen de Estado
                                        </h6>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><i class="fas fa-check-circle text-success me-2"></i>Aprobadas</span>
                                            <strong class="text-success"><?php echo $total_bitacoras_aprobadas; ?></strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><i class="fas fa-clock text-warning me-2"></i>Pendientes</span>
                                            <strong class="text-warning"><?php echo $total_bitacoras_pendientes; ?></strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <span><i class="fas fa-times-circle text-danger me-2"></i>Faltantes</span>
                                            <strong class="text-danger"><?php echo $bitacoras_faltantes; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>



                        

                        <!-- Tab 3: Actividad Reciente -->
                        <div class="tab-pane fade" id="actividad" role="tabpanel">
                            <h5 class="fw-bold text-success mb-4">
                                <i class="fas fa-history me-2"></i> Historial de Actividad
                            </h5>

                            <?php
                            function mapActividadIconoClase($tipo) {
                                switch ($tipo) {
                                    case 'login': return ['bg' => 'bg-primary', 'icon' => 'fas fa-sign-in-alt text-primary'];
                                    case 'logout': return ['bg' => 'bg-secondary', 'icon' => 'fas fa-sign-out-alt text-secondary'];
                                    case 'bitacora_creada': return ['bg' => 'bg-success', 'icon' => 'fas fa-upload text-success'];
                                    case 'bitacora_aprobada': return ['bg' => 'bg-success', 'icon' => 'fas fa-check-circle text-success'];
                                    case 'perfil_actualizado': return ['bg' => 'bg-info', 'icon' => 'fas fa-edit text-info'];
                                    case 'foto_actualizada': return ['bg' => 'bg-info', 'icon' => 'fas fa-camera text-info'];
                                    case 'contrasena_actualizada': return ['bg' => 'bg-warning', 'icon' => 'fas fa-key text-warning'];
                                    default: return ['bg' => 'bg-light', 'icon' => 'fas fa-info-circle text-muted'];
                                }
                            }
                            ?>

                            <?php // $actividades ya fue cargado arriba de forma segura ?>

                            <div class="timeline">
                                <?php if (!empty($actividades)): ?>
                                    <?php foreach ($actividades as $act): 
                                        $m = mapActividadIconoClase(strtolower($act['tipo']));
                                        $fechaFmt = date('d/m/Y H:i', strtotime($act['fecha']));
                                        $titulo = htmlspecialchars($act['titulo'] ?? ucfirst($act['tipo']));
                                        $desc = htmlspecialchars($act['descripcion'] ?? '');
                                    ?>
                                    <div class="timeline-item" id="actividad-<?= $act['id']; ?>" data-actividad-id="<?= $act['id']; ?>">
                                        <div class="timeline-marker <?= $m['bg']; ?>"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="fw-bold mb-0">
                                                    <i class="<?= $m['icon']; ?> me-2"></i>
                                                    <?= $titulo; ?>
                                                </h6>
                                                <div class="d-flex align-items-center gap-2">
                                                    <small class="text-muted me-2 mb-0"><?= $fechaFmt; ?></small>
                                                    <button type="button" class="btn-close" aria-label="Eliminar actividad" title="Eliminar actividad" onclick="eliminarActividad(<?= $act['id']; ?>)"></button>
                                                </div>
                                            </div>
                                            <?php if (!empty($desc)): ?>
                                                <p class="mb-0 text-muted"><?= $desc; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-bell-slash fa-2x d-block mb-2"></i>
                                        <small>No hay actividad reciente.</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="text-center mt-4">
                                <a class="btn btn-outline-success" href="#" onclick="return false;">
                                    <i class="fas fa-history me-2"></i>
                                    Ver historial completo
                                </a>
                            </div>
                        </div>
</div>
                    </div> <!-- end .card-body -->
                </div> <!-- end .card -->
            </div> <!-- end .col-12 col-xl-11 -->
        </div> <!-- end .row justify-content-center -->
    </div> <!-- end #perfil -->











 <div id="bitacora" class="py-4 app-section d-none">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card p-4">


                <div class="text-center mb-4">
                    <img src="imagenes/logoSena.png" alt="Logo SENA" class="mb-3" style="height: 80px;">
                    <h2 class="fw-bold">Bitácora de Seguimiento Etapa Productiva</h2>
                    <small>Proceso Gestión de Formación Profesional Integral</small><br>
                    <small>Formato Bitácora seguimiento Etapa productiva</small><br>
                    <strong class="text-uppercase">REGIONAL ANTIOQUIA</strong><br>
                    <strong class="text-uppercase">CENTRO DE FORMACIÓN MINERO AMBIENTAL</strong>
                </div>


                 <form id="bitacora-form" action="guardar_bitacora.php" method="POST" enctype="multipart/form-data">

                    <?php
                    // Mostrar mensaje de éxito o error al guardar bitácora
                    if (isset($_SESSION['mensaje_bitacora'])):
                    ?>
                        <div class="alert alert-<?= $_SESSION['tipo_mensaje'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $_SESSION['tipo_mensaje'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= $_SESSION['mensaje_bitacora'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <script>
                            // Mostrar SweetAlert si está disponible
                            <?php if ($_SESSION['tipo_mensaje'] === 'success'): ?>
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: '<?= $_SESSION['mensaje_bitacora'] ?>',
                                    confirmButtonText: 'Ok',
                                    confirmButtonColor: '#4CAF50'
                                });
                            <?php endif; ?>
                        </script>
                    <?php
                        // Limpiar mensaje de sesión
                        unset($_SESSION['mensaje_bitacora']);
                        unset($_SESSION['tipo_mensaje']);
                    endif;
                    ?>

                    <h4 class="text-center mb-3 text-success">Datos Generales</h4>
                    <div class="mb-3">
                        <label for="empresa" class="form-label"><i class="fas fa-building me-2"></i>Nombre de la empresa:</label>
                        <input type="text" name="empresa" id="empresa" class="form-control" required>
                    </div>


                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label for="nit" class="form-label"><i class="fas fa-id-card me-2"></i>NIT:</label>
                            <input type="text" name="nit" id="nit" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="bitacora_num" class="form-label"><i class="fas fa-file-alt me-2"></i>Bitácora N°:</label>
                            <input type="text" name="bitacora_num" id="bitacora_num" class="form-control" required>
                        </div>
                    </div>


                    <div class="mb-3">
                        <label for="periodo" class="form-label"><i class="fas fa-calendar-alt me-2"></i>Periodo:</label>
                        <input type="text" name="periodo" id="periodo" class="form-control" required>
                    </div>


                    <div class="mb-3">
                        <label for="jefe" class="form-label"><i class="fas fa-user-tie me-2"></i>Nombre del jefe inmediato/Responsable:</label>
                        <input type="text" name="jefe" id="jefe" class="form-control" required>
                    </div>


                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <label for="telefono_jefe" class="form-label"><i class="fas fa-phone me-2"></i>Teléfono de contacto:</label>
                            <input type="text" name="telefono_jefe" id="telefono_jefe" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="correo_jefe" class="form-label"><i class="fas fa-envelope me-2"></i>Correo electrónico:</label>
                            <input type="email" name="correo_jefe" id="correo_jefe" class="form-control" required>
                        </div>
                    </div>


                    <div class="my-5 p-4 bg-light rounded shadow-sm">
                        <h4 class="text-center mb-3 text-success">Modalidad de Etapa Productiva</h4>
                        <p class="text-center text-muted mb-4">Seleccione con una "X" el tipo de modalidad</p>


                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="checkbox-container d-block mb-3">Contrato de Aprendizaje
                                    <input type="checkbox" name="modalidad[]" value="contrato">
                                    <span class="checkmark"></span>
                                </label>
                                <label class="checkbox-container d-block mb-3">Vínculo Laboral o Contractual
                                    <input type="checkbox" name="modalidad[]" value="vinculo">
                                    <span class="checkmark"></span>
                                </label>
                                <label class="checkbox-container d-block mb-3">Proyecto Productivo
                                    <input type="checkbox" name="modalidad[]" value="proyecto">
                                    <span class="checkmark"></span>
                                </label>
                                <label class="checkbox-container d-block">Apoyo a una Unidad Productiva Familiar
                                    <input type="checkbox" name="modalidad[]" value="unidad">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="checkbox-container d-block mb-3">Apoyo a Institución Estatal, nacional, territorial, o una ONG o a entidad sin ánimo de lucro
                                    <input type="checkbox" name="modalidad[]" value="institucion">
                                    <span class="checkmark"></span>
                                </label>
                                <label class="checkbox-container d-block mb-3">Monitoria
                                    <input type="checkbox" name="modalidad[]" value="monitoria">
                                    <span class="checkmark"></span>
                                </label>
                                <label class="checkbox-container d-block">Pasantía
                                    <input type="checkbox" name="modalidad[]" value="pasantia">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                    </div>


                    <h4 class="text-center mb-3 text-success">Datos del Aprendiz</h4>
                    <div class="mb-3">
                        <label for="nombre_aprendiz" class="form-label"><i class="fas fa-user-graduate me-2"></i>Nombre del aprendiz:</label>
                        <input type="text" name="nombre_aprendiz" id="nombre_aprendiz" class="form-control" value="<?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?>" required>
                    </div>


                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label for="documento_aprendiz" class="form-label"><i class="fas fa-id-card me-2"></i>Documento Id.:</label>
                            <input type="text" name="documento_aprendiz" id="documento_aprendiz" class="form-control" value="<?php echo htmlspecialchars($datos_usuario['numero_documento']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono_aprendiz" class="form-label"><i class="fas fa-phone me-2"></i>Teléfono de contacto:</label>
                            <input type="text" name="telefono_aprendiz" id="telefono_aprendiz" class="form-control" required>
                        </div>
                    </div>


                    <div class="mb-3">
                        <label for="correo_aprendiz" class="form-label"><i class="fas fa-envelope me-2"></i>Correo electrónico institucional:</label>
                        <input type="email" name="correo_aprendiz" id="correo_aprendiz" class="form-control" value="<?php echo htmlspecialchars($datos_usuario['correo']); ?>" required>
                    </div>


                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <label for="ficha" class="form-label"><i class="fas fa-hashtag me-2"></i>Número de ficha:</label>
                            <input type="text" name="ficha" id="ficha" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="programa" class="form-label"><i class="fas fa-book me-2"></i>Programa de formación:</label>
                            <input type="text" name="programa" id="programa" class="form-control" required>
                        </div>
                    </div>


                    <h4 class="text-center mb-3 text-success">Actividades Desarrolladas</h4>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-striped text-center">
                            <thead>
                                <tr class="bg-secondary text-white">
                                    <th>Descripción de la Actividad</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Evidencia de Cumplimiento</th>
                                    <th>Observaciones, Inasistencias y/o Dificultades</th>
                                </tr>
                            </thead>
                            <tbody id="actividades-table-body">
                                <?php for ($i = 0; $i < 8; $i++): ?>
                                <tr>
                                    <td><input type="text" name="actividad[]" class="form-control border-0"></td>
                                    <td><input type="date" name="fecha_inicio[]" class="form-control border-0"></td>
                                    <td><input type="date" name="fecha_fin[]" class="form-control border-0"></td>
                                    <td><input type="text" name="evidencia[]" class="form-control border-0"></td>
                                    <td><input type="text" name="observaciones[]" class="form-control border-0"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>


                    <h4 class="text-center mb-3 text-success">Firmas</h4>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Importante:</strong> Todas las firmas son obligatorias. Puede subir imágenes de firmas digitales (PNG, JPG, JPEG) o firmas escaneadas.
                    </div>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'aprendiz'): ?>

                    <!-- Firma del Aprendiz -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-user-graduate me-2"></i>Firma del Aprendiz</label>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre_aprendiz_firma" class="form-label">Nombre completo del aprendiz:</label>
                            <input type="text" name="nombre_aprendiz_firma" id="nombre_aprendiz_firma" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?>" 
                                   readonly required>
                        </div>
                        <div class="col-md-6">
                            <label for="firma_aprendiz" class="form-label">Subir firma (imagen) <span class="text-danger">*</span></label>
                            <input type="file" name="firma_aprendiz" id="firma_aprendiz" 
                                   class="form-control" 
                                   accept="image/png,image/jpeg,image/jpg" 
                                   required>
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG (máx. 2MB)</small>
                            <div id="preview_firma_aprendiz" class="mt-2"></div>
                        </div>
                    </div>

                    <?php endif; ?>

                    <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['instructor','administrador'])): ?>

                    <!-- Firma del Instructor -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-chalkboard-teacher me-2"></i>Firma del Instructor de Seguimiento</label>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre_instructor" class="form-label">Nombre del instructor de seguimiento: <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_instructor" id="nombre_instructor" 
                                   class="form-control" 
                                   placeholder="Ingrese el nombre completo del instructor" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="firma_instructor" class="form-label">Subir firma (imagen) <span class="text-danger">*</span></label>
                            <input type="file" name="firma_instructor" id="firma_instructor" 
                                   class="form-control" 
                                   accept="image/png,image/jpeg,image/jpg" 
                                   required>
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG (máx. 2MB)</small>
                            <div id="preview_firma_instructor" class="mt-2"></div>
                        </div>
                    </div>

                    <!-- Firma del Jefe Inmediato (Opcional) -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-user-tie me-2"></i>Firma del Jefe Inmediato (Opcional)</label>
                        </div>
                        <div class="col-md-6">
                             <label for="nombre_jefe_firma" class="form-label">Nombre del jefe inmediato:</label>
                            <input type="text" name="nombre_jefe_firma" id="nombre_jefe_firma" 
                                   class="form-control" 
                                   placeholder="Ingrese el nombre si aplica">
                        </div>
                        <div class="col-md-6">
                            <label for="firma_jefe" class="form-label">Subir firma (imagen)</label>
                            <input type="file" name="firma_jefe" id="firma_jefe" 
                                   class="form-control" 
                                   accept="image/png,image/jpeg,image/jpg">
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG (máx. 2MB)</small>
                            <div id="preview_firma_jefe" class="mt-2"></div>
                        </div>
                    </div>

                    <?php endif; ?>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
                            <i class="fas fa-file-pdf me-2"></i>Exportar a PDF
                        </button>
                        <button type="button" class="btn btn-export-excel" onclick="exportarExcel()">
                            <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                        </button>
                        <button type="button" class="btn btn-export-word" onclick="exportarWord()">
                            <i class="fas fa-file-word me-2"></i>Exportar a Word
                        </button>
                        <button type="submit" class="btn btn-success-custom btn-lg">
                            <i class="fas fa-save me-2"></i>Guardar Bitácora
                        </button>
                    </div>
                </form>


            </div>
        </div>
    </div>
</div>
   <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        /* Navbar */
        header.navbar {
            background: #4CAF50;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand h1 {
            font-weight: 600;
            color: #fff;
        }
        
        /* Botón menú */
        .material-symbols-outlined[type="button"] {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .material-symbols-outlined[type="button"]:hover {
            color: #FFC107;
        }
        
        /* Menú lateral */
        .offcanvas .nav-link {
            transition: all 0.3s ease;
            color: #495057;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .offcanvas .nav-link:hover {
            background-color: #e6f4ea;
            color: #2E7D32 !important;
        }
        .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
            font-weight: bold;
        }

        /* Sección bienvenida */
        .welcome-section {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .welcome-section::after {
            content: "";
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(25deg);
        }

        /* Tarjetas de estadísticas */
        .stats-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 4px solid #4CAF50;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .stats-card span {
            font-size: 2.5rem;
            color: #4CAF50;
        }

        /* FIX PARA MODO OSCURO - Mantener texto negro en stats-card */
        .bg-dark .stats-card {
            background-color: #2c2c2c !important;
            border-left-color: #444 !important;
            color: #e0e0e0 !important;
        }
        .bg-dark .stats-card h4 {
            color: #e0e0e0 !important;
        }
        .bg-dark .stats-card p {
            color: #a0a0a0 !important;
        }
        .bg-dark .stats-card span {
            color: #66bb6a !important;
        }

        /* Botones */
        .btn-success-custom {
            background-color: #4CAF50;
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background-color: #2E7D32;
            transform: translateY(-2px);
        }
        /* Botón de eliminar */
        .btn-danger-custom {
            background-color: #dc3545;
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-danger-custom:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        /* ESTILOS PARA MODO OSCURO */
        body.bg-dark {
            background-color: #121212 !important;
            color: #e0e0e0;
        }
        .bg-dark .navbar {
            background-color: #212121 !important;
            box-shadow: 0 3px 8px rgba(255,255,255,0.1);
        }
        .bg-dark .navbar-brand h1 {
            color: #fff;
        }
        .bg-dark .offcanvas {
            background-color: #212121 !important;
            color: #e0e0e0;
        }
        .bg-dark .offcanvas-header .btn-close {
            filter: invert(1);
        }
        .bg-dark .offcanvas .nav-link {
            color: #bdbdbd;
        }
        .bg-dark .offcanvas .nav-link:hover {
            background-color: #333;
            color: #e0e0e0 !important;
        }
        .bg-dark .offcanvas .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .bg-dark .welcome-section {
            background: linear-gradient(135deg, #2e8700, #4CAF50) !important;
            box-shadow: 0 10px 25px rgba(255,255,255,0.15);
        }
        .bg-dark .card,
        .bg-dark .list-group-item,
        .bg-dark .form-control,
        .bg-dark .form-select,
        .bg-dark .input-group-text,
        .bg-dark .perfil-info {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        .bg-dark .card-title,
        .bg-dark h2,
        .bg-dark h4 {
            color: #e0e0e0 !important;
        }
        .bg-dark .text-muted {
            color: #a0a0a0 !important;
        }
        .bg-dark .text-success {
            color: #66bb6a !important;
        }
        .bg-dark .nav-link.active,
        .bg-dark .nav-link:hover {
            background-color: #4CAF50 !important;
            color: #fff !important;
        }
        .bg-dark .list-group-item:hover {
            background: rgba(57,169,0,0.1) !important;
        }
        .bg-dark .btn-outline-dark {
            color: #bdbdbd !important;
            border-color: #bdbdbd !important;
        }
        .bg-dark .btn-outline-dark:hover {
            background-color: #bdbdbd !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-danger {
            color: #ef5350 !important;
            border-color: #ef5350 !important;
        }
        .bg-dark .btn-outline-danger:hover {
            background-color: #ef5350 !important;
            color: #212121 !important;
        }
        .bg-dark .btn-outline-success {
            color: #66bb6a !important;
            border-color: #66bb6a !important;
        }
        .bg-dark .btn-outline-success:hover {
            background-color: #66bb6a !important;
            color: #212121 !important;
        }

        /* Estilos específicos para la tabla de fichas */
        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: rgba(0,0,0,.05);
        }
        .bg-dark .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: rgba(255,255,255,.05);
        }

        /* --- INICIO: MEJORAS PARA MODO OSCURO EN LA TABLA DE FICHAS --- */
        /* Estilo general de la tabla en modo oscuro */
        .bg-dark .table {
            color: #e0e0e0;
            border-color: #444;
        }

        /* Encabezados de la tabla */
        .bg-dark .table thead {
            color: #fff;
            background-color: #333;
            border-bottom: 2px solid #666;
        }

        /* Filas impares (alternas) de la tabla */
        .bg-dark .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: #2c2c2c;
            color: #e0e0e0;
        }

        /* Filas pares (normales) de la tabla */
        .bg-dark .table-striped>tbody>tr:nth-of-type(even)>* {
            background-color: #212121;
            color: #e0e0e0;
        }

        /* Efecto hover en las filas */
        .bg-dark .table-hover>tbody>tr:hover>* {
            background-color: #383838;
            color: #fff;
        }
        /* --- FIN: MEJORAS PARA MODO OSCURO EN LA TABLA DE FICHA --- */

        /* ======================= ESTILOS MEJORADOS PARA CONFIGURACIÓN ======================= */
        
        /* Contenedor principal de configuración */
        #configuracion {
            background: transparent;
            min-height: 100vh;
        }
        
        /* Header de configuración */
        .config-header {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 3rem;
            text-align: center;
            box-shadow: 0 15px 35px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .config-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .config-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .config-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modo oscuro para header */
        .bg-dark .config-header {
            background: linear-gradient(135deg, #2e8700 0%, #1B5E20 100%);
            box-shadow: 0 15px 35px rgba(46, 135, 0, 0.4);
        }
        
        /* Cards de configuración */
        .config-card {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .bg-dark .config-card {
            background: #1e1e1e;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .bg-dark .config-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        /* Header de cada card */
        .config-card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .bg-dark .config-card-header {
            background: linear-gradient(135deg, #2c2c2c 0%, #333333 100%);
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .config-card-header h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .bg-dark .config-card-header h4 {
            color: #ecf0f1;
        }
        
        .config-card-header .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        /* Body de cada card */
        .config-card-body {
            padding: 2rem;
        }
        
        /* Formularios mejorados */
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-label-enhanced {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bg-dark .form-label-enhanced {
            color: #bdc3c7;
        }
        
        .form-control-enhanced {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control-enhanced:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.15);
            background: #ffffff;
        }
        
        .bg-dark .form-control-enhanced {
            background: #2c2c2c;
            border-color: #444;
            color: #e0e0e0;
        }
        
        .bg-dark .form-control-enhanced:focus {
            background: #333;
            border-color: #66bb6a;
            box-shadow: 0 0 0 0.2rem rgba(102, 187, 106, 0.15);
        }


        /* ======================= PLACEHOLDERS PERSONALIZADOS ======================= */

        /* Modo claro */
        .form-control-enhanced::placeholder {
            color: #7f8c8d; /* gris suave */
            opacity: 1;
            transition: color 0.3s ease;
        }

/* Modo oscuro */
        .bg-dark .form-control-enhanced::placeholder {
            color: #b0b0b0; /* gris claro */
            opacity: 1;
        }

        /* Botones mejorados */
        .btn-enhanced {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-enhanced::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.5s ease;
        }
        
        .btn-enhanced:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-primary-enhanced {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
        }
        
        .btn-primary-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }
        
        .btn-danger-enhanced {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }
        
        .btn-warning-enhanced {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }
        
        .btn-success-enhanced {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border: none;
        }
        
        .btn-success-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
            background: linear-gradient(135deg, #45a049, #267326);
        }
        
        /* Sección de foto de perfil */
        .profile-section {
            text-align: center;
            padding: 2rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .bg-dark .profile-section {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .profile-image-enhanced {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .profile-image-enhanced:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .bg-dark .profile-image-enhanced {
            border-color: #2c2c2c;
        }
        
        .image-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(76, 175, 80, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-upload-overlay:hover {
            opacity: 1;
        }
        
        .image-upload-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        /* Sección de preferencias */
        .preferences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .preference-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .preference-card:hover {
            border-color: #4CAF50;
            background: #e8f5e8;
            transform: translateY(-3px);
        }
        
        .bg-dark .preference-card {
            background: #2c2c2c;
            border-color: #444;
        }
        
        .bg-dark .preference-card:hover {
            border-color: #66bb6a;
            background: #333;
        }
        
        .preference-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .preference-card h5 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .bg-dark .preference-card h5 {
            color: #ecf0f1;
        }
        
        .preference-card p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .bg-dark .preference-card p {
            color: #95a5a6;
        }
        
        /* Toggle switch mejorado */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        input:checked + .toggle-slider {
            background: #4CAF50;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        /* Animaciones de entrada */
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .fade-in-left {
            animation: fadeInLeft 0.8s ease-out;
        }
        
        .fade-in-right {
            animation: fadeInRight 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Separadores mejorados */
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #4CAF50, transparent);
            margin: 3rem 0;
            border: none;
        }
        
        .bg-dark .section-divider {
            background: linear-gradient(90deg, transparent, #66bb6a, transparent);
        }
        
        /* Estilos para Preferencias Avanzadas */
        .preference-section {
            background: #f8f9fa;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .preference-section:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .bg-dark .preference-section {
            background: #2c2c2c;
            border-color: #444 !important;
        }
        
        .bg-dark .preference-section:hover {
            background: #333;
        }
        
        .preference-section h5 {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .preference-section .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .bg-dark .preference-section .form-label {
            color: #e0e0e0;
        }
        
        .preference-section .form-select,
        .preference-section .form-control {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .bg-dark .preference-section .form-select,
        .bg-dark .preference-section .form-control {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }
        
        /* Responsive design mejorado */
        @media (max-width: 768px) {
            .config-header {
                padding: 2rem 1rem;
            }
            
            .config-header h1 {
                font-size: 2rem;
            }
            
            .config-card-body {
                padding: 1.5rem;
            }
            
            .preferences-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    /* Mejoras para notificaciones en modo oscuro */
.bg-dark .alert-danger {
    background-color: #dc3545 !important;
    color: #fff !important;
    border-color: #b02a37 !important;
}

.bg-dark .alert-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
    border-color: #d39e00 !important;
}

.bg-dark .alert-info {
    background-color: #17a2b8 !important;
    color: #fff !important;
    border-color: #117a8b !important;
}

.bg-dark .alert-success {
    background-color: #28a745 !important;
    color: #fff !important;
    border-color: #1e7e34 !important;
}

/* Animación suave para notificaciones */
.alert {
    animation: slideInRight 0.4s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Toasts con sombra mejorada */
.toast {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
/* Estilos para preferencias de notificaciones */
.notification-pref-item {
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.notification-pref-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.form-check-input:checked {
    background-color: #4CAF50;
    border-color: #4CAF50;
}

.form-check-input:focus {
    border-color: #4CAF50;
    box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.25);
}

/* Modo oscuro para preferencias de notificaciones */
.bg-dark .notification-pref-item {
    background: #2c2c2c;
    border-color: #444 !important;
}

.bg-dark .notification-pref-item:hover {
    background: #333;
}

.bg-dark .notification-pref-item h6 {
    color: #e0e0e0 !important;
}

.bg-dark .notification-pref-item small {
    color: #bdbdbd !important;
}    

/* Estilos para botón de eliminar notificación */
.btn-close-notif {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    padding: 0;
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/12px auto no-repeat;
    border: 0;
    border-radius: 50%;
    opacity: 0.5;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-close-notif:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.1);
    transform: scale(1.2);
}

.btn-close-notif:active {
    transform: scale(0.9);
}

/* Animación de eliminación */
.notificacion-eliminando {
    animation: slideOutRight 0.4s ease-out forwards;
}

@keyframes slideOutRight {
    0% {
        opacity: 1;
        transform: translateX(0);
    }
    100% {
        opacity: 0;
        transform: translateX(100%);
        height: 0;
        margin: 0;
        padding: 0;
    }
}

/* Modo oscuro para botón X */
.bg-dark .btn-close-notif {
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/12px auto no-repeat;
}

.bg-dark .btn-close-notif:hover {
    background-color: rgba(255, 255, 255, 0.2);
}
  /* Estilos para notificaciones horizontales en configuración */
.notification-pref-item-horizontal {
    background: #f8f9fa;
    transition: all 0.3s ease;
    border: 2px solid transparent !important;
}

.notification-pref-item-horizontal:hover {
    background: #e9ecef;
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    border-color: #4CAF50 !important;
}

.notification-icon-large {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Modo oscuro para notificaciones horizontales */
.bg-dark .notification-pref-item-horizontal {
    background: #2c2c2c;
    border-color: #444 !important;
}

.bg-dark .notification-pref-item-horizontal:hover {
    background: #333;
    border-color: #4CAF50 !important;
}

.bg-dark .notification-pref-item-horizontal h5 {
    color: #e0e0e0 !important;
}

.bg-dark .notification-pref-item-horizontal p {
    color: #bdbdbd !important;
}

/* Responsive para notificaciones horizontales */
@media (max-width: 991px) {
    .notification-icon-large {
        width: 50px;
        height: 50px;
    }
    
    .notification-icon-large i {
        font-size: 1.5rem !important;
    }
}

@media (max-width: 767px) {
    .notification-pref-item-horizontal {
        text-align: center;
    }
    
    .notification-pref-item-horizontal .d-flex {
        flex-direction: column !important;
    }
    
    .notification-icon-large {
        margin: 0 auto 1rem !important;
    }
}
  </style>

<div id="configuracion" class="py-5 app-section d-none">
    <!-- Header principal -->
    <div class="config-header fade-in-up">
        <h1><i class="fas fa-cogs me-3"></i>Configuración de Cuenta</h1>
        <p>Personaliza y gestiona tu cuenta de forma segura y profesional</p>
    </div>

    <div class="container-fluid">
        <div class="row g-4">
            
            <!-- Card 1: Información Personal -->
            <div class="col-lg-6 col-12">
                <div class="config-card fade-in-left">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            Información Personal
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <form action="actualizar_perfil.php" method="POST" class="form-section">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nombre" class="form-label form-label-enhanced">
                                        <i class="fas fa-user me-2"></i>Nombre Completo
                                    </label>
                                    <input type="text" 
                                           name="nombre" 
                                           id="nombre" 
                                           class="form-control form-control-enhanced" 
                                           value="<?php echo htmlspecialchars($datos_usuario['nombre'].' '.$datos_usuario['apellido']); ?>" 
                                           required>
                                </div>
                                <div class="col-12">
                                    <label for="correo" class="form-label form-label-enhanced">
                                        <i class="fas fa-envelope me-2"></i>Correo Electrónico
                                    </label>
                                    <input type="email" 
                                           name="correo" 
                                           id="correo" 
                                           class="form-control form-control-enhanced" 
                                           value="<?php echo htmlspecialchars($datos_usuario['correo']); ?>" 
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="fecha_nacimiento" class="form-label form-label-enhanced">
                                        <i class="fas fa-calendar me-2"></i>Fecha de Nacimiento
                                    </label>
                                    <input type="date" 
                                           name="fecha_nacimiento" 
                                           id="fecha_nacimiento" 
                                           class="form-control form-control-enhanced" 
                                           value="<?php echo htmlspecialchars($datos_usuario['fecha_nacimiento']); ?>" 
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="genero" class="form-label form-label-enhanced">
                                        <i class="fas fa-venus-mars me-2"></i>Género
                                    </label>
                                    <select name="genero" 
                                            id="genero" 
                                            class="form-select form-control-enhanced" 
                                            required>
                                        <option value="masculino" <?php echo ($datos_usuario['genero']=='masculino'?'selected':''); ?>>Masculino</option>
                                        <option value="femenino" <?php echo ($datos_usuario['genero']=='femenino'?'selected':''); ?>>Femenino</option>
                                        <option value="otro" <?php echo ($datos_usuario['genero']=='otro'?'selected':''); ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-enhanced btn-primary-enhanced w-100">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card 2: Foto de Perfil -->
            <div class="col-lg-6 col-12">
                <div class="config-card fade-in-right">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            Foto de Perfil
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="profile-section">
                            <div class="profile-image-container">
                                <img id="previewFoto" 
                                     src="<?php echo !empty($datos_usuario['foto_perfil']) ? htmlspecialchars($datos_usuario['foto_perfil']) : 'imagenes/perfil_default.png'; ?>" 
                                     alt="Foto actual" 
                                     class="profile-image-enhanced">
                                <div class="image-upload-overlay" onclick="document.getElementById('foto').click()">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            <h5 class="text-center mb-3"><?php echo htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']); ?></h5>
                        </div>
                        
                        <form action="subir_foto.php" method="POST" enctype="multipart/form-data">
                            <div class="form-section">
                                <label for="foto" class="form-label form-label-enhanced">
                                    <i class="fas fa-upload me-2"></i>Seleccionar Nueva Imagen
                                </label>
                                <input type="file" 
                                       name="foto" 
                                       id="foto" 
                                       accept="image/*" 
                                       class="form-control form-control-enhanced" 
                                       onchange="previewImagen(event)" 
                                       required>
                                <small class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Formatos aceptados: JPG, PNG, GIF (máx. 2MB)
                                </small>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-enhanced btn-primary-enhanced w-100">
                                    <i class="fas fa-upload me-2"></i>Actualizar Foto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Separador -->
        <hr class="section-divider">

        <div class="row g-4">

            <!-- Card 3: Seguridad -->
            <div class="col-lg-8 col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            Seguridad y Contraseña
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="alert alert-info border-0" role="alert">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>Seguridad:</strong> Actualiza tu contraseña regularmente para mantener tu cuenta segura.
                                </div>
                            </div>
                        </div>
                        
                        <form action="cambiar_contrasena.php" method="POST" class="form-section">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="contrasena_actual" class="form-label form-label-enhanced">
                                        <i class="fas fa-key me-2"></i>Contraseña Actual
                                    </label>
                                    <input type="password" 
                                           name="contrasena_actual" 
                                           id="contrasena_actual" 
                                           class="form-control form-control-enhanced" 
                                           required
                                           placeholder="Ingresa tu contraseña actual">
                                </div>
                                <div class="col-md-6">
                                    <label for="nueva_contrasena" class="form-label form-label-enhanced">
                                        <i class="fas fa-lock me-2"></i>Nueva Contraseña
                                    </label>
                                    <input type="password" 
                                           name="nueva_contrasena" 
                                           id="nueva_contrasena" 
                                           class="form-control form-control-enhanced" 
                                           required
                                           placeholder="Mínimo 8 caracteres">
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmar_contrasena" class="form-label form-label-enhanced">
                                        <i class="fas fa-check-circle me-2"></i>Confirmar Contraseña
                                    </label>
                                    <input type="password" 
                                           name="confirmar_contrasena" 
                                           id="confirmar_contrasena" 
                                           class="form-control form-control-enhanced" 
                                           required
                                           placeholder="Repite la nueva contraseña">
                                </div>
                                <div class="col-12">
                                    <div class="password-strength mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-enhanced btn-danger-enhanced">
                                        <i class="fas fa-shield-alt me-2"></i>Actualizar Contraseña
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card 4: Preferencias -->
            <div class="col-lg-4 col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            Preferencias
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="preferences-grid">
                            <!-- Tema de la aplicación -->
                            <div class="preference-card" onclick="toggleDarkMode()">
                                <div class="preference-icon">
                                    <i class="fas fa-moon"></i>
                                </div>
                                <h5>Tema Visual</h5>
                                <p>Cambia entre modo claro y oscuro</p>
                                <div class="toggle-switch">
                                    <input type="checkbox" id="dark-mode-toggle">
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                            
                       <!-- Información adicional -->
                        <div class="mt-4 p-3" style="background: #f8f9fa; border-radius: 10px;">
                            <h6 class="mb-2" style="color: #212529 !important;"><i class="fas fa-info-circle me-2 text-primary"></i>Información de Cuenta</h6>
                            <small class="text-muted" style="color: #495057 !important;">
                                <strong style="color: #212529 !important;">Rol:</strong> <?php echo $rolText; ?><br>
                                <strong style="color: #212529 !important;">Último acceso:</strong> <?php echo date("d/m/Y H:i"); ?><br>
                                <strong style="color: #212529 !important;">Estado:</strong> <span class="text-success">Activo</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Separador -->
        <hr class="section-divider">

        <!-- Card 4: Preferencias de Notificaciones (Nueva ubicación) -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            Preferencias de Notificaciones
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Elige qué tipo de notificaciones deseas recibir para mantenerte informado sobre tus bitácoras.
                                </p>
                            </div>
                        </div>

                        <form id="form-preferencias-notificaciones">
                            <div class="row g-4">
                                <!-- Bitácoras Vencidas -->
                                <div class="col-lg-4 col-md-6 col-12">
                                    <div class="notification-pref-item-horizontal p-4 border rounded h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="notification-icon-large bg-danger bg-opacity-10 text-danger rounded-circle p-3 me-3">
                                                <i class="fas fa-exclamation-circle fa-2x"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-0 fw-bold">Bitácoras Vencidas</h5>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="notificar_vencidas" name="notificar_vencidas" 
                                                       <?php echo $user_prefs['notificar_vencidas'] ? 'checked' : ''; ?>
                                                       style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            </div>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Recibir una alerta urgente si no entregas una bitácora a tiempo.
                                        </p>
                                    </div>
                                </div>

                                <!-- Próximas a Vencer -->
                                <div class="col-lg-4 col-md-6 col-12">
                                    <div class="notification-pref-item-horizontal p-4 border rounded h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="notification-icon-large bg-warning bg-opacity-10 text-warning rounded-circle p-3 me-3">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-0 fw-bold">Próximas a Vencer</h5>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="notificar_proximas" name="notificar_proximas" 
                                                       <?php echo $user_prefs['notificar_proximas'] ? 'checked' : ''; ?>
                                                       style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            </div>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Recibir un recordatorio cuando una bitácora esté cerca de su fecha límite.
                                        </p>
                                    </div>
                                </div>

                                <!-- Bitácoras Faltantes -->
                                <div class="col-lg-4 col-md-12 col-12">
                                    <div class="notification-pref-item-horizontal p-4 border rounded h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="notification-icon-large bg-info bg-opacity-10 text-info rounded-circle p-3 me-3">
                                                <i class="fas fa-info-circle fa-2x"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-0 fw-bold">Bitácoras Faltantes</h5>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="notificar_faltantes" name="notificar_faltantes" 
                                                       <?php echo $user_prefs['notificar_faltantes'] ? 'checked' : ''; ?>
                                                       style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            </div>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Recibir un aviso informativo sobre cuántas bitácoras te faltan por completar.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-enhanced btn-success-enhanced px-5">
                                        <i class="fas fa-save me-2"></i>Guardar Preferencias Básicas
                                    </button>
                                    <p class="text-muted mt-2 mb-0">
                                        <small>Los cambios se guardarán inmediatamente para tu cuenta.</small>
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Separador -->
        <hr class="section-divider">

        <!-- CENTRO DE PREFERENCIAS AVANZADAS -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            Centro de Preferencias Avanzadas
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="alert alert-info border-0 mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Personaliza tu experiencia:</strong> Configura cómo y cuándo deseas recibir notificaciones para optimizar tu productividad.
                        </div>

                        <form id="form-preferencias-avanzadas">
                            <div class="row g-4">
                                
                                <!-- 1. HORARIOS DE NOTIFICACIONES (NO MOLESTAR) -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-moon text-primary me-2"></i>
                                            Horario "No Molestar"
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Define un horario en el que no deseas recibir notificaciones
                                        </p>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="no_molestar_activo" name="no_molestar_activo"
                                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            <label class="form-check-label ms-2" for="no_molestar_activo">
                                                Activar modo "No Molestar"
                                            </label>
                                        </div>

                                        <div id="horario-no-molestar" style="display: none;">
                                            <div class="row g-3">
                                                <div class="col-6">
                                                    <label for="hora_inicio_nm" class="form-label">
                                                        <i class="fas fa-clock me-1"></i>Desde
                                                    </label>
                                                    <input type="time" class="form-control" id="hora_inicio_nm" 
                                                           name="hora_inicio_nm" value="22:00">
                                                </div>
                                                <div class="col-6">
                                                    <label for="hora_fin_nm" class="form-label">
                                                        <i class="fas fa-clock me-1"></i>Hasta
                                                    </label>
                                                    <input type="time" class="form-control" id="hora_fin_nm" 
                                                           name="hora_fin_nm" value="08:00">
                                                </div>
                                            </div>
                                            <small class="text-muted mt-2 d-block">
                                                <i class="fas fa-info-circle me-1"></i>
                                                No recibirás notificaciones durante este horario
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. FRECUENCIA DE RECORDATORIOS -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-bell text-warning me-2"></i>
                                            Frecuencia de Recordatorios
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Elige con qué anticipación deseas recibir recordatorios
                                        </p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                Recordar bitácoras próximas a vencer:
                                            </label>
                                            <select class="form-select" id="dias_recordatorio" name="dias_recordatorio">
                                                <option value="1">1 día antes</option>
                                                <option value="3" selected>3 días antes</option>
                                                <option value="5">5 días antes</option>
                                                <option value="7">7 días antes (1 semana)</option>
                                                <option value="14">14 días antes (2 semanas)</option>
                                            </select>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="recordatorios_multiples" 
                                                   name="recordatorios_multiples" checked>
                                            <label class="form-check-label" for="recordatorios_multiples">
                                                Recordatorios escalonados (14, 7, 3 y 1 día antes)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. NOTIFICACIONES POR EMAIL -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-envelope text-danger me-2"></i>
                                            Notificaciones por Email
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Recibe notificaciones importantes en tu correo electrónico
                                        </p>
                                        
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="email_vencidas" 
                                                   name="email_vencidas">
                                            <label class="form-check-label" for="email_vencidas">
                                                <i class="fas fa-exclamation-circle text-danger me-1"></i>
                                                Bitácoras vencidas
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="email_proximas" 
                                                   name="email_proximas">
                                            <label class="form-check-label" for="email_proximas">
                                                <i class="fas fa-clock text-warning me-1"></i>
                                                Recordatorios de vencimiento
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="email_logros" 
                                                   name="email_logros" checked>
                                            <label class="form-check-label" for="email_logros">
                                                <i class="fas fa-trophy text-success me-1"></i>
                                                Logros y felicitaciones
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="email_resumen" 
                                                   name="email_resumen" checked>
                                            <label class="form-check-label" for="email_resumen">
                                                <i class="fas fa-chart-line text-info me-1"></i>
                                                Resumen semanal
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. PREFERENCIAS DE RESUMEN SEMANAL -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-chart-bar text-success me-2"></i>
                                            Resumen Semanal
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Configura tu reporte semanal de progreso
                                        </p>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="resumen_semanal_activo" name="resumen_semanal_activo" checked
                                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            <label class="form-check-label ms-2" for="resumen_semanal_activo">
                                                Recibir resumen semanal
                                            </label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-calendar-week me-1"></i>
                                                Día de envío:
                                            </label>
                                            <select class="form-select" id="dia_resumen" name="dia_resumen">
                                                <option value="1" selected>Lunes</option>
                                                <option value="2">Martes</option>
                                                <option value="3">Miércoles</option>
                                                <option value="4">Jueves</option>
                                                <option value="5">Viernes</option>
                                                <option value="6">Sábado</option>
                                                <option value="7">Domingo</option>
                                            </select>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="incluir_estadisticas" 
                                                   name="incluir_estadisticas" checked>
                                            <label class="form-check-label" for="incluir_estadisticas">
                                                Incluir estadísticas detalladas
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="incluir_sugerencias" 
                                                   name="incluir_sugerencias" checked>
                                            <label class="form-check-label" for="incluir_sugerencias">
                                                Incluir sugerencias personalizadas
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. MENSAJES MOTIVACIONALES -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-heart text-danger me-2"></i>
                                            Mensajes Motivacionales
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Recibe mensajes de motivación para mantenerte enfocado
                                        </p>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="mensajes_motivacionales" name="mensajes_motivacionales" checked
                                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            <label class="form-check-label ms-2" for="mensajes_motivacionales">
                                                Activar mensajes motivacionales
                                            </label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-sync-alt me-1"></i>
                                                Frecuencia:
                                            </label>
                                            <select class="form-select" id="frecuencia_motivacion" name="frecuencia_motivacion">
                                                <option value="diario">Diario</option>
                                                <option value="cada_2_dias">Cada 2 días</option>
                                                <option value="semanal" selected>Semanal</option>
                                                <option value="quincenal">Quincenal</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. SONIDO DE NOTIFICACIONES -->
                                <div class="col-lg-6 col-12">
                                    <div class="preference-section p-4 border rounded">
                                        <h5 class="mb-3">
                                            <i class="fas fa-volume-up text-info me-2"></i>
                                            Sonido y Alertas
                                        </h5>
                                        <p class="text-muted small mb-3">
                                            Configura las alertas sonoras y visuales
                                        </p>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="sonido_notificaciones" name="sonido_notificaciones"
                                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            <label class="form-check-label ms-2" for="sonido_notificaciones">
                                                Activar sonido para notificaciones críticas
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="notif_navegador" 
                                                   name="notif_navegador">
                                            <label class="form-check-label" for="notif_navegador">
                                                <i class="fas fa-desktop me-1"></i>
                                                Notificaciones del navegador (push)
                                            </label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="badge_contador" 
                                                   name="badge_contador" checked>
                                            <label class="form-check-label" for="badge_contador">
                                                <i class="fas fa-bell me-1"></i>
                                                Mostrar contador en el ícono
                                            </label>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Botón de guardar -->
                            <div class="row mt-4">
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-enhanced btn-primary-enhanced px-5 py-3">
                                        <i class="fas fa-save me-2"></i>Guardar Preferencias Avanzadas
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary px-5 py-3 ms-2" onclick="resetearPreferencias()">
                                        <i class="fas fa-undo me-2"></i>Restaurar Valores por Defecto
                                    </button>
                                    <p class="text-muted mt-3 mb-0">
                                        <small><i class="fas fa-shield-alt me-1"></i>Tus preferencias se guardan de forma segura y se aplican inmediatamente.</small>
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <!-- Nueva sección: Estadísticas de cuenta -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="config-card fade-in-up">
                    <div class="config-card-header">
                        <h4>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            Estadísticas de cuenta
                        </h4>
                    </div>
                    <div class="config-card-body">
                        <div class="row g-4 text-center">
                            <div class="col-md-4 col-12">
                                <div class="stat-box p-4 rounded-3">
                                    <div class="stat-icon mb-2"><i class="fas fa-calendar-alt text-success"></i></div>
                                    <h6 class="mb-1">Registrado</h6>
                                    <p class="mb-0 text-muted">
                                        <?php echo !empty($datos_usuario['fecha_registro']) ? date('d/m/Y', strtotime($datos_usuario['fecha_registro'])) : '—'; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4 col-12">
                                <div class="stat-box p-4 rounded-3">
                                    <div class="stat-icon mb-2"><i class="fas fa-book text-info"></i></div>
                                    <h6 class="mb-1">Bitácoras subidas</h6>
                                    <p class="mb-0 text-muted">
                                        <?php echo intval($total_bitacoras); ?> / 12
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4 col-12">
                                <div class="stat-box p-4 rounded-3">
                                    <div class="stat-icon mb-2"><i class="fas fa-user-tag text-warning"></i></div>
                                    <h6 class="mb-1">Rol</h6>
                                    <p class="mb-0 text-muted">
                                        <?php echo htmlspecialchars($rolText ?? ucfirst($datos_usuario['rol'] ?? '')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Separador -->
        <hr class="section-divider">

<!-- Card 6: Acciones de Cuenta -->





















        <!-- Card 6: Acciones de Cuenta -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="config-card fade-in-up">
            <div class="config-card-header">
                <h4>
                    <div class="icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    Gestión de Cuenta
                </h4>
            </div>

            <div class="config-card-body">
                <div class="row g-3 justify-content-center">
                    <div class="col-lg-5 col-md-6">
                        <div class="d-grid">
                            <button class="btn btn-enhanced btn-primary-enhanced" onclick="showHelpModal()">
                                <i class="fas fa-question-circle me-2"></i>Centro de Ayuda
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-5 col-md-6">
                        <div class="d-grid">
                            <button class="btn btn-enhanced btn-danger-enhanced" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-warning border-0" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Mantén tu información actualizada para recibir notificaciones importantes sobre tu cuenta y actividades académicas.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>
</div>
</div>

<!-- ======================= FIN SECCIÓN DE CONFIGURACIÓN ======================= -->

</main>

<div id="success-message">
    <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>Bitácora guardada con éxito</h4>
</div>

<!-- Contenedor de Toasts -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-mostrar toasts de notificaciones al cargar la página
(function() {
    const notificaciones = <?php echo json_encode(array_slice($notificaciones_usuario, 0, 3)); ?>;
    const container = document.getElementById('toast-container');
    
    if (!container || !Array.isArray(notificaciones) || notificaciones.length === 0) return;
    
    notificaciones.forEach((n, index) => {
        const tipo = n.tipo || 'info';
        const icono = n.icono || 'fas fa-info-circle';
        const mensaje = n.mensaje || '';
        const fecha = new Date(n.fecha_creacion).toLocaleString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Mapear tipos de alerta a colores de toast de Bootstrap
        let bgClass = 'bg-info';
        let textClass = 'text-white';
        if (tipo === 'danger') { bgClass = 'bg-danger'; textClass = 'text-white'; }
        else if (tipo === 'warning') { bgClass = 'bg-warning'; textClass = 'text-dark'; }
        else if (tipo === 'success') { bgClass = 'bg-success'; textClass = 'text-white'; }
        
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center ${bgClass} ${textClass} border-0 mb-2`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="${icono} me-2"></i>
                    <strong>${mensaje}</strong>
                    <div class="mt-1"><small class="opacity-75">${fecha}</small></div>
                </div>
                <button type="button" class="btn-close ${textClass === 'text-white' ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        container.appendChild(toastEl);
        
        // Mostrar toast con delay escalonado
        setTimeout(() => {
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
        }, index * 300);
    });
})();
</script>
    <script>
// ============================================
// FUNCIONES DE EXPORTACIÓN DE BITÁCORA
// ============================================

/**
 * Exportar bitácora a PDF
 */
function exportarPDF() {
    const bitacoraForm = document.getElementById('bitacora-form');
    
    if (!bitacoraForm) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se encontró el formulario de bitácora',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Validar que haya datos en el formulario
    const empresa = document.getElementById('empresa')?.value;
    if (!empresa || empresa.trim() === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Formulario vacío',
            text: 'Por favor complete el formulario antes de exportar',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Mostrar mensaje de carga
    Swal.fire({
        title: 'Generando PDF...',
        text: 'Por favor espere mientras se genera el documento',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Recopilar datos del formulario
    const datos = {
        empresa: document.getElementById('empresa')?.value || '',
        nit: document.getElementById('nit')?.value || '',
        bitacora_num: document.getElementById('bitacora_num')?.value || '',
        periodo: document.getElementById('periodo')?.value || '',
        jefe: document.getElementById('jefe')?.value || '',
        telefono_jefe: document.getElementById('telefono_jefe')?.value || '',
        correo_jefe: document.getElementById('correo_jefe')?.value || '',
        modalidad: Array.from(document.querySelectorAll('input[name="modalidad[]"]:checked'))
            .map(cb => cb.value).join(', '),
        nombre_aprendiz: document.getElementById('nombre_aprendiz')?.value || '',
        documento_aprendiz: document.getElementById('documento_aprendiz')?.value || '',
        telefono_aprendiz: document.getElementById('telefono_aprendiz')?.value || '',
        correo_aprendiz: document.getElementById('correo_aprendiz')?.value || '',
        ficha: document.getElementById('ficha')?.value || '',
        programa: document.getElementById('programa')?.value || '',
        nombre_instructor: document.getElementById('nombre_instructor')?.value || '',
        nombre_jefe_firma: document.getElementById('nombre_jefe_firma')?.value || ''
    };

    // Recopilar actividades
    const actividades = [];
    const actividadInputs = document.querySelectorAll('input[name="actividad[]"]');
    actividadInputs.forEach((input, index) => {
        const actividad = input.value;
        const fechaInicio = document.querySelectorAll('input[name="fecha_inicio[]"]')[index]?.value || '';
        const fechaFin = document.querySelectorAll('input[name="fecha_fin[]"]')[index]?.value || '';
        const evidencia = document.querySelectorAll('input[name="evidencia[]"]')[index]?.value || '';
        const observaciones = document.querySelectorAll('input[name="observaciones[]"]')[index]?.value || '';
        
        if (actividad || fechaInicio || fechaFin || evidencia || observaciones) {
            actividades.push({ actividad, fechaInicio, fechaFin, evidencia, observaciones });
        }
    });

    // Crear contenido HTML personalizado para PDF
    const pdfContent = document.createElement('div');
    pdfContent.style.padding = '20px';
    pdfContent.style.fontFamily = 'Arial, sans-serif';
    pdfContent.style.fontSize = '11pt';
    pdfContent.style.lineHeight = '1.4';
    pdfContent.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="imagenes/logoSena.png" alt="Logo SENA" style="height: 60px; margin-bottom: 10px;">
            <h2 style="color: #2E7D32; margin: 10px 0; font-size: 16pt;">Bitácora de Seguimiento Etapa Productiva</h2>
            <p style="margin: 5px 0; font-size: 9pt;">Proceso Gestión de Formación Profesional Integral</p>
            <p style="margin: 5px 0; font-size: 9pt;">Formato Bitácora seguimiento Etapa productiva</p>
            <p style="margin: 5px 0; font-weight: bold; font-size: 10pt;">REGIONAL ANTIOQUIA</p>
            <p style="margin: 5px 0; font-weight: bold; font-size: 10pt;">CENTRO DE FORMACIÓN MINERO AMBIENTAL</p>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="background-color: #4CAF50; color: white; padding: 8px; font-size: 12pt; text-align: center;">DATOS GENERALES</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; width: 35%; background-color: #e8f5e9; font-weight: bold;">Nombre de la empresa:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.empresa}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">NIT:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.nit}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Bitácora N°:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.bitacora_num}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Periodo:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.periodo}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Nombre del jefe inmediato:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.jefe}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Teléfono de contacto:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.telefono_jefe}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Correo electrónico:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.correo_jefe}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Modalidad de Etapa Productiva:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.modalidad}</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="background-color: #4CAF50; color: white; padding: 8px; font-size: 12pt; text-align: center;">DATOS DEL APRENDIZ</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; width: 35%; background-color: #e8f5e9; font-weight: bold;">Nombre del aprendiz:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.nombre_aprendiz}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Documento Id.:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.documento_aprendiz}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Teléfono de contacto:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.telefono_aprendiz}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Correo electrónico institucional:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.correo_aprendiz}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Número de ficha:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.ficha}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Programa de formación:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.programa}</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="background-color: #4CAF50; color: white; padding: 8px; font-size: 12pt; text-align: center;">ACTIVIDADES DESARROLLADAS</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <thead>
                    <tr style="background-color: #6c757d; color: white;">
                        <th style="border: 1px solid #333; padding: 6px; font-size: 9pt;">Descripción de la Actividad</th>
                        <th style="border: 1px solid #333; padding: 6px; font-size: 9pt; width: 12%;">Fecha Inicio</th>
                        <th style="border: 1px solid #333; padding: 6px; font-size: 9pt; width: 12%;">Fecha Fin</th>
                        <th style="border: 1px solid #333; padding: 6px; font-size: 9pt; width: 15%;">Evidencia</th>
                        <th style="border: 1px solid #333; padding: 6px; font-size: 9pt; width: 18%;">Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${actividades.map(act => `
                        <tr>
                            <td style="border: 1px solid #333; padding: 6px; font-size: 9pt;">${act.actividad}</td>
                            <td style="border: 1px solid #333; padding: 6px; font-size: 9pt; text-align: center;">${act.fechaInicio}</td>
                            <td style="border: 1px solid #333; padding: 6px; font-size: 9pt; text-align: center;">${act.fechaFin}</td>
                            <td style="border: 1px solid #333; padding: 6px; font-size: 9pt;">${act.evidencia}</td>
                            <td style="border: 1px solid #333; padding: 6px; font-size: 9pt;">${act.observaciones}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="background-color: #4CAF50; color: white; padding: 8px; font-size: 12pt; text-align: center;">FIRMAS</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; width: 35%; background-color: #e8f5e9; font-weight: bold;">Nombre del Aprendiz:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.nombre_aprendiz}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Nombre del Instructor de Seguimiento:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.nombre_instructor}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #333; padding: 6px; background-color: #e8f5e9; font-weight: bold;">Nombre del Jefe Inmediato:</td>
                    <td style="border: 1px solid #333; padding: 6px;">${datos.nombre_jefe_firma}</td>
                </tr>
            </table>
        </div>
    `;

    // Configuración de html2pdf
    const opt = {
        margin: [10, 10, 10, 10],
        filename: `bitacora_${datos.bitacora_num || Date.now()}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            logging: false
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'letter', 
            orientation: 'portrait' 
        }
    };

    // Generar PDF
    html2pdf().set(opt).from(pdfContent).save().then(() => {
        Swal.fire({
            icon: 'success',
            title: '¡PDF Generado!',
            text: 'El archivo PDF se ha descargado correctamente',
            confirmButtonColor: '#4CAF50',
            timer: 3000
        });
    }).catch(error => {
        console.error('Error al generar PDF:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al generar PDF',
            text: 'Ocurrió un error al generar el documento. Por favor intente nuevamente.',
            confirmButtonColor: '#4CAF50'
        });
    });
}

/**
 * Exportar bitácora a Excel
 */
function exportarExcel() {
    const bitacoraForm = document.getElementById('bitacora-form');
    
    if (!bitacoraForm) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se encontró el formulario de bitácora',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Validar que haya datos en el formulario
    const empresa = document.getElementById('empresa')?.value;
    if (!empresa || empresa.trim() === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Formulario vacío',
            text: 'Por favor complete el formulario antes de exportar',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Mostrar mensaje de carga
    Swal.fire({
        title: 'Generando Excel...',
        text: 'Por favor espere mientras se genera el archivo',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Recopilar datos del formulario
        const datos = {
            // Datos Generales
            empresa: document.getElementById('empresa')?.value || '',
            nit: document.getElementById('nit')?.value || '',
            bitacora_num: document.getElementById('bitacora_num')?.value || '',
            periodo: document.getElementById('periodo')?.value || '',
            jefe: document.getElementById('jefe')?.value || '',
            telefono_jefe: document.getElementById('telefono_jefe')?.value || '',
            correo_jefe: document.getElementById('correo_jefe')?.value || '',
            
            // Modalidad
            modalidad: Array.from(document.querySelectorAll('input[name="modalidad[]"]:checked'))
                .map(cb => cb.value).join(', '),
            
            // Datos del Aprendiz
            nombre_aprendiz: document.getElementById('nombre_aprendiz')?.value || '',
            documento_aprendiz: document.getElementById('documento_aprendiz')?.value || '',
            telefono_aprendiz: document.getElementById('telefono_aprendiz')?.value || '',
            correo_aprendiz: document.getElementById('correo_aprendiz')?.value || '',
            ficha: document.getElementById('ficha')?.value || '',
            programa: document.getElementById('programa')?.value || '',
            
            // Firmas
            nombre_instructor: document.getElementById('nombre_instructor')?.value || '',
            nombre_jefe_firma: document.getElementById('nombre_jefe_firma')?.value || ''
        };

        // Recopilar actividades
        const actividades = [];
        const actividadInputs = document.querySelectorAll('input[name="actividad[]"]');
        actividadInputs.forEach((input, index) => {
            const actividad = input.value;
            const fechaInicio = document.querySelectorAll('input[name="fecha_inicio[]"]')[index]?.value || '';
            const fechaFin = document.querySelectorAll('input[name="fecha_fin[]"]')[index]?.value || '';
            const evidencia = document.querySelectorAll('input[name="evidencia[]"]')[index]?.value || '';
            const observaciones = document.querySelectorAll('input[name="observaciones[]"]')[index]?.value || '';
            
            if (actividad || fechaInicio || fechaFin || evidencia || observaciones) {
                actividades.push({
                    actividad,
                    fechaInicio,
                    fechaFin,
                    evidencia,
                    observaciones
                });
            }
        });

        // Crear contenido HTML para Excel con formato mejorado
        let htmlContent = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta charset="UTF-8">
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Bitácora</x:Name>
                                <x:WorksheetOptions>
                                    <x:Print>
                                        <x:ValidPrinterInfo/>
                                    </x:Print>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <style>
                    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
                    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                    th, td { border: 1px solid #333; padding: 8px; vertical-align: top; }
                    .header-logo { text-align: center; margin-bottom: 20px; }
                    .header-title { text-align: center; color: #2E7D32; font-size: 16pt; font-weight: bold; margin: 10px 0; }
                    .header-subtitle { text-align: center; font-size: 9pt; margin: 5px 0; }
                    .header-region { text-align: center; font-weight: bold; font-size: 10pt; margin: 5px 0; }
                    .section-header { background-color: #4CAF50; color: white; font-weight: bold; text-align: center; padding: 10px; font-size: 12pt; }
                    .label-cell { background-color: #e8f5e9; font-weight: bold; width: 35%; }
                    .table-header { background-color: #6c757d; color: white; font-weight: bold; text-align: center; }
                </style>
            </head>
            <body>
                <div class="header-logo">
                    <p style="font-size: 14pt; font-weight: bold; color: #2E7D32;">SENA</p>
                </div>
                <h1 class="header-title">BITÁCORA DE SEGUIMIENTO ETAPA PRODUCTIVA</h1>
                <p class="header-subtitle">Proceso Gestión de Formación Profesional Integral</p>
                <p class="header-subtitle">Formato Bitácora seguimiento Etapa productiva</p>
                <p class="header-region">REGIONAL ANTIOQUIA</p>
                <p class="header-region">CENTRO DE FORMACIÓN MINERO AMBIENTAL</p>
                <br>
                
                <h3 class="section-header">DATOS GENERALES</h3>
                <table>
                    <tr><td class="label-cell">Nombre de la empresa:</td><td>${datos.empresa}</td></tr>
                    <tr><td class="label-cell">NIT:</td><td>${datos.nit}</td></tr>
                    <tr><td class="label-cell">Bitácora N°:</td><td>${datos.bitacora_num}</td></tr>
                    <tr><td class="label-cell">Periodo:</td><td>${datos.periodo}</td></tr>
                    <tr><td class="label-cell">Nombre del jefe inmediato:</td><td>${datos.jefe}</td></tr>
                    <tr><td class="label-cell">Teléfono de contacto:</td><td>${datos.telefono_jefe}</td></tr>
                    <tr><td class="label-cell">Correo electrónico:</td><td>${datos.correo_jefe}</td></tr>
                    <tr><td class="label-cell">Modalidad de Etapa Productiva:</td><td>${datos.modalidad}</td></tr>
                </table>
                
                <h3 class="section-header">DATOS DEL APRENDIZ</h3>
                <table>
                    <tr><td class="label-cell">Nombre del aprendiz:</td><td>${datos.nombre_aprendiz}</td></tr>
                    <tr><td class="label-cell">Documento Id.:</td><td>${datos.documento_aprendiz}</td></tr>
                    <tr><td class="label-cell">Teléfono de contacto:</td><td>${datos.telefono_aprendiz}</td></tr>
                    <tr><td class="label-cell">Correo electrónico institucional:</td><td>${datos.correo_aprendiz}</td></tr>
                    <tr><td class="label-cell">Número de ficha:</td><td>${datos.ficha}</td></tr>
                    <tr><td class="label-cell">Programa de formación:</td><td>${datos.programa}</td></tr>
                </table>
                
                <h3 class="section-header">ACTIVIDADES DESARROLLADAS</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="table-header">Descripción de la Actividad</th>
                            <th class="table-header">Fecha Inicio</th>
                            <th class="table-header">Fecha Fin</th>
                            <th class="table-header">Evidencia de Cumplimiento</th>
                            <th class="table-header">Observaciones, Inasistencias y/o Dificultades</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Agregar actividades
        actividades.forEach(act => {
            htmlContent += `
                <tr>
                    <td>${act.actividad}</td>
                    <td>${act.fechaInicio}</td>
                    <td>${act.fechaFin}</td>
                    <td>${act.evidencia}</td>
                    <td>${act.observaciones}</td>
                </tr>
            `;
        });

        htmlContent += `
                    </tbody>
                </table>
                
                <h3 class="section-header">FIRMAS</h3>
                <table>
                    <tr><td class="label-cell">Nombre del Aprendiz:</td><td>${datos.nombre_aprendiz}</td></tr>
                    <tr><td class="label-cell">Nombre del Instructor de Seguimiento:</td><td>${datos.nombre_instructor}</td></tr>
                    <tr><td class="label-cell">Nombre del Jefe Inmediato:</td><td>${datos.nombre_jefe_firma}</td></tr>
                </table>
            </body>
            </html>
        `;

        // Crear blob y descargar
        const blob = new Blob(['\ufeff', htmlContent], { type: 'application/vnd.ms-excel' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `bitacora_${datos.bitacora_num || Date.now()}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire({
            icon: 'success',
            title: '¡Excel Generado!',
            text: 'El archivo Excel se ha descargado correctamente',
            confirmButtonColor: '#4CAF50',
            timer: 3000
        });
    } catch (error) {
        console.error('Error al generar Excel:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al generar Excel',
            text: 'Ocurrió un error al generar el archivo. Por favor intente nuevamente.',
            confirmButtonColor: '#4CAF50'
        });
    }
}

/**
 * Exportar bitácora a Word
 */
function exportarWord() {
    const bitacoraForm = document.getElementById('bitacora-form');
    
    if (!bitacoraForm) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se encontró el formulario de bitácora',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Validar que haya datos en el formulario
    const empresa = document.getElementById('empresa')?.value;
    if (!empresa || empresa.trim() === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Formulario vacío',
            text: 'Por favor complete el formulario antes de exportar',
            confirmButtonColor: '#4CAF50'
        });
        return;
    }

    // Mostrar mensaje de carga
    Swal.fire({
        title: 'Generando Word...',
        text: 'Por favor espere mientras se genera el documento',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Recopilar datos del formulario
        const datos = {
            // Datos Generales
            empresa: document.getElementById('empresa')?.value || '',
            nit: document.getElementById('nit')?.value || '',
            bitacora_num: document.getElementById('bitacora_num')?.value || '',
            periodo: document.getElementById('periodo')?.value || '',
            jefe: document.getElementById('jefe')?.value || '',
            telefono_jefe: document.getElementById('telefono_jefe')?.value || '',
            correo_jefe: document.getElementById('correo_jefe')?.value || '',
            
            // Modalidad
            modalidad: Array.from(document.querySelectorAll('input[name="modalidad[]"]:checked'))
                .map(cb => cb.value).join(', '),
            
            // Datos del Aprendiz
            nombre_aprendiz: document.getElementById('nombre_aprendiz')?.value || '',
            documento_aprendiz: document.getElementById('documento_aprendiz')?.value || '',
            telefono_aprendiz: document.getElementById('telefono_aprendiz')?.value || '',
            correo_aprendiz: document.getElementById('correo_aprendiz')?.value || '',
            ficha: document.getElementById('ficha')?.value || '',
            programa: document.getElementById('programa')?.value || '',
            
            // Firmas
            nombre_instructor: document.getElementById('nombre_instructor')?.value || '',
            nombre_jefe_firma: document.getElementById('nombre_jefe_firma')?.value || ''
        };

        // Recopilar actividades
        const actividades = [];
        const actividadInputs = document.querySelectorAll('input[name="actividad[]"]');
        actividadInputs.forEach((input, index) => {
            const actividad = input.value;
            const fechaInicio = document.querySelectorAll('input[name="fecha_inicio[]"]')[index]?.value || '';
            const fechaFin = document.querySelectorAll('input[name="fecha_fin[]"]')[index]?.value || '';
            const evidencia = document.querySelectorAll('input[name="evidencia[]"]')[index]?.value || '';
            const observaciones = document.querySelectorAll('input[name="observaciones[]"]')[index]?.value || '';
            
            if (actividad || fechaInicio || fechaFin || evidencia || observaciones) {
                actividades.push({
                    actividad,
                    fechaInicio,
                    fechaFin,
                    evidencia,
                    observaciones
                });
            }
        });

        // Crear contenido HTML para Word (formato compatible con MS Word)
        let htmlContent = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" 
                  xmlns:w="urn:schemas-microsoft-com:office:word"
                  xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <xml>
                    <w:WordDocument>
                        <w:View>Print</w:View>
                        <w:Zoom>90</w:Zoom>
                        <w:DoNotOptimizeForBrowser/>
                    </w:WordDocument>
                </xml>
                <style>
                    @page {
                        size: 8.5in 11in;
                        margin: 0.75in 0.75in 0.75in 0.75in;
                    }
                    body {
                        font-family: 'Calibri', 'Arial', sans-serif;
                        font-size: 11pt;
                        line-height: 1.4;
                    }
                    .header-section {
                        text-align: center;
                        margin-bottom: 20pt;
                    }
                    .logo-text {
                        font-size: 16pt;
                        font-weight: bold;
                        color: #2E7D32;
                        margin-bottom: 8pt;
                    }
                    h1 {
                        text-align: center;
                        color: #2E7D32;
                        font-size: 16pt;
                        font-weight: bold;
                        margin: 8pt 0;
                    }
                    .subtitle {
                        text-align: center;
                        font-size: 9pt;
                        margin: 4pt 0;
                    }
                    .region-text {
                        text-align: center;
                        font-weight: bold;
                        font-size: 10pt;
                        margin: 4pt 0;
                    }
                    h3 {
                        background-color: #4CAF50;
                        color: white;
                        padding: 8pt;
                        font-size: 12pt;
                        font-weight: bold;
                        text-align: center;
                        margin-top: 15pt;
                        margin-bottom: 10pt;
                    }
                    table {
                        border-collapse: collapse;
                        width: 100%;
                        margin-bottom: 15pt;
                    }
                    th, td {
                        border: 1pt solid #333333;
                        padding: 6pt;
                        vertical-align: top;
                    }
                    .label-cell {
                        background-color: #e8f5e9;
                        font-weight: bold;
                        width: 35%;
                        text-align: left;
                    }
                    .data-cell {
                        text-align: left;
                    }
                    .table-header {
                        background-color: #6c757d;
                        color: white;
                        font-weight: bold;
                        text-align: center;
                        font-size: 9pt;
                    }
                    .activity-cell {
                        font-size: 9pt;
                    }
                </style>
            </head>
            <body>
                <div class="header-section">
                    <p class="logo-text">SENA</p>
                    <h1>BITÁCORA DE SEGUIMIENTO ETAPA PRODUCTIVA</h1>
                    <p class="subtitle">Proceso Gestión de Formación Profesional Integral</p>
                    <p class="subtitle">Formato Bitácora seguimiento Etapa productiva</p>
                    <p class="region-text">REGIONAL ANTIOQUIA</p>
                    <p class="region-text">CENTRO DE FORMACIÓN MINERO AMBIENTAL</p>
                </div>
                
                <h3>DATOS GENERALES</h3>
                <table>
                    <tr><td class="label-cell">Nombre de la empresa:</td><td class="data-cell">${datos.empresa}</td></tr>
                    <tr><td class="label-cell">NIT:</td><td class="data-cell">${datos.nit}</td></tr>
                    <tr><td class="label-cell">Bitácora N°:</td><td class="data-cell">${datos.bitacora_num}</td></tr>
                    <tr><td class="label-cell">Periodo:</td><td class="data-cell">${datos.periodo}</td></tr>
                    <tr><td class="label-cell">Nombre del jefe inmediato:</td><td class="data-cell">${datos.jefe}</td></tr>
                    <tr><td class="label-cell">Teléfono de contacto:</td><td class="data-cell">${datos.telefono_jefe}</td></tr>
                    <tr><td class="label-cell">Correo electrónico:</td><td class="data-cell">${datos.correo_jefe}</td></tr>
                    <tr><td class="label-cell">Modalidad de Etapa Productiva:</td><td class="data-cell">${datos.modalidad}</td></tr>
                </table>
                
                <h3>DATOS DEL APRENDIZ</h3>
                <table>
                    <tr><td class="label-cell">Nombre del aprendiz:</td><td class="data-cell">${datos.nombre_aprendiz}</td></tr>
                    <tr><td class="label-cell">Documento Id.:</td><td class="data-cell">${datos.documento_aprendiz}</td></tr>
                    <tr><td class="label-cell">Teléfono de contacto:</td><td class="data-cell">${datos.telefono_aprendiz}</td></tr>
                    <tr><td class="label-cell">Correo electrónico institucional:</td><td class="data-cell">${datos.correo_aprendiz}</td></tr>
                    <tr><td class="label-cell">Número de ficha:</td><td class="data-cell">${datos.ficha}</td></tr>
                    <tr><td class="label-cell">Programa de formación:</td><td class="data-cell">${datos.programa}</td></tr>
                </table>
                
                <h3>ACTIVIDADES DESARROLLADAS</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="table-header">Descripción de la Actividad</th>
                            <th class="table-header">Fecha Inicio</th>
                            <th class="table-header">Fecha Fin</th>
                            <th class="table-header">Evidencia de Cumplimiento</th>
                            <th class="table-header">Observaciones, Inasistencias y/o Dificultades</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Agregar actividades
        actividades.forEach(act => {
            htmlContent += `
                <tr>
                    <td class="activity-cell">${act.actividad}</td>
                    <td class="activity-cell" style="text-align: center;">${act.fechaInicio}</td>
                    <td class="activity-cell" style="text-align: center;">${act.fechaFin}</td>
                    <td class="activity-cell">${act.evidencia}</td>
                    <td class="activity-cell">${act.observaciones}</td>
                </tr>
            `;
        });

        htmlContent += `
                    </tbody>
                </table>
                
                <h3>FIRMAS</h3>
                <table>
                    <tr><td class="label-cell">Nombre del Aprendiz:</td><td class="data-cell">${datos.nombre_aprendiz}</td></tr>
                    <tr><td class="label-cell">Nombre del Instructor de Seguimiento:</td><td class="data-cell">${datos.nombre_instructor}</td></tr>
                    <tr><td class="label-cell">Nombre del Jefe Inmediato:</td><td class="data-cell">${datos.nombre_jefe_firma}</td></tr>
                </table>
            </body>
            </html>
        `;

        // Crear blob y descargar como documento Word
        const blob = new Blob(['\ufeff', htmlContent], { 
            type: 'application/msword' 
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `bitacora_${datos.bitacora_num || Date.now()}.doc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire({
            icon: 'success',
            title: '¡Word Generado!',
            text: 'El archivo Word se ha descargado correctamente',
            confirmButtonColor: '#4CAF50',
            timer: 3000
        });
    } catch (error) {
        console.error('Error al generar Word:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al generar Word',
            text: 'Ocurrió un error al generar el documento. Por favor intente nuevamente.',
            confirmButtonColor: '#4CAF50'
        });
    }
}

function mostrarSeccion(id) {
        // Ocultar todas las secciones con clase app-section
        document.querySelectorAll('.app-section').forEach(sec => {
            sec.classList.add('d-none');
        });

        // Mostrar la sección activa
        const activeElement = document.getElementById(id);
        if (activeElement) {
            activeElement.classList.remove('d-none');
            try { window.location.hash = id; } catch (e) {}
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) {}
        }

        const sideMenu = document.getElementById('sideMenu');
        if (sideMenu) {
            let offcanvas = bootstrap.Offcanvas.getInstance(sideMenu);
            if (!offcanvas) {
                try { offcanvas = new bootstrap.Offcanvas(sideMenu); } catch (e) {}
            }
            if (offcanvas) {
                offcanvas.hide();
            }
        }

        // Limpieza defensiva: eliminar posibles backdrops que bloqueen clics
        try {
            document.querySelectorAll('.offcanvas-backdrop').forEach(el => el.remove());
            // Quitar estilos inline que dejan el body sin scroll/clicks
            if (document.body.style.overflow) document.body.style.overflow = '';
            if (document.body.style.paddingRight) document.body.style.paddingRight = '';
        } catch (e) {}

        document.querySelectorAll('.offcanvas-body .nav-link').forEach(link => link.classList.remove('active'));
        const linkToShow = document.querySelector(`.offcanvas-body a[onclick="mostrarSeccion('${id}')"]`);
        if (linkToShow) {
            linkToShow.classList.add('active');
        }
        return false;
    }

    function previewImagen(event) {
        const reader = new FileReader();
        reader.onload = function() {
            document.getElementById('previewFoto').src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }

    function toggleDarkMode() {
    const body = document.body;
    const toggle = document.getElementById('dark-mode-toggle');
    
    body.classList.toggle('bg-dark');
    const isDark = body.classList.contains('bg-dark');
    
    toggle.checked = isDark;
    
    // Guardar en la base de datos en lugar de localStorage
    const formData = new FormData();
    formData.append('tema_oscuro', isDark ? '1' : '0');
    
    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar notificación
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                icon: 'success',
                title: `Tema ${isDark ? 'oscuro' : 'claro'} activado`
            });
            // Refrescar tras guardar para asegurar consistencia global
            setTimeout(() => location.reload(), 400);
        } else {
            console.error('Error al guardar tema:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
    // ============================================
    // MANEJO DE PREFERENCIAS AVANZADAS
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar que el formulario existe
        const formPreferencias = document.getElementById('form-preferencias-avanzadas');
        console.log("DEBUG: Formulario de preferencias encontrado:", formPreferencias);
        
        if (formPreferencias) {
            console.log("DEBUG: Agregando event listener al formulario de preferencias");
            
            // Toggle para mostrar/ocultar horario "No Molestar"
            const noMolestarToggle = document.getElementById('no_molestar_activo');
            if (noMolestarToggle) {
                noMolestarToggle.addEventListener('change', function() {
                    const horarioDiv = document.getElementById('horario-no-molestar');
                    if (this.checked) {
                        horarioDiv.style.display = 'block';
                    } else {
                        horarioDiv.style.display = 'none';
                    }
                });
            }
            
            // Manejar formulario de preferencias avanzadas
            formPreferencias.addEventListener('submit', function(e) {
                e.preventDefault();
                
                console.log("DEBUG: Iniciando envío de preferencias avanzadas");
                
                const formData = recolectarDatosPreferencias();
                const btn = this.querySelector('button[type="submit"]');
                const btnText = btn.innerHTML;
                
                console.log("DEBUG: Datos recolectados:");
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Mostrar carga
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
                
                console.log("DEBUG: Enviando petición a guardar_preferencias_avanzadas.php");
                
                // Enviar la petición
                fetch('guardar_preferencias_avanzadas.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(response => {
                    console.log("DEBUG: Respuesta recibida, status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Respuesta del servidor:', data);
                    
                    if (data && data.success) {
                        // Mostrar notificación de éxito
                        Swal.fire({
                            icon: 'success',
                            title: '¡Preferencias Guardadas!',
                            text: 'Tus preferencias avanzadas se han actualizado correctamente',
                            confirmButtonColor: '#4CAF50',
                            timer: 3000,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false
                        });
                        
                        // Mostrar toast de confirmación
                        if (typeof mostrarToast === 'function') {
                            mostrarToast('Preferencias avanzadas guardadas exitosamente', 'success');
                        }
                        
                        // Recargar la página para asegurar que los cambios se reflejen
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        // Mostrar error específico del servidor
                        let errorMsg = 'No se pudieron guardar las preferencias';
                        if (data && data.message) {
                            errorMsg = data.message;
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al guardar',
                            text: errorMsg,
                            confirmButtonColor: '#d33',
                            allowOutsideClick: false
                        });
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    
                    if (error.message.includes('Failed to fetch')) {
                        // Error de conexión
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            html: 'No se pudo conectar con el servidor. Por favor, verifica que:<br><br>' +
                                  '1. El servidor esté en ejecución<br>' +
                                  '2. Tu conexión a internet sea estable<br>' +
                                  '3. El archivo <code>guardar_preferencias_avanzadas.php</code> exista en el servidor',
                            confirmButtonColor: '#d33',
                            allowOutsideClick: false
                        });
                    } else {
                        // Otro tipo de error
                        Swal.fire({
                            icon: 'error',
                            title: 'Error inesperado',
                            text: 'Ocurrió un error al procesar tu solicitud: ' + error.message,
                            confirmButtonColor: '#d33',
                            allowOutsideClick: false
                        });
                    }
                })
                .finally(() => {
                    // Restaurar botón
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btnText || '<i class="fas fa-save me-2"></i>Guardar Cambios';
                    }
                });
            });
        }
    });
    
    // Función para recolectar todos los datos del formulario de preferencias avanzadas
    function recolectarDatosPreferencias() {
        const form = document.getElementById('form-preferencias-avanzadas');
        const formData = new FormData();
        
        // Agregar manualmente los campos del formulario
        // 1. Horario No Molestar
        formData.append('no_molestar_activo', document.getElementById('no_molestar_activo').checked ? '1' : '0');
        formData.append('hora_inicio_nm', document.getElementById('hora_inicio_nm').value);
        formData.append('hora_fin_nm', document.getElementById('hora_fin_nm').value);
        
        // 2. Frecuencia de Recordatorios
        formData.append('dias_recordatorio', document.getElementById('dias_recordatorio').value);
        formData.append('recordatorios_multiples', document.getElementById('recordatorios_multiples').checked ? '1' : '0');
        
        // 3. Notificaciones por Email
        formData.append('email_vencidas', document.getElementById('email_vencidas')?.checked ? '1' : '0');
        formData.append('email_proximas', document.getElementById('email_proximas')?.checked ? '1' : '0');
        formData.append('email_logros', document.getElementById('email_logros')?.checked ? '1' : '0');
        formData.append('email_resumen', document.getElementById('email_resumen')?.checked ? '1' : '0');
        
        // 4. Resumen Semanal
        formData.append('resumen_semanal_activo', document.getElementById('resumen_semanal_activo')?.checked ? '1' : '0');
        formData.append('dia_resumen', document.getElementById('dia_resumen')?.value || '1');
        formData.append('incluir_estadisticas', document.getElementById('incluir_estadisticas')?.checked ? '1' : '0');
        formData.append('incluir_sugerencias', document.getElementById('incluir_sugerencias')?.checked ? '1' : '0');
        
        // 5. Mensajes Motivacionales
        formData.append('mensajes_motivacionales', document.getElementById('mensajes_motivacionales')?.checked ? '1' : '0');
        formData.append('frecuencia_motivacion', document.getElementById('frecuencia_motivacion')?.value || 'semanal');
        
        // 6. Sonido y Alertas
        formData.append('sonido_notificaciones', document.getElementById('sonido_notificaciones')?.checked ? '1' : '0');
        formData.append('notif_navegador', document.getElementById('notif_navegador')?.checked ? '1' : '0');
        formData.append('badge_contador', document.getElementById('badge_contador')?.checked ? '1' : '0');
        
        return formData;
    }
    
    // Función para resetear preferencias a valores por defecto
    function resetearPreferencias() {
        Swal.fire({
            title: '¿Restaurar valores por defecto?',
            text: 'Esto restablecerá todas tus preferencias avanzadas a los valores predeterminados',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4CAF50',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, restaurar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Resetear valores del formulario
                document.getElementById('no_molestar_activo').checked = false;
                document.getElementById('horario-no-molestar').style.display = 'none';
                document.getElementById('hora_inicio_nm').value = '22:00';
                document.getElementById('hora_fin_nm').value = '08:00';
                document.getElementById('dias_recordatorio').value = '3';
                document.getElementById('recordatorios_multiples').checked = true;
                document.getElementById('email_vencidas').checked = false;
                document.getElementById('email_proximas').checked = false;
                document.getElementById('email_logros').checked = true;
                document.getElementById('email_resumen').checked = true;
                document.getElementById('resumen_semanal_activo').checked = true;
                document.getElementById('dia_resumen').value = '1';
                document.getElementById('incluir_estadisticas').checked = true;
                document.getElementById('incluir_sugerencias').checked = true;
                document.getElementById('mensajes_motivacionales').checked = true;
                document.getElementById('frecuencia_motivacion').value = 'semanal';
                document.getElementById('sonido_notificaciones').checked = false;
                document.getElementById('notif_navegador').checked = false;
                document.getElementById('badge_contador').checked = true;
                
                Swal.fire({
                    icon: 'success',
                    title: 'Valores Restaurados',
                    text: 'Las preferencias han sido restablecidas a los valores por defecto',
                    confirmButtonColor: '#4CAF50',
                    timer: 2000
                });
                
                mostrarToast('Preferencias restauradas a valores por defecto', 'info');
            }
        });
    }
    
    // Hacer la función global para que pueda ser llamada desde el HTML
    window.resetearPreferencias = resetearPreferencias;

     // Manejar formulario de preferencias de notificaciones
    document.getElementById('form-preferencias-notificaciones')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        const btnText = btn.innerHTML;
        
        // Deshabilitar botón mientras se procesa
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
        
        fetch('guardar_preferencias_notificaciones.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Preferencias Guardadas!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Opcional: Recargar la página después de 2 segundos para aplicar cambios
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un problema al guardar las preferencias'
            });
        })
        .finally(() => {
            // Restaurar botón
            btn.disabled = false;
            btn.innerHTML = btnText;
        });
    });

    // Nuevas funciones para la configuración
    function exportData() {
        Swal.fire({
            title: 'Exportar Datos',
            text: 'Se descargará un archivo con tu información personal y actividades.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Descargar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Aquí iría la lógica para exportar datos
                Swal.fire('¡Éxito!', 'Tus datos han sido exportados.', 'success');
            }
        });
    }

    function showHelpModal() {
        Swal.fire({
            title: 'Centro de Ayuda',
            html: `
                <div class="text-start">
                    <h6><i class="fas fa-question-circle me-2 text-primary"></i>Preguntas Frecuentes</h6>
                    <p><strong>¿Cómo cambio mi foto de perfil?</strong><br>
                    Ve a la sección de Configuración > Foto de Perfil y sube una nueva imagen.</p>
                    
                    <p><strong>¿Cómo actualizo mi información?</strong><br>
                    En Configuración > Información Personal puedes editar tus datos.</p>
                    
                    <p><strong>¿Necesitas más ayuda?</strong><br>
                    Contacta al administrador del sistema.</p>
                </div>
            `,
            showCloseButton: true,
            focusConfirm: false,
            confirmButtonText: 'Entendido'
        });
    }

   function confirmLogout() {
    if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
        window.location.href = 'logout.php';
    }
}
// ============================================
// SISTEMA DE NOTIFICACIONES MEJORADO
// ============================================

/**
 * Mostrar notificación toast
 */
function mostrarToast(mensaje, tipo = 'info', duracion = 5000) {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const iconos = {
        'danger': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle',
        'success': 'fas fa-check-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${tipo}`;
    toast.innerHTML = `
        <i class="${iconos[tipo]} toast-icon" style="color: ${getColorByType(tipo)}"></i>
        <div class="toast-content">
            <div class="fw-semibold">${mensaje}</div>
        </div>
        <button class="toast-close" onclick="cerrarToast(this)">&times;</button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto-eliminar después de la duración especificada
    setTimeout(() => {
        cerrarToast(toast.querySelector('.toast-close'));
    }, duracion);
}

/**
 * Cerrar toast
 */
function cerrarToast(button) {
    const toast = button.closest('.toast-notification');
    if (toast) {
        toast.classList.add('toast-removing');
        setTimeout(() => toast.remove(), 400);
    }
}

/**
 * Obtener color según tipo de notificación
 */
function getColorByType(tipo) {
    const colores = {
        'danger': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8',
        'success': '#28a745'
    };
    return colores[tipo] || '#17a2b8';
}

/**
 * Toggle del panel de notificaciones (para futuras implementaciones)
 */
function toggleNotificationPanel() {
    // Por ahora solo muestra la sección de inicio donde están las notificaciones
    mostrarSeccion('inicio');
    
    // Scroll suave hacia las notificaciones
    setTimeout(() => {
        const notifCard = document.querySelector('#notificaciones-container');
        if (notifCard) {
            notifCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 300);
}

/**
 * Marcar todas las notificaciones como leídas
 */
function marcarTodasLeidas() {
    Swal.fire({
        title: '¿Marcar todas como leídas?',
        text: 'Esto eliminará todas las notificaciones actuales',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, marcar todas',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Obtener todas las notificaciones
            const notificaciones = document.querySelectorAll('.notificacion-item');
            
            if (notificaciones.length === 0) {
                return;
            }
            
            // Animar salida de todas las notificaciones
            notificaciones.forEach((notif, index) => {
                setTimeout(() => {
                    notif.classList.add('notification-slide-out');
                }, index * 50); // Efecto cascada
            });
            
            // Enviar petición al servidor
            fetch('marcar_todas_leidas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Esperar a que terminen las animaciones
                    setTimeout(() => {
                        // Actualizar el contenedor
                        const container = document.getElementById('notificaciones-container');
                        if (container) {
                            container.innerHTML = `
                                <div class="text-muted text-center py-5" id="no-notifications-msg">
                                    <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-50"></i>
                                    <p class="mb-0">No hay notificaciones por ahora.</p>
                                    <small>Te notificaremos cuando haya novedades</small>
                                </div>
                            `;
                        }
                        
                        // Actualizar contador en el header
                        const badge = document.getElementById('notif-count');
                        if (badge) {
                            badge.remove();
                        }
                        
                        // Actualizar badge en el card header
                        const cardBadge = document.querySelector('.pulse-badge');
                        if (cardBadge) {
                            cardBadge.remove();
                        }
                        
                        // Ocultar botón de marcar todas
                        const btnMarcarTodas = document.querySelector('[onclick="marcarTodasLeidas()"]');
                        if (btnMarcarTodas) {
                            btnMarcarTodas.style.display = 'none';
                        }
                        
                        // Mostrar toast de éxito
                        mostrarToast('Todas las notificaciones han sido marcadas como leídas', 'success');
                    }, notificaciones.length * 50 + 400);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudieron marcar las notificaciones como leídas',
                        confirmButtonColor: '#4CAF50'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al conectar con el servidor',
                    confirmButtonColor: '#4CAF50'
                });
            });
        }
    });
}

/**
 * Actualizar contador de notificaciones
 */
function actualizarContadorNotificaciones() {
    const notificaciones = document.querySelectorAll('.notificacion-item');
    const count = notificaciones.length;
    
    const badge = document.getElementById('notif-count');
    const cardBadge = document.querySelector('.pulse-badge');
    
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
        }
        if (cardBadge) {
            cardBadge.textContent = count;
        }
    } else {
        if (badge) {
            badge.remove();
        }
        if (cardBadge) {
            cardBadge.remove();
        }
    }
}

// Función para eliminar notificación
    function eliminarNotificacion(idNotificacion) {
        const notifElement = document.getElementById('notificacion-' + idNotificacion);
        
        if (!notifElement) {
            console.error('Notificación no encontrada');
            return;
        }
        
        // Agregar clase de animación mejorada
        notifElement.classList.add('notification-slide-out');
        
        // Enviar petición al servidor
        fetch('eliminar_notificacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id_notificacion=' + idNotificacion
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Esperar a que termine la animación antes de eliminar del DOM
                setTimeout(() => {
                    notifElement.remove();
                    
                    // Actualizar contador de notificaciones
                    actualizarContadorNotificaciones();
                    
                    // Verificar si no quedan notificaciones
                    const notificaciones = document.querySelectorAll('.notificacion-item');
                    if (notificaciones.length === 0) {
                        // Mostrar mensaje de "No hay notificaciones"
                        const container = document.getElementById('notificaciones-container');
                        if (container) {
                            container.innerHTML = `
                                <div class="text-muted text-center py-5" id="no-notifications-msg">
                                    <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-50"></i>
                                    <p class="mb-0">No hay notificaciones por ahora.</p>
                                    <small>Te notificaremos cuando haya novedades</small>
                                </div>
                            `;
                        }
                        
                        // Ocultar botón de marcar todas
                        const btnMarcarTodas = document.querySelector('[onclick="marcarTodasLeidas()"]');
                        if (btnMarcarTodas) {
                            btnMarcarTodas.style.display = 'none';
                        }
                    }
                    
                    // Mostrar toast de confirmación
                    mostrarToast('Notificación eliminada', 'success', 3000);
                }, 400); // Tiempo de la animación
            } else {
                // Revertir animación si hay error
                notifElement.classList.remove('notification-slide-out');
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Error', 
                    text: 'Error al eliminar la notificación' + (data.message ? ': ' + data.message : ''),
                    confirmButtonColor: '#4CAF50'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            notifElement.classList.remove('notification-slide-out');
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: 'Error al eliminar la notificación',
                confirmButtonColor: '#4CAF50'
            });
        });
    }
    function eliminarActividad(idActividad) {
        const actElement = document.getElementById('actividad-' + idActividad);
        if (!actElement) {
            console.error('Actividad no encontrada');
            return;
        }
        actElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        actElement.style.opacity = '0.2';
        actElement.style.transform = 'translateX(10px)';

        fetch('eliminar_actividad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_actividad=' + encodeURIComponent(idActividad)
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                setTimeout(() => {
                    actElement.remove();
                    const restantes = document.querySelectorAll('#actividad .timeline .timeline-item');
                    if (restantes.length === 0) {
                        const contenedor = document.querySelector('#actividad .timeline');
                        if (contenedor) {
                            contenedor.innerHTML = `
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-bell-slash fa-2x d-block mb-2"></i>
                                    <small>No hay actividad reciente.</small>
                                </div>
                            `;
                        }
                    }
                }, 300);
            } else {
                actElement.style.opacity = '';
                actElement.style.transform = '';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar la actividad' + (data && data.message ? ': ' + data.message : '') });
            }
        })
        .catch(err => {
            console.error(err);
            actElement.style.opacity = '';
            actElement.style.transform = '';
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar la actividad' });
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        mostrarSeccion('inicio');

        // Inicializar toggle según el estado actual del body (servidor)
        const toggle = document.getElementById('dark-mode-toggle');
        if (toggle) {
            toggle.checked = document.body.classList.contains('bg-dark');
            toggle.addEventListener('change', toggleDarkMode);
        }

        // Asegurar que el hash de la URL cargue la sección correcta
        const hash = window.location.hash.substring(1);
        if (hash) {
            mostrarSeccion(hash);
        }

        // Animaciones de entrada para las cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observar elementos con animación
        document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.8s ease-out';
            observer.observe(el);
        });
    });


     function confirmLogout() {
        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: 'Se cerrará tu sesión actual.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, cerrar sesión',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }






    // Función para la alerta de confirmación de borrado
    function confirmDeleteFicha(button) {
        const form = button.closest('form');
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto! Se eliminarán todas las bitácoras y relaciones con aprendices asociadas a esta ficha.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, ¡bórrala!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    // Mostrar alerta de éxito o error al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                title: "<?php echo $_SESSION['message_type'] === 'success' ? '¡Éxito!' : '¡Error!'; ?>",
                text: "<?php echo $_SESSION['message']; ?>",
                icon: "<?php echo $_SESSION['message_type']; ?>",
                confirmButtonText: 'Ok'
            });
            <?php
            // Limpiar las variables de sesión para que la alerta no se muestre de nuevo al recargar
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
    });

    // Validación en tiempo real para contraseñas
    document.addEventListener('DOMContentLoaded', function() {
        const nuevaPassword = document.getElementById('nueva_contrasena');
        const confirmarPassword = document.getElementById('confirmar_contrasena');
        
        if (nuevaPassword && confirmarPassword) {
            function validatePasswords() {
                if (confirmarPassword.value !== '' && nuevaPassword.value !== confirmarPassword.value) {
                    confirmarPassword.setCustomValidity('Las contraseñas no coinciden');
                    confirmarPassword.classList.add('is-invalid');
                } else {
                    confirmarPassword.setCustomValidity('');
                    confirmarPassword.classList.remove('is-invalid');
                }
            }
            
            nuevaPassword.addEventListener('input', validatePasswords);
            confirmarPassword.addEventListener('input', validatePasswords);
        }

        // ============================================
        // VALIDACIÓN Y PREVIEW DE FIRMAS
        // ============================================
        
        // Función para validar tamaño de archivo (máx 2MB)
        function validarTamanoArchivo(file) {
            const maxSize = 2 * 1024 * 1024; // 2MB en bytes
            return file.size <= maxSize;
        }

        // Función para mostrar preview de imagen
        function mostrarPreview(input, previewId) {
            const file = input.files[0];
            const previewDiv = document.getElementById(previewId);
            
            if (file) {
                // Validar tamaño
                if (!validarTamanoArchivo(file)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo muy grande',
                        text: 'El archivo debe ser menor a 2MB',
                        confirmButtonColor: '#4CAF50'
                    });
                    input.value = '';
                    previewDiv.innerHTML = '';
                    return;
                }

                // Validar tipo de archivo
                const validTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Formato no válido',
                        text: 'Solo se permiten archivos PNG, JPG o JPEG',
                        confirmButtonColor: '#4CAF50'
                    });
                    input.value = '';
                    previewDiv.innerHTML = '';
                    return;
                }

                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `
                        <div class="border rounded p-2 bg-light">
                            <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 100px;" class="img-fluid">
                            <p class="mb-0 mt-2 small text-success"><i class="fas fa-check-circle me-1"></i>Firma cargada correctamente</p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.innerHTML = '';
            }
        }

        // Event listeners para las firmas
        const firmaAprendiz = document.getElementById('firma_aprendiz');
        const firmaInstructor = document.getElementById('firma_instructor');
        const firmaJefe = document.getElementById('firma_jefe');

        if (firmaAprendiz) {
            firmaAprendiz.addEventListener('change', function() {
                mostrarPreview(this, 'preview_firma_aprendiz');
            });
        }

        if (firmaInstructor) {
            firmaInstructor.addEventListener('change', function() {
                mostrarPreview(this, 'preview_firma_instructor');
            });
        }

        if (firmaJefe) {
            firmaJefe.addEventListener('change', function() {
                mostrarPreview(this, 'preview_firma_jefe');
            });
        }

        // Validación del formulario de bitácora antes de enviar
        const bitacoraForm = document.getElementById('bitacora-form');
        if (bitacoraForm) {
            bitacoraForm.addEventListener('submit', function(e) {
                // Validar que las firmas obligatorias estén presentes
                const firmaAprendizInput = document.getElementById('firma_aprendiz');
                const firmaInstructorInput = document.getElementById('firma_instructor');
                const nombreInstructor = document.getElementById('nombre_instructor');

                let errores = [];

                // Validar firma del aprendiz
                if (!firmaAprendizInput.files || firmaAprendizInput.files.length === 0) {
                    errores.push('Debe subir la firma del aprendiz');
                }

                // Validar firma del instructor
                if (!firmaInstructorInput.files || firmaInstructorInput.files.length === 0) {
                    errores.push('Debe subir la firma del instructor');
                }

                // Validar nombre del instructor
                if (!nombreInstructor.value.trim()) {
                    errores.push('Debe ingresar el nombre del instructor de seguimiento');
                }

                // Si hay errores, prevenir el envío y mostrar alerta
                if (errores.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Firmas incompletas',
                        html: '<p>Por favor complete las siguientes firmas obligatorias:</p><ul style="text-align: left;">' + 
                              errores.map(err => '<li>' + err + '</li>').join('') + '</ul>',
                        confirmButtonColor: '#4CAF50',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }

                // Mostrar mensaje de carga
                Swal.fire({
                    title: 'Guardando bitácora...',
                    text: 'Por favor espere mientras se procesan las firmas',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            });
        }
    });

</script>
<?php if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } ?>
</body>
</html>