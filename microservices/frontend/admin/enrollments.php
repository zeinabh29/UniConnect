<?php
require_once __DIR__ . '/../config/gateway_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$search        = trim($_GET['search'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll') {
        $payload = [
            'student_id' => (int)($_POST['student_id'] ?? 0),
            'course_id'  => (int)($_POST['course_id']  ?? 0),
            'semester'   => trim($_POST['semester'] ?? ''),
        ];
        $res = api_post('/api/enrollments', $payload);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Student enrolled successfully.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Enrollment failed.');
        }
    } elseif ($action === 'remove') {
        $eid = (int)($_POST['enrollment_id'] ?? 0);
        $res = api_delete("/api/enrollments/{$eid}");
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Enrollment removed.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Failed to remove enrollment.');
        }
    }
    $qs = http_build_query(array_filter(['course_id' => $course_filter ?: '', 'search' => $search]));
    header('Location: enrollments.php' . ($qs ? "?{$qs}" : ''));
    exit;
}

// Fetch enrollments (the service returns enrollments + courses in one call)
$params = [];
if ($search)        $params['search']    = $search;
if ($course_filter) $params['course_id'] = $course_filter;
$res = api_get('/api/enrollments', $params);
$enrollments = $res['body']['data']['enrollments'] ?? [];
$courses     = $res['body']['data']['courses']     ?? [];   // active courses included in response

// Fetch students for the enroll modal
$sres     = api_get('/api/students');
$students = $sres['body']['data']['students'] ?? [];

$page_title = 'Enrollment Management';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
  <div class="content-header">
    <h1><i class="fas fa-user-graduate me-2"></i>Enrollment Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollModal">
      <i class="fas fa-plus me-1"></i>Enroll Student
    </button>
  </div>

  <?php include __DIR__ . '/../includes/alerts.php'; ?>

  <!-- Filter Bar -->
  <div class="card card-academic mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Student name, ID or course..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Filter by Course</label>
          <select name="course_id" class="form-select">
            <option value="">All Courses</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $course_filter == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-outline-primary me-2"><i class="fas fa-search me-1"></i>Search</button>
          <a href="enrollments.php" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Enrollments Table -->
  <div class="card card-academic">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-list me-2"></i>Enrollments
        <span class="badge bg-secondary ms-2"><?= count($enrollments) ?></span>
      </h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($enrollments)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-inbox fa-3x mb-3"></i>
          <p class="mt-2">No enrollments found.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-academic table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Course</th>
              <th>Semester</th>
              <th>Enrolled On</th>
              <th>Grade</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enrollments as $i => $e): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($e['student_name'] ?? 'N/A') ?></div>
                <small class="text-muted"><?= htmlspecialchars($e['student_code'] ?? '') ?></small>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($e['course_name'] ?? 'N/A') ?></div>
                <small class="text-muted"><?= htmlspecialchars($e['course_code'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars($e['semester'] ?? '') ?></td>
              <td><?= isset($e['enrollment_date']) ? date('M d, Y', strtotime($e['enrollment_date'])) : (isset($e['created_at']) ? date('M d, Y', strtotime($e['created_at'])) : 'N/A') ?></td>
              <td>
                <?php
                  $grade = $e['letter_grade'] ?? null;
                  if ($grade !== null && $grade !== '') {
                      $gc = in_array($grade, ['A+','A','A-','B+','B']) ? 'success' : (in_array($grade, ['B-','C+','C']) ? 'warning' : 'danger');
                      echo "<span class=\"badge bg-{$gc}\">" . htmlspecialchars($grade) . "</span>";
                  } else {
                      echo '<span class="text-muted">Not graded</span>';
                  }
                ?>
              </td>
              <td>
                <?php
                  $st  = $e['status'] ?? 'enrolled';
                  $cls = match($st) { 'enrolled' => 'success', 'completed' => 'primary', 'dropped' => 'danger', default => 'secondary' };
                  echo "<span class=\"badge bg-{$cls}\">" . ucfirst(htmlspecialchars($st)) . "</span>";
                ?>
              </td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this enrollment?')">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="enrollment_id" value="<?= $e['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                    <i class="fas fa-trash"></i>
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
</div>

<!-- Enroll Student Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="enroll">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Enroll Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select name="student_id" class="form-select" required>
              <option value="">Select student...</option>
              <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>">
                  <?= htmlspecialchars(($s['student_id'] ?? $s['student_code'] ?? '') . ' - ' . ($s['full_name'] ?? $s['name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Course <span class="text-danger">*</span></label>
            <select name="course_id" class="form-select" required>
              <option value="">Select course...</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
            <input type="text" name="semester" class="form-control" placeholder="e.g. Fall 2025" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enroll</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
