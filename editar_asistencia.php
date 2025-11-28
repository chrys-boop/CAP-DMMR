<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

$base_path = '/CAP-DMMR';

// 1. Proteger la página
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

$asistencia_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$asistencia = null;
$error_message = '';
$success_message = '';

if (!$asistencia_id) {
    $_SESSION['flash_error'] = "ID DE REGISTRO NO VÁLIDO.";
    header("Location: registrar_asistencia.php");
    exit();
}

// 2. Lógica de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $curso_id = $_POST['curso_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $duracion_horas = $_POST['duracion_horas'];

    if (!empty($user_id) && !empty($curso_id) && !empty($fecha_inicio) && !empty($duracion_horas)) {
        try {
            $stmt = $conn->prepare(
                "UPDATE asistencias_cursos SET user_id = :user_id, curso_id = :curso_id, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, duracion_horas = :duracion_horas WHERE id = :id"
            );
            $stmt->execute([
                ':user_id' => $user_id,
                ':curso_id' => $curso_id,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => !empty($fecha_fin) ? $fecha_fin : NULL,
                ':duracion_horas' => $duracion_horas,
                ':id' => $asistencia_id
            ]);
            $_SESSION['flash_message'] = "¡REGISTRO DE ASISTENCIA ACTUALIZADO CON ÉXITO!";
            header("Location: registrar_asistencia.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "ERROR AL ACTUALIZAR EL REGISTRO: " . $e->getMessage();
        }
    } else {
        $error_message = "POR FAVOR, COMPLETA TODOS LOS CAMPOS OBLIGATORIOS.";
    }
}

// 3. Obtener datos para el formulario
try {
    // Datos del registro de asistencia específico
    $stmt = $conn->prepare("SELECT * FROM asistencias_cursos WHERE id = :id");
    $stmt->execute([':id' => $asistencia_id]);
    $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asistencia) {
        $_SESSION['flash_error'] = "NO SE ENCONTRÓ EL REGISTRO SOLICITADO.";
        header("Location: registrar_asistencia.php");
        exit();
    }

    // Lista de todos los usuarios y cursos para los desplegables
    $stmt_users = $conn->query("SELECT id, nombre_completo, expediente FROM usuarios WHERE role BETWEEN 1 AND 5 ORDER BY nombre_completo ASC");
    $todos_usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $stmt_cursos = $conn->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "ERROR AL CARGAR LOS DATOS PARA LA EDICIÓN: " . $e->getMessage();
    header("Location: registrar_asistencia.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDITAR REGISTRO DE ASISTENCIA</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>EDITAR REGISTRO DE ASISTENCIA</h1>
        <a href="registrar_asistencia.php" class="logout-btn">VOLVER AL HISTORIAL</a>
    </header>

    <main class="dashboard-container">
        
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>

        <section class="form-section">
            <h3>MODIFICAR REGISTRO</h3>
            <form action="editar_asistencia.php?id=<?php echo $asistencia['id']; ?>" method="POST">
                
                <div class="form-group">
                    <label for="user_id">USUARIO</label>
                    <select id="user_id" name="user_id" required>
                        <?php foreach ($todos_usuarios as $usuario): ?>
                            <option value="<?php echo $usuario['id']; ?>" <?php echo ($usuario['id'] == $asistencia['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nombre_completo']) . ' (' . htmlspecialchars($usuario['expediente']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="curso_id">CURSO</label>
                    <select id="curso_id" name="curso_id" required>
                         <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>" <?php echo ($curso['id'] == $asistencia['curso_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">FECHA DE INICIO</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?php echo htmlspecialchars($asistencia['fecha_inicio']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">FECHA DE FIN (OPCIONAL)</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($asistencia['fecha_fin']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="duracion_horas">DURACIÓN TOTAL (HORAS)</label>
                    <input type="number" id="duracion_horas" name="duracion_horas" step="0.1" required value="<?php echo htmlspecialchars($asistencia['duracion_horas']); ?>">
                </div>

                <button type="submit" class="action-btn green">GUARDAR CAMBIOS</button>
            </form>
        </section>

    </main>

    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p></footer>

</body>
</html>