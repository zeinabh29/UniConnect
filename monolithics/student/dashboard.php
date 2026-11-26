<?php
/**
 * UniConnect - Monolithic University Management System
 * Student Portal: Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_student();

$pageTitle = 'Student Dashboard';
$pdo = get_db_connection();
$user = current_user();

// Fetch student full record
$stmtStudent = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.created_at AS member_since
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
    LIMIT 1
");
$stmtStudent->execute([$user['id']]);
$student = $stmtStudent->fetch();

if (!$student) {
    die("Student profile not found. Please contact an administrator.");
}

$studentId = $student['id'];

// Calculate academic metrics (GPA, credits, etc.)
$gpaData = calculate_student_gpa($pdo, $studentId);

// Fetch enrolled courses for current semester
$stmtEnrollments = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.enrollment_date, e.status AS enrollment_status,
           c.id AS course_id, c.course_code, c.course_name, c.department, c.credits, c.instructor_name,
           g.assignment_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade, g.grade_point
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN grades g ON e.id = g.enrollment_id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_code ASC
");
$stmtEnrollments->execute([$studentId]);
$enrolledCourses = $stmtEnrollments->fetchAll();
$totalEnrolledCount = count($enrolledCourses);

// Fetch certificate requests
$stmtCerts = $pdo->prepare("
    SELECT * FROM certificate_requests
    WHERE student_id = ?
    ORDER BY requested_at DESC
    LIMIT 5
");
$stmtCerts->execute([$studentId]);
$recentCerts = $stmtCerts->fetchAll();

// Count pending certificates
$stmtPendingCerts = $pdo->prepare("SELECT COUNT(*) FROM certificate_requests WHERE student_id = ? AND status = 'pending'");
$stmtPendingCerts->execute([$studentId]);
$pendingCertsCount = (int)$stmtPendingCerts->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card border-0 rounded-4 text-white p-4 mb-4" style="background: linear-gradient(135deg, #0f4c81 0%, #1e3a8a 100%);">
    <div class="row align-items-center">
        <div class="col-md-8">
            <span class="badge bg-white text-primary mb-2 px-3 py-1 fw-bold"><?= e($student['department']) ?></span>
            <h2 class="fw-bold mb-1">Welcome back, <?= e($student['name']) ?>!</h2>
            <p class="text-white-50 mb-0">
                Student ID: <strong><?= e($student['student_id']) ?></strong> &bull; Level: <strong><?= e($student['academic_year']) ?></strong> &bull; Semester: <strong><?= e($student['semester']) ?></strong>
            </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-light text-primary fw-semibold me-2 shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Enroll Courses
            </a>
            <a href="<?= BASE_URL ?>student/certificates.php" class="btn btn-outline-light">
                <i class="fa-solid fa-file-signature me-1"></i> Request Doc
            </a>
        </div>
    </div>
</div>

<!-- Stat Widgets Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget">
            <div class="stat-icon-wrapper stat-icon-blue">
                <i class="fa-solid fa-book-open-reader"></i>
            </div>
            <div>
                <div class="stat-value"><?= $totalEnrolledCount ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget">
            <div class="stat-icon-wrapper stat-icon-green">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div class="stat-value"><?= $gpaData['gpa'] ?> <small class="text-muted fs-6">/ 4.0</small></div>
                <div class="stat-label">Cumulative GPA</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget">
            <div class="stat-icon-wrapper stat-icon-purple">
                <i class="fa-solid fa-award"></i>
            </div>
            <div>
                <div class="stat-value"><?= $gpaData['total_credits'] ?></div>
                <div class="stat-label">Earned Credits</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget">
            <div class="stat-icon-wrapper stat-icon-amber">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
            <div>
                <div class="stat-value"><?= $pendingCertsCount ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Enrolled Courses Card -->
    <div class="col-lg-8">
        <div class="card-academic h-100">
            <div class="card-header-custom">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>My Enrolled Courses</h5>
                    <small class="text-muted">Courses currently registered for <?= e($student['semester']) ?></small>
                </div>
                <a href="<?= BASE_URL ?>student/my-courses.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($enrolledCourses)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-folder-open text-muted fs-1 mb-3"></i>
                        <h6>No courses enrolled yet</h6>
                        <p class="text-muted small">You haven't enrolled in any courses for the current semester.</p>
                        <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-compass me-1"></i> Browse Course Catalog
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-academic mb-0">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Credits</th>
                                    <th>Instructor</th>
                                    <th>Current Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledCourses as $course): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1">
                                                <?= e($course['course_code']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($course['course_name']) ?></div>
                                            <small class="text-muted"><?= e($course['department']) ?></small>
                                        </td>
                                        <td><span class="fw-semibold"><?= (int)$course['credits'] ?></span> cr</td>
                                        <td>
                                            <small class="text-secondary"><i class="fa-regular fa-user me-1"></i><?= e($course['instructor_name']) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($course['letter_grade'])): ?>
                                                <?= grade_badge($course['letter_grade']) ?>
                                                <small class="text-muted ms-1">(<?= number_format((float)$course['total_score'], 1) ?>%)</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Student Profile & Academic Progress Summary -->
    <div class="col-lg-4">
        <div class="card-academic mb-4">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>Academic Summary</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Student ID</span>
                        <span class="fw-bold font-monospace text-dark"><?= e($student['student_id']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Department</span>
                        <span class="fw-semibold text-dark"><?= e($student['department']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Academic Standing</span>
                        <span class="badge bg-success-subtle text-success">Good Standing</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Semester</span>
                        <span class="fw-semibold text-dark"><?= e($student['semester']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Total Graded Courses</span>
                        <span class="fw-semibold text-dark"><?= $gpaData['courses_graded'] ?></span>
                    </li>
                </ul>

                <hr class="my-3">

                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Progress to Graduation</span>
                        <span class="fw-semibold text-primary"><?= min(100, round(($gpaData['total_credits'] / 120) * 100)) ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, round(($gpaData['total_credits'] / 120) * 100)) ?>%"></div>
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem; margin-top: 4px;">
                        <?= $gpaData['total_credits'] ?> / 120 total required credits
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?= BASE_URL ?>student/grades.php" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fa-solid fa-file-lines me-1"></i> View Full Academic Transcript
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Certificate Requests -->
        <div class="card-academic">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-shield text-primary me-2"></i>Certificate Status</h6>
                <a href="<?= BASE_URL ?>student/certificates.php" class="small text-decoration-none">Manage</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentCerts)): ?>
                    <div class="text-center py-4 text-muted small">No certificate requests submitted yet.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($recentCerts as $cert): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-semibold text-dark"><?= e($cert['certificate_type']) ?></div>
                                    <?= status_badge($cert['status']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    Ref: <code><?= e($cert['reference_no'] ?? 'N/A') ?></code> &bull; <?= format_date($cert['requested_at']) ?>
                                </div>
                                <?php if ($cert['status'] === 'approved' || $cert['status'] === 'completed'): ?>
                                    <div class="mt-2">
                                        <a href="<?= BASE_URL ?>student/certificate-view.php?id=<?= $cert['id'] ?>" class="btn btn-xs btn-outline-success py-1 px-2 text-decoration-none small" style="font-size: 0.75rem;" target="_blank">
                                            <i class="fa-solid fa-print me-1"></i> View Official Document
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
