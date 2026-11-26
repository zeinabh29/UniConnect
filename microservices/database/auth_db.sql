-- =======================================================
-- UniConnect Microservices - Authentication Service DB
-- Database: uniconnect_auth_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_auth_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_auth_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `auth_tokens`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_jti` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token_user` (`user_id`),
  CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@uniconnect.edu', '$2y$10$V9OkbHmHPdNqMp7uc4REgudeNyj/11.tPbJG53880hjVhgZSoYxDi', 'admin', 'active'),
(2, 'Johnathan Doe', 'john.doe@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(3, 'Sarah Jenkins', 'sarah.j@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(4, 'Michael Chen', 'm.chen@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(5, 'Emily Watson', 'emily.w@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(6, 'Alex Rivera', 'alex.r@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active');
