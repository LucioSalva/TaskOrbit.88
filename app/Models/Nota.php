<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Nota
{
    public static function getByScope(string $scope, int $referenciaId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT n.*, u.nombre_completo AS autor_nombre
             FROM notas n
             JOIN usuarios u ON u.id = n.user_id
             WHERE n.scope = ? AND n.referencia_id = ? AND n.deleted_at IS NULL
             ORDER BY n.created_at DESC',
            [$scope, $referenciaId]
        );
    }

    public static function getPersonales(int $userId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT n.*, u.nombre_completo AS autor_nombre
             FROM notas n
             JOIN usuarios u ON u.id = n.user_id
             WHERE n.scope = 'personal' AND n.user_id = ? AND n.deleted_at IS NULL
             ORDER BY n.created_at DESC",
            [$userId]
        );
    }

    public static function getAllForUser(int $userId, string $role): array
    {
        $db = Database::getInstance();

        if ($role === 'GOD') {
            return $db->fetchAll(
                'SELECT n.*, u.nombre_completo AS autor_nombre
                 FROM notas n
                 JOIN usuarios u ON u.id = n.user_id
                 WHERE n.deleted_at IS NULL
                 ORDER BY n.created_at DESC'
            );
        }

        if ($role === 'ADMIN') {
            // ADMIN sees: own personal notes + notes on projects they created + related tasks/subtasks
            return $db->fetchAll(
                "SELECT n.*, u.nombre_completo AS autor_nombre
                 FROM notas n
                 JOIN usuarios u ON u.id = n.user_id
                 WHERE n.deleted_at IS NULL
                   AND (
                     (n.scope = 'personal' AND n.user_id = ?)
                     OR (n.scope = 'proyecto' AND n.referencia_id IN (
                           SELECT id FROM proyectos WHERE created_by = ? AND deleted_at IS NULL
                         ))
                     OR (n.scope = 'tarea' AND n.referencia_id IN (
                           SELECT t.id FROM tareas t
                           JOIN proyectos p ON p.id = t.proyecto_id
                           WHERE p.created_by = ? AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                         ))
                     OR (n.scope = 'subtarea' AND n.referencia_id IN (
                           SELECT s.id FROM subtareas s
                           JOIN tareas t ON t.id = s.tarea_id
                           JOIN proyectos p ON p.id = t.proyecto_id
                           WHERE p.created_by = ? AND s.deleted_at IS NULL AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                         ))
                   )
                 ORDER BY n.created_at DESC",
                [$userId, $userId, $userId, $userId]
            );
        }

        // USER sees: own personal notes + notes on projects/tasks/subtasks assigned to them
        return $db->fetchAll(
            "SELECT n.*, u.nombre_completo AS autor_nombre
             FROM notas n
             JOIN usuarios u ON u.id = n.user_id
             WHERE n.deleted_at IS NULL
               AND (
                 (n.scope = 'personal' AND n.user_id = ?)
                 OR (n.scope = 'proyecto' AND n.referencia_id IN (
                       SELECT id FROM proyectos WHERE usuario_asignado_id = ? AND deleted_at IS NULL
                     ))
                 OR (n.scope = 'tarea' AND n.referencia_id IN (
                       SELECT t.id FROM tareas t
                       JOIN proyectos p ON p.id = t.proyecto_id
                       WHERE COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?
                         AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                     ))
                 OR (n.scope = 'subtarea' AND n.referencia_id IN (
                       SELECT s.id FROM subtareas s
                       JOIN tareas t ON t.id = s.tarea_id
                       JOIN proyectos p ON p.id = t.proyecto_id
                       WHERE COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?
                         AND s.deleted_at IS NULL AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                     ))
               )
             ORDER BY n.created_at DESC",
            [$userId, $userId, $userId, $userId]
        );
    }

    public static function create(array $data, int $userId): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            'INSERT INTO notas (scope, referencia_id, user_id, titulo, contenido, tipo)
             VALUES (?,?,?,?,?,?) RETURNING id',
            [
                $data['scope'],
                $data['referencia_id'] ?: null,
                $userId,
                $data['titulo'] ?? null,
                $data['contenido'],
                $data['tipo'] ?? 'personal',
            ]
        );
        return (int)$stmt->fetchColumn();
    }

    public static function softDelete(int $id, int $userId, bool $adminOverride = false): bool
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        if ($adminOverride) {
            return $db->execute(
                'UPDATE notas SET deleted_at = ? WHERE id = ?',
                [$now, $id]
            );
        }
        return $db->execute(
            'UPDATE notas SET deleted_at = ? WHERE id = ? AND user_id = ?',
            [$now, $id, $userId]
        );
    }
}
