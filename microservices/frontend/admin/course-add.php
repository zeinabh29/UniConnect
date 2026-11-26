<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Add Course
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

$pageTitle = 'Add New Course';

$error       = '';
$courseCode  = '';
$courseName  = '';
$department  = 'Computer Science';
$credits     = 3;
$semester    = DEFAULT_SEMESTER;
$instructor  = '';
$maxCapacity = 40;
$description = '';
$status      = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode  = strtoupper(trim($_POST['course_code'] ?? ''));
    $courseName  = trim($_POST['course_name'] ?? '');
    $department  = trim($_POST['department'] ?? 'Computer Science');
    $credits     = (int)($_POST['credits'] ?? 3);
    $semester    = trim($_POST['semester'] ?? DEFAULT_SEMESTER);
    $instructor  = trim($_POST['instructor_name'] ?? '');
    $maxCapacity = (int)($_POST['max_capacity'] ?? 40);
    $description = trim($_POST['description'] ?? '');
    $status      = trim($_POST['status'] ?? 'active');
    $csrfToken   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid session token.';
    } else {
        $res = api_post('/api/courses', [
            'course_code'     => $courseCode,
            'course_name'     => $courseName,
            'department'      => $department,
            'credits'         => $credits,
            'semester'        => $semester,
            'instructor_name' => $instructor,
            'max_capacity'    => $maxCapacity,
            'description'     => $description,
            'status'          => $status,
        ]);

        if ($res['status'] === 201 && !empty($res['body']['success'])) {
            set_flash('success', $res['body']['message'] ?? "Course '{$courseCode}' created successfully.");
            header("Location: " . BASE_URL . "admin/courses.php");
            exit;
        } else {
            $error = $res['body']['error'] ?? 'Failed to add course.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add New Course</h4>
        <p class="text-muted small mb-0">Create and publish a new academic course in the university curriculum</p>
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
                    <input type="text" class="form-control font-monospace text-uppercase" id="course_code" name="course_code" value="<?= e($courseCode) ?>" placeholder="e.g. CS302" required autofocus>
                </div>
                <div class="col-md-8">
                    <label for="course_name" class="form-label small fw-semibold text-secondary">Course Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="course_name" name="course_name" value="<?= e($courseName) ?>" placeholder="e.g. Operating Systems & Architecture" required>
                </div>

                <div class="col-md-4">
                    <label for="department" class="form-label small fw-semibold text-secondary">Academic Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="Computer Science" <?= $department === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Software Engineering" <?= $department === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                        <option value="Information Technology" <?= $department === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="Data Science" <?= $department === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                        <option value="Mathematics & CS" <?= $department === 'Mathematics & CS' ? 'selected' : '' ?>>Mathematics &amp; CS</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="credits" class="form-label small fw-semibold text-secondary">Credit Hours <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="credits" name="credits" min="1" max="6" value="<?= (int)$credits ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label small fw-semibold text-secondary">Semester Term <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="semester" name="semester" value="<?= e($semester) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="instructor_name" class="form-label small fw-semibold text-secondary">Instructor / Professor Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="instructor_name" name="instructor_name" value="<?= e($instructor) ?>" placeholder="e.g. Dr. Ada Lovelace" required>
                </div>
                <div class="col-md-3">
                    <label for="max_capacity" class="form-label small fw-semibold text-secondary">Max Student Capacity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="max_capacity" name="max_capacity" min="1" max="200" value="<?= (int)$maxCapacity ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small fw-semibold text-secondary">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label small fw-semibold text-secondary">Course Description &amp; Syllabus Summary</label>
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Overview of syllabus, prerequisites, and learning outcomes..."><?= e($description) ?></textarea>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="<?= BASE_URL ?>admin/courses.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="fa-solid fa-check me-1"></i> Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
