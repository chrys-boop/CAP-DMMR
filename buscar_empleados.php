<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

$query = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $sql = "SELECT expediente, nombre_completo FROM usuarios WHERE nombre_completo LIKE :query OR expediente LIKE :query ORDER BY nombre_completo ASC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':query' => "%{$query}%"]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $suggestions = [];
    foreach ($results as $row) {
        // Formato para jQuery UI Autocomplete: label y value
        $suggestions[] = [
            'label' => $row['nombre_completo'] . ' (' . $row['expediente'] . ')',
            'value' => $row['expediente'] // Se usará el expediente para la búsqueda final
        ];
    }
    
    echo json_encode($suggestions);

} catch (PDOException $e) {
    // En un entorno de producción, registrarías este error en lugar de mostrarlo
    http_response_code(500);
    echo json_encode(['error' => 'ERROR EN LA BASE DE DATOS']);
}
?>