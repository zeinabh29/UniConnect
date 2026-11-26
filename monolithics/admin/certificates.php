<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Certificate & Document Requests
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$pageTitle = 'Certificate Requests Management';
$pdo = get_db_connection();
$user = current_user();

// Handle Status Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_cert') {
    $certId       = (int)($_POST['cert_id'] ?? 0);
    $newStatus    = trim($_POST['status'] ?? 'pending');
    $adminRemarks = trim($_POST['admin_remarks'] ?? '');
    $csrfToken    = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
        header("Location: " . BASE_URL . "admin/certificates.php");
        exit;
    }

    if ($certId <= 0) {
        set_flash('danger', 'Invalid certificate request.');
        header("Location: " . BASE_URL . "admin/certificates.php");
        exit;
    }

    // Verify request
    $stmtFind = $pdo->prepare("
        SELECT r.*, s.user_id, u.name AS student_name
        FROM certificate_requests r
        JOIN students s ON r.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmtFind->execute([$certId]);
    $req = $stmtFind->fetch();

    if (!$req) {
        set_flash('danger', 'Certificate request not found.');
        header("Location: " . BASE_URL . "admin/certificates.php");
        exit;
    }

    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE certificate_requests 
            SET status = ?, admin_remarks = ?, processed_by = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $newStatus,
            $adminRemarks ?: null,
            $user['id'],
            $certId
        ]);

        // Determine notification type
        $notifType = ($newStatus === 'approved' || $newStatus === 'completed') ? 'success' : ($newStatus === 'rejected' ? 'danger' : 'info');

        // Notify student
        add_notification(
            $pdo,
            $req['user_id'],
            'Certificate Request ' . ucfirst($newStatus),
            "Your request for {$req['certificate_type']} (#{$req['reference_no']}) has been marked as {$newStatus}. " . ($adminRemarks ? "Notes: {$adminRemarks}" : ""),
            $notifType,
            'student/certificates.php'
        );

        set_flash('success', "Certificate request #{$req['reference_no']} updated to status: " . ucfirst($newStatus) . ".");
        header("Location: " . BASE_URL . "admin/certificates.php");
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Failed to update request: ' . $e->getMessage());
        header("Location: " . BASE_URL . "admin/certificates.php");
        exit;
    }
}

// Status Filter
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT r.*, s.student_id AS student_code, s.department, u.name AS student_name, u.email AS student_email,
           admin_u.name AS reviewer_name
    FROM certificate_requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN users admin_u ON r.processed_by = admin_u.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $query .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR s.student_id LIKE ? OR r.reference_no LIKE ? OR r.certificate_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY r.requested_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Counts for filter tabs
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'pending'")->fetchColumn();
$countApproved = (int)$pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'approved'")->fetchColumn();
$countTotal = (int)$pdo->query("SELECT COUNT(*) FROM certificate_requests")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-file-shield text-primary me-2"></i>Certificate & Document Requests</h4>
        <p class="text-muted small mb-0">Review, verify, and approve official document and transcript submissions</p>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-3">
    <a href="<?= BASE_URL ?>admin/certificates.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline-secondary' ?>">
        All Requests (<?= $countTotal ?>)
    </a>
    <a href="<?= BASE_URL ?>admin/certificates.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">
        <i class="fa-solid fa-clock me-1"></i> Pending (<?= $countPending ?>)
    </a>
    <a href="<?= BASE_URL ?>admin/certificates.php?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">
        <i class="fa-solid fa-circle-check me-1"></i> Approved (<?= $countApproved ?>)
    </a>
    <a href="<?= BASE_URL ?>admin/certificates.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
        Rejected
    </a>
</div>

<!-- Requests Table Card -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-table-list text-primary me-2"></i>Document Requests</h6>
        <span class="badge bg-light text-secondary border"><?= count($requests) ?> Requests</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fa-regular fa-circle-check text-muted fs-1 mb-3"></i>
                <h6>No requests matching the selected filter</h6>
                <p class="text-muted small">No pending student document requests at this time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Ref Number</th>
                            <th>Student</th>
                            <th>Document Requested</th>
                            <th>Purpose / Justification</th>
                            <th>Delivery</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td>
                                    <code class="fw-bold text-dark font-monospace"><?= e($r['reference_no']) ?></code>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($r['student_name']) ?></div>
                                    <small class="text-muted"><?= e($r['student_code']) ?> &bull; <?= e($r['department']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= e($r['certificate_type']) ?></span>
                                </td>
                                <td>
                                    <small class="text-secondary text-truncate d-inline-block" style="max-width: 200px;">
                                        <?= e($r['purpose']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($r['delivery_type'] === 'physical_pickup'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary small">Office Pickup</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info-emphasis small">Digital PDF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_date($r['requested_at']) ?></small>
                                </td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td>
                                    <small class="text-muted fst-italic text-truncate d-inline-block" style="max-width: 160px;">
                                        <?= e($r['admin_remarks'] ?: 'None') ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#processCertModal<?= $r['id'] ?>" title="Process Request">
                                            <i class="fa-solid fa-stamp me-1"></i> Review
                                        </button>
                                        <?php if ($r['status'] === 'approved' || $r['status'] === 'completed'): ?>
                                            <a href="<?= BASE_URL ?>student/certificate-view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank" title="View Document">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Process Modal -->
                                    <div class="modal fade text-start" id="processCertModal<?= $r['id'] ?>" tabindex="-1" aria-labelledby="processCertModalLabel<?= $r['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title fw-bold" id="processCertModalLabel<?= $r['id'] ?>">
                                                        <i class="fa-solid fa-stamp me-2"></i>Process Request: <?= e($r['reference_no']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="process_cert">
                                                    <input type="hidden" name="cert_id" value="<?= $r['id'] ?>">

                                                    <div class="modal-body p-4">
                                                        <div class="p-3 bg-light rounded border mb-3 small">
                                                            <div><strong>Student:</strong> <?= e($r['student_name']) ?> (<?= e($r['student_code']) ?>)</div>
                                                            <div><strong>Type:</strong> <?= e($r['certificate_type']) ?></div>
                                                            <div><strong>Purpose:</strong> <?= e($r['purpose']) ?></div>
                                                            <div><strong>Delivery:</strong> <?= ucfirst(str_replace('_', ' ', $r['delivery_type'])) ?></div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold text-secondary">Update Status <span class="text-danger">*</span></label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?= $r['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                <option value="approved" <?= $r['status'] === 'approved' ? 'selected' : '' ?>>Approve Document</option>
                                                                <option value="completed" <?= $r['status'] === 'completed' ? 'selected' : '' ?>>Completed / Issued</option>
                                                                <option value="rejected" <?= $r['status'] === 'rejected' ? 'selected' : '' ?>>Reject Request</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold text-secondary">Registrar Verification Remarks</label>
                                                            <textarea class="form-control" name="admin_remarks" rows="3" placeholder="e.g. Official verification confirmed. Academic requirements verified."><?= e($r['admin_remarks'] ?? '') ?></textarea>
                                                            <small class="text-muted">This comment will appear on the issued document and in the student's notification.</small>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Status
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal -->
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
