<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Lógica para procesar el guardado de roles (cuando se envíe el formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roles'])) {
    // Preparamos una única consulta para actualizar
    $sql = "UPDATE usuarios SET role = :role WHERE id = :id";
    $stmt = $conn->prepare($sql);

    foreach ($_POST['roles'] as $userId => $newRole) {
        $stmt->execute(['role' => $newRole, 'id' => $userId]);
    }
    // Redirigimos a la misma página con un mensaje de éxito
    header("Location: gestionar_personal.php?success=1");
    exit();
}

// Obtener todos los usuarios
$stmt = $conn->query("SELECT id, expediente, nombre_completo, role FROM usuarios ORDER BY nombre_completo ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Personal</title>
    <!-- ¡RUTA ABSOLUTA CORREGIDA! -->
    <link rel="stylesheet" href="/CAP-DMMR/estilos/estilosges.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Gestión de Personal</h1>
        <a href="dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">

        <!-- Campo de búsqueda -->
        <section class="search-bar-section">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por nombre o expediente...">
        </section>

        <!-- Formulario para guardar los roles -->
        <form method="POST" action="gestionar_personal.php">
            <section class="user-management-table">
                <h3>Listado de Usuarios del Sistema</h3>

                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">¡Roles actualizados correctamente!</div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="userTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Expediente</th>
                                <th>Rol Actual</th>
                                <th>Cambiar Rol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['expediente']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['role']); ?></td>
                                    <td>
                                        <select name="roles[<?php echo $usuario['id']; ?>]">
                                            <option value="1" <?php echo ($usuario['role'] == 1) ? 'selected' : ''; ?>>1: Trabajador</option>
                                            <option value="2" <?php echo ($usuario['role'] == 2) ? 'selected' : ''; ?>>2: Instructor</option>
                                            <option value="3" <?php echo ($usuario['role'] == 3) ? 'selected' : ''; ?>>3: Enlace</option>
                                            <option value="4" <?php echo ($usuario['role'] == 4) ? 'selected' : ''; ?>>4: CAP-DMMR</option>
                                            <option value="5" <?php echo ($usuario['role'] == 5) ? 'selected' : ''; ?>>5: Admin</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="save-btn">Guardar Cambios</button>
            </section>
        </form>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo | Todos los derechos reservados</p>
    </footer>

    <!-- JavaScript para el filtro de búsqueda -->
    <script>
    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("userTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
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

</body>
</html>