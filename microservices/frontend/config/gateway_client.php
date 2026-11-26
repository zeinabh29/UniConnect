<?php
/**
 * UniConnect Microservices Architecture
 * Frontend API Gateway Client & Session Configuration
 *
 * Notice: The Frontend NEVER connects directly to any MySQL database.
 * Every operation routes strictly through the API Gateway via HTTP REST APIs.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('UNICONNECT_MS_FE');
    session_start();
}

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/HttpClient.php';
require_once __DIR__ . '/../../shared/Helpers.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', FRONTEND_BASE_URL);
}

/**
 * Flash messaging system (Frontend UI State).
 */
function set_flash($type, $message) {
    $_SESSION['ms_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function has_flash() {
    return isset($_SESSION['ms_flash']);
}

function get_flash() {
    if (isset($_SESSION['ms_flash'])) {
        $flash = $_SESSION['ms_flash'];
        unset($_SESSION['ms_flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF Protection for Frontend HTML Forms.
 */
function generate_csrf_token() {
    if (empty($_SESSION['ms_csrf_token'])) {
        $_SESSION['ms_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ms_csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['ms_csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['ms_csrf_token'], (string)$token);
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

/**
 * Core HTTP Gateway caller for Frontend pages.
 */
function gateway_call($method, $endpoint, $data = null) {
    $headers = [];
    if (!empty($_SESSION['ms_token'])) {
        $headers['Authorization'] = 'Bearer ' . $_SESSION['ms_token'];
    }

    $url = rtrim(SERVICE_API_GATEWAY, '/') . '/' . ltrim($endpoint, '/');
    $res = HttpClient::request($method, $url, $data, $headers);

    // If session token became invalid/expired while browsing protected endpoints, reset local auth state cleanly
    if ($res['status'] === 401 && !empty($_SESSION['ms_token']) && strpos($endpoint, '/auth/login') === false) {
        unset($_SESSION['ms_token'], $_SESSION['ms_user']);
    }

    return $res;
}

function api_get($endpoint, $query = []) {
    return gateway_call('GET', $endpoint, $query);
}

function api_post($endpoint, $data = []) {
    return gateway_call('POST', $endpoint, $data);
}

function api_put($endpoint, $data = []) {
    return gateway_call('PUT', $endpoint, $data);
}

function api_delete($endpoint, $data = null) {
    return gateway_call('DELETE', $endpoint, $data);
}

require_once __DIR__ . '/../includes/auth.php';
