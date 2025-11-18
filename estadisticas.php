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

// --- OBTENER FILTROS ---
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$selected_area = isset($_GET['area']) ? $_GET['area'] : 'all';
$selected_curso_id = isset($_GET['curso_id']) ? (is_numeric($_GET['curso_id']) ? (int)$_GET['curso_id'] : 'all') : 'all';
$user_search_query = isset($_GET['user_query']) ? trim($_GET['user_query']) : '';

$stats = [
    'total_cursos' => 0,
    'total_asistencias' => 0,
    'total_horas_capacitacion' => 0,
    'reporte_detallado' => [],
    'historial_empleado' => [],
    'empleado_info' => null,
];
$available_years = [];
$available_areas = [];
$available_cursos = [];

try {
    // --- OBTENER DATOS PARA FILTROS ---
    $years_query = $conn->query("SELECT DISTINCT YEAR(fecha_inicio) as year FROM asistencias_cursos ORDER BY year DESC");
    $available_years = $years_query->fetchAll(PDO::FETCH_COLUMN);
    $areas_query = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL AND area != '' ORDER BY area ASC");
    $available_areas = $areas_query->fetchAll(PDO::FETCH_COLUMN);
    $cursos_query = $conn->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
    $available_cursos = $cursos_query->fetchAll(PDO::FETCH_ASSOC);

    // --- CONSTRUIR CONSULTA PRINCIPAL CON FILTROS ---
    $main_query_conditions = [];
    $main_query_params = [];

    if ($selected_year != 'all') {
        $main_query_conditions[] = "YEAR(a.fecha_inicio) = :year";
        $main_query_params[':year'] = $selected_year;
    }
    if ($selected_area != 'all') {
        $main_query_conditions[] = "u.area = :area";
        $main_query_params[':area'] = $selected_area;
    }
    if ($selected_curso_id != 'all') {
        $main_query_conditions[] = "a.curso_id = :curso_id";
        $main_query_params[':curso_id'] = $selected_curso_id;
    }

    $where_clause = "";
    if (!empty($main_query_conditions)) {
        $where_clause = " WHERE " . implode(' AND ', $main_query_conditions);
    }

    // --- REPORTE DETALLADO ---
    $reporte_sql = 
        "SELECT u.nombre_completo, u.expediente, u.area, c.nombre_curso, a.fecha_inicio, a.duracion_horas " .
        "FROM asistencias_cursos a " .
        "JOIN usuarios u ON a.user_id = u.id " .
        "JOIN cursos c ON a.curso_id = c.id" .
        $where_clause . 
        " ORDER BY u.nombre_completo, a.fecha_inicio DESC";

    $stmt_reporte = $conn->prepare($reporte_sql);
    $stmt_reporte->execute($main_query_params);
    $stats['reporte_detallado'] = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // --- TARJETAS DE ESTADÍSTICAS GENERALES ---
    $stats['total_asistencias'] = count($stats['reporte_detallado']);
    $stats['total_horas_capacitacion'] = array_sum(array_column($stats['reporte_detallado'], 'duracion_horas'));
    $stats['total_cursos'] = $conn->query("SELECT COUNT(*) FROM cursos")->fetchColumn();

    // --- PREPARAR DATOS PARA GRÁFICOS ---
    $chart_data_horas_por_area = [];
    $chart_data_asistencias_por_curso = [];

    foreach ($stats['reporte_detallado'] as $item) {
        if (empty($item['area'])) continue; // Omitir áreas vacías
        if (!isset($chart_data_horas_por_area[$item['area']])) {
            $chart_data_horas_por_area[$item['area']] = 0;
        }
        $chart_data_horas_por_area[$item['area']] += (float)$item['duracion_horas'];

        if (!isset($chart_data_asistencias_por_curso[$item['nombre_curso']])) {
            $chart_data_asistencias_por_curso[$item['nombre_curso']] = 0;
        }
        $chart_data_asistencias_por_curso[$item['nombre_curso']]++;
    }
    arsort($chart_data_horas_por_area);
    arsort($chart_data_asistencias_por_curso);
    $chart_data_asistencias_por_curso = array_slice($chart_data_asistencias_por_curso, 0, 10, true);


    // --- Lógica de Búsqueda de Empleado ---
    if (!empty($user_search_query)) {
        $stmt_find_user = $conn->prepare("SELECT * FROM usuarios WHERE nombre_completo LIKE :query OR expediente LIKE :query LIMIT 1");
        $stmt_find_user->execute([':query' => "%{$user_search_query}%"]);
        $stats['empleado_info'] = $stmt_find_user->fetch(PDO::FETCH_ASSOC);
        if ($stats['empleado_info']) {
            $stmt_historial = $conn->prepare(
                "SELECT c.nombre_curso, COUNT(a.id) as veces_tomado, SUM(a.duracion_horas) as total_horas " .
                "FROM asistencias_cursos a JOIN cursos c ON a.curso_id = c.id " .
                "WHERE a.user_id = :user_id GROUP BY c.nombre_curso ORDER BY c.nombre_curso ASC"
            );
            $stmt_historial->execute([':user_id' => $stats['empleado_info']['id']]);
            $stats['historial_empleado'] = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    $error_message = "Error al consultar las estadísticas: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Capacitación</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .stats-cards { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; text-align: center; min-width: 200px; }
        .stat-card h3 { margin-top: 0; font-size: 1.1em; color: #555; }
        .stat-card p { font-size: 2.2em; font-weight: 700; color: #333; margin: 10px 0 0; }
        .filter-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end; }
        .charts-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .chart-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); position: relative; height: 400px; }
        @media (max-width: 992px) {
            .charts-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Estadísticas de Capacitación</h1>
        <a href="<?php echo ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php'; ?>" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">

        <?php if (isset($error_message)): ?><div class="error-message"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        
        <section class="filter-section">
            <h3>Filtros de Reporte</h3>
            <form action="estadisticas.php" method="GET">
                <div class="filter-grid">
                    <div class="form-group"><label for="year">Año</label><select name="year" id="year"><option value="all">Todos</option><?php foreach ($available_years as $year): ?><option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="area">Área</label><select name="area" id="area"><option value="all">Todas</option><?php foreach ($available_areas as $area): ?><option value="<?php echo $area; ?>" <?php echo ($selected_area == $area) ? 'selected' : ''; ?>><?php echo htmlspecialchars($area); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="curso_id">Curso</label><select name="curso_id" id="curso_id"><option value="all">Todos</option><?php foreach ($available_cursos as $curso): ?><option value="<?php echo $curso['id']; ?>" <?php echo ($selected_curso_id == $curso['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($curso['nombre_curso']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><button type="submit" class="action-btn">Filtrar</button><a href="estadisticas.php" class="action-btn-secondary" style="text-decoration:none;">Limpiar</a></div>
                </div>
            </form>
        </section>

        <section class="stats-cards">
            <div class="stat-card"><h3>Total de Cursos (Catálogo)</h3><p><?php echo $stats['total_cursos']; ?></p></div>
            <div class="stat-card"><h3>Asistencias (Filtradas)</h3><p><?php echo $stats['total_asistencias']; ?></p></div>
            <div class="stat-card"><h3>Horas de Cap. (Filtradas)</h3><p><?php echo number_format($stats['total_horas_capacitacion'], 1); ?></p></div>
        </section>

        <hr class="section-divider">

        <section class="charts-section">
            <div class="chart-container">
                <h3>Horas de Capacitación por Área</h3>
                <canvas id="horasPorAreaChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Top 10 Cursos por Nº de Asistencias</h3>
                <canvas id="asistenciasPorCursoChart"></canvas>
            </div>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>Reporte Detallado</h3>
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table>
                    <thead><tr><th>Nombre Completo</th><th>Expediente</th><th>Área</th><th>Curso</th><th>Fecha</th><th>Horas</th></tr></thead>
                    <tbody>
                        <?php if (empty($stats['reporte_detallado'])): ?>
                            <tr><td colspan="6">No hay datos que coincidan con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($stats['reporte_detallado'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($item['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($item['area']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre_curso']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($item['fecha_inicio'])); ?></td>
                                    <td><?php echo number_format($item['duracion_horas'], 1); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <hr class="section-divider">

        <section class="form-section">
            <h3>Consultar Historial por Empleado</h3>
            <form action="estadisticas.php" method="GET"><div class="form-group"><label for="user_query">Buscar por Nombre o Expediente</label><input type="text" id="user_query" name="user_query" placeholder="Ej: Juan Perez o 12345" value="<?php echo htmlspecialchars($user_search_query); ?>" required></div><button type="submit" class="action-btn">Buscar Empleado</button></form>
        </section>

        <?php if (!empty($user_search_query)): ?>
            <section class="table-section">
                <?php if ($stats['empleado_info']): ?>
                    <h3>Historial de: <?php echo htmlspecialchars($stats['empleado_info']['nombre_completo']); ?> (Exp: <?php echo htmlspecialchars($stats['empleado_info']['expediente']); ?>)</h3>
                    <div class="table-responsive"><table><thead><tr><th>Curso Tomado</th><th>Veces Tomado</th><th>Total de Horas</th></tr></thead><tbody>
                        <?php if (empty($stats['historial_empleado'])): ?>
                            <tr><td colspan="3">Este empleado no tiene cursos registrados.</td></tr>
                        <?php else: ?>
                            <?php $total_general_horas = array_sum(array_column($stats['historial_empleado'], 'total_horas')); ?>
                            <?php foreach ($stats['historial_empleado'] as $historial): ?>
                                <tr><td><?php echo htmlspecialchars($historial['nombre_curso']); ?></td><td><?php echo $historial['veces_tomado']; ?></td><td><?php echo htmlspecialchars(number_format($historial['total_horas'], 1)); ?></td></tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold; background-color:#f2f2f2;"><td colspan="2" style="text-align:right;">Total de Horas de Capacitación:</td><td><?php echo number_format($total_general_horas, 1); ?></td></tr>
                        <?php endif; ?>
                    </tbody></table></div>
                <?php else: ?>
                    <div class="error-message">No se encontró ningún empleado con el criterio de búsqueda '<?php echo htmlspecialchars($user_search_query); ?>'.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </main>

    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> Sistema Administrativo</p></footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Gráfico de Horas por Área (Barras) ---
    const areaCtx = document.getElementById('horasPorAreaChart');
    if (areaCtx && <?php echo json_encode(!empty($chart_data_horas_por_area)); ?>) {
        new Chart(areaCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($chart_data_horas_por_area)); ?>,
                datasets: [{
                    label: 'Total Horas',
                    data: <?php echo json_encode(array_values($chart_data_horas_por_area)); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                scales: { x: { beginAtZero: true } },
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- Gráfico de Asistencias por Curso (Dona) ---
    const cursoCtx = document.getElementById('asistenciasPorCursoChart');
    if (cursoCtx && <?php echo json_encode(!empty($chart_data_asistencias_por_curso)); ?>) {
        new Chart(cursoCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($chart_data_asistencias_por_curso)); ?>,
                datasets: [{
                    label: 'Nº Asistencias',
                    data: <?php echo json_encode(array_values($chart_data_asistencias_por_curso)); ?>,
                    backgroundColor: ['#2980b9','#27ae60','#f39c12','#8e44ad','#c0392b','#16a085','#d35400','#7f8c8d','#34495e','#9b59b6'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
});
</script>

</body>
</html>
