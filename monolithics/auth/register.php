<?php
/**
 * UniConnect - Monolithic University Management System
 * Authentication: Student Self-Registration
 */

require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . (is_admin() ? "admin/dashboard.php" : "student/dashboard.php"));
    exit;
}

$error = '';
$name = '';
$email = '';
$studentIdInput = '';
$department = 'Computer Science';
$academicYear = 'Year 1';
$semester = DEFAULT_SEMESTER;
$phone = '';
$dob = '';
$address = '';

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
    $confirmPass    = $_POST['confirm_password'] ?? '';
    $csrfToken      = $_POST['csrf_token'] ?? '';

    if (empty($semester)) {
        $semester = DEFAULT_SEMESTER;
    }

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Session expired. Please try again.';
    } elseif (empty($name) || empty($email) || empty($studentIdInput) || empty($password) || empty($confirmPass)) {
        $error = 'Please fill in all required fields (Name, Email, Student ID, Password).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $studentIdInput)) {
        $error = 'Student ID must be 3 to 50 characters and contain only letters, numbers, hyphens, or underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = get_db_connection();

        // Check if email already registered
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtCheck->execute([$email]);

        // Check if student ID already registered
        $stmtCheckId = $pdo->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $stmtCheckId->execute([$studentIdInput]);

        if ($stmtCheck->rowCount() > 0) {
            $error = 'This email address is already registered. Please sign in or use a different email.';
        } elseif ($stmtCheckId->rowCount() > 0) {
            $error = "Student ID '{$studentIdInput}' is already registered. Please enter a different Student ID or sign in.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert into users
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, status)
                    VALUES (?, ?, ?, 'student', 'active')
                ");
                $stmtUser->execute([$name, $email, $hashedPassword]);
                $userId = $pdo->lastInsertId();

                // 2. Use the Student ID provided by the student
                $studentIdCode = $studentIdInput;

                // 3. Insert into students table
                $stmtStudent = $pdo->prepare("
                    INSERT INTO students (user_id, student_id, department, academic_year, semester, phone, date_of_birth, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtStudent->execute([
                    $userId,
                    $studentIdCode,
                    $department,
                    $academicYear,
                    $semester,
                    $phone ?: null,
                    $dob ?: null,
                    $address ?: null
                ]);
                $studentPk = $pdo->lastInsertId();

                // 4. Create initial welcome notification
                add_notification(
                    $pdo,
                    $userId,
                    'Welcome to UniConnect Portal',
                    'Your student account has been created with Student ID: ' . $studentIdCode . '. You can now enroll in courses and request documents.',
                    'success',
                    'student/courses.php'
                );

                $pdo->commit();

                // Auto login student
                $userRow = [
                    'id'    => $userId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'student'
                ];
                $studentRow = [
                    'id'         => $studentPk,
                    'student_id' => $studentIdCode,
                    'department' => $department
                ];
                login_user($userRow, $studentRow);

                set_flash('success', "Registration successful! Welcome to UniConnect, {$name}. Your Student ID is {$studentIdCode}.");
                header("Location: " . BASE_URL . "student/dashboard.php");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .register-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            max-width: 680px;
            width: 100%;
            overflow: hidden;
        }
        .register-header {
            background: #0f4c81;
            padding: 2rem;
            color: #ffffff;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="register-header">
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-user-plus me-2"></i>Student Registration</h3>
        <p class="text-white-50 mb-0 small">Create your UniConnect academic profile and student account</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 py-2 px-3 small">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_input() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" placeholder="e.g. Alexander Clark" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="e.g. alexander@student.edu" required>
                </div>

                <div class="col-md-6">
                    <label for="student_id" class="form-label small fw-semibold text-secondary">Student ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace" id="student_id" name="student_id" value="<?= e($studentIdInput) ?>" placeholder="e.g. STD<?= date('Y') ?>003" pattern="[A-Za-z0-9_-]{3,50}" title="3-50 characters: letters, numbers, hyphens, or underscores" required>
                </div>
                <div class="col-md-6">
                    <label for="semester" class="form-label small fw-semibold text-secondary">Academic Semester <span class="text-danger">*</span></label>
                    <select class="form-select" id="semester" name="semester" required>
                        <?php foreach (get_academic_semester_options($semester) as $semOption): ?>
                            <option value="<?= e($semOption) ?>" <?= $semester === $semOption ? 'selected' : '' ?>><?= e($semOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="department" class="form-label small fw-semibold text-secondary">Academic Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="Computer Science" <?= $department === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Software Engineering" <?= $department === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                        <option value="Information Technology" <?= $department === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="Data Science" <?= $department === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                        <option value="Mathematics & CS" <?= $department === 'Mathematics & CS' ? 'selected' : '' ?>>Mathematics & CS</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="academic_year" class="form-label small fw-semibold text-secondary">Academic Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="academic_year" name="academic_year" required>
                        <option value="Year 1" <?= $academicYear === 'Year 1' ? 'selected' : '' ?>>Year 1 (Freshman)</option>
                        <option value="Year 2" <?= $academicYear === 'Year 2' ? 'selected' : '' ?>>Year 2 (Sophomore)</option>
                        <option value="Year 3" <?= $academicYear === 'Year 3' ? 'selected' : '' ?>>Year 3 (Junior)</option>
                        <option value="Year 4" <?= $academicYear === 'Year 4' ? 'selected' : '' ?>>Year 4 (Senior)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+1 (555) 000-0000">
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($dob) ?>">
                </div>

                <div class="col-12">
                    <label for="address" class="form-label small fw-semibold text-secondary">Residential / Campus Address</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= e($address) ?>" placeholder="Street Address, City, State">
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="At least 6 characters" required>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="fa-solid fa-check-circle me-1"></i> Register Student Account
                </button>
            </div>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">Already registered?</span>
            <a href="<?= BASE_URL ?>auth/login.php" class="fw-semibold text-primary small text-decoration-none ms-1">Sign in here</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
