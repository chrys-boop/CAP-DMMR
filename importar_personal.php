<?php
// importar_personal.php

set_time_limit(0); 
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';

$conn->exec("SET NAMES 'utf8mb4'");

$message = '';
$error = '';
$updated_count = 0;
$inserted_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file_path = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file_path, "r")) !== FALSE) {
        fgetcsv($handle); // Omitir cabeceras

        // --- AJUSTE DE LOTES ---
        // Lotes más pequeños para evitar timeouts al hashear contraseñas
        $batch_size = 50;
        $record_counter = 0;

        $conn->beginTransaction();

        try {
            $stmt_select = $conn->prepare("SELECT id FROM usuarios WHERE expediente = :expediente");
            $stmt_update = $conn->prepare(
                "UPDATE usuarios SET 
                    nombre_completo = :nombre_completo, password = :password, role = :role, categoria = :categoria, 
                    taller = :taller, area_interna = :area_interna, calidad_laboral = :calidad_laboral, 
                    descanso = :descanso, horario_entrada = :horario_entrada, horario_salida = :horario_salida, 
                    fecha_ingreso = :fecha_ingreso 
                WHERE expediente = :expediente"
            );
            $stmt_insert = $conn->prepare(
                "INSERT INTO usuarios (expediente, nombre_completo, password, role, categoria, taller, area_interna, calidad_laboral, descanso, fecha_ingreso, horario_entrada, horario_salida) 
                VALUES (:expediente, :nombre_completo, :password, :role, :categoria, :taller, :area_interna, :calidad_laboral, :descanso, :fecha_ingreso, :horario_entrada, :horario_salida)"
            );

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < 11) continue;
                
                $expediente = trim($data[0]);
                if (empty($expediente)) continue;

                if ($record_counter > 0 && $record_counter % $batch_size === 0) {
                    $conn->commit();
                    $conn->beginTransaction();
                }
                
                $nombre_completo   = mb_convert_encoding(trim($data[1]), 'UTF-8', 'Windows-1252');
                $role_from_csv     = trim($data[3]);
                $categoria         = mb_convert_encoding(trim($data[4]), 'UTF-8', 'Windows-1252');
                $taller            = mb_convert_encoding(trim($data[5]), 'UTF-8', 'Windows-1252');
                $area_interna      = mb_convert_encoding(trim($data[6]), 'UTF-8', 'Windows-1252');
                $calidad_laboral   = mb_convert_encoding(trim($data[7]), 'UTF-8', 'Windows-1252');
                $descanso          = mb_convert_encoding(trim($data[8]), 'UTF-8', 'Windows-1252');
                $horario_completo  = trim($data[9]);
                $fecha_ingreso_str = trim($data[10]);

                $horarios = preg_split('/[\s]*[a-][\s]*/i', $horario_completo);
                $horarios = array_map('trim', $horarios);
                $horario_entrada = !empty($horarios[0]) ? date("H:i:s", strtotime($horarios[0])) : null;
                $horario_salida = (isset($horarios[1]) && !empty($horarios[1])) ? date("H:i:s", strtotime($horarios[1])) : null;

                $fecha_ingreso = null;
                if (!empty($fecha_ingreso_str)) {
                    $date_obj = DateTime::createFromFormat('d/m/Y', $fecha_ingreso_str) ?: DateTime::createFromFormat('Y-m-d', $fecha_ingreso_str);
                    if ($date_obj) $fecha_ingreso = $date_obj->format('Y-m-d');
                }

                $stmt_select->bindParam(':expediente', $expediente);
                $stmt_select->execute();
                $user_exists = $stmt_select->fetch(PDO::FETCH_ASSOC);

                $hashed_password = password_hash($expediente, PASSWORD_DEFAULT);
                $role_to_use = (!empty($role_from_csv) && is_numeric($role_from_csv) && $role_from_csv >= 1 && $role_from_csv <= 5) ? (int)$role_from_csv : 1;

                if ($user_exists) {
                    $stmt_update->execute([
                        ':expediente' => $expediente, ':nombre_completo' => $nombre_completo, ':password' => $hashed_password,
                        ':role' => $role_to_use, ':categoria' => $categoria, ':taller' => $taller, ':area_interna' => $area_interna,
                        ':calidad_laboral' => $calidad_laboral, ':descanso' => $descanso, ':horario_entrada' => $horario_entrada,
                        ':horario_salida' => $horario_salida, ':fecha_ingreso' => $fecha_ingreso
                    ]);
                    $updated_count++;
                } else {
                    $stmt_insert->execute([
                        ':expediente' => $expediente, ':nombre_completo' => $nombre_completo, ':password' => $hashed_password,
                        ':role' => $role_to_use, ':categoria' => $categoria, ':taller' => $taller, ':area_interna' => $area_interna,
                        ':calidad_laboral' => $calidad_laboral, ':descanso' => $descanso, ':fecha_ingreso' => $fecha_ingreso,
                        ':horario_entrada' => $horario_entrada, ':horario_salida' => $horario_salida
                    ]);
                    $inserted_count++;
                }
                $record_counter++;
            }
            
            $conn->commit();
            $message = "Proceso completado. Registros actualizados: $updated_count. Registros nuevos: $inserted_count. Todas las contraseñas han sido reseteadas y hasheadas.";

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "Error en la transacción: " . $e->getMessage();
        } finally {
            fclose($handle);
        }
    } else {
        $error = "No se pudo abrir el archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es"><head><title>Importar Personal</title><link rel="stylesheet" href="style.css"><style>body{font-family:Arial,sans-serif;background-color:#f4f4f4;padding:20px}.container{max-width:800px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.1)}.form-upload{border:2px dashed #ccc;padding:20px;text-align:center;margin-top:20px}.form-upload button{padding:10px 20px;background-color:#007bff;color:#fff;border:none;border-radius:5px;cursor:pointer}.message,.error,.info{padding:15px;margin-top:20px;border-radius:5px}.message{background-color:#d4edda;color:#155724}.error{background-color:#f8d7da;color:#721c24}.info{background-color:#e2e3e5;color:#383d41}code{background-color:#d6d8db;padding:2px 4px;border-radius:3px}</style></head><body><div class="container"><h1>Importar o Actualizar Personal</h1><p>Sube un archivo .csv para agregar nuevos empleados o actualizar los existentes.</p><div class="info"><strong>¡Atención!</strong> Al subir el archivo, la contraseña de <strong>todos</strong> los usuarios del CSV (nuevos y existentes) se establecerá como su número de expediente.</div><?php if($message):?><div class="message"><?php echo htmlspecialchars($message);?></div><?php endif;?><?php if($error):?><div class="error"><?php echo htmlspecialchars($error);?></div><?php endif;?><form action="importar_personal.php" method="post" enctype="multipart/form-data" class="form-upload"><label for="csv_file">Selecciona el archivo CSV:</label><br><br><input type="file" name="csv_file" id="csv_file" accept=".csv" required><br><br><button type="submit">Subir y Procesar Archivo</button></form></div></body></html>