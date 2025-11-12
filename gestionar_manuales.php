<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// Asegurarse de que el usuario es administrador
if (!isset($_SESSION['user_id']) || (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] !== 'administrador')) {
    header("Location: {$base_path}/index.html");
    exit();
}

$message = null;
$message_type = '';

// Directorio para guardar los manuales
$upload_dir = 'manuales_uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Lógica para ELIMINAR un manual
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        // Obtener la ruta del archivo para poder borrarlo del servidor
        $stmt = $conn->prepare("SELECT ruta_archivo FROM manuales WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file && file_exists($file['ruta_archivo'])) {
            unlink($file['ruta_archivo']); // Borrar archivo físico
        }

        // Borrar el registro de la base de datos
        $stmt_delete = $conn->prepare("DELETE FROM manuales WHERE id = :id");
        $stmt_delete->execute([':id' => $_GET['id']]);

        $_SESSION['flash_message'] = "Manual eliminado exitosamente.";

    } catch (PDOException $e) {
        $_SESSION['flash_message_error'] = "Error al eliminar el manual: " . $e->getMessage();
    }
    header("Location: gestionar_manuales.php");
    exit();
}

// Lógica para SUBIR un nuevo manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['manual_file'])) {
    if ($_FILES['manual_file']['error'] == UPLOAD_ERR_OK) {
        $original_name = basename($_FILES['manual_file']['name']);
        $safe_name = preg_replace("/[^a-zA-Z0-9_.-]/", "_", $original_name);
        $file_path = $upload_dir . time() . '-' . $safe_name;

        if (move_uploaded_file($_FILES['manual_file']['tmp_name'], $file_path)) {
            try {
                $sql = "INSERT INTO manuales (nombre_archivo, ruta_archivo) VALUES (:nombre, :ruta)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':nombre' => $original_name, ':ruta' => $file_path]);
                $_SESSION['flash_message'] = "¡Manual subido exitosamente!";
            } catch (PDOException $e) {
                $_SESSION['flash_message_error'] = "Error al guardar en la BD: " . $e->getMessage();
                unlink($file_path); // Si falla la BD, no dejar el archivo huérfano
            }
        } else {
            $_SESSION['flash_message_error'] = "Error al mover el archivo subido.";
        }
    } else {
        $_SESSION['flash_message_error'] = "Error en la subida del archivo.";
    }
    header("Location: gestionar_manuales.php");
    exit();
}

// Obtener la lista de manuales para mostrarla
$manuales = $conn->query("SELECT id, nombre_archivo, ruta_archivo, fecha_subida FROM manuales ORDER BY fecha_subida DESC")->fetchAll(PDO::FETCH_ASSOC);

// Mensajes de notificación (flash messages)
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
    <title>Gestionar Manuales</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdocman.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>.action-btn-small.red { background-color: #dc3545; } .action-btn-small.red:hover { background-color: #c82333; }</style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Gestionar Manuales y Diagramas</h1>
        <a href="dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>Subir Nuevo Documento</h3>
            <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="gestionar_manuales.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="manual_file">Seleccionar Archivo (PDF, DOCX, PNG, JPG, etc.):</label>
                    <input type="file" id="manual_file" name="manual_file" required>
                </div>
                <button type="submit" class="action-btn green">Subir Archivo</button>
            </form>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>Documentos Almacenados</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre del Archivo</th>
                            <th>Fecha de Subida</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($manuales)): ?>
                            <tr><td colspan="3" style="text-align: center;">Aún no se han subido manuales.</td></tr>
                        <?php else: ?>
                            <?php foreach ($manuales as $manual): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($manual['nombre_archivo']); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($manual['fecha_subida'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="<?php echo $base_path . '/' . htmlspecialchars($manual['ruta_archivo']); ?>" class="action-btn-small green" download>Descargar</a>
                                        <a href="?action=delete&id=<?php echo $manual['id']; ?>" class="action-btn-small red" onclick="return confirm('¿Estás seguro de que quieres eliminar este archivo?');">Eliminar</a>
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
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>