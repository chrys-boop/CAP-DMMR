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

$message = null;
$message_type = '';

// Lógica para ELIMINAR un requerimiento
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        // Primero, eliminar las entregas asociadas para mantener la integridad de la base de datos
        $sql_delete_entregas = "DELETE FROM entregas WHERE requerimiento_id = :id";
        $stmt_delete_entregas = $conn->prepare($sql_delete_entregas);
        $stmt_delete_entregas->execute([':id' => $_GET['id']]);

        // Luego, eliminar el requerimiento en sí
        $sql_delete_req = "DELETE FROM requerimientos WHERE id = :id";
        $stmt_delete_req = $conn->prepare($sql_delete_req);
        $stmt_delete_req->execute([':id' => $_GET['id']]);

        $_SESSION['flash_message'] = "Requerimiento eliminado exitosamente.";

    } catch (PDOException $e) {
        $_SESSION['flash_message_error'] = "Error al eliminar el requerimiento: " . $e->getMessage();
    }
    header("Location: gestionar_documentos.php");
    exit();
}

// Lógica para GUARDAR un nuevo requerimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = $_POST['titulo'];
    $fecha_limite = $_POST['fecha_limite'];
    $descripcion = !empty($_POST['descripcion']) ? $_POST['descripcion'] : null;

    try {
        $sql = "INSERT INTO requerimientos (titulo, descripcion, fecha_limite) VALUES (:titulo, :descripcion, :fecha_limite)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':fecha_limite' => $fecha_limite
        ]);
        $_SESSION['flash_message'] = "¡Requerimiento creado exitosamente!";
    } catch (PDOException $e) {
        $_SESSION['flash_message_error'] = "Error al crear el requerimiento: " . $e->getMessage();
    }
    header("Location: gestionar_documentos.php");
    exit();
}

// Lógica para LEER y mostrar los requerimientos
$requerimientos = $conn->query("SELECT id, titulo, fecha_limite FROM requerimientos ORDER BY fecha_limite DESC")->fetchAll(PDO::FETCH_ASSOC);

// Gestionar mensajes flash para mostrar notificaciones
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = 'success';
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_message_error'])) {
    $message = $_SESSION['flash_message_error'];
    $message_type = 'error';
    unset($_SESSION['flash_message_error']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Documentos</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .action-btn-small.red { background-color: #dc3545; }
        .action-btn-small.red:hover { background-color: #c82333; }
    </style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Gestionar Documentos</h1>
        <a href="<?php echo $base_path; ?>/dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>Crear Nuevo Requerimiento</h3>
            <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="gestionar_documentos.php" method="POST">
                <div class="form-group">
                    <label for="titulo">Título del Requerimiento:</label>
                    <input type="text" id="titulo" name="titulo" placeholder="Ej: Reporte Mensual de Actividades" required>
                </div>
                <div class="form-group">
                    <label for="fecha_limite">Fecha Límite de Entrega:</label>
                    <input type="date" id="fecha_limite" name="fecha_limite" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción (Opcional):</label>
                    <textarea id="descripcion" name="descripcion" rows="3" placeholder="Instrucciones adicionales sobre el documento a entregar..."></textarea>
                </div>
                <button type="submit" class="action-btn green">Crear Requerimiento</button>
            </form>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>Listado de Requerimientos</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Título del Requerimiento</th>
                            <th>Fecha Límite</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requerimientos)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Aún no hay requerimientos creados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requerimientos as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($req['fecha_limite']))); ?></td>
                                    <td class="actions-cell">
                                        <a href="ver_entregas.php?id=<?php echo $req['id']; ?>" class="action-btn-small blue">Ver Entregas</a>
                                        <a href="editar_requerimiento.php?id=<?php echo $req['id']; ?>" class="action-btn-small orange">Editar</a>
                                        <a href="?action=delete&id=<?php echo $req['id']; ?>" class="action-btn-small red" onclick="return confirm('¿Estás seguro de que quieres eliminar este requerimiento? Esto borrará también todas sus entregas asociadas.');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo | Todos los derechos reservados</p>
    </footer>
</body>
</html>