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

// --- LÓGICA DE BORRADO ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM asistencias_cursos WHERE id = :id");
            $stmt->execute([':id' => $delete_id]);
            $_SESSION['flash_message'] = '¡Registro de asistencia eliminado con éxito!';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Error al eliminar el registro: ' . $e->getMessage();
        }
    }
    header("Location: registrar_asistencia.php");
    exit();
}

// Manejar mensajes flash
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// --- LÓGICA DE REGISTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $curso_id = $_POST['curso_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_termino = $_POST['fecha_termino']; // <-- CORREGIDO
    $duracion_horas = $_POST['duracion_horas'];

    if (!empty($user_id) && !empty($curso_id) && !empty($fecha_inicio) && !empty($duracion_horas)) {
        try {
            // La columna 'fecha_fin' ya no se usa, usamos 'fecha_termino'
            $stmt = $conn->prepare("INSERT INTO asistencias_cursos (user_id, curso_id, fecha_inicio, fecha_termino, duracion_horas) VALUES (:user_id, :curso_id, :fecha_inicio, :fecha_termino, :duracion_horas)");
            $stmt->execute([
                ':user_id' => $user_id, ':curso_id' => $curso_id, ':fecha_inicio' => $fecha_inicio,
                ':fecha_termino' => !empty($fecha_termino) ? $fecha_termino : NULL, // <-- CORREGIDO
                ':duracion_horas' => $duracion_horas
            ]);
            $success_message = "¡Asistencia al curso registrada con éxito!";
        } catch (PDOException $e) {
            $error_message = "Error al registrar la asistencia: " . $e->getMessage();
        }
    } else {
        $error_message = "Por favor, completa todos los campos obligatorios.";
    }
}

// --- LÓGICA PARA OBTENER DATOS (historial actualizado) ---
$stmt_users = $conn->query("SELECT id, nombre_completo, expediente FROM usuarios WHERE role BETWEEN 1 AND 5 ORDER BY nombre_completo ASC");
$todos_usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
$stmt_cursos = $conn->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
$stmt_asistencias = $conn->query(
    "SELECT a.id, u.nombre_completo, u.expediente, c.nombre_curso, a.fecha_inicio, a.fecha_termino, a.duracion_horas " .
    "FROM asistencias_cursos a JOIN usuarios u ON a.user_id = u.id JOIN cursos c ON a.curso_id = c.id ORDER BY a.id DESC"
);
$asistencias = $stmt_asistencias->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Asistencia a Cursos</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .autocomplete-container { position: relative; }
        .autocomplete-results { position: absolute; border: 1px solid #ddd; border-top: none; z-index: 99; top: 100%; left: 0; right: 0; background-color: white; max-height: 200px; overflow-y: auto; display: none; }
        .autocomplete-results div { padding: 10px; cursor: pointer; border-bottom: 1px solid #ddd; }
        .autocomplete-results div:hover { background-color: #f1f1f1; }
    </style>
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>Registrar Asistencia a Cursos</h1>
        <a href="<?php echo ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php'; ?>" class="logout-btn">Volver al Panel</a>
    </header>
    <main class="dashboard-container">
        <?php if ($success_message): ?><div class="success-message"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>
        <section class="form-section">
            <h3>Registrar Nueva Asistencia</h3>
            <form action="registrar_asistencia.php" method="POST" id="asistencia-form">
                <div class="form-group autocomplete-container">
                    <label for="user_search">Buscar Usuario (por nombre o expediente)</label>
                    <input type="text" id="user_search" placeholder="Escribe para buscar..." autocomplete="off" required>
                    <input type="hidden" id="user_id" name="user_id">
                    <div id="user_results" class="autocomplete-results"></div>
                </div>
                <div class="form-group">
                    <label for="curso_id">Seleccionar Curso</label>
                    <select id="curso_id" name="curso_id" required>
                        <option value="">-- Elige un curso del catálogo --</option>
                         <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nombre_curso']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_termino">Fecha de Término (opcional)</label> <!-- CORREGIDO -->
                        <input type="date" id="fecha_termino" name="fecha_termino"> <!-- CORREGIDO -->
                    </div>
                </div>
                <div class="form-group">
                    <label for="duracion_horas">Duración Total (Horas)</label>
                    <input type="number" id="duracion_horas" name="duracion_horas" step="0.1" required placeholder="Ej: 8, 16.5, 24">
                </div>
                <button type="submit" class="action-btn green">Guardar Registro</button>
            </form>
        </section>
        <section class="table-section">
            <h3>Historial de Asistencias</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Empleado (Expediente)</th>
                            <th>Curso</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Término</th> <!-- AÑADIDO -->
                            <th>Duración (Hrs)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($asistencias)): ?>
                            <tr><td colspan="6">No hay asistencias registradas.</td></tr> <!-- colspan actualizado -->
                        <?php else: ?>
                            <?php foreach ($asistencias as $asistencia): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asistencia['nombre_completo']) . ' (' . htmlspecialchars($asistencia['expediente']) . ')'; ?></td>
                                    <td><?php echo htmlspecialchars($asistencia['nombre_curso']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($asistencia['fecha_inicio'])); ?></td>
                                    <td><?php echo $asistencia['fecha_termino'] ? date("d/m/Y", strtotime($asistencia['fecha_termino'])) : 'N/A'; ?></td> <!-- AÑADIDO -->
                                    <td><?php echo htmlspecialchars($asistencia['duracion_horas']); ?></td>
                                    <td class="actions-cell">
                                        <a href="editar_asistencia.php?id=<?php echo $asistencia['id']; ?>" class="action-btn-small orange">Editar</a>
                                        <a href="registrar_asistencia.php?delete_id=<?php echo $asistencia['id']; ?>" class="action-btn-small red" onclick="return confirm('¿Estás seguro de que quieres eliminar este registro? Es una acción irreversible.');">Borrar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <footer class="dashboard-footer"><p>© <?php echo date('Y'); ?> Sistema Administrativo</p></footer>
<script>
    const usuarios = <?php echo json_encode($todos_usuarios); ?>;
    const userSearchInput = document.getElementById('user_search');
    const userIdInput = document.getElementById('user_id');
    const userResultsDiv = document.getElementById('user_results');
    const asistenciaForm = document.getElementById('asistencia-form');
    userSearchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        userResultsDiv.innerHTML = '';
        userIdInput.value = '';
        if (query.length < 2) { userResultsDiv.style.display = 'none'; return; }
        const filteredUsers = usuarios.filter(u => u.nombre_completo.toLowerCase().includes(query) || u.expediente.toString().toLowerCase().includes(query));
        userResultsDiv.style.display = 'block';
        filteredUsers.forEach(usuario => {
            const userDiv = document.createElement('div');
            userDiv.textContent = `${usuario.nombre_completo} (${usuario.expediente})`;
            userDiv.addEventListener('click', () => {
                userSearchInput.value = userDiv.textContent;
                userIdInput.value = usuario.id;
                userResultsDiv.style.display = 'none';
            });
            userResultsDiv.appendChild(userDiv);
        });
    });
    document.addEventListener('click', e => { if (!userSearchInput.contains(e.target)) userResultsDiv.style.display = 'none'; });
    asistenciaForm.addEventListener('submit', e => {
        if (!userIdInput.value) {
            e.preventDefault();
            alert('Debes buscar y seleccionar un usuario válido de la lista.');
            userSearchInput.focus();
        }
    });
</script>
</body>
</html>
