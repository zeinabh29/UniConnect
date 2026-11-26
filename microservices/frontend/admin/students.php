<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Student Management
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

$pageTitle = 'Student Management';

$search     = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');
$yearFilter = trim($_GET['year'] ?? '');

$res = api_get('/api/students', [
    'search'     => $search,
    'department' => $deptFilter,
    'year'       => $yearFilter,
]);
$students    = $res['body']['data']['students'] ?? [];
$departments = $res['body']['data']['departments'] ?? [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i>Student Management</h4>
        <p class="text-muted small mb-0">View, add, update, and manage university student records and enrollments</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>admin/student-add.php" class="btn btn-primary">
            <i class="fa-solid fa-user-plus me-1"></i> Add New Student
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
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or Student ID..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e($d) ?>" <?= $deptFilter === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select">
                    <option value="">All Levels</option>
                    <option value="Year 1" <?= $yearFilter === 'Year 1' ? 'selected' : '' ?>>Year 1</option>
                    <option value="Year 2" <?= $yearFilter === 'Year 2' ? 'selected' : '' ?>>Year 2</option>
                    <option value="Year 3" <?= $yearFilter === 'Year 3' ? 'selected' : '' ?>>Year 3</option>
                    <option value="Year 4" <?= $yearFilter === 'Year 4' ? 'selected' : '' ?>>Year 4</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($deptFilter) || !empty($yearFilter)): ?>
                    <a href="<?= BASE_URL ?>admin/students.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Data Table -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users text-primary me-2"></i>Registered Students List</h6>
        <span class="badge bg-light text-secondary border"><?= count($students) ?> Students</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-user-slash text-muted fs-1 mb-3"></i>
                <h6>No students found</h6>
                <p class="text-muted small">Try modifying your search or filter terms.</p>
                <a href="<?= BASE_URL ?>admin/student-add.php" class="btn btn-primary btn-sm">Add New Student</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Profile</th>
                            <th>Department</th>
                            <th>Academic Year</th>
                            <th>Contact Phone</th>
                            <th>Active Courses</th>
                            <th>Account Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><code class="fw-bold text-dark font-monospace"><?= e($s['student_id']) ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($s['name']) ?></div>
                                    <small class="text-muted"><?= e($s['email']) ?></small>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary"><?= e($s['department']) ?></span></td>
                                <td><span class="badge bg-light text-secondary border"><?= e($s['academic_year']) ?></span></td>
                                <td><small class="text-secondary"><?= e($s['phone'] ?: 'N/A') ?></small></td>
                                <td><span class="badge bg-info-subtle text-info-emphasis"><?= (int)$s['active_courses'] ?> courses</span></td>
                                <td><?= status_badge($s['user_status']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="<?= BASE_URL ?>admin/student-edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Student">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>admin/student-delete.php" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete student <?= e($s['name']) ?> (<?= e($s['student_id']) ?>)? All associated enrollments and records will be removed." title="Delete Student">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
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
