<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// Security Check: Role 3 for Enlace, and must have a taller assigned
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3 || empty($_SESSION['user_taller'])) {
    header("Location: index.html");
    exit();
}

$user_taller = $_SESSION['user_taller'];

// --- INITIALIZE VARIABLES ---
$search_expediente = isset($_GET['search_expediente']) ? trim($_GET['search_expediente']) : '';
$selected_expediente = isset($_GET['expediente']) ? trim($_GET['expediente']) : '';

$taller_users = [];
$user_details = null;
$error_message = '';

try {
    // --- LOGIC TO DISPLAY FULL USER DETAILS ---
    if (!empty($selected_expediente)) {
        // Security: Ensure the requested user belongs to the same taller as the enlace
        $stmt_details = $conn->prepare("SELECT * FROM usuarios WHERE expediente = :expediente AND taller = :taller");
        $stmt_details->execute([':expediente' => $selected_expediente, ':taller' => $user_taller]);
        $user_details = $stmt_details->fetch(PDO::FETCH_ASSOC);
    }

    // --- LOGIC TO DISPLAY THE LIST OF USERS (PLANTILLA) ---
    $sql_conditions = ["taller = :taller"];
    $sql_params = [':taller' => $user_taller];

    if (!empty($search_expediente)) {
        $sql_conditions[] = "expediente LIKE :expediente";
        $sql_params[':expediente'] = "%{$search_expediente}%";
    }

    $where_clause = " WHERE " . implode(' AND ', $sql_conditions);
    $sql_all_users = "SELECT id, expediente, nombre_completo, categoria, taller FROM usuarios" . $where_clause . " ORDER BY nombre_completo ASC";
    
    $stmt_all_users = $conn->prepare($sql_all_users);
    $stmt_all_users->execute($sql_params);
    $taller_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "ERROR EN LA BASE DE DATOS: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLANTILLA DE TALLER</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .details-card { background-color: #e9f5ff; border-left: 5px solid #2980b9; padding: 20px; margin-bottom: 30px; border-radius: 8px; }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .details-grid p { margin: 0; padding: 5px; background-color: #fff; border-radius: 4px; }
        .details-grid p strong { color: #2980b9; }
        .filter-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end; }
    </style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>PLANTILLA DEL TALLER: <?php echo htmlspecialchars($user_taller); ?></h1>
        <a href="dashboard_enlace.php" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">

        <?php if ($error_message): ?><div class="error-message"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <!-- User Details Card -->
        <?php if ($user_details): ?>
            <section class="details-card">
                <h3>DETALLES DE: <strong><?php echo htmlspecialchars($user_details['nombre_completo']); ?></strong></h3>
                <div class="details-grid">
                    <p><strong>EXPEDIENTE:</strong> <?php echo htmlspecialchars($user_details['expediente']); ?></p>
                    <p><strong>CATEGORÍA:</strong> <?php echo htmlspecialchars($user_details['categoria'] ?? 'N/A'); ?></p>
                    <p><strong>TALLER:</strong> <?php echo htmlspecialchars($user_details['taller'] ?? 'N/A'); ?></p>
                    <p><strong>ÁREA INTERNA:</strong> <?php echo htmlspecialchars($user_details['area_interna'] ?? 'N/A'); ?></p>
                    <p><strong>CALIDAD LABORAL:</strong> <?php echo htmlspecialchars($user_details['calidad_laboral'] ?? 'N/A'); ?></p>
                    <p><strong>EMAIL:</strong> <?php echo htmlspecialchars($user_details['email'] ?? 'N/A'); ?></p>
                </div>
            </section>
        <?php elseif (!empty($selected_expediente)): ?>
             <div class="error-message">NO SE ENCONTRARON DETALLES PARA EL EXPEDIENTE '<?php echo htmlspecialchars($selected_expediente); ?>' O NO PERTENECE A TU TALLER.</div>
        <?php endif; ?>

        <!-- Filter Section -->
        <section class="filter-section">
            <h3>BUSCAR EN TU TALLER</h3>
            <form action="plantilla_taller.php" method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="search_expediente">BUSCAR POR EXPEDIENTE</label>
                        <input type="text" name="search_expediente" id="search_expediente" value="<?php echo htmlspecialchars($search_expediente); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="action-btn">BUSCAR</button>
                        <a href="plantilla_taller.php" class="action-btn-secondary" style="text-decoration:none;">LIMPIAR</a>
                    </div>
                </div>
            </form>
        </section>

        <!-- Users Table -->
        <section class="table-section">
            <h3>PERSONAL DE TU TALLER (<?php echo count($taller_users); ?> ENCONTRADOS)</h3>
            <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE COMPLETO</th>
                            <th>EXPEDIENTE</th>
                            <th>CATEGORÍA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($taller_users)): ?>
                            <tr><td colspan="3">NO SE ENCONTRARON USUARIOS EN TU TALLER.</td></tr>
                        <?php else: ?>
                            <?php foreach ($taller_users as $user): ?>
                                <tr>
                                    <td><a href="plantilla_taller.php?expediente=<?php echo htmlspecialchars($user['expediente']); ?>"><?php echo htmlspecialchars($user['nombre_completo']); ?></a></td>
                                    <td><?php echo htmlspecialchars($user['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($user['categoria'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p></footer>

</body>
</html>