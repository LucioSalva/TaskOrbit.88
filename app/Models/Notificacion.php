<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Modelos de Datos
 *  Archivo: Notificacion.php
 *
 *  © 2025–2026 Humberto Salvador Ruiz Lucio.
 *  Todos los derechos reservados.
 *
 *  PROPIEDAD INTELECTUAL Y CONFIDENCIALIDAD:
 *  El presente código fuente, su estructura lógica,
 *  funcionalidad, arquitectura, diseño de datos,
 *  documentación y componentes asociados forman parte
 *  de un sistema propietario y confidencial.
 *
 *  Queda prohibida su copia, reproducción, distribución,
 *  adaptación, descompilación, comercialización,
 *  divulgación o utilización no autorizada, total o parcial,
 *  por cualquier medio, sin el consentimiento previo
 *  y por escrito de su titular.
 *
 *  El uso no autorizado de este software podrá dar lugar
 *  a las acciones legales civiles, mercantiles, administrativas
 *  o penales correspondientes conforme a la legislación aplicable
 *  en los Estados Unidos Mexicanos.
 *
 *  Uso interno exclusivo.
 *  Documento/código confidencial.
 * ================================================================
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Notificacion
{
    public static function getByUser(int $userId, int $limit = 20): array
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT
                id,
                user_id,
                type,
                title,
                message,
                severity,
                channel,
                entity_type,
                entity_id,
                CASE WHEN "read" = true THEN 1 ELSE 0 END AS is_read,
                delivered_at,
                created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$userId, $limit]
        );

        // Normalise is_read to a plain PHP bool for consistent JS consumption.
        foreach ($rows as &$row) {
            $row['is_read'] = (bool)$row['is_read'];
        }
        unset($row);

        return $rows;
    }

    public static function getUnreadCount(int $userId): int
    {
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT COUNT(*)::int AS total FROM notifications WHERE user_id = ? AND "read" = FALSE',
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
            'UPDATE notifications SET "read" = TRUE, delivered_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE notifications SET "read" = TRUE, delivered_at = CURRENT_TIMESTAMP WHERE user_id = ? AND "read" = FALSE',
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
