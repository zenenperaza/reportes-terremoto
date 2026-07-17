-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-07-2026 a las 17:52:01
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
-- Base de datos: `caminoseguro`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados`
--

CREATE TABLE `estados` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `codigo_iso` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `estados`
--

INSERT INTO `estados` (`id`, `nombre`, `codigo_iso`, `created_at`, `updated_at`) VALUES
(1, 'Amazonas', 've-am', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(2, 'Anzoátegui', 've-an', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(3, 'Apure', 've-ap', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(4, 'Aragua', 've-ar', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(5, 'Barinas', 've-ba', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(6, 'Bolívar', 've-bo', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(7, 'Carabobo', 've-ca', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(8, 'Cojedes', 've-co', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(9, 'Delta Amacuro', 've-da', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(10, 'Distrito Capital', 've-dc', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(11, 'Falcón', 've-fa', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(12, 'Guárico', 've-gu', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(13, 'Lara', 've-la', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(14, 'Mérida', 've-me', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(15, 'Miranda', 've-mi', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(16, 'Monagas', 've-mo', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(17, 'Nueva Esparta', 've-ne', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(18, 'Portuguesa', 've-po', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(19, 'Sucre', 've-su', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(20, 'Táchira', 've-ta', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(21, 'Trujillo', 've-tr', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(22, 'La Guaira', 've-vg', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(23, 'Yaracuy', 've-ya', '2026-05-27 14:59:36', '2026-05-27 14:59:36'),
(24, 'Zulia', 've-zu', '2026-05-27 14:59:36', '2026-05-27 14:59:36');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `estados`
--
ALTER TABLE `estados`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `estados`
--
ALTER TABLE `estados`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
