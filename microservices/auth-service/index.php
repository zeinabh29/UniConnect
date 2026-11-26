<?php
/**
 * UniConnect Microservices - Authentication Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AuthService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = auth_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'auth-service',
            'database' => DB_AUTH,
            'status'   => 'healthy',
        ], 'Authentication service is healthy');
    }

    $service = new AuthService(auth_db());

    // ---------------------------------------------------------
    // Public Authentication Endpoints
    // ---------------------------------------------------------
    if ($method === 'POST' && $route === '/login') {
        $body = Response::getJsonBody();
        $res = $service->login($body['identifier'] ?? '', $body['password'] ?? '');
        Response::success($res, 'Welcome back, ' . $res['user']['name'] . '!');
    }

    if ($method === 'POST' && $route === '/register') {
        $body = Response::getJsonBody();
        $res = $service->registerStudent($body);
        Response::success(
            $res,
            "Registration successful! Welcome to UniConnect, {$res['user']['name']}. Your Student ID is {$res['user']['student_id']}.",
            201
        );
    }

    if ($method === 'GET' && $route === '/verify') {
        $token = JwtHelper::extractBearerToken();
        $verified = $service->verifyToken($token);
        Response::success($verified, 'Token is valid');
    }

    if ($method === 'POST' && $route === '/logout') {
        $token = JwtHelper::extractBearerToken();
        $service->logout($token);
        Response::success(null, 'You have been successfully logged out.');
    }

    if ($method === 'GET' && $route === '/me') {
        $ctx = Response::requireAuth();
        $user = $service->getUserById($ctx['id']);
        if (!$user) {
            Response::error('User not found', 404);
        }
        Response::success([
            'user' => array_merge($user, [
                'student_pk' => $ctx['student_pk'],
                'student_id' => $ctx['student_id'],
                'department' => $ctx['department'],
            ]),
        ]);
    }

    if ($method === 'PUT' && $route === '/password') {
        $ctx = Response::requireAuth();
        $body = Response::getJsonBody();
        $service->changePassword(
            $ctx['id'],
            $body['current_password'] ?? '',
            $body['new_password'] ?? '',
            $body['confirm_password'] ?? ''
        );
        Response::success(null, 'Password updated successfully!');
    }

    // ---------------------------------------------------------
    // Internal Service-to-Service Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/internal/users/batch') {
        Response::requireInternal();
        $ids = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))));
        Response::success($service->getUsersBatch($ids));
    }

    if ($method === 'GET' && $route === '/internal/admin-ids') {
        Response::requireInternal();
        Response::success(array_map('intval', $service->getAdminIds()));
    }

    if ($method === 'GET' && preg_match('#^/internal/users/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $user = $service->getUserById((int)$m[1]);
        if (!$user) {
            Response::error('User not found', 404);
        }
        Response::success($user);
    }

    if ($method === 'POST' && $route === '/internal/users') {
        Response::requireInternal();
        $body = Response::getJsonBody();
        $created = $service->createUserInternal($body);
        Response::success($created, 'User created', 201);
    }

    if ($method === 'PUT' && preg_match('#^/internal/users/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $body = Response::getJsonBody();
        $updated = $service->updateUserInternal((int)$m[1], $body);
        Response::success($updated, 'User updated');
    }

    if ($method === 'DELETE' && preg_match('#^/internal/users/(\d+)$#', $route, $m)) {
        Response::requireInternal();
        $deleted = $service->deleteUserInternal((int)$m[1]);
        Response::success($deleted, 'User deleted');
    }

    Response::error("Route not found in auth-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (RuntimeException $e) {
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (Throwable $e) {
    Response::error('Auth service error: ' . $e->getMessage(), 500);
}
