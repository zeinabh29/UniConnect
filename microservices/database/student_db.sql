-- =======================================================
-- UniConnect Microservices - Student Service DB
-- Database: uniconnect_student_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_student_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_student_db`;

DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE COMMENT 'Logical reference to uniconnect_auth_db.users.id',
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL DEFAULT 'Year 1',
  `semester` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `phone` VARCHAR(30) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `user_id`, `student_id`, `department`, `academic_year`, `semester`, `phone`, `date_of_birth`, `address`) VALUES
(1, 2, 'STD2024001', 'Computer Science', 'Year 3', 'Fall 2026', '+1 (555) 234-5678', '2003-04-15', '124 University Avenue, Campus Dorms, Cityville'),
(2, 3, 'STD2024002', 'Software Engineering', 'Year 2', 'Fall 2026', '+1 (555) 345-6789', '2004-09-22', '45 North Hall, College Heights, Cityville'),
(3, 4, 'STD2024003', 'Information Technology', 'Year 4', 'Fall 2026', '+1 (555) 456-7890', '2002-11-08', '89 Academic Boulevard, West Campus, Cityville'),
(4, 5, 'STD2024004', 'Computer Science', 'Year 1', 'Fall 2026', '+1 (555) 567-8901', '2005-01-30', '31 Elm Street, Suite 4B, Cityville'),
(5, 6, 'STD2024005', 'Data Science', 'Year 2', 'Fall 2026', '+1 (555) 678-9012', '2004-06-18', '77 Science Park Way, Apt 12, Cityville');
