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

$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$user_search_query = isset($_GET['user_query']) ? trim($_GET['user_query']) : '';
$selected_curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : null;

$stats = [
    'total_cursos' => 0,
    'total_asistencias' => 0,
    'total_horas_capacitacion' => 0,
    'participantes_por_curso' => [],
    'participantes_por_area' => [],
    'historial_empleado' => [],
    'empleado_info' => null,
    'lista_participantes_curso' => [],
    'nombre_curso_seleccionado' => ''
];
$available_years = [];

try {
    $years_query = $conn->query("SELECT DISTINCT YEAR(fecha_inicio) as year FROM asistencias_cursos ORDER BY year DESC");
    $available_years = $years_query->fetchAll(PDO::FETCH_COLUMN);

    $stats['total_cursos'] = $conn->query("SELECT COUNT(*) FROM cursos")->fetchColumn();
    $where_clause_year = ($selected_year != 'all') ? " WHERE YEAR(fecha_inicio) = :year " : '';
    $params_year = ($selected_year != 'all') ? [':year' => $selected_year] : [];
    $stmt_asistencias = $conn->prepare("SELECT COUNT(*) FROM asistencias_cursos" . $where_clause_year);
    $stmt_asistencias->execute($params_year);
    $stats['total_asistencias'] = $stmt_asistencias->fetchColumn();
    $stmt_horas = $conn->prepare("SELECT SUM(duracion_horas) FROM asistencias_cursos" . $where_clause_year);
    $stmt_horas->execute($params_year);
    $stats['total_horas_capacitacion'] = $stmt_horas->fetchColumn() ?: 0;

    // --- Consulta de desglose por curso (Añadido dias_hombre) ---
    $join_condition_year_curso = ($selected_year != 'all') ? " AND YEAR(a.fecha_inicio) = :year " : '';
    $params_participantes_curso = ($selected_year != 'all') ? [':year' => $selected_year] : [];
    $participantes_sql = 
        "SELECT c.id, c.nombre_curso, COUNT(a.id) as total_participantes, COALESCE(SUM(a.duracion_horas), 0) as horas_hombre, (COALESCE(SUM(a.duracion_horas), 0) / 8) as dias_hombre " .
        "FROM cursos c LEFT JOIN asistencias_cursos a ON c.id = a.curso_id{$join_condition_year_curso}" .
        " GROUP BY c.id, c.nombre_curso ORDER BY horas_hombre DESC";
    $stmt_participantes = $conn->prepare($participantes_sql);
    $stmt_participantes->execute($params_participantes_curso);
    $stats['participantes_por_curso'] = $stmt_participantes->fetchAll(PDO::FETCH_ASSOC);

    // --- Consulta de Participantes por Área ---
    $area_conditions = ["u.area IS NOT NULL AND u.area != ''"];
    $area_params = [];
    if ($selected_year != 'all') {
        $area_conditions[] = "YEAR(a.fecha_inicio) = :year";
        $area_params[':year'] = $selected_year;
    }
    $area_where_clause = " WHERE " . implode(' AND ', $area_conditions);
    $area_sql = "SELECT u.area, COUNT(a.id) as total_participantes FROM asistencias_cursos a JOIN usuarios u ON a.user_id = u.id" . $area_where_clause . " GROUP BY u.area ORDER BY total_participantes DESC";
    $stmt_area = $conn->prepare($area_sql);
    $stmt_area->execute($area_params);
    $stats['participantes_por_area'] = $stmt_area->fetchAll(PDO::FETCH_ASSOC);

    // --- Lógica para obtener lista de participantes de un curso seleccionado ---
    if ($selected_curso_id) {
        $lista_conditions = ["a.curso_id = :curso_id"];
        $lista_params = [':curso_id' => $selected_curso_id];
        if ($selected_year != 'all') {
            $lista_conditions[] = "YEAR(a.fecha_inicio) = :year";
            $lista_params[':year'] = $selected_year;
        }
        $lista_where_clause = " WHERE " . implode(' AND ', $lista_conditions);
        $lista_sql = "SELECT u.expediente, u.nombre_completo FROM asistencias_cursos a JOIN usuarios u ON a.user_id = u.id" . $lista_where_clause . " ORDER BY u.nombre_completo ASC";
        $stmt_lista = $conn->prepare($lista_sql);
        $stmt_lista->execute($lista_params);
        $stats['lista_participantes_curso'] = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_curso_nombre = $conn->prepare("SELECT nombre_curso FROM cursos WHERE id = :curso_id");
        $stmt_curso_nombre->execute([':curso_id' => $selected_curso_id]);
        $stats['nombre_curso_seleccionado'] = $stmt_curso_nombre->fetchColumn();
    }

    // --- Lógica de Búsqueda de Empleado (MODIFICADA para contar cursos repetidos) ---
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
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .stats-cards { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; text-align: center; min-width: 200px; }
        .stat-card h3 { margin-top: 0; font-size: 1.1em; color: #555; }
        .stat-card p { font-size: 2.2em; font-weight: 700; color: #333; margin: 10px 0 0; }
        .filter-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .stats-layout { display: flex; flex-wrap: wrap; gap: 30px; }
        .main-col { flex: 3; min-width: 450px; }
        .side-col { flex: 1; min-width: 300px; }
        .table-section a { color: #007bff; text-decoration: none; }
        .table-section a:hover { text-decoration: underline; }
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
            <form action="estadisticas.php" method="GET" style="display:flex; align-items:center; gap:20px;">
                <div class="form-group">
                    <label for="year">Filtrar por Año</label>
                    <select name="year" id="year" onchange="this.form.submit()">
                        <option value="all" <?php echo ($selected_year == 'all') ? 'selected' : ''; ?>>Todos los Años</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <a href="estadisticas.php" class="action-btn" style="padding: 8px 15px; text-decoration: none;">Limpiar Filtros</a>
            </form>
        </section>

        <section class="stats-cards">
            <div class="stat-card"><h3>Total de Cursos</h3><p><?php echo $stats['total_cursos']; ?></p></div>
            <div class="stat-card"><h3>Total Asistencias (<?php echo htmlspecialchars($selected_year); ?>)</h3><p><?php echo $stats['total_asistencias']; ?></p></div>
            <div class="stat-card"><h3>Total Horas Cap. (<?php echo htmlspecialchars($selected_year); ?>)</h3><p><?php echo number_format($stats['total_horas_capacitacion'], 1); ?></p></div>
        </section>

        <hr class="section-divider">

        <div class="stats-layout">
            <div class="main-col">
                <section class="table-section">
                    <h3>Desglose por Curso (<?php echo htmlspecialchars($selected_year); ?>)</h3>
                    <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                        <table>
                            <thead><tr><th>Nombre del Curso</th><th>Nº Participantes</th><th>Horas-Hombre</th><th>Días-Hombre</th></tr></thead>
                            <tbody>
                                <?php if (empty($stats['participantes_por_curso'])) : ?>
                                    <tr><td colspan="4">No hay datos de cursos para el año seleccionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($stats['participantes_por_curso'] as $curso): ?>
                                        <tr <?php if ($selected_curso_id == $curso['id']) echo 'style="background-color: #e8f0fe;"'; ?> >
                                            <td><a href="?year=<?php echo $selected_year; ?>&curso_id=<?php echo $curso['id']; ?>#lista-participantes"><?php echo htmlspecialchars($curso['nombre_curso']); ?></a></td>
                                            <td><?php echo $curso['total_participantes']; ?></td>
                                            <td><?php echo number_format($curso['horas_hombre'], 1); ?></td>
                                            <td><?php echo number_format($curso['dias_hombre'], 1); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="side-col">
                 <section class="table-section">
                    <h3>Participantes por Área (<?php echo htmlspecialchars($selected_year); ?>)</h3>
                    <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                        <table>
                            <thead><tr><th>Área</th><th>Nº Participantes</th></tr></thead>
                            <tbody>
                                <?php if (empty($stats['participantes_por_area'])): ?>
                                    <tr><td colspan="2">No hay datos de áreas para mostrar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($stats['participantes_por_area'] as $area): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($area['area']); ?></td>
                                            <td><?php echo $area['total_participantes']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <?php if ($selected_curso_id): ?>
            <hr class="section-divider">
            <section class="table-section" id="lista-participantes">
                <?php if (!empty($stats['lista_participantes_curso'])): ?>
                    <h3>Participantes del Curso: "<?php echo htmlspecialchars($stats['nombre_curso_seleccionado']); ?>" (Año: <?php echo htmlspecialchars($selected_year); ?>)</h3>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table>
                            <thead><tr><th>Expediente</th><th>Nombre Completo</th></tr></thead>
                            <tbody>
                                <?php foreach ($stats['lista_participantes_curso'] as $participante): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($participante['expediente']); ?></td>
                                        <td><?php echo htmlspecialchars($participante['nombre_completo']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="info-message">No se encontraron participantes para "<?php echo htmlspecialchars($stats['nombre_curso_seleccionado']); ?>" en el período seleccionado.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        
        <hr class="section-divider">

        <section class="form-section">
            <h3>Consultar Historial por Empleado</h3>
            <form action="estadisticas.php" method="GET">
                <div class="form-group">
                    <label for="user_query">Buscar por Nombre o Expediente</label>
                    <input type="text" id="user_query" name="user_query" placeholder="Ej: Juan Perez o 12345" value="<?php echo htmlspecialchars($user_search_query); ?>" required>
                </div>
                <button type="submit" class="action-btn">Buscar Empleado</button>
            </form>
        </section>

        <?php if (!empty($user_search_query)): ?>
            <section class="table-section">
                <?php if ($stats['empleado_info']): ?>
                    <h3>Historial de: <?php echo htmlspecialchars($stats['empleado_info']['nombre_completo']); ?> (Exp: <?php echo htmlspecialchars($stats['empleado_info']['expediente']); ?>)</h3>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Curso Tomado</th><th>Veces Tomado</th><th>Total de Horas</th></tr></thead>
                            <tbody>
                                <?php if (empty($stats['historial_empleado'])): ?>
                                    <tr><td colspan="3">Este empleado no tiene cursos registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($stats['historial_empleado'] as $historial): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($historial['nombre_curso']); ?></td>
                                            <td><?php echo $historial['veces_tomado']; ?></td>
                                            <td><?php echo htmlspecialchars(number_format($historial['total_horas'], 1)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="error-message">No se encontró ningún empleado con el criterio de búsqueda '<?php echo htmlspecialchars($user_search_query); ?>'.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </main>

    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> Sistema Administrativo</p></footer>

</body>
</html>
