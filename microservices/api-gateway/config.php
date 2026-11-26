<?php
/**
 * UniConnect Microservices - API Gateway Configuration & Route Registry
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/HttpClient.php';
require_once __DIR__ . '/../shared/JwtHelper.php';
require_once __DIR__ . '/../shared/Response.php';

/**
 * Returns service registry map for health checks and routing.
 */
function gateway_service_registry() {
    return [
        'auth-service'         => SERVICE_AUTH,
        'student-service'      => SERVICE_STUDENT,
        'course-service'       => SERVICE_COURSE,
        'request-service'      => SERVICE_REQUEST,
        'notification-service' => SERVICE_NOTIFICATION,
        'admin-service'        => SERVICE_ADMIN,
    ];
}

/**
 * Resolves an incoming API Gateway route to a target microservice URL + required auth/role policy.
 *
 * @return array{service_url:string, target_path:string, auth:bool, roles:string[]}|null
 */
function gateway_resolve_route($method, $route) {
    // Strip optional /api prefix so both /api/auth/login and /auth/login work cleanly
    $normalized = preg_replace('#^/api(?=/|$)#', '', $route);
    if ($normalized === '' || $normalized === false) {
        $normalized = '/';
    }

    // 1. Auth Service routes
    if (preg_match('#^/auth(/.*)?$#', $normalized, $m)) {
        $sub = $m[1] ?? '/';
        $isPublic = in_array($sub, ['/login', '/register', '/logout', '/verify'], true);
        return [
            'service_url' => SERVICE_AUTH,
            'target_path' => $sub,
            'auth'        => !$isPublic,
            'roles'       => [],
            'forward_jwt' => true,
        ];
    }

    // 2. Student Dashboard & Profile -> Student Service
    if ($normalized === '/student/dashboard' || $normalized === '/student/profile') {
        $sub = substr($normalized, strlen('/student'));
        return [
            'service_url' => SERVICE_STUDENT,
            'target_path' => $sub,
            'auth'        => true,
            'roles'       => ['student'],
        ];
    }

    // 3. Admin Service routes (Dashboard, Audit Logs, Orchestrated Student Writes)
    if (preg_match('#^/admin(/.*)?$#', $normalized, $m)) {
        $sub = $m[1] ?? '/';
        return [
            'service_url' => SERVICE_ADMIN,
            'target_path' => $sub,
            'auth'        => true,
            'roles'       => ['admin'],
        ];
    }

    // 4. Student Read/Directory endpoints -> Student Service
    if (preg_match('#^/students(/.*)?$#', $normalized)) {
        return [
            'service_url' => SERVICE_STUDENT,
            'target_path' => $normalized,
            'auth'        => true,
            'roles'       => ['admin'],
        ];
    }

    // 5. Course, Enrollment & Grade routes -> Course Service
    if (preg_match('#^/(courses|enrollments|grades)(/.*)?$#', $normalized, $m)) {
        $roles = [];
        // Fine-grained RBAC enforced at Gateway for write operations on courses/grades
        if ($m[1] === 'courses' && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $roles = ['admin'];
        } elseif ($m[1] === 'grades' && $method === 'POST') {
            $roles = ['admin'];
        } elseif ($m[1] === 'enrollments' && $method === 'DELETE') {
            $roles = ['admin'];
        } elseif ($m[1] === 'enrollments' && preg_match('#^/enrollments/\d+/drop$#', $normalized)) {
            $roles = ['student'];
        }
        return [
            'service_url' => SERVICE_COURSE,
            'target_path' => $normalized,
            'auth'        => true,
            'roles'       => $roles,
        ];
    }

    // 6. Request Service routes (Certificates + Contact Messages)
    if (preg_match('#^/certificates(/.*)?$#', $normalized)) {
        $roles = [];
        if ($method === 'POST' && $normalized === '/certificates') {
            $roles = ['student'];
        } elseif ($method === 'PUT') {
            $roles = ['admin'];
        }
        return [
            'service_url' => SERVICE_REQUEST,
            'target_path' => $normalized,
            'auth'        => true,
            'roles'       => $roles,
        ];
    }

    if (preg_match('#^/messages(/.*)?$#', $normalized)) {
        // Public POST /messages from university homepage contact form
        $isPublicPost = ($method === 'POST' && $normalized === '/messages');
        return [
            'service_url' => SERVICE_REQUEST,
            'target_path' => $normalized,
            'auth'        => !$isPublicPost,
            'roles'       => $isPublicPost ? [] : ['admin'],
        ];
    }

    // 7. Notification Service routes
    if (preg_match('#^/notifications(/.*)?$#', $normalized)) {
        return [
            'service_url' => SERVICE_NOTIFICATION,
            'target_path' => $normalized,
            'auth'        => true,
            'roles'       => [],
        ];
    }

    return null;
}
