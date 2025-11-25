<?php
session_start();
require 'database.php';

// ✅ Verificar sesión y rol
if (!isset($_SESSION['documento']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    die("Acceso no autorizado.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ✅ Sanitizar y validar entradas
    $codigo_ficha = trim($_POST['codigo_ficha']);
    $programa = trim($_POST['programa']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    if (empty($codigo_ficha) || empty($programa) || empty($fecha_inicio) || empty($fecha_fin)) {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'error',title:'Campos requeridos',text:'❌ Todos los campos son obligatorios.'}).then(()=>{history.back();});</script></body></html>";
        exit();
    }

    // ✅ Validar formato de fechas
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'warning',title:'Formato inválido',text:'❌ Formato de fecha inválido.'}).then(()=>{history.back();});</script></body></html>";
        exit();
    }

    // ✅ Verificar que la fecha de inicio sea menor a la de fin
    if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'error',title:'Rango de fechas',text:'❌ La fecha de inicio no puede ser posterior a la fecha de fin.'}).then(()=>{history.back();});</script></body></html>";
        exit();
    }

    // ✅ Verificar si el código de ficha ya existe
    $check_sql = "SELECT id FROM fichas WHERE codigo_ficha = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $codigo_ficha);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'info',title:'Código duplicado',text:'⚠️ El código de ficha ya existe.'}).then(()=>{history.back();});</script></body></html>";
        $check_stmt->close();
        $conn->close();
        exit();
    }
    $check_stmt->close();

    // ✅ Insertar ficha en la base de datos
    $sql = "INSERT INTO fichas (codigo_ficha, programa, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $codigo_ficha, $programa, $fecha_inicio, $fecha_fin);

    if ($stmt->execute()) {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'success',title:'Éxito',text:'✅ Ficha agregada correctamente.'}).then(()=>{window.location.href='instructores.php';});</script></body></html>";
    } else {
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>.swal2-popup{background:#2b2b2b!important;color:#f1f1f1!important}.swal2-title,.swal2-html-container{color:#f1f1f1!important}</style></head><body><script>Swal.fire({icon:'error',title:'Error',text:'❌ Error al agregar la ficha: " . addslashes($conn->error) . "'}).then(()=>{history.back();});</script></body></html>";
    }

    $stmt->close();
    $conn->close();
} else {
    // Si intentan acceder directamente sin enviar el formulario
    header("Location: instructores.php");
    exit();
}
?>

