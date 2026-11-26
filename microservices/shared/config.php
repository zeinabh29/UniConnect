<?php
/**
 * UniConnect Microservices Architecture
 * Shared Global Configuration & Service Discovery
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'UniConnect');
    define('APP_TAGLINE', 'Unified Microservices University Management Portal');
    define('APP_VERSION', '2.0.0-ms');
}

// Dynamic Academic Term & Year Calculation
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');

if ($currentMonth >= 9) {
    $computedSemester = "Fall {$currentYear}";
    $computedAcadYear = "{$currentYear}-" . ($currentYear + 1);
} elseif ($currentMonth >= 6) {
    $computedSemester = "Summer {$currentYear}";
    $computedAcadYear = ($currentYear - 1) . "-{$currentYear}";
} else {
    $computedSemester = "Spring {$currentYear}";
    $computedAcadYear = ($currentYear - 1) . "-{$currentYear}";
}

if (!defined('DEFAULT_ACADEMIC_YEAR')) {
    define('DEFAULT_ACADEMIC_YEAR', $computedAcadYear);
}
if (!defined('DEFAULT_SEMESTER')) {
    define('DEFAULT_SEMESTER', $computedSemester);
}

// Security Secrets (Shared between Gateway & Microservices for Token Verification & Internal Auth)
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', getenv('UNICONNECT_JWT_SECRET') ?: 'uniconnect_ms_jwt_hmac_secret_2026_x9Kp7mQw');
}
if (!defined('JWT_TTL_SECONDS')) {
    define('JWT_TTL_SECONDS', 86400); // 24 hours
}
if (!defined('INTERNAL_SERVICE_KEY')) {
    define('INTERNAL_SERVICE_KEY', getenv('UNICONNECT_INTERNAL_KEY') ?: 'uc_internal_ms_key_94f8e2a1b7c3');
}

// Database Host Defaults for XAMPP MySQL
if (!defined('DB_HOST')) define('DB_HOST', getenv('UNICONNECT_DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('UNICONNECT_DB_PORT') ?: '3306');
if (!defined('DB_USER')) define('DB_USER', getenv('UNICONNECT_DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('UNICONNECT_DB_PASS') !== false ? getenv('UNICONNECT_DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Database Names Owned by Each Microservice
if (!defined('DB_AUTH'))         define('DB_AUTH', 'uniconnect_auth_db');
if (!defined('DB_STUDENT'))      define('DB_STUDENT', 'uniconnect_student_db');
if (!defined('DB_COURSE'))       define('DB_COURSE', 'uniconnect_course_db');
if (!defined('DB_REQUEST'))      define('DB_REQUEST', 'uniconnect_request_db');
if (!defined('DB_NOTIFICATION')) define('DB_NOTIFICATION', 'uniconnect_notification_db');
if (!defined('DB_ADMIN'))        define('DB_ADMIN', 'uniconnect_admin_db');
if (!defined('DB_MONOLITH'))     define('DB_MONOLITH', 'uniconnect_db');

// Detect Base Web Path for /microservices
if (!function_exists('ms_detect_base_path')) {
    function ms_detect_base_path() {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strpos($scriptName, '/microservices/');
        if ($pos !== false) {
            return substr($scriptName, 0, $pos + strlen('/microservices'));
        }
        return '/UniConnect/microservices';
    }
}

$msBasePath = ms_detect_base_path();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$publicHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$serverPort = isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80;
$internalHost = ($serverPort && $serverPort !== 80 && $serverPort !== 443)
    ? "127.0.0.1:{$serverPort}"
    : '127.0.0.1';
$internalScheme = ($serverPort === 443) ? 'https://' : 'http://';

if (!defined('MS_PUBLIC_BASE_URL')) {
    define('MS_PUBLIC_BASE_URL', getenv('UNICONNECT_MS_BASE_URL') ?: ($protocol . $publicHost . $msBasePath));
}
if (!defined('MS_INTERNAL_BASE_URL')) {
    define('MS_INTERNAL_BASE_URL', getenv('UNICONNECT_MS_INTERNAL_URL') ?: ($internalScheme . $internalHost . $msBasePath));
}
if (!defined('FRONTEND_BASE_URL')) {
    define('FRONTEND_BASE_URL', MS_PUBLIC_BASE_URL . '/frontend/');
}

// Microservice Registry URLs (used for internal HTTP service-to-service communication)
if (!defined('SERVICE_API_GATEWAY'))  define('SERVICE_API_GATEWAY',  MS_INTERNAL_BASE_URL . '/api-gateway');
if (!defined('SERVICE_AUTH'))         define('SERVICE_AUTH',         MS_INTERNAL_BASE_URL . '/auth-service');
if (!defined('SERVICE_STUDENT'))      define('SERVICE_STUDENT',      MS_INTERNAL_BASE_URL . '/student-service');
if (!defined('SERVICE_COURSE'))       define('SERVICE_COURSE',       MS_INTERNAL_BASE_URL . '/course-service');
if (!defined('SERVICE_REQUEST'))      define('SERVICE_REQUEST',      MS_INTERNAL_BASE_URL . '/request-service');
if (!defined('SERVICE_NOTIFICATION')) define('SERVICE_NOTIFICATION', MS_INTERNAL_BASE_URL . '/notification-service');
if (!defined('SERVICE_ADMIN'))        define('SERVICE_ADMIN',        MS_INTERNAL_BASE_URL . '/admin-service');
