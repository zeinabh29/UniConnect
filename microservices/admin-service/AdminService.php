<?php
/**
 * UniConnect Microservices - Admin Service Business Logic
 * Orchestrates executive dashboard metrics, student/user lifecycle & audit logging
 */

class AdminService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function logAction($adminUserId, $adminName, $action, $targetType, $targetId = null, $details = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_audit_logs (admin_user_id, admin_name, action, target_type, target_id, details)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([(int)$adminUserId, (string)$adminName, $action, $targetType, $targetId !== null ? (string)$targetId : null, $details]);
        } catch (Throwable $e) {
            error_log('Failed to write admin audit log: ' . $e->getMessage());
        }
    }

    public function getAuditLogs($limit = 20) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDashboard() {
        $studRes   = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/metrics');
        $courseRes = HttpClient::internal('GET', SERVICE_COURSE . '/internal/metrics');
        $reqRes    = HttpClient::internal('GET', SERVICE_REQUEST . '/internal/metrics');

        $studData   = ($studRes['status'] === 200 && !empty($studRes['body']['data'])) ? $studRes['body']['data'] : [];
        $courseData = ($courseRes['status'] === 200 && !empty($courseRes['body']['data'])) ? $courseRes['body']['data'] : [];
        $reqData    = ($reqRes['status'] === 200 && !empty($reqRes['body']['data'])) ? $reqRes['body']['data'] : [];

        return [
            'metrics' => [
                'total_students'    => (int)($studData['total_students'] ?? 0),
                'total_courses'     => (int)($courseData['total_courses'] ?? 0),
                'total_enrollments' => (int)($courseData['total_enrollments'] ?? 0),
                'pending_certs'     => (int)($reqData['pending_certs'] ?? 0),
                'unread_messages'   => (int)($reqData['unread_messages'] ?? 0),
            ],
            'recent_students'         => $studData['recent_students'] ?? [],
            'pending_cert_list'       => $reqData['pending_cert_list'] ?? [],
            'department_distribution' => $studData['department_distribution'] ?? [],
            'recent_audit_logs'       => $this->getAuditLogs(5),
        ];
    }

    public function createStudent(array $data, array $adminCtx) {
        $name         = trim((string)($data['name'] ?? ''));
        $email        = trim((string)($data['email'] ?? ''));
        $password     = (string)($data['password'] ?? '');
        $studentCode  = trim((string)($data['student_id'] ?? ''));
        $department   = trim((string)($data['department'] ?? 'Computer Science'));
        $academicYear = trim((string)($data['academic_year'] ?? 'Year 1'));
        $semester     = trim((string)($data['semester'] ?? DEFAULT_SEMESTER));
        $phone        = trim((string)($data['phone'] ?? ''));
        $dob          = trim((string)($data['date_of_birth'] ?? ''));
        $address      = trim((string)($data['address'] ?? ''));

        if ($name === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Name, Email, and Password are required fields.', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.', 400);
        }

        if ($studentCode === '') {
            $nextRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/next-id');
            $studentCode = $nextRes['body']['data']['next_student_id'] ?? ('STD' . date('Y') . '001');
        }

        // Pre-validate student_id uniqueness before creating auth user
        $chkRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/check-code/' . rawurlencode($studentCode));
        if ($chkRes['status'] === 200 && !empty($chkRes['body']['data']['exists'])) {
            throw new RuntimeException("Student ID '{$studentCode}' is already assigned to another student.", 409);
        }

        // Step 1: Create auth user in auth-service
        $userRes = HttpClient::internal('POST', SERVICE_AUTH . '/internal/users', [
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role'     => 'student',
            'status'   => 'active',
        ]);

        if ($userRes['status'] !== 201 || empty($userRes['body']['data']['id'])) {
            $errMsg = $userRes['body']['error'] ?? 'Failed to create user credentials.';
            throw new RuntimeException($errMsg, $userRes['status'] >= 400 ? $userRes['status'] : 500);
        }
        $userId = (int)$userRes['body']['data']['id'];

        // Step 2: Create student record in student-service
        $studRes = HttpClient::internal('POST', SERVICE_STUDENT . '/internal/students', [
            'user_id'       => $userId,
            'student_id'    => $studentCode,
            'department'    => $department,
            'academic_year' => $academicYear,
            'semester'      => $semester ?: DEFAULT_SEMESTER,
            'phone'         => $phone ?: null,
            'date_of_birth' => $dob ?: null,
            'address'       => $address ?: null,
        ]);

        // Compensating rollback if student profile creation fails
        if ($studRes['status'] !== 201 || empty($studRes['body']['data'])) {
            HttpClient::internal('DELETE', SERVICE_AUTH . '/internal/users/' . $userId);
            $errMsg = $studRes['body']['error'] ?? 'Failed to create student record.';
            throw new RuntimeException($errMsg, $studRes['status'] >= 400 ? $studRes['status'] : 500);
        }

        $student = $studRes['body']['data'];

        // Step 3: Welcome notification
        HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
            'user_id' => $userId,
            'title'   => 'Account Created by Registrar',
            'message' => "Your student account has been created with ID {$studentCode}. You may now enroll in courses.",
            'type'    => 'success',
            'link'    => 'student/dashboard.php',
        ]);

        // Step 4: Audit Log
        $this->logAction(
            $adminCtx['id'],
            $adminCtx['name'] ?: 'Administrator',
            'CREATE_STUDENT',
            'student',
            $studentCode,
            "Created student '{$name}' ({$email})"
        );

        return array_merge($student, [
            'name'        => $name,
            'email'       => $email,
            'user_status' => 'active',
        ]);
    }

    public function updateStudent($studentPk, array $data, array $adminCtx) {
        $existingRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . (int)$studentPk);
        if ($existingRes['status'] !== 200 || empty($existingRes['body']['data'])) {
            throw new RuntimeException('Student not found.', 404);
        }
        $existing = $existingRes['body']['data'];
        $userId = (int)$existing['user_id'];

        $name       = trim((string)($data['name'] ?? $existing['name']));
        $email      = trim((string)($data['email'] ?? $existing['email']));
        $department = trim((string)($data['department'] ?? $existing['department']));

        if ($name === '' || $email === '' || $department === '') {
            throw new InvalidArgumentException('Name, Email, and Department are required.', 400);
        }

        // Step 1: Update user credentials/identity in auth-service
        $userRes = HttpClient::internal('PUT', SERVICE_AUTH . '/internal/users/' . $userId, [
            'name'         => $name,
            'email'        => $email,
            'status'       => $data['status'] ?? $existing['user_status'],
            'new_password' => $data['new_password'] ?? '',
        ]);
        if ($userRes['status'] !== 200) {
            $errMsg = $userRes['body']['error'] ?? 'Failed to update user account.';
            throw new RuntimeException($errMsg, $userRes['status'] >= 400 ? $userRes['status'] : 500);
        }

        // Step 2: Update academic profile in student-service
        $studRes = HttpClient::internal('PUT', SERVICE_STUDENT . '/internal/students/' . (int)$studentPk, [
            'department'    => $department,
            'academic_year' => $data['academic_year'] ?? $existing['academic_year'],
            'semester'      => $data['semester'] ?? $existing['semester'],
            'phone'         => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'address'       => $data['address'] ?? null,
        ]);
        if ($studRes['status'] !== 200) {
            $errMsg = $studRes['body']['error'] ?? 'Failed to update student profile.';
            throw new RuntimeException($errMsg, $studRes['status'] >= 400 ? $studRes['status'] : 500);
        }

        $this->logAction(
            $adminCtx['id'],
            $adminCtx['name'] ?: 'Administrator',
            'UPDATE_STUDENT',
            'student',
            $existing['student_id'],
            "Updated profile for '{$name}'"
        );

        return $studRes['body']['data'];
    }

    public function deleteStudent($studentPk, array $adminCtx) {
        $existingRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . (int)$studentPk);
        if ($existingRes['status'] !== 200 || empty($existingRes['body']['data'])) {
            throw new RuntimeException('Student not found.', 404);
        }
        $student = $existingRes['body']['data'];
        $userId = (int)$student['user_id'];

        // Distributed Cascade Cleanup across owning services
        HttpClient::internal('DELETE', SERVICE_COURSE . "/internal/students/{$studentPk}/enrollments");
        HttpClient::internal('DELETE', SERVICE_REQUEST . "/internal/students/{$studentPk}/certificates");
        HttpClient::internal('DELETE', SERVICE_NOTIFICATION . "/internal/users/{$userId}/notifications");
        HttpClient::internal('DELETE', SERVICE_STUDENT . "/internal/students/{$studentPk}");
        HttpClient::internal('DELETE', SERVICE_AUTH . "/internal/users/{$userId}");

        $this->logAction(
            $adminCtx['id'],
            $adminCtx['name'] ?: 'Administrator',
            'DELETE_STUDENT',
            'student',
            $student['student_id'],
            "Deleted student '{$student['name']}' and cascaded cleanup across course, request, notification, student, and auth services."
        );

        return $student;
    }
}
