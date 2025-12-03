<?php
include 'conexion.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];
    $sql = "SELECT * FROM usuarios WHERE usuario = ? AND clave = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $usuario, $clave);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['usuario'] = $row['usuario'];
        $_SESSION['tipo'] = $row['tipo'];
        echo json_encode(['success' => true, 'tipo' => $row['tipo']]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
