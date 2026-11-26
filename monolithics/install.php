<?php
/**
 * UniConnect - Monolithic University Management System
 * Database One-Click Installer & Diagnostic Tool
 */

require_once __DIR__ . '/config/database.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    try {
        // Connect to MySQL server without selecting database first
        $pdoServer = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sqlFile = __DIR__ . '/uniconnect_db.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL export file 'uniconnect_db.sql' not found in project root.");
        }

        $sql = file_get_contents($sqlFile);

        // Dynamically align seeded semester and academic year to the current date
        $currentMonth = (int)date('n');
        $currentYear  = (int)date('Y');
        if ($currentMonth >= 9) {
            $activeSemester = "Fall {$currentYear}";
            $activeAcadYear = "{$currentYear}-" . ($currentYear + 1);
        } elseif ($currentMonth >= 6) {
            $activeSemester = "Summer {$currentYear}";
            $activeAcadYear = ($currentYear - 1) . "-{$currentYear}";
        } else {
            $activeSemester = "Spring {$currentYear}";
            $activeAcadYear = ($currentYear - 1) . "-{$currentYear}";
        }
        $sql = str_replace(['Fall 2026', 'Fall 2024'], $activeSemester, $sql);
        $sql = str_replace(['2026-2027', '2024-2025'], $activeAcadYear, $sql);

        $pdoServer->exec($sql);

        $status = 'success';
        $message = "Database 'uniconnect_db' and all required tables have been successfully created and populated with demo data!";
    } catch (Exception $e) {
        $status = 'danger';
        $message = "Database installation failed: " . htmlspecialchars($e->getMessage());
    }
}

// Check current status
$alreadyInstalled = false;
try {
    $pdoTest = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
    ]);
    $check = $pdoTest->query("SHOW TABLES LIKE 'users'");
    if ($check && $check->rowCount() > 0) {
        $alreadyInstalled = true;
    }
} catch (Exception $e) {
    $alreadyInstalled = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniConnect Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
        }
        .installer-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-width: 650px;
            width: 100%;
            overflow: hidden;
        }
        .header-banner {
            background: #0f4c81;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .badge-academic {
            background: #f0f4f8;
            color: #0f4c81;
            font-size: 0.85rem;
            padding: 6px 14px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="installer-card">
    <div class="header-banner">
        <span class="badge-academic"><i class="fa-solid fa-graduation-cap me-1"></i> Academic Monolithic System</span>
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-database me-2"></i>UniConnect Database Setup</h2>
        <p class="mb-0 text-white-50">One-click database installation and demo data provisioning</p>
    </div>
    <div class="p-4 p-md-5">
        <?php if ($message): ?>
            <div class="alert alert-<?= $status ?> alert-dismissible fade show" role="alert">
                <i class="fa-solid <?= $status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-2"></i>
                <?= $message ?>
                <?php if ($status === 'success'): ?>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-primary btn-sm me-2"><i class="fa-solid fa-house me-1"></i> Go to Home Page</a>
                        <a href="auth/login.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-right-to-bracket me-1"></i> Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($alreadyInstalled && empty($message)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info me-2"></i>
                <strong>Database is already initialized!</strong> The database <code>uniconnect_db</code> exists and tables are ready.
                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary me-2"><i class="fa-solid fa-house me-1"></i> Home Page</a>
                    <a href="auth/login.php" class="btn btn-dark"><i class="fa-solid fa-right-to-bracket me-1"></i> Login Portal</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="card bg-light border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-server me-2 text-primary"></i>Target Configuration</h6>
                <ul class="list-unstyled mb-0 small text-muted">
                    <li><strong>Host:</strong> <?= DB_HOST ?>:<?= DB_PORT ?></li>
                    <li><strong>Database Name:</strong> <?= DB_NAME ?></li>
                    <li><strong>Database User:</strong> <?= DB_USER ?></li>
                    <li><strong>SQL Source:</strong> <code>uniconnect_db.sql</code></li>
                </ul>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="install">
            <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-semibold">
                <i class="fa-solid fa-wand-magic-sparkles me-2"></i> <?= $alreadyInstalled ? 'Re-install / Reset Database' : 'Initialize Database Now' ?>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center text-muted small">
            <p class="mb-1">You can also import <code>uniconnect_db.sql</code> directly via <strong>phpMyAdmin</strong>:</p>
            <code>http://localhost/phpmyadmin/</code>
        </div>
    </div>
</div>
</body>
</html>
