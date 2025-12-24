-- =========================
-- BASE DE DATOS
-- =========================
CREATE DATABASE IF NOT EXISTS el_hornero;
USE el_hornero;

-- =========================
-- TABLAS
-- =========================

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(100) NOT NULL,
    tipo ENUM('admin','cocina','pedido','caja') NOT NULL
);

CREATE TABLE menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(8,2) NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255)
);

CREATE TABLE caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_apertura DATETIME NOT NULL,
    fecha_cierre DATETIME,
    monto_inicial DECIMAL(8,2) NOT NULL,
    monto_final DECIMAL(8,2),
    estado ENUM('ABIERTA','CERRADA') DEFAULT 'ABIERTA'
);

CREATE TABLE venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    monto DECIMAL(8,2) NOT NULL
);

CREATE TABLE pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mesa INT NOT NULL,
    detalle TEXT,
    estado ENUM('pedido','preparado','entregado') DEFAULT 'pedido',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venta_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    menu_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES venta(id),
    FOREIGN KEY (menu_id) REFERENCES menu(id)
);

CREATE TABLE recibo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    numero VARCHAR(20) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    igv DECIMAL(8,2) NOT NULL,
    total DECIMAL(8,2) NOT NULL,
    tipo ENUM('BOLETA','FACTURA') DEFAULT 'BOLETA',
    estado_sunat ENUM('PENDIENTE','ENVIADO','RECHAZADO') DEFAULT 'PENDIENTE',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES venta(id)
);

CREATE TABLE sunat_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recibo_id INT NOT NULL,
    respuesta VARCHAR(50),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recibo_id) REFERENCES recibo(id)
);

-- =========================
-- USUARIOS DE PRUEBA
-- =========================

INSERT INTO usuarios (usuario, clave, tipo) VALUES
('admin', 'admin123', 'admin'),
('cocinero', 'cocina123', 'cocina'),
('mesero', 'pedido123', 'pedido'),
('cajero', 'caja123', 'caja');

-- =========================
-- MENÚ POLLERÍA (INSERCIÓN)
-- =========================

INSERT INTO menu (nombre, precio, descripcion, imagen) VALUES
('Pollo entero', 65.00, '1 pollo entero con papas y ensalada', NULL),
('Medio pollo', 35.00, '1/2 pollo con papas y ensalada', NULL),
('Cuarto de pollo', 20.00, '1/4 de pollo con papas', NULL),
('Octavo de pollo', 12.00, '1/8 de pollo ideal para un monstrito', NULL);

-- =========================
-- VENTAS DE PRUEBA
-- =========================

INSERT INTO venta (fecha, monto) VALUES
(CURDATE(), 65.00),
(CURDATE(), 35.00),
(CURDATE(), 20.00);
