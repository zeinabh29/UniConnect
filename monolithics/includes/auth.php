<?php
/**
 * UniConnect - Monolithic University Management System
 * Authentication and Authorization Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has the admin role.
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Checks if the logged-in user has the student role.
 */
function is_student() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

/**
 * Returns the currently logged in user's session data.
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'],
        'name'        => $_SESSION['user_name'] ?? 'User',
        'email'       => $_SESSION['user_email'] ?? '',
        'role'        => $_SESSION['user_role'] ?? 'guest',
        'student_id'  => $_SESSION['student_code'] ?? null,
        'student_pk'  => $_SESSION['student_pk'] ?? null,
        'department'  => $_SESSION['student_dept'] ?? null,
    ];
}

/**
 * Guard: Requires user to be logged in.
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

/**
 * Guard: Requires administrator privileges.
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges are required.');
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit;
    }
}

/**
 * Guard: Requires student privileges.
 */
function require_student() {
    require_login();
    if (!is_student()) {
        set_flash('info', 'Redirected to your Administrator Dashboard.');
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;
    }
}

/**
 * Log in a user and set their session.
 */
function login_user($user, $student = null) {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];

    if ($user['role'] === 'student' && $student) {
        $_SESSION['student_pk']   = $student['id'];
        $_SESSION['student_code'] = $student['student_id'];
        $_SESSION['student_dept'] = $student['department'];
    }
}

/**
 * Log out user and clean up session.
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
    set_flash('success', 'You have been successfully logged out.');
}
