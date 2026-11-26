<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Delete Student Handler
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security token invalid or expired.');
        header("Location: " . BASE_URL . "admin/students.php");
        exit;
    }

    if ($studentId <= 0) {
        set_flash('danger', 'Invalid student identifier.');
        header("Location: " . BASE_URL . "admin/students.php");
        exit;
    }

    $pdo = get_db_connection();

    // Fetch student's user_id and name
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.student_id, u.name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ? 
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if ($student) {
        try {
            // Delete user - cascading foreign keys will delete students, enrollments, grades, cert requests, notifs
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$student['user_id']]);

            set_flash('success', "Student '{$student['name']}' ({$student['student_id']}) and all related records have been deleted.");
        } catch (Exception $e) {
            set_flash('danger', 'Failed to delete student: ' . $e->getMessage());
        }
    } else {
        set_flash('danger', 'Student not found.');
    }
}

header("Location: " . BASE_URL . "admin/students.php");
exit;
