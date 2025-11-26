-- ============================================================================
-- BASE DE DATOS: SISTEMA DE GESTIÓN DE INSPECCIONES CRUZADAS - ICASE
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- CREAR BASE DE DATOS
-- ============================================================================
CREATE DATABASE IF NOT EXISTS `icase_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `icase_system`;

-- ============================================================================
-- TABLA: usuarios
-- ============================================================================
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('facilitador','socio') NOT NULL,
  `contrasena` varchar(32) NOT NULL COMMENT 'MD5 hash',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: socios
-- ============================================================================
CREATE TABLE `socios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre_empresa` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_socios_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: items (7 ítems de evaluación)
-- ============================================================================
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `peso_base` decimal(5,4) NOT NULL,
  `es_general` tinyint(1) NOT NULL COMMENT '1=General, 0=Específico',
  `orden` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: preguntas (28 preguntas del checklist)
-- ============================================================================
CREATE TABLE `preguntas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `texto_pregunta` text NOT NULL,
  `orden` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_preguntas_items` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: inspecciones
-- ============================================================================
CREATE TABLE `inspecciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_inspector_id` int(11) NOT NULL,
  `socio_inspeccionado_id` int(11) NOT NULL,
  `nombre_inspector` varchar(100) NOT NULL,
  `facilitador_nombre` varchar(100) DEFAULT 'Ninguno',
  `lugar` varchar(200) NOT NULL,
  `fecha_inspeccion` date NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notificado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `socio_inspector_id` (`socio_inspector_id`),
  KEY `socio_inspeccionado_id` (`socio_inspeccionado_id`),
  KEY `idx_fecha` (`fecha_inspeccion`),
  CONSTRAINT `fk_insp_inspector` FOREIGN KEY (`socio_inspector_id`) REFERENCES `socios` (`id`),
  CONSTRAINT `fk_insp_inspeccionado` FOREIGN KEY (`socio_inspeccionado_id`) REFERENCES `socios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: respuestas_checklist
-- ============================================================================
CREATE TABLE `respuestas_checklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspeccion_id` int(11) NOT NULL,
  `pregunta_id` int(11) NOT NULL,
  `valor` varchar(10) NOT NULL COMMENT '0, 0.5, 1, o NA',
  PRIMARY KEY (`id`),
  KEY `inspeccion_id` (`inspeccion_id`),
  KEY `pregunta_id` (`pregunta_id`),
  CONSTRAINT `fk_resp_inspeccion` FOREIGN KEY (`inspeccion_id`) REFERENCES `inspecciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resp_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: observaciones
-- ============================================================================
CREATE TABLE `observaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspeccion_id` int(11) NOT NULL,
  `foto_url` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo` int(11) DEFAULT NULL COMMENT '1-6 o NULL para Ninguno',
  `fecha_registro` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('vencida','en_plazo','completada') NOT NULL DEFAULT 'en_plazo',
  `es_nula` tinyint(1) DEFAULT 0 COMMENT 'Facilitador puede declarar nula',
  PRIMARY KEY (`id`),
  KEY `inspeccion_id` (`inspeccion_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fechas` (`fecha_registro`,`fecha_vencimiento`),
  CONSTRAINT `fk_obs_inspeccion` FOREIGN KEY (`inspeccion_id`) REFERENCES `inspecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: levantamientos
-- ============================================================================
CREATE TABLE `levantamientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `observacion_id` int(11) NOT NULL,
  `foto_url` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_levantamiento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `observacion_id` (`observacion_id`),
  CONSTRAINT `fk_lev_observacion` FOREIGN KEY (`observacion_id`) REFERENCES `observaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: notificaciones
-- ============================================================================
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('nueva_inspeccion','observacion_levantada','observacion_vencida','observacion_proxima') NOT NULL,
  `mensaje` text NOT NULL,
  `referencia_id` int(11) DEFAULT NULL COMMENT 'ID de inspección u observación',
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_leida` (`leida`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: porcentajes_items_inspeccion (por inspección individual)
-- ============================================================================
CREATE TABLE `porcentajes_items_inspeccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspeccion_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `porcentaje` decimal(5,2) DEFAULT NULL COMMENT 'NULL si es N.A.',
  `es_na` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `inspeccion_id` (`inspeccion_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_porc_insp` FOREIGN KEY (`inspeccion_id`) REFERENCES `inspecciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_porc_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: porcentajes_items_mensual (consolidados)
-- ============================================================================
CREATE TABLE `porcentajes_items_mensual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `mes` int(11) NOT NULL COMMENT '1-12',
  `anio` int(11) NOT NULL,
  `porcentaje_consolidado` decimal(5,2) DEFAULT NULL COMMENT 'NULL si es N.A.',
  `es_na` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_socio_item_mes` (`socio_id`,`item_id`,`mes`,`anio`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_porcmes_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_porcmes_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: icase_mensual
-- ============================================================================
CREATE TABLE `icase_mensual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_id` int(11) NOT NULL,
  `mes` int(11) NOT NULL COMMENT '1-12',
  `anio` int(11) NOT NULL,
  `icase` decimal(5,2) DEFAULT NULL,
  `fecha_calculo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_socio_mes` (`socio_id`,`mes`,`anio`),
  CONSTRAINT `fk_icase_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERTAR DATOS: Usuarios y Socios
-- ============================================================================
-- Contraseña por defecto: 123456 en MD5 = e10adc3949ba59abbe56e057f20f883e

-- Insertar Facilitador (opción A: un solo usuario)
INSERT INTO `usuarios` (`nombre`, `tipo`, `contrasena`) VALUES
('Facilitador', 'facilitador', 'e10adc3949ba59abbe56e057f20f883e');

-- Insertar Socios
INSERT INTO `usuarios` (`nombre`, `tipo`, `contrasena`) VALUES
('Antu', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('RSC', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('WSP', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('Stracon', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('Ausenco', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('Global', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('Flesan', 'socio', 'e10adc3949ba59abbe56e057f20f883e'),
('Diamond', 'socio', 'e10adc3949ba59abbe56e057f20f883e');

-- Insertar Socios (relación con usuarios)
INSERT INTO `socios` (`usuario_id`, `nombre_empresa`) VALUES
(2, 'Antu'),
(3, 'RSC'),
(4, 'WSP'),
(5, 'Stracon'),
(6, 'Ausenco'),
(7, 'Global'),
(8, 'Flesan'),
(9, 'Diamond');

-- ============================================================================
-- INSERTAR DATOS: Ítems de Evaluación (7 ítems)
-- ============================================================================
INSERT INTO `items` (`nombre`, `peso_base`, `es_general`, `orden`) VALUES
('PMAO', 0.1500, 1, 1),
('Orden y limpieza', 0.1000, 1, 2),
('Manejo de residuos', 0.1500, 1, 3),
('Sistemas de contención', 0.1500, 1, 4),
('Control de erosión y sedimentos', 0.1500, 0, 5),
('Manejo de suelo orgánico', 0.1500, 0, 6),
('Control de calidad de aire', 0.1500, 0, 7);

-- ============================================================================
-- INSERTAR DATOS: Preguntas del Checklist (28 preguntas)
-- ============================================================================

-- PMAO (4 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(1, '¿Cuenta con PMAO aprobado y actualizado?', 1),
(1, '¿El personal conoce y aplica el PMAO?', 2),
(1, '¿Se evidencian registros de implementación del PMAO?', 3),
(1, '¿Se realizan inspecciones periódicas según el PMAO?', 4);

-- Orden y limpieza (3 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(2, '¿El frente de trabajo se encuentra ordenado?', 1),
(2, '¿Las áreas de trabajo están limpias y libres de residuos?', 2),
(2, '¿Los materiales están debidamente almacenados y señalizados?', 3);

-- Manejo de residuos (4 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(3, '¿Los residuos están segregados correctamente?', 1),
(3, '¿Los contenedores de residuos están debidamente identificados?', 2),
(3, '¿Se cuenta con manifiestos de disposición de residuos?', 3),
(3, '¿El área de almacenamiento temporal cumple con las especificaciones?', 4);

-- Sistemas de contención (5 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(4, '¿Se cuenta con sistemas de contención para sustancias peligrosas?', 1),
(4, '¿Los sistemas de contención están en buen estado?', 2),
(4, '¿Se realizan inspecciones periódicas a los sistemas de contención?', 3),
(4, '¿Existe procedimiento para atención de derrames?', 4),
(4, '¿Se cuenta con kit anti-derrames disponible y accesible?', 5);

-- Control de erosión y sedimentos (5 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(5, '¿Se han implementado medidas de control de erosión?', 1),
(5, '¿Las estructuras de control de sedimentos funcionan correctamente?', 2),
(5, '¿Se realiza mantenimiento a las estructuras de control?', 3),
(5, '¿Se monitorea la efectividad de las medidas implementadas?', 4),
(5, '¿Las áreas intervenidas están estabilizadas?', 5);

-- Manejo de suelo orgánico (4 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(6, '¿Se realiza remoción y acopio adecuado del suelo orgánico?', 1),
(6, '¿El suelo orgánico está almacenado en pilas con altura adecuada?', 2),
(6, '¿Se protege el suelo orgánico de la erosión?', 3),
(6, '¿Existe señalización de las áreas de acopio?', 4);

-- Control de calidad de aire (3 preguntas)
INSERT INTO `preguntas` (`item_id`, `texto_pregunta`, `orden`) VALUES
(7, '¿Se implementan medidas de control de emisiones de polvo?', 1),
(7, '¿Se realiza humectación de vías y áreas de trabajo?', 2),
(7, '¿Los vehículos y maquinaria cuentan con mantenimiento preventivo?', 3);

-- ============================================================================
-- COMMIT
-- ============================================================================
COMMIT;

-- ============================================================================
-- FIN DE LA BASE DE DATOS
-- ============================================================================
