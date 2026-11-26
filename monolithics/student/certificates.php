<?php
/**
 * UniConnect - Monolithic University Management System
 * Student Portal: Certificate & Document Requests
 */

require_once __DIR__ . '/../config/config.php';
require_student();

$pageTitle = 'Certificate Requests';
$pdo = get_db_connection();
$user = current_user();

// Fetch student ID
$stmtStudent = $pdo->prepare("SELECT id, student_id, department FROM students WHERE user_id = ? LIMIT 1");
$stmtStudent->execute([$user['id']]);
$student = $stmtStudent->fetch();
$studentId = $student['id'];

// Handle new certificate request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_cert') {
    $certType     = trim($_POST['certificate_type'] ?? '');
    $purpose      = trim($_POST['purpose'] ?? '');
    $deliveryType = trim($_POST['delivery_type'] ?? 'digital');
    $csrfToken    = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token. Please try again.');
        header("Location: " . BASE_URL . "student/certificates.php");
        exit;
    }

    if (empty($certType) || empty($purpose)) {
        set_flash('danger', 'Please choose a certificate type and provide a reason/purpose.');
        header("Location: " . BASE_URL . "student/certificates.php");
        exit;
    }

    try {
        $referenceNo = generate_cert_reference();

        $stmtInsert = $pdo->prepare("
            INSERT INTO certificate_requests (student_id, certificate_type, purpose, delivery_type, status, reference_no, requested_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmtInsert->execute([
            $studentId,
            $certType,
            $purpose,
            $deliveryType,
            $referenceNo
        ]);

        // Student notification
        add_notification(
            $pdo,
            $user['id'],
            'Certificate Request Submitted',
            "Your request for {$certType} (#{$referenceNo}) has been submitted to the Registrar Office.",
            'info',
            'student/certificates.php'
        );

        // Administrator notification
        $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $stmtAdmin->fetchColumn();
        if ($adminId) {
            add_notification(
                $pdo,
                $adminId,
                'New Certificate Request',
                "Student {$user['name']} ({$student['student_id']}) requested {$certType}.",
                'warning',
                'admin/certificates.php'
            );
        }

        set_flash('success', "Your request for '{$certType}' (#{$referenceNo}) was successfully submitted!");
        header("Location: " . BASE_URL . "student/certificates.php");
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Failed to submit request: ' . $e->getMessage());
        header("Location: " . BASE_URL . "student/certificates.php");
        exit;
    }
}

// Fetch all requests by this student
$stmtRequests = $pdo->prepare("
    SELECT * FROM certificate_requests
    WHERE student_id = ?
    ORDER BY requested_at DESC
");
$stmtRequests->execute([$studentId]);
$requests = $stmtRequests->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-file-contract text-primary me-2"></i>Certificate & Document Requests</h4>
        <p class="text-muted small mb-0">Apply for official university documentation, transcripts, and verified credentials</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestCertModal">
            <i class="fa-solid fa-plus-circle me-1"></i> New Document Request
        </button>
    </div>
</div>

<!-- Requests Table Card -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>My Request History</h6>
        <span class="badge bg-light text-secondary border"><?= count($requests) ?> Total Requests</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-folder-open text-muted fs-1 mb-3"></i>
                <h6>No certificate requests yet</h6>
                <p class="text-muted small">Need an official document? Click the button below to submit a request.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestCertModal">
                    <i class="fa-solid fa-plus-circle me-1"></i> Request Certificate
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Reference No</th>
                            <th>Certificate Type</th>
                            <th>Purpose / Notes</th>
                            <th>Delivery</th>
                            <th>Requested On</th>
                            <th>Status</th>
                            <th>Registrar Remarks</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td>
                                    <code class="fw-bold text-dark"><?= e($r['reference_no'] ?? 'N/A') ?></code>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= e($r['certificate_type']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted text-break" style="max-width: 240px; display: inline-block;">
                                        <?= e($r['purpose']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($r['delivery_type'] === 'physical_pickup'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary"><i class="fa-solid fa-building me-1"></i> Registrar Pickup</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info-emphasis"><i class="fa-solid fa-cloud-arrow-down me-1"></i> Digital PDF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_date($r['requested_at']) ?></small>
                                </td>
                                <td>
                                    <?= status_badge($r['status']) ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= e($r['admin_remarks'] ?: 'Pending review by Registrar.') ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <?php if ($r['status'] === 'approved' || $r['status'] === 'completed'): ?>
                                        <a href="<?= BASE_URL ?>student/certificate-view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank" title="View & Print Official Certificate">
                                            <i class="fa-solid fa-print me-1"></i> View / Print
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Under Review</span>
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

<!-- Modal: New Certificate Request -->
<div class="modal fade" id="requestCertModal" tabindex="-1" aria-labelledby="requestCertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="requestCertModalLabel"><i class="fa-solid fa-file-signature me-2"></i>Request Academic Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="request_cert">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="certificate_type" class="form-label small fw-semibold text-secondary">Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="certificate_type" name="certificate_type" required>
                            <option value="">-- Select Certificate / Document --</option>
                            <option value="Enrollment Verification Certificate">Enrollment Verification Certificate</option>
                            <option value="Official Academic Transcript">Official Academic Transcript</option>
                            <option value="Degree Completion Certificate">Degree Completion Certificate</option>
                            <option value="Letter of Recommendation">Letter of Recommendation</option>
                            <option value="Good Conduct & Character Certificate">Good Conduct & Character Certificate</option>
                            <option value="English Language Proficiency Certificate">English Language Proficiency Certificate</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="delivery_type" class="form-label small fw-semibold text-secondary">Delivery Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="delivery_type" name="delivery_type" required>
                            <option value="digital">Digital Document (Instant Printable Portal Access)</option>
                            <option value="physical_pickup">Physical Official Copy (Pick up at Office of Registrar)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="purpose" class="form-label small fw-semibold text-secondary">Reason / Purpose of Request <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" placeholder="e.g. Visa application, internship submission, scholarship audit..." required></textarea>
                        <small class="text-muted">Explain where and why this certificate will be used.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
