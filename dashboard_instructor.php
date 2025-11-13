<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// --- NUEVA LÓGICA DE SEGURIDAD SIMPLE ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$nombreUsuario = $_SESSION['user_nombre'];

// 2. Obtener solo los requerimientos ASIGNADOS al usuario actual
try {
    $sql_req = "SELECT DISTINCT r.id, r.titulo, r.descripcion, r.fecha_limite FROM requerimientos AS r
                LEFT JOIN requerimiento_asignaciones AS ra ON r.id = ra.requerimiento_id
                WHERE r.asignado_a_rol = 0 OR r.asignado_a_rol = :user_role OR ra.user_id = :user_id
                ORDER BY r.fecha_limite DESC";
    $stmt_req = $conn->prepare($sql_req);
    $stmt_req->execute([':user_role' => $user_role, ':user_id' => $user_id]);
    $requerimientos = $stmt_req->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requerimientos = [];
    $db_error_message = "Error al consultar los requerimientos: " . $e->getMessage();
}

// 3. Obtener las entregas del usuario actual
$stmt_entregas = $conn->prepare("SELECT requerimiento_id FROM entregas WHERE user_id = :user_id");
$stmt_entregas->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_entregas->execute();
$entregas_usuario = $stmt_entregas->fetchAll(PDO::FETCH_COLUMN, 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Instructor</title>
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* --- ESTILOS PARA NOTIFICACIONES (AÑADIDO) --- */
        .notification-bell {
            position: relative;
            font-size: 24px;
            margin-right: 25px;
            cursor: pointer;
        }
        .notification-counter {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            display: none; /* Oculto por defecto */
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            background-color: #2a3e50;
            color: #fff;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            transform: translateX(100%);
        }
        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }
    </style>
</head>
<body class="dashboard-page">

    <input type="hidden" id="user-role" value="instructor">

    <header class="dashboard-header">
        <h1>Panel de Instructor</h1>
        <!-- ELEMENTOS DE NOTIFICACIÓN AÑADIDOS AQUÍ -->
        <div style="display: flex; align-items: center; margin-left: auto;">
            <div id="notification-container" class="notification-bell">
                <span>🔔</span>
                <span id="notification-count" class="notification-counter">0</span>
            </div>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </header>
    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Bienvenido, Instructor <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">Aquí puede ver sus requerimientos asignados.</p>
        </section>
        
        <section class="actions">
            <h3>Menú Principal</h3>
            <div class="menu-grid">
                 <a href="cargar_oficio.php" class="menu-card blue">
                     <div class="menu-icon">⬆️</div>
                     <div class="menu-text"><h4>Cargar Documentos</h4><p>Subir cartas descriptivas y otros formatos</p></div>
                </a>
                 <a href="ver_manuales_diagramas.php" class="menu-card orange">
                     <div class="menu-icon">📚</div>
                     <div class="menu-text"><h4>Manuales y Diagramas</h4><p>Consulta la documentación</p></div>
                </a>
            </div>
        </section>

        <section class="requerimientos-list">
            <h3>Mis Requerimientos Asignados</h3>
             <?php if (empty($requerimientos) && !isset($db_error_message)): ?>
                <div class="requerimiento-card" style="text-align:center;"><p>No tienes requerimientos pendientes.</p></div>
            <?php else: ?>
                <?php foreach ($requerimientos as $req): ?>
                    <div class="requerimiento-card">
                         <div class="info-col">
                            <h4><?php echo htmlspecialchars($req['titulo']); ?></h4>
                            <?php if(!empty($req['descripcion'])): ?><p><?php echo htmlspecialchars($req['descripcion']); ?></p><?php endif; ?>
                            <p><strong>Fecha Límite:</strong> <?php echo date("d/m/Y H:i", strtotime($req['fecha_limite'])); ?> hs</p>
                        </div>
                        <div class="upload-col">
                             <?php
                            $requerimiento_id = $req['id'];
                            if (in_array($requerimiento_id, $entregas_usuario)):
                            ?>
                                <div class="status-box success">✔ Entregado</div>
                            <?php elseif (new DateTime() > new DateTime($req['fecha_limite'])):
                            ?>
                                <div class="status-box danger">✘ Plazo finalizado</div>
                            <?php else: ?>
                                <form action="procesar_entrega.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="requerimiento_id" value="<?php echo $requerimiento_id; ?>">
                                    <input type="file" name="documento" required>
                                    <textarea name="comentario" placeholder="Comentario (opcional)"></textarea>
                                    <button type="submit" class="action-btn-small green">Subir</button>
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

    <!-- CONTENEDOR DE TOASTS Y SCRIPT -->
    <div id="toast-container" class="toast-container"></div>
    <script src="notifications.js"></script>

</body>
</html>