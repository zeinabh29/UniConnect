<?php
/**
 * UniConnect Microservices - Student Service Business Logic
 */

class StudentService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getByCode($studentCode) {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
        $stmt->execute([trim((string)$studentCode)]);
        return $stmt->fetch() ?: null;
    }

    public function getByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        return $stmt->fetch() ?: null;
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return $stmt->fetch() ?: null;
    }

    public function codeExists($studentCode) {
        $stmt = $this->pdo->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $stmt->execute([trim((string)$studentCode)]);
        return $stmt->fetchColumn() !== false;
    }

    public function generateNextStudentId() {
        $year = date('Y');
        $prefix = "STD" . $year;
        $stmt = $this->pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastId = $stmt->fetchColumn();

        if ($lastId) {
            $numPart = (int)substr((string)$lastId, strlen($prefix));
            $nextNum = str_pad((string)($numPart + 1), 3, '0', STR_PAD_LEFT);
        } else {
            $nextNum = '001';
        }
        return $prefix . $nextNum;
    }

    /**
     * Fetches users map keyed by user_id from auth-service via HTTP.
     */
    private function fetchUsersMap(array $userIds) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }
        $res = HttpClient::internal('GET', SERVICE_AUTH . '/internal/users/batch', ['ids' => implode(',', $userIds)]);
        if ($res['status'] === 200 && !empty($res['body']['data']) && is_array($res['body']['data'])) {
            $map = [];
            foreach ($res['body']['data'] as $u) {
                $map[(int)$u['id']] = $u;
            }
            return $map;
        }
        return [];
    }

    /**
     * Enriches a list of student rows with user identity fields from auth-service.
     */
    public function enrichWithUsers(array $students) {
        if (empty($students)) {
            return [];
        }
        $userIds = array_column($students, 'user_id');
        $usersMap = $this->fetchUsersMap($userIds);

        foreach ($students as &$s) {
            $uid = (int)$s['user_id'];
            $u = $usersMap[$uid] ?? null;
            $s['name']            = $u['name'] ?? 'Unknown Student';
            $s['email']           = $u['email'] ?? '';
            $s['user_status']     = $u['status'] ?? 'active';
            $s['account_created'] = $u['created_at'] ?? $s['created_at'];
            $s['member_since']    = $u['created_at'] ?? $s['created_at'];
        }
        unset($s);
        return $students;
    }

    public function getEnrichedById($id) {
        $s = $this->getById($id);
        if (!$s) {
            return null;
        }
        $enriched = $this->enrichWithUsers([$s]);
        return $enriched[0];
    }

    public function getBatchEnriched(array $studentIds) {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if (empty($studentIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id IN ({$placeholders})");
        $stmt->execute($studentIds);
        $rows = $stmt->fetchAll();
        return $this->enrichWithUsers($rows);
    }

    public function listStudents($search = '', $department = '', $year = '') {
        $sql = "SELECT * FROM students WHERE 1=1";
        $params = [];

        if ($department !== '') {
            $sql .= " AND department = ?";
            $params[] = $department;
        }
        if ($year !== '') {
            $sql .= " AND academic_year = ?";
            $params[] = $year;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll();

        $students = $this->enrichWithUsers($students);

        // Apply cross-attribute search across student_id + enriched name & email
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $students = array_values(array_filter($students, function ($s) use ($needle) {
                return mb_strpos(mb_strtolower($s['name'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($s['email'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($s['student_id'] ?? ''), $needle) !== false;
            }));
        }

        // Attach active course counts via course-service HTTP call
        $countsRes = HttpClient::internal('GET', SERVICE_COURSE . '/internal/enrollment-counts-by-student');
        $countsMap = ($countsRes['status'] === 200 && is_array($countsRes['body']['data'] ?? null))
            ? $countsRes['body']['data']
            : [];

        foreach ($students as &$s) {
            $sidKey = (string)$s['id'];
            $s['active_courses'] = (int)($countsMap[$sidKey] ?? $countsMap[(int)$s['id']] ?? 0);
        }
        unset($s);

        $departments = $this->pdo->query("SELECT DISTINCT department FROM students ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

        return [
            'students'    => $students,
            'departments' => $departments,
        ];
    }

    public function createStudent(array $data) {
        $userId       = (int)($data['user_id'] ?? 0);
        $studentCode  = trim((string)($data['student_id'] ?? ''));
        $department   = trim((string)($data['department'] ?? 'Computer Science'));
        $academicYear = trim((string)($data['academic_year'] ?? 'Year 1'));
        $semester     = trim((string)($data['semester'] ?? DEFAULT_SEMESTER));
        $phone        = trim((string)($data['phone'] ?? '')) ?: null;
        $dob          = trim((string)($data['date_of_birth'] ?? '')) ?: null;
        $address      = trim((string)($data['address'] ?? '')) ?: null;

        if ($semester === '') {
            $semester = DEFAULT_SEMESTER;
        }
        if ($userId <= 0 || $studentCode === '') {
            throw new InvalidArgumentException('user_id and student_id are required.', 400);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $studentCode)) {
            throw new InvalidArgumentException('Student ID must be 3 to 50 characters and contain only letters, numbers, hyphens, or underscores.', 400);
        }
        if ($this->codeExists($studentCode)) {
            throw new RuntimeException("Student ID '{$studentCode}' is already registered.", 409);
        }
        if ($this->getByUserId($userId)) {
            throw new RuntimeException("Student profile already exists for this user.", 409);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO students (user_id, student_id, department, academic_year, semester, phone, date_of_birth, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $studentCode,
            $department ?: 'Computer Science',
            $academicYear ?: 'Year 1',
            $semester,
            $phone,
            $dob,
            $address,
        ]);

        return $this->getById((int)$this->pdo->lastInsertId());
    }

    public function updateStudent($id, array $data, $selfServiceOnly = false) {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException('Student not found.', 404);
        }

        $phone   = array_key_exists('phone', $data) ? (trim((string)$data['phone']) ?: null) : $existing['phone'];
        $dob     = array_key_exists('date_of_birth', $data) ? (trim((string)$data['date_of_birth']) ?: null) : $existing['date_of_birth'];
        $address = array_key_exists('address', $data) ? (trim((string)$data['address']) ?: null) : $existing['address'];

        if ($selfServiceOnly) {
            $stmt = $this->pdo->prepare("UPDATE students SET phone = ?, date_of_birth = ?, address = ? WHERE id = ?");
            $stmt->execute([$phone, $dob, $address, (int)$id]);
            return $this->getEnrichedById($id);
        }

        $department   = trim((string)($data['department'] ?? $existing['department']));
        $academicYear = trim((string)($data['academic_year'] ?? $existing['academic_year']));
        $semester     = trim((string)($data['semester'] ?? $existing['semester']));

        $stmt = $this->pdo->prepare("
            UPDATE students
            SET department = ?, academic_year = ?, semester = ?, phone = ?, date_of_birth = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([$department, $academicYear, $semester, $phone, $dob, $address, (int)$id]);

        return $this->getEnrichedById($id);
    }

    public function deleteStudent($id) {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException('Student not found.', 404);
        }
        $stmt = $this->pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $existing;
    }

    public function getMetrics() {
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $recentRaw = $this->pdo->query("SELECT * FROM students ORDER BY id DESC LIMIT 5")->fetchAll();
        $recent = $this->enrichWithUsers($recentRaw);

        $deptDist = $this->pdo->query("
            SELECT department, COUNT(*) AS count
            FROM students
            GROUP BY department
            ORDER BY count DESC
        ")->fetchAll();

        return [
            'total_students'          => $total,
            'recent_students'         => $recent,
            'department_distribution' => $deptDist,
        ];
    }

    /**
     * Builds full Student Dashboard payload by orchestrating course-service and request-service.
     */
    public function getStudentDashboard($userId) {
        $rawStudent = $this->getByUserId($userId);
        if (!$rawStudent) {
            throw new RuntimeException('Student profile not found.', 404);
        }
        $student = $this->enrichWithUsers([$rawStudent])[0];
        $studentPk = (int)$student['id'];

        $gpaRes = HttpClient::internal('GET', SERVICE_COURSE . "/internal/students/{$studentPk}/gpa");
        $gpaData = ($gpaRes['status'] === 200 && !empty($gpaRes['body']['data']))
            ? $gpaRes['body']['data']
            : ['gpa' => '0.00', 'total_credits' => 0, 'courses_graded' => 0];

        $enrollRes = HttpClient::internal('GET', SERVICE_COURSE . "/internal/students/{$studentPk}/enrollments", ['status' => 'enrolled']);
        $enrolledCourses = ($enrollRes['status'] === 200 && is_array($enrollRes['body']['data'] ?? null))
            ? $enrollRes['body']['data']
            : [];

        $certRes = HttpClient::internal('GET', SERVICE_REQUEST . "/internal/students/{$studentPk}/certificates", ['limit' => 5]);
        $certData = ($certRes['status'] === 200 && is_array($certRes['body']['data'] ?? null))
            ? $certRes['body']['data']
            : ['requests' => [], 'pending_count' => 0];

        return [
            'student'            => $student,
            'gpa_data'           => $gpaData,
            'enrolled_courses'   => $enrolledCourses,
            'recent_certs'       => $certData['requests'] ?? [],
            'pending_certs_count'=> (int)($certData['pending_count'] ?? 0),
        ];
    }
}
