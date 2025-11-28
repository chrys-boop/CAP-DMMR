<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';
require_once __DIR__ . '/vendor/autoload.php';

use WebSocket\Client;

// --- LÓGICA DE SEGURIDAD (CORREGIDA) ---
// Solo Admin (5) y Cap-dmmr (4) pueden acceder.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

$upload_dir = 'manuales_uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- LÓGICA PARA ELIMINAR UN MANUAL ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT ruta_archivo FROM manuales WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file && file_exists($file['ruta_archivo'])) {
            unlink($file['ruta_archivo']);
        }

        $stmt_delete = $conn->prepare("DELETE FROM manuales WHERE id = :id");
        $stmt_delete->execute([':id' => $_GET['id']]);

        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'MANUAL ELIMINADO EXITOSAMENTE.'];

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'ERROR AL ELIMINAR EL MANUAL: ' . $e->getMessage()];
    }
    header("Location: gestionar_manuales.php");
    exit();
}

// --- LÓGICA PARA SUBIR UN NUEVO MANUAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['manual_file'])) {
    if ($_FILES['manual_file']['error'] == UPLOAD_ERR_OK) {
        $original_name = basename($_FILES['manual_file']['name']);
        $file_path = $upload_dir . time() . '-' . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $original_name);

        if (move_uploaded_file($_FILES['manual_file']['tmp_name'], $file_path)) {
            try {
                $stmt = $conn->prepare("INSERT INTO manuales (nombre_archivo, ruta_archivo) VALUES (:nombre, :ruta)");
                $stmt->execute([':nombre' => $original_name, ':ruta' => $file_path]);
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => '¡MANUAL SUBIDO EXITOSAMENTE!'];

                // Notificación por WebSocket (método unificado)
                try {
                    $ws_client = new Client("ws://localhost:8081");
                    $payload = json_encode([
                        'type'    => 'notification',
                        'payload' => ['type' => 'new_manual', 'manual_name' => $original_name]
                    ]);
                    $ws_client->send($payload);
                } catch (Exception $e) {
                    error_log("FALLO AL ENVIAR NOTIFICACIÓN POR WEBSOCKET: " . $e->getMessage());
                }

            } catch (PDOException $e) {
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'ERROR AL GUARDAR EN LA BD: ' . $e->getMessage()];
                unlink($file_path);
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'ERROR AL MOVER EL ARCHIVO SUBIDO.'];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'ERROR EN LA SUBIDA DEL ARCHIVO.'];
    }
    header("Location: gestionar_manuales.php");
    exit();
}

// Obtener manuales para mostrar en la tabla
$manuales = $conn->query("SELECT id, nombre_archivo, ruta_archivo, fecha_subida FROM manuales ORDER BY fecha_subida DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESTIONAR MANUALES</title>
    <link rel="stylesheet" href="estilos/estilosgesdocman.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>GESTIONAR MANUALES Y DIAGRAMAS</h1>
        <?php
        // --- BOTÓN DE VOLVER DINÁMICO (CORREGIDO) ---
        $dashboard_link = ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php';
        ?>
        <a href="<?php echo $dashboard_link; ?>" class="logout-btn">VOLVER AL PANEL</a>
    </header>
    <main class="dashboard-container">
        <section class="form-section">
            <h3>SUBIR NUEVO DOCUMENTO</h3>
            <?php
            if (isset($_SESSION['flash_message'])) {
                $msg = $_SESSION['flash_message'];
                echo '<div class="flash-message ' . htmlspecialchars($msg['type']) . '">' . htmlspecialchars($msg['text']) . '</div>';
                unset($_SESSION['flash_message']);
            }
            ?>
            <form action="gestionar_manuales.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="manual_file">SELECCIONAR ARCHIVO:</label>
                    <input type="file" id="manual_file" name="manual_file" required>
                </div>
                <button type="submit" class="action-btn green">SUBIR ARCHIVO</button>
            </form>
        </section>
        <hr class="section-divider">
        <section class="table-section">
            <h3>DOCUMENTOS ALMACENADOS</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE DEL ARCHIVO</th>
                            <th>FECHA DE SUBIDA</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($manuales)): ?>
                            <tr><td colspan="3" style="text-align: center;">NO SE HAN SUBIDO MANUALES.</td></tr>
                        <?php else: ?>
                            <?php foreach ($manuales as $manual): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($manual['nombre_archivo']); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($manual['fecha_subida'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="<?php echo htmlspecialchars($manual['ruta_archivo']); ?>" class="action-btn-small green" download>DESCARGAR</a>
                                        <a href="?action=delete&id=<?php echo $manual['id']; ?>" class="action-btn-small red" onclick="return confirm('¿SEGURO QUE QUIERES ELIMINAR ESTE ARCHIVO?');">ELIMINAR</a>
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
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p>
    </footer>
</body>
</html>