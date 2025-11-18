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

$success_message = '';
$error_message = '';

// --- LÓGICA DE BORRADO (CORREGIDA Y ROBUSTA) ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // 1. Primero, verificar si el curso tiene asistencias registradas.
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM asistencias_cursos WHERE curso_id = :curso_id");
            $stmt_check->execute([':curso_id' => $delete_id]);
            $asistencias_count = $stmt_check->fetchColumn();

            // 2. Si el conteo es mayor a 0, no permitir el borrado.
            if ($asistencias_count > 0) {
                $_SESSION['flash_error'] = 'Error: No se puede eliminar el curso porque ya tiene asistencias registradas (' . $asistencias_count . ' registros encontrados).';
            } else {
                // 3. Si el conteo es 0, proceder con la eliminación.
                $stmt_delete = $conn->prepare("DELETE FROM cursos WHERE id = :id");
                $stmt_delete->execute([':id' => $delete_id]);
                $_SESSION['flash_message'] = '¡Curso eliminado del catálogo con éxito!';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
    header("Location: gestionar_cursos.php");
    exit();
}


// Mostrar mensajes flash
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// --- LÓGICA DE CREACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_curso'])) {
    $nombre_curso = trim($_POST['nombre_curso']);
    $tipo_curso = trim($_POST['tipo_curso']);
    $ubicacion = trim($_POST['ubicacion']);

    if (!empty($nombre_curso)) {
        try {
            $stmt_check = $conn->prepare("SELECT id FROM cursos WHERE nombre_curso = :nombre_curso");
            $stmt_check->execute([':nombre_curso' => $nombre_curso]);

            if ($stmt_check->fetch()) {
                $error_message = "Error: Ya existe un curso con ese nombre.";
            } else {
                $stmt = $conn->prepare("INSERT INTO cursos (nombre_curso, tipo_curso, ubicacion) VALUES (:nombre_curso, :tipo_curso, :ubicacion)");
                $stmt->execute([':nombre_curso' => $nombre_curso, ':tipo_curso' => $tipo_curso, ':ubicacion' => $ubicacion]);
                $success_message = "¡Curso creado con éxito!";
            }
        } catch (PDOException $e) {
            $error_message = "Error en la base de datos: " . $e->getMessage();
        }
    } else {
        $error_message = "El nombre del curso no puede estar vacío.";
    }
}

// Obtener la lista de cursos
$cursos = $conn->query("SELECT * FROM cursos ORDER BY nombre_curso ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Catálogo de Cursos</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Gestionar Catálogo de Cursos</h1>
        <a href="<?php echo ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php'; ?>" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        
        <?php if ($success_message): ?><div class="success-message"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>

        <section class="form-section">
            <h3>Añadir Nuevo Curso al Catálogo</h3>
            <form action="gestionar_cursos.php" method="POST">
                <div class="form-group"><label for="nombre_curso">Nombre del Curso</label><input type="text" id="nombre_curso" name="nombre_curso" required placeholder="Ej: Seguridad e Higiene Industrial"></div>
                <div class="form-group"><label for="tipo_curso">Tipo de Curso</label><input type="text" id="tipo_curso" name="tipo_curso" placeholder="Ej: INTERNO, EXTERNO, ONLINE"></div>
                <div class="form-group"><label for="ubicacion">Ubicación del Curso</label><input type="text" id="ubicacion" name="ubicacion" placeholder="Ej: Sala de Juntas B, Plataforma Zoom"></div>
                <button type="submit" class="action-btn green">Guardar Nuevo Curso</button>
            </form>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>Catálogo de Cursos Existentes</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Nombre del Curso</th><th>Tipo</th><th>Ubicación</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cursos)): ?>
                            <tr><td colspan="4" style="text-align: center;">No hay cursos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cursos as $curso): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($curso['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['tipo_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="editar_curso.php?id=<?php echo $curso['id']; ?>" class="action-btn-small orange">Editar</a>
                                        <a href="gestionar_cursos.php?delete_id=<?php echo $curso['id']; ?>" class="action-btn-small red" onclick="return confirm('¿Estás seguro de que quieres eliminar este curso? Esta acción no se puede deshacer.');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> Sistema Administrativo</p></footer>

</body>
</html>