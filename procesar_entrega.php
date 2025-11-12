<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// --- INICIO DE LA MODIFICACIÓN: Función para validar números romanos ---
function es_romano_valido($romano) {
    $romano = strtoupper($romano);
    // Expresión regular para números romanos del 1 al 49
    $regex = '/^X{0,3}(IX|IV|V?I{0,3})$|^XL(IX|IV|V?I{0,3})$/'; // I a XXXIX
    $regex_40s = '/^XL(IX|IV|V?I{0,3})$/'; // 40 a 49
    if ($romano === 'XL') return true;
    if (preg_match('/^X{0,4}L?', $romano) && strlen($romano) > 3 && strpos($romano, 'L') === false) return false; // Evita cosas como XXXX
    if (preg_match($regex, $romano) || preg_match($regex_40s, $romano)) {
        // Convertir a número para un chequeo final de rango, aunque el regex es bastante preciso
        $map = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50];
        $valor = 0;
        $i = 0;
        while ($i < strlen($romano)) {
            $actual = $map[$romano[$i]];
            $siguiente = ($i + 1 < strlen($romano)) ? $map[$romano[$i + 1]] : 0;
            if ($siguiente > $actual) {
                $valor += $siguiente - $actual;
                $i += 2;
            } else {
                $valor += $actual;
                $i++;
            }
        }
        return $valor >= 1 && $valor <= 49;
    }
    return false;
}
// --- FIN DE LA MODIFICACIÓN ---

// Seguridad: Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['requerimiento_id']) || !isset($_FILES['documento'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: Solicitud no válida.'];
    header('Location: dashboard_enlace.php');
    exit();
}

// Recuperar datos del usuario de la sesión
$user_id = $_SESSION['user_id'];
$user_expediente = $_SESSION['user_expediente'];
$user_area = $_SESSION['user_area'];
$current_year = date('Y');

$requerimiento_id = $_POST['requerimiento_id'];
$comentario = $_POST['comentario'] ?? '';
$documento = $_FILES['documento'];

// --- INICIO DE LA MODIFICACIÓN: Validación del nombre de archivo ---
$nombre_original = pathinfo($documento['name'], PATHINFO_FILENAME);
$partes = explode('_', $nombre_original);

if (count($partes) !== 4) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "Nombre de archivo inválido. Debe tener el formato: numerocurso_expediente_area_año."];
    header('Location: dashboard_enlace.php');
    exit();
}

list($num_curso, $expediente_archivo, $area_archivo, $anio_archivo) = $partes;

if (!es_romano_valido($num_curso)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "El número de curso '{$num_curso}' no es un número romano válido (I a XLIX)."];
    header('Location: dashboard_enlace.php');
    exit();
}
if ($expediente_archivo !== $user_expediente) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "El expediente en el nombre del archivo ({$expediente_archivo}) no coincide con tu expediente ({$user_expediente})."];
    header('Location: dashboard_enlace.php');
    exit();
}
if (strcasecmp($area_archivo, $user_area) !== 0) { // Comparación insensible a mayúsculas/minúsculas
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "El área en el nombre del archivo ({$area_archivo}) no coincide con tu área ({$user_area})."];
    header('Location: dashboard_enlace.php');
    exit();
}
if ($anio_archivo !== $current_year) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "El año en el nombre del archivo ({$anio_archivo}) debe ser el año actual ({$current_year})."];
    header('Location: dashboard_enlace.php');
    exit();
}
// --- FIN DE LA MODIFICACIÓN ---

date_default_timezone_set('America/Mexico_City');

try {
    // Verificación de la fecha límite en el lado del servidor...
    // (El resto del código permanece igual)
    $stmt = $conn->prepare("SELECT fecha_limite FROM requerimientos WHERE id = :id");
    $stmt->bindParam(':id', $requerimiento_id);
    $stmt->execute();
    $requerimiento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requerimiento) {
         $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: El requerimiento no existe.'];
         header('Location: dashboard_enlace.php');
         exit();
    }
    
    $fecha_limite = new DateTime($requerimiento['fecha_limite']);
    $ahora = new DateTime();

    if ($ahora > $fecha_limite) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'El plazo para esta entrega ha finalizado.'];
        header('Location: dashboard_enlace.php');
        exit();
    }
    
    $stmt_check = $conn->prepare("SELECT id FROM entregas WHERE user_id = :user_id AND requerimiento_id = :requerimiento_id");
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':requerimiento_id', $requerimiento_id);
    $stmt_check->execute();
    if ($stmt_check->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Ya has realizado una entrega para este requerimiento.'];
        header('Location: dashboard_enlace.php');
        exit();
    }

    if ($_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error al subir el archivo. Código: ' . $_FILES['documento']['error']];
        header('Location: dashboard_enlace.php');
        exit();
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Usar el nombre original validado para guardar el archivo
    $destination_path = $upload_dir . basename($_FILES['documento']['name']);

    if (move_uploaded_file($_FILES['documento']['tmp_name'], $destination_path)) {
        $stmt_insert = $conn->prepare(
            "INSERT INTO entregas (user_id, requerimiento_id, archivo_path, comentario, fecha_entrega) VALUES (:user_id, :requerimiento_id, :archivo_path, :comentario, NOW())"
        );
        $stmt_insert->bindParam(':user_id', $user_id);
        $stmt_insert->bindParam(':requerimiento_id', $requerimiento_id);
        $stmt_insert->bindParam(':archivo_path', $destination_path);
        $stmt_insert->bindParam(':comentario', $comentario);
        
        if ($stmt_insert->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => '¡Documento entregado exitosamente!'];
        } else {
            unlink($destination_path);
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error al registrar la entrega en la base de datos.'];
        }

    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error al mover el archivo subido al directorio final.'];
    }

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error de base de datos: ' . $e->getMessage()];
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

header('Location: dashboard_enlace.php');
exit();
?>