<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require_once 'db_connection.php';

echo "<html><head><title>Reparando Nombres</title>";
echo "<style>body { font-family: monospace; background-color: #111; color: #eee; } .success { color: #72ff72; } .error { color: #ff7272; } .info { color: #72c6ff; }</style>";
echo "</head><body>";
echo "<h1>INICIANDO PROCESO DE REPARACIÓN DE NOMBRES...</h1>";

// Mapa de caracteres corruptos a caracteres correctos
$correcciones = [
    'μ' => 'Á',
    'Š' => 'É',
    'Õ' => 'Í',
    'Ø' => 'Ó',
    'ü' => 'Ú',
    '¥' => 'Ñ'
    // Agregamos también las minúsculas por si acaso
    // (Aunque en las capturas todos son mayúsculas)
];

try {
    $conn->beginTransaction();

    // 1. Obtener todos los usuarios
    $stmt_select = $conn->query("SELECT id, nombre_completo FROM usuarios");
    $usuarios = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
    echo "<p class='info'>" . count($usuarios) . " usuarios encontrados para revisar.</p><hr>";

    $update_count = 0;
    $sql_update = "UPDATE usuarios SET nombre_completo = :nombre_corregido WHERE id = :id";
    $stmt_update = $conn->prepare($sql_update);

    // 2. Iterar y corregir cada uno
    foreach ($usuarios as $usuario) {
        $id = $usuario['id'];
        $nombre_original = $usuario['nombre_completo'];
        $nombre_corregido = $nombre_original;
        
        // Aplica todas las correcciones definidas en el mapa
        foreach ($correcciones as $char_malo => $char_bueno) {
            $nombre_corregido = str_replace($char_malo, $char_bueno, $nombre_corregido);
        }

        // 3. Si hubo un cambio, actualizar la base de datos
        if ($nombre_original !== $nombre_corregido) {
            echo "<p>ID: {$id} | ORIGINAL: " . htmlspecialchars($nombre_original, ENT_QUOTES, 'UTF-8') . " -> CORREGIDO: <span class='success'>" . htmlspecialchars($nombre_corregido, ENT_QUOTES, 'UTF-8') . "</span></p>";
            
            $stmt_update->execute([
                ':nombre_corregido' => $nombre_corregido,
                ':id' => $id
            ]);
            $update_count++;
        }
    }

    // 4. Confirmar los cambios en la base de datos
    $conn->commit();

    echo "<hr><h2 class='success'>¡PROCESO COMPLETADO!</h2>";
    echo "<p class='info'>Se han corregido un total de <strong>{$update_count}</strong> nombres en la base de datos.</p>";
    echo "<p>Por favor, revisa la página 'Plantilla General' para confirmar que los nombres ahora se ven correctamente.</p>";

} catch (PDOException $e) {
    // Si algo falla, revertir todo para no dejar la BD a medias.
    $conn->rollBack();
    echo "<h2 class='error'>¡ERROR CRÍTICO!</h2>";
    echo "<p class='error'>No se pudo completar la operación. Se han revertido todos los cambios.</p>";
    echo "<p>Detalle del error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>
