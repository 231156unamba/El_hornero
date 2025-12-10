<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';

// Devolver el menú desde la BD
try {
    // Verificar conexión
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión a la BD: ' . $conn->connect_error]);
        exit;
    }

    $sql = "SELECT id, nombre, precio, descripcion, imagen FROM menu ORDER BY id";
    $result = $conn->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la consulta: ' . $conn->error]);
        exit;
    }

    $menu = [];
    while($row = $result->fetch_assoc()) {
        // Asegurar tipos de dato correctos
        $menu[] = [
            'id' => (int)$row['id'],
            'nombre' => (string)$row['nombre'],
            'precio' => (float)$row['precio'],
            'descripcion' => (string)$row['descripcion'],
            'imagen' => (string)$row['imagen']
        ];
    }

    http_response_code(200);
    echo json_encode($menu, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Excepción: ' . $e->getMessage()]);
}
?>
