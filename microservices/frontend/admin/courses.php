<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Administrator Portal: Course Catalog Management
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_admin();

$pageTitle = 'Course Management';

$search     = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');

$res = api_get('/api/courses', ['search' => $search, 'department' => $deptFilter]);
$courses     = $res['body']['data']['courses'] ?? [];
$departments = $res['body']['data']['departments'] ?? [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i>Course Management</h4>
        <p class="text-muted small mb-0">Create, configure, and manage university academic courses and curricula</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>admin/course-add.php" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle me-1"></i> Add New Course
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card-academic mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by course code, title, or instructor..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e($d) ?>" <?= $deptFilter === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($deptFilter)): ?>
                    <a href="<?= BASE_URL ?>admin/courses.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Courses Table -->
<div class="card-academic">
    <div class="card-header-custom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i>Curriculum Courses</h6>
        <span class="badge bg-light text-secondary border"><?= count($courses) ?> Courses</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-book-bookmark text-muted fs-1 mb-3"></i>
                <h6>No courses found</h6>
                <p class="text-muted small">Try modifying search or add a new course.</p>
                <a href="<?= BASE_URL ?>admin/course-add.php" class="btn btn-primary btn-sm">Add New Course</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Title</th>
                            <th>Department</th>
                            <th>Credits</th>
                            <th>Term</th>
                            <th>Instructor</th>
                            <th>Class Capacity</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <?php
                                $enrolled = (int)$c['enrolled_students'];
                                $capacity = (int)$c['max_capacity'];
                                $isFull   = $enrolled >= $capacity;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-bold font-monospace px-2 py-1 fs-6">
                                        <?= e($c['course_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($c['course_name']) ?></div>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">
                                        <?= e($c['description'] ?: 'No description.') ?>
                                    </small>
                                </td>
                                <td><span class="small"><?= e($c['department']) ?></span></td>
                                <td><span class="fw-bold"><?= (int)$c['credits'] ?></span> cr</td>
                                <td><span class="small text-muted"><?= e($c['semester']) ?></span></td>
                                <td><small class="text-dark"><i class="fa-regular fa-user text-muted me-1"></i><?= e($c['instructor_name']) ?></small></td>
                                <td>
                                    <span class="badge <?= $isFull ? 'bg-danger-subtle text-danger' : 'bg-light text-secondary border' ?>">
                                        <?= $enrolled ?> / <?= $capacity ?>
                                    </span>
                                </td>
                                <td><?= status_badge($c['status']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="<?= BASE_URL ?>admin/course-edit.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Course">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>admin/course-delete.php" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete course <?= e($c['course_code']) ?> (<?= e($c['course_name']) ?>)? All associated enrollments and grades will also be removed." title="Delete Course">
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
