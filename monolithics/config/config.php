<?php
/**
 * UniConnect - Monolithic University Management System
 * Global Application Configuration
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
define('APP_NAME', 'UniConnect');
define('APP_TAGLINE', 'Unified Monolithic University Management Portal');
define('APP_VERSION', '1.0.0');

// Base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';
$currentDir = str_replace('\\', '/', dirname(__DIR__));
$subDir = str_replace($docRoot, '', $currentDir);
$baseUrl = rtrim($protocol . $host . $subDir, '/') . '/';

define('BASE_URL', $baseUrl);

// Dynamic Academic Term & Year Calculation
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');

if ($currentMonth >= 9) {
    $computedSemester = "Fall {$currentYear}";
    $computedAcadYear = "{$currentYear}-" . ($currentYear + 1);
} elseif ($currentMonth >= 6) {
    $computedSemester = "Summer {$currentYear}";
    $computedAcadYear = ($currentYear - 1) . "-{$currentYear}";
} else {
    $computedSemester = "Spring {$currentYear}";
    $computedAcadYear = ($currentYear - 1) . "-{$currentYear}";
}

define('DEFAULT_ACADEMIC_YEAR', $computedAcadYear);
define('DEFAULT_SEMESTER', $computedSemester);

// Required Core Includes
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
