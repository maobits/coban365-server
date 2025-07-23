-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 23-07-2025 a las 08:56:37
-- Versión del servidor: 10.11.10-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u429495711_coban365`
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `utility` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Utilidad o ganancia de la transacción',
  `is_transfer` tinyint(1) NOT NULL DEFAULT 0 COMMENT '¿Es transferencia entre cajas?',
  `box_reference` int(11) DEFAULT NULL COMMENT 'Caja destino si es transferencia',
  `transfer_status` tinyint(1) DEFAULT NULL COMMENT '1: aceptada, 0: rechazada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(20, 'Ingresos', 'Transaccion prueba', '2025-06-01 16:58:35', '2025-06-01 16:58:35', 1),
(21, 'Ingresos', 'Recarga Nequi', '2025-07-10 17:25:59', '2025-07-10 17:25:59', 1);

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
(1, 'admin@maobits.com', 'Mauricio Chara', '3153774638', '$2y$10$v7ruMRiDLqL0PUH36fbZxOl/jjhUQce48PhilInQzONA2bnAIC55W', 1, 'superadmin', '[\"manageCorrespondents\",\"manageAdministrators\",\"manageReports\",\"manageTransactions\",\"manageCorrespondent\"]', NULL, '2025-03-08 21:10:05', '2025-06-06 12:35:45');

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
-- AUTO_INCREMENT de la tabla `correspondents`
--
ALTER TABLE `correspondents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `others`
--
ALTER TABLE `others`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=614;

--
-- AUTO_INCREMENT de la tabla `transaction_types`
--
ALTER TABLE `transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `types_correspondents`
--
ALTER TABLE `types_correspondents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

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
