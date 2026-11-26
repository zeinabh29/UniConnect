<?php
require_once __DIR__ . '/../config/gateway_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Handle process action (PUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'process') {
        $cert_id = (int)($_POST['cert_id'] ?? 0);
        $payload = [
            'status'        => trim($_POST['status']        ?? ''),
            'admin_remarks' => trim($_POST['admin_remarks'] ?? ''),
        ];
        $res = api_put("/api/certificates/{$cert_id}", $payload);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Certificate request updated successfully.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Failed to update certificate.');
        }
    }
    $qs = http_build_query(array_filter(['search' => $search, 'status' => $status_filter]));
    header("Location: certificates.php" . ($qs ? "?{$qs}" : ''));
    exit;
}

// Fetch certificates for current filter
$params = [];
if ($search)        $params['search'] = $search;
if ($status_filter) $params['status'] = $status_filter;
$res   = api_get('/api/certificates', $params);
$data  = $res['body']['data'] ?? [];
$certs = $data['requests'] ?? [];

// Fetch ALL for tab counts (service already returns counts, but we also need non-filtered list for the other statuses)
$all_res    = api_get('/api/certificates');
$all_data   = $all_res['body']['data'] ?? [];
$srv_counts = $all_data['counts'] ?? [];

// Build tab counts
$counts = [
    'all'       => (int)($srv_counts['total']    ?? count($all_data['requests'] ?? [])),
    'pending'   => (int)($srv_counts['pending']  ?? 0),
    'approved'  => (int)($srv_counts['approved'] ?? 0),
    'rejected'  => 0,
    'completed' => 0,
];
// Count rejected + completed from full list
foreach ($all_data['requests'] ?? [] as $c) {
    $st = $c['status'] ?? '';
    if ($st === 'rejected')  $counts['rejected']++;
    if ($st === 'completed') $counts['completed']++;
}

$page_title = 'Certificate Requests';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
  <div class="content-header">
    <h1><i class="fas fa-certificate me-2"></i>Certificate Requests</h1>
  </div>

  <?php include __DIR__ . '/../includes/alerts.php'; ?>

  <!-- Filter Bar -->
  <div class="card card-academic mb-3">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Student name or ID..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-6">
          <button type="submit" class="btn btn-outline-primary me-2"><i class="fas fa-search me-1"></i>Search</button>
          <a href="certificates.php<?= $status_filter ? '?status=' . urlencode($status_filter) : '' ?>" class="btn btn-outline-secondary">Reset Search</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Status Tabs -->
  <ul class="nav nav-tabs mb-3">
    <?php
      $tabs = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'];
      $tab_colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'completed' => 'primary', 'all' => 'secondary'];
      foreach ($tabs as $key => $label):
        $active = ($status_filter === $key || ($key === 'all' && $status_filter === ''));
        $qs = http_build_query(array_filter(['status' => ($key === 'all' ? '' : $key), 'search' => $search]));
    ?>
    <li class="nav-item">
      <a class="nav-link <?= $active ? 'active' : '' ?>" href="certificates.php<?= $qs ? '?' . $qs : '' ?>">
        <?= $label ?>
        <span class="badge bg-<?= $tab_colors[$key] ?> ms-1"><?= $counts[$key] ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <!-- Certificates Table -->
  <div class="card card-academic">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-list me-2"></i>
        <?= ucfirst($status_filter ?: 'All') ?> Requests
        <span class="badge bg-secondary ms-2"><?= count($certs) ?></span>
      </h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($certs)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-certificate fa-3x mb-3"></i>
          <p class="mt-2">No certificate requests found.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-academic table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Certificate Type</th>
              <th>Purpose</th>
              <th>Requested On</th>
              <th>Status</th>
              <th>Admin Remarks</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($certs as $i => $cert): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($cert['student_name'] ?? 'N/A') ?></div>
                <small class="text-muted"><?= htmlspecialchars($cert['student_code'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $cert['certificate_type'] ?? ''))) ?></td>
              <td><small><?= htmlspecialchars(mb_strimwidth($cert['purpose'] ?? '', 0, 60, '...')) ?></small></td>
              <td><?= isset($cert['requested_at']) ? date('M d, Y', strtotime($cert['requested_at'])) : 'N/A' ?></td>
              <td>
                <?php
                  $st  = $cert['status'] ?? 'pending';
                  $cls = match($st) { 'approved' => 'success', 'rejected' => 'danger', 'completed' => 'primary', default => 'warning' };
                  echo "<span class=\"badge bg-{$cls}\">" . ucfirst(htmlspecialchars($st)) . "</span>";
                ?>
              </td>
              <td><small class="text-muted"><?= htmlspecialchars($cert['admin_remarks'] ?? '—') ?></small></td>
              <td class="d-flex gap-1 flex-wrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal"
                  data-id="<?= $cert['id'] ?>"
                  data-student="<?= htmlspecialchars($cert['student_name'] ?? '') ?>"
                  data-type="<?= htmlspecialchars($cert['certificate_type'] ?? '') ?>"
                  data-purpose="<?= htmlspecialchars($cert['purpose'] ?? '') ?>"
                  data-status="<?= htmlspecialchars($cert['status'] ?? 'pending') ?>"
                  data-remarks="<?= htmlspecialchars($cert['admin_remarks'] ?? '') ?>">
                  <i class="fas fa-edit"></i> Review
                </button>
                <?php if (in_array($cert['status'] ?? '', ['approved', 'completed'])): ?>
                <a href="../student/certificate-view.php?id=<?= $cert['id'] ?>" target="_blank"
                   class="btn btn-sm btn-outline-success">
                  <i class="fas fa-eye"></i> View
                </a>
                <?php endif; ?>
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

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="process">
        <input type="hidden" name="cert_id" id="modal_cert_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-certificate me-2"></i>Review Certificate Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small">Student</label>
            <div class="fw-semibold" id="modal_student_name"></div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small">Certificate Type</label>
            <div id="modal_cert_type"></div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small">Purpose / Reason</label>
            <div id="modal_cert_purpose" class="text-muted small p-2 bg-light rounded"></div>
          </div>
          <hr>
          <div class="mb-3">
            <label class="form-label fw-semibold">Decision <span class="text-danger">*</span></label>
            <select name="status" id="modal_status" class="form-select" required>
              <option value="pending">Pending (keep)</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="completed">Completed (issued)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Admin Remarks</label>
            <textarea name="admin_remarks" id="modal_remarks" class="form-control" rows="3" placeholder="Optional remarks for the student..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('reviewModal').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  document.getElementById('modal_cert_id').value            = btn.dataset.id;
  document.getElementById('modal_student_name').textContent = btn.dataset.student;
  document.getElementById('modal_cert_type').textContent    = btn.dataset.type.replace(/_/g,' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
  document.getElementById('modal_cert_purpose').textContent = btn.dataset.purpose;
  document.getElementById('modal_status').value             = btn.dataset.status;
  document.getElementById('modal_remarks').value            = btn.dataset.remarks;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
