<?php
/**
 * UniConnect - Monolithic University Management System
 * Administrator Portal: Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_admin();

$pageTitle = 'Administrator Dashboard';
$pdo = get_db_connection();

// Aggregate Metrics
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetchColumn();
$totalEnrollments = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'")->fetchColumn();
$pendingCerts = (int)$pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'pending'")->fetchColumn();
$unreadMessages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

// Recent Students
$stmtRecentStudents = $pdo->query("
    SELECT s.*, u.name, u.email, u.status AS user_status, u.created_at AS reg_date
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.id DESC
    LIMIT 5
");
$recentStudents = $stmtRecentStudents->fetchAll();

// Pending Certificate Requests
$stmtRecentCerts = $pdo->query("
    SELECT r.*, s.student_id, u.name AS student_name
    FROM certificate_requests r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at ASC
    LIMIT 5
");
$pendingCertList = $stmtRecentCerts->fetchAll();

// Department Student Distribution
$stmtDeptDist = $pdo->query("
    SELECT department, COUNT(*) as count 
    FROM students 
    GROUP BY department 
    ORDER BY count DESC
");
$deptDist = $stmtDeptDist->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card border-0 rounded-4 text-white p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
    <div class="row align-items-center">
        <div class="col-md-8">
            <span class="badge bg-primary mb-2 px-3 py-1">Administrator Control Center</span>
            <h2 class="fw-bold mb-1">UniConnect Executive Management</h2>
            <p class="text-white-50 mb-0">Academic Operations &bull; <?= DEFAULT_SEMESTER ?> &bull; System Health: Optimal</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>admin/student-add.php" class="btn btn-light text-primary fw-semibold me-2 shadow-sm">
                <i class="fa-solid fa-user-plus me-1"></i> Add Student
            </a>
            <a href="<?= BASE_URL ?>admin/course-add.php" class="btn btn-outline-light">
                <i class="fa-solid fa-plus-circle me-1"></i> Add Course
            </a>
        </div>
    </div>
</div>

<!-- Stat Widgets Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>admin/students.php" class="text-decoration-none">
            <div class="stat-widget">
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $totalStudents ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>admin/courses.php" class="text-decoration-none">
            <div class="stat-widget">
                <div class="stat-icon-wrapper stat-icon-green">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $totalCourses ?></div>
                    <div class="stat-label">Active Courses</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>admin/enrollments.php" class="text-decoration-none">
            <div class="stat-widget">
                <div class="stat-icon-wrapper stat-icon-purple">
                    <i class="fa-solid fa-id-card-clip"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $totalEnrollments ?></div>
                    <div class="stat-label">Active Enrollments</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>admin/certificates.php" class="text-decoration-none">
            <div class="stat-widget <?= $pendingCerts > 0 ? 'border-warning' : '' ?>">
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="fa-solid fa-file-circle-question"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $pendingCerts ?></div>
                    <div class="stat-label">Pending Certificates</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recent Students Table -->
    <div class="col-lg-8">
        <div class="card-academic h-100">
            <div class="card-header-custom">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i>Recently Registered Students</h5>
                    <small class="text-muted">Latest student enrollments in university database</small>
                </div>
                <a href="<?= BASE_URL ?>admin/students.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-academic mb-0">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name & Email</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $s): ?>
                                <tr>
                                    <td>
                                        <code class="fw-bold text-dark"><?= e($s['student_id']) ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($s['name']) ?></div>
                                        <small class="text-muted"><?= e($s['email']) ?></small>
                                    </td>
                                    <td><span class="small"><?= e($s['department']) ?></span></td>
                                    <td><span class="badge bg-light text-secondary border"><?= e($s['academic_year']) ?></span></td>
                                    <td><?= status_badge($s['user_status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>admin/student-edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Student">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Certificate Requests & Quick Actions -->
    <div class="col-lg-4">
        <!-- Certificate Alerts -->
        <div class="card-academic mb-4">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-circle-exclamation text-warning me-2"></i>Action Needed: Certificates</h6>
                <a href="<?= BASE_URL ?>admin/certificates.php" class="small text-decoration-none">Review</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingCertList)): ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="fa-regular fa-circle-check text-success fs-3 mb-2 d-block"></i>
                        No pending certificate requests!
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($pendingCertList as $pc): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark"><?= e($pc['certificate_type']) ?></div>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>
                                </div>
                                <div class="text-muted mb-2" style="font-size: 0.75rem;">
                                    By: <strong><?= e($pc['student_name']) ?></strong> (<?= e($pc['student_id']) ?>) &bull; <?= format_date($pc['requested_at']) ?>
                                </div>
                                <a href="<?= BASE_URL ?>admin/certificates.php" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                    Process Request <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Department Breakdown -->
        <div class="card-academic">
            <div class="card-header-custom">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-column text-primary me-2"></i>Students by Department</h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($deptDist as $d): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span><?= e($d['department']) ?></span>
                            <span class="badge bg-primary-subtle text-primary rounded-pill"><?= $d['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
