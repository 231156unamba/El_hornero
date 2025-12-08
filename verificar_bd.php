<?php
header('Content-Type: text/html; charset=utf-8');
include 'resources/js/conexion.php';

echo "<h1>🔍 Verificación de Base de Datos</h1>";

// Verificar conexión
if ($conn->connect_error) {
    echo "<p style='color:red;font-weight:bold'>❌ Error de conexión: " . $conn->connect_error . "</p>";
    exit;
}
echo "<p style='color:green;font-weight:bold'>✅ Conexión exitosa a: " . $conn->get_charset()->charset . "</p>";

// Verificar tabla menu
echo "<h2>📋 Tabla MENU</h2>";
$sql = "SELECT * FROM menu";
$result = $conn->query($sql);

if (!$result) {
    echo "<p style='color:red'>❌ Error en tabla menu: " . $conn->error . "</p>";
} else {
    $count = $result->num_rows;
    echo "<p>Total de platos: <strong>$count</strong></p>";
    if ($count > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse'>";
        echo "<tr style='background:#ff6f00;color:white'><th>ID</th><th>Nombre</th><th>Precio</th><th>Descripción</th></tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['nombre'] . "</td>";
            echo "<td>S/ " . $row['precio'] . "</td>";
            echo "<td>" . substr($row['descripcion'], 0, 50) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange'>⚠️ La tabla menu está vacía. Necesitas insertar datos.</p>";
    }
}

// Verificar tabla usuarios
echo "<h2>👥 Tabla USUARIOS</h2>";
$sql = "SELECT id, usuario, tipo FROM usuarios";
$result = $conn->query($sql);

if (!$result) {
    echo "<p style='color:red'>❌ Error en tabla usuarios: " . $conn->error . "</p>";
} else {
    $count = $result->num_rows;
    echo "<p>Total de usuarios: <strong>$count</strong></p>";
    if ($count > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse'>";
        echo "<tr style='background:#ff6f00;color:white'><th>ID</th><th>Usuario</th><th>Tipo</th></tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['usuario'] . "</td>";
            echo "<td>" . $row['tipo'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange'>⚠️ La tabla usuarios está vacía.</p>";
    }
}

// Verificar tabla pedido
echo "<h2>🍽️ Tabla PEDIDO</h2>";
$sql = "SELECT * FROM pedido LIMIT 5";
$result = $conn->query($sql);

if (!$result) {
    echo "<p style='color:red'>❌ Error en tabla pedido: " . $conn->error . "</p>";
} else {
    $count = $result->num_rows;
    echo "<p>Total de pedidos (últimos 5 mostrados): <strong>$count</strong></p>";
    if ($count > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse'>";
        echo "<tr style='background:#ff6f00;color:white'><th>ID</th><th>Mesa</th><th>Detalle</th><th>Estado</th></tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['mesa'] . "</td>";
            echo "<td>" . substr($row['detalle'], 0, 30) . "...</td>";
            echo "<td>" . $row['estado'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:blue'>ℹ️ No hay pedidos aún.</p>";
    }
}

// Test directo a menu_api.php
echo "<h2>🔗 Test de menu_api.php</h2>";
echo "<p><strong>URL:</strong> ./resources/js/menu_api.php</p>";
echo "<p>Intenta acceder desde JavaScript. Abre la consola (F12) en el navegador.</p>";

?>
