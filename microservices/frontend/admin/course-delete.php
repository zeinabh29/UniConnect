<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Delete Course Handler
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId  = (int)($_POST['course_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security session token invalid.');
    } elseif ($courseId <= 0) {
        set_flash('danger', 'Invalid course identifier.');
    } else {
        $res = api_delete("/api/courses/{$courseId}");
        if ($res['status'] === 200 && !empty($res['body']['success'])) {
            set_flash('success', $res['body']['message'] ?? 'Course deleted.');
        } else {
            set_flash('danger', $res['body']['error'] ?? 'Failed to delete course.');
        }
    }
}

header("Location: " . BASE_URL . "admin/courses.php");
exit;
