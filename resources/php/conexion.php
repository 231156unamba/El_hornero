<?php
$host = "localhost";
$user = "root"; // Cambia si tu usuario es diferente
$pass = "75804805"; // Cambia si tienes contraseña
$db = "el_hornero";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
