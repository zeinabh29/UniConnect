<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Edit Course
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid course identifier.');
    header("Location: " . BASE_URL . "admin/courses.php");
    exit;
}

$pdo = get_db_connection();

$stmtCourse = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmtCourse->execute([$id]);
$course = $stmtCourse->fetch();

if (!$course) {
    set_flash('danger', 'Course not found.');
    header("Location: " . BASE_URL . "admin/courses.php");
    exit;
}

$pageTitle = 'Edit Course - ' . $course['course_code'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode  = strtoupper(trim($_POST['course_code'] ?? ''));
    $courseName  = trim($_POST['course_name'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $credits     = (int)($_POST['credits'] ?? 3);
    $semester    = trim($_POST['semester'] ?? '');
    $instructor  = trim($_POST['instructor_name'] ?? '');
    $maxCapacity = (int)($_POST['max_capacity'] ?? 40);
    $description = trim($_POST['description'] ?? '');
    $status      = trim($_POST['status'] ?? 'active');
    $csrfToken   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid session token.';
    } elseif (empty($courseCode) || empty($courseName) || empty($instructor)) {
        $error = 'Course Code, Course Name, and Instructor are required.';
    } elseif ($credits < 1 || $credits > 6) {
        $error = 'Credits must be between 1 and 6.';
    } elseif ($maxCapacity < 1) {
        $error = 'Capacity must be at least 1.';
    } else {
        // Check uniqueness of code excluding this course
        $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ? LIMIT 1");
        $stmtCheck->execute([$courseCode, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "Another course already uses code '{$courseCode}'.";
        } else {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE courses 
                    SET course_code = ?, course_name = ?, department = ?, credits = ?, semester = ?, instructor_name = ?, description = ?, max_capacity = ?, status = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $courseCode,
                    $courseName,
                    $department,
                    $credits,
                    $semester,
                    $instructor,
                    $description ?: null,
                    $maxCapacity,
                    $status,
                    $id
                ]);

                set_flash('success', "Course '{$courseCode} &mdash; {$courseName}' updated successfully.");
                header("Location: " . BASE_URL . "admin/courses.php");
                exit;
            } catch (Exception $e) {
                $error = 'Failed to update course: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Course</h4>
        <p class="text-muted small mb-0">Modify details for <strong><?= e($course['course_code']) ?> &mdash; <?= e($course['course_name']) ?></strong></p>
    </div>
    <a href="<?= BASE_URL ?>admin/courses.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Courses
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

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="course_code" class="form-label small fw-semibold text-secondary">Course Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace text-uppercase" id="course_code" name="course_code" value="<?= e($course['course_code']) ?>" required>
                </div>
                <div class="col-md-8">
                    <label for="course_name" class="form-label small fw-semibold text-secondary">Course Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="course_name" name="course_name" value="<?= e($course['course_name']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="department" class="form-label small fw-semibold text-secondary">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="Computer Science" <?= $course['department'] === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Software Engineering" <?= $course['department'] === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                        <option value="Information Technology" <?= $course['department'] === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="Data Science" <?= $course['department'] === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                        <option value="Mathematics & CS" <?= $course['department'] === 'Mathematics & CS' ? 'selected' : '' ?>>Mathematics & CS</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="credits" class="form-label small fw-semibold text-secondary">Credits <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="credits" name="credits" min="1" max="6" value="<?= (int)$course['credits'] ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label small fw-semibold text-secondary">Semester Term <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="semester" name="semester" value="<?= e($course['semester']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="instructor_name" class="form-label small fw-semibold text-secondary">Instructor Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="instructor_name" name="instructor_name" value="<?= e($course['instructor_name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="max_capacity" class="form-label small fw-semibold text-secondary">Max Student Capacity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="max_capacity" name="max_capacity" min="1" max="200" value="<?= (int)$course['max_capacity'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small fw-semibold text-secondary">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $course['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $course['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label small fw-semibold text-secondary">Course Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= e($course['description']) ?></textarea>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="<?= BASE_URL ?>admin/courses.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Course
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
