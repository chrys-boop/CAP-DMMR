<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// --- LÓGICA DE SEGURIDAD (CORREGIDA) ---
// Solo Admin (5) y Cap-dmmr (4) pueden acceder.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

// --- Obtener datos para los formularios ---
$users = $conn->query("SELECT id, nombre_completo, expediente FROM usuarios ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);
$roles_map = [1 => 'Trabajador', 2 => 'Instructor', 3 => 'Enlace', 4 => 'CAP-DMMR', 5 => 'Admin'];

// --- Lógica de Notificaciones (Mensajes Flash) ---
$message = null;
$message_type = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = 'success';
    unset($_SESSION['flash_message']);
} elseif (isset($_SESSION['flash_message_error'])) {
    $message = $_SESSION['flash_message_error'];
    $message_type = 'error';
    unset($_SESSION['flash_message_error']);
}

// --- Lógica para ELIMINAR un requerimiento ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        // La BD se encarga de borrar en cascada las asignaciones y entregas.
        $sql_delete_req = "DELETE FROM requerimientos WHERE id = :id";
        $stmt_delete_req = $conn->prepare($sql_delete_req);
        $stmt_delete_req->execute([':id' => $_GET['id']]);
        $_SESSION['flash_message'] = "Requerimiento eliminado exitosamente.";
    } catch (PDOException $e) {
        $_SESSION['flash_message_error'] = "Error al eliminar: " . $e->getMessage();
    }
    header("Location: gestionar_documentos.php");
    exit();
}

// --- Lógica para GUARDAR un nuevo requerimiento ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = $_POST['titulo'];
    $fecha_limite = $_POST['fecha_limite'];
    $descripcion = !empty($_POST['descripcion']) ? $_POST['descripcion'] : null;
    $tipo_asignacion = $_POST['tipo_asignacion'] ?? '';

    $conn->beginTransaction();
    try {
        $asignado_a_rol = null;

        if ($tipo_asignacion === 'rol') {
            $asignado_a_rol = $_POST['rol_id'] ?? null;
        } elseif ($tipo_asignacion === 'todos') {
            $asignado_a_rol = 0; // Usamos 0 como valor especial para "todos"
        }

        // 1. Insertar el requerimiento base
        $sql_req = "INSERT INTO requerimientos (titulo, descripcion, fecha_limite, asignado_a_rol) VALUES (:titulo, :descripcion, :fecha_limite, :rol)";
        $stmt_req = $conn->prepare($sql_req);
        $stmt_req->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':fecha_limite' => $fecha_limite,
            ':rol' => $asignado_a_rol
        ]);
        $requerimiento_id = $conn->lastInsertId();

        // 2. Si es asignación individual, insertar en la tabla de asignaciones
        if ($tipo_asignacion === 'individual' && !empty($_POST['usuarios_ids'])) {
            $usuarios_ids = $_POST['usuarios_ids'];
            $sql_asig = "INSERT INTO requerimiento_asignaciones (requerimiento_id, user_id) VALUES (:req_id, :user_id)";
            $stmt_asig = $conn->prepare($sql_asig);
            foreach ($usuarios_ids as $user_id) {
                $stmt_asig->execute([':req_id' => $requerimiento_id, ':user_id' => $user_id]);
            }
        }

        $conn->commit();
        $_SESSION['flash_message'] = "¡Requerimiento creado y asignado exitosamente!";

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_message_error'] = "Error al crear el requerimiento: " . $e->getMessage();
    }
    header("Location: gestionar_documentos.php");
    exit();
}

// --- Lógica para LEER y mostrar los requerimientos existentes ---
$requerimientos = $conn->query("SELECT * FROM requerimientos ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Documentos</title>
    <link rel="stylesheet" href="estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .action-btn-small.red { background-color: #dc3545; }
        .action-btn-small.red:hover { background-color: #c82333; }
        .assignment-block { background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; margin-top: 10px; }
        .assignment-block label { font-weight: 600; display: block; margin-bottom: 10px; }
        .user-select { width: 100%; height: 150px; border: 1px solid #ccc; border-radius: 4px; padding: 5px; }
        .user-select option { padding: 5px; }
    </style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Gestionar Documentos</h1>
        <?php
        // --- BOTÓN DE VOLVER DINÁMICO (CORREGIDO) ---
        $dashboard_link = ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php';
        ?>
        <a href="<?php echo $dashboard_link; ?>" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>Crear Nuevo Requerimiento</h3>
            <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form action="gestionar_documentos.php" method="POST">
                <div class="form-group">
                    <label for="titulo">Título del Requerimiento:</label>
                    <input type="text" id="titulo" name="titulo" placeholder="Ej: Reporte Mensual de Actividades" required>
                </div>
                <div class="form-group">
                    <label for="fecha_limite">Fecha Límite de Entrega:</label>
                    <input type="date" id="fecha_limite" name="fecha_limite" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción (Opcional):</label>
                    <textarea id="descripcion" name="descripcion" rows="3" placeholder="Instrucciones adicionales sobre el documento a entregar..."></textarea>
                </div>

                <!-- NUEVO: Sección de Asignación -->
                <div class="form-group">
                    <label style="font-weight: bold; color: #333;">Asignar a:</label>
                    
                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="rol" id="asig_rol" checked>
                        <label for="asig_rol">Un Rol Específico:</label>
                        <select name="rol_id">
                            <?php foreach ($roles_map as $role_id => $role_name): ?>
                                <option value="<?php echo $role_id; ?>"><?php echo htmlspecialchars($role_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="individual" id="asig_individual">
                        <label for="asig_individual">Usuario(s) Específico(s):</label>
                        <select name="usuarios_ids[]" multiple class="user-select">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['nombre_completo']) . ' (' . htmlspecialchars($user['expediente']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Mantén presionada la tecla Ctrl (o Cmd en Mac) para seleccionar varios usuarios.</small>
                    </div>

                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="todos" id="asig_todos">
                        <label for="asig_todos">Todos los usuarios</label>
                    </div>
                </div>
                
                <button type="submit" class="action-btn green">Crear y Asignar Requerimiento</button>
            </form>
        </section>

        <hr class="section-divider">

        <section class="table-section">
            <h3>Listado de Requerimientos</h3>
            <!-- El listado se mantiene por ahora, se mejorará después -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Fecha Límite</th>
                            <th>Asignado a</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requerimientos as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['titulo']); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($req['fecha_limite']))); ?></td>
                                <td>
                                    <?php 
                                    if ($req['asignado_a_rol'] === '0') {
                                        echo '<strong>Todos los Usuarios</strong>';
                                    } elseif (isset($roles_map[$req['asignado_a_rol']])) {
                                        echo 'Rol: <strong>' . htmlspecialchars($roles_map[$req['asignado_a_rol']]) . '</strong>';
                                    } else {
                                        echo 'Usuarios Específicos';
                                    }
                                    ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="ver_entregas.php?id=<?php echo $req['id']; ?>" class="action-btn-small blue">Ver Entregas</a>
                                    <a href="editar_requerimiento.php?id=<?php echo $req['id']; ?>" class="action-btn-small orange">Editar</a> <!-- Deshabilitado temporalmente -->
                                    <a href="?action=delete&id=<?php echo $req['id']; ?>" class="action-btn-small red" onclick="return confirm('¿Estás seguro de eliminar este requerimiento y todas sus entregas asociadas?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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