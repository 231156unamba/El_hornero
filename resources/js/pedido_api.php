<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
session_start();

// POST: crear pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mesa = isset($_POST['mesa']) ? (int)$_POST['mesa'] : null;
    $detalle = isset($_POST['detalle']) ? $_POST['detalle'] : null;
    
    if (!$mesa || !$detalle) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Mesa y detalle son requeridos']);
        exit;
    }
    
    $sql = "INSERT INTO pedido (mesa, detalle, estado, fecha) VALUES (?, ?, 'pedido', NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error en preparación: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('is', $mesa, $detalle);
    $ok = $stmt->execute();
    
    if ($ok) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al insertar: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// GET: devolver lista de pedidos
$sql = "SELECT id, mesa, detalle, estado, fecha FROM pedido ORDER BY fecha DESC";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en consulta: ' . $conn->error]);
    exit;
}

$pedidos = [];
while($row = $result->fetch_assoc()) {
    $pedidos[] = [
        'id' => (int)$row['id'],
        'mesa' => (int)$row['mesa'],
        'detalle' => (string)$row['detalle'],
        'estado' => (string)$row['estado'],
        'fecha' => (string)$row['fecha']
    ];
}

http_response_code(200);
echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
?>
