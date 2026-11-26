<?php
/**
 * UniConnect Microservices - Authentication Service Business Logic
 */

class AuthService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function issueTokenForUser(array $user, $student = null) {
        $payload = [
            'sub'          => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'student_pk'   => $student ? (int)$student['id'] : null,
            'student_code' => $student ? $student['student_id'] : null,
            'department'   => $student ? $student['department'] : null,
            'jti'          => bin2hex(random_bytes(16)),
            'iat'          => time(),
            'exp'          => time() + JWT_TTL_SECONDS,
        ];

        $token = JwtHelper::encode($payload, JWT_TTL_SECONDS);

        $stmt = $this->pdo->prepare("
            INSERT INTO auth_tokens (user_id, token_jti, expires_at, is_revoked)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([
            (int)$user['id'],
            $payload['jti'],
            date('Y-m-d H:i:s', $payload['exp']),
        ]);

        return $token;
    }

    public function login($identifier, $password) {
        $identifier = trim((string)$identifier);
        $password   = (string)$password;

        if ($identifier === '' || $password === '') {
            throw new InvalidArgumentException('Please enter both your Student ID / email and password.', 400);
        }

        // 1. Try lookup by email in auth_db
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        $student = null;

        // 2. If not found by email, query student-service by Student ID code
        if (!$user) {
            $studRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/by-student-code/' . rawurlencode($identifier));
            if ($studRes['status'] === 200 && !empty($studRes['body']['data'])) {
                $student = $studRes['body']['data'];
                $stmtById = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmtById->execute([(int)$student['user_id']]);
                $user = $stmtById->fetch();
            }
        }

        if (!$user || !password_verify($password, $user['password'])) {
            throw new RuntimeException('Invalid Student ID / email or password.', 401);
        }

        if ($user['status'] !== 'active') {
            throw new RuntimeException('Your account has been deactivated. Please contact the administrator.', 403);
        }

        // Resolve student profile via student-service if student role and not loaded yet
        if ($user['role'] === 'student' && !$student) {
            $studRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/by-user/' . (int)$user['id']);
            if ($studRes['status'] === 200 && !empty($studRes['body']['data'])) {
                $student = $studRes['body']['data'];
            }
        }

        $token = $this->issueTokenForUser($user, $student);

        return [
            'token'   => $token,
            'user'    => [
                'id'           => (int)$user['id'],
                'name'         => $user['name'],
                'email'        => $user['email'],
                'role'         => $user['role'],
                'status'       => $user['status'],
                'student_pk'   => $student ? (int)$student['id'] : null,
                'student_id'   => $student ? $student['student_id'] : null,
                'department'   => $student ? $student['department'] : null,
            ],
            'student' => $student,
        ];
    }

    public function registerStudent(array $data) {
        $name         = trim((string)($data['name'] ?? ''));
        $email        = trim((string)($data['email'] ?? ''));
        $studentCode  = trim((string)($data['student_id'] ?? ''));
        $department   = trim((string)($data['department'] ?? 'Computer Science'));
        $academicYear = trim((string)($data['academic_year'] ?? 'Year 1'));
        $semester     = trim((string)($data['semester'] ?? DEFAULT_SEMESTER));
        $phone        = trim((string)($data['phone'] ?? ''));
        $dob          = trim((string)($data['date_of_birth'] ?? ''));
        $address      = trim((string)($data['address'] ?? ''));
        $password     = (string)($data['password'] ?? '');
        $confirmPass  = (string)($data['confirm_password'] ?? $password);

        if ($semester === '') {
            $semester = DEFAULT_SEMESTER;
        }

        if ($name === '' || $email === '' || $studentCode === '' || $password === '' || $confirmPass === '') {
            throw new InvalidArgumentException('Please fill in all required fields (Name, Email, Student ID, Password).', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.', 400);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $studentCode)) {
            throw new InvalidArgumentException('Student ID must be 3 to 50 characters and contain only letters, numbers, hyphens, or underscores.', 400);
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters long.', 400);
        }
        if ($password !== $confirmPass) {
            throw new InvalidArgumentException('Passwords do not match.', 400);
        }

        // Check email uniqueness locally
        $stmtEmail = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->rowCount() > 0) {
            throw new RuntimeException('This email address is already registered. Please sign in or use a different email.', 409);
        }

        // Check student_id uniqueness remotely via student-service
        $checkIdRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/check-code/' . rawurlencode($studentCode));
        if ($checkIdRes['status'] !== 200) {
            throw new RuntimeException('Student service unavailable while validating Student ID.', 503);
        }
        if (!empty($checkIdRes['body']['data']['exists'])) {
            throw new RuntimeException("Student ID '{$studentCode}' is already registered. Please enter a different Student ID or sign in.", 409);
        }

        // Step 1: Create auth user
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmtUser = $this->pdo->prepare("
            INSERT INTO users (name, email, password, role, status)
            VALUES (?, ?, ?, 'student', 'active')
        ");
        $stmtUser->execute([$name, $email, $hashed]);
        $userId = (int)$this->pdo->lastInsertId();

        // Step 2: Create student profile via HTTP call to student-service
        $studRes = HttpClient::internal('POST', SERVICE_STUDENT . '/internal/students', [
            'user_id'       => $userId,
            'student_id'    => $studentCode,
            'department'    => $department,
            'academic_year' => $academicYear,
            'semester'      => $semester,
            'phone'         => $phone ?: null,
            'date_of_birth' => $dob ?: null,
            'address'       => $address ?: null,
        ]);

        // Compensating transaction rollback if student-service fails
        if ($studRes['status'] !== 201 || empty($studRes['body']['data'])) {
            $delStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $delStmt->execute([$userId]);
            $errMsg = $studRes['body']['error'] ?? 'Failed to create student profile.';
            throw new RuntimeException($errMsg, $studRes['status'] >= 400 ? $studRes['status'] : 500);
        }

        $student = $studRes['body']['data'];

        // Step 3: Trigger welcome notification via notification-service (best-effort)
        HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
            'user_id' => $userId,
            'title'   => 'Welcome to UniConnect Portal',
            'message' => "Your student account has been created with Student ID: {$studentCode}. You can now enroll in courses and request documents.",
            'type'    => 'success',
            'link'    => 'student/courses.php',
        ]);

        $userRow = [
            'id'     => $userId,
            'name'   => $name,
            'email'  => $email,
            'role'   => 'student',
            'status' => 'active',
        ];
        $token = $this->issueTokenForUser($userRow, $student);

        return [
            'token'   => $token,
            'user'    => [
                'id'           => $userId,
                'name'         => $name,
                'email'        => $email,
                'role'         => 'student',
                'status'       => 'active',
                'student_pk'   => (int)$student['id'],
                'student_id'   => $studentCode,
                'department'   => $department,
            ],
            'student' => $student,
        ];
    }

    public function verifyToken($token) {
        $payload = JwtHelper::decode($token);
        if (!$payload || empty($payload['sub']) || empty($payload['jti'])) {
            throw new RuntimeException('Invalid or expired authentication token.', 401);
        }

        // Confirm token wasn't revoked in auth_tokens
        $stmtTok = $this->pdo->prepare("SELECT is_revoked, expires_at FROM auth_tokens WHERE token_jti = ? LIMIT 1");
        $stmtTok->execute([$payload['jti']]);
        $tokRow = $stmtTok->fetch();
        if ($tokRow && ((int)$tokRow['is_revoked'] === 1 || strtotime($tokRow['expires_at']) < time())) {
            throw new RuntimeException('Token has been revoked or expired.', 401);
        }

        // Confirm user still exists and is active
        $stmtUser = $this->pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([(int)$payload['sub']]);
        $user = $stmtUser->fetch();
        if (!$user || $user['status'] !== 'active') {
            throw new RuntimeException('User account inactive or not found.', 401);
        }

        return [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'status'       => $user['status'],
            'student_pk'   => $payload['student_pk'] ?? null,
            'student_id'   => $payload['student_code'] ?? null,
            'department'   => $payload['department'] ?? null,
            'jti'          => $payload['jti'],
        ];
    }

    public function logout($token) {
        $payload = JwtHelper::decode($token);
        if ($payload && !empty($payload['jti'])) {
            $stmt = $this->pdo->prepare("UPDATE auth_tokens SET is_revoked = 1 WHERE token_jti = ?");
            $stmt->execute([$payload['jti']]);
        }
        return true;
    }

    public function changePassword($userId, $currentPass, $newPass, $confirmPass) {
        if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
            throw new InvalidArgumentException('Please fill in all password fields.', 400);
        }
        if (strlen($newPass) < 6) {
            throw new InvalidArgumentException('New password must be at least 6 characters.', 400);
        }
        if ($newPass !== $confirmPass) {
            throw new InvalidArgumentException('New password confirmation does not match.', 400);
        }

        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        $existingHash = $stmt->fetchColumn();

        if (!$existingHash || !password_verify($currentPass, $existingHash)) {
            throw new RuntimeException('Incorrect current password.', 400);
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$newHash, (int)$userId]);

        HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
            'user_id' => (int)$userId,
            'title'   => 'Password Changed',
            'message' => 'Your account password was updated successfully.',
            'type'    => 'info',
            'link'    => 'student/profile.php',
        ]);

        return true;
    }

    // ---------------------------------------------------------
    // Internal User Management Methods (for Admin Service & Enrichment)
    // ---------------------------------------------------------
    public function getUsersBatch(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, role, status, avatar, created_at
            FROM users WHERE id IN ({$placeholders})
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT id, name, email, role, status, avatar, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAdminIds() {
        return $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function createUserInternal(array $data) {
        $name     = trim((string)($data['name'] ?? ''));
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role     = trim((string)($data['role'] ?? 'student'));
        $status   = trim((string)($data['status'] ?? 'active'));

        if ($name === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Name, Email, and Password are required fields.', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.', 400);
        }

        $chk = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->rowCount() > 0) {
            throw new RuntimeException('A user with this email address already exists.', 409);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed, $role, $status]);

        return $this->getUserById((int)$this->pdo->lastInsertId());
    }

    public function updateUserInternal($id, array $data) {
        $existing = $this->getUserById($id);
        if (!$existing) {
            throw new RuntimeException('User not found.', 404);
        }

        $name        = trim((string)($data['name'] ?? $existing['name']));
        $email       = trim((string)($data['email'] ?? $existing['email']));
        $status      = trim((string)($data['status'] ?? $existing['status']));
        $newPassword = (string)($data['new_password'] ?? '');

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('Name and Email are required.', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.', 400);
        }

        $chk = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $chk->execute([$email, (int)$id]);
        if ($chk->rowCount() > 0) {
            throw new RuntimeException('Email is already used by another account.', 409);
        }

        if ($newPassword !== '') {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hashed, $status, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET name = ?, email = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $email, $status, (int)$id]);
        }

        return $this->getUserById($id);
    }

    public function deleteUserInternal($id) {
        $existing = $this->getUserById($id);
        if (!$existing) {
            throw new RuntimeException('User not found.', 404);
        }
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $existing;
    }
}
