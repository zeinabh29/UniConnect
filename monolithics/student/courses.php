<?php
/**
 * UniConnect - Monolithic University Management System
 * Student Portal: Course Catalog & Enrollment
 */

require_once __DIR__ . '/../config/config.php';
require_student();

$pageTitle = 'Course Catalog';
$pdo = get_db_connection();
$user = current_user();

// Fetch student ID
$stmtStudent = $pdo->prepare("SELECT id, department, semester FROM students WHERE user_id = ? LIMIT 1");
$stmtStudent->execute([$user['id']]);
$student = $stmtStudent->fetch();
$studentId = $student['id'];

// Handle Course Enrollment Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $courseId  = (int)($_POST['course_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid or expired session. Please try again.');
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    if ($courseId <= 0) {
        set_flash('danger', 'Invalid course selection.');
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    // Verify course exists and is active
    $stmtCourse = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active' LIMIT 1");
    $stmtCourse->execute([$courseId]);
    $course = $stmtCourse->fetch();

    if (!$course) {
        set_flash('danger', 'Selected course is unavailable or inactive.');
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    // 1. Prevent duplicate enrollment in the same semester
    $stmtCheckEnroll = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = ? AND course_id = ? AND semester = ? AND status != 'dropped'
        LIMIT 1
    ");
    $stmtCheckEnroll->execute([$studentId, $course['id'], $course['semester']]);
    if ($stmtCheckEnroll->rowCount() > 0) {
        set_flash('warning', "You are already enrolled in {$course['course_code']} ({$course['course_name']}) for {$course['semester']}.");
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    // 2. Check capacity
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status != 'dropped'");
    $stmtCount->execute([$course['id']]);
    $currentCount = (int)$stmtCount->fetchColumn();

    if ($currentCount >= (int)$course['max_capacity']) {
        set_flash('danger', "Sorry, {$course['course_code']} is at full capacity ({$currentCount}/{$course['max_capacity']}).");
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }

    // 3. Process enrollment
    try {
        $stmtInsert = $pdo->prepare("
            INSERT INTO enrollments (student_id, course_id, enrollment_date, academic_year, semester, status)
            VALUES (?, ?, CURDATE(), ?, ?, 'enrolled')
        ");
        $stmtInsert->execute([
            $studentId,
            $course['id'],
            DEFAULT_ACADEMIC_YEAR,
            $course['semester']
        ]);
        $enrollmentId = $pdo->lastInsertId();

        // 4. Initialize empty grade placeholder row
        $stmtGrade = $pdo->prepare("
            INSERT INTO grades (enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point)
            VALUES (?, 0.00, 0.00, 0.00, 0.00, 'F', 0.00)
        ");
        $stmtGrade->execute([$enrollmentId]);

        // 5. Add notification
        add_notification(
            $pdo,
            $user['id'],
            'Course Enrollment Confirmed',
            "You have successfully enrolled in {$course['course_code']}: {$course['course_name']} ({$course['credits']} credits).",
            'success',
            'student/my-courses.php'
        );

        set_flash('success', "Successfully enrolled in {$course['course_code']} &mdash; {$course['course_name']}!");
        header("Location: " . BASE_URL . "student/my-courses.php");
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Enrollment failed: ' . $e->getMessage());
        header("Location: " . BASE_URL . "student/courses.php");
        exit;
    }
}

// Fetch query filters
$search = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');

$query = "
    SELECT c.*,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status != 'dropped') AS enrolled_students,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status != 'dropped') AS is_enrolled
    FROM courses c
    WHERE c.status = 'active'
";
$params = [$studentId];

if (!empty($search)) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ? OR c.instructor_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterDept)) {
    $query .= " AND c.department = ?";
    $params[] = $filterDept;
}

$query .= " ORDER BY c.course_code ASC";

$stmtCourses = $pdo->prepare($query);
$stmtCourses->execute($params);
$courses = $stmtCourses->fetchAll();

// Fetch distinct departments for filter dropdown
$deptStmt = $pdo->query("SELECT DISTINCT department FROM courses ORDER BY department ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

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

<!-- Courses Grid / List -->
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
                $isEnrolled = (int)$c['is_enrolled'] > 0;
                $isFull = (int)$c['enrolled_students'] >= (int)$c['max_capacity'];
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
                    <div class="card-body p-4 flex-grow-1 d-flex flex-direction-column flex-column justify-content-between">
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
                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
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
