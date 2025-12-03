<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mesa = $_POST['mesa'];
    $detalle = $_POST['detalle'];
    $sql = "INSERT INTO pedido (mesa, detalle, estado) VALUES (?, ?, 'pedido')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $mesa, $detalle);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit;
}

$sql = "SELECT * FROM pedido ORDER BY fecha DESC";
$result = $conn->query($sql);
$pedidos = [];
while($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}
echo json_encode($pedidos);
?>
