<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Delete Course Handler
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security session token invalid.');
        header("Location: " . BASE_URL . "admin/courses.php");
        exit;
    }

    if ($courseId <= 0) {
        set_flash('danger', 'Invalid course identifier.');
        header("Location: " . BASE_URL . "admin/courses.php");
        exit;
    }

    $pdo = get_db_connection();

    $stmtCourse = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = ? LIMIT 1");
    $stmtCourse->execute([$courseId]);
    $course = $stmtCourse->fetch();

    if ($course) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmtDel->execute([$courseId]);

            set_flash('success', "Course '{$course['course_code']} &mdash; {$course['course_name']}' was deleted.");
        } catch (Exception $e) {
            set_flash('danger', 'Failed to delete course: ' . $e->getMessage());
        }
    } else {
        set_flash('danger', 'Course not found.');
    }
}

header("Location: " . BASE_URL . "admin/courses.php");
exit;
