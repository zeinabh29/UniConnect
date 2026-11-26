<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Portal Header Component (Fetches Notifications via API Gateway)
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_login();

$user = current_user();

$unreadCount  = 0;
$recentNotifs = [];

$notifRes = api_get('/api/notifications', ['limit' => 4]);
if ($notifRes['status'] === 200 && !empty($notifRes['body']['success'])) {
    $recentNotifs = $notifRes['body']['data']['notifications'] ?? [];
    $unreadCount  = (int)($notifRes['body']['data']['unread_count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= APP_NAME ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<div class="portal-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="portal-main">
        <header class="portal-topbar">
            <div class="topbar-left">
                <button type="button" class="topbar-toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="d-none d-md-block">
                    <h5 class="mb-0 fw-bold text-dark"><?= isset($pageTitle) ? e($pageTitle) : 'Portal' ?></h5>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Notifications Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light rounded-circle position-relative p-2" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                        <i class="fa-regular fa-bell text-secondary"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem;">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="notifDropdown" style="width: 320px; max-width: 90vw;">
                        <li class="d-flex justify-content-between align-items-center px-2 py-1 mb-1 border-bottom">
                            <span class="fw-bold small">Notifications</span>
                            <span class="badge bg-primary-subtle text-primary"><?= $unreadCount ?> new</span>
                        </li>
                        <?php if (empty($recentNotifs)): ?>
                            <li class="text-center py-3 text-muted small">No notifications yet</li>
                        <?php else: ?>
                            <?php foreach ($recentNotifs as $notif): ?>
                                <li>
                                    <a class="dropdown-item rounded p-2 text-wrap <?= $notif['is_read'] ? 'opacity-75' : 'bg-light' ?>" href="<?= BASE_URL ?><?= is_student() ? 'student/notifications.php' : 'admin/dashboard.php' ?>">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fa-solid fa-circle-dot mt-1 text-<?= e($notif['type']) ?>" style="font-size: 0.6rem;"></i>
                                            <div>
                                                <div class="fw-semibold small text-dark"><?= e($notif['title']) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= e(substr($notif['message'], 0, 70)) ?>...</div>
                                                <div class="text-secondary" style="font-size: 0.68rem;"><?= format_date($notif['created_at']) ?></div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (is_student()): ?>
                            <li class="border-top pt-1 mt-1 text-center">
                                <a href="<?= BASE_URL ?>student/notifications.php" class="small text-decoration-none fw-semibold">View All Notifications</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-outline-light border text-dark d-flex align-items-center gap-2 py-1 px-2 rounded-pill" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="d-none d-sm-inline fw-semibold small"><?= e($user['name']) ?></span>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 0.75rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenuDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark"><?= e($user['name']) ?></div>
                            <small class="text-muted"><?= e($user['email']) ?></small>
                        </li>
                        <?php if (is_student()): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>student/profile.php"><i class="fa-solid fa-user me-2 text-muted"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>student/grades.php"><i class="fa-solid fa-square-poll-vertical me-2 text-muted"></i> My Transcript</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>index.php" target="_blank"><i class="fa-solid fa-globe me-2 text-muted"></i> University Portal</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php" data-confirm="Are you sure you want to sign out?"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="portal-content">
            <?php require_once __DIR__ . '/alerts.php'; ?>
