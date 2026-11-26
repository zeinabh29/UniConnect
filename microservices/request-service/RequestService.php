<?php
/**
 * UniConnect Microservices - Request Service Business Logic
 * Handles Student Certificate Requests & Public Contact Inquiries
 */

class RequestService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function resolveStudent(array $ctx) {
        if (!empty($ctx['student_pk'])) {
            return [
                'id'         => (int)$ctx['student_pk'],
                'student_id' => $ctx['student_id'] ?? '',
            ];
        }
        $res = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/by-user/' . (int)$ctx['id']);
        if ($res['status'] === 200 && !empty($res['body']['data'])) {
            return $res['body']['data'];
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

    private function fetchUsersMap(array $userIds) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($ids)) {
            return [];
        }
        $res = HttpClient::internal('GET', SERVICE_AUTH . '/internal/users/batch', ['ids' => implode(',', $ids)]);
        if ($res['status'] === 200 && is_array($res['body']['data'] ?? null)) {
            $map = [];
            foreach ($res['body']['data'] as $u) {
                $map[(int)$u['id']] = $u;
            }
            return $map;
        }
        return [];
    }

    private function enrichCertificateRows(array $rows) {
        if (empty($rows)) {
            return [];
        }
        $studentMap = $this->fetchStudentsMap(array_column($rows, 'student_id'));
        $adminMap   = $this->fetchUsersMap(array_column($rows, 'processed_by'));

        foreach ($rows as &$r) {
            $st = $studentMap[(int)$r['student_id']] ?? null;
            $ad = !empty($r['processed_by']) ? ($adminMap[(int)$r['processed_by']] ?? null) : null;

            $r['student_code']  = $st['student_id'] ?? ('ID-' . $r['student_id']);
            $r['student_id_code'] = $r['student_code'];
            $r['department']    = $st['department'] ?? '';
            $r['student_name']  = $st['name'] ?? 'Unknown Student';
            $r['student_email'] = $st['email'] ?? '';
            $r['reviewer_name'] = $ad['name'] ?? null;
        }
        unset($r);
        return $rows;
    }

    // =========================================================
    // Certificate Requests
    // =========================================================
    public function listStudentCertificates($studentPk, $limit = 0) {
        $sql = "SELECT * FROM certificate_requests WHERE student_id = ? ORDER BY requested_at DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([(int)$studentPk]);
        $requests = $stmt->fetchAll();

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM certificate_requests WHERE student_id = ? AND status = 'pending'");
        $cntStmt->execute([(int)$studentPk]);
        $pendingCount = (int)$cntStmt->fetchColumn();

        return [
            'requests'      => $requests,
            'pending_count' => $pendingCount,
        ];
    }

    public function listAdminCertificates($statusFilter = '', $search = '') {
        $sql = "SELECT * FROM certificate_requests WHERE 1=1 ";
        $params = [];
        if ($statusFilter !== '') {
            $sql .= " AND status = ? ";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY requested_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $this->enrichCertificateRows($stmt->fetchAll());

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                return mb_strpos(mb_strtolower($r['student_name'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['student_code'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['reference_no'] ?? ''), $needle) !== false
                    || mb_strpos(mb_strtolower($r['certificate_type'] ?? ''), $needle) !== false;
            }));
        }

        $countPending  = (int)$this->pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'pending'")->fetchColumn();
        $countApproved = (int)$this->pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'approved'")->fetchColumn();
        $countTotal    = (int)$this->pdo->query("SELECT COUNT(*) FROM certificate_requests")->fetchColumn();

        return [
            'requests' => $rows,
            'counts'   => [
                'pending'  => $countPending,
                'approved' => $countApproved,
                'total'    => $countTotal,
            ],
        ];
    }

    public function submitCertificate($studentPk, $studentCode, array $user, array $data) {
        $certType     = trim((string)($data['certificate_type'] ?? ''));
        $purpose      = trim((string)($data['purpose'] ?? ''));
        $deliveryType = trim((string)($data['delivery_type'] ?? 'digital'));

        if ($certType === '' || $purpose === '') {
            throw new InvalidArgumentException('Please choose a certificate type and provide a reason/purpose.', 400);
        }
        if (!in_array($deliveryType, ['digital', 'physical_pickup'], true)) {
            $deliveryType = 'digital';
        }

        $referenceNo = generate_cert_reference();

        $stmt = $this->pdo->prepare("
            INSERT INTO certificate_requests (student_id, certificate_type, purpose, delivery_type, status, reference_no, requested_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([(int)$studentPk, $certType, $purpose, $deliveryType, $referenceNo]);
        $newId = (int)$this->pdo->lastInsertId();

        // Notify student
        HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
            'user_id' => (int)$user['id'],
            'title'   => 'Certificate Request Submitted',
            'message' => "Your request for {$certType} (#{$referenceNo}) has been submitted to the Registrar Office.",
            'type'    => 'info',
            'link'    => 'student/certificates.php',
        ]);

        // Notify administrators
        $adminRes = HttpClient::internal('GET', SERVICE_AUTH . '/internal/admin-ids');
        if ($adminRes['status'] === 200 && is_array($adminRes['body']['data'] ?? null)) {
            foreach ($adminRes['body']['data'] as $adminId) {
                HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                    'user_id' => (int)$adminId,
                    'title'   => 'New Certificate Request',
                    'message' => "Student {$user['name']} ({$studentCode}) requested {$certType}.",
                    'type'    => 'warning',
                    'link'    => 'admin/certificates.php',
                ]);
            }
        }

        $fetch = $this->pdo->prepare("SELECT * FROM certificate_requests WHERE id = ?");
        $fetch->execute([$newId]);
        return $fetch->fetch();
    }

    public function processCertificate($certId, array $data, $adminUserId) {
        $stmt = $this->pdo->prepare("SELECT * FROM certificate_requests WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$certId]);
        $req = $stmt->fetch();
        if (!$req) {
            throw new RuntimeException('Certificate request not found.', 404);
        }

        $newStatus    = trim((string)($data['status'] ?? 'pending'));
        $adminRemarks = trim((string)($data['admin_remarks'] ?? ''));

        if (!in_array($newStatus, ['pending', 'approved', 'rejected', 'completed'], true)) {
            throw new InvalidArgumentException('Invalid status value.', 400);
        }

        $upd = $this->pdo->prepare("
            UPDATE certificate_requests
            SET status = ?, admin_remarks = ?, processed_by = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$newStatus, $adminRemarks ?: null, (int)$adminUserId, (int)$certId]);

        // Notify student via student-service + notification-service
        $stRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . (int)$req['student_id']);
        if ($stRes['status'] === 200 && !empty($stRes['body']['data']['user_id'])) {
            $notifType = in_array($newStatus, ['approved', 'completed'], true)
                ? 'success'
                : ($newStatus === 'rejected' ? 'danger' : 'info');

            HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                'user_id' => (int)$stRes['body']['data']['user_id'],
                'title'   => 'Certificate Request ' . ucfirst($newStatus),
                'message' => "Your request for {$req['certificate_type']} (#{$req['reference_no']}) has been marked as {$newStatus}. " . ($adminRemarks ? "Notes: {$adminRemarks}" : ""),
                'type'    => $notifType,
                'link'    => 'student/certificates.php',
            ]);
        }

        $fetch = $this->pdo->prepare("SELECT * FROM certificate_requests WHERE id = ?");
        $fetch->execute([(int)$certId]);
        return $this->enrichCertificateRows([$fetch->fetch()])[0];
    }

    public function getCertificateForView($certId, array $ctx) {
        $stmt = $this->pdo->prepare("SELECT * FROM certificate_requests WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$certId]);
        $cert = $stmt->fetch();
        if (!$cert) {
            throw new RuntimeException('Certificate request not found.', 404);
        }

        // Student ownership verification
        if ($ctx['role'] === 'student') {
            $student = $this->resolveStudent($ctx);
            if ((int)$cert['student_id'] !== (int)$student['id']) {
                throw new RuntimeException('Unauthorized access to this document.', 403);
            }
        }

        $stRes = HttpClient::internal('GET', SERVICE_STUDENT . '/internal/students/' . (int)$cert['student_id']);
        $studentInfo = ($stRes['status'] === 200 && !empty($stRes['body']['data'])) ? $stRes['body']['data'] : [];

        $approverName = null;
        if (!empty($cert['processed_by'])) {
            $adRes = HttpClient::internal('GET', SERVICE_AUTH . '/internal/users/' . (int)$cert['processed_by']);
            if ($adRes['status'] === 200 && !empty($adRes['body']['data']['name'])) {
                $approverName = $adRes['body']['data']['name'];
            }
        }

        $gpaRes = HttpClient::internal('GET', SERVICE_COURSE . '/internal/students/' . (int)$cert['student_id'] . '/gpa');
        $gpaData = ($gpaRes['status'] === 200 && !empty($gpaRes['body']['data']))
            ? $gpaRes['body']['data']
            : ['gpa' => '0.00', 'total_credits' => 0, 'courses_graded' => 0];

        return [
            'certificate' => array_merge($cert, [
                'student_pk'    => (int)$cert['student_id'],
                'student_code'  => $studentInfo['student_id'] ?? '',
                'department'    => $studentInfo['department'] ?? '',
                'academic_year' => $studentInfo['academic_year'] ?? '',
                'semester'      => $studentInfo['semester'] ?? '',
                'date_of_birth' => $studentInfo['date_of_birth'] ?? null,
                'student_name'  => $studentInfo['name'] ?? '',
                'student_email' => $studentInfo['email'] ?? '',
                'approver_name' => $approverName,
            ]),
            'gpa_data' => $gpaData,
        ];
    }

    public function deleteCertificatesByStudent($studentPk) {
        $stmt = $this->pdo->prepare("DELETE FROM certificate_requests WHERE student_id = ?");
        $stmt->execute([(int)$studentPk]);
        return $stmt->rowCount();
    }

    // =========================================================
    // Contact Inquiries / Messages
    // =========================================================
    public function submitContactMessage(array $data) {
        $name    = trim((string)($data['name'] ?? ''));
        $email   = trim((string)($data['email'] ?? ''));
        $subject = trim((string)($data['subject'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            throw new InvalidArgumentException('Please fill in all contact form fields.', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.', 400);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$name, $email, $subject, $message]);
        $newId = (int)$this->pdo->lastInsertId();

        // Notify admins
        $adminRes = HttpClient::internal('GET', SERVICE_AUTH . '/internal/admin-ids');
        if ($adminRes['status'] === 200 && is_array($adminRes['body']['data'] ?? null)) {
            foreach ($adminRes['body']['data'] as $adminId) {
                HttpClient::internal('POST', SERVICE_NOTIFICATION . '/internal/notifications', [
                    'user_id' => (int)$adminId,
                    'title'   => 'New Contact Inquiry',
                    'message' => "Message received from {$name} ({$email}): {$subject}",
                    'type'    => 'info',
                    'link'    => 'admin/messages.php',
                ]);
            }
        }

        $fetch = $this->pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $fetch->execute([$newId]);
        return $fetch->fetch();
    }

    public function listMessages() {
        return $this->pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    }

    public function markMessageRead($msgId) {
        $stmt = $this->pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([(int)$msgId]);
        if ($stmt->rowCount() === 0) {
            $chk = $this->pdo->prepare("SELECT id FROM contact_messages WHERE id = ?");
            $chk->execute([(int)$msgId]);
            if ($chk->rowCount() === 0) {
                throw new RuntimeException('Message not found.', 404);
            }
        }
        return true;
    }

    public function deleteMessage($msgId) {
        $stmt = $this->pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([(int)$msgId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Message not found.', 404);
        }
        return true;
    }

    public function getMetrics() {
        $pendingCerts = (int)$this->pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'pending'")->fetchColumn();
        $unreadMsgs   = (int)$this->pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

        $pendingRaw = $this->pdo->query("
            SELECT * FROM certificate_requests
            WHERE status = 'pending'
            ORDER BY requested_at ASC
            LIMIT 5
        ")->fetchAll();
        $pendingList = $this->enrichCertificateRows($pendingRaw);

        return [
            'pending_certs'     => $pendingCerts,
            'unread_messages'   => $unreadMsgs,
            'pending_cert_list' => $pendingList,
        ];
    }
}
