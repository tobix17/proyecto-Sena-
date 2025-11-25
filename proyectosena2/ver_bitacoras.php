<?php
session_start();
require 'database.php';

// Validar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Validar parámetros recibidos
if (!isset($_GET['aprendiz_id']) || !isset($_GET['ficha_id'])) {
    die("❌ Parámetros inválidos.");
}

$aprendiz_id = (int) $_GET['aprendiz_id'];
$ficha_id = (int) $_GET['ficha_id'];

// Manejar actualización de estado (solo instructores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['rol']) && $_SESSION['rol'] === 'instructor') {
    $id_bitacora = isset($_POST['id_bitacora']) ? (int)$_POST['id_bitacora'] : 0;
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
    $permitidos = ['borrador', 'completada', 'revisada', 'aprobada', 'rechazada'];
    if ($id_bitacora > 0 && in_array($nuevo_estado, $permitidos, true)) {
        $stmt_up = $conn->prepare("UPDATE bitacoras SET estado=? WHERE id=? AND aprendiz_id=? AND ficha_id=?");
        $stmt_up->bind_param('siii', $nuevo_estado, $id_bitacora, $aprendiz_id, $ficha_id);
        if ($stmt_up->execute()) {
            $_SESSION['vb_msg'] = 'Estado actualizado a \'' . htmlspecialchars($nuevo_estado) . '\' correctamente.';
            $_SESSION['vb_type'] = 'success';
        } else {
            $_SESSION['vb_msg'] = 'No se pudo actualizar el estado.';
            $_SESSION['vb_type'] = 'danger';
        }
        $stmt_up->close();
    } else {
        $_SESSION['vb_msg'] = 'Datos inválidos para actualizar el estado.';
        $_SESSION['vb_type'] = 'warning';
    }
    header('Location: ver_bitacoras.php?aprendiz_id=' . $aprendiz_id . '&ficha_id=' . $ficha_id);
    exit();
}

// Obtener datos del aprendiz
$sql_aprendiz = "SELECT nombre, apellido, numero_documento, correo 
                 FROM usuarios 
                 WHERE id=?";
$stmt = $conn->prepare($sql_aprendiz);
$stmt->bind_param("i", $aprendiz_id);
$stmt->execute();
$aprendiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener datos de la ficha (código y programa)
$sql_ficha = "SELECT codigo_ficha, programa FROM fichas WHERE id=? LIMIT 1";
$stmtFicha = $conn->prepare($sql_ficha);
$stmtFicha->bind_param("i", $ficha_id);
$stmtFicha->execute();
$ficha = $stmtFicha->get_result()->fetch_assoc();
$stmtFicha->close();

// Obtener bitácoras de ese aprendiz
$sql_bitacoras = "SELECT id, numero_bitacora, fecha_subida, archivo, firma_aprendiz, nombre_empresa, nit,
                         periodo, nombre_jefe, telefono_contacto, correo_contacto,
                         modalidad_etapa, nombre_aprendiz, documento_aprendiz, telefono_aprendiz, correo_aprendiz,
                         programa_formacion, descripcion_actividad, fecha_inicio, fecha_fin,
                         evidencia_cumplimiento, observaciones, estado, fecha_entrega
                 FROM bitacoras
                 WHERE aprendiz_id=? AND ficha_id=?
                 ORDER BY numero_bitacora ASC";
$stmt2 = $conn->prepare($sql_bitacoras);
$stmt2->bind_param("ii", $aprendiz_id, $ficha_id);
$stmt2->execute();
$result_bitacoras = $stmt2->get_result();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácoras de <?= htmlspecialchars($aprendiz['nombre'].' '.$aprendiz['apellido']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        h2 { color: #4CAF50; font-weight: bold; }
        .card-custom {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table thead { background-color: #4CAF50; color: #fff; }
        .badge { font-size: 0.9rem; }
        .bg-gray-custom {
            background-color: #f0f0f0; 
            border-radius: 5px;
            padding: 10px;
        }

        /* ESTILOS PARA MODO OSCURO */
        body.bg-dark {
            background-color: #121212 !important;
            color: #e0e0e0;
        }
        .bg-dark h2 {
            color: #66bb6a !important;
        }
        .bg-dark .card-custom {
            background-color: #1c1c1c !important;
            color: #e0e0e0 !important;
            box-shadow: 0 4px 10px rgba(255,255,255,0.1) !important;
        }
        .bg-dark .text-muted {
            color: #a0a0a0 !important;
        }
        .bg-dark .text-success {
            color: #66bb6a !important;
        }
        .bg-dark .alert-info {
            background-color: #1c1c1c;
            color: #e0e0e0;
            border-color: #333;
        }
        .bg-dark .bg-gray-custom {
            background-color: #2c2c2c !important; 
        }
    </style>
</head>
<body <?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
    echo $isDark ? 'class="bg-dark"' : '';
?>>

<div class="container my-5">
    <div class="card card-custom p-4">
        <h2 class="mb-1 text-center">
            Bitácoras de <?= htmlspecialchars($aprendiz['nombre'].' '.$aprendiz['apellido']); ?>
        </h2>
        <p class="text-center text-muted mb-4">
            <?php if (!empty($ficha)): ?>
                Ficha: <strong><?= htmlspecialchars($ficha['codigo_ficha']); ?></strong>
                <?php if (!empty($ficha['programa'])): ?> - Programa: <strong><?= htmlspecialchars($ficha['programa']); ?></strong><?php endif; ?>
            <?php else: ?>
                Ficha ID: <strong><?= (int)$ficha_id; ?></strong>
            <?php endif; ?>
        </p>
        <?php if (isset($_SESSION['vb_msg'])): ?>
            <div class="alert alert-<?= $_SESSION['vb_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['vb_msg']; unset($_SESSION['vb_msg'], $_SESSION['vb_type']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <p class="text-center text-muted">
            Documento: <?= htmlspecialchars($aprendiz['numero_documento']); ?> | 
            Correo: <?= htmlspecialchars($aprendiz['correo']); ?>
        </p>

        <?php if ($result_bitacoras->num_rows > 0): ?>
            <?php while ($bitacora = $result_bitacoras->fetch_assoc()): ?>
                <div class="card card-custom p-4 mb-4">
                    <div class="text-center mb-4">
                        <img src="imagenes/logoSena.png" alt="Logo SENA" class="mb-3" style="height: 80px;">
                        <h2 class="fw-bold">Bitácora de Seguimiento Etapa Productiva</h2>
                        <small>Proceso Gestión de Formación Profesional Integral</small><br>
                        <small>Formato Bitácora seguimiento Etapa productiva</small><br>
                        <strong class="text-uppercase">REGIONAL ANTIOQUIA</strong><br>
                        <strong class="text-uppercase">CENTRO DE FORMACIÓN MINERO AMBIENTAL</strong>
                    </div>

                    <h4 class="text-center mb-3 text-success">Datos Generales</h4>
                    <div class="mb-3">
                        <p class="mb-1"><strong><i class="fas fa-building me-2"></i>Nombre de la empresa:</strong> <?= htmlspecialchars($bitacora['nombre_empresa']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-id-card me-2"></i>NIT:</strong> <?= htmlspecialchars($bitacora['nit']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-file-alt me-2"></i>Bitácora N°:</strong> <?= htmlspecialchars($bitacora['numero_bitacora']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-calendar-alt me-2"></i>Periodo:</strong> <?= htmlspecialchars($bitacora['periodo']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-user-tie me-2"></i>Nombre del jefe inmediato/Responsable:</strong> <?= htmlspecialchars($bitacora['nombre_jefe']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-phone me-2"></i>Teléfono de contacto:</strong> <?= htmlspecialchars($bitacora['telefono_contacto']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-envelope me-2"></i>Correo electrónico:</strong> <?= htmlspecialchars($bitacora['correo_contacto']); ?></p>
                    </div>

                    <div class="my-3 p-4 bg-gray-custom rounded shadow-sm">
                        <h4 class="text-center mb-3 text-success">Modalidad de Etapa Productiva</h4>
                        <p class="text-center mb-0"><strong><?= htmlspecialchars($bitacora['modalidad_etapa']); ?></strong></p>
                    </div>

                    <h4 class="text-center mb-3 text-success">Datos del Aprendiz</h4>
                    <div class="mb-3">
                        <p class="mb-1"><strong><i class="fas fa-user-graduate me-2"></i>Nombre del aprendiz:</strong> <?= htmlspecialchars($bitacora['nombre_aprendiz']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-id-card me-2"></i>Documento Id.:</strong> <?= htmlspecialchars($bitacora['documento_aprendiz']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-phone me-2"></i>Teléfono de contacto:</strong> <?= htmlspecialchars($bitacora['telefono_aprendiz']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-envelope me-2"></i>Correo electrónico institucional:</strong> <?= htmlspecialchars($bitacora['correo_aprendiz']); ?></p>
                        <p class="mb-1"><strong><i class="fas fa-book me-2"></i>Programa de formación:</strong> <?= htmlspecialchars($bitacora['programa_formacion']); ?></p>
                    </div>

                    <?php if (isset($_SESSION['rol']) && strtolower(trim($_SESSION['rol'])) === 'instructor' && !empty($bitacora['firma_aprendiz'])): ?>
                    <div class="my-3 p-4 bg-gray-custom rounded shadow-sm text-center">
                        <h5 class="text-success mb-3"><i class="fas fa-signature me-2"></i>Firma del Aprendiz</h5>
                        <img src="<?= htmlspecialchars($bitacora['firma_aprendiz']); ?>" alt="Firma del aprendiz" style="max-height:120px; max-width:100%; object-fit:contain; border:1px solid #ddd; background:#fff; padding:8px; border-radius:8px;">
                    </div>
                    <?php elseif (isset($_SESSION['rol']) && strtolower(trim($_SESSION['rol'])) === 'instructor'): ?>
                    <div class="my-3 p-3 bg-gray-custom rounded text-center">
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Este registro no tiene firma de aprendiz cargada.</small>
                    </div>
                    <?php endif; ?>

                    <h4 class="text-center mb-3 text-success">Actividades Desarrolladas</h4>
                    <div class="my-3 p-4 bg-gray-custom rounded shadow-sm">
                        <p class="mb-0"><strong>Descripción de la Actividad:</strong></p>
                        <p><?= nl2br(htmlspecialchars($bitacora['descripcion_actividad'])); ?></p>
                        <p class="mb-0"><strong>Fecha Inicio:</strong> <?= htmlspecialchars($bitacora['fecha_inicio']); ?></p>
                        <p class="mb-0"><strong>Fecha Fin:</strong> <?= htmlspecialchars($bitacora['fecha_fin']); ?></p>
                    </div>
                    
                    <div class="my-3 p-4 bg-gray-custom rounded shadow-sm">
                        <p class="mb-0"><strong>Evidencia de Cumplimiento:</strong></p>
                        <p><?= nl2br(htmlspecialchars($bitacora['evidencia_cumplimiento'])); ?></p>
                    </div>
                    
                    <div class="my-3 p-4 bg-gray-custom rounded shadow-sm">
                        <p class="mb-0"><strong>Observaciones, Inasistencias y/o Dificultades:</strong></p>
                        <p><?= nl2br(htmlspecialchars($bitacora['observaciones'] ?? 'Ninguna')); ?></p>
                    </div>
                    
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'instructor'): ?>
                    <h4 class="text-center mb-3 text-success">Firmas</h4>
                    <div class="row mb-4 g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-chalkboard-teacher me-2"></i>Firma del Instructor de Seguimiento</label>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre_instructor_<?= (int)$bitacora['id']; ?>" class="form-label">Nombre del instructor de seguimiento: <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_instructor_<?= (int)$bitacora['id']; ?>" id="nombre_instructor_<?= (int)$bitacora['id']; ?>" 
                                   class="form-control" 
                                   placeholder="Ingrese el nombre completo del instructor" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="firma_instructor_<?= (int)$bitacora['id']; ?>" class="form-label">Subir firma (imagen) <span class="text-danger">*</span></label>
                            <input type="file" name="firma_instructor_<?= (int)$bitacora['id']; ?>" id="firma_instructor_<?= (int)$bitacora['id']; ?>" 
                                   class="form-control" 
                                   accept="image/png,image/jpeg,image/jpg" 
                                   required>
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG (máx. 2MB)</small>
                            <div id="preview_firma_instructor_<?= (int)$bitacora['id']; ?>" class="mt-2"></div>
                        </div>
                    </div>

                    <div class="row mb-4 g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-user-tie me-2"></i>Firma del Jefe Inmediato (Opcional)</label>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre_jefe_firma_<?= (int)$bitacora['id']; ?>" class="form-label">Nombre del jefe inmediato:</label>
                            <input type="text" name="nombre_jefe_firma_<?= (int)$bitacora['id']; ?>" id="nombre_jefe_firma_<?= (int)$bitacora['id']; ?>" 
                                   class="form-control" 
                                   placeholder="Ingrese el nombre si aplica">
                        </div>
                        <div class="col-md-6">
                            <label for="firma_jefe_<?= (int)$bitacora['id']; ?>" class="form-label">Subir firma (imagen)</label>
                            <input type="file" name="firma_jefe_<?= (int)$bitacora['id']; ?>" id="firma_jefe_<?= (int)$bitacora['id']; ?>" 
                                   class="form-control" 
                                   accept="image/png,image/jpeg,image/jpg">
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG (máx. 2MB)</small>
                            <div id="preview_firma_jefe_<?= (int)$bitacora['id']; ?>" class="mt-2"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p class="mt-3">
                        <strong>Estado:</strong> 
                        <?php if ($bitacora['estado'] === 'completada' || $bitacora['estado'] === 'aprobada'): ?>
                            <span class="badge bg-success"><?= htmlspecialchars($bitacora['estado']); ?></span>
                        <?php elseif ($bitacora['estado'] === 'revisada'): ?>
                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($bitacora['estado']); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($bitacora['estado']); ?></span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Fecha entrega:</strong> <?= $bitacora['fecha_entrega']; ?></p>

                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'instructor'): ?>
                        <form method="POST" class="row g-2 align-items-end mt-2">
                            <div class="col-sm-6 col-md-4">
                                <label for="estado-<?= $bitacora['id']; ?>" class="form-label">Cambiar estado</label>
                                <select id="estado-<?= $bitacora['id']; ?>" name="estado" class="form-select" required>
                                    <?php
                                    $options = ['borrador'=>'Borrador','completada'=>'Completada','revisada'=>'Revisada','aprobada'=>'Aprobada','rechazada'=>'Rechazada'];
                                    foreach ($options as $val => $label): ?>
                                        <option value="<?= $val; ?>" <?= $bitacora['estado']===$val ? 'selected' : ''; ?>><?= $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <input type="hidden" name="id_bitacora" value="<?= (int)$bitacora['id']; ?>">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-save me-1"></i>Actualizar estado</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($bitacora['archivo'])): ?>
                        <a href="uploads/<?= htmlspecialchars($bitacora['archivo']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-3">📂 Ver archivo</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">❌ Este aprendiz no ha subido bitácoras aún.</div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="ficha_dashboard.php" class="btn btn-success">⬅ Volver a la ficha</a>
        </div>
    </div>
</div>

<!-- Tema aplicado por sesión -->

</body>
</html>