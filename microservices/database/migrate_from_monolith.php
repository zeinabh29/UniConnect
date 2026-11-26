<?php
/**
 * UniConnect Microservices Architecture
 * Data Migration Utility: Monolithic DB (uniconnect_db) -> 6 Service Databases
 *
 * Strictly READ-ONLY against uniconnect_db; preserves all existing IDs, users,
 * students, courses, enrollments, grades, certificates, notifications, and messages.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/Database.php';

function run_monolith_to_microservices_migration($resetTargetTables = true) {
    $pdoServer = Database::connectServer();
    $report = [
        'source_found' => false,
        'counts'       => [],
        'errors'       => [],
    ];

    // Verify source monolithic database exists
    $dbCheck = $pdoServer->query("SHOW DATABASES LIKE '" . DB_MONOLITH . "'");
    if ($dbCheck->rowCount() === 0) {
        $report['errors'][] = "Source database '" . DB_MONOLITH . "' not found.";
        return $report;
    }
    $report['source_found'] = true;

    // Ensure target schemas exist
    $sqlFiles = ['auth_db.sql', 'student_db.sql', 'course_db.sql', 'request_db.sql', 'notification_db.sql', 'admin_db.sql'];
    foreach ($sqlFiles as $file) {
        $path = __DIR__ . '/' . $file;
        if (!file_exists($path)) {
            throw new RuntimeException("Missing schema file: {$file}");
        }
        $pdoServer->exec(file_get_contents($path));
    }

    $srcDb    = Database::connect(DB_MONOLITH);
    $authDb   = Database::connect(DB_AUTH);
    $studDb   = Database::connect(DB_STUDENT);
    $courseDb = Database::connect(DB_COURSE);
    $reqDb    = Database::connect(DB_REQUEST);
    $notifDb  = Database::connect(DB_NOTIFICATION);
    $adminDb  = Database::connect(DB_ADMIN);

    if ($resetTargetTables) {
        $authDb->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE auth_tokens; TRUNCATE TABLE users; SET FOREIGN_KEY_CHECKS = 1;");
        $studDb->exec("TRUNCATE TABLE students;");
        $courseDb->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE grades; TRUNCATE TABLE enrollments; TRUNCATE TABLE courses; SET FOREIGN_KEY_CHECKS = 1;");
        $reqDb->exec("TRUNCATE TABLE certificate_requests; TRUNCATE TABLE contact_messages;");
        $notifDb->exec("TRUNCATE TABLE notifications;");
        $adminDb->exec("TRUNCATE TABLE admin_audit_logs;");
    }

    // 1. Migrate users -> uniconnect_auth_db.users
    $users = $srcDb->query("SELECT id, name, email, password, role, status, avatar, created_at, updated_at FROM users")->fetchAll();
    $stmt = $authDb->prepare("
        INSERT INTO users (id, name, email, password, role, status, avatar, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($users as $u) {
        $stmt->execute([$u['id'], $u['name'], $u['email'], $u['password'], $u['role'], $u['status'], $u['avatar'], $u['created_at'], $u['updated_at']]);
    }
    $report['counts']['users'] = count($users);

    // 2. Migrate students -> uniconnect_student_db.students
    $students = $srcDb->query("SELECT id, user_id, student_id, department, academic_year, semester, phone, date_of_birth, address, created_at, updated_at FROM students")->fetchAll();
    $stmt = $studDb->prepare("
        INSERT INTO students (id, user_id, student_id, department, academic_year, semester, phone, date_of_birth, address, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($students as $s) {
        $stmt->execute([$s['id'], $s['user_id'], $s['student_id'], $s['department'], $s['academic_year'], $s['semester'], $s['phone'], $s['date_of_birth'], $s['address'], $s['created_at'], $s['updated_at']]);
    }
    $report['counts']['students'] = count($students);

    // 3. Migrate courses -> uniconnect_course_db.courses
    $courses = $srcDb->query("SELECT id, course_code, course_name, department, credits, semester, instructor_name, description, max_capacity, status, created_at FROM courses")->fetchAll();
    $stmt = $courseDb->prepare("
        INSERT INTO courses (id, course_code, course_name, department, credits, semester, instructor_name, description, max_capacity, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($courses as $c) {
        $stmt->execute([$c['id'], $c['course_code'], $c['course_name'], $c['department'], $c['credits'], $c['semester'], $c['instructor_name'], $c['description'], $c['max_capacity'], $c['status'], $c['created_at']]);
    }
    $report['counts']['courses'] = count($courses);

    // 4. Migrate enrollments -> uniconnect_course_db.enrollments
    $enrollments = $srcDb->query("SELECT id, student_id, course_id, enrollment_date, academic_year, semester, status, created_at FROM enrollments")->fetchAll();
    $stmt = $courseDb->prepare("
        INSERT INTO enrollments (id, student_id, course_id, enrollment_date, academic_year, semester, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($enrollments as $e) {
        $stmt->execute([$e['id'], $e['student_id'], $e['course_id'], $e['enrollment_date'], $e['academic_year'], $e['semester'], $e['status'], $e['created_at']]);
    }
    $report['counts']['enrollments'] = count($enrollments);

    // 5. Migrate grades -> uniconnect_course_db.grades
    $grades = $srcDb->query("SELECT id, enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point, remarks, graded_by, graded_at FROM grades")->fetchAll();
    $stmt = $courseDb->prepare("
        INSERT INTO grades (id, enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point, remarks, graded_by, graded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($grades as $g) {
        $stmt->execute([$g['id'], $g['enrollment_id'], $g['assignment_score'], $g['midterm_score'], $g['final_score'], $g['total_score'], $g['letter_grade'], $g['grade_point'], $g['remarks'], $g['graded_by'], $g['graded_at']]);
    }
    $report['counts']['grades'] = count($grades);

    // 6. Migrate certificate_requests -> uniconnect_request_db.certificate_requests
    $certs = $srcDb->query("SELECT id, student_id, certificate_type, purpose, delivery_type, status, admin_remarks, reference_no, processed_by, requested_at, processed_at FROM certificate_requests")->fetchAll();
    $stmt = $reqDb->prepare("
        INSERT INTO certificate_requests (id, student_id, certificate_type, purpose, delivery_type, status, admin_remarks, reference_no, processed_by, requested_at, processed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($certs as $cert) {
        $stmt->execute([$cert['id'], $cert['student_id'], $cert['certificate_type'], $cert['purpose'], $cert['delivery_type'], $cert['status'], $cert['admin_remarks'], $cert['reference_no'], $cert['processed_by'], $cert['requested_at'], $cert['processed_at']]);
    }
    $report['counts']['certificate_requests'] = count($certs);

    // 7. Migrate contact_messages -> uniconnect_request_db.contact_messages
    $msgs = $srcDb->query("SELECT id, name, email, subject, message, is_read, admin_reply, created_at FROM contact_messages")->fetchAll();
    $stmt = $reqDb->prepare("
        INSERT INTO contact_messages (id, name, email, subject, message, is_read, admin_reply, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($msgs as $m) {
        $stmt->execute([$m['id'], $m['name'], $m['email'], $m['subject'], $m['message'], $m['is_read'], $m['admin_reply'], $m['created_at']]);
    }
    $report['counts']['contact_messages'] = count($msgs);

    // 8. Migrate notifications -> uniconnect_notification_db.notifications
    $notifs = $srcDb->query("SELECT id, user_id, title, message, type, link, is_read, created_at FROM notifications")->fetchAll();
    $stmt = $notifDb->prepare("
        INSERT INTO notifications (id, user_id, title, message, type, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($notifs as $n) {
        $stmt->execute([$n['id'], $n['user_id'], $n['title'], $n['message'], $n['type'], $n['link'], $n['is_read'], $n['created_at']]);
    }
    $report['counts']['notifications'] = count($notifs);

    // 9. Record migration audit entry in uniconnect_admin_db.admin_audit_logs
    $stmt = $adminDb->prepare("
        INSERT INTO admin_audit_logs (admin_user_id, admin_name, action, target_type, target_id, details)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        1,
        'Migration Tool',
        'MIGRATE_FROM_MONOLITH',
        'databases',
        'uniconnect_db',
        'Migrated data cleanly from monolithic uniconnect_db: ' . json_encode($report['counts']),
    ]);
    $report['counts']['admin_audit_logs'] = 1;

    return $report;
}

// Allow direct execution via PHP CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        echo "Starting UniConnect Monolith -> Microservices Data Migration...\n";
        $result = run_monolith_to_microservices_migration(true);
        if (!$result['source_found']) {
            fwrite(STDERR, "Migration aborted: " . implode('; ', $result['errors']) . "\n");
            exit(1);
        }
        echo "Migration completed successfully!\n";
        foreach ($result['counts'] as $table => $cnt) {
            echo "  - {$table}: {$cnt} rows migrated\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}
