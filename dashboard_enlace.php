<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. Validar sesión y rol del usuario
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: index.html");
    exit();
}

if ($_SESSION['user_role'] == 5) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nombreUsuario = $_SESSION['user_nombre'];
// Recuperamos las variables de sesión que necesitamos para el formato del archivo
$user_expediente = $_SESSION['user_expediente'];
$user_area = $_SESSION['user_area'];

// 2. Obtener todos los requerimientos
$stmt_req = $conn->prepare("SELECT id, titulo, descripcion, fecha_limite FROM requerimientos ORDER BY fecha_limite DESC");
$stmt_req->execute();
$requerimientos = $stmt_req->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtener las entregas del usuario actual
$stmt_entregas = $conn->prepare("SELECT requerimiento_id FROM entregas WHERE user_id = :user_id");
$stmt_entregas->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_entregas->execute();
$entregas_usuario = $stmt_entregas->fetchAll(PDO::FETCH_COLUMN, 0);

date_default_timezone_set('America/Mexico_City');
$current_year = date('Y'); // Obtenemos el año actual

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario</title>
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Panel de Entrega de Documentos</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </header>

    <main class="dashboard-container">
        <?php
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            echo "<div class='flash-message {" . htmlspecialchars($message['type']) . "}'>" . htmlspecialchars($message['text']) . "</div>";
            unset($_SESSION['flash_message']);
        }
        ?>

        <section class="welcome-section">
            <h2>Bienvenido, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">Aquí puedes ver los requerimientos pendientes y subir tus documentos.</p>
        </section>

        <section class="requerimientos-list">
            <h3>Listado de Requerimientos</h3>
            <?php if (empty($requerimientos)): ?>
                <p>No hay requerimientos activos en este momento.</p>
            <?php else: ?>
                <?php foreach ($requerimientos as $req): ?>
                    <div class="requerimiento-card">
                        <h4><?php echo htmlspecialchars($req['titulo']); ?></h4>
                        <p><?php echo htmlspecialchars($req['descripcion']); ?></p>
                        <p><strong>Fecha Límite:</strong> <?php echo date("d/m/Y H:i", strtotime($req['fecha_limite'])); ?> hs</p>
                        
                        <div class="upload-section">
                            <?php
                            $fecha_limite = new DateTime($req['fecha_limite']);
                            $ahora = new DateTime();
                            $requerimiento_id = $req['id'];

                            if (in_array($requerimiento_id, $entregas_usuario)):
                            ?>
                                <div class="status-box success">✔ Ya has entregado este documento.</div>
                            <?php elseif ($ahora > $fecha_limite):
                            ?>
                                <div class="status-box danger">✘ Plazo de entrega finalizado.</div>
                            <?php else: 
                            ?>
                                <!-- --- INICIO DE LA MODIFICACIÓN --- -->
                                <form action="procesar_entrega.php" method="post" enctype="multipart/form-data">
                                    <div class="file-format-info">
                                        <p><strong>Formato Requerido:</strong> <code>numerocurso_expediente_area_año.extension</code></p>
                                        <p><strong>Ejemplo para ti:</strong> <code>XX_<?php echo htmlspecialchars($user_expediente); ?>_<?php echo htmlspecialchars($user_area); ?>_<?php echo $current_year; ?>.pdf</code></p>
                                        <p class="small-text">* Reemplaza 'XX' con el número de curso en romano indicado en la descripción.</p>
                                    </div>
                                    <input type="hidden" name="requerimiento_id" value="<?php echo $requerimiento_id; ?>">
                                    <input type="file" name="documento" required>
                                    <textarea name="comentario" placeholder="Añade un comentario (opcional)"></textarea>
                                    <button type="submit" class="action-btn-small green">Subir Archivo</button>
                                </form>
                                <!-- --- FIN DE LA MODIFICACIÓN --- -->
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>