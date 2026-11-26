<?php
/**
 * UniConnect Microservices - Notification Service Business Logic
 */

class NotificationService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listForUser($userId, $limit = 0) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll();
    }

    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([(int)$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAllRead($userId) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([(int)$userId]);
        return $stmt->rowCount();
    }

    public function create(array $data) {
        $userId  = (int)($data['user_id'] ?? 0);
        $title   = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $type    = trim((string)($data['type'] ?? 'info'));
        $link    = isset($data['link']) && $data['link'] !== '' ? trim((string)$data['link']) : null;

        if ($userId <= 0 || $title === '' || $message === '') {
            throw new InvalidArgumentException('user_id, title, and message are required.', 400);
        }
        if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
            $type = 'info';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $title, $message, $type, $link]);
        $id = (int)$this->pdo->lastInsertId();

        $fetch = $this->pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $fetch->execute([$id]);
        return $fetch->fetch();
    }

    public function deleteForUser($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([(int)$userId]);
        return $stmt->rowCount();
    }
}
