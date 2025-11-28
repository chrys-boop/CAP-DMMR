<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: index.html");
    exit();
}

$all_cursos = [];
$error_message = '';

try {
    // Obtener los cursos programados para 2025 y 2026
    $sql = "SELECT DISTINCT c.nombre_curso, c.tipo_curso, c.ubicacion, YEAR(ac.fecha_inicio) as anio
            FROM asistencias_cursos ac
            JOIN cursos c ON ac.curso_id = c.id
            WHERE YEAR(ac.fecha_inicio) IN (2025, 2026)
            ORDER BY anio, c.nombre_curso";
    $stmt = $conn->query($sql);
    $all_cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de cursos: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos Programados</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <style>
        .courses-section { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .courses-section h3 { color: #D35400; margin-top: 0; margin-bottom: 20px; font-size: 1.6em; border-bottom: 2px solid #E67E22; padding-bottom: 10px; }
        .courses-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .courses-table th, .courses-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .courses-table th { background-color: #f9f9f9; font-weight: 600; color: #333; }
        .courses-table tr:hover { background-color: #f1f1f1; }
        .no-results-message { text-align: center; padding: 30px; font-size: 1.1em; color: #777; }
    </style>
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>Cursos Programados (2025-2026)</h1>
        <a href="dashboard_enlace.php" class="logout-btn">Volver al Panel</a>
    </header>
    <main class="dashboard-container">
        <?php if ($error_message): ?>
            <div class="flash-message error" style="display:block;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <section class="courses-section">
            <h3>Listado de Próximos Cursos</h3>
            <?php if (empty($all_cursos) && !$error_message): ?>
                <p class="no-results-message">No se encontraron cursos programados para los años 2025 y 2026.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Nombre del Curso</th>
                                <th>Tipo de Curso</th>
                                <th>Ubicación</th>
                                <th>Año Programado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_cursos as $curso): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($curso['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['tipo_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['ubicacion']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($curso['anio']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </main>
    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>
