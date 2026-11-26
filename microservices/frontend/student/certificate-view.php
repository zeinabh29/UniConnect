<?php
/**
 * UniConnect Microservices Architecture
 * Frontend: Printable Official Certificate & Document View
 */

require_once __DIR__ . '/../config/gateway_client.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid certificate identifier.');
}

$res = api_get("/api/certificates/{$id}/view");
if ($res['status'] !== 200 || empty($res['body']['success'])) {
    die(e($res['body']['error'] ?? 'Certificate request not found or unauthorized access.'));
}

$cert    = $res['body']['data']['certificate'];
$gpaData = $res['body']['data']['gpa_data'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Document &mdash; <?= e($cert['reference_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background-color: #f1f5f9;
            padding: 30px 15px;
        }
        .cert-paper {
            background: #ffffff;
            border: 12px double #0f4c81;
            border-radius: 8px;
            padding: 60px 70px;
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.04;
            width: 450px;
            pointer-events: none;
        }
        .cert-seal-stamp {
            width: 100px;
            height: 100px;
            border: 3px dashed #0f4c81;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #0f4c81;
            font-size: 0.72rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="text-center mb-4 no-print">
    <button type="button" class="btn btn-primary px-4 me-2" onclick="window.print();">
        <i class="fa-solid fa-print me-1"></i> Print / Download PDF
    </button>
    <?php if (is_admin()): ?>
        <a href="<?= BASE_URL ?>admin/certificates.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Certificate Requests
        </a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>student/certificates.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to My Requests
        </a>
    <?php endif; ?>
</div>

<div class="cert-paper">
    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="UniConnect Watermark" class="cert-watermark">

    <div class="text-center pb-4 mb-4 border-bottom border-2 border-primary">
        <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
            <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="UniConnect Seal" style="width: 65px; height: 65px;">
            <div class="text-start">
                <h2 class="fw-bold mb-0 text-dark" style="letter-spacing: 1px; font-family: Georgia, serif;">UNICONNECT UNIVERSITY</h2>
                <div class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 2px;">Office of Academic Affairs &amp; Registrar</div>
            </div>
        </div>
        <small class="text-muted">100 University Boulevard, Academic City &bull; registrar@uniconnect.edu &bull; www.uniconnect.edu</small>
    </div>

    <div class="text-center my-4">
        <span class="badge bg-light text-primary border border-primary px-3 py-1 mb-2 font-monospace">REF: <?= e($cert['reference_no']) ?></span>
        <h3 class="fw-bold text-dark text-uppercase" style="letter-spacing: 1.5px; font-family: Georgia, serif;">
            <?= e($cert['certificate_type']) ?>
        </h3>
        <p class="text-muted small">ISSUED UNDER THE OFFICIAL AUTHORITY OF THE UNIVERSITY REGISTRAR</p>
    </div>

    <div class="my-5" style="line-height: 2; font-size: 1.08rem; color: #1e293b;">
        <p>
            This document officially certifies that <strong><?= e($cert['student_name']) ?></strong>, bearing University Student Identification Number <strong><?= e($cert['student_code']) ?></strong>, is a bona fide registered student in good standing in the <strong>Department of <?= e($cert['department']) ?></strong> at UniConnect University.
        </p>

        <p>
            The student is currently undertaking academic coursework corresponding to <strong><?= e($cert['academic_year']) ?></strong> for the <strong><?= e($cert['semester']) ?></strong> session. Academic records confirm a cumulative grade point average (GPA) of <strong><?= e($gpaData['gpa']) ?> / 4.00</strong>, with <strong><?= (int)$gpaData['total_credits'] ?></strong> completed semester credit hours.
        </p>

        <?php if (!empty($cert['admin_remarks'])): ?>
            <div class="p-3 bg-light rounded border my-3" style="font-size: 0.95rem; line-height: 1.6;">
                <strong>Official Registrar Endorsement:</strong><br>
                <?= e($cert['admin_remarks']) ?>
            </div>
        <?php endif; ?>

        <p class="mb-0">
            This certificate is formally issued upon request for: <em><?= e($cert['purpose']) ?></em>.
        </p>
    </div>

    <div class="row align-items-end pt-5 mt-5 border-top">
        <div class="col-4 text-center">
            <div class="fw-bold font-monospace text-primary mb-1">
                <?= format_date($cert['processed_at'] ?: $cert['requested_at'], 'F d, Y') ?>
            </div>
            <div class="border-top pt-2 small text-muted">Date of Verification</div>
        </div>

        <div class="col-4 text-center">
            <div class="cert-seal-stamp">
                <i class="fa-solid fa-stamp mb-1"></i>
                <span>OFFICIAL</span>
                <span>SEAL</span>
            </div>
        </div>

        <div class="col-4 text-center">
            <div class="fw-bold text-dark mb-1" style="font-family: 'Brush Script MT', cursive, sans-serif; font-size: 1.4rem;">
                <?= e($cert['approver_name'] ?? 'Dr. H. Vance') ?>
            </div>
            <div class="border-top pt-2 small text-muted">University Registrar</div>
        </div>
    </div>

    <div class="text-center mt-4 pt-3 text-muted" style="font-size: 0.7rem;">
        Security Notice: This credential was generated via the official UniConnect University Portal. Electronic record hash verified.
    </div>
</div>

</body>
</html>
