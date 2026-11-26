<?php
require_once __DIR__ . '/../config/gateway_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $mid = (int)($_POST['message_id'] ?? 0);
        $res = api_put("/api/messages/{$mid}/read", []);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Message marked as read.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Failed to mark as read.');
        }
    } elseif ($action === 'delete') {
        $mid = (int)($_POST['message_id'] ?? 0);
        $res = api_delete("/api/messages/{$mid}");
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Message deleted.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Failed to delete message.');
        }
    }
    header('Location: messages.php');
    exit;
}

// Fetch messages — service returns plain array directly as $data
$res      = api_get('/api/messages');
$raw_data = $res['body']['data'] ?? [];

// data could be a plain array of rows, or wrapped with 'messages' key
if (isset($raw_data['messages'])) {
    $messages = $raw_data['messages'];
} elseif (isset($raw_data[0]) || empty($raw_data)) {
    $messages = $raw_data; // plain array
} else {
    $messages = [];
}

$unread_count = 0;
foreach ($messages as $m) {
    if (!(bool)($m['is_read'] ?? false)) $unread_count++;
}

$page_title = 'Contact Messages';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
  <div class="content-header">
    <h1>
      <i class="fas fa-envelope me-2"></i>Contact Messages
      <?php if ($unread_count > 0): ?>
        <span class="badge bg-danger ms-2"><?= $unread_count ?> Unread</span>
      <?php endif; ?>
    </h1>
  </div>

  <?php include __DIR__ . '/../includes/alerts.php'; ?>

  <div class="card card-academic">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Inbox
        <span class="badge bg-secondary ms-2"><?= count($messages) ?></span>
        <?php if ($unread_count > 0): ?>
          <span class="badge bg-danger ms-1"><?= $unread_count ?> unread</span>
        <?php endif; ?>
      </h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($messages)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-envelope-open fa-3x mb-3"></i>
          <p class="mt-2">No messages in inbox.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-academic table-hover mb-0">
          <thead>
            <tr>
              <th style="width:30px;"></th>
              <th>Sender</th>
              <th>Subject</th>
              <th>Message Preview</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $msg):
              $is_read = (bool)($msg['is_read'] ?? false);
            ?>
            <tr class="<?= !$is_read ? 'fw-bold table-warning' : '' ?>">
              <td class="text-center">
                <?php if (!$is_read): ?>
                  <span class="text-danger" title="Unread"><i class="fas fa-circle" style="font-size:.55rem;"></i></span>
                <?php else: ?>
                  <span class="text-success" title="Read"><i class="fas fa-check-circle" style="font-size:.8rem;"></i></span>
                <?php endif; ?>
              </td>
              <td>
                <div><?= htmlspecialchars($msg['name'] ?? $msg['sender_name'] ?? 'Anonymous') ?></div>
                <small class="text-muted"><?= htmlspecialchars($msg['email'] ?? $msg['sender_email'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars($msg['subject'] ?? '(no subject)') ?></td>
              <td>
                <span class="text-truncate d-inline-block" style="max-width:220px;">
                  <?= htmlspecialchars(mb_strimwidth($msg['message'] ?? '', 0, 70, '...')) ?>
                </span>
              </td>
              <td><small><?= isset($msg['created_at']) ? date('M d, Y H:i', strtotime($msg['created_at'])) : 'N/A' ?></small></td>
              <td>
                <?php if ($is_read): ?>
                  <span class="badge bg-success">Read</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Unread</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#msgModal"
                    data-sender="<?= htmlspecialchars($msg['name'] ?? $msg['sender_name'] ?? '') ?>"
                    data-email="<?= htmlspecialchars($msg['email'] ?? $msg['sender_email'] ?? '') ?>"
                    data-subject="<?= htmlspecialchars($msg['subject'] ?? '') ?>"
                    data-body="<?= htmlspecialchars($msg['message'] ?? '') ?>"
                    title="View Full Message">
                    <i class="fas fa-eye"></i>
                  </button>
                  <?php if (!$is_read): ?>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Read">
                      <i class="fas fa-check"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message permanently?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="fas fa-trash"></i>
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
</div>

<!-- Full Message View Modal -->
<div class="modal fade" id="msgModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope-open me-2"></i>Message Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-3">
          <dt class="col-sm-2">From</dt>
          <dd class="col-sm-10"><strong id="mod_sender"></strong> &lt;<span id="mod_email"></span>&gt;</dd>
          <dt class="col-sm-2">Subject</dt>
          <dd class="col-sm-10 fw-semibold" id="mod_subject"></dd>
        </dl>
        <hr>
        <div id="mod_body" class="p-3 bg-light rounded" style="white-space:pre-wrap; min-height:80px;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('msgModal').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  document.getElementById('mod_sender').textContent  = btn.dataset.sender;
  document.getElementById('mod_email').textContent   = btn.dataset.email;
  document.getElementById('mod_subject').textContent = btn.dataset.subject;
  document.getElementById('mod_body').textContent    = btn.dataset.body;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
