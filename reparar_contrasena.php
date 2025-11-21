<?php
// reparar_contrasenas.php

set_time_limit(240);
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';

$repaired_count = 0;
$error = '';

try {
    $conn->beginTransaction();

    // 1. Obtener TODOS los usuarios con su id, expediente y password
    $stmt_select = $conn->query("SELECT id, expediente, password FROM usuarios");
    $users = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

    $stmt_update = $conn->prepare("UPDATE usuarios SET password = :password WHERE id = :id");

    foreach ($users as $user) {
        $password = $user['password'];
        $expediente = $user['expediente'];
        $id = $user['id'];

        // 2. Revisar si la contraseña necesita ser hasheada.
        // Un hash válido de password_hash() nunca tendrá una longitud menor a 60.
        // Esta es una forma simple y efectiva de detectar contraseñas antiguas.
        if (strlen($password) < 60) {
            if (!empty($expediente)) {
                // 3. Si necesita reparación, se crea el nuevo hash y se actualiza
                $new_hash = password_hash($expediente, PASSWORD_DEFAULT);
                $stmt_update->execute([
                    ':password' => $new_hash,
                    ':id' => $id
                ]);
                $repaired_count++;
            }
        }
    }

    $conn->commit();
    $message = "¡Proceso de reparación completado! Se actualizaron y aseguraron <strong>" . $repaired_count . "</strong> contraseñas antiguas.";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $error = "Error crítico durante la reparación: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Reparación de Contraseñas</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .message, .error { padding: 15px; margin-top: 20px; border-radius: 5px; }
        .message { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Estado de la Base de Datos</h1>
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
