<?php
require_once "../conexion.php";
header('Content-Type: application/json');

$res = $conn->query("SELECT * FROM recibo WHERE estado_sunat='PENDIENTE' ORDER BY id DESC LIMIT 1");
if (!$res || $res->num_rows == 0) {
    echo json_encode(['error' => 'No hay recibos pendientes']);
    exit;
}

$r = $res->fetch_assoc();
$rid = intval($r['id']);

// Simular envío a SUNAT
$stmt = $conn->prepare("UPDATE recibo SET estado_sunat='ENVIADO' WHERE id=?");
$stmt->bind_param("i", $rid);
if ($stmt->execute()) {
    $resp = 'ENVIADO';
    $stmt2 = $conn->prepare("INSERT INTO sunat_log (recibo_id, respuesta) VALUES (?, ?)");
    $stmt2->bind_param("is", $rid, $resp);
    $stmt2->execute();
    echo json_encode(['ok' => true, 'msg' => 'Recibo enviado a SUNAT', 'recibo_id' => $rid]);
} else {
    echo json_encode(['error' => $conn->error]);
}
