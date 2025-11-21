<?php
// db_connection.php

// --- CONFIGURACIÓN DE LA BASE DE DATOS PARA XAMPP ---
// --- MODIFICADO PARA USAR NGROK ---
//$servername = "6.tcp.us-cal-1.ngrok.ioo"; // Hostname (dominio) proporcionado por ngrok
//$port = "11124";                // Puerto ALEATORIO asignado por ngrok
$servername = "localhost";     // Hostname para XAMPP
$username = "root";             // Usuario por defecto en XAMPP
$password = "";                 // Contraseña por defecto en XAMPP (vacía)
$dbname = "metro-dmmr";         // Nombre de tu base de datos
// --- CREAR LA CONEXIÓN ---
try {
    // Se añade el puerto a la cadena de conexión PDO
    //$conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Para la terminal, es mejor mostrar un error directo.
    if (php_sapi_name() === 'cli') {
        echo "Error de Conexión: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de Conexión: " . $e->getMessage()]);
    }
    exit();
}
?>