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

// Validar que se recibió un ID de requerimiento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestionar_documentos.php");
    exit();
}
$requerimiento_id = $_GET['id'];

// Obtener información del requerimiento
$stmt_req = $conn->prepare("SELECT titulo FROM requerimientos WHERE id = :id");
$stmt_req->execute([':id' => $requerimiento_id]);
$requerimiento = $stmt_req->fetch(PDO::FETCH_ASSOC);

if (!$requerimiento) {
    header("Location: gestionar_documentos.php");
    exit();
}

// Obtener todas las entregas para este requerimiento, uniendo con TU tabla de usuarios
$sql_entregas = "
    SELECT 
        e.nombre_archivo, 
        e.ruta_archivo, 
        e.fecha_entrega, 
        u.nombre_completo as nombre_usuario -- CORREGIDO
    FROM entregas e
    JOIN usuarios u ON e.user_id = u.id -- CORREGIDO
    WHERE e.requerimiento_id = :requerimiento_id
    ORDER BY e.fecha_entrega DESC
";
$stmt_entregas = $conn->prepare($sql_entregas);
$stmt_entregas->execute([':requerimiento_id' => $requerimiento_id]);
$entregas = $stmt_entregas->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregas de Requerimiento</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Ver Entregas</h1>
        <a href="gestionar_documentos.php" class="logout-btn">Volver a Requerimientos</a>
    </header>

    <main class="dashboard-container">
        <section class="table-section">
            <h3>Entregas para: "<?php echo htmlspecialchars($requerimiento['titulo']); ?>"</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre del Archivo</th>
                            <th>Fecha de Entrega</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">Aún no se han recibido entregas para este requerimiento.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entrega['nombre_usuario']); ?></td> -- CORREGIDO
                                    <td><?php echo htmlspecialchars($entrega['nombre_archivo']); ?></td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($entrega['fecha_entrega']))); ?></td>
                                    <td class="actions-cell">
                                        <a href="<?php echo $base_path . '/' . htmlspecialchars($entrega['ruta_archivo']); ?>" class="action-btn-small green" download>Descargar</a>
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