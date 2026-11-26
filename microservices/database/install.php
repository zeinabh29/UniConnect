<?php
/**
 * UniConnect Microservices Architecture
 * One-Click Database Installer & Migration Console
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/Database.php';
require_once __DIR__ . '/migrate_from_monolith.php';

$message = '';
$status  = '';
$migrationCounts = [];

$serviceDbs = [
    'Auth Service'         => DB_AUTH,
    'Student Service'      => DB_STUDENT,
    'Course Service'       => DB_COURSE,
    'Request Service'      => DB_REQUEST,
    'Notification Service' => DB_NOTIFICATION,
    'Admin Service'        => DB_ADMIN,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'install_seed') {
            $pdoServer = Database::connectServer();
            $files = ['auth_db.sql', 'student_db.sql', 'course_db.sql', 'request_db.sql', 'notification_db.sql', 'admin_db.sql'];
            foreach ($files as $file) {
                $sql = file_get_contents(__DIR__ . '/' . $file);
                $sql = str_replace(['Fall 2026', 'Fall 2024'], DEFAULT_SEMESTER, $sql);
                $sql = str_replace(['2026-2027', '2024-2025'], DEFAULT_ACADEMIC_YEAR, $sql);
                $pdoServer->exec($sql);
            }
            $status = 'success';
            $message = 'All 6 microservice databases were created and seeded with demo data!';
        } elseif ($action === 'migrate_monolith') {
            $res = run_monolith_to_microservices_migration(true);
            if ($res['source_found']) {
                $status = 'success';
                $migrationCounts = $res['counts'];
                $message = 'Existing records from monolithic database (uniconnect_db) were successfully migrated across all 6 service databases!';
            } else {
                $status = 'warning';
                $message = implode(' ', $res['errors']) . ' Use "Initialize with Seed Data" instead.';
            }
        }
    } catch (Throwable $e) {
        $status = 'danger';
        $message = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Diagnostic check for each service DB + monolith DB
$pdoServer = null;
$dbStatuses = [];
$monolithExists = false;

try {
    $pdoServer = Database::connectServer();
    $existing = $pdoServer->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    $monolithExists = in_array(DB_MONOLITH, $existing, true);

    foreach ($serviceDbs as $label => $dbName) {
        $exists = in_array($dbName, $existing, true);
        $tables = 0;
        if ($exists) {
            $tables = (int)$pdoServer->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdoServer->quote($dbName))->fetchColumn();
        }
        $dbStatuses[] = [
            'label'  => $label,
            'db'     => $dbName,
            'ready'  => $exists && $tables > 0,
            'tables' => $tables,
        ];
    }
} catch (Throwable $e) {
    $status = 'danger';
    $message = 'Cannot connect to MySQL: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniConnect Microservices Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 24px;
        }
        .installer-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            max-width: 780px;
            width: 100%;
            overflow: hidden;
        }
        .header-banner {
            background: #0f4c81;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="installer-card">
    <div class="header-banner">
        <span class="badge bg-light text-primary rounded-pill px-3 py-1 mb-2 fw-semibold">
            <i class="fa-solid fa-cubes me-1"></i> Microservices Architecture (6 Independent DBs)
        </span>
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-database me-2"></i>UniConnect Microservices Setup</h2>
        <p class="mb-0 text-white-50">Database provisioning, status diagnostics &amp; monolith data migration</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if ($message): ?>
            <div class="alert alert-<?= $status ?> mb-4">
                <i class="fa-solid <?= $status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-2"></i>
                <?= $message ?>
                <?php if (!empty($migrationCounts)): ?>
                    <ul class="small mt-2 mb-0">
                        <?php foreach ($migrationCounts as $tbl => $cnt): ?>
                            <li><code><?= htmlspecialchars($tbl) ?></code>: <strong><?= (int)$cnt ?></strong> records migrated</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($status === 'success'): ?>
                    <div class="mt-3">
                        <a href="../frontend/index.php" class="btn btn-primary btn-sm me-2"><i class="fa-solid fa-house me-1"></i> Open Microservices Portal</a>
                        <a href="../frontend/auth/login.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-right-to-bracket me-1"></i> Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-server text-primary me-2"></i>Service Databases Status</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Microservice</th>
                        <th>Owned Database</th>
                        <th class="text-center">Tables</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbStatuses as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($row['label']) ?></td>
                            <td><code><?= htmlspecialchars($row['db']) ?></code></td>
                            <td class="text-center"><?= $row['tables'] ?></td>
                            <td class="text-center">
                                <?php if ($row['ready']): ?>
                                    <span class="badge bg-success-subtle text-success">Ready</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Not Initialized</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-3">
            <?php if ($monolithExists): ?>
                <div class="col-md-6">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="migrate_monolith">
                        <button type="submit" class="btn btn-success w-100 py-3 fw-semibold">
                            <i class="fa-solid fa-arrow-right-arrow-left me-2"></i> Migrate Existing Data<br>
                            <small class="fw-normal opacity-75">From <code>uniconnect_db</code> into 6 DBs</small>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="<?= $monolithExists ? 'col-md-6' : 'col-12' ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="install_seed">
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold">
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Install Fresh Demo Data<br>
                        <small class="fw-normal opacity-75">Create &amp; seed all 6 microservice DBs</small>
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center small text-muted">
            <a href="../frontend/index.php" class="text-decoration-none"><i class="fa-solid fa-globe me-1"></i> Go to Microservices Frontend</a>
            <a href="../../monolithics/index.php" class="text-decoration-none text-secondary"><i class="fa-solid fa-cube me-1"></i> View Original Monolith</a>
        </div>
    </div>
</div>
</body>
</html>
