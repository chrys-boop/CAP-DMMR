<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

$success_message = '';
$error_message = '';

// --- LÓGICA DE BORRADO (CORREGIDA Y ROBUSTA) ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // 1. Primero, verificar si el curso tiene asistencias registradas.
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM asistencias_cursos WHERE curso_id = :curso_id");
            $stmt_check->execute([':curso_id' => $delete_id]);
            $asistencias_count = $stmt_check->fetchColumn();

            // 2. Si el conteo es mayor a 0, no permitir el borrado.
            if ($asistencias_count > 0) {
                $_SESSION['flash_error'] = 'ERROR: NO SE PUEDE ELIMINAR EL CURSO PORQUE YA TIENE ASISTENCIAS REGISTRADAS (' . $asistencias_count . ' REGISTROS ENCONTRADOS).';
            } else {
                // 3. Si el conteo es 0, proceder con la eliminación.
                $stmt_delete = $conn->prepare("DELETE FROM cursos WHERE id = :id");
                $stmt_delete->execute([':id' => $delete_id]);
                $_SESSION['flash_message'] = '¡CURSO ELIMINADO DEL CATÁLOGO CON ÉXITO!';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'ERROR EN LA BASE DE DATOS: ' . $e->getMessage();
        }
    }
    header("Location: gestionar_cursos.php");
    exit();
}


// Mostrar mensajes flash
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// --- LÓGICA DE CREACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_curso'])) {
    $nombre_curso = trim($_POST['nombre_curso']);
    $tipo_curso = trim($_POST['tipo_curso']);
    $ubicacion = trim($_POST['ubicacion']);

    if (!empty($nombre_curso)) {
        try {
            $stmt_check = $conn->prepare("SELECT id FROM cursos WHERE nombre_curso = :nombre_curso");
            $stmt_check->execute([':nombre_curso' => $nombre_curso]);

            if ($stmt_check->fetch()) {
                $error_message = "ERROR: YA EXISTE UN CURSO CON ESE NOMBRE.";
            } else {
                $stmt = $conn->prepare("INSERT INTO cursos (nombre_curso, tipo_curso, ubicacion) VALUES (:nombre_curso, :tipo_curso, :ubicacion)");
                $stmt->execute([':nombre_curso' => $nombre_curso, ':tipo_curso' => $tipo_curso, ':ubicacion' => $ubicacion]);
                $success_message = "¡CURSO CREADO CON ÉXITO!";
            }
        } catch (PDOException $e) {
            $error_message = "ERROR EN LA BASE DE DATOS: " . $e->getMessage();
        }
    } else {
        $error_message = "EL NOMBRE DEL CURSO NO PUEDE ESTAR VACÍO.";
    }
}

// Obtener la lista de cursos
$cursos = $conn->query("SELECT * FROM cursos ORDER BY nombre_curso ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESTIONAR CATÁLOGO DE CURSOS</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>GESTIONAR CATÁLOGO DE CURSOS</h1>
        <a href="<?php echo ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php'; ?>" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">
        
        <?php if ($success_message): ?><div class="success-message"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>

        <section class="form-section">
            <h3>AÑADIR NUEVO CURSO AL CATÁLOGO</h3>
            <form action="gestionar_cursos.php" method="POST">
                <div class="form-group"><label for="nombre_curso">NOMBRE DEL CURSO</label><input type="text" id="nombre_curso" name="nombre_curso" required placeholder="EJ: SEGURIDAD E HIGIENE INDUSTRIAL"></div>
                <div class="form-group"><label for="tipo_curso">TIPO DE CURSO</label><input type="text" id="tipo_curso" name="tipo_curso" placeholder="EJ: INTERNO, EXTERNO, ONLINE"></div>
                <div class="form-group"><label for="ubicacion">UBICACIÓN DEL CURSO</label><input type="text" id="ubicacion" name="ubicacion" placeholder="EJ: SALA DE JUNTAS B, PLATAFORMA ZOOM"></div>
                <button type="submit" class="action-btn green">GUARDAR NUEVO CURSO</button>
            </form>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>CATÁLOGO DE CURSOS EXISTENTES</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>NOMBRE DEL CURSO</th><th>TIPO</th><th>UBICACIÓN</th><th>ACCIONES</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cursos)): ?>
                            <tr><td colspan="4" style="text-align: center;">NO HAY CURSOS REGISTRADOS.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cursos as $curso): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($curso['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['tipo_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($curso['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="editar_curso.php?id=<?php echo $curso['id']; ?>" class="action-btn-small orange">EDITAR</a>
                                        <a href="gestionar_cursos.php?delete_id=<?php echo $curso['id']; ?>" class="action-btn-small red" onclick="return confirm('¿ESTÁS SEGURO DE QUE QUIERES ELIMINAR ESTE CURSO? ESTA ACCIÓN NO SE PUEDE DESHACER.');">ELIMINAR</a>
                                    </td>
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