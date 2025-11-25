<?php
session_start();
require 'database.php';

// Verificar sesión de aprendiz
if (!isset($_SESSION['aprendiz_id'])) {
    die("❌ Debes iniciar sesión como aprendiz.");
}
$aprendiz_id = $_SESSION['aprendiz_id'];

// --- Ficha fija ---
$ficha_codigo = '2910309';

// Buscar ID de la ficha en la tabla `fichas`
$stmt = $conn->prepare("SELECT id, programa FROM fichas WHERE codigo_ficha = ?");
$stmt->bind_param("s", $ficha_codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($ficha = $result->fetch_assoc()) {
    $ficha_id = $ficha['id'];
    $programa = $ficha['programa']; // Para programa_formacion
} else {
    die("❌ Ficha no encontrada en la base de datos.");
}
$stmt->close();

// Función para limpiar entradas
function limpiar($valor) {
    return is_array($valor) ? null : trim($valor);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_bitacora        = limpiar($_POST['numero_bitacora'] ?? $_POST['bitacora_num'] ?? null);
    $fecha_entrega          = limpiar($_POST['fecha_entrega'] ?? null);
    // Aceptar ambos nombres de campos (subir_bitacora y principal)
    $nombre_empresa         = limpiar($_POST['nombre_empresa'] ?? $_POST['empresa'] ?? null);
    $nit                    = limpiar($_POST['nit'] ?? null);
    $periodo                = limpiar($_POST['periodo'] ?? null);
    $nombre_jefe            = limpiar($_POST['nombre_jefe'] ?? $_POST['jefe'] ?? null);
    $telefono_contacto      = limpiar($_POST['telefono_contacto'] ?? $_POST['telefono_jefe'] ?? null);
    $correo_contacto        = limpiar($_POST['correo_contacto'] ?? $_POST['correo_jefe'] ?? null);
    $modalidad_etapa        = limpiar($_POST['modalidad_etapa'] ?? null);
    $descripcion_actividad  = limpiar($_POST['descripcion_actividad'] ?? null);
    $fecha_inicio           = limpiar($_POST['fecha_inicio'] ?? null);
    $fecha_fin              = limpiar($_POST['fecha_fin'] ?? null);
    $evidencia_cumplimiento = limpiar($_POST['evidencia_cumplimiento'] ?? null);
    $observaciones          = limpiar($_POST['observaciones'] ?? null);

    // Datos del aprendiz
    $nombre_aprendiz        = limpiar($_POST['nombre_aprendiz'] ?? ""); 
    $documento_aprendiz     = limpiar($_POST['documento_aprendiz'] ?? ""); 
    $telefono_aprendiz      = limpiar($_POST['telefono_aprendiz'] ?? ""); 
    $correo_aprendiz        = limpiar($_POST['correo_aprendiz'] ?? ""); 

    // Validación estricta para ficha 2910309 (empresa, NIT, jefe, teléfono, correo)
    if ($ficha_codigo === '2910309') {
        $errores = [];
        // Valores esperados exactos
        $expected = [
            'empresa'  => 'Institución Educativa El Bagre',
            'nit'      => '811040660',
            'jefe'     => 'William Machado',
            'telefono' => '3117479065',
            'correo'   => 'wialma64@hotmail.es'
        ];
        // Comparación estricta (trim + comparar string; sensible a acentos)
        if (trim((string)$nombre_empresa) !== $expected['empresa']) {
            $errores[] = 'Nombre de la empresa debe ser exactamente: "' . $expected['empresa'] . '"';
        }
        if (trim((string)$nit) !== $expected['nit']) {
            $errores[] = 'NIT debe ser exactamente: ' . $expected['nit'];
        }
        if (trim((string)$nombre_jefe) !== $expected['jefe']) {
            $errores[] = 'Nombre del jefe/responsable debe ser exactamente: "' . $expected['jefe'] . '"';
        }
        if (trim((string)$telefono_contacto) !== $expected['telefono']) {
            $errores[] = 'Teléfono de contacto debe ser exactamente: ' . $expected['telefono'];
        }
        if (trim((string)$correo_contacto) !== $expected['correo']) {
            $errores[] = 'Correo electrónico debe ser exactamente: ' . $expected['correo'];
        }

        if (!empty($errores)) {
            $mensaje = implode("\n- ", $errores);
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'>"
               . "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>"
               . "<style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style>"
               . "</head><body><script>"
               . "Swal.fire({icon:'warning',title:'Validación de datos',html:`Se encontraron campos con información incorrecta:<br><pre style=\"text-align:left;white-space:pre-wrap;color:#f1f1f1\">- $mensaje</pre>`}).then(()=>{history.back();});"
               . "</script></body></html>";
            exit();
        }
    }

    // Manejo de archivo
    $archivoRuta = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        $permitidas = ['pdf', 'doc', 'docx', 'jpg', 'png'];
        if (in_array($ext, $permitidas)) {
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $archivoRuta = 'uploads/' . uniqid('bitacora_') . '.' . $ext;
            move_uploaded_file($_FILES['archivo']['tmp_name'], $archivoRuta);
        }
    }

    // Estado por defecto
    $estado = "borrador";

    // Insertar en la tabla (sin id, fecha_subida, fecha_actualizacion -> automáticas)
    $sql = "INSERT INTO bitacoras
        (aprendiz_id, ficha_id, numero_bitacora, archivo, nombre_empresa, nit, periodo, 
         nombre_jefe, telefono_contacto, correo_contacto, modalidad_etapa, nombre_aprendiz, 
         documento_aprendiz, telefono_aprendiz, correo_aprendiz, programa_formacion, 
         descripcion_actividad, fecha_inicio, fecha_fin, evidencia_cumplimiento, 
         observaciones, estado, fecha_entrega)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);

    // 3 enteros (i) y 20 strings (s) = 23 parámetros
    $stmt->bind_param(
        "iiissssssssssssssssssss",
        $aprendiz_id, $ficha_id, $numero_bitacora, $archivoRuta, $nombre_empresa, $nit, $periodo,
        $nombre_jefe, $telefono_contacto, $correo_contacto, $modalidad_etapa, $nombre_aprendiz,
        $documento_aprendiz, $telefono_aprendiz, $correo_aprendiz, $programa,
        $descripcion_actividad, $fecha_inicio, $fecha_fin, $evidencia_cumplimiento,
        $observaciones, $estado, $fecha_entrega
    );

    if ($stmt->execute()) {
        // Registrar actividad: bitácora creada
        $conn->query("CREATE TABLE IF NOT EXISTS actividades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($log = $conn->prepare("INSERT INTO actividades (usuario_id, tipo, titulo, descripcion) VALUES (?,?,?,?)")) {
            $tipo = 'bitacora_creada';
            $titulo = 'Bitácora creada';
            $descripcion = 'Número: ' . ($numero_bitacora ?? '') . ' | Empresa: ' . ($nombre_empresa ?? '');
            $log->bind_param('isss', $aprendiz_id, $tipo, $titulo, $descripcion);
            $log->execute();
            $log->close();
        }

        header("Location: ficha_dashboard.php?ficha_id=" . $ficha_id);
        exit();
    } else {
        die("❌ Error al guardar: " . $stmt->error);
    }
}
$conn->close();
?>

