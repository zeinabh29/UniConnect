<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Student Portal: My Enrolled Courses
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_student();

$pageTitle = 'My Enrolled Courses';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'drop') {
    $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
    $csrfToken    = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $res = api_post("/api/enrollments/{$enrollmentId}/drop");
        if ($res['status'] === 200 && !empty($res['body']['success'])) {
            set_flash('info', $res['body']['message'] ?? 'Course dropped successfully.');
        } else {
            set_flash('danger', $res['body']['error'] ?? 'Failed to drop course.');
        }
    }
    header("Location: " . BASE_URL . "student/my-courses.php");
    exit;
}

$res = api_get('/api/enrollments');
$courses      = $res['body']['data']['enrollments'] ?? [];
$totalCredits = (int)($res['body']['data']['total_credits'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>My Enrolled Courses</h4>
        <p class="text-muted small mb-0">Current academic schedule for <?= DEFAULT_SEMESTER ?> &bull; <?= count($courses) ?> courses registered (<?= $totalCredits ?> total credits)</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle me-1"></i> Register More Courses
        </a>
    </div>
</div>

<?php if (empty($courses)): ?>
    <div class="card-academic text-center py-5">
        <i class="fa-solid fa-calendar-xmark text-muted fs-1 mb-3"></i>
        <h5>No active enrollments for this semester</h5>
        <p class="text-muted small">You have not registered for any courses in <?= DEFAULT_SEMESTER ?>.</p>
        <div>
            <a href="<?= BASE_URL ?>student/courses.php" class="btn btn-primary">
                <i class="fa-solid fa-compass me-1"></i> Go to Course Catalog
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card-academic">
        <div class="table-responsive">
            <table class="table table-academic mb-0">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name &amp; Department</th>
                        <th>Credits</th>
                        <th>Instructor</th>
                        <th>Enrolled Date</th>
                        <th>Academic Progress</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1 fs-6">
                                    <?= e($c['course_code']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($c['course_name']) ?></div>
                                <small class="text-muted"><?= e($c['department']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border fw-semibold"><?= (int)$c['credits'] ?> Credits</span>
                            </td>
                            <td>
                                <div class="small text-dark"><i class="fa-regular fa-user text-muted me-1"></i><?= e($c['instructor_name']) ?></div>
                            </td>
                            <td>
                                <small class="text-muted"><?= format_date($c['enrollment_date']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($c['letter_grade']) && (float)$c['total_score'] > 0): ?>
                                    <?= grade_badge($c['letter_grade']) ?>
                                    <small class="text-muted ms-1"><?= number_format((float)$c['total_score'], 1) ?>%</small>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="drop">
                                    <input type="hidden" name="enrollment_id" value="<?= (int)$c['enrollment_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to drop <?= e($c['course_code']) ?>? This action cannot be undone.">
                                        <i class="fa-solid fa-trash-can me-1"></i> Drop
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
