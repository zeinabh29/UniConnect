<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Auth Context & Role Guards (Backed by API Gateway JWT Session)
 */

function is_logged_in() {
    return !empty($_SESSION['ms_token']) && !empty($_SESSION['ms_user']['id']);
}

function is_admin() {
    return is_logged_in() && (($_SESSION['ms_user']['role'] ?? '') === 'admin');
}

function is_student() {
    return is_logged_in() && (($_SESSION['ms_user']['role'] ?? '') === 'student');
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return $_SESSION['ms_user'];
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges are required.');
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit;
    }
}

function require_student() {
    require_login();
    if (!is_student()) {
        set_flash('info', 'Redirected to your Administrator Dashboard.');
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;
    }
}

function login_user_session($token, array $user) {
    session_regenerate_id(true);
    $_SESSION['ms_token'] = $token;
    $_SESSION['ms_user']  = $user;
}

function logout_user_session() {
    if (!empty($_SESSION['ms_token'])) {
        api_post('/api/auth/logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    session_start();
    set_flash('success', 'You have been successfully logged out.');
}
