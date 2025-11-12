<?php
// db_connection.php

// --- CONFIGURACIÓN DE LA BASE DE DATOS PARA XAMPP ---
$servername = "localhost";
$username = "root";       // Usuario por defecto en XAMPP
$password = "";          // Contraseña por defecto en XAMPP (vacía)
$dbname = "metro-dmmr"; // Nombre de tu base de datos

// --- CREAR LA CONEXIÓN ---
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    // Modificamos la línea de abajo para que muestre el error real de MySQL
    echo json_encode(["success" => false, "message" => "Error de Conexión: " . $e->getMessage()]);
    exit();
}
?>