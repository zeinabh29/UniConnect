<?php
require_once __DIR__ . '/../config/gateway_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$search        = trim($_GET['search'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);

// Handle grade save (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_grade') {
        $payload = [
            'enrollment_id'    => (int)($_POST['enrollment_id'] ?? 0),
            'assignment_score' => $_POST['assignment_score'] !== '' ? (float)$_POST['assignment_score'] : 0,
            'midterm_score'    => $_POST['midterm_score']    !== '' ? (float)$_POST['midterm_score']    : 0,
            'final_score'      => $_POST['final_score']      !== '' ? (float)$_POST['final_score']      : 0,
            'remarks'          => trim($_POST['remarks'] ?? ''),
        ];
        $res = api_post('/api/grades', $payload);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            set_flash('success', 'Grade saved successfully.');
        } else {
            set_flash('error', $res['body']['error'] ?? 'Failed to save grade.');
        }
    }
    $qs = http_build_query(array_filter(['course_id' => $course_filter ?: '', 'search' => $search]));
    header("Location: grades.php" . ($qs ? "?{$qs}" : ''));
    exit;
}

// Fetch grades — service returns: { records:[], class_avg:{}, courses:[] }
$params = [];
if ($search)        $params['search']    = $search;
if ($course_filter) $params['course_id'] = $course_filter;
$res      = api_get('/api/grades', $params);
$data     = $res['body']['data'] ?? [];
$grades   = $data['records']   ?? [];
$stats    = $data['class_avg'] ?? null;
$courses  = $data['courses']   ?? [];

$page_title = 'Grade Management';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
  <div class="content-header">
    <h1><i class="fas fa-star me-2"></i>Grade Management</h1>
  </div>

  <?php include __DIR__ . '/../includes/alerts.php'; ?>

  <!-- Filter Bar -->
  <div class="card card-academic mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Search Student</label>
          <input type="text" name="search" class="form-control" placeholder="Name or student ID..." value="<?= htmlspecialchars($search) ?>">
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
          <button type="submit" class="btn btn-outline-primary me-2"><i class="fas fa-search me-1"></i>Filter</button>
          <a href="grades.php" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Course Stats Widget (shown when filtered by course and stats exist) -->
  <?php if ($stats && $course_filter && isset($stats['graded_count']) && (int)$stats['graded_count'] > 0): ?>
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="stat-widget">
        <div class="stat-value"><?= count($grades) ?></div>
        <div class="stat-label">Students Enrolled</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-widget">
        <div class="stat-value"><?= number_format((float)($stats['avg_score'] ?? 0), 1) ?></div>
        <div class="stat-label">Class Average (%)</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-widget">
        <div class="stat-value"><?= number_format((float)($stats['avg_gpa'] ?? 0), 2) ?></div>
        <div class="stat-label">Average GPA</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-widget">
        <div class="stat-value"><?= (int)($stats['graded_count'] ?? 0) ?></div>
        <div class="stat-label">Graded Students</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Grades Table -->
  <div class="card card-academic">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-table me-2"></i>Grade Sheet
        <span class="badge bg-secondary ms-2"><?= count($grades) ?></span>
      </h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($grades)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-star-half-alt fa-3x mb-3"></i>
          <p class="mt-2">No grade records found. Select a course or search for a student.</p>
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
              <th>Assignment</th>
              <th>Midterm</th>
              <th>Final</th>
              <th>Total</th>
              <th>Grade</th>
              <th>Remarks</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grades as $i => $g):
              $total = $g['total_score'] ?? null;
              $lg    = $g['letter_grade'] ?? null;
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($g['student_name'] ?? 'N/A') ?></div>
                <small class="text-muted"><?= htmlspecialchars($g['student_code'] ?? '') ?></small>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($g['course_name'] ?? 'N/A') ?></div>
                <small class="text-muted"><?= htmlspecialchars($g['course_code'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars($g['semester'] ?? '') ?></td>
              <td><?= $g['assignment_score'] !== null ? number_format((float)$g['assignment_score'], 1) : '<span class="text-muted">-</span>' ?></td>
              <td><?= $g['midterm_score']    !== null ? number_format((float)$g['midterm_score'],    1) : '<span class="text-muted">-</span>' ?></td>
              <td><?= $g['final_score']      !== null ? number_format((float)$g['final_score'],      1) : '<span class="text-muted">-</span>' ?></td>
              <td><?= $total !== null ? '<strong>' . number_format((float)$total, 1) . '</strong>' : '<span class="text-muted">-</span>' ?></td>
              <td>
                <?php if ($lg): $gc = in_array($lg, ['A+','A','A-','B+','B']) ? 'success' : (in_array($lg, ['B-','C+','C']) ? 'warning' : 'danger'); ?>
                  <span class="badge bg-<?= $gc ?>"><?= htmlspecialchars($lg) ?></span>
                <?php else: ?><span class="text-muted">-</span><?php endif; ?>
              </td>
              <td><small><?= htmlspecialchars($g['remarks'] ?? '') ?></small></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gradeModal"
                  data-eid="<?= $g['enrollment_id'] ?>"
                  data-student="<?= htmlspecialchars($g['student_name'] ?? '') ?>"
                  data-course="<?= htmlspecialchars($g['course_name'] ?? '') ?>"
                  data-assign="<?= $g['assignment_score'] ?? '' ?>"
                  data-midterm="<?= $g['midterm_score']    ?? '' ?>"
                  data-final="<?= $g['final_score']        ?? '' ?>"
                  data-remarks="<?= htmlspecialchars($g['remarks'] ?? '') ?>">
                  <i class="fas fa-edit"></i>
                </button>
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

<!-- Grade Edit Modal -->
<div class="modal fade" id="gradeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="save_grade">
        <input type="hidden" name="enrollment_id" id="modal_eid">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Grade</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3">
            <strong id="modal_student"></strong> &mdash; <span id="modal_course"></span>
          </p>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Assignment (30%)</label>
              <input type="number" name="assignment_score" id="modal_assign" class="form-control grade-input"
                     min="0" max="100" step="0.01" placeholder="0–100">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Midterm (30%)</label>
              <input type="number" name="midterm_score" id="modal_midterm" class="form-control grade-input"
                     min="0" max="100" step="0.01" placeholder="0–100">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Final (40%)</label>
              <input type="number" name="final_score" id="modal_final" class="form-control grade-input"
                     min="0" max="100" step="0.01" placeholder="0–100">
            </div>
          </div>
          <div class="mt-3 p-3 bg-light rounded">
            <strong>Live Preview: </strong>
            <span id="grade_preview" class="fs-5 fw-bold text-primary">-</span>
          </div>
          <div class="mb-3 mt-3">
            <label class="form-label fw-semibold">Remarks</label>
            <textarea name="remarks" id="modal_remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Grade</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('gradeModal');
  modal.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('modal_eid').value     = btn.dataset.eid;
    document.getElementById('modal_student').textContent = btn.dataset.student;
    document.getElementById('modal_course').textContent  = btn.dataset.course;
    document.getElementById('modal_assign').value  = btn.dataset.assign;
    document.getElementById('modal_midterm').value = btn.dataset.midterm;
    document.getElementById('modal_final').value   = btn.dataset.final;
    document.getElementById('modal_remarks').value = btn.dataset.remarks;
    calcPreview();
  });
  document.querySelectorAll('.grade-input').forEach(function(el) {
    el.addEventListener('input', calcPreview);
  });
  function calcPreview() {
    var a = parseFloat(document.getElementById('modal_assign').value)  || 0;
    var m = parseFloat(document.getElementById('modal_midterm').value) || 0;
    var f = parseFloat(document.getElementById('modal_final').value)   || 0;
    var total = (a * 0.30) + (m * 0.30) + (f * 0.40);
    var letter = '-';
    if (a || m || f) {
      if      (total >= 97) letter = 'A+';
      else if (total >= 93) letter = 'A';
      else if (total >= 90) letter = 'A-';
      else if (total >= 87) letter = 'B+';
      else if (total >= 83) letter = 'B';
      else if (total >= 80) letter = 'B-';
      else if (total >= 77) letter = 'C+';
      else if (total >= 73) letter = 'C';
      else if (total >= 70) letter = 'C-';
      else if (total >= 67) letter = 'D+';
      else if (total >= 60) letter = 'D';
      else                   letter = 'F';
    }
    document.getElementById('grade_preview').textContent = (a || m || f) ? total.toFixed(1) + ' — ' + letter : '-';
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
