<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/database.php';

try {
    if (!isset($_SESSION['documento'], $_SESSION['tipo_documento'])) {
        echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    if (!isset($_POST['id_actividad']) || !is_numeric($_POST['id_actividad'])) {
        echo json_encode(['success' => false, 'message' => 'Parámetro inválido']);
        exit;
    }

    $idActividad = (int)$_POST['id_actividad'];

    // Obtener id del usuario autenticado
    $stmtUser = $conn->prepare("SELECT id FROM usuarios WHERE numero_documento = ? AND tipo_documento = ? LIMIT 1");
    $stmtUser->bind_param('ss', $_SESSION['documento'], $_SESSION['tipo_documento']);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($resUser->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }
    $idUsuario = (int)$resUser->fetch_assoc()['id'];
    $stmtUser->close();

    // Verificar que la actividad pertenece al usuario
    $stmtCheck = $conn->prepare("SELECT id FROM actividades WHERE id = ? AND usuario_id = ? LIMIT 1");
    $stmtCheck->bind_param('ii', $idActividad, $idUsuario);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Actividad no encontrada o sin permisos']);
        exit;
    }
    $stmtCheck->close();

    // Eliminar definitivamente
    $stmtDel = $conn->prepare("DELETE FROM actividades WHERE id = ? AND usuario_id = ?");
    $stmtDel->bind_param('ii', $idActividad, $idUsuario);
    $ok = $stmtDel->execute();
    $stmtDel->close();

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar']);
    }
} catch (Throwable $e) {
    error_log('Eliminar actividad error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}
