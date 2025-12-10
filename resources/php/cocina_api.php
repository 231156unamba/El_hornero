<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] !== 'cocina') {
    echo json_encode([]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $estado = $_POST['estado'];
    $sql = "UPDATE pedido SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $estado, $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
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
