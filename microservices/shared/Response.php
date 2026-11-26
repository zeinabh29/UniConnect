<?php
/**
 * UniConnect Microservices Architecture
 * Shared REST Request Parsing, Routing & JSON Response Helpers
 */

class Response {
    /**
     * Sends a JSON response with HTTP status code and terminates execution.
     */
    public static function json($data, $statusCode = 200) {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Internal-Service-Key');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, $message = 'OK', $statusCode = 200, $extra = []) {
        $payload = array_merge([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $extra);
        self::json($payload, $statusCode);
    }

    public static function error($message, $statusCode = 400, $details = null) {
        $payload = [
            'success' => false,
            'error'   => $message,
        ];
        if ($details !== null) {
            $payload['details'] = $details;
        }
        self::json($payload, $statusCode);
    }

    /**
     * Handles CORS preflight requests.
     */
    public static function handlePreflight() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            self::json(['success' => true], 204);
        }
    }

    /**
     * Parses JSON request body (falls back to $_POST for form posts).
     */
    public static function getJsonBody() {
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return !empty($_POST) ? $_POST : [];
    }

    /**
     * Extracts normalized route path relative to the current service directory.
     */
    public static function getRoutePath() {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uriPath, $scriptDir) === 0) {
            $route = substr($uriPath, strlen($scriptDir));
        } else {
            $route = $uriPath;
        }

        // Also strip /index.php if accessed without mod_rewrite
        if (strpos($route, '/index.php') === 0) {
            $route = substr($route, strlen('/index.php'));
        }

        // Support optional ?route=/... query fallback
        if (($route === '' || $route === '/') && !empty($_GET['route'])) {
            $route = '/' . ltrim((string)$_GET['route'], '/');
        }

        $route = '/' . trim((string)$route, '/');
        return $route === '//' ? '/' : $route;
    }

    /**
     * Verifies that request originates from API Gateway or another trusted service.
     */
    public static function isInternalAuthorized() {
        $key = $_SERVER['HTTP_X_INTERNAL_SERVICE_KEY'] ?? '';
        if ($key === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strcasecmp($k, 'X-Internal-Service-Key') === 0) {
                    $key = $v;
                    break;
                }
            }
        }
        return $key !== '' && hash_equals(INTERNAL_SERVICE_KEY, (string)$key);
    }

    /**
     * Resolves authenticated user context either from Gateway trusted headers
     * OR directly from a valid Bearer JWT (allowing standalone service testing).
     */
    public static function getAuthContext() {
        if (self::isInternalAuthorized() && !empty($_SERVER['HTTP_X_USER_ID'])) {
            return [
                'id'           => (int)$_SERVER['HTTP_X_USER_ID'],
                'role'         => $_SERVER['HTTP_X_USER_ROLE'] ?? 'student',
                'name'         => $_SERVER['HTTP_X_USER_NAME'] ?? '',
                'email'        => $_SERVER['HTTP_X_USER_EMAIL'] ?? '',
                'student_pk'   => !empty($_SERVER['HTTP_X_STUDENT_PK']) ? (int)$_SERVER['HTTP_X_STUDENT_PK'] : null,
                'student_id'   => !empty($_SERVER['HTTP_X_STUDENT_CODE']) ? $_SERVER['HTTP_X_STUDENT_CODE'] : null,
                'department'   => !empty($_SERVER['HTTP_X_STUDENT_DEPT']) ? $_SERVER['HTTP_X_STUDENT_DEPT'] : null,
            ];
        }

        $token = JwtHelper::extractBearerToken();
        if ($token) {
            $payload = JwtHelper::decode($token);
            if ($payload && !empty($payload['sub'])) {
                return [
                    'id'           => (int)$payload['sub'],
                    'role'         => $payload['role'] ?? 'student',
                    'name'         => $payload['name'] ?? '',
                    'email'        => $payload['email'] ?? '',
                    'student_pk'   => isset($payload['student_pk']) ? (int)$payload['student_pk'] : null,
                    'student_id'   => $payload['student_code'] ?? null,
                    'department'   => $payload['department'] ?? null,
                ];
            }
        }

        return null;
    }

    public static function requireAuth() {
        $ctx = self::getAuthContext();
        if (!$ctx) {
            self::error('Unauthorized: authentication required.', 401);
        }
        return $ctx;
    }

    public static function requireRole($role) {
        $ctx = self::requireAuth();
        if (($ctx['role'] ?? '') !== $role) {
            self::error("Forbidden: {$role} privileges required.", 403);
        }
        return $ctx;
    }

    public static function requireInternal() {
        if (!self::isInternalAuthorized()) {
            self::error('Forbidden: internal service key required.', 403);
        }
    }
}
