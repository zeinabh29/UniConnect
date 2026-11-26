<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Course Enrollment Management
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$pageTitle = 'Enrollment Management';
$pdo = get_db_connection();

// Handle Manual Student Enrollment by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_enroll') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $courseId  = (int)($_POST['course_id'] ?? 0);
    $semester  = trim($_POST['semester'] ?? DEFAULT_SEMESTER);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }

    if ($studentId <= 0 || $courseId <= 0) {
        set_flash('danger', 'Please select both a valid student and course.');
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }

    // Check duplicate enrollment
    $stmtCheck = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = ? AND course_id = ? AND semester = ? AND status != 'dropped'
        LIMIT 1
    ");
    $stmtCheck->execute([$studentId, $courseId, $semester]);
    if ($stmtCheck->rowCount() > 0) {
        set_flash('warning', 'This student is already enrolled in this course for the selected term.');
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }

    // Check course capacity
    $stmtCourse = $pdo->prepare("
        SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status != 'dropped') AS enrolled_count
        FROM courses c WHERE c.id = ? LIMIT 1
    ");
    $stmtCourse->execute([$courseId]);
    $course = $stmtCourse->fetch();

    if (!$course) {
        set_flash('danger', 'Course not found.');
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }

    if ((int)$course['enrolled_count'] >= (int)$course['max_capacity']) {
        set_flash('danger', "Course {$course['course_code']} has reached maximum capacity ({$course['enrolled_count']}/{$course['max_capacity']}).");
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }

    try {
        $stmtInsert = $pdo->prepare("
            INSERT INTO enrollments (student_id, course_id, enrollment_date, academic_year, semester, status)
            VALUES (?, ?, CURDATE(), ?, ?, 'enrolled')
        ");
        $stmtInsert->execute([$studentId, $courseId, DEFAULT_ACADEMIC_YEAR, $semester]);
        $enrollmentId = $pdo->lastInsertId();

        // Initialize grade row
        $stmtGrade = $pdo->prepare("
            INSERT INTO grades (enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point)
            VALUES (?, 0.00, 0.00, 0.00, 0.00, 'F', 0.00)
        ");
        $stmtGrade->execute([$enrollmentId]);

        // Get student user_id for notification
        $stmtStudUser = $pdo->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
        $stmtStudUser->execute([$studentId]);
        $targetUserId = $stmtStudUser->fetchColumn();

        if ($targetUserId) {
            add_notification(
                $pdo,
                $targetUserId,
                'Enrolled in Course by Administrator',
                "Administrator has enrolled you in {$course['course_code']} - {$course['course_name']}.",
                'info',
                'student/my-courses.php'
            );
        }

        set_flash('success', "Student successfully enrolled in {$course['course_code']}!");
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Enrollment failed: ' . $e->getMessage());
        header("Location: " . BASE_URL . "admin/enrollments.php");
        exit;
    }
}

// Handle Drop / Remove Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_enrollment') {
    $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
    $csrfToken    = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmtDel->execute([$enrollmentId]);
        set_flash('success', 'Enrollment record removed.');
    }
    header("Location: " . BASE_URL . "admin/enrollments.php");
    exit;
}

// Filter parameters
$courseFilter = (int)($_GET['course_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT e.*, c.course_code, c.course_name, c.credits, c.instructor_name,
           s.student_id AS student_code, u.name AS student_name, u.email AS student_email,
           g.letter_grade, g.total_score
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN grades g ON e.id = g.enrollment_id
    WHERE 1=1
";
$params = [];

if ($courseFilter > 0) {
    $query .= " AND e.course_id = ?";
    $params[] = $courseFilter;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR s.student_id LIKE ? OR c.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY e.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Fetch all courses and students for the manual enrollment modal & filter
$allCourses = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code ASC")->fetchAll();
$allStudents = $pdo->query("SELECT s.id, s.student_id, u.name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-id-card-clip text-primary me-2"></i>Course Enrollments</h4>
        <p class="text-muted small mb-0">Monitor active student enrollments, register students into courses, and manage rosters</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminEnrollModal">
            <i class="fa-solid fa-user-plus me-1"></i> Enroll Student
        </button>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card-academic mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by student name, ID, or course code..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="course_id" class="form-select">
                    <option value="">All Courses</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['course_code']) ?> &mdash; <?= e($c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <?php if ($courseFilter > 0 || !empty($search)): ?>
                    <a href="<?= BASE_URL ?>admin/enrollments.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Enrollments Table -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Active Rosters</h6>
        <span class="badge bg-light text-secondary border"><?= count($enrollments) ?> Enrollments</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($enrollments)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-folder-open text-muted fs-1 mb-3"></i>
                <h6>No enrollments found</h6>
                <p class="text-muted small">Try modifying search filters or enroll a student.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEnrollModal">
                    Enroll Student Now
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Enrolled Course</th>
                            <th>Term / Year</th>
                            <th>Enrollment Date</th>
                            <th>Current Grade</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $e): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($e['student_name']) ?></div>
                                    <code class="small text-muted font-monospace"><?= e($e['student_code']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1 me-1">
                                        <?= e($e['course_code']) ?>
                                    </span>
                                    <span class="fw-semibold text-dark"><?= e($e['course_name']) ?></span>
                                    <div class="small text-muted"><?= (int)$e['credits'] ?> cr &bull; <?= e($e['instructor_name']) ?></div>
                                </td>
                                <td>
                                    <span class="small text-dark"><?= e($e['semester']) ?></span>
                                    <div class="small text-muted"><?= e($e['academic_year']) ?></div>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_date($e['enrollment_date']) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($e['letter_grade']) && (float)$e['total_score'] > 0): ?>
                                        <?= grade_badge($e['letter_grade']) ?>
                                        <small class="text-muted ms-1"><?= number_format((float)$e['total_score'], 1) ?>%</small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= status_badge($e['status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>admin/grades.php?course_id=<?= $e['course_id'] ?>" class="btn btn-sm btn-outline-primary" title="Assign / Edit Grade">
                                        <i class="fa-solid fa-award"></i> Grade
                                    </a>
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="remove_enrollment">
                                        <input type="hidden" name="enrollment_id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove <?= e($e['student_name']) ?> from <?= e($e['course_code']) ?>? Associated grade records will be deleted." title="Remove Enrollment">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Enroll Student -->
<div class="modal fade" id="adminEnrollModal" tabindex="-1" aria-labelledby="adminEnrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="adminEnrollModalLabel"><i class="fa-solid fa-user-plus me-2"></i>Enroll Student into Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="admin_enroll">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="student_id" class="form-label small fw-semibold text-secondary">Select Student <span class="text-danger">*</span></label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($allStudents as $st): ?>
                                <option value="<?= $st['id'] ?>">
                                    <?= e($st['name']) ?> (<?= e($st['student_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="course_id" class="form-label small fw-semibold text-secondary">Select Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($allCourses as $co): ?>
                                <option value="<?= $co['id'] ?>">
                                    <?= e($co['course_code']) ?> &mdash; <?= e($co['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="semester" class="form-label small fw-semibold text-secondary">Academic Term <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="semester" name="semester" value="<?= DEFAULT_SEMESTER ?>" required>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check me-1"></i> Confirm Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
