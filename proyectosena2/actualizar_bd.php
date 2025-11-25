<?php
/**
 * Script de actualización automática de la base de datos
 * Sistema de Notificaciones Mejorado
 * 
 * IMPORTANTE: Ejecutar este archivo UNA SOLA VEZ
 * Acceder desde: http://localhost/proyectosena/actualizar_bd.php
 */

// Configuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutos

// Incluir conexión a base de datos
require_once 'database.php';

// Verificar conexión
if (!$conn) {
    die("❌ Error: No se pudo conectar a la base de datos");
}

// Array para almacenar resultados
$resultados = [];
$errores = [];

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Actualización de Base de Datos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .step.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .step.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .step.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .step h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon {
            font-size: 1.5rem;
        }
        .summary {
            background: #e9ecef;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .summary h2 {
            color: #2E7D32;
            margin-bottom: 15px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #4CAF50;
        }
        .stat-box .label {
            color: #666;
            font-size: 0.9rem;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #2E7D32;
            transform: translateY(-2px);
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔄 Actualización de Base de Datos</h1>
            <p>Sistema de Notificaciones Mejorado</p>
        </div>
        <div class='content'>";

// ============================================
// 1. CREAR TABLA: preferencias_notificaciones
// ============================================
echo "<div class='step'><h3><span class='icon'>📋</span>Tabla: preferencias_notificaciones</h3>";
$sql = "CREATE TABLE IF NOT EXISTS preferencias_notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    notificar_vencidas TINYINT(1) NOT NULL DEFAULT 1,
    notificar_proximas TINYINT(1) NOT NULL DEFAULT 1,
    notificar_faltantes TINYINT(1) NOT NULL DEFAULT 1,
    tema_oscuro TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_usuario (id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Tabla creada/verificada correctamente</p>";
    $resultados[] = "preferencias_notificaciones: OK";
} else {
    echo "<p>❌ Error: " . $conn->error . "</p>";
    $errores[] = "preferencias_notificaciones: " . $conn->error;
}
echo "</div>";

// ============================================
// 2. CREAR TABLA: notificaciones
// ============================================
echo "<div class='step'><h3><span class='icon'>🔔</span>Tabla: notificaciones</h3>";
$sql = "CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'info',
    icono VARCHAR(50) DEFAULT 'fas fa-info-circle',
    leida TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura TIMESTAMP NULL,
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_leida (leida),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Tabla creada/verificada correctamente</p>";
    $resultados[] = "notificaciones: OK";
} else {
    echo "<p>❌ Error: " . $conn->error . "</p>";
    $errores[] = "notificaciones: " . $conn->error;
}
echo "</div>";

// ============================================
// 3. CREAR TABLA: preferencias_avanzadas
// ============================================
echo "<div class='step'><h3><span class='icon'>⚙️</span>Tabla: preferencias_avanzadas</h3>";
$sql = "CREATE TABLE IF NOT EXISTS preferencias_avanzadas (
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
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user (id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Tabla creada/verificada correctamente</p>";
    $resultados[] = "preferencias_avanzadas: OK";
} else {
    echo "<p>❌ Error: " . $conn->error . "</p>";
    $errores[] = "preferencias_avanzadas: " . $conn->error;
}
echo "</div>";

// ============================================
// 4. CREAR TABLA: actividades
// ============================================
echo "<div class='step'><h3><span class='icon'>📊</span>Tabla: actividades</h3>";
$sql = "CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha),
    INDEX idx_tipo (tipo),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Tabla creada/verificada correctamente</p>";
    $resultados[] = "actividades: OK";
} else {
    echo "<p>❌ Error: " . $conn->error . "</p>";
    $errores[] = "actividades: " . $conn->error;
}
echo "</div>";

// ============================================
// 5. INSERTAR PREFERENCIAS POR DEFECTO
// ============================================
echo "<div class='step'><h3><span class='icon'>💾</span>Insertar preferencias por defecto</h3>";

// Preferencias básicas
$sql = "INSERT IGNORE INTO preferencias_notificaciones (id_usuario, notificar_vencidas, notificar_proximas, notificar_faltantes, tema_oscuro)
        SELECT u.id, 1, 1, 1, 0
        FROM usuarios u
        WHERE NOT EXISTS (
            SELECT 1 FROM preferencias_notificaciones pn WHERE pn.id_usuario = u.id
        )";

if ($conn->query($sql) === TRUE) {
    $affected = $conn->affected_rows;
    echo "<p>✅ Preferencias básicas: $affected usuarios actualizados</p>";
} else {
    echo "<p>⚠️ Advertencia: " . $conn->error . "</p>";
}

// Preferencias avanzadas
$sql = "INSERT IGNORE INTO preferencias_avanzadas (
            id_usuario, no_molestar_activo, hora_inicio_nm, hora_fin_nm,
            dias_recordatorio, recordatorios_multiples, email_vencidas, email_proximas,
            email_logros, email_resumen, resumen_semanal_activo, dia_resumen,
            incluir_estadisticas, incluir_sugerencias, mensajes_motivacionales,
            frecuencia_motivacion, sonido_notificaciones, notif_navegador, badge_contador
        )
        SELECT u.id, 0, '22:00:00', '08:00:00', 3, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 'semanal', 0, 0, 1
        FROM usuarios u
        WHERE NOT EXISTS (
            SELECT 1 FROM preferencias_avanzadas pa WHERE pa.id_usuario = u.id
        )";

if ($conn->query($sql) === TRUE) {
    $affected = $conn->affected_rows;
    echo "<p>✅ Preferencias avanzadas: $affected usuarios actualizados</p>";
} else {
    echo "<p>⚠️ Advertencia: " . $conn->error . "</p>";
}
echo "</div>";

// ============================================
// 6. RESUMEN Y ESTADÍSTICAS
// ============================================
echo "<div class='summary'>";
echo "<h2>📈 Resumen de Actualización</h2>";

// Contar registros
$stats = [];
$tables = ['preferencias_notificaciones', 'notificaciones', 'preferencias_avanzadas', 'actividades', 'usuarios'];

foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats[$table] = $row['total'];
    }
}

echo "<div class='stats'>";
foreach ($stats as $table => $count) {
    $icon = '📊';
    if ($table == 'usuarios') $icon = '👥';
    if ($table == 'notificaciones') $icon = '🔔';
    if ($table == 'preferencias_notificaciones') $icon = '⚙️';
    if ($table == 'preferencias_avanzadas') $icon = '🎛️';
    if ($table == 'actividades') $icon = '📝';
    
    echo "<div class='stat-box'>
            <div class='icon'>$icon</div>
            <div class='number'>$count</div>
            <div class='label'>$table</div>
          </div>";
}
echo "</div>";

echo "<h3 style='margin-top: 20px;'>✅ Operaciones Exitosas:</h3>";
echo "<ul>";
foreach ($resultados as $resultado) {
    echo "<li>$resultado</li>";
}
echo "</ul>";

if (!empty($errores)) {
    echo "<h3 style='margin-top: 20px; color: #dc3545;'>❌ Errores Encontrados:</h3>";
    echo "<ul>";
    foreach ($errores as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

echo "<div style='text-align: center;'>";
echo "<a href='principal.php' class='btn'>🚀 Ir a la Aplicación</a>";
echo "<a href='actualizar_bd.php' class='btn' style='background: #6c757d; margin-left: 10px;'>🔄 Ejecutar Nuevamente</a>";
echo "</div>";

echo "</div>"; // summary

echo "        </div>
    </div>
</body>
</html>";

// Cerrar conexión
$conn->close();
?>
