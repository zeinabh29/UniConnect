-- =======================================================
-- UniConnect Microservices - Request Service DB
-- Database: uniconnect_request_db
-- =======================================================

CREATE DATABASE IF NOT EXISTS `uniconnect_request_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniconnect_request_db`;

DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `certificate_requests`;

CREATE TABLE `certificate_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL COMMENT 'Logical reference to uniconnect_student_db.students.id',
  `certificate_type` VARCHAR(100) NOT NULL,
  `purpose` TEXT NOT NULL,
  `delivery_type` ENUM('digital', 'physical_pickup') NOT NULL DEFAULT 'digital',
  `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
  `admin_remarks` TEXT DEFAULT NULL,
  `reference_no` VARCHAR(60) DEFAULT NULL,
  `processed_by` INT DEFAULT NULL COMMENT 'Logical reference to uniconnect_auth_db.users.id',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  INDEX `idx_cert_student` (`student_id`),
  INDEX `idx_cert_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO `certificate_requests` (`id`, `student_id`, `certificate_type`, `purpose`, `delivery_type`, `status`, `admin_remarks`, `reference_no`, `processed_by`, `requested_at`, `processed_at`) VALUES
(1, 1, 'Enrollment Verification Certificate', 'Required by national health insurance provider for annual dependent status verification.', 'digital', 'approved', 'Verified active full-time enrollment for Fall 2026 academic semester.', 'UC-CERT-2026-0081', 1, '2026-10-10 09:30:00', '2026-10-11 14:15:00'),
(2, 1, 'Official Academic Transcript', 'Application for Microsoft Summer Software Engineering Internship program.', 'digital', 'pending', NULL, 'UC-CERT-2026-0094', NULL, '2026-11-20 11:20:00', NULL),
(3, 2, 'Good Conduct & Character Certificate', 'Application for university exchange program in Germany.', 'physical_pickup', 'approved', 'Certificate printed and stamped at Registrar Office, ready for physical pickup.', 'UC-CERT-2026-0075', 1, '2026-10-05 15:45:00', '2026-10-07 10:00:00'),
(4, 3, 'Degree Completion Certificate', 'Pre-graduation employment verification for upcoming full-time associate role.', 'digital', 'pending', NULL, 'UC-CERT-2026-0102', NULL, '2026-11-22 08:45:00', NULL),
(5, 5, 'English Language Proficiency Certificate', 'Submission to international student symposium scholarship committee.', 'digital', 'completed', 'Standard academic medium of instruction verification issued.', 'UC-CERT-2026-0063', 1, '2026-09-18 13:00:00', '2026-09-19 16:30:00');

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `admin_reply`, `created_at`) VALUES
(1, 'Marcus Vance', 'marcus.vance@gmail.com', 'Inquiry regarding Transfer Credit Evaluation', 'Hello, I am currently a sophomore at another university and interested in transferring to UniConnect for Computer Science. Could you please let me know the syllabus evaluation process?', 1, 'Hello Marcus, thank you for reaching out. Please submit your official transcript and course syllabi to admissions@uniconnect.edu for preliminary evaluation.', '2026-10-12 11:15:00'),
(2, 'Helena Troy', 'helena.t@outlook.com', 'Scholarship Application Deadline 2027', 'Good day, I would like to confirm the deadline for merit-based scholarship applications for incoming Spring 2027 undergraduates.', 0, NULL, '2026-11-24 14:20:00'),
(3, 'Robert Martinez', 'r.martinez@techcorp.com', 'Campus Career Fair Spring 2027 Booth', 'Dear University Administration, our tech company would like to reserve a booth at the upcoming university career fair. Whom should we contact?', 0, NULL, '2026-11-25 09:30:00');
