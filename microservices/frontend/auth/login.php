<?php
/**
 * UniConnect Microservices Architecture
 * Frontend: Login Portal (Communicates with API Gateway)
 */

require_once __DIR__ . '/../config/gateway_client.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . (is_admin() ? "admin/dashboard.php" : "student/dashboard.php"));
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrfToken  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $res = api_post('/api/auth/login', [
            'identifier' => $identifier,
            'password'   => $password,
        ]);

        if ($res['status'] === 200 && !empty($res['body']['success']) && !empty($res['body']['data']['token'])) {
            $token = $res['body']['data']['token'];
            $user  = $res['body']['data']['user'];
            login_user_session($token, $user);
            set_flash('success', $res['body']['message'] ?? ('Welcome back, ' . $user['name'] . '!'));

            header("Location: " . BASE_URL . (($user['role'] === 'admin') ? "admin/dashboard.php" : "student/dashboard.php"));
            exit;
        } else {
            $error = $res['body']['error'] ?? 'Invalid Student ID / email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }
        .auth-header {
            background: #0f4c81;
            padding: 2.2rem 2rem 1.8rem;
            color: #ffffff;
            text-align: center;
        }
        .auth-logo-badge {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <div class="auth-logo-badge">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h3 class="fw-bold mb-1"><?= APP_NAME ?></h3>
        <p class="text-white-50 mb-0 small">University Management System Portal</p>
    </div>

    <div class="p-4 p-md-4">
        <?php require_once __DIR__ . '/../includes/alerts.php'; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_input() ?>

            <div class="mb-3">
                <label for="identifier" class="form-label small fw-semibold text-secondary">Student ID or Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-id-card"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="identifier" name="identifier" value="<?= e($identifier) ?>" placeholder="e.g. STD<?= date('Y') ?>001 or admin@uniconnect.edu" required autofocus>
                </div>
                <div class="form-text text-muted" style="font-size: 0.78rem;">Students can sign in using their Student ID or registered email.</div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-1">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Portal
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">Are you a new student?</span>
            <a href="<?= BASE_URL ?>auth/register.php" class="fw-semibold text-primary small text-decoration-none ms-1">Create an account</a>
        </div>
        <div class="text-center mt-2">
            <a href="<?= BASE_URL ?>index.php" class="text-secondary small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to UniConnect Homepage
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
