/*
 Navicat Premium Data Transfer

 Source Server         : XAMPP
 Source Server Type    : MySQL
 Source Server Version : 80030
 Source Host           : localhost:3306
 Source Schema         : db_gudang

 Target Server Type    : MySQL
 Target Server Version : 80030
 File Encoding         : 65001

 Date: 07/12/2025 13:25:49
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for goods_in
-- ----------------------------
DROP TABLE IF EXISTS `goods_in`;
CREATE TABLE `goods_in`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `item_id`(`item_id` ASC) USING BTREE,
  CONSTRAINT `goods_in_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of goods_in
-- ----------------------------

-- ----------------------------
-- Table structure for goods_out
-- ----------------------------
DROP TABLE IF EXISTS `goods_out`;
CREATE TABLE `goods_out`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `item_id`(`item_id` ASC) USING BTREE,
  CONSTRAINT `goods_out_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of goods_out
-- ----------------------------

-- ----------------------------
-- Table structure for rfid_registrations
-- ----------------------------
DROP TABLE IF EXISTS `rfid_registrations`;
CREATE TABLE `rfid_registrations`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `name_label` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `pcs` int NOT NULL,
  `rfid_tag` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rfid_registrations
-- ----------------------------

-- ----------------------------
-- Table structure for warehouse_items
-- ----------------------------
DROP TABLE IF EXISTS `warehouse_items`;
CREATE TABLE `warehouse_items`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `storage_location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of warehouse_items
-- ----------------------------

SET FOREIGN_KEY_CHECKS = 1;
