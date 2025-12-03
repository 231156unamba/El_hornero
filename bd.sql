-- Base de datos para restaurante El Hornero
CREATE DATABASE IF NOT EXISTS el_hornero;
USE el_hornero;

-- Tabla de menú
CREATE TABLE menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(8,2) NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255)
);

-- Tabla de pedidos
CREATE TABLE pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mesa INT NOT NULL,
    detalle TEXT,
    estado ENUM('pedido','preparado','entregado') DEFAULT 'pedido',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    monto DECIMAL(8,2) NOT NULL
);

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(100) NOT NULL,
    tipo ENUM('admin','cocina','pedido') NOT NULL
);

-- Insertar usuarios de ejemplo
INSERT INTO usuarios (usuario, clave, tipo) VALUES
('admin', 'admin123', 'admin'),
('cocinero', 'cocina123', 'cocina'),
('mesero', 'pedido123', 'pedido');

-- Insertar datos de ejemplo en menú
INSERT INTO menu (nombre, precio, descripcion, imagen) VALUES
('Pizza grande', 35.00, 'Pizza familiar con ingredientes frescos.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591'),
('Lomo saltado', 28.00, 'Plato tradicional peruano.', 'https://images.unsplash.com/photo-1504674900247-0877df9cc836'),
('Ensalada César', 18.00, 'Ensalada fresca con pollo y aderezo César.', 'https://images.unsplash.com/photo-1464306076886-debede6b0df1');

-- Insertar datos de ejemplo en ventas
INSERT INTO venta (fecha, monto) VALUES
('2025-12-01', 320.00),
('2025-12-02', 410.00),
('2025-12-03', 380.00),
('2025-11-01', 500.00),
('2025-11-02', 450.00),
('2025-10-01', 600.00),
('2025-09-01', 700.00),
('2025-08-01', 650.00),
('2025-07-01', 480.00),
('2025-06-01', 520.00),
('2025-05-01', 610.00),
('2025-04-01', 720.00);
