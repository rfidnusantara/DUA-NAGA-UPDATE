-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 07, 2025 at 09:37 AM
-- Server version: 8.0.30
-- PHP Version: 8.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_gudang`
--

-- --------------------------------------------------------

--
-- Table structure for table `goods_in`
--

CREATE TABLE `goods_in` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `goods_out`
--

CREATE TABLE `goods_out` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `rfid_registrations`
--

CREATE TABLE `rfid_registrations` (
  `id` int NOT NULL,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name_label` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcs` int NOT NULL,
  `rfid_tag` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `rfid_registrations`
--

INSERT INTO `rfid_registrations` (`id`, `api_product_id`, `product_name`, `po_number`, `so_number`, `name_label`, `batch_number`, `pcs`, `rfid_tag`, `is_active`, `created_at`) VALUES
(1, 759, 'Bright And Glow All Day Acne Soap', '018/ZAN/PO/XI/2025', '018/ZAN/PO/XI/2025 - A02', 'Tes', '', 1000, 'E28069150000401AD9A45C3D', 0, '2025-12-07 05:53:07'),
(2, 759, 'Bright And Glow All Day Acne Soap', '018/ZAN/PO/XI/2025', '018/ZAN/PO/XI/2025 - A02', 'tes', '1', 1000, 'E28069150000501AD9A4583D', 1, '2025-12-07 06:24:35');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint UNSIGNED NOT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `registration_id` bigint UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `movement_time` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `rfid_tag`, `registration_id`, `warehouse_id`, `movement_type`, `movement_time`, `created_at`, `created_by`, `notes`) VALUES
(1, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 06:45:02', '2025-12-07 06:45:02', NULL, ''),
(2, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 06:45:02', '2025-12-07 06:45:02', NULL, ''),
(3, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 06:45:27', '2025-12-07 06:45:27', NULL, ''),
(4, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 06:45:27', '2025-12-07 06:45:27', NULL, ''),
(5, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 07:09:16', '2025-12-07 07:09:16', NULL, ''),
(6, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 07:09:16', '2025-12-07 07:09:16', NULL, ''),
(7, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 07:09:25', '2025-12-07 07:09:25', NULL, ''),
(8, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 07:09:25', '2025-12-07 07:09:25', NULL, ''),
(9, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-07 14:18:02', '2025-12-07 07:18:02', NULL, ''),
(10, 'E28069150000501AD9A4583D', 2, 3, 'OUT', '2025-12-07 14:18:02', '2025-12-07 07:18:02', NULL, ''),
(11, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 14:18:22', '2025-12-07 07:18:22', NULL, ''),
(12, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 14:18:22', '2025-12-07 07:18:22', NULL, ''),
(13, 'E28069150000401AD9A45C3D', 1, 3, 'IN', '2025-12-07 14:18:52', '2025-12-07 07:18:52', NULL, ''),
(14, 'E28069150000501AD9A4583D', 2, 3, 'IN', '2025-12-07 14:18:52', '2025-12-07 07:18:52', NULL, ''),
(15, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 14:23:49', '2025-12-07 07:23:49', NULL, ''),
(16, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 14:23:49', '2025-12-07 07:23:49', NULL, ''),
(17, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 14:24:03', '2025-12-07 07:24:03', NULL, ''),
(18, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 14:24:03', '2025-12-07 07:24:03', NULL, ''),
(19, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:29:27', '2025-12-07 07:29:27', NULL, ''),
(20, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:29:27', '2025-12-07 07:29:27', NULL, ''),
(21, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 14:34:14', '2025-12-07 07:34:14', 'admin', ''),
(22, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 14:34:14', '2025-12-07 07:34:14', 'admin', ''),
(23, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:34:52', '2025-12-07 07:34:52', 'admin', ''),
(24, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:34:52', '2025-12-07 07:34:52', 'admin', ''),
(25, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:42:07', '2025-12-07 07:42:07', 'admin', ''),
(26, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:42:07', '2025-12-07 07:42:07', 'admin', ''),
(27, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 15:17:05', '2025-12-07 08:17:05', 'admin', ''),
(28, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 15:17:05', '2025-12-07 08:17:05', 'admin', ''),
(29, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:20:28', '2025-12-07 08:20:28', 'admin', ''),
(30, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:20:28', '2025-12-07 08:20:28', 'admin', ''),
(31, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 15:27:19', '2025-12-07 08:27:19', 'admin', ''),
(32, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 15:27:19', '2025-12-07 08:27:19', 'admin', ''),
(33, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:36:05', '2025-12-07 08:36:05', 'admin', ''),
(34, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:36:05', '2025-12-07 08:36:05', 'admin', ''),
(35, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 15:41:07', '2025-12-07 08:41:07', 'admin', ''),
(36, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 15:41:07', '2025-12-07 08:41:07', 'admin', ''),
(37, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:48:30', '2025-12-07 08:48:30', 'admin', ''),
(38, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:48:30', '2025-12-07 08:48:30', 'admin', ''),
(41, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 16:01:55', '2025-12-07 09:01:55', 'admin', ''),
(42, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 16:01:55', '2025-12-07 09:01:55', 'admin', ''),
(43, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 16:17:33', '2025-12-07 09:17:33', 'admin', '');

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan`
--

CREATE TABLE `surat_jalan` (
  `id` bigint UNSIGNED NOT NULL,
  `no_sj` varchar(50) NOT NULL,
  `tanggal_sj` date NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_address` varchar(255) NOT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `surat_jalan`
--

INSERT INTO `surat_jalan` (`id`, `no_sj`, `tanggal_sj`, `customer_name`, `customer_address`, `po_number`, `warehouse_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 'SJ/2512/0001', '2025-12-07', 'A', 'A', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 07:42:07'),
(2, 'SJ/2512/0002', '2025-12-07', 'A', 'A', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:20:28'),
(3, 'SJ/2512/0003', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:36:05'),
(4, 'SJ/2512/0004', '2025-12-07', 'tes', 'tes', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:48:30'),
(5, 'SJ/2512/0005', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 09:17:33');

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan_items`
--

CREATE TABLE `surat_jalan_items` (
  `id` bigint UNSIGNED NOT NULL,
  `surat_jalan_id` bigint UNSIGNED NOT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `unit` varchar(20) DEFAULT 'PCS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `surat_jalan_items`
--

INSERT INTO `surat_jalan_items` (`id`, `surat_jalan_id`, `rfid_tag`, `product_name`, `batch_number`, `qty`, `unit`) VALUES
(1, 1, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(2, 1, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(3, 2, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(4, 2, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(5, 3, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(6, 3, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(7, 4, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(8, 4, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(9, 5, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'admin123', 'admin', 1, '2025-12-07 07:02:43');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Gudang A', 'GUDANG_A', 'Gudang utama', '2025-12-07 06:39:39', '2025-12-07 08:38:46'),
(2, 'Gudang B', 'GUDANG_B', 'Gudang bahan baku', '2025-12-07 06:39:39', '2025-12-07 06:39:39'),
(3, 'Gudang C', 'GUDANG_C', 'Gudang finished goods', '2025-12-07 06:39:39', '2025-12-07 06:39:39');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_items`
--

CREATE TABLE `warehouse_items` (
  `id` int NOT NULL,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `storage_location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `goods_in`
--
ALTER TABLE `goods_in`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `item_id` (`item_id`) USING BTREE;

--
-- Indexes for table `goods_out`
--
ALTER TABLE `goods_out`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `item_id` (`item_id`) USING BTREE;

--
-- Indexes for table `rfid_registrations`
--
ALTER TABLE `rfid_registrations`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tag_time` (`rfid_tag`,`movement_time`),
  ADD KEY `idx_wh_type_time` (`warehouse_id`,`movement_type`,`movement_time`);

--
-- Indexes for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sj_items_header` (`surat_jalan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_warehouses_code` (`code`);

--
-- Indexes for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `goods_in`
--
ALTER TABLE `goods_in`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goods_out`
--
ALTER TABLE `goods_out`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rfid_registrations`
--
ALTER TABLE `rfid_registrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `goods_in`
--
ALTER TABLE `goods_in`
  ADD CONSTRAINT `goods_in_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `goods_out`
--
ALTER TABLE `goods_out`
  ADD CONSTRAINT `goods_out_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD CONSTRAINT `fk_sj_items_header` FOREIGN KEY (`surat_jalan_id`) REFERENCES `surat_jalan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
