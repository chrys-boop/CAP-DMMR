<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// 1. Proteger la página
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

$curso_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$curso = null;
$error_message = '';
$success_message = '';

if (!$curso_id) {
    $_SESSION['flash_error'] = "ID de curso no válido.";
    header("Location: gestionar_cursos.php");
    exit();
}

// 2. Lógica de actualización (cuando se envía el formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_curso = trim($_POST['nombre_curso']);
    $tipo_curso = trim($_POST['tipo_curso']);
    $ubicacion = trim($_POST['ubicacion']);

    if (!empty($nombre_curso)) {
        try {
            $stmt = $conn->prepare("UPDATE cursos SET nombre_curso = :nombre_curso, tipo_curso = :tipo_curso, ubicacion = :ubicacion WHERE id = :id");
            $stmt->execute([
                ':nombre_curso' => $nombre_curso,
                ':tipo_curso' => $tipo_curso,
                ':ubicacion' => $ubicacion,
                ':id' => $curso_id
            ]);
            $_SESSION['flash_message'] = "¡Curso actualizado con éxito!";
            header("Location: gestionar_cursos.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error al actualizar el curso: " . $e->getMessage();
        }
    } else {
        $error_message = "El nombre del curso no puede estar vacío.";
    }
}

// 3. Obtener los datos actuales del curso para mostrar en el formulario
try {
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id");
    $stmt->execute([':id' => $curso_id]);
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$curso) {
        $_SESSION['flash_error'] = "No se encontró el curso solicitado.";
        header("Location: gestionar_cursos.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al buscar el curso: " . $e->getMessage();
    header("Location: gestionar_cursos.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Curso</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Editar Curso del Catálogo</h1>
        <a href="gestionar_cursos.php" class="logout-btn">Volver al Catálogo</a>
    </header>

    <main class="dashboard-container">
        
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>

        <section class="form-section">
            <h3>Modificar Información del Curso</h3>
            <form action="editar_curso.php?id=<?php echo $curso['id']; ?>" method="POST">
                <div class="form-group">
                    <label for="nombre_curso">Nombre del Curso</label>
                    <input type="text" id="nombre_curso" name="nombre_curso" required value="<?php echo htmlspecialchars($curso['nombre_curso']); ?>">
                </div>
                <div class="form-group">
                    <label for="tipo_curso">Tipo de Curso</label>
                    <input type="text" id="tipo_curso" name="tipo_curso" value="<?php echo htmlspecialchars($curso['tipo_curso']); ?>">
                </div>
                <div class="form-group">
                    <label for="ubicacion">Ubicación del Curso</label>
                    <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($curso['ubicacion']); ?>">
                </div>
                <button type="submit" class="action-btn green">Guardar Cambios</button>
            </form>
        </section>

    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>

</body>
</html>