<?php
/**
 * UniConnect Microservices - Course, Enrollment & Grading Service REST Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CourseService.php';

Response::handlePreflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route  = Response::getRoutePath();

try {
    if ($method === 'GET' && ($route === '/' || $route === '/health')) {
        $pdo = course_db();
        $pdo->query("SELECT 1");
        Response::success([
            'service'  => 'course-service',
            'database' => DB_COURSE,
            'status'   => 'healthy',
        ], 'Course service is healthy');
    }

    $service = new CourseService(course_db());

    // ---------------------------------------------------------
    // Internal Service-to-Service Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && preg_match('#^/internal/students/(\d+)/gpa$#', $route, $m)) {
        Response::requireInternal();
        Response::success($service->calculateStudentGpa((int)$m[1]));
    }

    if ($method === 'GET' && preg_match('#^/internal/students/(\d+)/enrollments$#', $route, $m)) {
        Response::requireInternal();
        $status = trim((string)($_GET['status'] ?? 'enrolled'));
        Response::success($service->getStudentEnrollments((int)$m[1], $status));
    }

    if ($method === 'DELETE' && preg_match('#^/internal/students/(\d+)/enrollments$#', $route, $m)) {
        Response::requireInternal();
        $deleted = $service->deleteEnrollmentsByStudent((int)$m[1]);
        Response::success(['deleted' => $deleted], 'Student enrollments removed');
    }

    if ($method === 'GET' && $route === '/internal/enrollment-counts-by-student') {
        Response::requireInternal();
        Response::success($service->getEnrollmentCountsByStudent());
    }

    if ($method === 'GET' && $route === '/internal/metrics') {
        Response::requireInternal();
        Response::success($service->getMetrics());
    }

    // ---------------------------------------------------------
    // Courses Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/courses') {
        $ctx = Response::requireAuth();
        $studentPk = ($ctx['role'] === 'student') ? $service->resolveStudentPk($ctx) : 0;
        $search = trim((string)($_GET['search'] ?? ''));
        $dept   = trim((string)($_GET['department'] ?? ''));
        Response::success($service->listCourses($ctx['role'], $studentPk, $search, $dept));
    }

    if ($method === 'GET' && preg_match('#^/courses/(\d+)$#', $route, $m)) {
        Response::requireAuth();
        $course = $service->getCourse((int)$m[1]);
        if (!$course) {
            Response::error('Course not found.', 404);
        }
        Response::success($course);
    }

    if ($method === 'POST' && $route === '/courses') {
        Response::requireRole('admin');
        $body = Response::getJsonBody();
        $created = $service->createCourse($body);
        Response::success($created, "Course '{$created['course_code']} &mdash; {$created['course_name']}' was created successfully.", 201);
    }

    if ($method === 'PUT' && preg_match('#^/courses/(\d+)$#', $route, $m)) {
        Response::requireRole('admin');
        $body = Response::getJsonBody();
        $updated = $service->updateCourse((int)$m[1], $body);
        Response::success($updated, "Course '{$updated['course_code']} &mdash; {$updated['course_name']}' updated successfully.");
    }

    if ($method === 'DELETE' && preg_match('#^/courses/(\d+)$#', $route, $m)) {
        Response::requireRole('admin');
        $deleted = $service->deleteCourse((int)$m[1]);
        Response::success($deleted, "Course '{$deleted['course_code']} &mdash; {$deleted['course_name']}' was deleted.");
    }

    // ---------------------------------------------------------
    // Enrollments Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/enrollments') {
        $ctx = Response::requireAuth();
        if ($ctx['role'] === 'admin') {
            $courseFilter = (int)($_GET['course_id'] ?? 0);
            $search = trim((string)($_GET['search'] ?? ''));
            Response::success($service->listAllEnrollmentsAdmin($courseFilter, $search));
        } else {
            $studentPk = $service->resolveStudentPk($ctx);
            $courses = $service->getStudentEnrollments($studentPk, 'enrolled');
            $totalCredits = 0;
            foreach ($courses as $c) {
                $totalCredits += (int)$c['credits'];
            }
            Response::success([
                'enrollments'   => $courses,
                'total_credits' => $totalCredits,
            ]);
        }
    }

    if ($method === 'POST' && $route === '/enrollments') {
        $ctx = Response::requireAuth();
        $body = Response::getJsonBody();

        if ($ctx['role'] === 'admin') {
            $studentPk = (int)($body['student_id'] ?? 0);
            $courseId  = (int)($body['course_id'] ?? 0);
            $semester  = trim((string)($body['semester'] ?? DEFAULT_SEMESTER));

            // Fetch student's user_id via student-service
            $stRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . $studentPk);
            if ($stRes['status'] !== 200 || empty($stRes['body']['data'])) {
                Response::error('Selected student not found.', 404);
            }
            $targetUserId = (int)$stRes['body']['data']['user_id'];
            $result = $service->enrollStudent($studentPk, $courseId, $targetUserId, $semester, true);
            Response::success($result, "Student successfully enrolled in {$result['course']['course_code']}!", 201);
        } else {
            $studentPk = $service->resolveStudentPk($ctx);
            $courseId  = (int)($body['course_id'] ?? 0);
            $result = $service->enrollStudent($studentPk, $courseId, $ctx['id'], null, false);
            Response::success($result, "Successfully enrolled in {$result['course']['course_code']} &mdash; {$result['course']['course_name']}!", 201);
        }
    }

    if ($method === 'POST' && preg_match('#^/enrollments/(\d+)/drop$#', $route, $m)) {
        $ctx = Response::requireRole('student');
        $studentPk = $service->resolveStudentPk($ctx);
        $dropped = $service->dropEnrollment((int)$m[1], $studentPk, $ctx['id']);
        Response::success($dropped, "Successfully dropped {$dropped['course_code']} &mdash; {$dropped['course_name']}.");
    }

    if ($method === 'DELETE' && preg_match('#^/enrollments/(\d+)$#', $route, $m)) {
        Response::requireRole('admin');
        $service->deleteEnrollment((int)$m[1]);
        Response::success(null, 'Enrollment record removed.');
    }

    // ---------------------------------------------------------
    // Grades Endpoints
    // ---------------------------------------------------------
    if ($method === 'GET' && $route === '/grades') {
        $ctx = Response::requireAuth();
        if ($ctx['role'] === 'admin') {
            $courseId = (int)($_GET['course_id'] ?? 0);
            $search   = trim((string)($_GET['search'] ?? ''));
            Response::success($service->listGradesAdmin($courseId, $search));
        } else {
            $studentPk = $service->resolveStudentPk($ctx);
            Response::success($service->getStudentTranscript($studentPk));
        }
    }

    if ($method === 'POST' && $route === '/grades') {
        $ctx = Response::requireRole('admin');
        $body = Response::getJsonBody();
        $saved = $service->saveGrade($body, $ctx['id']);
        Response::success(
            $saved,
            "Grade for {$saved['student_name']} in {$saved['course_code']} successfully saved: {$saved['calculated']['letter']} ({$saved['calculated']['total']}%)."
        );
    }

    Response::error("Route not found in course-service: {$method} {$route}", 404);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (RuntimeException $e) {
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 400 && $code < 600) ? $code : 400);
} catch (Throwable $e) {
    Response::error('Course service error: ' . $e->getMessage(), 500);
}
