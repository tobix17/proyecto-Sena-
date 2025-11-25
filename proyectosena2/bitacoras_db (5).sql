-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-11-2025 a las 23:09:56
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bitacoras_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `actividades`
--

INSERT INTO `actividades` (`id`, `usuario_id`, `tipo`, `titulo`, `descripcion`, `fecha`) VALUES
(1, 4, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-03 23:26:02'),
(2, 4, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-04 00:03:35'),
(3, 4, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 14:57:10'),
(4, 1, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 15:57:53'),
(5, 1, 'admin_login', 'Inicio de sesión', 'Administrador inició sesión', '2025-11-06 15:57:53'),
(6, 4, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 16:13:10'),
(7, 1, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 16:32:55'),
(8, 1, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 16:35:41'),
(9, 1, 'admin_login', 'Inicio de sesión', 'Administrador inició sesión', '2025-11-06 16:35:41'),
(10, 1, 'usuario_rechazado', 'Usuario rechazado', 'juanse mejia (instructor)', '2025-11-06 16:35:56'),
(11, 1, 'usuario_rechazado', 'Usuario rechazado', 'juanse mejia (instructor)', '2025-11-06 16:36:08'),
(12, 1, 'usuario_rechazado', 'Usuario rechazado', 'juanse mejia (instructor)', '2025-11-06 16:41:58'),
(13, 1, 'usuario_rechazado', 'Usuario rechazado', 'juanse mejia (instructor)', '2025-11-06 16:46:08'),
(14, 1, 'usuario_aprobado', 'Usuario aprobado', 'juanse mejia (instructor)', '2025-11-06 16:46:55'),
(15, 1, 'login', 'Inicio de sesión', 'Acceso desde: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-06 17:01:59'),
(16, 1, 'admin_login', 'Inicio de sesión', 'Administrador inició sesión', '2025-11-06 17:01:59'),
(17, 1, 'usuario_aprobado', 'Usuario aprobado', 'juanse mejia (instructor)', '2025-11-06 17:02:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacoras`
--

CREATE TABLE `bitacoras` (
  `id` int(11) NOT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `ficha_id` int(255) NOT NULL,
  `numero_bitacora` int(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archivo` varchar(255) DEFAULT NULL,
  `nombre_empresa` varchar(255) NOT NULL,
  `nit` varchar(50) NOT NULL,
  `periodo` varchar(100) NOT NULL,
  `nombre_jefe` varchar(255) NOT NULL,
  `telefono_contacto` varchar(20) NOT NULL,
  `correo_contacto` varchar(255) NOT NULL,
  `modalidad_etapa` varchar(100) NOT NULL,
  `nombre_aprendiz` varchar(255) NOT NULL,
  `documento_aprendiz` varchar(20) NOT NULL,
  `telefono_aprendiz` varchar(20) NOT NULL,
  `correo_aprendiz` varchar(255) NOT NULL,
  `programa_formacion` varchar(255) NOT NULL,
  `descripcion_actividad` text NOT NULL,
  `fecha_inicio` date DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_fin` date DEFAULT NULL,
  `evidencia_cumplimiento` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('borrador','completada','revisada','aprobada','pendiente') DEFAULT 'borrador',
  `fecha_entrega` date NOT NULL,
  `firma_aprendiz` varchar(255) DEFAULT NULL COMMENT 'Ruta de la imagen de firma del aprendiz',
  `firma_instructor` varchar(255) DEFAULT NULL COMMENT 'Ruta de la imagen de firma del instructor',
  `firma_jefe` varchar(255) DEFAULT NULL COMMENT 'Ruta de la imagen de firma del jefe inmediato',
  `nombre_instructor` varchar(255) DEFAULT NULL COMMENT 'Nombre del instructor de seguimiento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bitacoras`
--

INSERT INTO `bitacoras` (`id`, `aprendiz_id`, `ficha_id`, `numero_bitacora`, `fecha_subida`, `archivo`, `nombre_empresa`, `nit`, `periodo`, `nombre_jefe`, `telefono_contacto`, `correo_contacto`, `modalidad_etapa`, `nombre_aprendiz`, `documento_aprendiz`, `telefono_aprendiz`, `correo_aprendiz`, `programa_formacion`, `descripcion_actividad`, `fecha_inicio`, `fecha_actualizacion`, `fecha_fin`, `evidencia_cumplimiento`, `observaciones`, `estado`, `fecha_entrega`, `firma_aprendiz`, `firma_instructor`, `firma_jefe`, `nombre_instructor`) VALUES
(1, 2, 1, 0, '2025-08-26 17:37:45', NULL, '', '811040660', '1-30 agosto', '', '', '', '', 'Tobias Echeverrí', '1011513422', '3312345678', 'tob@gmail.com', 'EL BAGRE - SENA PROGRAMACIÓN DE SOFTWARE', '', NULL, '2025-08-26 17:37:45', NULL, '', NULL, 'borrador', '0000-00-00', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fichas`
--

CREATE TABLE `fichas` (
  `id` int(11) NOT NULL,
  `codigo_ficha` varchar(50) NOT NULL,
  `programa` varchar(255) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fichas`
--

INSERT INTO `fichas` (`id`, `codigo_ficha`, `programa`, `fecha_inicio`, `fecha_fin`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, '2910309', 'EL BAGRE - SENA PROGRAMACIÓN DE SOFTWARE', '2024-03-11', '2025-11-11', '2025-08-26 06:02:04', '2025-08-26 06:02:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('danger','warning','info','success') DEFAULT 'info',
  `icono` varchar(50) DEFAULT 'fas fa-bell',
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `id_usuario`, `mensaje`, `tipo`, `icono`, `leida`, `fecha_creacion`, `fecha_lectura`) VALUES
(3, 4, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-09-29 20:53:42', NULL),
(8, 4, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-10-10 00:21:08', NULL),
(9, 5, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-10-23 23:53:25', NULL),
(10, 2, '¡URGENTE! Tienes 1 bitácora(s) vencida(s).', 'danger', 'fas fa-exclamation-circle', 0, '2025-10-27 13:34:58', NULL),
(11, 2, 'Te faltan 11 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-10-27 13:34:58', NULL),
(12, 4, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-11-04 04:26:02', NULL),
(14, 4, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-11-04 05:18:50', NULL),
(15, 4, 'Te faltan 12 bitácora(s) por completar del total (12).', 'info', 'fas fa-info-circle', 0, '2025-11-06 19:57:10', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencias`
--

CREATE TABLE `preferencias` (
  `id` int(11) NOT NULL,
  `numero_documento` varchar(50) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `notificar_vencidas` tinyint(1) DEFAULT 1,
  `notificar_proximas` tinyint(1) DEFAULT 1,
  `notificar_faltantes` tinyint(1) DEFAULT 1,
  `actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `preferencias`
--

INSERT INTO `preferencias` (`id`, `numero_documento`, `tipo_documento`, `notificar_vencidas`, `notificar_proximas`, `notificar_faltantes`, `actualizado`) VALUES
(1, '1040601735', 'tarjeta_identidad', 0, 0, 0, '2025-09-06 20:17:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencias_avanzadas`
--

CREATE TABLE `preferencias_avanzadas` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `no_molestar_activo` tinyint(1) DEFAULT 0,
  `hora_inicio_nm` time DEFAULT '22:00:00',
  `hora_fin_nm` time DEFAULT '08:00:00',
  `dias_recordatorio` int(11) DEFAULT 3,
  `recordatorios_multiples` tinyint(1) DEFAULT 1,
  `email_vencidas` tinyint(1) DEFAULT 0,
  `email_proximas` tinyint(1) DEFAULT 0,
  `email_logros` tinyint(1) DEFAULT 1,
  `email_resumen` tinyint(1) DEFAULT 1,
  `resumen_semanal_activo` tinyint(1) DEFAULT 1,
  `dia_resumen` int(11) DEFAULT 1 COMMENT '1=Lunes, 7=Domingo',
  `incluir_estadisticas` tinyint(1) DEFAULT 1,
  `incluir_sugerencias` tinyint(1) DEFAULT 1,
  `mensajes_motivacionales` tinyint(1) DEFAULT 1,
  `frecuencia_motivacion` varchar(20) DEFAULT 'semanal',
  `sonido_notificaciones` tinyint(1) DEFAULT 0,
  `notif_navegador` tinyint(1) DEFAULT 0,
  `badge_contador` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `preferencias_avanzadas`
--

INSERT INTO `preferencias_avanzadas` (`id`, `id_usuario`, `no_molestar_activo`, `hora_inicio_nm`, `hora_fin_nm`, `dias_recordatorio`, `recordatorios_multiples`, `email_vencidas`, `email_proximas`, `email_logros`, `email_resumen`, `resumen_semanal_activo`, `dia_resumen`, `incluir_estadisticas`, `incluir_sugerencias`, `mensajes_motivacionales`, `frecuencia_motivacion`, `sonido_notificaciones`, `notif_navegador`, `badge_contador`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 0, '22:00:00', '08:00:00', 3, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 'semanal', 0, 0, 1, '2025-11-06 19:56:20', '2025-11-06 19:56:20'),
(4, 2, 0, '22:00:00', '08:00:00', 3, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 'semanal', 0, 0, 1, '2025-11-06 19:56:20', '2025-11-06 19:56:20'),
(5, 4, 0, '22:00:00', '08:00:00', 3, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 'semanal', 0, 0, 1, '2025-11-06 19:56:20', '2025-11-06 19:56:20'),
(6, 5, 0, '22:00:00', '08:00:00', 3, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 'semanal', 0, 0, 1, '2025-11-06 19:56:20', '2025-11-06 19:56:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencias_notificaciones`
--

CREATE TABLE `preferencias_notificaciones` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `notificar_vencidas` tinyint(1) DEFAULT 1,
  `notificar_proximas` tinyint(1) DEFAULT 1,
  `notificar_faltantes` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tema_oscuro` tinyint(1) DEFAULT 0 COMMENT '0 = tema claro, 1 = tema oscuro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `preferencias_notificaciones`
--

INSERT INTO `preferencias_notificaciones` (`id`, `id_usuario`, `notificar_vencidas`, `notificar_proximas`, `notificar_faltantes`, `fecha_creacion`, `fecha_actualizacion`, `tema_oscuro`) VALUES
(1, 4, 1, 1, 1, '2025-09-30 01:32:30', '2025-10-10 01:41:35', 1),
(2, 1, 1, 1, 1, '2025-10-10 01:37:23', '2025-10-23 23:48:58', 1),
(3, 5, 1, 1, 1, '2025-10-23 23:53:25', '2025-10-23 23:53:25', 0),
(4, 2, 0, 0, 0, '2025-10-27 13:34:58', '2025-10-27 13:41:40', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones`
--

CREATE TABLE `sesiones` (
  `id` varchar(128) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_ultima_actividad` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `tipo_documento` enum('tarjeta_identidad','cedula_ciudadania','cedula_extranjeria','pasaporte') NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `rol` enum('aprendiz','instructor','administrador') NOT NULL,
  `genero` enum('masculino','femenino','otro') NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `correo` varchar(255) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `foto_perfil` varchar(255) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `tipo_documento`, `numero_documento`, `rol`, `genero`, `fecha_nacimiento`, `correo`, `contrasena_hash`, `foto_perfil`, `ficha_id`, `fecha_registro`, `fecha_actualizacion`, `estado`) VALUES
(1, 'Paulina', 'Ochoa', 'tarjeta_identidad', '1011513423', 'administrador', 'femenino', '2008-09-26', 'pau@gmail.com', '$2y$10$FRnSGrkwgI4S2QW/GQRJa.QwTYlt55EDz8K0RKUbzjdH1x7HCK8.S', 'uploads/1011513423_1756229773_b5Cnq10vksElonVa6l8SllU4O0C.jpg', NULL, '2025-08-26 06:00:55', '2025-08-26 17:36:13', 'activo'),
(2, 'Tobias', 'Echeverrí', 'tarjeta_identidad', '1011513422', 'aprendiz', 'masculino', '2008-05-21', 'tob@gmail.com', '$2y$10$5IrneDPTwxfQgH0vj8VGH.sedTscZwKagD/kFBxJM0XoOzq4qqeCW', '', 1, '2025-08-26 06:02:55', '2025-08-26 06:02:55', 'activo'),
(4, 'Tobias', 'echeverri perez', 'tarjeta_identidad', '1040601735', 'aprendiz', 'masculino', '2019-07-10', 'tobiax17@gmail.com', '$2y$10$WQCCg2m.//pZD1cWquevEO3haSrMFcXeAibk21H0m50/XKggJi.j6', 'uploads/1040601735_1757189637_logoSena.png', 1, '2025-09-01 04:35:58', '2025-09-06 20:13:57', 'activo'),
(5, 'juan Felipe', 'Gonzales', 'tarjeta_identidad', '1080394811', 'aprendiz', 'masculino', '2015-06-10', 'Gonzalex12@gmail.com', '$2y$10$sYiml7ikqrANsUKthMgvIOWQm2AXRXBiK1S6PGfpuzvhRat4ctkUG', '', 1, '2025-10-23 23:52:54', '2025-10-23 23:52:54', 'activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `bitacoras`
--
ALTER TABLE `bitacoras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bitacora_aprendiz` (`aprendiz_id`),
  ADD KEY `fk_bitacora_ficha` (`ficha_id`),
  ADD KEY `idx_bitacora_fecha_entrega` (`fecha_entrega`),
  ADD KEY `idx_bitacora_estado` (`estado`);

--
-- Indices de la tabla `fichas`
--
ALTER TABLE `fichas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_ficha` (`codigo_ficha`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_usuario` (`id_usuario`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_creacion`),
  ADD KEY `idx_leida` (`leida`),
  ADD KEY `idx_notif_usuario_fecha` (`id_usuario`,`fecha_creacion`),
  ADD KEY `idx_notif_tipo` (`tipo`);

--
-- Indices de la tabla `preferencias`
--
ALTER TABLE `preferencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`,`tipo_documento`);

--
-- Indices de la tabla `preferencias_avanzadas`
--
ALTER TABLE `preferencias_avanzadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user` (`id_usuario`);

--
-- Indices de la tabla `preferencias_notificaciones`
--
ALTER TABLE `preferencias_notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_usuario` (`id_usuario`),
  ADD KEY `fk_pref_usuario` (`id_usuario`);

--
-- Indices de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sesion_usuario` (`usuario_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `fk_usuario_ficha` (`ficha_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `bitacoras`
--
ALTER TABLE `bitacoras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `fichas`
--
ALTER TABLE `fichas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `preferencias`
--
ALTER TABLE `preferencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `preferencias_avanzadas`
--
ALTER TABLE `preferencias_avanzadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `preferencias_notificaciones`
--
ALTER TABLE `preferencias_notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `fk_actividad_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bitacoras`
--
ALTER TABLE `bitacoras`
  ADD CONSTRAINT `fk_bitacora_aprendiz` FOREIGN KEY (`aprendiz_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bitacora_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `preferencias_avanzadas`
--
ALTER TABLE `preferencias_avanzadas`
  ADD CONSTRAINT `fk_pref_avanzadas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `preferencias_notificaciones`
--
ALTER TABLE `preferencias_notificaciones`
  ADD CONSTRAINT `fk_pref_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
