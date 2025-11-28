<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

// --- Lógica para Estadísticas Globales ---
$sql_total_users = "SELECT COUNT(*) FROM usuarios WHERE role = 3";
$total_usuarios_activos = $conn->query($sql_total_users)->fetchColumn();

$sql_requerimientos = "
    SELECT r.id, r.titulo, r.fecha_limite, COUNT(e.id) as numero_entregas
    FROM requerimientos r
    LEFT JOIN entregas e ON r.id = e.requerimiento_id
    GROUP BY r.id, r.titulo, r.fecha_limite
    ORDER BY r.fecha_limite DESC";
$requerimientos = $conn->query($sql_requerimientos)->fetchAll(PDO::FETCH_ASSOC);

// --- Lógica para Consulta de Usuario Específico ---
$lista_enlaces = $conn->query("SELECT id, nombre_completo, expediente FROM usuarios WHERE role = 3 ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_user_id = null;
$selected_user_info = null;
$user_history = [];
$stats = ['total' => 0, 'entregados' => 0, 'faltantes' => 0, 'porcentaje' => 0];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $selected_user_id = $_GET['user_id'];

    $stmt_user = $conn->prepare("SELECT nombre_completo, expediente FROM usuarios WHERE id = :id AND role = 3");
    $stmt_user->execute([':id' => $selected_user_id]);
    $selected_user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($selected_user_info) {
        $sql_history = "
            SELECT r.titulo, r.fecha_limite, e.fecha_entrega, e.ruta_archivo
            FROM requerimientos r
            LEFT JOIN entregas e ON r.id = e.requerimiento_id AND e.user_id = :user_id
            ORDER BY r.fecha_limite DESC";
        $stmt_history = $conn->prepare($sql_history);
        $stmt_history->execute([':user_id' => $selected_user_id]);
        $user_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($user_history)) {
            $stats['total'] = count($user_history);
            $stats['entregados'] = count(array_filter($user_history, function($item) { return !is_null($item['fecha_entrega']); }));
            $stats['faltantes'] = $stats['total'] - $stats['entregados'];
            if ($stats['total'] > 0) {
                $stats['porcentaje'] = ($stats['entregados'] / $stats['total']) * 100;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HISTORIAL DE RECIBIMIENTO</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- NUEVO: Inclusión de Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>HISTORIAL DE RECIBIMIENTO Y ESTADÍSTICAS</h1>
        <a href="<?php echo ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php'; ?>" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>CONSULTA DE HISTORIAL POR USUARIO</h3>
            <form action="historial_recibimiento.php" method="GET">
                <div class="form-group">
                    <label for="user_id">SELECCIONA UN ENLACE:</label>
                    <select name="user_id" id="user_id" required onchange="this.form.submit()">
                        <option value="">-- ELIGE UN USUARIO --</option>
                        <?php foreach ($lista_enlaces as $enlace): ?>
                            <option value="<?php echo $enlace['id']; ?>" <?php echo ($enlace['id'] == $selected_user_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($enlace['nombre_completo'] . ' (' . $enlace['expediente'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="action-btn">CONSULTAR</button></noscript>
            </form>
        </section>

        <?php if ($selected_user_id && $selected_user_info): ?>
            <section class="stats-summary-section">
                <h3>RESUMEN PARA: <strong><?php echo htmlspecialchars($selected_user_info['nombre_completo']); ?></strong></h3>
                <div class="stats-container">
                    <div class="stats-cards-container">
                        <div class="stat-card">
                            <span class="stat-value"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">TOTAL ASIGNADOS</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value green-text"><?php echo $stats['entregados']; ?></span>
                            <span class="stat-label">ENTREGADOS</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value red-text"><?php echo $stats['faltantes']; ?></span>
                            <span class="stat-label">FALTANTES</span>
                        </div>
                        <div class="stat-card highlight">
                            <span class="stat-value-percent"><?php echo round($stats['porcentaje'], 1); ?>%</span>
                            <span class="stat-label">CUMPLIMIENTO</span>
                        </div>
                    </div>
                    <!-- NUEVO: Contenedor para la gráfica -->
                    <div class="stats-chart-container">
                        <canvas id="complianceChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="table-section" style="margin-top: 20px;">
                <h4>DESGLOSE DE REQUERIMIENTOS</h4>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>REQUERIMIENTO</th><th>FECHA LÍMITE</th><th>ESTADO</th><th>FECHA DE ENTREGA</th><th>ACCIÓN</th></tr></thead>
                        <tbody>
                            <?php foreach ($user_history as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['titulo']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($item['fecha_limite'])); ?></td>
                                    <td><?php echo $item['fecha_entrega'] ? '<span class="status-entregado">ENTREGADO</span>' : '<span class="status-faltante">FALTANTE</span>'; ?></td>
                                    <td><?php echo $item['fecha_entrega'] ? date("d/m/Y H:i", strtotime($item['fecha_entrega'])) : '-'; ?></td>
                                    <td><?php echo $item['ruta_archivo'] ? '<a href="' . $base_path . '/' . htmlspecialchars($item['ruta_archivo']) . '" class="action-btn-small green" download>DESCARGAR</a>' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <hr class="section-divider">

        <section class="table-section">
            <h3>SUPERVISIÓN DE ENTREGAS POR EVENTO</h3>
            <div class="table-responsive">
                 <table>
                    <thead><tr><th>TÍTULO DEL REQUERIMIENTO</th><th>FECHA LÍMITE</th><th>PROGRESO DE ENTREGA</th><th>ACCIONES</th></tr></thead>
                    <tbody>
                        <?php if (empty($requerimientos)): ?>
                            <tr><td colspan="4" style="text-align: center;">NO HAY REQUERIMIENTOS CREADOS.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requerimientos as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['titulo']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($req['fecha_limite'])); ?></td>
                                    <td><strong><?php echo $req['numero_entregas']; ?> / <?php echo $total_usuarios_activos; ?></strong> ENTREGADOS</td>
                                    <td class="actions-cell"><a href="detalle_recibimiento.php?req_id=<?php echo $req['id']; ?>" class="action-btn-small blue">VER DETALLES</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p>
    </footer>

    <!-- NUEVO: Script para generar la gráfica -->
    <?php if ($selected_user_id && $selected_user_info && $stats['total'] > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('complianceChart').getContext('2d');
            const complianceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['ENTREGADOS', 'FALTANTES'],
                    datasets: [{
                        label: 'ESTADO DE ENTREGAS',
                        data: [<?php echo $stats['entregados']; ?>, <?php echo $stats['faltantes']; ?>],
                        backgroundColor: [
                            '#28a745', // Verde para entregados
                            '#dc3545'  // Rojo para faltantes
                        ],
                        borderColor: [
                            '#ffffff',
                            '#ffffff'
                        ],
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 14,
                                    family: 'Poppins'
                                }
                            }
                        },
                        tooltip: {
                            enabled: true
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>

</body>
</html>