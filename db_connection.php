<?php
// db_connection.php

// --- CONFIGURACIÓN DE LA BASE DE DATOS PARA NGROK ---
// IMPORTANTE: Estos datos probablemente son incorrectos.
// Debes reiniciar el túnel de ngrok para la base de datos (ngrok tcp 3306)
// y reemplazar $servername y $port con la nueva información.
//$servername = "2.tcp.us-cal-1.ngrok.io"; // ACTUALIZADO
$servername = "localhost";
//$port = "13236";   
$port = "3306";                    // ACTUALIZADO
$username = "usuario_app";
$password = "Luna022495";
$dbname = "metro-dmmr";



// --- CREAR LA CONEXIÓN ---
try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Devolver un error en formato JSON, como espera el frontend (login.js)
    http_response_code(500); // Internal Server Error
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Error de Conexión a la Base de Datos: " . $e->getMessage()]);
    exit();
}
?>