-- =======================================================
-- UniConnect Microservices - Notification Service DB
-- Database: uniconnect_notification_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_notification_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_notification_db`;

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL COMMENT 'Logical reference to uniconnect_auth_db.users.id',
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'Welcome to UniConnect Portal', 'Your student account has been verified and activated for the Fall 2026 academic term.', 'info', 'student/profile.php', 1, '2026-09-01 08:00:00'),
(2, 2, 'Course Enrollment Confirmed', 'You have successfully enrolled in CS201, CS305, SE401, and IT150 for Fall 2026.', 'success', 'student/my-courses.php', 1, '2026-09-02 10:00:00'),
(3, 2, 'Midterm & Final Grades Posted', 'Final examination grades for Data Structures and Full-Stack Development have been finalized.', 'success', 'student/grades.php', 0, '2026-11-15 16:00:00'),
(4, 2, 'Certificate Request Approved', 'Your Enrollment Verification Certificate request (#UC-CERT-2026-0081) has been approved by the Registrar.', 'success', 'student/certificates.php', 0, '2026-10-11 14:15:00'),
(5, 1, 'New Student Registration', 'Student Emily Watson (STD2024004) registered in Computer Science.', 'info', 'admin/students.php', 1, '2026-09-01 09:00:00'),
(6, 1, 'Pending Certificate Request', 'Johnathan Doe submitted a new Official Academic Transcript request.', 'warning', 'admin/certificates.php', 0, '2026-11-20 11:20:00');
