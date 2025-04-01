-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 30-03-2025 a las 13:55:34
-- Versión del servidor: 10.11.11-MariaDB
-- Versión de PHP: 8.3.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mar27ked_cotizaciones`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `razon_social` varchar(100) NOT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contacto_nombre` varchar(100) DEFAULT NULL,
  `contacto_cargo` varchar(50) DEFAULT NULL,
  `contacto_telefono` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `tipo_documento` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `razon_social`, `ruc`, `direccion`, `telefono`, `email`, `contacto_nombre`, `contacto_cargo`, `contacto_telefono`, `fecha_registro`, `estado`, `tipo_documento`) VALUES
(1, 'COMERCIAL ABC SAC', '20111222333', 'Av. Arequipa 450, Lima', '01-333-4444', 'compras@abccomercial.com', 'Roberto Torres', 'Jefe de Compras', '998877665', '2025-03-27 23:34:45', 'activo', NULL),
(2, 'INDUSTRIAS XYZ EIRL', '20444555666', 'Jr. Huallaga 789, Lima', '01-555-6666', 'logistica@xyz.com.pe', 'Carmen Vargas', 'Gerente de Logística', '976543210', '2025-03-27 23:34:45', 'activo', NULL),
(3, 'SUPERMERCADOS NORTE SA', '20777888999', 'Av. La Marina 1234, San Miguel', '01-777-8888', 'proveedores@supernorte.com', 'Diego Sánchez', 'Coordinador de Proveedores', '945678123', '2025-03-27 23:34:45', 'activo', NULL),
(4, 'prueba', '123', 'dsa', '321', 'ds@sad.sd', NULL, NULL, NULL, '2025-03-28 17:35:17', 'activo', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id_cotizacion` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL COMMENT 'Código único de la cotización',
  `id_cliente` int(11) NOT NULL,
  `fecha_cotizacion` date NOT NULL,
  `validez` int(11) NOT NULL DEFAULT 15 COMMENT 'Días de validez',
  `condiciones_pago` varchar(100) DEFAULT NULL,
  `tiempo_entrega` varchar(100) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que genera la cotización',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','aprobada','rechazada','convertida','vencida') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_modificacion` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_detalles`
--

CREATE TABLE `cotizacion_detalles` (
  `id_detalle` int(11) NOT NULL,
  `id_cotizacion` int(11) NOT NULL,
  `id_material` int(11) NOT NULL,
  `ancho` decimal(10,2) NOT NULL COMMENT 'cm',
  `largo` decimal(10,2) NOT NULL COMMENT 'cm',
  `micraje` decimal(10,2) NOT NULL COMMENT 'micras',
  `fuelle` decimal(10,2) DEFAULT 0.00 COMMENT 'cm',
  `colores` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de colores',
  `color_texto` varchar(50) DEFAULT NULL,
  `biodegradable` tinyint(1) DEFAULT 0,
  `cantidad` int(11) NOT NULL,
  `costo_unitario` decimal(12,4) NOT NULL COMMENT 'Costo por unidad',
  `precio_unitario` decimal(12,2) NOT NULL COMMENT 'Precio de venta por unidad',
  `subtotal` decimal(12,2) NOT NULL COMMENT 'precio_unitario * cantidad',
  `peso_unitario` decimal(10,4) DEFAULT NULL COMMENT 'kg por unidad',
  `notas_tecnicas` text DEFAULT NULL,
  `espesor` varchar(50) DEFAULT NULL,
  `medida_referencial` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_proformas`
--

CREATE TABLE `historial_proformas` (
  `id_historial` int(11) NOT NULL,
  `id_proforma` int(11) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `detalles` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id_material` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('R1','R2','Virgen') NOT NULL,
  `color` enum('Negro','Colores','Transparente') NOT NULL DEFAULT 'Negro',
  `biodegradable` tinyint(1) DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id_material`, `codigo`, `nombre`, `tipo`, `color`, `biodegradable`, `descripcion`, `estado`) VALUES
(1, 'PE-R1', 'Polietileno R1', 'R1', 'Negro', 0, 'Material reciclado de primera calidad', 'activo'),
(2, 'PE-R2', 'Polietileno R2', 'R2', 'Negro', 0, 'Material reciclado de segunda calidad', 'activo'),
(3, 'PE-V', 'Polietileno Virgen', 'Virgen', 'Transparente', 0, 'Material virgen de alta calidad', 'activo'),
(4, 'PE-BIO', 'Aditivo Biodegradable', 'Virgen', 'Transparente', 1, 'Aditivo para degradar el polietileno', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medidas_estandar`
--

CREATE TABLE `medidas_estandar` (
  `id_medida` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL COMMENT 'Ejemplo: 25 LTS, 50 LTS',
  `ancho` decimal(10,2) DEFAULT NULL COMMENT 'pulgadas',
  `largo` decimal(10,2) DEFAULT NULL COMMENT 'pulgadas',
  `micraje_recomendado` decimal(10,2) NOT NULL COMMENT 'micras',
  `fuelle` decimal(10,2) DEFAULT 0.00 COMMENT 'pulgadas',
  `peso_aproximado` decimal(10,4) DEFAULT NULL COMMENT 'kg',
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `medidas_estandar`
--

INSERT INTO `medidas_estandar` (`id_medida`, `nombre`, `ancho`, `largo`, `micraje_recomendado`, `fuelle`, `peso_aproximado`, `descripcion`, `estado`) VALUES
(1, '25 LTS', 20.00, 20.00, 2.00, 0.00, NULL, 'Bolsa para 25 litros', 'activo'),
(2, '35 LTS', 24.00, 25.00, 2.00, 0.00, NULL, 'Bolsa para 35 litros', 'activo'),
(3, '50 LTS', 24.00, 27.00, 2.50, 0.00, NULL, 'Bolsa para 50 litros', 'activo'),
(4, '75 LTS', 27.00, 34.00, 2.50, 0.00, NULL, 'Bolsa para 75 litros', 'activo'),
(5, '100 LTS', 33.00, 41.00, 3.00, 0.00, NULL, 'Bolsa para 100 litros', 'activo'),
(6, '120 LTS', 32.00, 43.00, 3.00, 0.00, NULL, 'Bolsa para 120 litros', 'activo'),
(9, '140 LTS', 34.00, 39.00, 3.00, 0.00, NULL, 'Bolsa para 140 litros', 'activo'),
(10, '180 LTS', 34.00, 47.00, 3.50, 0.00, NULL, 'Bolsa para 180 litros', 'activo'),
(11, '200 LTS', 35.00, 50.00, 3.50, 0.00, NULL, 'Bolsa para 200 litros', 'activo'),
(12, '220 LTS', 35.00, 52.00, 4.00, 0.00, NULL, 'Bolsa para 220 litros', 'activo'),
(13, '240 LTS', 38.00, 56.00, 4.00, 0.00, NULL, 'Bolsa para 240 litros', 'activo'),
(14, '260 LTS', 40.00, 60.00, 4.00, 0.00, NULL, 'Bolsa para 260 litros', 'activo'),
(16, '300 LTS', 45.00, 62.00, 4.50, 0.00, NULL, 'Bolsa para 300 litros', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_venta`
--

CREATE TABLE `ordenes_venta` (
  `id_orden` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `id_proforma` int(11) DEFAULT NULL,
  `id_cliente` int(11) NOT NULL,
  `fecha_emision` datetime NOT NULL,
  `condiciones_pago` varchar(255) NOT NULL,
  `tiempo_entrega` varchar(255) NOT NULL,
  `estado` varchar(20) DEFAULT 'pendiente' COMMENT 'pendiente,en_produccion,completada,cancelada',
  `subtotal` decimal(10,2) NOT NULL,
  `impuestos` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL,
  `fecha_inicio_produccion` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_detalles`
--

CREATE TABLE `orden_detalles` (
  `id_detalle` int(11) NOT NULL,
  `id_orden` int(11) NOT NULL,
  `id_material` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `ancho` decimal(10,2) NOT NULL,
  `largo` decimal(10,2) NOT NULL,
  `micraje` decimal(10,2) NOT NULL,
  `fuelle` decimal(10,2) DEFAULT 0.00,
  `colores` int(11) DEFAULT 0,
  `biodegradable` tinyint(1) DEFAULT 0,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `espesor` varchar(50) DEFAULT NULL,
  `medida_referencial` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_materiales`
--

CREATE TABLE `precios_materiales` (
  `id_precio` int(11) NOT NULL,
  `id_material` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `moneda` enum('PEN','USD') DEFAULT 'PEN',
  `fecha_vigencia` date NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `precios_materiales`
--

INSERT INTO `precios_materiales` (`id_precio`, `id_material`, `id_proveedor`, `precio`, `moneda`, `fecha_vigencia`, `fecha_registro`, `estado`) VALUES
(5, 1, 1, 4.50, 'PEN', '2025-03-01', '2025-03-27 23:34:36', 'activo'),
(6, 2, 1, 3.80, 'PEN', '2025-03-01', '2025-03-27 23:34:36', 'activo'),
(7, 3, 1, 5.20, 'PEN', '2025-03-01', '2025-03-27 23:34:36', 'activo'),
(8, 4, 1, 6.50, 'PEN', '2025-03-01', '2025-03-27 23:34:36', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proformas`
--

CREATE TABLE `proformas` (
  `id_proforma` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL COMMENT 'Código único de la proforma',
  `id_cotizacion` int(11) DEFAULT NULL,
  `id_cliente` int(11) NOT NULL,
  `fecha_emision` date NOT NULL,
  `validez` int(11) NOT NULL DEFAULT 15 COMMENT 'Días de validez',
  `condiciones_pago` varchar(100) DEFAULT NULL,
  `tiempo_entrega` varchar(100) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que genera la proforma',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('emitida','aprobada','rechazada','facturada','vencida') DEFAULT 'emitida',
  `notas` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_modificacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL,
  `id_usuario_aprobacion` int(11) DEFAULT NULL,
  `fecha_rechazo` datetime DEFAULT NULL,
  `id_usuario_rechazo` int(11) DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proforma_detalles`
--

CREATE TABLE `proforma_detalles` (
  `id_detalle` int(11) NOT NULL,
  `id_proforma` int(11) NOT NULL,
  `id_material` int(11) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `ancho` decimal(10,2) NOT NULL COMMENT 'cm',
  `largo` decimal(10,2) NOT NULL COMMENT 'cm',
  `micraje` decimal(10,2) NOT NULL COMMENT 'micras',
  `fuelle` decimal(10,2) DEFAULT 0.00 COMMENT 'cm',
  `colores` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de colores',
  `color_texto` varchar(50) DEFAULT NULL,
  `biodegradable` tinyint(1) DEFAULT 0,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL COMMENT 'Precio de venta por unidad',
  `subtotal` decimal(12,2) NOT NULL COMMENT 'precio_unitario * cantidad',
  `peso_unitario` decimal(10,4) DEFAULT NULL COMMENT 'kg por unidad',
  `espesor` varchar(50) DEFAULT NULL,
  `medida_referencial` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `razon_social` varchar(100) NOT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contacto_nombre` varchar(100) DEFAULT NULL,
  `contacto_telefono` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `razon_social`, `ruc`, `direccion`, `telefono`, `email`, `contacto_nombre`, `contacto_telefono`, `fecha_registro`, `estado`) VALUES
(1, 'PLASTICOS DANIPLAST E.I.R.L.', '20607456209', 'establo valera - puente piedra', '01-614-8900', 'isma7_7@hotmail.com', 'Ismael Durand Huertas', '949161939', '2025-03-27 23:31:54', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','vendedor','gerente') NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `email`, `password`, `rol`, `fecha_registro`, `ultimo_acceso`, `estado`) VALUES
(1, 'Admin', 'Sistema', 'admin@keydansac.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-03-27 23:26:55', '2025-03-30 13:43:25', 'activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`);

--
-- Indices de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id_cotizacion`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_cotizaciones_cliente` (`id_cliente`),
  ADD KEY `idx_cotizaciones_estado` (`estado`),
  ADD KEY `idx_cotizaciones_fecha` (`fecha_cotizacion`);

--
-- Indices de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `idx_cotizacion_detalles_cotizacion` (`id_cotizacion`),
  ADD KEY `idx_cotizacion_detalles_material` (`id_material`);

--
-- Indices de la tabla `historial_proformas`
--
ALTER TABLE `historial_proformas`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_proforma` (`id_proforma`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id_material`);

--
-- Indices de la tabla `medidas_estandar`
--
ALTER TABLE `medidas_estandar`
  ADD PRIMARY KEY (`id_medida`);

--
-- Indices de la tabla `ordenes_venta`
--
ALTER TABLE `ordenes_venta`
  ADD PRIMARY KEY (`id_orden`),
  ADD KEY `id_proforma` (`id_proforma`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `orden_detalles`
--
ALTER TABLE `orden_detalles`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_material` (`id_material`);

--
-- Indices de la tabla `precios_materiales`
--
ALTER TABLE `precios_materiales`
  ADD PRIMARY KEY (`id_precio`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `idx_precios_materiales_material` (`id_material`),
  ADD KEY `idx_precios_materiales_vigencia` (`fecha_vigencia`);

--
-- Indices de la tabla `proformas`
--
ALTER TABLE `proformas`
  ADD PRIMARY KEY (`id_proforma`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_proformas_cotizacion` (`id_cotizacion`),
  ADD KEY `idx_proformas_cliente` (`id_cliente`),
  ADD KEY `idx_proformas_estado` (`estado`);

--
-- Indices de la tabla `proforma_detalles`
--
ALTER TABLE `proforma_detalles`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_proforma` (`id_proforma`),
  ADD KEY `id_material` (`id_material`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=520;

--
-- AUTO_INCREMENT de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id_cotizacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_proformas`
--
ALTER TABLE `historial_proformas`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id_material` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `medidas_estandar`
--
ALTER TABLE `medidas_estandar`
  MODIFY `id_medida` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `ordenes_venta`
--
ALTER TABLE `ordenes_venta`
  MODIFY `id_orden` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orden_detalles`
--
ALTER TABLE `orden_detalles`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `precios_materiales`
--
ALTER TABLE `precios_materiales`
  MODIFY `id_precio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `proformas`
--
ALTER TABLE `proformas`
  MODIFY `id_proforma` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proforma_detalles`
--
ALTER TABLE `proforma_detalles`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `cotizaciones_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `cotizaciones_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD CONSTRAINT `cotizacion_detalles_ibfk_1` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`),
  ADD CONSTRAINT `cotizacion_detalles_ibfk_2` FOREIGN KEY (`id_material`) REFERENCES `materiales` (`id_material`);

--
-- Filtros para la tabla `historial_proformas`
--
ALTER TABLE `historial_proformas`
  ADD CONSTRAINT `historial_proformas_ibfk_1` FOREIGN KEY (`id_proforma`) REFERENCES `proformas` (`id_proforma`),
  ADD CONSTRAINT `historial_proformas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `ordenes_venta`
--
ALTER TABLE `ordenes_venta`
  ADD CONSTRAINT `ordenes_venta_ibfk_1` FOREIGN KEY (`id_proforma`) REFERENCES `proformas` (`id_proforma`),
  ADD CONSTRAINT `ordenes_venta_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `ordenes_venta_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `orden_detalles`
--
ALTER TABLE `orden_detalles`
  ADD CONSTRAINT `orden_detalles_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_venta` (`id_orden`),
  ADD CONSTRAINT `orden_detalles_ibfk_2` FOREIGN KEY (`id_material`) REFERENCES `materiales` (`id_material`);

--
-- Filtros para la tabla `precios_materiales`
--
ALTER TABLE `precios_materiales`
  ADD CONSTRAINT `precios_materiales_ibfk_1` FOREIGN KEY (`id_material`) REFERENCES `materiales` (`id_material`),
  ADD CONSTRAINT `precios_materiales_ibfk_2` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`);

--
-- Filtros para la tabla `proformas`
--
ALTER TABLE `proformas`
  ADD CONSTRAINT `proformas_ibfk_1` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`),
  ADD CONSTRAINT `proformas_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `proformas_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `proforma_detalles`
--
ALTER TABLE `proforma_detalles`
  ADD CONSTRAINT `proforma_detalles_ibfk_1` FOREIGN KEY (`id_proforma`) REFERENCES `proformas` (`id_proforma`),
  ADD CONSTRAINT `proforma_detalles_ibfk_2` FOREIGN KEY (`id_material`) REFERENCES `materiales` (`id_material`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
