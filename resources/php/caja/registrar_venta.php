<?php
require_once "../conexion.php";
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$monto = isset($data['monto']) ? floatval($data['monto']) : 0.00;

if ($monto <= 0) {
	echo json_encode(['error' => 'Monto inválido']);
	exit;
}

// guardar venta
$stmt = $conn->prepare("INSERT INTO venta (fecha, monto) VALUES (CURDATE(), ?)");
$stmt->bind_param("d", $monto);
if ($stmt->execute()) {
	$ventaId = $conn->insert_id;
	echo json_encode(['ok' => true, 'msg' => 'Venta registrada. Total: S/ ' . number_format($monto, 2), 'ventaId' => $ventaId]);
} else {
	echo json_encode(['error' => $conn->error]);
}
