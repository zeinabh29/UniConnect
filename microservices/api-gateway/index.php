<?php
/**
 * UniConnect Microservices - Central API Gateway
 * Handles authentication verification, RBAC enforcement, validation, and HTTP routing.
 */

require_once __DIR__ . '/config.php';

Response::handlePreflight();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$route  = Response::getRoutePath();

try {
    // ---------------------------------------------------------
    // Gateway Info & Aggregated Health Check
    // ---------------------------------------------------------
    if ($method === 'GET' && ($route === '/' || $route === '/health' || $route === '/api/health')) {
        $servicesStatus = [];
        $allHealthy = true;
        foreach (gateway_service_registry() as $name => $url) {
            $res = HttpClient::get($url . '/health');
            $healthy = ($res['status'] === 200 && !empty($res['body']['success']));
            if (!$healthy) {
                $allHealthy = false;
            }
            $servicesStatus[$name] = [
                'url'     => $url,
                'healthy' => $healthy,
                'status'  => $res['status'],
                'details' => $res['body']['data'] ?? ($res['body']['error'] ?? null),
            ];
        }

        Response::success([
            'gateway'  => 'api-gateway',
            'version'  => APP_VERSION,
            'status'   => $allHealthy ? 'healthy' : 'degraded',
            'services' => $servicesStatus,
        ], $allHealthy ? 'All microservices operational' : 'Some microservices degraded', $allHealthy ? 200 : 503);
    }

    // Block external access to internal service endpoints through Gateway
    if (preg_match('#/internal(/|$)#', $route)) {
        Response::error('Forbidden: internal endpoints cannot be accessed via API Gateway.', 403);
    }

    // ---------------------------------------------------------
    // Route Matching
    // ---------------------------------------------------------
    $match = gateway_resolve_route($method, $route);
    if (!$match) {
        Response::error("Gateway route not found: {$method} {$route}", 404);
    }

    // ---------------------------------------------------------
    // Gateway Auth Verification & RBAC Enforcement
    // ---------------------------------------------------------
    $verifiedUser = null;
    $token = JwtHelper::extractBearerToken();

    if ($match['auth']) {
        if (!$token) {
            Response::error('Unauthorized: missing Bearer authentication token.', 401);
        }

        // Fast local signature verification
        $decoded = JwtHelper::decode($token);
        if (!$decoded) {
            Response::error('Unauthorized: invalid or expired authentication token.', 401);
        }

        // Confirm token revocation & active status with auth-service
        $verifyRes = HttpClient::get(SERVICE_AUTH . '/verify', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        if ($verifyRes['status'] !== 200 || empty($verifyRes['body']['success'])) {
            $errMsg = $verifyRes['body']['error'] ?? 'Unauthorized: token verification failed.';
            Response::error($errMsg, $verifyRes['status'] === 503 ? 503 : 401);
        }

        $verifiedUser = $verifyRes['body']['data'];

        // Check role authorization
        if (!empty($match['roles']) && !in_array($verifiedUser['role'] ?? '', $match['roles'], true)) {
            Response::error('Forbidden: insufficient role permissions for this operation.', 403);
        }
    }

    // ---------------------------------------------------------
    // Proxy HTTP Request to Target Microservice
    // ---------------------------------------------------------
    $targetUrl = $match['service_url'] . $match['target_path'];

    // Preserve query parameters (excluding internal fallback route param)
    $queryParams = $_GET;
    unset($queryParams['route']);
    if (!empty($queryParams)) {
        $targetUrl .= (strpos($targetUrl, '?') === false ? '?' : '&') . http_build_query($queryParams);
    }

    $headers = [
        'X-Internal-Service-Key' => INTERNAL_SERVICE_KEY,
    ];
    if (!empty($match['forward_jwt']) && $token) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    if ($verifiedUser) {
        $headers['X-User-Id']      = (string)$verifiedUser['id'];
        $headers['X-User-Role']    = (string)$verifiedUser['role'];
        $headers['X-User-Name']    = (string)$verifiedUser['name'];
        $headers['X-User-Email']   = (string)$verifiedUser['email'];
        $headers['X-Student-Pk']   = (string)($verifiedUser['student_pk'] ?? '');
        $headers['X-Student-Code'] = (string)($verifiedUser['student_id'] ?? '');
        $headers['X-Student-Dept'] = (string)($verifiedUser['department'] ?? '');
    }

    $payload = null;
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $body = Response::getJsonBody();
        if (!empty($body)) {
            $payload = $body;
        }
    }

    $downstream = HttpClient::request($method, $targetUrl, $payload, $headers);
    $status = $downstream['status'];
    $respBody = $downstream['body'];

    if ($downstream['error'] !== null) {
        Response::error('Bad Gateway: ' . $downstream['error'], 502);
    }

    Response::json($respBody, $status);
} catch (Throwable $e) {
    Response::error('API Gateway internal error: ' . $e->getMessage(), 500);
}
