<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// 1. Validar sesión y rol del usuario
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: {$base_path}/index.html");
    exit();
}

// Este dashboard es solo para roles que no son administradores (ej. rol 3)
if ($_SESSION['user_role'] == 5) { // Asumiendo que 5 es el rol de administrador
    header("Location: {$base_path}/dashboard.php"); // Redirigir al admin a su propio panel
    exit();
}

$user_id = $_SESSION['user_id'];
$nombreUsuario = $_SESSION['user_nombre'];

// 2. Obtener todos los requerimientos
$stmt_req = $conn->prepare("SELECT id, titulo, descripcion, fecha_limite FROM requerimientos ORDER BY fecha_limite DESC");
$stmt_req->execute();
$requerimientos = $stmt_req->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtener las entregas del usuario actual para saber qué ha completado
$stmt_entregas = $conn->prepare("SELECT requerimiento_id FROM entregas WHERE user_id = :user_id");
$stmt_entregas->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_entregas->execute();
$entregas_usuario = $stmt_entregas->fetchAll(PDO::FETCH_COLUMN, 0); // Un array simple con los IDs de requerimientos entregados

// Configurar zona horaria para la comparación
date_default_timezone_set('America/Mexico_City');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Panel de Entrega de Documentos</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </header>

    <main class="dashboard-container">
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
                            // 4. LÓGICA DE VISUALIZACIÓN
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
                                <form action="procesar_entrega.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="requerimiento_id" value="<?php echo $requerimiento_id; ?>">
                                    <input type="file" name="documento" required>
                                    <textarea name="comentario" placeholder="Añade un comentario (opcional)"></textarea>
                                    <button type="submit" class="action-btn-small green">Subir Archivo</button>
                                </form>
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