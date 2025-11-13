<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. Validar sesión y rol de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 5) {
    header("Location: index.html");
    exit();
}

// 2. Definir valores para los filtros y obtenerlos de GET
$search_nombre = $_GET['nombre'] ?? '';
$search_expediente = $_GET['expediente'] ?? '';
$search_area = $_GET['area'] ?? '';
$search_role = $_GET['role'] ?? '';
$search_fecha_desde = $_GET['fecha_desde'] ?? '';
$search_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// 3. Obtener valores únicos para los desplegables de filtros
$areas = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL AND area != '' ORDER BY area ASC")->fetchAll(PDO::FETCH_COLUMN);
$roles_map = [1 => 'Trabajador', 2 => 'Instructor', 3 => 'Enlace', 4 => 'CAP-DMMR', 5 => 'Admin'];

// 4. Construir la consulta SQL dinámicamente
try {
    $sql_base = "SELECT 
                    op.id, op.nombre_archivo_original, op.ruta_archivo, op.comentario, op.fecha_carga, 
                    u.nombre_completo, u.expediente, u.area, u.role
                 FROM oficios_personalizados AS op
                 JOIN usuarios AS u ON op.user_id = u.id";
    
    $where_clauses = [];
    $params = [];

    if (!empty($search_nombre)) {
        $where_clauses[] = "u.nombre_completo LIKE :nombre";
        $params[':nombre'] = '%' . $search_nombre . '%';
    }
    if (!empty($search_expediente)) {
        $where_clauses[] = "u.expediente LIKE :expediente";
        $params[':expediente'] = '%' . $search_expediente . '%';
    }
    if (!empty($search_area)) {
        $where_clauses[] = "u.area = :area";
        $params[':area'] = $search_area;
    }
    if (!empty($search_role)) {
        $where_clauses[] = "u.role = :role";
        $params[':role'] = $search_role;
    }
    if (!empty($search_fecha_desde)) {
        $where_clauses[] = "DATE(op.fecha_carga) >= :fecha_desde";
        $params[':fecha_desde'] = $search_fecha_desde;
    }
    if (!empty($search_fecha_hasta)) {
        $where_clauses[] = "DATE(op.fecha_carga) <= :fecha_hasta";
        $params[':fecha_hasta'] = $search_fecha_hasta;
    }

    if (count($where_clauses) > 0) {
        $sql_base .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql_base .= " ORDER BY op.fecha_carga DESC";

    $stmt = $conn->prepare($sql_base);
    $stmt->execute($params);
    $oficios_recibidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $oficios_recibidos = [];
    $error_message = "Error de base de datos: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de Oficios Recibidos</title>
    <link rel="stylesheet" href="estilos/estilorev_oficio.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Bandeja de Oficios Recibidos</h1>
        <a href="dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        
        <section class="filter-section">
            <h3>Filtrar Oficios</h3>
            <form method="GET" action="revisar_oficios.php" class="filter-form">
                <div class="filter-grid">
                    <input type="text" name="nombre" placeholder="Nombre..." value="<?php echo htmlspecialchars($search_nombre); ?>">
                    <input type="text" name="expediente" placeholder="Expediente..." value="<?php echo htmlspecialchars($search_expediente); ?>">
                    <select name="area">
                        <option value="">Toda Área</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>" <?php echo ($search_area == $area) ? 'selected' : ''; ?>><?php echo htmlspecialchars($area); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="role">
                        <option value="">Todo Rol</option>
                        <?php foreach ($roles_map as $role_id => $role_name): ?>
                            <option value="<?php echo $role_id; ?>" <?php echo ($search_role == $role_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="fecha_desde" title="Fecha desde" value="<?php echo htmlspecialchars($search_fecha_desde); ?>">
                    <input type="date" name="fecha_hasta" title="Fecha hasta" value="<?php echo htmlspecialchars($search_fecha_hasta); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="action-btn-small green">Filtrar</button>
                    <a href="revisar_oficios.php" class="action-btn-small blue">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="requerimientos-list">
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (empty($oficios_recibidos)): ?>
                <div class="requerimiento-card" style="text-align: center;">
                    <p>No se encontraron oficios con los filtros seleccionados. Pruebe a limpiar los filtros.</p>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 1rem; text-align: right;"><strong>Total de registros: <?php echo count($oficios_recibidos); ?></strong></div>
                <?php foreach ($oficios_recibidos as $oficio): ?>
                    <div class="requerimiento-card">
                        <div class="info-col">
                            <h4 style="margin-bottom: 1rem;">Oficio: <?php echo htmlspecialchars($oficio['nombre_archivo_original']); ?></h4>
                            <p><strong>Remitente:</strong> <?php echo htmlspecialchars($oficio['nombre_completo']); ?></p>
                            <p><strong>Expediente:</strong> <?php echo htmlspecialchars($oficio['expediente']); ?></p>
                            <p><strong>Área:</strong> <?php echo htmlspecialchars($oficio['area']); ?></p>
                            <p><strong>Rol:</strong> <?php echo htmlspecialchars($roles_map[$oficio['role']] ?? 'Desconocido'); ?></p>
                            <?php if (!empty($oficio['comentario'])) : ?>
                                <p><strong>Comentario:</strong> <?php echo htmlspecialchars($oficio['comentario']); ?></p>
                            <?php endif; ?>
                            <p class="small-text">Recibido el: <?php echo date("d/m/Y H:i", strtotime($oficio['fecha_carga'])); ?> hs</p>
                        </div>
                        <div class="actions-col" style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="<?php echo htmlspecialchars($oficio['ruta_archivo']); ?>" class="action-btn-small green" target="_blank">Ver</a>
                            <a href="<?php echo htmlspecialchars($oficio['ruta_archivo']); ?>" class="action-btn-small blue" download>Descargar</a>
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
