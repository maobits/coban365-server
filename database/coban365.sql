-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 31-08-2025 a las 01:27:46
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
-- Base de datos: `coban365`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `account_statement_others`
--

CREATE TABLE `account_statement_others` (
  `id` int(11) NOT NULL,
  `account_to_pay` decimal(15,2) DEFAULT 0.00,
  `account_receivable` decimal(15,2) DEFAULT 0.00,
  `transaction_id` int(11) NOT NULL,
  `id_third` int(11) NOT NULL,
  `state` tinyint(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cash`
--

CREATE TABLE `cash` (
  `id` int(11) NOT NULL,
  `correspondent_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `capacity` bigint(20) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = abierto, 0 = cerrado',
  `last_note` varchar(255) DEFAULT NULL COMMENT 'Última nota registrada',
  `initial_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto de configuración inicial de la caja'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cash`
--

INSERT INTO `cash` (`id`, `correspondent_id`, `cashier_id`, `name`, `capacity`, `state`, `created_at`, `updated_at`, `open`, `last_note`, `initial_amount`) VALUES
(24, 23, 35, 'Caja 1', 10000000, 1, '2025-07-27 18:02:42', '2025-08-11 23:31:47', 1, 'Faltante en caja', 1000000.00),
(25, 23, 36, 'Caja auxiliar', 10000000, 1, '2025-07-27 18:03:15', '2025-07-27 18:04:03', 0, 'Caja auxiliar', 1000000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cash_balance`
--

CREATE TABLE `cash_balance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `correspondent_id` bigint(20) UNSIGNED NOT NULL,
  `cash_id` bigint(20) UNSIGNED NOT NULL,
  `cashier_id` bigint(20) UNSIGNED NOT NULL,
  `balance_date` date NOT NULL,
  `balance_time` time NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`details`)),
  `total_bills` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_bundles` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_coins` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_effective` decimal(16,2) NOT NULL DEFAULT 0.00,
  `current_cash` decimal(16,2) NOT NULL DEFAULT 0.00,
  `diff_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `diff_status` enum('OK','SOBRANTE','FALTANTE') NOT NULL DEFAULT 'OK',
  `frozen_box` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `cash_balance`
--

INSERT INTO `cash_balance` (`id`, `correspondent_id`, `cash_id`, `cashier_id`, `balance_date`, `balance_time`, `details`, `total_bills`, `total_bundles`, `total_coins`, `total_effective`, `current_cash`, `diff_amount`, `diff_status`, `frozen_box`, `note`, `created_at`, `updated_at`) VALUES
(10, 12029, 24, 35, '2025-08-09', '01:03:35', '{\"header\":{\"correspondent_code\":\"12029\",\"correspondent_name\":\"BARRIO CENTENARIO\",\"cash\":{\"id\":24,\"name\":\"Caja 1\"},\"reported_at\":\"2025-08-11T06:03:35.665Z\"},\"sections\":{\"bills\":[{\"denom\":100000,\"count\":20,\"subtotal\":2000000},{\"denom\":50000,\"count\":18,\"subtotal\":900000},{\"denom\":20000,\"count\":0,\"subtotal\":0},{\"denom\":10000,\"count\":0,\"subtotal\":0},{\"denom\":5000,\"count\":0,\"subtotal\":0},{\"denom\":2000,\"count\":0,\"subtotal\":0},{\"denom\":1000,\"count\":0,\"subtotal\":0}],\"bundles\":[{\"denom\":100000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":50000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":20000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":10000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":5000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":2000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":1000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0}],\"coins\":[{\"denom\":1000,\"count\":0,\"subtotal\":0},{\"denom\":500,\"count\":0,\"subtotal\":0},{\"denom\":200,\"count\":0,\"subtotal\":0},{\"denom\":100,\"count\":0,\"subtotal\":0},{\"denom\":50,\"count\":0,\"subtotal\":0}]},\"subtotals\":{\"bills\":2900000,\"bundles\":0,\"coins\":0},\"totals\":{\"total_effective\":2900000,\"current_cash\":2900000,\"balance\":0,\"abs_diff\":0,\"message\":\"Cuadre OK\"}}', 2900000.00, 0.00, 0.00, 2900000.00, 2900000.00, 0.00, 'OK', 1, 'Cuadre OK', '2025-08-11 06:03:35', '2025-08-11 06:03:35'),
(11, 12029, 24, 35, '2025-08-10', '01:43:14', '{\"header\":{\"correspondent_code\":\"12029\",\"correspondent_name\":\"BARRIO CENTENARIO\",\"cash\":{\"id\":24,\"name\":\"Caja 1\"},\"reported_at\":\"2025-08-11T06:43:14.517Z\"},\"sections\":{\"bills\":[{\"denom\":100000,\"count\":25,\"subtotal\":2500000},{\"denom\":50000,\"count\":0,\"subtotal\":0},{\"denom\":20000,\"count\":0,\"subtotal\":0},{\"denom\":10000,\"count\":0,\"subtotal\":0},{\"denom\":5000,\"count\":0,\"subtotal\":0},{\"denom\":2000,\"count\":0,\"subtotal\":0},{\"denom\":1000,\"count\":0,\"subtotal\":0}],\"bundles\":[{\"denom\":100000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":50000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":20000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":10000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":5000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":2000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0},{\"denom\":1000,\"count\":0,\"units_per_bundle\":100,\"subtotal\":0}],\"coins\":[{\"denom\":1000,\"count\":0,\"subtotal\":0},{\"denom\":500,\"count\":0,\"subtotal\":0},{\"denom\":200,\"count\":0,\"subtotal\":0},{\"denom\":100,\"count\":0,\"subtotal\":0},{\"denom\":50,\"count\":0,\"subtotal\":0}]},\"subtotals\":{\"bills\":2500000,\"bundles\":0,\"coins\":0},\"totals\":{\"total_effective\":2500000,\"current_cash\":3000000,\"balance\":-500000,\"abs_diff\":500000,\"message\":\"Faltante en caja\"}}', 2500000.00, 0.00, 0.00, 2500000.00, 3000000.00, -500000.00, 'FALTANTE', 1, 'Faltante en caja', '2025-08-11 06:43:14', '2025-08-11 06:43:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cash_closing_register`
--

CREATE TABLE `cash_closing_register` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cash_id` bigint(20) UNSIGNED NOT NULL,
  `closing_date` date NOT NULL,
  `closing_time` time NOT NULL,
  `closed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cash_closing_register`
--

INSERT INTO `cash_closing_register` (`id`, `cash_id`, `closing_date`, `closing_time`, `closed_by`, `note`, `created_at`) VALUES
(9, 24, '2025-08-09', '01:03:35', 35, 'Cuadre OK', '2025-08-11 06:03:35'),
(10, 24, '2025-08-10', '01:43:14', 35, 'Faltante en caja', '2025-08-11 06:43:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `correspondents`
--

CREATE TABLE `correspondents` (
  `id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`location`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `transactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`transactions`)),
  `credit_limit` bigint(20) NOT NULL DEFAULT 500000,
  `state` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
  `premium` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Premium, 0 = Básico'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `correspondents`
--

INSERT INTO `correspondents` (`id`, `type_id`, `code`, `operator_id`, `name`, `location`, `created_at`, `updated_at`, `transactions`, `credit_limit`, `state`, `premium`) VALUES
(23, 1, '12029', 34, 'BARRIO CENTENARIO', '{\"departamento\":\"Antioquia\",\"ciudad\":\"Caucacia\"}', '2025-07-23 20:35:21', '2025-07-23 20:35:33', '[{\"id\":7,\"name\":\"Abono a tarjeta de crédito\"},{\"id\":13,\"name\":\"Ahorro ALM\"},{\"id\":18,\"name\":\"Compensación\"},{\"id\":6,\"name\":\"Depósito\"},{\"id\":15,\"name\":\"Pago  a tercero\"},{\"id\":8,\"name\":\"Pago de crédito\"},{\"id\":17,\"name\":\"Pago de tercero\"},{\"id\":16,\"name\":\"Préstamo a tercero\"},{\"id\":14,\"name\":\"Préstamo de terceros\"},{\"id\":3,\"name\":\"Recaudos\"},{\"id\":5,\"name\":\"Retiro\"},{\"id\":9,\"name\":\"Retiro con tarjeta\"},{\"id\":10,\"name\":\"Retiro Nequi\"},{\"id\":11,\"name\":\"Saldo\"},{\"id\":12,\"name\":\"Transferencia\"},{\"id\":19,\"name\":\"Transferir a otra caja\"}]', 50000000, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `others`
--

CREATE TABLE `others` (
  `id` int(11) NOT NULL,
  `correspondent_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `negative_balance` tinyint(1) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `others`
--

INSERT INTO `others` (`id`, `correspondent_id`, `name`, `id_type`, `id_number`, `email`, `phone`, `address`, `credit`, `balance`, `negative_balance`, `state`, `created_at`, `updated_at`) VALUES
(20, 23, 'Maobits', 'Cédula de Ciudadanía', '1061740164', 'admin@maobits.com', '3153774638', 'Calle 15', 1000000.00, 100000.00, 1, 1, '2025-08-08 04:28:00', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pos_calculator`
--

CREATE TABLE `pos_calculator` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cash_id` int(10) UNSIGNED NOT NULL,
  `correspondent_id` int(10) UNSIGNED NOT NULL,
  `cashier_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `subtotal` bigint(20) NOT NULL DEFAULT 0,
  `discount` bigint(20) NOT NULL DEFAULT 0,
  `fee` bigint(20) NOT NULL DEFAULT 0,
  `total` bigint(20) NOT NULL DEFAULT 0,
  `note` varchar(160) DEFAULT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pos_calculator`
--

INSERT INTO `pos_calculator` (`id`, `cash_id`, `correspondent_id`, `cashier_id`, `customer_name`, `customer_phone`, `subtotal`, `discount`, `fee`, `total`, `note`, `items_json`, `created_at`, `updated_at`) VALUES
(2, 24, 23, 35, 'Mauricio chara', '3153774638', 750000, 0, 0, 750000, 'EXACTO', '{\"tasks\":[{\"name\":\"tarea 1\",\"value\":500000},{\"name\":\"tarea 2\",\"value\":250000}],\"denominations\":[{\"denom\":2000,\"qty\":250},{\"denom\":5000,\"qty\":50},{\"denom\":10000,\"qty\":0},{\"denom\":20000,\"qty\":0},{\"denom\":50000,\"qty\":0},{\"denom\":100000,\"qty\":0}],\"coins\":0}', '2025-08-15 18:24:38', '2025-08-15 18:24:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rates`
--

CREATE TABLE `rates` (
  `id` int(11) NOT NULL,
  `correspondent_id` int(11) NOT NULL,
  `transaction_type_id` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rates`
--

INSERT INTO `rates` (`id`, `correspondent_id`, `transaction_type_id`, `price`, `created_at`, `updated_at`) VALUES
(10, 21, 7, 500.00, '2025-06-11 06:17:20', '2025-06-11 06:17:20'),
(11, 21, 6, 500.00, '2025-06-11 06:17:56', '2025-06-11 06:17:56'),
(12, 21, 7, 400.00, '2025-06-11 06:18:21', '2025-06-11 06:18:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `correspondent_id` int(11) NOT NULL,
  `cash_id` int(11) NOT NULL,
  `transaction_type` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `agreement` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `document_id` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `state` tinyint(1) DEFAULT 1,
  `expired` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `id_cashier` int(11) NOT NULL,
  `id_cash` int(11) NOT NULL,
  `id_correspondent` int(11) NOT NULL,
  `transaction_type_id` int(11) NOT NULL,
  `polarity` tinyint(1) NOT NULL DEFAULT 1,
  `neutral` tinyint(1) DEFAULT 0,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `cancellation_note` text DEFAULT NULL,
  `client_reference` varchar(255) DEFAULT NULL,
  `third_party_note` varchar(255) DEFAULT NULL,
  `type_of_movement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `utility` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Utilidad o ganancia de la transacción',
  `is_transfer` tinyint(1) NOT NULL DEFAULT 0 COMMENT '¿Es transferencia entre cajas?',
  `box_reference` int(11) DEFAULT NULL COMMENT 'Caja destino si es transferencia',
  `transfer_status` tinyint(1) DEFAULT NULL COMMENT '1: aceptada, 0: rechazada',
  `cash_tag` decimal(15,2) DEFAULT NULL COMMENT 'Etiqueta de valor de caja'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transactions`
--

INSERT INTO `transactions` (`id`, `id_cashier`, `id_cash`, `id_correspondent`, `transaction_type_id`, `polarity`, `neutral`, `cost`, `state`, `note`, `cancellation_note`, `client_reference`, `third_party_note`, `type_of_movement`, `created_at`, `updated_at`, `utility`, `is_transfer`, `box_reference`, `transfer_status`, `cash_tag`) VALUES
(397, 1, 24, 23, 6, 1, 0, 100000.00, 1, '-', NULL, NULL, NULL, NULL, '2025-08-30 16:10:20', NULL, 0.00, 0, NULL, NULL, 1100000.00),
(398, 1, 24, 23, 17, 1, 0, 50000.00, 1, 'Maobits', NULL, '20', 'charge_to_third_party', 'Entrega en efectivo', '2025-08-30 16:11:07', NULL, 0.00, 0, NULL, NULL, 1150000.00),
(399, 1, 24, 23, 16, 0, 0, 50000.00, 1, 'Maobits', NULL, '20', 'loan_to_third_party', 'Entrega en efectivo', '2025-08-30 16:13:58', NULL, 0.00, 0, NULL, NULL, 1100000.00),
(400, 1, 24, 23, 14, 1, 0, 50000.00, 1, 'Maobits', NULL, '20', 'loan_from_third_party', 'Entrega en efectivo', '2025-08-30 16:16:50', NULL, 0.00, 0, NULL, NULL, 1150000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transaction_types`
--

CREATE TABLE `transaction_types` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `polarity` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transaction_types`
--

INSERT INTO `transaction_types` (`id`, `category`, `name`, `created_at`, `updated_at`, `polarity`) VALUES
(3, 'Ingresos', 'Recaudos', '2025-03-23 20:58:28', '2025-04-12 20:34:31', 1),
(5, 'Retiros', 'Retiro', '2025-04-12 20:08:55', '2025-04-12 20:08:55', 0),
(6, 'Ingresos', 'Depósito', '2025-04-17 22:45:21', '2025-04-17 22:45:21', 1),
(7, 'Ingresos', 'Abono a tarjeta de crédito', '2025-04-17 22:45:47', '2025-04-17 22:45:47', 1),
(8, 'Ingresos', 'Pago de crédito', '2025-04-17 22:46:06', '2025-04-17 22:46:06', 1),
(9, 'Retiros', 'Retiro con tarjeta', '2025-04-17 22:46:28', '2025-04-17 22:46:28', 0),
(10, 'Retiros', 'Retiro Nequi', '2025-04-17 22:47:08', '2025-04-17 22:47:08', 0),
(11, 'Otros', 'Saldo', '2025-05-24 02:06:11', '2025-05-24 02:06:11', 1),
(12, 'Otros', 'Transferencia', '2025-05-24 02:06:58', '2025-05-24 02:06:58', 1),
(13, 'Otros', 'Ahorro ALM', '2025-05-24 02:07:38', '2025-05-24 02:07:38', 1),
(14, 'Terceros', 'Préstamo de terceros', '2025-05-24 03:52:09', '2025-05-24 03:52:09', 1),
(15, 'Terceros', 'Pago  a tercero', '2025-05-24 03:52:45', '2025-05-24 03:52:45', 0),
(16, 'Terceros', 'Préstamo a tercero', '2025-05-24 03:53:21', '2025-05-24 03:53:21', 0),
(17, 'Terceros', 'Pago de tercero', '2025-05-24 03:54:11', '2025-05-24 03:54:11', 1),
(18, 'Compensación', 'Compensación', '2025-05-26 00:32:38', '2025-05-26 00:32:38', 0),
(19, 'Transferir', 'Transferir a otra caja', '2025-05-27 20:13:14', '2025-05-27 20:13:14', 0),
(20, 'Ingresos', 'Transaccion prueba', '2025-06-01 16:58:35', '2025-06-01 16:58:35', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `types_correspondents`
--

CREATE TABLE `types_correspondents` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `processes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`processes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `types_correspondents`
--

INSERT INTO `types_correspondents` (`id`, `name`, `description`, `processes`, `created_at`, `updated_at`) VALUES
(1, 'Tipo1', 'Corresponsales Bancarios manejados por Datafonos', '{\n    \"Entradas y Salidas\": \"SI\",\n    \"Cobro de comisiones a entidad financiera\": \"Mensual\",\n    \"Realiza facturación para cobro comisión\": \"SI / NO\"\n}', '2025-03-10 06:07:23', '2025-03-10 06:07:33'),
(2, 'Tipo2', 'Corresponsales Bancarios manejados por Plataformas Web', '{\n    \"Entradas y Salidas\": \"SI\",\n    \"Cobro de comisiones a entidad financiera\": \"Diario\",\n    \"Realiza facturación para cobro comisión\": \"Autoliquida\"\n}', '2025-03-10 06:07:23', '2025-04-17 22:42:02'),
(3, 'Tipo3', 'Otros Corresponsales', '{\n    \"Entradas y Salidas\": \"SI\",\n    \"Cobro de comisiones a entidad financiera\": \"Mensual\",\n    \"Realiza facturación para cobro comisión\": \"Autoliquida\"\n}', '2025-03-10 06:07:23', '2025-05-14 22:12:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `role` enum('admin','superadmin','cajero','tercero') NOT NULL DEFAULT 'tercero',
  `permissions` text DEFAULT NULL,
  `correspondents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `email`, `fullname`, `phone`, `password`, `status`, `role`, `permissions`, `correspondents`, `created_at`, `updated_at`) VALUES
(1, 'admin@maobits.com', 'Mauricio Chara', '3153774638', '$2y$10$v7ruMRiDLqL0PUH36fbZxOl/jjhUQce48PhilInQzONA2bnAIC55W', 1, 'superadmin', '[\"manageCorrespondents\",\"manageAdministrators\",\"manageTransactions\",\"manageCorrespondent\"]', NULL, '2025-03-08 21:10:05', '2025-07-28 04:59:00'),
(34, 'mauriciochara10k@gmail.com', 'Administrador', '+57 3153774638', '$2y$10$vey00uediNBkVddy.9YywuSCoUDaHzv8.fjmkh5Q0MyxlWxOljUeu', 1, 'admin', '[\"manageCorrespondent\"]', NULL, '2025-07-23 20:34:11', '2025-07-28 04:58:45'),
(35, 'cajero1@gmail.com', 'Cajero 1', '124578', '$2y$10$m48/sADZLDPjFOOIcXa7re75lwaif5hrskb.C3FzUhpO03jvCy6W.', 1, 'cajero', '[\"manageCash\"]', '[{\"id\":23}]', '2025-07-23 20:36:54', '2025-07-23 20:36:54'),
(36, 'cajero2@gmail.com', 'Cajero 2', '124578', '$2y$10$yZUtcd8pW7Btaztv4HLFAuMNWwVJxRteAwbLh8JvG/mUcroPB6.S2', 1, 'cajero', '[\"manageCash\"]', '[{\"id\":23}]', '2025-07-23 20:37:25', '2025-07-23 20:37:25'),
(37, 'cajero3@gmail.com', 'Cajero 3', '124578', '$2y$10$DhVSNVTMycqVOJTEAqMaEuXV4T0zQTbvCV.EfrW9zWmK19Dtjv5AW', 1, 'cajero', '[\"manageCash\"]', '[{\"id\":23}]', '2025-07-23 20:38:10', '2025-07-23 20:38:10'),
(38, 'cajero4@gmal.com', 'cajero4', '124578', '$2y$10$HBRLh7l7oNTGJsGQF00i4uxtmaelaijilyHO9Ck6gJXTD6kkb0Dzq', 1, 'cajero', '[\"manageCash\"]', '[{\"id\":23}]', '2025-07-23 20:39:02', '2025-07-23 20:39:27');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `account_statement_others`
--
ALTER TABLE `account_statement_others`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cash`
--
ALTER TABLE `cash`
  ADD PRIMARY KEY (`id`),
  ADD KEY `correspondent_id` (`correspondent_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indices de la tabla `cash_balance`
--
ALTER TABLE `cash_balance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_date` (`cash_id`,`balance_date`),
  ADD KEY `idx_corr_date` (`correspondent_id`,`balance_date`),
  ADD KEY `idx_cashier_dt` (`cashier_id`,`balance_date`),
  ADD KEY `idx_cb_cash_date_frozen` (`cash_id`,`balance_date`,`frozen_box`);

--
-- Indices de la tabla `cash_closing_register`
--
ALTER TABLE `cash_closing_register`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_date` (`cash_id`,`closing_date`);

--
-- Indices de la tabla `correspondents`
--
ALTER TABLE `correspondents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `type_id` (`type_id`);

--
-- Indices de la tabla `others`
--
ALTER TABLE `others`
  ADD PRIMARY KEY (`id`),
  ADD KEY `correspondent_id` (`correspondent_id`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `pos_calculator`
--
ALTER TABLE `pos_calculator`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_id` (`cash_id`),
  ADD KEY `idx_corr_id` (`correspondent_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `rates`
--
ALTER TABLE `rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_type_id` (`transaction_type_id`);

--
-- Indices de la tabla `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `correspondent_id` (`correspondent_id`),
  ADD KEY `cash_id` (`cash_id`);

--
-- Indices de la tabla `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cashier` (`id_cashier`),
  ADD KEY `id_cash` (`id_cash`),
  ADD KEY `id_correspondent` (`id_correspondent`),
  ADD KEY `transaction_type_id` (`transaction_type_id`);

--
-- Indices de la tabla `transaction_types`
--
ALTER TABLE `transaction_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `types_correspondents`
--
ALTER TABLE `types_correspondents`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `account_statement_others`
--
ALTER TABLE `account_statement_others`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cash`
--
ALTER TABLE `cash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `cash_balance`
--
ALTER TABLE `cash_balance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cash_closing_register`
--
ALTER TABLE `cash_closing_register`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `correspondents`
--
ALTER TABLE `correspondents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `others`
--
ALTER TABLE `others`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pos_calculator`
--
ALTER TABLE `pos_calculator`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rates`
--
ALTER TABLE `rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=401;

--
-- AUTO_INCREMENT de la tabla `transaction_types`
--
ALTER TABLE `transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `types_correspondents`
--
ALTER TABLE `types_correspondents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cash`
--
ALTER TABLE `cash`
  ADD CONSTRAINT `cash_ibfk_1` FOREIGN KEY (`correspondent_id`) REFERENCES `correspondents` (`id`),
  ADD CONSTRAINT `cash_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `correspondents`
--
ALTER TABLE `correspondents`
  ADD CONSTRAINT `correspondents_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `types_correspondents` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `rates`
--
ALTER TABLE `rates`
  ADD CONSTRAINT `rates_ibfk_1` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`);

--
-- Filtros para la tabla `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`correspondent_id`) REFERENCES `correspondents` (`id`),
  ADD CONSTRAINT `shifts_ibfk_2` FOREIGN KEY (`cash_id`) REFERENCES `cash` (`id`);

--
-- Filtros para la tabla `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`id_cashier`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`id_cash`) REFERENCES `cash` (`id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`id_correspondent`) REFERENCES `correspondents` (`id`),
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
