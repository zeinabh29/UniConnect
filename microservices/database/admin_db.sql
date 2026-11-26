-- =======================================================
-- UniConnect Microservices - Admin Service DB
-- Database: uniconnect_admin_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_admin_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_admin_db`;

DROP TABLE IF EXISTS `admin_audit_logs`;

CREATE TABLE `admin_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_user_id` INT NOT NULL COMMENT 'Logical reference to uniconnect_auth_db.users.id',
  `admin_name` VARCHAR(100) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` VARCHAR(60) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_admin` (`admin_user_id`),
  INDEX `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_audit_logs` (`admin_user_id`, `admin_name`, `action`, `target_type`, `target_id`, `details`) VALUES
(1, 'System Administrator', 'SYSTEM_MIGRATION_INIT', 'architecture', 'v2.0', 'Initialized UniConnect Microservices Admin Audit Store.');
