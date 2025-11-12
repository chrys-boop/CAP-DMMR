<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}/index.html");
    exit();
}

// 1. Validar y obtener el ID del requerimiento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestionar_documentos.php");
    exit();
}
$requerimiento_id = $_GET['id'];

// 2. Lógica para ACTUALIZAR el requerimiento al recibir un POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $fecha_limite = $_POST['fecha_limite'];
    $descripcion = !empty($_POST['descripcion']) ? $_POST['descripcion'] : null;

    try {
        $sql = "UPDATE requerimientos SET titulo = :titulo, descripcion = :descripcion, fecha_limite = :fecha_limite WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':fecha_limite' => $fecha_limite,
            ':id' => $requerimiento_id
        ]);

        $_SESSION['flash_message'] = "¡Requerimiento actualizado exitosamente!";
        header("Location: gestionar_documentos.php");
        exit();

    } catch (PDOException $e) {
        $error_message = "Error al actualizar el requerimiento: " . $e->getMessage();
    }
}

// 3. Obtener los datos actuales del requerimiento para mostrarlos en el formulario
try {
    $stmt = $conn->prepare("SELECT titulo, descripcion, fecha_limite FROM requerimientos WHERE id = :id");
    $stmt->execute([':id' => $requerimiento_id]);
    $requerimiento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requerimiento) {
        $_SESSION['flash_message_error'] = "No se encontró el requerimiento solicitado.";
        header("Location: gestionar_documentos.php");
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error al obtener el requerimiento: " . $e->getMessage();
    $requerimiento = ['titulo' => '', 'descripcion' => '', 'fecha_limite' => '']; // Evitar errores en el form
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Requerimiento</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Editar Requerimiento</h1>
        <a href="gestionar_documentos.php" class="logout-btn">Cancelar y Volver</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>Modificando el Requerimiento</h3>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="editar_requerimiento.php?id=<?php echo $requerimiento_id; ?>" method="POST">
                <div class="form-group">
                    <label for="titulo">Título del Requerimiento:</label>
                    <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($requerimiento['titulo']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="fecha_limite">Fecha Límite de Entrega:</label>
                    <input type="date" id="fecha_limite" name="fecha_limite" value="<?php echo htmlspecialchars($requerimiento['fecha_limite']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción (Opcional):</label>
                    <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($requerimiento['descripcion']); ?></textarea>
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