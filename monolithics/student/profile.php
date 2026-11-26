<?php
/**
 * UniConnect - Monolithic University Management System
 * Student Portal: Profile Management
 */

require_once __DIR__ . '/../config/config.php';
require_student();

$pageTitle = 'My Profile';
$pdo = get_db_connection();
$user = current_user();

// Fetch student full information
$stmtStudent = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.created_at AS account_created
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
    LIMIT 1
");
$stmtStudent->execute([$user['id']]);
$student = $stmtStudent->fetch();
$studentId = $student['id'];

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $phone    = trim($_POST['phone'] ?? '');
    $dob      = trim($_POST['date_of_birth'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $stmtUpdate = $pdo->prepare("
            UPDATE students 
            SET phone = ?, date_of_birth = ?, address = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $phone ?: null,
            $dob ?: null,
            $address ?: null,
            $studentId
        ]);
        set_flash('success', 'Profile information updated successfully.');
    }
    header("Location: " . BASE_URL . "student/profile.php");
    exit;
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $csrfToken   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
    } elseif (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        set_flash('danger', 'Please fill in all password fields.');
    } elseif (strlen($newPass) < 6) {
        set_flash('danger', 'New password must be at least 6 characters.');
    } elseif ($newPass !== $confirmPass) {
        set_flash('danger', 'New password confirmation does not match.');
    } else {
        // Verify current password
        $stmtUser = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$user['id']]);
        $existingHash = $stmtUser->fetchColumn();

        if (password_verify($currentPass, $existingHash)) {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmtUpdatePass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUpdatePass->execute([$newHash, $user['id']]);

            add_notification($pdo, $user['id'], 'Password Changed', 'Your account password was updated successfully.', 'info', 'student/profile.php');
            set_flash('success', 'Password updated successfully!');
        } else {
            set_flash('danger', 'Incorrect current password.');
        }
    }
    header("Location: " . BASE_URL . "student/profile.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-id-badge text-primary me-2"></i>My Student Profile</h4>
    <p class="text-muted small mb-0">Manage your university student records, contact details, and account security</p>
</div>

<div class="row g-4">
    <!-- Left Column: Profile Card & Academic Details -->
    <div class="col-lg-4">
        <div class="card-academic text-center p-4 mb-4">
            <div class="mx-auto mb-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 80px; height: 80px; font-size: 2rem; font-weight: 700;">
                <?= strtoupper(substr($student['name'], 0, 1)) ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= e($student['name']) ?></h5>
            <div class="badge bg-primary-subtle text-primary mb-2 font-monospace px-3 py-1"><?= e($student['student_id']) ?></div>
            <div class="text-muted small mb-3"><?= e($student['department']) ?></div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Account Status:</span>
                    <span class="badge bg-success-subtle text-success">Active Student</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Level:</span>
                    <span class="fw-semibold text-dark"><?= e($student['academic_year']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Current Term:</span>
                    <span class="fw-semibold text-dark"><?= e($student['semester']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Joined:</span>
                    <span class="text-dark"><?= format_date($student['account_created']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Forms -->
    <div class="col-lg-8">
        <!-- Contact Information Form -->
        <div class="card-academic mb-4">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-address-book text-primary me-2"></i>Personal & Contact Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" class="form-control bg-light" value="<?= e($student['name']) ?>" readonly>
                            <small class="text-muted">Official name can only be modified by the Registrar.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">University Email</label>
                            <input type="email" class="form-control bg-light" value="<?= e($student['email']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($student['phone']) ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($student['date_of_birth']) ?>">
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label small fw-semibold text-secondary">Residential Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Street Address, Campus Dorm, City"><?= e($student['address']) ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security & Password Form -->
        <div class="card-academic">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-lock text-primary me-2"></i>Change Password</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="current_password" class="form-label small fw-semibold text-secondary">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="new_password" class="form-label small fw-semibold text-secondary">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="At least 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fa-solid fa-key me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
