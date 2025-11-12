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

// --- Lógica para las Estadísticas ---

// ¡CONSULTA FINALMENTE CORRECTA!
// 1. Contar el total de usuarios que deben entregar (los que son enlaces, role = 3)
$sql_total_users = "SELECT COUNT(*) FROM usuarios WHERE role = 3";
$stmt_total_users = $conn->prepare($sql_total_users);
$stmt_total_users->execute();
$total_usuarios_activos = $stmt_total_users->fetchColumn();

// 2. Obtener todos los requerimientos con su conteo de entregas
$sql_requerimientos = "
    SELECT 
        r.id, 
        r.titulo, 
        r.fecha_limite, 
        COUNT(e.id) as numero_entregas
    FROM 
        requerimientos r
    LEFT JOIN 
        entregas e ON r.id = e.requerimiento_id
    GROUP BY 
        r.id, r.titulo, r.fecha_limite
    ORDER BY 
        r.fecha_limite DESC
";
$requerimientos = $conn->query($sql_requerimientos)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Recibimiento</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Historial de Recibimiento y Estadísticas</h1>
        <a href="dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="table-section">
            <h3>Supervisión de Entregas</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Título del Requerimiento</th>
                            <th>Fecha Límite</th>
                            <th>Progreso de Entrega</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requerimientos)):
                            ?>
                            <tr><td colspan="4" style="text-align: center;">No hay requerimientos creados.</td></tr>
                        <?php else:
                            ?>
                            <?php foreach ($requerimientos as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['titulo']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($req['fecha_limite'])); ?></td>
                                    <td>
                                        <strong><?php echo $req['numero_entregas']; ?> / <?php echo $total_usuarios_activos; ?></strong> entregados
                                    </td>
                                    <td class="actions-cell">
                                        <a href="detalle_recibimiento.php?req_id=<?php echo $req['id']; ?>" class="action-btn-small blue">Ver Detalles</a>
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