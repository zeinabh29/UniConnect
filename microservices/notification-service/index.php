<?php
/**
 * UniConnect Microservices - Notification Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NotificationService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = notification_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'notification-service',
            'database' => DB_NOTIFICATION,
            'status'   => 'healthy',
        ], 'Notification service is healthy');
    }

    $service = new NotificationService(notification_db());

    // Internal endpoints (service-to-service)
    if ($method === 'POST' && $route === '/internal/notifications') {
        Response::requireInternal();
        $body = Response::getJsonBody();
        $created = $service->create($body);
        Response::success($created, 'Notification created', 201);
    }

    if ($method === 'DELETE' && preg_match('#^/internal/users/(\d+)/notifications$#', $route, $m)) {
        Response::requireInternal();
        $deleted = $service->deleteForUser((int)$m[1]);
        Response::success(['deleted' => $deleted], 'User notifications deleted');
    }

    // Authenticated endpoints
    if ($method === 'GET' && $route === '/notifications') {
        $ctx = Response::requireAuth();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        $list = $service->listForUser($ctx['id'], $limit);
        $unread = $service->getUnreadCount($ctx['id']);
        Response::success([
            'notifications' => $list,
            'unread_count'  => $unread,
        ]);
    }

    if ($method === 'GET' && $route === '/notifications/unread-count') {
        $ctx = Response::requireAuth();
        Response::success([
            'unread_count' => $service->getUnreadCount($ctx['id']),
        ]);
    }

    if ($method === 'POST' && $route === '/notifications/mark-all-read') {
        $ctx = Response::requireAuth();
        $updated = $service->markAllRead($ctx['id']);
        Response::success(['updated' => $updated], 'All notifications marked as read.');
    }

    Response::error("Route not found in notification-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    Response::error('Notification service error: ' . $e->getMessage(), 500);
}
