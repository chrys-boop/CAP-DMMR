<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'db_connection.php';

// --- LÓGICA DE SEGURIDAD ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

// --- LÓGICA DE PROCESAMIENTO DEL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roles'])) {
    // Si no se envió ningún rol, simplemente redirige.
    if (empty($_POST['roles'])) {
        header("Location: gestionar_personal.php");
        exit();
    }

    try {
        $conn->beginTransaction();
        $sql = "UPDATE usuarios SET role = :role WHERE id = :id";
        $stmt = $conn->prepare($sql);

        foreach ($_POST['roles'] as $userId => $newRole) {
            // Validar que el ID y el rol sean enteros
            $stmt->execute(['role' => (int)$newRole, 'id' => (int)$userId]);
        }

        $conn->commit();
        $_SESSION['flash_success'] = "¡ROLES ACTUALIZADOS CORRECTAMENTE!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_error'] = "ERROR AL ACTUALIZAR LOS ROLES: " . $e->getMessage();
    }
    
    header("Location: gestionar_personal.php");
    exit();
}

// --- OBTENCIÓN DE DATOS Y MENSAJES FLASH ---
$stmt = $conn->query("SELECT id, expediente, nombre_completo, role FROM usuarios ORDER BY nombre_completo ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success_message = $_SESSION['flash_success'] ?? '';
$error_message = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESTIÓN DE PERSONAL</title>
    <link rel="stylesheet" href="/CAP-DMMR/estilos/estilosges.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script>
    // Se mueve la función al <head> para que esté definida antes de ser llamada.
    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("userTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) { // Empezar en 1 para saltar la cabecera
            const tdNombre = tr[i].getElementsByTagName("td")[0];
            const tdExpediente = tr[i].getElementsByTagName("td")[1];

            if (tdNombre || tdExpediente) {
                const nombreText = tdNombre.textContent || tdNombre.innerText;
                const expedienteText = tdExpediente.textContent || tdExpediente.innerText;

                if (nombreText.toUpperCase().indexOf(filter) > -1 || expedienteText.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
    </script>
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>GESTIÓN DE PERSONAL</h1>
        <?php
        $dashboard_link = ($_SESSION['user_role'] == 5) ? 'dashboard.php' : 'dashboard_cap-dmmr.php';
        ?>
        <a href="<?php echo $dashboard_link; ?>" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">

        <section class="search-bar-section">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="BUSCAR POR NOMBRE O EXPEDIENTE...">
        </section>

        <form method="POST" action="gestionar_personal.php" id="rolesForm">
            <section class="user-management-table">
                <h3>LISTADO DE USUARIOS DEL SISTEMA</h3>

                <?php if ($success_message): ?>
                    <div class="success-message"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="userTable">
                        <thead>
                            <tr>
                                <th>NOMBRE</th>
                                <th>EXPEDIENTE</th>
                                <th>ROL ACTUAL</th>
                                <th>CAMBIAR ROL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['role']); ?></td>
                                    <td>
                                        <!-- Atributo `data-original-value` para guardar el valor inicial -->
                                        <select name="roles[<?php echo $usuario['id']; ?>]" class="role-select" data-original-value="<?php echo $usuario['role']; ?>">
                                            <option value="1" <?php echo ($usuario['role'] == 1) ? 'selected' : ''; ?>>1: TRABAJADOR</option>
                                            <option value="2" <?php echo ($usuario['role'] == 2) ? 'selected' : ''; ?>>2: INSTRUCTOR</option>
                                            <option value="3" <?php echo ($usuario['role'] == 3) ? 'selected' : ''; ?>>3: ENLACE</option>
                                            <option value="4" <?php echo ($usuario['role'] == 4) ? 'selected' : ''; ?>>4: CAP-DMMR</option>
                                            <option value="5" <?php echo ($usuario['role'] == 5) ? 'selected' : ''; ?>>5: ADMIN</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="save-btn">GUARDAR CAMBIOS</button>
            </section>
        </form>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO | TODOS LOS DERECHOS RESERVADOS</p>
    </footer>

    <script>
    // Este script se ejecuta cuando el DOM está listo.
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('rolesForm');
        if (form) {
            // Se añade un evento que se dispara justo antes de enviar el formulario.
            form.addEventListener('submit', function(e) {
                const selects = form.querySelectorAll('select.role-select');
                selects.forEach(function(select) {
                    // Si el valor del select NO ha cambiado respecto a su valor original...
                    if (select.value == select.getAttribute('data-original-value')) {
                        // ...se elimina su atributo 'name'.
                        // Los campos sin 'name' no se envían con el formulario.
                        select.name = '';
                    }
                });
            });
        }
    });
    </script>

</body>
</html>
