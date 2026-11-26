<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Contact Inquiries & Feedback
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$pageTitle = 'Contact Inquiries';
$pdo = get_db_connection();

// Handle Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrfToken) && $msgId > 0) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$msgId]);
        set_flash('success', 'Message marked as read.');
    }
    header("Location: " . BASE_URL . "admin/messages.php");
    exit;
}

// Handle Delete Message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_msg') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrfToken) && $msgId > 0) {
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$msgId]);
        set_flash('success', 'Message deleted successfully.');
    }
    header("Location: " . BASE_URL . "admin/messages.php");
    exit;
}

// Fetch all messages
$stmtMessages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$messages = $stmtMessages->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Contact Inquiries</h4>
        <p class="text-muted small mb-0">Messages submitted via the public university landing page contact form</p>
    </div>
</div>

<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-inbox text-primary me-2"></i>Inbox Messages</h6>
        <span class="badge bg-light text-secondary border"><?= count($messages) ?> Inquiries</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="fa-regular fa-envelope text-muted fs-1 mb-3"></i>
                <h6>No inquiries received yet</h6>
                <p class="text-muted small">Messages sent from the homepage contact form will be listed here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Subject</th>
                            <th>Message Content</th>
                            <th>Received On</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $m): ?>
                            <tr class="<?= $m['is_read'] ? '' : 'bg-light fw-semibold' ?>">
                                <td>
                                    <div class="text-dark"><?= e($m['name']) ?></div>
                                    <small class="text-muted"><?= e($m['email']) ?></small>
                                </td>
                                <td>
                                    <span class="text-dark"><?= e($m['subject']) ?></span>
                                </td>
                                <td>
                                    <p class="small text-secondary mb-0 text-wrap" style="max-width: 320px;">
                                        <?= e($m['message']) ?>
                                    </p>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_datetime($m['created_at']) ?></small>
                                </td>
                                <td>
                                    <?php if ($m['is_read']): ?>
                                        <span class="badge bg-light text-muted border">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary text-white">New</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if (!$m['is_read']): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_msg">
                                            <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete message from <?= e($m['name']) ?>?" title="Delete Message">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
