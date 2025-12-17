<?php
require_once "../conexion.php";
header('Content-Type: application/json');

// verificar si hay caja abierta
$check = $conn->query("SELECT id FROM caja WHERE estado = 'ABIERTA' LIMIT 1");
if ($check && $check->num_rows > 0) {
    echo json_encode(['error' => 'Ya existe una caja abierta']);
    exit;
}

$montoInicial = isset($_POST['monto']) ? floatval($_POST['monto']) : 100.00;

$stmt = $conn->prepare("INSERT INTO caja (fecha_apertura, monto_inicial, estado) VALUES (NOW(), ?, 'ABIERTA')");
$stmt->bind_param("d", $montoInicial);
if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Caja abierta correctamente', 'id' => $conn->insert_id]);
} else {
    echo json_encode(['error' => $conn->error]);
}
