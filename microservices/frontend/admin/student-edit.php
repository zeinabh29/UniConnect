<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Edit Student
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid student ID.');
    header("Location: " . BASE_URL . "admin/students.php");
    exit;
}

$res = api_get("/api/students/{$id}");
if ($res['status'] !== 200 || empty($res['body']['data'])) {
    set_flash('danger', 'Student not found.');
    header("Location: " . BASE_URL . "admin/students.php");
    exit;
}

$student   = $res['body']['data'];
$pageTitle = 'Edit Student - ' . $student['name'];
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid session token.';
    } else {
        $updateRes = api_put("/api/admin/students/{$id}", [
            'name'          => trim($_POST['name'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'department'    => trim($_POST['department'] ?? ''),
            'academic_year' => trim($_POST['academic_year'] ?? ''),
            'semester'      => trim($_POST['semester'] ?? ''),
            'phone'         => trim($_POST['phone'] ?? ''),
            'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
            'status'        => trim($_POST['status'] ?? 'active'),
            'new_password'  => $_POST['new_password'] ?? '',
        ]);

        if ($updateRes['status'] === 200 && !empty($updateRes['body']['success'])) {
            set_flash('success', $updateRes['body']['message'] ?? "Student profile updated successfully.");
            header("Location: " . BASE_URL . "admin/students.php");
            exit;
        } else {
            $error = $updateRes['body']['error'] ?? 'Failed to update student.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Student Information</h4>
        <p class="text-muted small mb-0">Modifying profile and academic record for <strong><?= e($student['name']) ?></strong> (<?= e($student['student_id']) ?>)</p>
    </div>
    <a href="<?= BASE_URL ?>admin/students.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Students
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4 py-2 px-3 small">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card-academic">
    <div class="card-body p-4 p-md-5">
        <form method="POST" action="">
            <?= csrf_input() ?>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-id-card text-primary me-2"></i>Basic Identity &amp; Account</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($student['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label small fw-semibold text-secondary">University Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($student['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Student ID</label>
                    <input type="text" class="form-control font-monospace bg-light" value="<?= e($student['student_id']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label small fw-semibold text-secondary">Account Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $student['user_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $student['user_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="new_password" class="form-label small fw-semibold text-secondary">Reset Password (leave empty to keep current password)</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password if changing">
                </div>
            </div>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Academic Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="department" class="form-label small fw-semibold text-secondary">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="Computer Science" <?= $student['department'] === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Software Engineering" <?= $student['department'] === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                        <option value="Information Technology" <?= $student['department'] === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="Data Science" <?= $student['department'] === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                        <option value="Mathematics & CS" <?= $student['department'] === 'Mathematics & CS' ? 'selected' : '' ?>>Mathematics &amp; CS</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="academic_year" class="form-label small fw-semibold text-secondary">Academic Year <span class="text-danger">*</span></label>
                    <select class="form-select" id="academic_year" name="academic_year" required>
                        <option value="Year 1" <?= $student['academic_year'] === 'Year 1' ? 'selected' : '' ?>>Year 1 (Freshman)</option>
                        <option value="Year 2" <?= $student['academic_year'] === 'Year 2' ? 'selected' : '' ?>>Year 2 (Sophomore)</option>
                        <option value="Year 3" <?= $student['academic_year'] === 'Year 3' ? 'selected' : '' ?>>Year 3 (Junior)</option>
                        <option value="Year 4" <?= $student['academic_year'] === 'Year 4' ? 'selected' : '' ?>>Year 4 (Senior)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label small fw-semibold text-secondary">Enrolled Term</label>
                    <input type="text" class="form-control" id="semester" name="semester" value="<?= e($student['semester']) ?>" required>
                </div>
            </div>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-phone text-primary me-2"></i>Contact Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($student['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($student['date_of_birth']) ?>">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label small fw-semibold text-secondary">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= e($student['address']) ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>admin/students.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Student Record
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
