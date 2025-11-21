<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. Validar sesión de usuario
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// 2. Obtener la lista de manuales y diagramas
try {
    $stmt = $conn->prepare("SELECT id, nombre_archivo, ruta_archivo, fecha_subida FROM manuales ORDER BY fecha_subida DESC");
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $documentos = [];
    $error_message = "Error al conectar con la base de datos.";
}

// --- LÓGICA PARA EL BOTÓN DE VOLVER ---
// Determina el dashboard correcto según el rol del usuario
$dashboard_link = 'index.html'; // Fallback por si no hay rol
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 5: $dashboard_link = 'dashboard.php'; break;
        case 4: $dashboard_link = 'dashboard_cap-dmmr.php'; break;
        case 3: $dashboard_link = 'dashboard_enlace.php'; break;
        case 2: $dashboard_link = 'dashboard_instructor.php'; break;
        case 1: $dashboard_link = 'dashboard_trabajador.php'; break;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manuales y Diagramas</title>
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Manuales y Diagramas</h1>
        <!-- BOTÓN DE VOLVER CORREGIDO -->
        <a href="<?php echo $dashboard_link; ?>" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="manuales-section">
            <h2 class="section-title">📘 Diagramas y Manuales Disponibles</h2>

            <?php if (isset($error_message)): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (empty($documentos)): ?>
                <div class="empty-state">
                    <p>No hay manuales o diagramas disponibles en este momento.</p>
                </div>
            <?php else: ?>
                <div class="document-grid">
                    <?php foreach ($documentos as $doc): ?>
                        <div class="document-card">
                            <div class="doc-icon">📄</div>
                            <div class="doc-info">
                                <h4 class="doc-title"><?php echo htmlspecialchars($doc['nombre_archivo']); ?></h4>
                                <p class="doc-date">Subido el <?php echo date("d/m/Y H:i", strtotime($doc['fecha_subida'])); ?> hs</p>
                            </div>
                            <div class="doc-actions">
                                <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" class="btn-view" target="_blank">👁️ Ver</a>
                                <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" class="btn-download" download>⬇️ Descargar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>
