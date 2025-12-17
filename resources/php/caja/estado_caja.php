<?php
require_once "../conexion.php";
header('Content-Type: application/json');

$res = $conn->query("SELECT * FROM caja ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $r = $res->fetch_assoc();
    echo json_encode(['estado' => $r['estado'], 'id' => $r['id'], 'monto_inicial' => floatval($r['monto_inicial'])]);
} else {
    echo json_encode(['estado' => null]);
}
