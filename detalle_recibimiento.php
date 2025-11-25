
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// --- LÓGICA DE SEGURIDAD (CORREGIDA) ---
// Solo Admin (5) y Cap-dmmr (4) pueden acceder.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

if (!isset($_GET['req_id']) || !is_numeric($_GET['req_id'])) {
    header("Location: historial_recibimiento.php");
    exit();
}
$requerimiento_id = intval($_GET['req_id']);

$stmt_req = $conn->prepare("SELECT titulo FROM requerimientos WHERE id = :id");
$stmt_req->bindParam(':id', $requerimiento_id, PDO::PARAM_INT);
$stmt_req->execute();
$requerimiento = $stmt_req->fetch(PDO::FETCH_ASSOC);

if (!$requerimiento) {
    header("Location: historial_recibimiento.php");
    exit();
}

// --- 2. OBTENER LISTA DE ENTREGADOS (CON TALLER Y ÁREA INTERNA) ---
$sql_entregados = "
    SELECT u.nombre_completo, u.expediente, u.taller, u.area_interna, e.fecha_entrega, e.ruta_archivo
    FROM entregas e
    JOIN usuarios u ON e.user_id = u.id
    WHERE e.requerimiento_id = :req_id
    ORDER BY u.nombre_completo ASC
";
$stmt_entregados = $conn->prepare($sql_entregados);
$stmt_entregados->bindParam(':req_id', $requerimiento_id, PDO::PARAM_INT);
$stmt_entregados->execute();
$usuarios_entregados = $stmt_entregados->fetchAll(PDO::FETCH_ASSOC);

// --- 3. OBTENER LISTA DE FALTANTES (CON TALLER Y ÁREA INTERNA) ---
$sql_faltantes = "
    SELECT u.nombre_completo, u.expediente, u.taller, u.area_interna
    FROM usuarios u
    WHERE u.role = 3 AND u.id NOT IN (
        SELECT e.user_id 
        FROM entregas e 
        WHERE e.requerimiento_id = :req_id
    )
    ORDER BY u.nombre_completo ASC
";
$stmt_faltantes = $conn->prepare($sql_faltantes);
$stmt_faltantes->bindParam(':req_id', $requerimiento_id, PDO::PARAM_INT);
$stmt_faltantes->execute();
$usuarios_faltantes = $stmt_faltantes->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Recibimiento</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Detalle para: <?php echo htmlspecialchars($requerimiento['titulo']); ?></h1>
        <a href="historial_recibimiento.php" class="logout-btn">Volver al Historial</a>
    </header>

    <main class="dashboard-container">
        
        <section class="table-section">
            <h3 class="success-heading">Usuarios que Han Entregado</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Expediente</th>
                            <th>Taller</th>
                            <th>Área Interna</th>
                            <th>Fecha de Entrega</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios_entregados)): ?>
                            <tr><td colspan="6">Nadie ha entregado este documento todavía.</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios_entregados as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($user['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($user['taller']); ?></td>
                                    <td><?php echo htmlspecialchars($user['area_interna']); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($user['fecha_entrega'])); ?></td>
                                    <td>
                                        <a href="<?php echo $base_path . '/' . htmlspecialchars($user['ruta_archivo']); ?>" target="_blank" class="action-btn-small blue">
                                            Ver Archivo
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="table-section">
            <h3 class="danger-heading">Usuarios Faltantes por Entregar (Enlaces)</h3>
             <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Expediente</th>
                            <th>Taller</th>
                            <th>Área Interna</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios_faltantes)): ?>
                            <tr><td colspan="4">¡Todos los enlaces han entregado el documento!</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios_faltantes as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($user['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($user['taller']); ?></td>
                                    <td><?php echo htmlspecialchars($user['area_interna']); ?></td>
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