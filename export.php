<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Cargar el autoloader de Composer para incluir PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
require_once 'db_connection.php';

// 1. Verificación de Seguridad
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [4, 5])) {
    http_response_code(403);
    die('Acceso denegado.');
}

// 2. Obtener y Validar Filtros (igual que en estadisticas.php)
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$selected_taller = isset($_GET['taller']) ? $_GET['taller'] : 'all';
$selected_curso_id = isset($_GET['curso_id']) ? (is_numeric($_GET['curso_id']) ? (int)$_GET['curso_id'] : 'all') : 'all';

try {
    // 3. Construir y Ejecutar la Consulta (exactamente como en estadisticas.php)
    $main_query_conditions = [];
    $main_query_params = [];

    if ($selected_year != 'all') {
        $main_query_conditions[] = "YEAR(a.fecha_inicio) = :year";
        $main_query_params[':year'] = $selected_year;
    }
    if ($selected_taller != 'all') {
        $main_query_conditions[] = "u.taller = :taller";
        $main_query_params[':taller'] = $selected_taller;
    }
    if ($selected_curso_id != 'all') {
        $main_query_conditions[] = "a.curso_id = :curso_id";
        $main_query_params[':curso_id'] = $selected_curso_id;
    }

    $where_clause = !empty($main_query_conditions) ? " WHERE " . implode(' AND ', $main_query_conditions) : "";

    $reporte_sql = 
        "SELECT u.nombre_completo, u.expediente, u.taller, c.nombre_curso, a.fecha_inicio, a.duracion_horas " .
        "FROM asistencias_cursos a " .
        "JOIN usuarios u ON a.user_id = u.id " .
        "JOIN cursos c ON a.curso_id = c.id" .
        $where_clause . 
        " ORDER BY u.nombre_completo, a.fecha_inicio DESC";

    $stmt_reporte = $conn->prepare($reporte_sql);
    $stmt_reporte->execute($main_query_params);
    $results = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // 4. Crear el Archivo Excel con PhpSpreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte de Capacitación');

    // Encabezados
    $sheet->setCellValue('A1', 'Nombre Completo');
    $sheet->setCellValue('B1', 'Expediente');
    $sheet->setCellValue('C1', 'Taller');
    $sheet->setCellValue('D1', 'Curso');
    $sheet->setCellValue('E1', 'Fecha de Inicio');
    $sheet->setCellValue('F1', 'Duración (Horas)');

    // Aplicar estilo a los encabezados
    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4285F4']]
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($header_style);

    // Llenar datos
    $row_index = 2;
    foreach ($results as $row) {
        $sheet->setCellValue('A' . $row_index, $row['nombre_completo']);
        $sheet->setCellValue('B' . $row_index, $row['expediente']);
        $sheet->setCellValue('C' . $row_index, $row['taller']);
        $sheet->setCellValue('D' . $row_index, $row['nombre_curso']);
        // Formatear la fecha
        $formatted_date = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($row['fecha_inicio']));
        $sheet->setCellValue('E' . $row_index, $formatted_date);
        $sheet->getStyle('E' . $row_index)->getNumberFormat()->setFormatCode('dd/mm/yyyy');

        $sheet->setCellValue('F' . $row_index, $row['duracion_horas']);
        $row_index++;
    }

    // Auto-ajustar el tamaño de las columnas
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // 5. Enviar el Archivo al Navegador
    $writer = new Xlsx($spreadsheet);

    $filename = 'reporte_capacitacion_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    // Manejo de errores
    http_response_code(500);
    die("Error al generar el reporte: " . $e->getMessage());
}
?>