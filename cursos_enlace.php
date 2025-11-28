<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: index.html");
    exit();
}

$user_taller = $_SESSION['user_taller'];

// --- PROCESAMIENTO DE FILTROS ---
$curso_filter_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

$all_cursos = [];
$attendees = [];
$selected_course_name = 'Resultados de la Búsqueda'; // Título por defecto
$error_message = '';

try {
    // 1. Obtener siempre la lista de todos los cursos para el dropdown
    $stmt_all_cursos = $conn->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso");
    $all_cursos = $stmt_all_cursos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Solo ejecutar la búsqueda si al menos un filtro está activo
    if ($curso_filter_id > 0 || !empty($search_term)) {
        // Construcción de la consulta y parámetros dinámicos
        $sql_attendees = "SELECT u.nombre_completo, u.expediente, c.nombre_curso, DATE_FORMAT(ac.fecha_inicio, '%b %Y') as fecha
                          FROM asistencias_cursos ac
                          JOIN usuarios u ON ac.user_id = u.id
                          JOIN cursos c ON ac.curso_id = c.id
                          WHERE u.taller = :taller AND u.role = 1";
        
        $params = [':taller' => $user_taller];

        if ($curso_filter_id > 0) {
            $sql_attendees .= " AND ac.curso_id = :curso_id";
            $params[':curso_id'] = $curso_filter_id;
            
            // Para mostrar el nombre del curso en el título
            $stmt_course_name = $conn->prepare("SELECT nombre_curso FROM cursos WHERE id = :id");
            $stmt_course_name->execute([':id' => $curso_filter_id]);
            $course_name = $stmt_course_name->fetchColumn();
            if ($course_name) $selected_course_name = "Asistentes para: " . htmlspecialchars($course_name);
        }

        if (!empty($search_term)) {
            $sql_attendees .= " AND (u.nombre_completo LIKE :search OR u.expediente LIKE :search)";
            $params[':search'] = '%' . $search_term . '%';
        }

        $sql_attendees .= " ORDER BY u.nombre_completo, ac.fecha_inicio";
        
        $stmt_attendees = $conn->prepare($sql_attendees);
        $stmt_attendees->execute($params);
        $attendees = $stmt_attendees->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Error en la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla e Histórico de Cursos</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <style>
        .filter-section { background-color: #fff; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; align-items: flex-end; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .filter-form button { padding: 10px 25px; background-color: #E67E22; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; height: 42px; }
        .results-section h3 { color: #D35400; margin-top: 0; font-size: 1.5em; }
        .results-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .results-table th, .results-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .results-table th { background-color: #f7f7f7; font-weight: 600; color: #333; }
        .initial-message { text-align: center; padding: 30px; font-size: 1.1em; color: #777; }
    </style>
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>Plantilla e Histórico de Cursos</h1>
        <a href="dashboard_enlace.php" class="logout-btn">Volver al Panel</a>
    </header>
    <main class="dashboard-container">
        <section class="filter-section">
            <h3>Filtros de Búsqueda</h3>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label for="search">Por Nombre o Expediente:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Escribe para buscar...">
                </div>
                <div class="form-group">
                    <label for="curso_id">Por Curso:</label>
                    <select id="curso_id" name="curso_id">
                        <option value="0">-- Todos los Cursos --</option>
                        <?php foreach ($all_cursos as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>" <?php echo $curso_filter_id == $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                     <label>&nbsp;</label> <!-- Espacio para alinear el botón -->
                    <button type="submit">Filtrar</button>
                </div>
            </form>
        </section>

        <?php if ($error_message): ?>
            <div class="flash-message error" style="display:block;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($curso_filter_id) || !empty($search_term)): ?>
            <section class="results-section filter-section">
                <h3><?php echo $selected_course_name; ?></h3>
                <?php if (empty($attendees) && !$error_message): ?>
                    <p class="initial-message">No se encontraron registros que coincidan con los filtros aplicados.</p>
                <?php else: ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Trabajador</th>
                                <th>Expediente</th>
                                <th>Curso</th>
                                <th>Fecha de Realización</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $attendee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendee['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($attendee['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($attendee['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($attendee['fecha']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php else: ?>
             <div class="initial-message filter-section">
                <p>Por favor, utiliza los filtros para buscar en el historial de cursos.</p>
            </div>
        <?php endif; ?>

    </main>
    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>
