<?php
/**
 * UniConnect Microservices - Request Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/RequestService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = request_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'request-service',
            'database' => DB_REQUEST,
            'status'   => 'healthy',
        ], 'Request service is healthy');
    }

    $service = new RequestService(request_db());

    // ---------------------------------------------------------
    // Internal Service-to-Service Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && preg_match('#^/internal/students/(\d+)/certificates$#', $route, $m)) {
        Response::requireInternal();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        Response::success($service->listStudentCertificates((int)$m[1], $limit));
    }

    if ($method === 'DELETE' && preg_match('#^/internal/students/(\d+)/certificates$#', $route, $m)) {
        Response::requireInternal();
        $deleted = $service->deleteCertificatesByStudent((int)$m[1]);
        Response::success(['deleted' => $deleted], 'Student certificate requests deleted');
    }

    if ($method === 'GET' && $route === '/internal/metrics') {
        Response::requireInternal();
        Response::success($service->getMetrics());
    }

    // ---------------------------------------------------------
    // Certificate Requests Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/certificates') {
        $ctx = Response::requireAuth();
        if ($ctx['role'] === 'admin') {
            $status = trim((string)($_GET['status'] ?? ''));
            $search = trim((string)($_GET['search'] ?? ''));
            Response::success($service->listAdminCertificates($status, $search));
        } else {
            $st = $service->resolveStudent($ctx);
            Response::success($service->listStudentCertificates($st['id']));
        }
    }

    if ($method === 'POST' && $route === '/certificates') {
        $ctx = Response::requireRole('student');
        $st = $service->resolveStudent($ctx);
        $body = Response::getJsonBody();
        $created = $service->submitCertificate($st['id'], $st['student_id'], $ctx, $body);
        Response::success(
            $created,
            "Your request for '{$created['certificate_type']}' (#{$created['reference_no']}) was successfully submitted!",
            201
        );
    }

    if ($method === 'PUT' && preg_match('#^/certificates/(\d+)$#', $route, $m)) {
        $ctx = Response::requireRole('admin');
        $body = Response::getJsonBody();
        $updated = $service->processCertificate((int)$m[1], $body, $ctx['id']);
        Response::success(
            $updated,
            "Certificate request #{$updated['reference_no']} updated to status: " . ucfirst($updated['status']) . "."
        );
    }

    if ($method === 'GET' && preg_match('#^/certificates/(\d+)/view$#', $route, $m)) {
        $ctx = Response::requireAuth();
        Response::success($service->getCertificateForView((int)$m[1], $ctx));
    }

    // ---------------------------------------------------------
    // Contact Inquiries / Messages Endpoints
    // ---------------------------------------------------------
    if ($method === 'POST' && $route === '/messages') {
        // Public landing page inquiry submission
        $body = Response::getJsonBody();
        $created = $service->submitContactMessage($body);
        Response::success($created, 'Inquiry received! Thank you for contacting UniConnect.', 201);
    }

    if ($method === 'GET' && $route === '/messages') {
        Response::requireRole('admin');
        Response::success($service->listMessages());
    }

    if ($method === 'PUT' && preg_match('#^/messages/(\d+)/read$#', $route, $m)) {
        Response::requireRole('admin');
        $service->markMessageRead((int)$m[1]);
        Response::success(null, 'Message marked as read.');
    }

    if ($method === 'DELETE' && preg_match('#^/messages/(\d+)$#', $route, $m)) {
        Response::requireRole('admin');
        $service->deleteMessage((int)$m[1]);
        Response::success(null, 'Message deleted successfully.');
    }

    Response::error("Route not found in request-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (RuntimeException $e) {
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (Throwable $e) {
    Response::error('Request service error: ' . $e->getMessage(), 500);
}
