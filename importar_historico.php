<?php
// importar_historico.php (Versión Definitiva FINAL-CORREGIDA con Limpieza y Creación COMPLETA de Usuarios)

set_time_limit(600);
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require_once 'db_connection.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    header("Location: index.html");
    exit();
}

$message = '';
$error = '';
$summary = [
    'processed_rows' => 0,
    'inserted_count' => 0,
    'skipped_rows' => [],
    'new_courses_created' => 0,
    'new_users_created' => 0,
    'deleted_rows_count' => 0,
];

function getMonthNumber($monthName) {
    $months = [
        'ENERO' => '01', 'FEBRERO' => '02', 'MARZO' => '03', 'ABRIL' => '04',
        'MAYO' => '05', 'JUNIO' => '06', 'JULIO' => '07', 'AGOSTO' => '08',
        'SEPTIEMBRE' => '09', 'OCTUBRE' => '10', 'NOVIEMBRE' => '11', 'DICIEMBRE' => '12'
    ];
    $monthName = strtoupper(trim($monthName ?? ''));
    return $months[$monthName] ?? '01';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Error en la subida del archivo: " . $_FILES['excel_file']['error'];
    } else {
        $file_path = $_FILES['excel_file']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            array_shift($rows); // Omitir encabezados

            $conn->beginTransaction();

            // --- PASO 0: Limpiar TODOS los registros históricos anteriores para evitar duplicados ---
            $stmt_cleanup = $conn->prepare("DELETE FROM asistencias_cursos WHERE tipo_curso_excel IS NOT NULL AND tipo_curso_excel != ''");
            $stmt_cleanup->execute();
            $summary['deleted_rows_count'] = $stmt_cleanup->rowCount();

            // --- PASO 1: Auto-crear cursos que no existen ---
            $stmt_check_curso = $conn->prepare("SELECT id FROM cursos WHERE nombre_curso = :nombre_curso");
            $stmt_create_curso = $conn->prepare("INSERT INTO cursos (nombre_curso, tipo_curso, ubicacion) VALUES (:nombre_curso, 'Histórico', 'N/A')");
            
            $all_course_names = array_unique(array_column($rows, 'E'));
            foreach ($all_course_names as $course_name) {
                $trimmed_name = trim($course_name ?? '');
                if (empty($trimmed_name)) continue;

                $stmt_check_curso->execute([':nombre_curso' => $trimmed_name]);
                if ($stmt_check_curso->rowCount() == 0) {
                    $stmt_create_curso->execute([':nombre_curso' => $trimmed_name]);
                    $summary['new_courses_created']++;
                }
            }

            // --- PASO 2: Procesar e insertar asistencias (con creación COMPLETA de usuarios) ---
            $stmt_find_user = $conn->prepare("SELECT id FROM usuarios WHERE expediente = :expediente");
            // CORRECCIÓN: Se usa la columna `role` en lugar de `role_id`
            $stmt_create_user = $conn->prepare("INSERT INTO usuarios (expediente, nombre_completo, categoria, taller, password, role) VALUES (:exp, :nombre, :categoria, :taller, :pass, 1)");
            $stmt_find_curso = $conn->prepare("SELECT id FROM cursos WHERE nombre_curso = :nombre_curso");
            $stmt_insert_asistencia = $conn->prepare(
                "INSERT INTO asistencias_cursos (user_id, curso_id, fecha_inicio, duracion_horas, tipo_curso_excel)
                 VALUES (:user_id, :curso_id, :fecha_inicio, :duracion_horas, :tipo_curso_excel)"
            );
            
            $cached_cursos = []; 
            $cached_users = [];

            foreach ($rows as $row_num => $row) {
                $summary['processed_rows']++;

                $expediente = trim($row['A'] ?? '');
                $nombre_completo = trim($row['B'] ?? '');
                $categoria = trim($row['C'] ?? '');
                $taller = trim($row['D'] ?? '');
                $nombre_curso_excel = trim($row['E'] ?? '');
                $mes = trim($row['F'] ?? '');
                $anio = trim($row['G'] ?? '');
                $tipo_curso_excel = trim($row['H'] ?? '');

                if (empty($expediente) || empty($nombre_completo) || empty($nombre_curso_excel) || empty($anio)) {
                    $summary['skipped_rows'][] = "Fila #" . ($row_num + 2) . ": Faltan datos (Exp, Nombre, Curso o Año).";
                    continue;
                }

                // Buscar usuario (con cache y creación completa)
                if (!isset($cached_users[$expediente])) {
                    $stmt_find_user->execute([':expediente' => $expediente]);
                    $user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);
                    if (!$user) {
                        $stmt_create_user->execute([
                            ':exp' => $expediente,
                            ':nombre' => $nombre_completo, // Dato real del Excel
                            ':categoria' => $categoria,   // Dato real del Excel
                            ':taller' => $taller,       // Dato real del Excel
                            ':pass' => password_hash(uniqid(), PASSWORD_DEFAULT)
                        ]);
                        $cached_users[$expediente] = $conn->lastInsertId();
                        $summary['new_users_created']++;
                    } else {
                        $cached_users[$expediente] = $user['id'];
                    }
                }
                $user_id = $cached_users[$expediente];

                // Buscar curso (con cache)
                if (!isset($cached_cursos[$nombre_curso_excel])) {
                    $stmt_find_curso->execute([':nombre_curso' => $nombre_curso_excel]);
                    $curso = $stmt_find_curso->fetch(PDO::FETCH_ASSOC);
                     if (!$curso) { 
                        $summary['skipped_rows'][] = "Fila #" . ($row_num + 2) . ": Error, no se pudo encontrar/crear el curso '{$nombre_curso_excel}'.";
                        continue;
                    }
                    $cached_cursos[$nombre_curso_excel] = $curso['id'];
                }
                $curso_id = $cached_cursos[$nombre_curso_excel];

                $month_num = getMonthNumber($mes);
                $fecha_inicio = "{$anio}-{$month_num}-01";

                $stmt_insert_asistencia->execute([
                    ':user_id' => $user_id,
                    ':curso_id' => $curso_id,
                    ':fecha_inicio' => $fecha_inicio,
                    ':duracion_horas' => 0,
                    ':tipo_curso_excel' => $tipo_curso_excel
                ]);

                if ($stmt_insert_asistencia->rowCount() > 0) {
                    $summary['inserted_count']++;
                } else {
                     $summary['skipped_rows'][] = "Fila #" . ($row_num + 2) . ": No se pudo insertar el registro.";
                }
            }

            $conn->commit();
            $message = "Proceso de importación completado.";

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "Error crítico durante el procesamiento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Historial de Cursos</title>
    <link rel="stylesheet" href="estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .info, .success, .error, .summary { padding: 15px; margin: 20px 0; border-radius: 8px; font-size: 0.9em; }
        .info { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .summary { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .summary ul { max-height: 250px; overflow-y: auto; background-color: #fff; padding: 10px; border-radius: 5px; border: 1px solid #ccc; }
    </style>
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>Importar Historial de Cursos desde Excel</h1>
        <a href="dashboard.php" class="logout-btn">Volver al Panel</a>
    </header>

    <main class="dashboard-container">
        <section class="form-section">
            <div class="info">
                 <p>Este script importará el historial de cursos desde un archivo Excel.</p>
                <strong>Importante:</strong>
                <ul>
                    <li>Cada vez que suba un archivo, <strong>todos los registros históricos anteriores serán eliminados</strong> y reemplazados por los del nuevo archivo para evitar duplicados.</li>
                    <li>Si un curso del Excel no existe en el catálogo, se creará automáticamente.</li>
                    <li>Si un empleado (por su expediente) no existe en la base de datos, se creará un usuario <strong>completo</strong> con los datos del Excel para no perder sus registros.</li>
                </ul>
            </div>

            <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
                <div class="summary">
                    <h3>Resumen de la importación</h3>
                    <p><strong>Registros históricos eliminados:</strong> <?php echo $summary['deleted_rows_count']; ?></p>
                    <hr>
                    <p><strong>Nuevos cursos creados en el catálogo:</strong> <?php echo $summary['new_courses_created']; ?></p>
                    <p><strong>Nuevos usuarios creados:</strong> <?php echo $summary['new_users_created']; ?></p>
                    <p><strong>Filas procesadas del Excel:</strong> <?php echo $summary['processed_rows']; ?></p>
                    <p><strong>Nuevos registros de asistencia insertados:</strong> <?php echo $summary['inserted_count']; ?></p>
                    <p><strong>Filas omitidas:</strong> <?php echo count($summary['skipped_rows']); ?></p>
                    <?php if (!empty($summary['skipped_rows'])): ?>
                        <p><strong>Detalle de filas omitidas:</strong></p>
                        <ul>
                            <?php foreach (array_slice($summary['skipped_rows'], 0, 100) as $skip_reason): ?>
                                <li><?php echo htmlspecialchars($skip_reason); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="importar_historico.php" method="post" enctype="multipart/form-data" class="styled-form">
                <div class="form-group">
                    <label for="excel_file"><strong>Seleccionar archivo <code>.xlsx</code> para subir:</strong></label>
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                </div>
                <button type="submit" class="action-btn">Subir y Procesar Historial</button>
            </form>
        </section>
    </main>

    <footer class="dashboard-footer"><p>&copy; <?php echo date('Y'); ?> Sistema Administrativo</p></footer>
</body>
</html>