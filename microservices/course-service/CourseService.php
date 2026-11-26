<?php
/**
 * UniConnect Microservices - Course, Enrollment & Grading Service Logic
 */

class CourseService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function resolveStudentPk(array $ctx) {
        if (!empty($ctx['student_pk'])) {
            return (int)$ctx['student_pk'];
        }
        $res = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/by-user/' . (int)$ctx['id']);
        if ($res['status'] === 200 && !empty($res['body']['data']['id'])) {
            return (int)$res['body']['data']['id'];
        }
        throw new RuntimeException('Student profile not found.', 404);
    }

    private function fetchStudentsMap(array $studentPks) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentPks))));
        if (empty($ids)) {
            return [];
        }
        $res = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/batch', ['ids' => implode(',', $ids)]);
        if ($res['status'] === 200 && is_array($res['body']['data'] ?? null)) {
            $map = [];
            foreach ($res['body']['data'] as $s) {
                $map[(int)$s['id']] = $s;
            }
            return $map;
        }
        return [];
    }

    // =========================================================
    // Courses CRUD
    // =========================================================
    public function listCourses($role, $studentPk = 0, $search = '', $department = '') {
        $sql = "
            SELECT c.*,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status != 'dropped') AS enrolled_students
        ";
        $params = [];

        if ($role === 'student' && $studentPk > 0) {
            $sql .= ", (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status != 'dropped') AS is_enrolled ";
            $params[] = (int)$studentPk;
        } else {
            // Admin counts only 'enrolled' for consistency with original admin/courses.php
            $sql = "
                SELECT c.*,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_students,
                       0 AS is_enrolled
            ";
        }

        $sql .= " FROM courses c WHERE 1=1 ";
        if ($role === 'student') {
            $sql .= " AND c.status = 'active' ";
        }

        if ($search !== '') {
            $sql .= " AND (c.course_code LIKE ? OR c.course_name LIKE ? OR c.instructor_name LIKE ?) ";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($department !== '') {
            $sql .= " AND c.department = ? ";
            $params[] = $department;
        }

        $sql .= " ORDER BY c.course_code ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll();

        $departments = $this->pdo->query("SELECT DISTINCT department FROM courses ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

        return [
            'courses'     => $courses,
            'departments' => $departments,
        ];
    }

    public function getCourse($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return $stmt->fetch() ?: null;
    }

    public function createCourse(array $data) {
        $courseCode  = strtoupper(trim((string)($data['course_code'] ?? '')));
        $courseName  = trim((string)($data['course_name'] ?? ''));
        $department  = trim((string)($data['department'] ?? 'Computer Science'));
        $credits     = (int)($data['credits'] ?? 3);
        $semester    = trim((string)($data['semester'] ?? DEFAULT_SEMESTER));
        $instructor  = trim((string)($data['instructor_name'] ?? ''));
        $maxCapacity = (int)($data['max_capacity'] ?? 40);
        $description = trim((string)($data['description'] ?? ''));
        $status      = trim((string)($data['status'] ?? 'active'));

        if ($semester === '') {
            $semester = DEFAULT_SEMESTER;
        }
        if ($courseCode === '' || $courseName === '' || $instructor === '') {
            throw new InvalidArgumentException('Course Code, Course Name, and Instructor are required fields.', 400);
        }
        if ($credits < 1 || $credits > 6) {
            throw new InvalidArgumentException('Credits must be between 1 and 6.', 400);
        }
        if ($maxCapacity < 1) {
            throw new InvalidArgumentException('Maximum capacity must be at least 1.', 400);
        }

        $chk = $this->pdo->prepare("SELECT id FROM courses WHERE course_code = ? LIMIT 1");
        $chk->execute([$courseCode]);
        if ($chk->rowCount() > 0) {
            throw new RuntimeException("A course with code '{$courseCode}' already exists.", 409);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO courses (course_code, course_name, department, credits, semester, instructor_name, description, max_capacity, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $courseCode,
            $courseName,
            $department,
            $credits,
            $semester,
            $instructor,
            $description ?: null,
            $maxCapacity,
            in_array($status, ['active', 'inactive'], true) ? $status : 'active',
        ]);

        return $this->getCourse((int)$this->pdo->lastInsertId());
    }

    public function updateCourse($id, array $data) {
        $existing = $this->getCourse($id);
        if (!$existing) {
            throw new RuntimeException('Course not found.', 404);
        }

        $courseCode  = strtoupper(trim((string)($data['course_code'] ?? $existing['course_code'])));
        $courseName  = trim((string)($data['course_name'] ?? $existing['course_name']));
        $department  = trim((string)($data['department'] ?? $existing['department']));
        $credits     = (int)($data['credits'] ?? $existing['credits']);
        $semester    = trim((string)($data['semester'] ?? $existing['semester']));
        $instructor  = trim((string)($data['instructor_name'] ?? $existing['instructor_name']));
        $maxCapacity = (int)($data['max_capacity'] ?? $existing['max_capacity']);
        $description = array_key_exists('description', $data) ? trim((string)$data['description']) : $existing['description'];
        $status      = trim((string)($data['status'] ?? $existing['status']));

        if ($courseCode === '' || $courseName === '' || $instructor === '') {
            throw new InvalidArgumentException('Course Code, Course Name, and Instructor are required.', 400);
        }
        if ($credits < 1 || $credits > 6) {
            throw new InvalidArgumentException('Credits must be between 1 and 6.', 400);
        }
        if ($maxCapacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1.', 400);
        }

        $chk = $this->pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ? LIMIT 1");
        $chk->execute([$courseCode, (int)$id]);
        if ($chk->rowCount() > 0) {
            throw new RuntimeException("Another course already uses code '{$courseCode}'.", 409);
        }

        $stmt = $this->pdo->prepare("
            UPDATE courses
            SET course_code = ?, course_name = ?, department = ?, credits = ?, semester = ?, instructor_name = ?, description = ?, max_capacity = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $courseCode,
            $courseName,
            $department,
            $credits,
            $semester,
            $instructor,
            $description ?: null,
            $maxCapacity,
            in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            (int)$id,
        ]);

        return $this->getCourse($id);
    }

    public function deleteCourse($id) {
        $existing = $this->getCourse($id);
        if (!$existing) {
            throw new RuntimeException('Course not found.', 404);
        }
        $stmt = $this->pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $existing;
    }

    // =========================================================
    // Enrollments & Drop
    // =========================================================
    public function enrollStudent($studentPk, $courseId, $targetUserId, $semesterOverride = null, $byAdmin = false) {
        if ($studentPk <= 0 || $courseId <= 0) {
            throw new InvalidArgumentException('Please select both a valid student and course.', 400);
        }

        $sqlCourse = $byAdmin
            ? "SELECT * FROM courses WHERE id = ? LIMIT 1"
            : "SELECT * FROM courses WHERE id = ? AND status = 'active' LIMIT 1";
        $stmtCourse = $this->pdo->prepare($sqlCourse);
        $stmtCourse->execute([(int)$courseId]);
        $course = $stmtCourse->fetch();

        if (!$course) {
            throw new RuntimeException($byAdmin ? 'Course not found.' : 'Selected course is unavailable or inactive.', 404);
        }

        $semester = ($semesterOverride !== null && trim((string)$semesterOverride) !== '')
            ? trim((string)$semesterOverride)
            : $course['semester'];

        // Prevent duplicate active enrollment in same semester (handles dropped re-enroll safely)
        $chkDup = $this->pdo->prepare("
            SELECT id, status FROM enrollments
            WHERE student_id = ? AND course_id = ? AND semester = ?
            LIMIT 1
        ");
        $chkDup->execute([(int)$studentPk, (int)$course['id'], $semester]);
        $existingRow = $chkDup->fetch();

        if ($existingRow && $existingRow['status'] !== 'dropped') {
            $msg = $byAdmin
                ? 'This student is already enrolled in this course for the selected term.'
                : "You are already enrolled in {$course['course_code']} ({$course['course_name']}) for {$semester}.";
            throw new RuntimeException($msg, 409);
        }

        // Capacity check
        $stmtCnt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status != 'dropped'");
        $stmtCnt->execute([(int)$course['id']]);
        $enrolledCnt = (int)$stmtCnt->fetchColumn();
        if ($enrolledCnt >= (int)$course['max_capacity']) {
            throw new RuntimeException("Sorry, {$course['course_code']} has reached maximum capacity ({$enrolledCnt}/{$course['max_capacity']}).", 409);
        }

        $this->pdo->beginTransaction();
        try {
            if ($existingRow && $existingRow['status'] === 'dropped') {
                // Re-activate previously dropped enrollment row cleanly respecting UNIQUE constraint
                $upd = $this->pdo->prepare("
                    UPDATE enrollments
                    SET enrollment_date = CURDATE(), academic_year = ?, status = 'enrolled'
                    WHERE id = ?
                ");
                $upd->execute([DEFAULT_ACADEMIC_YEAR, (int)$existingRow['id']]);
                $enrollmentId = (int)$existingRow['id'];
            } else {
                $ins = $this->pdo->prepare("
                    INSERT INTO enrollments (student_id, course_id, enrollment_date, academic_year, semester, status)
                    VALUES (?, ?, CURDATE(), ?, ?, 'enrolled')
                ");
                $ins->execute([(int)$studentPk, (int)$course['id'], DEFAULT_ACADEMIC_YEAR, $semester]);
                $enrollmentId = (int)$this->pdo->lastInsertId();

                // Initialize placeholder grade row
                $insGrade = $this->pdo->prepare("
                    INSERT INTO grades (enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point)
                    VALUES (?, 0.00, 0.00, 0.00, 0.00, 'F', 0.00)
                ");
                $insGrade->execute([$enrollmentId]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($targetUserId > 0) {
            if ($byAdmin) {
                HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                    'user_id' => (int)$targetUserId,
                    'title'   => 'Enrolled in Course by Administrator',
                    'message' => "Administrator has enrolled you in {$course['course_code']} - {$course['course_name']}.",
                    'type'    => 'info',
                    'link'    => 'student/my-courses.php',
                ]);
            } else {
                HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                    'user_id' => (int)$targetUserId,
                    'title'   => 'Course Enrollment Confirmed',
                    'message' => "You have successfully enrolled in {$course['course_code']}: {$course['course_name']} ({$course['credits']} credits).",
                    'type'    => 'success',
                    'link'    => 'student/my-courses.php',
                ]);
            }
        }

        return [
            'enrollment_id' => $enrollmentId,
            'course'        => $course,
        ];
    }

    public function getStudentEnrollments($studentPk, $status = 'enrolled') {
        $sql = "
            SELECT e.id AS enrollment_id, e.id, e.enrollment_date, e.semester, e.academic_year, e.status AS enrollment_status,
                   c.id AS course_id, c.course_code, c.course_name, c.department, c.credits, c.instructor_name, c.description,
                   g.assignment_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade, g.grade_point
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE e.student_id = ?
        ";
        $params = [(int)$studentPk];
        if ($status !== '' && $status !== 'all') {
            $sql .= " AND e.status = ? ";
            $params[] = $status;
        }
        $sql .= " ORDER BY c.course_code ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function dropEnrollment($enrollmentId, $studentPk, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT e.id, c.course_code, c.course_name
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.id = ? AND e.student_id = ? AND e.status = 'enrolled'
            LIMIT 1
        ");
        $stmt->execute([(int)$enrollmentId, (int)$studentPk]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Enrollment record not found or already dropped.', 404);
        }

        $upd = $this->pdo->prepare("UPDATE enrollments SET status = 'dropped' WHERE id = ?");
        $upd->execute([(int)$enrollmentId]);

        HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
            'user_id' => (int)$userId,
            'title'   => 'Course Dropped',
            'message' => "You have dropped {$row['course_code']} - {$row['course_name']}.",
            'type'    => 'warning',
            'link'    => 'student/my-courses.php',
        ]);

        return $row;
    }

    public function listAllEnrollmentsAdmin($courseId = 0, $search = '') {
        $sql = "
            SELECT e.*, c.course_code, c.course_name, c.credits, c.instructor_name,
                   g.letter_grade, g.total_score
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE 1=1
        ";
        $params = [];
        if ($courseId > 0) {
            $sql .= " AND e.course_id = ? ";
            $params[] = (int)$courseId;
        }
        $sql .= " ORDER BY e.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $studentMap = $this->fetchStudentsMap(array_column($rows, 'student_id'));
        foreach ($rows as &$r) {
            $st = $studentMap[(int)$r['student_id']] ?? null;
            $r['student_code']  = $st['student_id'] ?? ('ID-' . $r['student_id']);
            $r['student_name']  = $st['name'] ?? 'Unknown Student';
            $r['student_email'] = $st['email'] ?? '';
        }
        unset($r);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                return mb_strpos(mb_strtolower($r['student_name'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['student_code'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['course_code'] ?? ''), $needle) !== false;
            }));
        }

        $activeCourses = $this->pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code ASC")->fetchAll();

        return [
            'enrollments' => $rows,
            'courses'     => $activeCourses,
        ];
    }

    public function deleteEnrollment($enrollmentId) {
        $stmt = $this->pdo->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt->execute([(int)$enrollmentId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Enrollment record not found.', 404);
        }
        return true;
    }

    public function deleteEnrollmentsByStudent($studentPk) {
        $stmt = $this->pdo->prepare("DELETE FROM enrollments WHERE student_id = ?");
        $stmt->execute([(int)$studentPk]);
        return $stmt->rowCount();
    }

    // =========================================================
    // Grades & GPA
    // =========================================================
    public function calculateStudentGpa($studentPk) {
        $stmt = $this->pdo->prepare("
            SELECT g.grade_point, c.credits
            FROM grades g
            JOIN enrollments e ON g.enrollment_id = e.id
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ? AND g.total_score > 0
        ");
        $stmt->execute([(int)$studentPk]);
        $grades = $stmt->fetchAll();

        if (empty($grades)) {
            return [
                'gpa'            => '0.00',
                'total_credits'  => 0,
                'courses_graded' => 0,
            ];
        }

        $totalPoints  = 0;
        $totalCredits = 0;
        foreach ($grades as $row) {
            $credits = (int)$row['credits'];
            $point   = (float)$row['grade_point'];
            $totalPoints  += ($point * $credits);
            $totalCredits += $credits;
        }

        $gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.00;
        return [
            'gpa'            => number_format($gpa, 2),
            'total_credits'  => $totalCredits,
            'courses_graded' => count($grades),
        ];
    }

    public function getStudentTranscript($studentPk) {
        $stmt = $this->pdo->prepare("
            SELECT e.id AS enrollment_id, e.semester, e.academic_year, e.status AS enrollment_status,
                   c.course_code, c.course_name, c.department, c.credits, c.instructor_name,
                   g.id AS grade_id, g.assignment_score, g.midterm_score, g.final_score,
                   g.total_score, g.letter_grade, g.grade_point, g.remarks, g.graded_at
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE e.student_id = ?
            ORDER BY e.academic_year DESC, e.semester DESC, c.course_code ASC
        ");
        $stmt->execute([(int)$studentPk]);
        $records = $stmt->fetchAll();

        $gpaData = $this->calculateStudentGpa($studentPk);

        return [
            'gpa_data' => $gpaData,
            'records'  => $records,
        ];
    }

    public function listGradesAdmin($courseId = 0, $search = '') {
        $sql = "
            SELECT e.id AS enrollment_id, e.student_id, e.semester, e.academic_year,
                   c.id AS course_id, c.course_code, c.course_name, c.credits,
                   g.id AS grade_id, g.assignment_score, g.midterm_score, g.final_score,
                   g.total_score, g.letter_grade, g.grade_point, g.remarks, g.graded_at
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE e.status = 'enrolled'
        ";
        $params = [];
        if ($courseId > 0) {
            $sql .= " AND e.course_id = ? ";
            $params[] = (int)$courseId;
        }
        $sql .= " ORDER BY c.course_code ASC, e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $studentMap = $this->fetchStudentsMap(array_column($rows, 'student_id'));
        foreach ($rows as &$r) {
            $st = $studentMap[(int)$r['student_id']] ?? null;
            $r['student_code'] = $st['student_id'] ?? ('ID-' . $r['student_id']);
            $r['student_name'] = $st['name'] ?? 'Unknown Student';
        }
        unset($r);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                return mb_strpos(mb_strtolower($r['student_name'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['student_code'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['course_code'] ?? ''), $needle) !== false;
            }));
        }

        // Sort cleanly by course_code, student_name like monolith
        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['course_code'], $b['course_code']);
            return $cmp !== 0 ? $cmp : strcmp($a['student_name'], $b['student_name']);
        });

        $classAvg = null;
        if ($courseId > 0) {
            $stmtStats = $this->pdo->prepare("
                SELECT AVG(g.total_score) AS avg_score, AVG(g.grade_point) AS avg_gpa,
                       MAX(g.total_score) AS max_score, COUNT(g.id) AS graded_count
                FROM grades g
                JOIN enrollments e ON g.enrollment_id = e.id
                WHERE e.course_id = ? AND g.total_score > 0
            ");
            $stmtStats->execute([(int)$courseId]);
            $classAvg = $stmtStats->fetch();
        }

        $coursesList = $this->pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code ASC")->fetchAll();

        return [
            'records'     => $rows,
            'class_avg'   => $classAvg,
            'courses'     => $coursesList,
        ];
    }

    public function saveGrade(array $data, $adminUserId) {
        $enrollmentId    = (int)($data['enrollment_id'] ?? 0);
        $assignmentScore = (float)($data['assignment_score'] ?? 0);
        $midtermScore    = (float)($data['midterm_score'] ?? 0);
        $finalScore      = (float)($data['final_score'] ?? 0);
        $remarks         = trim((string)($data['remarks'] ?? ''));

        if ($enrollmentId <= 0) {
            throw new InvalidArgumentException('Invalid enrollment record.', 400);
        }

        $stmtEnroll = $this->pdo->prepare("
            SELECT e.id, e.student_id, c.course_code, c.course_name
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.id = ? LIMIT 1
        ");
        $stmtEnroll->execute([$enrollmentId]);
        $enrollData = $stmtEnroll->fetch();
        if (!$enrollData) {
            throw new RuntimeException('Enrollment not found.', 404);
        }

        $calc = calculate_grade($assignmentScore, $midtermScore, $finalScore);

        $chk = $this->pdo->prepare("SELECT id FROM grades WHERE enrollment_id = ? LIMIT 1");
        $chk->execute([$enrollmentId]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $upd = $this->pdo->prepare("
                UPDATE grades
                SET assignment_score = ?, midterm_score = ?, final_score = ?, total_score = ?,
                    letter_grade = ?, grade_point = ?, remarks = ?, graded_by = ?, graded_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $assignmentScore, $midtermScore, $finalScore, $calc['total'],
                $calc['letter'], $calc['point'], $remarks ?: null, (int)$adminUserId, (int)$existingId
            ]);
        } else {
            $ins = $this->pdo->prepare("
                INSERT INTO grades (enrollment_id, assignment_score, midterm_score, final_score, total_score, letter_grade, grade_point, remarks, graded_by, graded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $enrollmentId, $assignmentScore, $midtermScore, $finalScore, $calc['total'],
                $calc['letter'], $calc['point'], $remarks ?: null, (int)$adminUserId
            ]);
        }

        // Resolve student user_id & name via student-service
        $stRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . (int)$enrollData['student_id']);
        $studentName = 'Student';
        if ($stRes['status'] === 200 && !empty($stRes['body']['data'])) {
            $stInfo = $stRes['body']['data'];
            $studentName = $stInfo['name'] ?? 'Student';
            if (!empty($stInfo['user_id'])) {
                HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                    'user_id' => (int)$stInfo['user_id'],
                    'title'   => 'Grade Finalized / Updated',
                    'message' => "Your grade for {$enrollData['course_code']} ({$enrollData['course_name']}) has been recorded: Letter Grade {$calc['letter']} ({$calc['total']}%).",
                    'type'    => 'success',
                    'link'    => 'student/grades.php',
                ]);
            }
        }

        return [
            'enrollment_id' => $enrollmentId,
            'student_name'  => $studentName,
            'course_code'   => $enrollData['course_code'],
            'calculated'    => $calc,
        ];
    }

    public function getEnrollmentCountsByStudent() {
        $rows = $this->pdo->query("
            SELECT student_id, COUNT(*) AS cnt
            FROM enrollments
            WHERE status = 'enrolled'
            GROUP BY student_id
        ")->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['student_id']] = (int)$r['cnt'];
        }
        return $map;
    }

    public function getMetrics() {
        $activeCourses = (int)$this->pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetchColumn();
        $activeEnrollments = (int)$this->pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'")->fetchColumn();
        return [
            'total_courses'     => $activeCourses,
            'total_enrollments' => $activeEnrollments,
        ];
    }
}
