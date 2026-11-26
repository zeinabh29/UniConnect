-- =======================================================
-- UniConnect: Monolithic University Management System
-- Database Schema & Sample Data
-- Database: uniconnect_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `certificate_requests`;
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------
-- 1. Table structure for table `users`
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- 2. Table structure for table `students`
-- -------------------------------------------------------
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL DEFAULT 'Year 1',
  `semester` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `phone` VARCHAR(30) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Table structure for table `courses`
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- 4. Table structure for table `enrollments`
-- -------------------------------------------------------
CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL DEFAULT '2026-2027',
  `semester` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `status` ENUM('enrolled', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_course_semester` (`student_id`, `course_id`, `semester`),
  CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 5. Table structure for table `grades`
-- -------------------------------------------------------
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
  `graded_by` INT DEFAULT NULL,
  `graded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_grades_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grades_user` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. Table structure for table `certificate_requests`
-- -------------------------------------------------------
CREATE TABLE `certificate_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `certificate_type` VARCHAR(100) NOT NULL,
  `purpose` TEXT NOT NULL,
  `delivery_type` ENUM('digital', 'physical_pickup') NOT NULL DEFAULT 'digital',
  `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
  `admin_remarks` TEXT DEFAULT NULL,
  `reference_no` VARCHAR(60) DEFAULT NULL,
  `processed_by` INT DEFAULT NULL,
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  CONSTRAINT `fk_cert_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 7. Table structure for table `notifications`
-- -------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 8. Table structure for table `contact_messages`
-- -------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `admin_reply` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SAMPLE DATA INSERTION
-- =======================================================

-- Passwords:
-- 'admin123'   -> $2y$10$V9OkbHmHPdNqMp7uc4REgudeNyj/11.tPbJG53880hjVhgZSoYxDi
-- 'student123' -> $2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW

-- Insert Users
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@uniconnect.edu', '$2y$10$V9OkbHmHPdNqMp7uc4REgudeNyj/11.tPbJG53880hjVhgZSoYxDi', 'admin', 'active'),
(2, 'Johnathan Doe', 'john.doe@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(3, 'Sarah Jenkins', 'sarah.j@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(4, 'Michael Chen', 'm.chen@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(5, 'Emily Watson', 'emily.w@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active'),
(6, 'Alex Rivera', 'alex.r@uniconnect.edu', '$2y$10$09Neq1Y5CzFLjErhsrVjOusDGHYArio/N8q/ieHtwTXSQsByMlSJW', 'student', 'active');

-- Insert Students
INSERT INTO `students` (`id`, `user_id`, `student_id`, `department`, `academic_year`, `semester`, `phone`, `date_of_birth`, `address`) VALUES
(1, 2, 'STD2024001', 'Computer Science', 'Year 3', 'Fall 2026', '+1 (555) 234-5678', '2003-04-15', '124 University Avenue, Campus Dorms, Cityville'),
(2, 3, 'STD2024002', 'Software Engineering', 'Year 2', 'Fall 2026', '+1 (555) 345-6789', '2004-09-22', '45 North Hall, College Heights, Cityville'),
(3, 4, 'STD2024003', 'Information Technology', 'Year 4', 'Fall 2026', '+1 (555) 456-7890', '2002-11-08', '89 Academic Boulevard, West Campus, Cityville'),
(4, 5, 'STD2024004', 'Computer Science', 'Year 1', 'Fall 2026', '+1 (555) 567-8901', '2005-01-30', '31 Elm Street, Suite 4B, Cityville'),
(5, 6, 'STD2024005', 'Data Science', 'Year 2', 'Fall 2026', '+1 (555) 678-9012', '2004-06-18', '77 Science Park Way, Apt 12, Cityville');

-- Insert Courses
INSERT INTO `courses` (`id`, `course_code`, `course_name`, `department`, `credits`, `semester`, `instructor_name`, `description`, `max_capacity`, `status`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 'Computer Science', 4, 'Fall 2026', 'Dr. Alan Turing', 'Fundamental concepts of computer programming, algorithms, data types, and problem-solving principles.', 45, 'active'),
(2, 'CS201', 'Data Structures & Algorithms', 'Computer Science', 4, 'Fall 2026', 'Prof. Donald Knuth', 'Comprehensive study of arrays, linked lists, trees, graphs, sorting, searching, and computational complexity.', 40, 'active'),
(3, 'CS305', 'Database Management Systems', 'Computer Science', 3, 'Fall 2026', 'Dr. Edgar Codd', 'Relational data models, SQL querying, normalization, indexing, transaction processing, and database design.', 35, 'active'),
(4, 'SE210', 'Software Engineering Fundamentals', 'Software Engineering', 3, 'Fall 2026', 'Dr. Margaret Hamilton', 'Software development lifecycle, requirements engineering, Agile methodologies, version control, and testing.', 40, 'active'),
(5, 'SE401', 'Full-Stack Web Development', 'Software Engineering', 3, 'Fall 2026', 'Prof. Tim Berners-Lee', 'Modern architectural patterns, MVC, RESTful APIs, responsive interfaces, sessions, security, and deployment.', 35, 'active'),
(6, 'IT150', 'Computer Networks & Security', 'Information Technology', 3, 'Fall 2026', 'Dr. Vint Cerf', 'OSI model, TCP/IP protocols, routing, firewalls, encryption, authentication protocols, and cyber defense.', 40, 'active'),
(7, 'DS200', 'Foundations of Data Science', 'Data Science', 3, 'Fall 2026', 'Dr. Claude Shannon', 'Statistical inference, exploratory data analysis, regression models, data visualization, and Python toolkits.', 35, 'active'),
(8, 'MATH201', 'Discrete Mathematics', 'Mathematics & CS', 3, 'Fall 2026', 'Dr. John von Neumann', 'Logic, set theory, graph theory, combinatorics, proof methods, and Boolean algebra for computer science.', 50, 'active');

-- Insert Enrollments
INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `academic_year`, `semester`, `status`) VALUES
-- Student 1 (Johnathan Doe)
(1, 1, 2, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(2, 1, 3, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(3, 1, 5, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(4, 1, 6, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),

-- Student 2 (Sarah Jenkins)
(5, 2, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(6, 2, 4, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(7, 2, 8, '2026-09-03', '2026-2027', 'Fall 2026', 'enrolled'),

-- Student 3 (Michael Chen)
(8, 3, 3, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(9, 3, 5, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(10, 3, 6, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),
(11, 3, 7, '2026-09-03', '2026-2027', 'Fall 2026', 'enrolled'),

-- Student 4 (Emily Watson)
(12, 4, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(13, 4, 8, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled'),

-- Student 5 (Alex Rivera)
(14, 5, 1, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(15, 5, 7, '2026-09-01', '2026-2027', 'Fall 2026', 'enrolled'),
(16, 5, 8, '2026-09-02', '2026-2027', 'Fall 2026', 'enrolled');

-- Insert Grades
-- Grading Scheme:
-- Assignment 20%, Midterm 30%, Final 50%
INSERT INTO `grades` (`id`, `enrollment_id`, `assignment_score`, `midterm_score`, `final_score`, `total_score`, `letter_grade`, `grade_point`, `remarks`, `graded_by`) VALUES
(1, 1, 92.00, 88.00, 91.00, 90.30, 'A', 4.00, 'Excellent grasp of balanced search trees and graph algorithms.', 1),
(2, 2, 85.00, 87.00, 89.00, 87.60, 'B+', 3.50, 'Very good query design and relational normalization.', 1),
(3, 3, 96.00, 94.00, 95.00, 94.90, 'A+', 4.00, 'Exceptional monolithic architecture and clean code implementation.', 1),
(4, 4, 88.00, 82.00, 84.00, 84.20, 'B', 3.00, 'Good understanding of network routing and packet inspection.', 1),

(5, 5, 94.00, 91.00, 93.00, 92.60, 'A', 4.00, 'Mastered fundamental programming paradigms and debugging.', 1),
(6, 6, 90.00, 89.00, 92.00, 90.70, 'A', 4.00, 'Great teamwork and requirements analysis documentation.', 1),
(7, 7, 82.00, 80.00, 85.00, 82.90, 'B', 3.00, 'Consistent work throughout discrete proofs and logic.', 1),

(8, 8, 91.00, 89.00, 93.00, 91.40, 'A', 4.00, 'Top tier indexing project and transaction isolation models.', 1),
(9, 9, 89.00, 92.00, 90.00, 90.40, 'A', 4.00, 'Great backend architecture and RESTful endpoint design.', 1),
(10, 10, 84.00, 86.00, 88.00, 86.60, 'B+', 3.50, 'Thorough firewall configuration and security auditing report.', 1),
(11, 11, 95.00, 93.00, 96.00, 94.90, 'A+', 4.00, 'Exemplary statistical modeling and data visualization.', 1);

-- Insert Certificate Requests
INSERT INTO `certificate_requests` (`id`, `student_id`, `certificate_type`, `purpose`, `delivery_type`, `status`, `admin_remarks`, `reference_no`, `processed_by`, `requested_at`, `processed_at`) VALUES
(1, 1, 'Enrollment Verification Certificate', 'Required by national health insurance provider for annual dependent status verification.', 'digital', 'approved', 'Verified active full-time enrollment for Fall 2026 academic semester.', 'UC-CERT-2024-0081', 1, '2024-10-10 09:30:00', '2024-10-11 14:15:00'),
(2, 1, 'Official Academic Transcript', 'Application for Microsoft Summer Software Engineering Internship program.', 'digital', 'pending', NULL, 'UC-CERT-2024-0094', NULL, '2024-11-20 11:20:00', NULL),
(3, 2, 'Good Conduct & Character Certificate', 'Application for university exchange program in Germany.', 'physical_pickup', 'approved', 'Certificate printed and stamped at Registrar Office, ready for physical pickup.', 'UC-CERT-2024-0075', 1, '2024-10-05 15:45:00', '2024-10-07 10:00:00'),
(4, 3, 'Degree Completion Certificate', 'Pre-graduation employment verification for upcoming full-time associate role.', 'digital', 'pending', NULL, 'UC-CERT-2024-0102', NULL, '2024-11-22 08:45:00', NULL),
(5, 5, 'English Language Proficiency Certificate', 'Submission to international student symposium scholarship committee.', 'digital', 'completed', 'Standard academic medium of instruction verification issued.', 'UC-CERT-2024-0063', 1, '2024-09-18 13:00:00', '2024-09-19 16:30:00');

-- Insert Notifications
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'Welcome to UniConnect Portal', 'Your student account has been verified and activated for the Fall 2026 academic term.', 'info', 'student/profile.php', 1, '2024-09-01 08:00:00'),
(2, 2, 'Course Enrollment Confirmed', 'You have successfully enrolled in CS201, CS305, SE401, and IT150 for Fall 2026.', 'success', 'student/my-courses.php', 1, '2024-09-02 10:00:00'),
(3, 2, 'Midterm & Final Grades Posted', 'Final examination grades for Data Structures and Full-Stack Development have been finalized.', 'success', 'student/grades.php', 0, '2024-11-15 16:00:00'),
(4, 2, 'Certificate Request Approved', 'Your Enrollment Verification Certificate request (#UC-CERT-2024-0081) has been approved by the Registrar.', 'success', 'student/certificates.php', 0, '2024-10-11 14:15:00'),
(5, 1, 'New Student Registration', 'Student Emily Watson (STD2024004) registered in Computer Science.', 'info', 'admin/students.php', 1, '2024-09-01 09:00:00'),
(6, 1, 'Pending Certificate Request', 'Johnathan Doe submitted a new Official Academic Transcript request.', 'warning', 'admin/certificates.php', 0, '2024-11-20 11:20:00');

-- Insert Contact Messages
INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `admin_reply`, `created_at`) VALUES
(1, 'Marcus Vance', 'marcus.vance@gmail.com', 'Inquiry regarding Transfer Credit Evaluation', 'Hello, I am currently a sophomore at another university and interested in transferring to UniConnect for Computer Science. Could you please let me know the syllabus evaluation process?', 1, 'Hello Marcus, thank you for reaching out. Please submit your official transcript and course syllabi to admissions@uniconnect.edu for preliminary evaluation.', '2024-10-12 11:15:00'),
(2, 'Helena Troy', 'helena.t@outlook.com', 'Scholarship Application Deadline 2025', 'Good day, I would like to confirm the deadline for merit-based scholarship applications for incoming Spring 2025 undergraduates.', 0, NULL, '2024-11-24 14:20:00'),
(3, 'Robert Martinez', 'r.martinez@techcorp.com', 'Campus Career Fair Spring 2025 Booth', 'Dear University Administration, our tech company would like to reserve a booth at the upcoming university career fair. Whom should we contact?', 0, NULL, '2024-11-25 09:30:00');
