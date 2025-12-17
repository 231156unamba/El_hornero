<?php
require_once "../conexion.php";
header('Content-Type: application/json');

$res = $conn->query("SELECT * FROM venta ORDER BY id DESC LIMIT 1");
if (!$res || $res->num_rows == 0) {
    echo json_encode(['error' => 'No hay ventas']);
    exit;
}

$venta = $res->fetch_assoc();
$ventaId = intval($venta['id']);
$total = floatval($venta['monto']);
$subtotal = round($total / 1.18, 2);
$igv = round($total - $subtotal, 2);
$numero = 'R' . date('Ymd') . str_pad($ventaId, 6, '0', STR_PAD_LEFT);

$stmt = $conn->prepare("INSERT INTO recibo (venta_id, numero, subtotal, igv, total, tipo, estado_sunat) VALUES (?, ?, ?, ?, ?, 'BOLETA', 'PENDIENTE')");
$stmt->bind_param("isddd", $ventaId, $numero, $subtotal, $igv, $total);
if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Recibo generado', 'recibo_id' => $conn->insert_id, 'numero' => $numero, 'subtotal' => $subtotal, 'igv' => $igv, 'total' => $total]);
} else {
    echo json_encode(['error' => $conn->error]);
}
