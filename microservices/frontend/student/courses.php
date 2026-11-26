<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Student Portal: Course Catalog & Enrollment
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_student();

$pageTitle = 'Course Catalog';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $courseId  = (int)($_POST['course_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid or expired session. Please try again.');
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    $res = api_post('/api/enrollments', ['course_id' => $courseId]);
    if ($res['status'] === 201 && !empty($res['body']['success'])) {
        set_flash('success', $res['body']['message'] ?? 'Successfully enrolled!');
        header("Location: " . BASE_URL . "student/my-courses.php");
        exit;
    } else {
        $type = ($res['status'] === 409 && strpos($res['body']['error'] ?? '', 'already enrolled') !== false) ? 'warning' : 'danger';
        set_flash($type, $res['body']['error'] ?? 'Enrollment failed.');
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }
}

$search     = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');

$res = api_get('/api/courses', ['search' => $search, 'department' => $filterDept]);
$courses     = $res['body']['data']['courses'] ?? [];
$departments = $res['body']['data']['departments'] ?? [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-compass text-primary me-2"></i>University Course Catalog</h4>
        <p class="text-muted small mb-0">Browse and enroll in academic courses for <?= DEFAULT_SEMESTER ?></p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>student/my-courses.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-book-bookmark me-1"></i> View My Enrolled Courses
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card-academic mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by course code, title, or instructor..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="department" class="form-select">
                    <option value="">All Academic Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= e($dept) ?>" <?= $filterDept === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($filterDept)): ?>
                    <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Courses Grid -->
<div class="row g-4">
    <?php if (empty($courses)): ?>
        <div class="col-12">
            <div class="card-academic text-center py-5">
                <i class="fa-solid fa-book-open text-muted fs-1 mb-3"></i>
                <h5>No courses found</h5>
                <p class="text-muted small">Try adjusting your search criteria or filter options.</p>
                <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-outline-primary btn-sm">Clear All Filters</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <?php
                $isEnrolled = (int)($c['is_enrolled'] ?? 0) > 0;
                $isFull     = (int)$c['enrolled_students'] >= (int)$c['max_capacity'];
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card-academic h-100 d-flex flex-column">
                    <div class="card-header-custom">
                        <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1 fs-6">
                            <?= e($c['course_code']) ?>
                        </span>
                        <span class="badge bg-light text-secondary border">
                            <?= (int)$c['credits'] ?> Credits
                        </span>
                    </div>
                    <div class="card-body p-4 flex-grow-1 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-muted small mb-1"><?= e($c['department']) ?></div>
                            <h5 class="fw-bold text-dark mb-2"><?= e($c['course_name']) ?></h5>
                            <p class="text-muted small mb-3">
                                <?= e($c['description'] ?: 'No course description available.') ?>
                            </p>
                        </div>

                        <div>
                            <div class="p-2 bg-light rounded-3 small mb-3">
                                <div class="d-flex justify-content-between text-muted mb-1">
                                    <span><i class="fa-regular fa-user me-1"></i> Instructor:</span>
                                    <span class="fw-semibold text-dark"><?= e($c['instructor_name']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between text-muted">
                                    <span><i class="fa-solid fa-users me-1"></i> Class Capacity:</span>
                                    <span class="fw-semibold <?= $isFull ? 'text-danger' : 'text-success' ?>">
                                        <?= (int)$c['enrolled_students'] ?> / <?= (int)$c['max_capacity'] ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($isEnrolled): ?>
                                <button type="button" class="btn btn-outline-success w-100" disabled>
                                    <i class="fa-solid fa-check-circle me-1"></i> Currently Enrolled
                                </button>
                            <?php elseif ($isFull): ?>
                                <button type="button" class="btn btn-secondary w-100" disabled>
                                    <i class="fa-solid fa-ban me-1"></i> Class Full
                                </button>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="enroll">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100" data-confirm="Confirm enrollment in <?= e($c['course_code']) ?> for <?= e($c['semester']) ?>?">
                                        <i class="fa-solid fa-plus-circle me-1"></i> Enroll in Course
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
