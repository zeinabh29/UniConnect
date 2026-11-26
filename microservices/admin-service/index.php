<?php
/**
 * UniConnect Microservices - Admin Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AdminService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = admin_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'admin-service',
            'database' => DB_ADMIN,
            'status'   => 'healthy',
        ], 'Admin service is healthy');
    }

    $ctx = Response::requireRole('admin');
    $service = new AdminService(admin_db());

    if ($method === 'GET' && $route === '/dashboard') {
        Response::success($service->getDashboard());
    }

    if ($method === 'POST' && $route === '/students') {
        $body = Response::getJsonBody();
        $created = $service->createStudent($body, $ctx);
        Response::success(
            $created,
            "Student '{$created['name']}' ({$created['student_id']}) has been successfully created!",
            201
        );
    }

    if ($method === 'PUT' && preg_match('#^/students/(\d+)$#', $route, $m)) {
        $body = Response::getJsonBody();
        $updated = $service->updateStudent((int)$m[1], $body, $ctx);
        Response::success($updated, "Student profile for '{$updated['name']}' has been updated successfully.");
    }

    if ($method === 'DELETE' && preg_match('#^/students/(\d+)$#', $route, $m)) {
        $deleted = $service->deleteStudent((int)$m[1], $ctx);
        Response::success(
            $deleted,
            "Student '{$deleted['name']}' ({$deleted['student_id']}) and all related records have been deleted."
        );
    }

    if ($method === 'GET' && $route === '/audit-logs') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        Response::success($service->getAuditLogs($limit));
    }

    Response::error("Route not found in admin-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (RuntimeException $e) {
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (Throwable $e) {
    Response::error('Admin service error: ' . $e->getMessage(), 500);
}
