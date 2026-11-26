<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Add New Student
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

$pageTitle = 'Add New Student';

$error          = '';
$name           = '';
$email          = '';
$studentIdInput = '';
$department     = 'Computer Science';
$academicYear   = 'Year 1';
$semester       = DEFAULT_SEMESTER;
$phone          = '';
$dob            = '';
$address        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $studentIdInput = trim($_POST['student_id'] ?? '');
    $department     = trim($_POST['department'] ?? 'Computer Science');
    $academicYear   = trim($_POST['academic_year'] ?? 'Year 1');
    $semester       = trim($_POST['semester'] ?? DEFAULT_SEMESTER);
    $phone          = trim($_POST['phone'] ?? '');
    $dob            = trim($_POST['date_of_birth'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $password       = $_POST['password'] ?? '';
    $csrfToken      = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $res = api_post('/api/admin/students', [
            'name'          => $name,
            'email'         => $email,
            'student_id'    => $studentIdInput,
            'department'    => $department,
            'academic_year' => $academicYear,
            'semester'      => $semester,
            'phone'         => $phone,
            'date_of_birth' => $dob,
            'address'       => $address,
            'password'      => $password,
        ]);

        if ($res['status'] === 201 && !empty($res['body']['success'])) {
            set_flash('success', $res['body']['message'] ?? "Student '{$name}' created successfully!");
            header("Location: " . BASE_URL . "admin/students.php");
            exit;
        } else {
            $error = $res['body']['error'] ?? 'Error adding student.';
        }
    }
} else {
    $nextRes = api_get('/api/students/next-id');
    $studentIdInput = $nextRes['body']['data']['next_student_id'] ?? ('STD' . date('Y') . '001');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i>Add New Student</h4>
        <p class="text-muted small mb-0">Create a student user account and academic profile record</p>
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

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-id-card text-primary me-2"></i>Basic Identity &amp; Credentials</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" placeholder="e.g. Rachel Adams" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label small fw-semibold text-secondary">University Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="rachel.a@uniconnect.edu" required>
                </div>
                <div class="col-md-6">
                    <label for="student_id" class="form-label small fw-semibold text-secondary">Student ID Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace" id="student_id" name="student_id" value="<?= e($studentIdInput) ?>" required>
                    <small class="text-muted">Auto-suggested based on current year.</small>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label small fw-semibold text-secondary">Initial Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="At least 6 characters" required>
                </div>
            </div>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Academic Placement</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="department" class="form-label small fw-semibold text-secondary">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="Computer Science" <?= $department === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Software Engineering" <?= $department === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                        <option value="Information Technology" <?= $department === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="Data Science" <?= $department === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                        <option value="Mathematics & CS" <?= $department === 'Mathematics & CS' ? 'selected' : '' ?>>Mathematics &amp; CS</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="academic_year" class="form-label small fw-semibold text-secondary">Academic Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="academic_year" name="academic_year" required>
                        <option value="Year 1" <?= $academicYear === 'Year 1' ? 'selected' : '' ?>>Year 1 (Freshman)</option>
                        <option value="Year 2" <?= $academicYear === 'Year 2' ? 'selected' : '' ?>>Year 2 (Sophomore)</option>
                        <option value="Year 3" <?= $academicYear === 'Year 3' ? 'selected' : '' ?>>Year 3 (Junior)</option>
                        <option value="Year 4" <?= $academicYear === 'Year 4' ? 'selected' : '' ?>>Year 4 (Senior)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label small fw-semibold text-secondary">Enrolled Term <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="semester" name="semester" value="<?= e($semester) ?>" required>
                </div>
            </div>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-phone text-primary me-2"></i>Contact Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+1 (555) 000-0000">
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($dob) ?>">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label small fw-semibold text-secondary">Address</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= e($address) ?>" placeholder="Street Address, City, State">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>admin/students.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="fa-solid fa-check me-1"></i> Create Student Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
