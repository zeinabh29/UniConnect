<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Grades & Academic Evaluation
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$pageTitle = 'Grades Management';
$pdo = get_db_connection();
$user = current_user();

// Handle Grade Assignment / Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade') {
    $enrollmentId    = (int)($_POST['enrollment_id'] ?? 0);
    $assignmentScore = (float)($_POST['assignment_score'] ?? 0);
    $midtermScore    = (float)($_POST['midterm_score'] ?? 0);
    $finalScore      = (float)($_POST['final_score'] ?? 0);
    $remarks         = trim($_POST['remarks'] ?? '');
    $csrfToken       = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
        header("Location: " . BASE_URL . "admin/grades.php");
        exit;
    }

    if ($enrollmentId <= 0) {
        set_flash('danger', 'Invalid enrollment record.');
        header("Location: " . BASE_URL . "admin/grades.php");
        exit;
    }

    // Verify enrollment
    $stmtEnroll = $pdo->prepare("
        SELECT e.id, e.student_id, c.course_code, c.course_name, s.user_id, u.name AS student_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmtEnroll->execute([$enrollmentId]);
    $enrollData = $stmtEnroll->fetch();

    if (!$enrollData) {
        set_flash('danger', 'Enrollment not found.');
        header("Location: " . BASE_URL . "admin/grades.php");
        exit;
    }

    // Calculate grade details
    $calc = calculate_grade($assignmentScore, $midtermScore, $finalScore);

    try {
        // Check if grade record exists
        $stmtCheck = $pdo->prepare("SELECT id FROM grades WHERE enrollment_id = ? LIMIT 1");
        $stmtCheck->execute([$enrollmentId]);
        $existingGradeId = $stmtCheck->fetchColumn();

        if ($existingGradeId) {
            $stmtUpdate = $pdo->prepare("
                UPDATE grades 
                SET assignment_score = ?, midterm_score = ?, final_score = ?, total_score = ?,
                    letter_grade = ?, grade_point = ?, remarks = ?, graded_by = ?, graded_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $assignmentScore,
                $midtermScore,
                $finalScore,
                $calc['total'],
                $calc['letter'],
                $calc['point'],
                $remarks ?: null,
                $user['id'],
                $existingGradeId
            ]);
        } else {
            $stmtInsert = $pdo->prepare("
                INSERT INTO grades (enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point, remarks, graded_by, graded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([
                $enrollmentId,
                $assignmentScore,
                $midtermScore,
                $finalScore,
                $calc['total'],
                $calc['letter'],
                $calc['point'],
                $remarks ?: null,
                $user['id']
            ]);
        }

        // Notify Student
        add_notification(
            $pdo,
            $enrollData['user_id'],
            'Grade Finalized / Updated',
            "Your grade for {$enrollData['course_code']} ({$enrollData['course_name']}) has been recorded: Letter Grade {$calc['letter']} ({$calc['total']}%).",
            'success',
            'student/grades.php'
        );

        set_flash('success', "Grade for {$enrollData['student_name']} in {$enrollData['course_code']} successfully saved: {$calc['letter']} ({$calc['total']}%).");
        header("Location: " . BASE_URL . "admin/grades.php" . (isset($_GET['course_id']) ? "?course_id=" . (int)$_GET['course_id'] : ''));
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Failed to save grade: ' . $e->getMessage());
        header("Location: " . BASE_URL . "admin/grades.php");
        exit;
    }
}

// Fetch query filters
$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT e.id AS enrollment_id, e.semester, e.academic_year,
           s.student_id AS student_code, u.name AS student_name,
           c.id AS course_id, c.course_code, c.course_name, c.credits,
           g.id AS grade_id, g.assignment_score, g.midterm_score, g.final_score, g.total_score,
           g.letter_grade, g.grade_point, g.remarks, g.graded_at
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN grades g ON e.id = g.enrollment_id
    WHERE e.status = 'enrolled'
";
$params = [];

if ($selectedCourseId > 0) {
    $query .= " AND e.course_id = ?";
    $params[] = $selectedCourseId;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR s.student_id LIKE ? OR c.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.course_code ASC, u.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate Class Aggregate Statistics if filtered by course
$classAvg = null;
if ($selectedCourseId > 0) {
    $stmtStats = $pdo->prepare("
        SELECT AVG(g.total_score) AS avg_score, AVG(g.grade_point) AS avg_gpa, MAX(g.total_score) AS max_score, COUNT(g.id) AS graded_count
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        WHERE e.course_id = ? AND g.total_score > 0
    ");
    $stmtStats->execute([$selectedCourseId]);
    $classAvg = $stmtStats->fetch();
}

// All active courses for dropdown
$coursesList = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-award text-primary me-2"></i>Grades & Performance Evaluation</h4>
        <p class="text-muted small mb-0">Record, assess, and publish student coursework, midterm, and final examination marks</p>
    </div>
</div>

<!-- Course Filter & Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card-academic h-100 p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold text-secondary mb-1">Filter by Course:</label>
                    <select name="course_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All University Courses</option>
                        <?php foreach ($coursesList as $co): ?>
                            <option value="<?= $co['id'] ?>" <?= $selectedCourseId === (int)$co['id'] ? 'selected' : '' ?>>
                                <?= e($co['course_code']) ?> &mdash; <?= e($co['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold text-secondary mb-1">Search Student:</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Name or ID..." value="<?= e($search) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Course Statistics Widget -->
    <?php if ($selectedCourseId > 0 && $classAvg && (int)$classAvg['graded_count'] > 0): ?>
        <div class="col-md-5">
            <div class="card-academic h-100 p-3 bg-light">
                <div class="small fw-bold text-dark mb-2"><i class="fa-solid fa-chart-simple text-primary me-1"></i>Course Performance Metrics</div>
                <div class="d-flex justify-content-around text-center">
                    <div>
                        <div class="text-muted" style="font-size: 0.72rem;">Class Average</div>
                        <div class="fw-bold fs-5 text-primary"><?= number_format((float)$classAvg['avg_score'], 1) ?>%</div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.72rem;">Course GPA</div>
                        <div class="fw-bold fs-5 text-success"><?= number_format((float)$classAvg['avg_gpa'], 2) ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.72rem;">Top Score</div>
                        <div class="fw-bold fs-5 text-dark"><?= number_format((float)$classAvg['max_score'], 1) ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Grades Table -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-table-list text-primary me-2"></i>Student Grading Sheet</h6>
        <span class="badge bg-light text-secondary border"><?= count($records) ?> Students</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-file-excel text-muted fs-1 mb-3"></i>
                <h6>No grade records found</h6>
                <p class="text-muted small">Try selecting a different course or ensure students are enrolled.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th class="text-center">Assignment (20%)</th>
                            <th class="text-center">Midterm (30%)</th>
                            <th class="text-center">Final (50%)</th>
                            <th class="text-center">Total (100%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Points</th>
                            <th>Instructor Remarks</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <?php 
                                $hasGrade = !empty($r['letter_grade']) && (float)$r['total_score'] > 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($r['student_name']) ?></div>
                                    <code class="small text-muted font-monospace"><?= e($r['student_code']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1">
                                        <?= e($r['course_code']) ?>
                                    </span>
                                    <div class="small text-muted"><?= e($r['course_name']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?= $hasGrade ? number_format((float)$r['assignment_score'], 1) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?= $hasGrade ? number_format((float)$r['midterm_score'], 1) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?= $hasGrade ? number_format((float)$r['final_score'], 1) : '-' ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <?= $hasGrade ? number_format((float)$r['total_score'], 1) . '%' : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($hasGrade): ?>
                                        <?= grade_badge($r['letter_grade']) ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Ungraded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <?= $hasGrade ? number_format((float)$r['grade_point'], 2) : '-' ?>
                                </td>
                                <td>
                                    <small class="text-muted fst-italic text-truncate d-inline-block" style="max-width: 180px;">
                                        <?= e($r['remarks'] ?: 'None') ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gradeModal<?= $r['enrollment_id'] ?>">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> <?= $hasGrade ? 'Edit' : 'Assign' ?>
                                    </button>

                                    <!-- Grade Modal for this enrollment -->
                                    <div class="modal fade text-start" id="gradeModal<?= $r['enrollment_id'] ?>" tabindex="-1" aria-labelledby="gradeModalLabel<?= $r['enrollment_id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title fw-bold" id="gradeModalLabel<?= $r['enrollment_id'] ?>">
                                                        <i class="fa-solid fa-award me-2"></i>Grade: <?= e($r['student_name']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="save_grade">
                                                    <input type="hidden" name="enrollment_id" value="<?= $r['enrollment_id'] ?>">

                                                    <div class="modal-body p-4">
                                                        <div class="p-2 bg-light rounded border mb-3 small">
                                                            <strong>Course:</strong> <?= e($r['course_code']) ?> &mdash; <?= e($r['course_name']) ?><br>
                                                            <strong>Student ID:</strong> <?= e($r['student_code']) ?>
                                                        </div>

                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold text-secondary">Assignment (20%)</label>
                                                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="assignment_score" value="<?= (float)($r['assignment_score'] ?? 0) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold text-secondary">Midterm (30%)</label>
                                                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="midterm_score" value="<?= (float)($r['midterm_score'] ?? 0) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold text-secondary">Final Exam (50%)</label>
                                                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="final_score" value="<?= (float)($r['final_score'] ?? 0) ?>" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="p-2 rounded bg-light border d-flex justify-content-between align-items-center small">
                                                                    <span>Projected Total: <strong class="text-primary live-total"><?= number_format((float)($r['total_score'] ?? 0), 2) ?>%</strong></span>
                                                                    <span>Grade: <strong class="badge bg-primary live-letter"><?= e($r['letter_grade'] ?: 'F') ?></strong></span>
                                                                    <span>GPA Points: <strong class="text-dark live-point"><?= number_format((float)($r['grade_point'] ?? 0), 2) ?></strong></span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small fw-semibold text-secondary">Instructor Comments & Remarks</label>
                                                                <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Excellent participation and final project."><?= e($r['remarks'] ?? '') ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Grade
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
