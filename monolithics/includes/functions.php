<?php
/**
 * UniConnect - Monolithic University Management System
 * General Utility and Helper Functions
 */

/**
 * Escapes HTML entities for safe output.
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizes input string (trim and strip tags).
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim(strip_tags((string)$data));
}

/**
 * Flash messaging system.
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function has_flash() {
    return isset($_SESSION['flash']);
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF Protection.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Grade calculation formula.
 * Standard weights: Assignment (20%), Midterm (30%), Final (50%)
 */
function calculate_grade($assignment, $midterm, $final) {
    $assignment = max(0, min(100, (float)$assignment));
    $midterm = max(0, min(100, (float)$midterm));
    $final = max(0, min(100, (float)$final));

    $total = ($assignment * 0.20) + ($midterm * 0.30) + ($final * 0.50);
    $total = round($total, 2);

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
        'total' => $total,
        'letter' => $letter,
        'point' => $point
    ];
}

/**
 * Calculates student cumulative GPA.
 */
function calculate_student_gpa($pdo, $student_id) {
    $sql = "SELECT g.grade_point, c.credits 
            FROM grades g
            JOIN enrollments e ON g.enrollment_id = e.id
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ? AND g.total_score > 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $grades = $stmt->fetchAll();

    if (empty($grades)) {
        return [
            'gpa' => '0.00',
            'total_credits' => 0,
            'courses_graded' => 0
        ];
    }

    $totalPoints = 0;
    $totalCredits = 0;
    foreach ($grades as $row) {
        $credits = (int)$row['credits'];
        $point = (float)$row['grade_point'];
        $totalPoints += ($point * $credits);
        $totalCredits += $credits;
    }

    $gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.00;

    return [
        'gpa' => number_format($gpa, 2),
        'total_credits' => $totalCredits,
        'courses_graded' => count($grades)
    ];
}

/**
 * Adds an in-app notification for a user.
 */
function add_notification($pdo, $user_id, $title, $message, $type = 'info', $link = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves unread notification count.
 */
function get_unread_notifications_count($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Returns HTML badge for status strings.
 */
function status_badge($status) {
    $status = strtolower(trim($status));
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

/**
 * Returns badge for letter grade.
 */
function grade_badge($letter) {
    $letter = strtoupper(trim($letter));
    if (strpos($letter, 'A') !== false) {
        return '<span class="badge bg-success px-2 py-1 fs-6">' . $letter . '</span>';
    } elseif (strpos($letter, 'B') !== false) {
        return '<span class="badge bg-primary px-2 py-1 fs-6">' . $letter . '</span>';
    } elseif (strpos($letter, 'C') !== false) {
        return '<span class="badge bg-info text-dark px-2 py-1 fs-6">' . $letter . '</span>';
    } elseif (strpos($letter, 'D') !== false) {
        return '<span class="badge bg-warning text-dark px-2 py-1 fs-6">' . $letter . '</span>';
    } else {
        return '<span class="badge bg-danger px-2 py-1 fs-6">' . $letter . '</span>';
    }
}

/**
 * Date/time formatting.
 */
function format_date($datetime, $format = 'M d, Y') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function format_datetime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Generates an automatic reference number for certificates.
 */
function generate_cert_reference() {
    return 'UC-CERT-' . date('Y') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Generates a unique Student ID (e.g. STD2024006).
 */
function generate_next_student_id($pdo) {
    $year = date('Y');
    $prefix = "STD" . $year;
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();

    if ($lastId) {
        $numPart = (int)substr($lastId, strlen($prefix));
        $nextNum = str_pad((string)($numPart + 1), 3, '0', STR_PAD_LEFT);
    } else {
        $nextNum = '001';
    }

    return $prefix . $nextNum;
}

/**
 * Returns a list of realistic academic semester options centered around the current year.
 */
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
