SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS sibi_biblioteca
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sibi_biblioteca;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS prestamos;
DROP TABLE IF EXISTS ejemplares;
DROP TABLE IF EXISTS libros;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS autores;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracion;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Configuracion de parametros institucionales
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dias_prestamo INT NOT NULL DEFAULT 7,
    tarifa_mora_diaria DECIMAL(6,2) NOT NULL DEFAULT 1.50,
    max_renovaciones INT NOT NULL DEFAULT 2,
    moneda VARCHAR(10) NOT NULL DEFAULT 'S/'
) ENGINE=InnoDB;

-- 2. Usuarios (administrador, bibliotecario, lector)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    dni VARCHAR(15) NOT NULL UNIQUE,
    correo VARCHAR(120) NOT NULL UNIQUE,
    telefono VARCHAR(20) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('administrador','bibliotecario','lector') NOT NULL DEFAULT 'lector',
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Autores
CREATE TABLE autores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL UNIQUE,
    nacionalidad VARCHAR(80) DEFAULT NULL
) ENGINE=InnoDB;

-- 4. Categorias
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 5. Libros (titulos bibliograficos)
CREATE TABLE libros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    isbn VARCHAR(25) DEFAULT NULL,
    editorial VARCHAR(120) DEFAULT NULL,
    anio_publicacion SMALLINT DEFAULT NULL,
    autor_id INT NOT NULL,
    categoria_id INT NOT NULL,
    sinopsis TEXT DEFAULT NULL,
    portada VARCHAR(255) NOT NULL DEFAULT 'assets/img/portada-default.jpg',
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_libros_autor FOREIGN KEY (autor_id) REFERENCES autores(id),
    CONSTRAINT fk_libros_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

-- 6. Ejemplares fisicos (copias unicas)
CREATE TABLE ejemplares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libro_id INT NOT NULL,
    codigo_ejemplar VARCHAR(20) NOT NULL UNIQUE,
    estado ENUM('disponible','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ejemplares_libro FOREIGN KEY (libro_id) REFERENCES libros(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Prestamos y devoluciones
CREATE TABLE prestamos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ejemplar_id INT NOT NULL,
    lector_id INT NOT NULL,
    bibliotecario_id INT NOT NULL,
    fecha_prestamo DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    fecha_devolucion DATE DEFAULT NULL,
    renovaciones INT NOT NULL DEFAULT 0,
    mora_monto DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    mora_pagada TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('activo','devuelto','vencido') NOT NULL DEFAULT 'activo',
    observaciones VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_prestamos_ejemplar FOREIGN KEY (ejemplar_id) REFERENCES ejemplares(id),
    CONSTRAINT fk_prestamos_lector FOREIGN KEY (lector_id) REFERENCES usuarios(id),
    CONSTRAINT fk_prestamos_biblio FOREIGN KEY (bibliotecario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 8. Auditoria de acciones
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(80) NOT NULL,
    detalle VARCHAR(255) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- DATOS INICIALES (clave de prueba: 123456 en bcrypt $2y$10$ verificado)
-- =====================================================================
INSERT INTO configuracion (dias_prestamo, tarifa_mora_diaria, max_renovaciones, moneda)
VALUES (7, 1.50, 2, 'S/');

INSERT INTO usuarios (nombres, apellidos, dni, correo, telefono, password_hash, rol, estado) VALUES
('Ana', 'Torres Medina', '71001001', 'admin@sibi.edu.pe', '958100100', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'administrador', 1),
('Carlos', 'Quispe Ramos', '71001002', 'biblio@sibi.edu.pe', '958200200', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'bibliotecario', 1),
('Mateo', 'Flores Vargas', '74521001', 'mateo@sibi.edu.pe', '951300301', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'lector', 1),
('Valeria', 'Ccahuana Díaz', '74521002', 'valeria@sibi.edu.pe', '951300302', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'lector', 1),
('Diego', 'Salazar Huamán', '74521003', 'diego@sibi.edu.pe', '951300303', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'lector', 1),
('Sofía', 'Paredes Condori', '74521004', 'sofia@sibi.edu.pe', '951300304', '$2y$10$B78Ing1r7z2wAB4LNUBepubk6saF92GSjCS5LZ5hXrCdYyD82Q8we', 'lector', 1);

INSERT INTO autores (nombre, nacionalidad) VALUES
('Robert C. Martin', 'Estados Unidos'),
('Abraham Silberschatz', 'Estados Unidos'),
('Marijn Haverbeke', 'Países Bajos'),
('Idalberto Chiavenato', 'Brasil'),
('Philip Kotler', 'Estados Unidos'),
('Mario Vargas Llosa', 'Perú');

INSERT INTO categorias (nombre) VALUES
('Tecnología'),
('Bases de Datos'),
('Administración'),
('Literatura'),
('Ciencia');

INSERT INTO libros (titulo, isbn, editorial, anio_publicacion, autor_id, categoria_id, sinopsis, portada) VALUES
('Código Limpio', '978-0132350884', 'Anaya Multimedia', 2012, 1, 1, 'Manual práctico de principios, patrones y buenas prácticas para escribir software mantenible, legible y libre de deuda técnica.', 'assets/img/portada-1.jpg'),
('Fundamentos de Bases de Datos', '978-8448190330', 'McGraw-Hill', 2014, 2, 2, 'Obra de referencia sobre diseño relacional, normalización, álgebra relacional, SQL avanzado, transacciones y concurrencia.', 'assets/img/portada-2.jpg'),
('Eloquent JavaScript', '978-1593279509', 'No Starch Press', 2019, 3, 1, 'Introducción moderna a la programación en JavaScript, programación funcional, asincronía, manejo del DOM y aplicaciones web.', 'assets/img/portada-3.jpg'),
('Teoría General de la Administración', '978-6071509802', 'McGraw-Hill', 2019, 4, 3, 'Recorrido completo por los enfoques clásicos, humanistas, estructuralistas y sistémicos de la administración moderna.', 'assets/img/portada-4.jpg'),
('Fundamentos de Marketing', '978-6073238458', 'Pearson Educación', 2017, 5, 3, 'Estrategias de posicionamiento, comportamiento del consumidor, propuesta de valor y marketing digital orientado a resultados.', 'assets/img/portada-5.jpg'),
('La ciudad y los perros', '978-8420412146', 'Alfaguara', 2012, 6, 4, 'Novela emblemática de la literatura peruana contemporánea ambientada en el Colegio Militar Leoncio Prado de Lima.', 'assets/img/portada-6.jpg'),
('Arquitectura Limpia', '978-0134494166', 'Anaya Multimedia', 2018, 1, 1, 'Guía definitiva sobre principios SOLID, desacoplamiento de componentes y diseño sostenible de sistemas empresariales.', 'assets/img/portada-7.jpg'),
('Gestión del Talento Humano', '978-6071514493', 'McGraw-Hill', 2020, 4, 5, 'Enfoque estratégico para reclutamiento, desarrollo de competencias, cultura organizacional y evaluación del desempeño.', 'assets/img/portada-8.jpg');

INSERT INTO ejemplares (libro_id, codigo_ejemplar, estado) VALUES
(1, 'REC-PRG-001', 'disponible'),
(1, 'REC-PRG-002', 'prestado'),
(2, 'REC-BDD-001', 'disponible'),
(2, 'REC-BDD-002', 'prestado'),
(3, 'REC-PRG-003', 'disponible'),
(4, 'REC-ADM-001', 'disponible'),
(5, 'REC-ADM-002', 'disponible'),
(6, 'REC-LIT-001', 'disponible'),
(7, 'REC-PRG-004', 'disponible'),
(8, 'REC-ADM-003', 'mantenimiento');

INSERT INTO prestamos (ejemplar_id, lector_id, bibliotecario_id, fecha_prestamo, fecha_vencimiento, fecha_devolucion, renovaciones, mora_monto, mora_pagada, estado, observaciones) VALUES
(2, 3, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, 0, 0.00, 0, 'activo', 'Ejemplar entregado en óptimo estado.'),
(4, 4, 2, DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), NULL, 0, 6.00, 0, 'vencido', 'Préstamo con retraso pendiente de devolución.'),
(1, 5, 2, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 0, 0.00, 1, 'devuelto', 'Devuelto a tiempo sin observaciones.');

INSERT INTO auditoria (usuario_id, accion, detalle) VALUES
(1, 'INICIO_SISTEMA', 'Carga inicial de catálogo, ejemplares y usuarios de SIBI IEST La Recoleta.');
