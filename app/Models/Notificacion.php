<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Notificacion
{
    public static function getByUser(int $userId, int $limit = 20): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function getUnreadCount(int $userId): int
    {
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT COUNT(*)::int AS total FROM notifications WHERE user_id = ? AND read = FALSE',
            [$userId]
        );
        return $row['total'] ?? 0;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            'INSERT INTO notifications
               (user_id, type, title, message, severity, channel, entity_type, entity_id)
             VALUES (?,?,?,?,?,?,?,?) RETURNING id',
            [
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'],
                $data['severity'] ?? 'info',
                $data['channel'] ?? 'in_app',
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
            ]
        );
        return (int)$stmt->fetchColumn();
    }

    public static function markRead(int $id, int $userId): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE notifications SET read = TRUE, delivered_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE notifications SET read = TRUE, delivered_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read = FALSE',
            [$userId]
        );
    }

    /**
     * Notify all ADMIN and GOD users with the same message.
     * Used when a USER-role user performs actions on projects/tasks/subtasks/notes.
     */
    public static function notifyAdmins(array $data): void
    {
        $db = Database::getInstance();
        $admins = $db->fetchAll(
            "SELECT u.id
             FROM usuarios u
             JOIN usuarios_roles ur ON ur.usuario_id = u.id
             JOIN roles r ON r.id = ur.rol_id
             WHERE u.activo = TRUE AND r.nombre IN ('ADMIN', 'GOD')"
        );

        foreach ($admins as $admin) {
            try {
                $db->execute(
                    'INSERT INTO notifications
                       (user_id, type, title, message, severity, channel, entity_type, entity_id)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $admin['id'],
                        $data['type'],
                        $data['title'],
                        $data['message'],
                        $data['severity'] ?? 'info',
                        'in_app',
                        $data['entity_type'] ?? null,
                        $data['entity_id'] ?? null,
                    ]
                );
            } catch (\Throwable $e) {
                // Notification failures must not break the main flow
            }
        }
    }
}
