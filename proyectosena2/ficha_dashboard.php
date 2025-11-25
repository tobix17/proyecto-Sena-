<?php
session_start();
require 'database.php';

// Validar sesión
if (!isset($_SESSION['documento']) || !isset($_SESSION['tipo_documento']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Verificar ficha seleccionada
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ficha'])) {
    $_SESSION['ficha_seleccionada'] = $_POST['ficha'];
}

if (!isset($_SESSION['ficha_seleccionada'])) {
    // Redirigir según rol si no hay ficha seleccionada
    if ($_SESSION['rol'] === 'aprendiz') {
        header("Location: principal.php");
    } else {
        header("Location: instructores.php");
    }
    exit();
}

$ficha_id = $_SESSION['ficha_seleccionada'];

// Lógica para eliminar un aprendiz
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_aprendiz'])) {
    $aprendiz_id_a_eliminar = $_POST['aprendiz_id_a_eliminar'];

    // Iniciar transacción
    $conn->begin_transaction();
    try {
        // Eliminar bitácoras del aprendiz
        $sql_delete_bitacoras = "DELETE FROM bitacoras WHERE aprendiz_id=?";
        $stmt_delete_bitacoras = $conn->prepare($sql_delete_bitacoras);
        $stmt_delete_bitacoras->bind_param("i", $aprendiz_id_a_eliminar);
        $stmt_delete_bitacoras->execute();
        $stmt_delete_bitacoras->close();

        // Eliminar al usuario (aprendiz)
        $sql_delete_user = "DELETE FROM usuarios WHERE id=? AND rol='aprendiz'";
        $stmt_delete_user = $conn->prepare($sql_delete_user);
        $stmt_delete_user->bind_param("i", $aprendiz_id_a_eliminar);
        $stmt_delete_user->execute();
        $stmt_delete_user->close();

        // Confirmar transacción
        $conn->commit();
        $_SESSION['mensaje'] = "Aprendiz eliminado con éxito.";
    } catch (mysqli_sql_exception $exception) {
        // Revertir transacción en caso de error
        $conn->rollback();
        $_SESSION['error'] = "Error al eliminar el aprendiz: " . $exception->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']); // Redirigir para evitar re-envío del formulario
    exit();
}

// Obtener información de la ficha
$sql_ficha = "SELECT codigo_ficha, programa FROM fichas WHERE id=?";
$stmt = $conn->prepare($sql_ficha);
$stmt->bind_param("i", $ficha_id);
$stmt->execute();
$result_ficha = $stmt->get_result();
$ficha = $result_ficha->fetch_assoc();
$stmt->close();

// Obtener aprendices de la ficha y calcular estadísticas, ordenados alfabéticamente
$sql_aprendices = "SELECT id, nombre, apellido, numero_documento, correo
                   FROM usuarios
                   WHERE rol='aprendiz' AND ficha_id=?
                   ORDER BY nombre ASC";
$stmt2 = $conn->prepare($sql_aprendices);
$stmt2->bind_param("i", $ficha_id);
$stmt2->execute();
$result_aprendices = $stmt2->get_result();

$aprendices_data = [];
$aprendices_completos = 0;
$total_aprendices = 0;

while ($row = $result_aprendices->fetch_assoc()) {
    $total_aprendices++;
    $aprendiz_id = $row['id'];
    
    $sql_bitacoras_count = "SELECT COUNT(*) AS total FROM bitacoras WHERE aprendiz_id=? AND ficha_id=?";
    $stmt_count = $conn->prepare($sql_bitacoras_count);
    $stmt_count->bind_param("ii", $aprendiz_id, $ficha_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $bitacoras_count = (int)$result_count->fetch_assoc()['total'];
    $stmt_count->close();

    $row['bitacoras_subidas'] = $bitacoras_count;
    if ($bitacoras_count >= 12) {
        $aprendices_completos++;
    }
    $aprendices_data[] = $row;
}
$stmt2->close();

$aprendices_incompletos = $total_aprendices - $aprendices_completos;
$porcentaje_completos = $total_aprendices > 0 ? round(($aprendices_completos / $total_aprendices) * 100) : 0;
$porcentaje_incompletos = $total_aprendices > 0 ? round(($aprendices_incompletos / $total_aprendices) * 100) : 0;

// Determinar página principal según rol
$pagina_principal = ($_SESSION['rol'] === 'aprendiz') ? 'principal.php' : 'instructores.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha <?= htmlspecialchars($ficha['codigo_ficha']); ?> - <?= htmlspecialchars($ficha['programa']); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        h2 {
            color: #4CAF50;
            font-weight: bold;
        }
        .card-custom {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .table thead {
            background-color: #4CAF50;
            color: #fff;
        }
        .completo {
            color: #2e7d32;
            font-weight: bold;
        }
        .incompleto {
            color: #d84315;
            font-weight: bold;
        }
        .btn-success-custom {
            background-color: #4CAF50;
            border: none;
            font-size: 1.1rem;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .btn-success-custom:hover {
            background-color: #388E3C;
        }
        .table tbody tr {
            background-color: #f0f0f0;
        }
        .btn-delete-custom {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-delete-custom:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        /* Estilos para las estadísticas */
        .stats-card {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .chart-container {
            position: relative;
            height: 320px;
            margin-bottom: 30px;
            padding: 8px;
            border-radius: 12px;
        }
        .card-custom .chart-container {
            background: #ffffff;
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
        .bg-dark .stats-card {
            background: linear-gradient(135deg, #66bb6a, #4caf50) !important;
            color: #fff !important;
        }
        .bg-dark .table {
            color: #e0e0e0 !important;
        }
        .bg-dark .table thead {
            background-color: #2c2c2c !important;
            color: #fff !important;
        }
        .bg-dark .table tbody tr {
            background-color: #2c2c2c !important;
        }
        /* Asegurar fondo oscuro SOLO en la sección de la tabla de aprendices */
        .bg-dark .table-responsive {
            background-color: #1c1c1c !important;
        }
        .bg-dark .table thead th {
            background-color: #2b2b2b !important;
            color: #eaeaea !important;
            border-color: #3a3a3a !important;
        }
        .bg-dark .table tbody td {
            background-color: #262626 !important;
            color: #e0e0e0 !important;
            border-color: rgba(255,255,255,0.08) !important;
        }
        .bg-dark .table-hover tbody tr:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.05);
        }
        .bg-dark .text-muted {
            color: #a0a0a0 !important;
        }
        .bg-dark .text-success {
            color: #66bb6a !important;
        }
        .bg-dark .badge.bg-success {
            background-color: #66bb6a !important;
        }
        .bg-dark .completo {
            color: #66bb6a !important;
        }
        .bg-dark .incompleto {
            color: #ff8a65 !important;
        }
        .bg-dark .btn-success-custom {
            background-color: #66bb6a !important;
            color: #1c1c1c !important;
        }
        .bg-dark .btn-success-custom:hover {
            background-color: #4caf50 !important;
        }
        .bg-dark .btn-delete-custom {
            background-color: #d9534f !important;
            border-color: #d9534f !important;
        }
        .bg-dark .btn-delete-custom:hover {
            background-color: #c9302c !important;
            border-color: #c9302c !important;
        }
        .bg-dark .modal-content {
            background-color: #1c1c1c;
            color: #e0e0e0;
        }
        .bg-dark .modal-header, .bg-dark .modal-footer {
            border-color: #333;
        }
        .bg-dark .alert-success {
            background-color: #215e24;
            color: #a3e6a3;
            border-color: #2e7d32;
        }
        .bg-dark .alert-warning {
            background-color: #7a6627;
            color: #ffe082;
            border-color: #ffa000;
        }
        .bg-dark .alert-danger {
            background-color: #7b2424;
            color: #ffcdd2;
            border-color: #d32f2f;
        }
        .bg-dark .text-primary {
            color: #79a1d9 !important;
        }
        .bg-dark .card-custom .chart-container { background: #121212; }
        /* ===================== Mejora de estilo general (modo claro) ===================== */
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif;
            line-height: 1.5;
        }
        .container {
            max-width: 1140px;
        }
        h2, h4, h5 {
            letter-spacing: .2px;
        }
        .card-custom {
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .card-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
        }
        .stats-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 110px;
        }
        .stat-number { letter-spacing: .5px; }
        .stat-label { opacity: .95; font-weight: 500; }
        /* Tablas */
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        .table thead th {
            border: 0;
            letter-spacing: .3px;
        }
        .table tbody tr {
            transition: background-color .15s ease, transform .05s ease;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(76,175,80,0.08);
        }
        .table tbody td { vertical-align: middle; }
        .badge { border-radius: 10px; padding: .4rem .6rem; font-weight: 600; }
        /* Botones */
        .btn-success-custom {
            box-shadow: 0 4px 12px rgba(76,175,80,.25);
        }
        .btn-success-custom:focus { box-shadow: 0 0 0 .2rem rgba(40,167,69,.35); }
        .btn-delete-custom {
            color: #fff;
            box-shadow: 0 3px 10px rgba(220,53,69,.25);
        }
        .btn-delete-custom:focus { box-shadow: 0 0 0 .2rem rgba(220,53,69,.35); }
        .btn:focus { outline: none; }
        /* Estados de fila */
        .completo { color: #2e7d32; }
        .incompleto { color: #d84315; }
        /* Enlaces dentro de tabla */
        .table a.text-success { text-underline-offset: 2px; }
        .table a.text-success:hover { text-decoration: underline; }
        /* Gráficas */
        .chart-container { background: transparent; }
        /* Responsividad suave */
        @media (max-width: 768px) {
            .stat-number { font-size: 2rem; }
            .stats-card { min-height: 100px; }
            h2 { font-size: 1.5rem; }
            h4 { font-size: 1.1rem; }
        }
        /* ===================== Ajustes modo oscuro adicionales ===================== */
        .bg-dark .table-hover tbody tr:hover {
            background-color: rgba(255,255,255,.06);
        }
        .bg-dark .table tbody td { border-color: rgba(255,255,255,.08) !important; }
        .bg-dark .badge { color: #eaeaea; }
        .bg-dark .btn-success-custom { box-shadow: 0 4px 12px rgba(102,187,106,.25); }
        .bg-dark .btn-success-custom:focus { box-shadow: 0 0 0 .2rem rgba(102,187,106,.35) !important; }
        .bg-dark .btn-delete-custom { box-shadow: 0 3px 10px rgba(217,83,79,.25); }
        .bg-dark .btn-delete-custom:focus { box-shadow: 0 0 0 .2rem rgba(217,83,79,.35) !important; }
        .bg-dark .card-custom:hover {
            box-shadow: 0 10px 24px rgba(255,255,255,.08) !important;
        }
        .bg-dark .table thead th { border: 0 !important; }
        .bg-dark .text-warning { color: #ffcc80 !important; }
        .bg-dark .text-info { color: #81d4fa !important; }
    </style>
</head>
<body <?php
    $temaSesion = isset($_SESSION['tema_oscuro']) ? intval($_SESSION['tema_oscuro']) : null;
    $temaCookie = isset($_COOKIE['tema_oscuro']) ? intval($_COOKIE['tema_oscuro']) : null;
    $isDark = ($temaSesion === 1) || ($temaSesion === null && $temaCookie === 1);
    echo $isDark ? 'class="bg-dark"' : '';
?>>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $total_aprendices; ?></div>
                <div class="stat-label">Total Aprendices</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $aprendices_completos; ?></div>
                <div class="stat-label">Al Día (12+ Bitácoras)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $aprendices_incompletos; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $porcentaje_completos; ?>%</div>
                <div class="stat-label">Porcentaje Completo</div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-3">
                <h5 class="text-center mb-3">Estado de Bitácoras - Gráfica Circular</h5>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-custom p-3">
                <h5 class="text-center mb-3">Progreso de Aprendices - Gráfica de Barras</h5>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-custom p-4 mb-4">
        <h4 class="text-center mb-3">📊 Resumen Estadístico</h4>
        <div class="row text-center">
            <div class="col-md-4">
                <h5 class="text-success"><?= $porcentaje_completos; ?>%</h5>
                <p>de los aprendices están al día con las bitácoras</p>
            </div>
            <div class="col-md-4">
                <h5 class="text-warning"><?= $porcentaje_incompletos; ?>%</h5>
                <p>de los aprendices tienen bitácoras pendientes</p>
            </div>
            <div class="col-md-4">
                <h5 class="text-info"><?= $aprendices_completos; ?>/<?= $total_aprendices; ?></h5>
                <p>aprendices han completado todas las bitácoras</p>
            </div>
        </div>
    </div>
    
    <div class="card card-custom p-4">
        <h2 class="mb-4 text-center">
            Ficha <?= htmlspecialchars($ficha['codigo_ficha']); ?> <br>
            <small class="text-muted"><?= htmlspecialchars($ficha['programa']); ?></small>
        </h2>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Correo</th>
                        <th>Bitácoras Subidas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aprendices_data as $aprendiz): ?>
                        <?php
                        // Estado
                        $estado = ($aprendiz['bitacoras_subidas'] >= 12)
                            ? "✅ Completo"
                            : "⏳ Incompleto (" . (12 - $aprendiz['bitacoras_subidas']) . " faltantes)";
                        $clase_estado = ($aprendiz['bitacoras_subidas'] >= 12) ? "completo" : "incompleto";
                        ?>
                        <tr>
                            <td>
                                <a href="ver_bitacoras.php?aprendiz_id=<?= $aprendiz['id']; ?>&ficha_id=<?= $ficha_id; ?>"
                                   class="text-decoration-none fw-bold text-success">
                                    <?= htmlspecialchars($aprendiz['nombre'].' '.$aprendiz['apellido']); ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($aprendiz['numero_documento']); ?></td>
                            <td><?= htmlspecialchars($aprendiz['correo']); ?></td>
                            <td><span class="badge bg-success"><?= $aprendiz['bitacoras_subidas'] ?>/12</span></td>
                            <td class="<?= $clase_estado; ?>"><?= $estado; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-delete-custom"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-aprendiz-id="<?= $aprendiz['id']; ?>"
                                        data-aprendiz-nombre="<?= htmlspecialchars($aprendiz['nombre'].' '.$aprendiz['apellido']); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <a href="<?= $pagina_principal; ?>" class="btn btn-success-custom shadow">
                ⬅ Volver a la página principal
            </a>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar al aprendiz <strong id="aprendizNombre"></strong>? Esta acción no se puede deshacer y también se eliminarán sus bitácoras asociadas.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form id="deleteForm" action="<?= $_SERVER['PHP_SELF']; ?>" method="POST">
            <input type="hidden" name="eliminar_aprendiz" value="1">
            <input type="hidden" name="aprendiz_id_a_eliminar" id="aprendizIdAEliminar">
            <button type="submit" class="btn btn-danger">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Tema aplicado por sesión

        // Script para manejar el modal de eliminación
        const confirmDeleteModal = document.getElementById('confirmDeleteModal');
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const aprendizId = button.getAttribute('data-aprendiz-id');
            const aprendizNombre = button.getAttribute('data-aprendiz-nombre');

            const modalBody = confirmDeleteModal.querySelector('.modal-body strong');
            const formInput = confirmDeleteModal.querySelector('#aprendizIdAEliminar');

            modalBody.textContent = aprendizNombre;
            formInput.value = aprendizId;
        });

        // Datos para las gráficas
        const aprendicesCompletos = <?= $aprendices_completos; ?>;
        const aprendicesIncompletos = <?= $aprendices_incompletos; ?>;
        const totalAprendices = <?= $total_aprendices; ?>;
        const isDarkMode = document.body.classList.contains('bg-dark');

        // Gráfica Circular (Pie Chart)
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Al Día (12+ Bitácoras)', 'Pendientes'],
                datasets: [{
                    data: [aprendicesCompletos, aprendicesIncompletos],
                    backgroundColor: [
                        isDarkMode ? '#66bb6a' : '#4CAF50',
                        isDarkMode ? '#ff8a65' : '#f44336'
                    ],
                    borderColor: isDarkMode ? '#1c1c1c' : '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                layout: { padding: 8 },
                plugins: {
                    title: {
                        display: true,
                        text: 'Estado de Bitácoras',
                        color: isDarkMode ? '#e0e0e0' : '#333333',
                        font: { weight: '600', size: 14 }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: isDarkMode ? '#e0e0e0' : '#333333',
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const percentage = totalAprendices > 0 ? ((value / totalAprendices) * 100).toFixed(1) : 0;
                                return context.label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Gráfica de Barras
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Al Día', 'Pendientes'],
                datasets: [{
                    label: 'Número de Aprendices',
                    data: [aprendicesCompletos, aprendicesIncompletos],
                    backgroundColor: [
                        isDarkMode ? '#66bb6a' : '#4CAF50',
                        isDarkMode ? '#ff8a65' : '#f44336'
                    ],
                    borderColor: [
                        isDarkMode ? '#4caf50' : '#45a049',
                        isDarkMode ? '#f44336' : '#F57C00'
                    ],
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 36,
                    categoryPercentage: 0.6,
                    barPercentage: 0.75
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 8 },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: isDarkMode ? '#a0a0a0' : '#333333'
                        },
                        grid: {
                            color: isDarkMode ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: isDarkMode ? '#a0a0a0' : '#333333'
                        },
                        grid: {
                            color: isDarkMode ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Progreso de Aprendices',
                        color: isDarkMode ? '#e0e0e0' : '#333333',
                        font: { weight: '600', size: 14 }
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const percentage = totalAprendices > 0 ? ((context.raw / totalAprendices) * 100).toFixed(1) : 0;
                                return 'Aprendices: ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    });
</script>

</body>
</html>