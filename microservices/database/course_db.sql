-- =======================================================
-- UniConnect Microservices - Course & Grading Service DB
-- Database: uniconnect_course_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_course_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_course_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `courses`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(30) NOT NULL UNIQUE,
  `course_name` VARCHAR(150) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `credits` INT NOT NULL DEFAULT 3,
  `semester` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `instructor_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_capacity` INT NOT NULL DEFAULT 40,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL COMMENT 'Logical reference to uniconnect_student_db.students.id',
  `course_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL DEFAULT '2026-2027',
  `semester` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `status` ENUM('enrolled', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_enrollments_student` (`student_id`),
  UNIQUE KEY `unique_student_course_semester` (`student_id`, `course_id`, `semester`),
  CONSTRAINT `fk_course_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `grades` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_id` INT NOT NULL UNIQUE,
  `assignment_score` DECIMAL(5,2) DEFAULT 0.00,
  `midterm_score` DECIMAL(5,2) DEFAULT 0.00,
  `final_score` DECIMAL(5,2) DEFAULT 0.00,
  `total_score` DECIMAL(5,2) DEFAULT 0.00,
  `letter_grade` VARCHAR(5) DEFAULT 'F',
  `grade_point` DECIMAL(3,2) DEFAULT 0.00,
  `remarks` VARCHAR(255) DEFAULT NULL,
  `graded_by` INT DEFAULT NULL COMMENT 'Logical reference to uniconnect_auth_db.users.id',
  `graded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_course_grades_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `courses` (`id`, `course_code`, `course_name`, `department`, `credits`, `semester`, `instructor_name`, `description`, `max_capacity`, `status`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 'Computer Science', 4, 'Fall 2026', 'Dr. Alan Turing', 'Fundamental concepts of computer programming, algorithms, data types, and problem-solving principles.', 45, 'active'),
(2, 'CS201', 'Data Structures & Algorithms', 'Computer Science', 4, 'Fall 2026', 'Prof. Donald Knuth', 'Comprehensive study of arrays, linked lists, trees, graphs, sorting, searching, and computational complexity.', 40, 'active'),
(3, 'CS305', 'Database Management Systems', 'Computer Science', 3, 'Fall 2026', 'Dr. Edgar Codd', 'Relational data models, SQL querying, normalization, indexing, transaction processing, and database design.', 35, 'active'),
(4, 'SE210', 'Software Engineering Fundamentals', 'Software Engineering', 3, 'Fall 2026', 'Dr. Margaret Hamilton', 'Software development lifecycle, requirements engineering, Agile methodologies, version control, and testing.', 40, 'active'),
(5, 'SE401', 'Full-Stack Web Development', 'Software Engineering', 3, 'Fall 2026', 'Prof. Tim Berners-Lee', 'Modern architectural patterns, MVC, RESTful APIs, responsive interfaces, sessions, security, and deployment.', 35, 'active'),
(6, 'IT150', 'Computer Networks & Security', 'Information Technology', 3, 'Fall 2026', 'Dr. Vint Cerf', 'OSI model, TCP/IP protocols, routing, firewalls, encryption, authentication protocols, and cyber defense.', 40, 'active'),
(7, 'DS200', 'Foundations of Data Science', 'Data Science', 3, 'Fall 2026', 'Dr. Claude Shannon', 'Statistical inference, exploratory data analysis, regression models, data visualization, and Python toolkits.', 35, 'active'),
(8, 'MATH201', 'Discrete Mathematics', 'Mathematics & CS', 3, 'Fall 2026', 'Dr. John von Neumann', 'Logic, set theory, graph theory, combinatorics, proof methods, and Boolean algebra for computer science.', 50, 'active');

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `academic_year`, `semester`, `status`) VALUES
(1, 1, 2, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(2, 1, 3, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(3, 1, 5, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(4, 1, 6, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(5, 2, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(6, 2, 4, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(7, 2, 8, '2026-09-03', '2026-2027', 'Fall 2026', 'enrolled'),
(8, 3, 3, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(9, 3, 5, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(10, 3, 6, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(11, 3, 7, '2026-09-03', '2026-2027', 'Fall 2026', 'enrolled'),
(12, 4, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(13, 4, 8, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(14, 5, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(15, 5, 7, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(16, 5, 8, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled');

INSERT INTO `grades` (`id`, `enrollment_id`, `assignment_score`, `midterm_score`, `final_score`, `total_score`, `letter_grade`, `grade_point`, `remarks`, `graded_by`) VALUES
(1, 1, 92.00, 88.00, 91.00, 90.30, 'A', 4.00, 'Excellent grasp of balanced search trees and graph algorithms.', 1),
(2, 2, 85.00, 87.00, 89.00, 87.60, 'B+', 3.50, 'Very good query design and relational normalization.', 1),
(3, 3, 96.00, 94.00, 95.00, 94.90, 'A+', 4.00, 'Exceptional architecture and clean code implementation.', 1),
(4, 4, 88.00, 82.00, 84.00, 84.20, 'B', 3.00, 'Good understanding of network routing and packet inspection.', 1),
(5, 5, 94.00, 91.00, 93.00, 92.60, 'A', 4.00, 'Mastered fundamental programming paradigms and debugging.', 1),
(6, 6, 90.00, 89.00, 92.00, 90.70, 'A', 4.00, 'Great teamwork and requirements analysis documentation.', 1),
(7, 7, 82.00, 80.00, 85.00, 82.90, 'B', 3.00, 'Consistent work throughout discrete proofs and logic.', 1),
(8, 8, 91.00, 89.00, 93.00, 91.40, 'A', 4.00, 'Top tier indexing project and transaction isolation models.', 1),
(9, 9, 89.00, 92.00, 90.00, 90.40, 'A', 4.00, 'Great backend architecture and RESTful endpoint design.', 1),
(10, 10, 84.00, 86.00, 88.00, 86.60, 'B+', 3.50, 'Thorough firewall configuration and security auditing report.', 1),
(11, 11, 95.00, 93.00, 96.00, 94.90, 'A+', 4.00, 'Exemplary statistical modeling and data visualization.', 1);
