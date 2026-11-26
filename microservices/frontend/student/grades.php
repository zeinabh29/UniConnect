<?php
/**
 * UniConnect Microservices Architecture
 * Frontend Student Portal: Academic Transcript & Grades
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_student();

$pageTitle = 'Grades & Academic Transcript';

$profileRes = api_get('/api/student/profile');
$gradesRes  = api_get('/api/grades');

$student = $profileRes['body']['data'] ?? current_user();
$gpaData = $gradesRes['body']['data']['gpa_data'] ?? ['gpa' => '0.00', 'total_credits' => 0, 'courses_graded' => 0];
$records = $gradesRes['body']['data']['records'] ?? [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-square-poll-vertical text-primary me-2"></i>Academic Transcript &amp; Grades</h4>
        <p class="text-muted small mb-0">Official record of coursework, performance assessments, and cumulative grade points</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Transcript
        </button>
        <a href="<?= BASE_URL ?>student/certificates.php" class="btn btn-primary">
            <i class="fa-solid fa-file-contract me-1"></i> Request Official Copy
        </a>
    </div>
</div>

<!-- Transcript Academic Summary Header -->
<div class="card-academic p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-6 mb-3 mb-md-0">
            <h5 class="fw-bold text-dark mb-1"><?= e($student['name']) ?></h5>
            <div class="text-muted small mb-1">
                Student ID: <strong class="font-monospace text-dark"><?= e($student['student_id']) ?></strong> &bull; Department: <strong><?= e($student['department']) ?></strong>
            </div>
            <div class="text-muted small">
                Level: <strong><?= e($student['academic_year'] ?? 'Year 1') ?></strong> &bull; Status: <span class="badge bg-success-subtle text-success">Active Enrolled</span>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-inline-flex gap-4 text-center">
                <div class="p-2 border rounded-3 bg-light" style="min-width: 120px;">
                    <div class="text-muted small">Cumulative GPA</div>
                    <div class="fw-bold fs-3 text-primary"><?= e($gpaData['gpa']) ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;">out of 4.00</div>
                </div>
                <div class="p-2 border rounded-3 bg-light" style="min-width: 120px;">
                    <div class="text-muted small">Earned Credits</div>
                    <div class="fw-bold fs-3 text-success"><?= (int)$gpaData['total_credits'] ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;">Credit Hours</div>
                </div>
                <div class="p-2 border rounded-3 bg-light" style="min-width: 120px;">
                    <div class="text-muted small">Academic Standing</div>
                    <div class="fw-bold fs-5 text-dark mt-1">
                        <?= (float)$gpaData['gpa'] >= 3.5 ? "Dean's List" : 'Good Standing' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Grades Table -->
<div class="card-academic mb-4">
    <div class="card-header-custom no-print">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Course Assessment Breakdown</h6>
        <span class="text-muted small">Standard Grading: Assignment (20%), Midterm (30%), Final Exam (50%)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-file-circle-question text-muted fs-1 mb-3"></i>
                <h6>No grade records found</h6>
                <p class="text-muted small">You do not have any enrolled or graded courses yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-academic mb-0">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Term</th>
                            <th class="text-center">Credits</th>
                            <th class="text-center">Assignment (20%)</th>
                            <th class="text-center">Midterm (30%)</th>
                            <th class="text-center">Final (50%)</th>
                            <th class="text-center">Total (100%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">GPA Point</th>
                            <th>Instructor Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <?php $hasGrade = !empty($r['letter_grade']) && (float)$r['total_score'] > 0; ?>
                            <tr>
                                <td>
                                    <div class="font-monospace fw-bold text-primary"><?= e($r['course_code']) ?></div>
                                    <div class="fw-semibold text-dark"><?= e($r['course_name']) ?></div>
                                    <small class="text-muted"><?= e($r['instructor_name']) ?></small>
                                </td>
                                <td><span class="small"><?= e($r['semester']) ?></span></td>
                                <td class="text-center fw-semibold"><?= (int)$r['credits'] ?></td>
                                <td class="text-center"><?= $hasGrade ? number_format((float)$r['assignment_score'], 1) : '-' ?></td>
                                <td class="text-center"><?= $hasGrade ? number_format((float)$r['midterm_score'], 1) : '-' ?></td>
                                <td class="text-center"><?= $hasGrade ? number_format((float)$r['final_score'], 1) : '-' ?></td>
                                <td class="text-center fw-bold"><?= $hasGrade ? number_format((float)$r['total_score'], 1) . '%' : '-' ?></td>
                                <td class="text-center">
                                    <?php if ($hasGrade): ?>
                                        <?= grade_badge($r['letter_grade']) ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-dark"><?= $hasGrade ? number_format((float)$r['grade_point'], 2) : '-' ?></td>
                                <td>
                                    <small class="text-muted fst-italic">
                                        <?= e($r['remarks'] ?: ($hasGrade ? 'Course completed satisfactorily.' : 'Grade pending finalization.')) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grading Legend Card -->
<div class="card-academic no-print">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-circle-info text-primary me-2"></i>University Grading Scale Reference</h6>
        <div class="row g-2 small text-center">
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-success mb-1">A+ / A</span>
                <div class="fw-bold">4.00</div>
                <div class="text-muted" style="font-size:0.75rem;">90% - 100%</div>
            </div>
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-success mb-1">A-</span>
                <div class="fw-bold">3.70</div>
                <div class="text-muted" style="font-size:0.75rem;">87% - 89%</div>
            </div>
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-primary mb-1">B+</span>
                <div class="fw-bold">3.30</div>
                <div class="text-muted" style="font-size:0.75rem;">83% - 86%</div>
            </div>
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-primary mb-1">B</span>
                <div class="fw-bold">3.00</div>
                <div class="text-muted" style="font-size:0.75rem;">80% - 82%</div>
            </div>
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-info text-dark mb-1">C+ / C</span>
                <div class="fw-bold">2.30 / 2.00</div>
                <div class="text-muted" style="font-size:0.75rem;">70% - 79%</div>
            </div>
            <div class="col-4 col-md-2 p-2 border rounded bg-light">
                <span class="badge bg-danger mb-1">D / F</span>
                <div class="fw-bold">1.00 / 0.00</div>
                <div class="text-muted" style="font-size:0.75rem;">Below 70%</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
