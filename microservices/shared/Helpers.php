<?php
/**
 * UniConnect Microservices Architecture
 * Shared Domain & UI Utility Functions
 */

function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim(strip_tags((string)$data));
}

/**
 * Grade calculation formula.
 * Standard weights: Assignment (20%), Midterm (30%), Final (50%)
 */
function calculate_grade($assignment, $midterm, $final) {
    $assignment = max(0, min(100, (float)$assignment));
    $midterm    = max(0, min(100, (float)$midterm));
    $final      = max(0, min(100, (float)$final));

    $total = round(($assignment * 0.20) + ($midterm * 0.30) + ($final * 0.50), 2);

    if ($total >= 93) {
        $letter = 'A+';
        $point = 4.00;
    } elseif ($total >= 90) {
        $letter = 'A';
        $point = 4.00;
    } elseif ($total >= 87) {
        $letter = 'A-';
        $point = 3.70;
    } elseif ($total >= 83) {
        $letter = 'B+';
        $point = 3.30;
    } elseif ($total >= 80) {
        $letter = 'B';
        $point = 3.00;
    } elseif ($total >= 77) {
        $letter = 'B-';
        $point = 2.70;
    } elseif ($total >= 73) {
        $letter = 'C+';
        $point = 2.30;
    } elseif ($total >= 70) {
        $letter = 'C';
        $point = 2.00;
    } elseif ($total >= 60) {
        $letter = 'D';
        $point = 1.00;
    } else {
        $letter = 'F';
        $point = 0.00;
    }

    return [
        'total'  => $total,
        'letter' => $letter,
        'point'  => $point,
    ];
}

function generate_cert_reference() {
    return 'UC-CERT-' . date('Y') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function get_academic_semester_options($selectedSemester = null) {
    $currentYear = (int)date('Y');
    $options = [];
    for ($year = $currentYear - 1; $year <= $currentYear + 1; $year++) {
        $options[] = "Spring {$year}";
        $options[] = "Summer {$year}";
        $options[] = "Fall {$year}";
    }
    if (!empty($selectedSemester) && !in_array($selectedSemester, $options, true)) {
        array_unshift($options, $selectedSemester);
    }
    return $options;
}

function format_date($datetime, $format = 'M d, Y') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function format_datetime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function status_badge($status) {
    $status = strtolower(trim((string)$status));
    switch ($status) {
        case 'active':
        case 'enrolled':
        case 'approved':
        case 'completed':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> ' . ucfirst($status) . '</span>';
        case 'pending':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="fa-solid fa-clock me-1"></i> ' . ucfirst($status) . '</span>';
        case 'rejected':
        case 'dropped':
        case 'inactive':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> ' . ucfirst($status) . '</span>';
        default:
            return '<span class="badge bg-secondary-subtle text-secondary px-2 py-1">' . ucfirst($status) . '</span>';
    }
}

function grade_badge($letter) {
    $letter = strtoupper(trim((string)$letter));
    if (strpos($letter, 'A') !== false) {
        return '<span class="badge bg-success px-2 py-1 fs-6">' . e($letter) . '</span>';
    } elseif (strpos($letter, 'B') !== false) {
        return '<span class="badge bg-primary px-2 py-1 fs-6">' . e($letter) . '</span>';
    } elseif (strpos($letter, 'C') !== false) {
        return '<span class="badge bg-info text-dark px-2 py-1 fs-6">' . e($letter) . '</span>';
    } elseif (strpos($letter, 'D') !== false) {
        return '<span class="badge bg-warning text-dark px-2 py-1 fs-6">' . e($letter) . '</span>';
    } else {
        return '<span class="badge bg-danger px-2 py-1 fs-6">' . e($letter) . '</span>';
    }
}
