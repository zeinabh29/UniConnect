<?php
/**
 * UniConnect Microservices - Student Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/StudentService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = student_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'student-service',
            'database' => DB_STUDENT,
            'status'   => 'healthy',
        ], 'Student service is healthy');
    }

    $service = new StudentService(student_db());

    // ---------------------------------------------------------
    // Internal Service-to-Service Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && preg_match('#^/internal/by-student-code/(.+)$#', $route, $m)) {
        Response::requireInternal();
        $student = $service->getByCode(urldecode($m[1]));
        if (!$student) {
            Response::error('Student not found', 404);
        }
        Response::success($student);
    }

    if ($method === 'GET' && preg_match('#^/internal/by-user/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $student = $service->getByUserId((int)$m[1]);
        if (!$student) {
            Response::error('Student not found for user', 404);
        }
        Response::success($student);
    }

    if ($method === 'GET' && preg_match('#^/internal/check-code/(.+)$#', $route, $m)) {
        Response::requireInternal();
        Response::success(['exists' => $service->codeExists(urldecode($m[1]))]);
    }

    if ($method === 'GET' && $route === '/internal/next-id') {
        Response::requireInternal();
        Response::success(['next_student_id' => $service->generateNextStudentId()]);
    }

    if ($method === 'GET' && $route === '/internal/students/batch') {
        Response::requireInternal();
        $idsRaw = $_GET['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', (string)$idsRaw)));
        Response::success($service->getBatchEnriched($ids));
    }

    if ($method === 'GET' && preg_match('#^/internal/students/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $s = $service->getEnrichedById((int)$m[1]);
        if (!$s) {
            Response::error('Student not found', 404);
        }
        Response::success($s);
    }

    if ($method === 'POST' && $route === '/internal/students') {
        Response::requireInternal();
        $body = Response::getJsonBody();
        $created = $service->createStudent($body);
        Response::success($created, 'Student profile created', 201);
    }

    if ($method === 'PUT' && preg_match('#^/internal/students/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $body = Response::getJsonBody();
        $updated = $service->updateStudent((int)$m[1], $body, false);
        Response::success($updated, 'Student profile updated');
    }

    if ($method === 'DELETE' && preg_match('#^/internal/students/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $deleted = $service->deleteStudent((int)$m[1]);
        Response::success($deleted, 'Student profile deleted');
    }

    if ($method === 'GET' && $route === '/internal/metrics') {
        Response::requireInternal();
        Response::success($service->getMetrics());
    }

    // ---------------------------------------------------------
    // Authenticated Student Portal Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/dashboard') {
        $ctx = Response::requireRole('student');
        Response::success($service->getStudentDashboard($ctx['id']));
    }

    if ($method === 'GET' && $route === '/profile') {
        $ctx = Response::requireRole('student');
        $raw = $service->getByUserId($ctx['id']);
        if (!$raw) {
            Response::error('Student profile not found.', 404);
        }
        Response::success($service->getEnrichedById($raw['id']));
    }

    if ($method === 'PUT' && $route === '/profile') {
        $ctx = Response::requireRole('student');
        $raw = $service->getByUserId($ctx['id']);
        if (!$raw) {
            Response::error('Student profile not found.', 404);
        }
        $body = Response::getJsonBody();
        $updated = $service->updateStudent($raw['id'], $body, true);
        Response::success($updated, 'Profile information updated successfully.');
    }

    // ---------------------------------------------------------
    // Admin Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/students/next-id') {
        Response::requireRole('admin');
        Response::success(['next_student_id' => $service->generateNextStudentId()]);
    }

    if ($method === 'GET' && $route === '/students') {
        Response::requireRole('admin');
        $search = trim((string)($_GET['search'] ?? ''));
        $dept   = trim((string)($_GET['department'] ?? ''));
        $year   = trim((string)($_GET['year'] ?? ''));
        Response::success($service->listStudents($search, $dept, $year));
    }

    if ($method === 'GET' && preg_match('#^/students/(\d+)$#', $route, $m)) {
        Response::requireRole('admin');
        $s = $service->getEnrichedById((int)$m[1]);
        if (!$s) {
            Response::error('Student not found.', 404);
        }
        Response::success($s);
    }

    Response::error("Route not found in student-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (RuntimeException $e) {
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (Throwable $e) {
    Response::error('Student service error: ' . $e->getMessage(), 500);
}
