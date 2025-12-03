<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}
$sql = "SELECT * FROM menu";
$result = $conn->query($sql);

$menu = [];
while($row = $result->fetch_assoc()) {
    $menu[] = $row;
}
echo json_encode($menu);
?>
