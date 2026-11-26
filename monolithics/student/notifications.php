<?php
/**
 * UniConnect - Monolithic University Management System
 * Student Portal: Notifications Center
 */

require_once __DIR__ . '/../config/config.php';
require_student();

$pageTitle = 'Notifications';
$pdo = get_db_connection();
$user = current_user();

// Handle Mark All as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $stmtMark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmtMark->execute([$user['id']]);
    set_flash('success', 'All notifications marked as read.');
    header("Location: " . BASE_URL . "student/notifications.php");
    exit;
}

// Fetch all notifications for this student
$stmtNotifs = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmtNotifs->execute([$user['id']]);
$notifications = $stmtNotifs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-bell text-primary me-2"></i>Notifications Center</h4>
        <p class="text-muted small mb-0">System updates, academic notices, and certificate status alerts</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="POST" action="">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="card-academic">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fa-regular fa-bell-slash text-muted fs-1 mb-3"></i>
                <h6>No notifications</h6>
                <p class="text-muted small">You're all caught up! Important notices will appear here.</p>
            </div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <li class="list-group-item p-3 p-md-4 <?= $n['is_read'] ? 'bg-white' : 'bg-light border-start border-4 border-primary' ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1">
                                <?php if ($n['type'] === 'success'): ?>
                                    <span class="badge rounded-circle p-2 bg-success text-white"><i class="fa-solid fa-check"></i></span>
                                <?php elseif ($n['type'] === 'warning'): ?>
                                    <span class="badge rounded-circle p-2 bg-warning text-dark"><i class="fa-solid fa-exclamation"></i></span>
                                <?php elseif ($n['type'] === 'danger'): ?>
                                    <span class="badge rounded-circle p-2 bg-danger text-white"><i class="fa-solid fa-xmark"></i></span>
                                <?php else: ?>
                                    <span class="badge rounded-circle p-2 bg-primary text-white"><i class="fa-solid fa-info"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold text-dark mb-0"><?= e($n['title']) ?></h6>
                                    <small class="text-muted"><?= format_datetime($n['created_at']) ?></small>
                                </div>
                                <p class="text-secondary small mb-2"><?= e($n['message']) ?></p>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?= BASE_URL . e($n['link']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.78rem;">
                                        View Details <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
