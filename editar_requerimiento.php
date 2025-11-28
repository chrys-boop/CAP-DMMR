<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// FIX: Añadir la variable de ruta base para redirecciones seguras
$base_path = '/CAP-DMMR';

// --- LÓGICA DE SEGURIDAD (CORREGIDA) ---
// Solo Admin (5) y Cap-dmmr (4) pueden acceder.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestionar_documentos.php");
    exit();
}
$requerimiento_id = $_GET['id'];

// --- Lógica de ACTUALIZACIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $fecha_limite = $_POST['fecha_limite'];
    $descripcion = !empty($_POST['descripcion']) ? $_POST['descripcion'] : null;
    $tipo_asignacion = $_POST['tipo_asignacion'] ?? '';

    $conn->beginTransaction();
    try {
        $sql_update_req = "UPDATE requerimientos SET titulo = :titulo, descripcion = :descripcion, fecha_limite = :fecha_limite WHERE id = :id";
        $stmt_update_req = $conn->prepare($sql_update_req);
        $stmt_update_req->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':fecha_limite' => $fecha_limite,
            ':id' => $requerimiento_id
        ]);

        $asignado_a_rol = null;
        if ($tipo_asignacion === 'rol') {
            $asignado_a_rol = $_POST['rol_id'] ?? null;
            $conn->prepare("DELETE FROM requerimiento_asignaciones WHERE requerimiento_id = ?")->execute([$requerimiento_id]);
        } elseif ($tipo_asignacion === 'todos') {
            $asignado_a_rol = 0;
            $conn->prepare("DELETE FROM requerimiento_asignaciones WHERE requerimiento_id = ?")->execute([$requerimiento_id]);
        } elseif ($tipo_asignacion === 'individual') {
            $asignado_a_rol = null; // Asegurar que el rol sea NULL para asignaciones individuales
            $conn->prepare("DELETE FROM requerimiento_asignaciones WHERE requerimiento_id = ?")->execute([$requerimiento_id]);
            if (!empty($_POST['usuarios_ids'])) {
                $sql_asig = "INSERT INTO requerimiento_asignaciones (requerimiento_id, user_id) VALUES (?, ?)";
                $stmt_asig = $conn->prepare($sql_asig);
                foreach ($_POST['usuarios_ids'] as $user_id) {
                    $stmt_asig->execute([$requerimiento_id, $user_id]);
                }
            }
        }

        $sql_update_rol = "UPDATE requerimientos SET asignado_a_rol = :rol WHERE id = :id";
        $stmt_update_rol = $conn->prepare($sql_update_rol);
        $stmt_update_rol->execute([':rol' => $asignado_a_rol, ':id' => $requerimiento_id]);

        $conn->commit();
        $_SESSION['flash_message'] = "¡REQUERIMIENTO ACTUALIZADO EXITOSAMENTE!";

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_message_error'] = "ERROR AL ACTUALIZAR: " . $e->getMessage();
    }
    
    header("Location: gestionar_documentos.php");
    exit();
}

// --- Lógica para OBTENER DATOS (GET) ---
try {
    $stmt = $conn->prepare("SELECT * FROM requerimientos WHERE id = ?");
    $stmt->execute([$requerimiento_id]);
    $requerimiento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requerimiento) {
        $_SESSION['flash_message_error'] = "NO SE ENCONTRÓ EL REQUERIMIENTO SOLICITADO.";
        header("Location: gestionar_documentos.php");
        exit();
    }

    $users = $conn->query("SELECT id, nombre_completo, expediente FROM usuarios ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);
    $roles_map = [1 => 'Trabajador', 2 => 'Instructor', 3 => 'Enlace', 4 => 'CAP-DMMR', 5 => 'Admin'];

    $stmt_ind = $conn->prepare("SELECT user_id FROM requerimiento_asignaciones WHERE requerimiento_id = ?");
    $stmt_ind->execute([$requerimiento_id]);
    $assigned_user_ids = $stmt_ind->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    die("ERROR AL CARGAR LOS DATOS PARA EDICIÓN: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDITAR REQUERIMIENTO</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/estilos/estilosgesdoc.css">
    <style>
        .assignment-block { background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; margin-top: 10px; }
        .assignment-block label { font-weight: 600; display: block; margin-bottom: 10px; }
        .user-select { width: 100%; height: 150px; border: 1px solid #ccc; border-radius: 4px; padding: 5px; }
        .user-select option { padding: 5px; }
    </style>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>EDITAR REQUERIMIENTO</h1>
        <a href="gestionar_documentos.php" class="logout-btn">CANCELAR Y VOLVER</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <h3>MODIFICANDO EL REQUERIMIENTO: "<?php echo htmlspecialchars($requerimiento['titulo']); ?>"</h3>

            <form action="editar_requerimiento.php?id=<?php echo $requerimiento_id; ?>" method="POST">
                <div class="form-group"><label for="titulo">TÍTULO:</label><input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($requerimiento['titulo']); ?>" required></div>
                <div class="form-group"><label for="fecha_limite">FECHA LÍMITE:</label><input type="date" id="fecha_limite" name="fecha_limite" value="<?php echo htmlspecialchars($requerimiento['fecha_limite']); ?>" required></div>
                <div class="form-group"><label for="descripcion">DESCRIPCIÓN:</label><textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($requerimiento['descripcion']); ?></textarea></div>

                <div class="form-group">
                    <label style="font-weight: bold; color: #333;">ASIGNAR A:</label>
                    <?php 
                        $is_individual = is_null($requerimiento['asignado_a_rol']);
                        $is_rol = !$is_individual && $requerimiento['asignado_a_rol'] != 0;
                        $is_todos = !$is_individual && $requerimiento['asignado_a_rol'] == 0;
                    ?>
                    
                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="rol" id="asig_rol" <?php if($is_rol) echo 'checked'; ?>>
                        <label for="asig_rol">UN ROL ESPECÍFICO:</label>
                        <select name="rol_id">
                            <?php foreach ($roles_map as $role_id => $role_name): ?>
                                <option value="<?php echo $role_id; ?>" <?php if($is_rol && $requerimiento['asignado_a_rol'] == $role_id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($role_name);
                                ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="individual" id="asig_individual" <?php if($is_individual) echo 'checked'; ?>>
                        <label for="asig_individual">USUARIO(S) ESPECÍFICO(S):</label>
                        <select name="usuarios_ids[]" multiple class="user-select">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php if($is_individual && in_array($user['id'], $assigned_user_ids)) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($user['nombre_completo']) . ' (' . htmlspecialchars($user['expediente']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>MANTÉN PRESIONADA LA TECLA CTRL (O CMD EN MAC) PARA SELECCIONAR VARIOS.</small>
                    </div>

                    <div class="assignment-block">
                        <input type="radio" name="tipo_asignacion" value="todos" id="asig_todos" <?php if($is_todos) echo 'checked'; ?>>
                        <label for="asig_todos">TODOS LOS USUARIOS</label>
                    </div>
                </div>
                
                <button type="submit" class="action-btn green">GUARDAR CAMBIOS</button>
            </form>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p>
    </footer>
</body>
</html>