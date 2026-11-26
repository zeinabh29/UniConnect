<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Delete Student Handler
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security token invalid or expired.');
    } elseif ($studentId <= 0) {
        set_flash('danger', 'Invalid student identifier.');
    } else {
        $res = api_delete("/api/admin/students/{$studentId}");
        if ($res['status'] === 200 && !empty($res['body']['success'])) {
            set_flash('success', $res['body']['message'] ?? 'Student deleted successfully.');
        } else {
            set_flash('danger', $res['body']['error'] ?? 'Failed to delete student.');
        }
    }
}

header("Location: " . BASE_URL . "admin/students.php");
exit;
