<?php
require_once "../conexion.php";
header('Content-Type: application/json');

$res = $conn->query("SELECT * FROM caja WHERE estado='ABIERTA' ORDER BY id DESC LIMIT 1");
if (!$res || $res->num_rows == 0) {
    echo json_encode(['error' => 'No hay caja abierta']);
    exit;
}

$caja = $res->fetch_assoc();
$id = $caja['id'];
$fecha_ap = $caja['fecha_apertura'];
$date = date('Y-m-d', strtotime($fecha_ap));

$res2 = $conn->query("SELECT COALESCE(SUM(monto),0) as total FROM venta WHERE fecha >= '$date'");
$row = $res2->fetch_assoc();
$totalVentas = floatval($row['total']);
$monto_final = floatval($caja['monto_inicial']) + $totalVentas;

$stmt = $conn->prepare("UPDATE caja SET fecha_cierre=NOW(), monto_final=?, estado='CERRADA' WHERE id=?");
$stmt->bind_param("di", $monto_final, $id);
if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Caja cerrada', 'monto_final' => number_format($monto_final, 2)]);
} else {
    echo json_encode(['error' => $conn->error]);
}
