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
 *  Archivo: Nota.php
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

class Nota
{
    public static function getByScope(string $scope, int $referenciaId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT n.*, COALESCE(u.nombre_completo, \'Sistema\') AS autor_nombre
             FROM notas n
             LEFT JOIN usuarios u ON u.id = n.user_id
             WHERE n.scope = ? AND n.referencia_id = ? AND n.deleted_at IS NULL
             ORDER BY n.is_pinned DESC, n.created_at DESC',
            [$scope, $referenciaId]
        );
    }

    public static function getByEntity(string $scope, int $referenciaId): array
    {
        return self::getByScope($scope, $referenciaId);
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT n.*, COALESCE(u.nombre_completo, \'Sistema\') AS autor_nombre
             FROM notas n
             LEFT JOIN usuarios u ON u.id = n.user_id
             WHERE n.id = ? AND n.deleted_at IS NULL',
            [$id]
        );
    }

    public static function getPersonales(int $userId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT n.*, COALESCE(u.nombre_completo, 'Sistema') AS autor_nombre
             FROM notas n
             LEFT JOIN usuarios u ON u.id = n.user_id
             WHERE n.scope = 'personal' AND n.user_id = ? AND n.deleted_at IS NULL
             ORDER BY n.is_pinned DESC, n.created_at DESC",
            [$userId]
        );
    }

    public static function getAllForUser(int $userId, string $role): array
    {
        $db = Database::getInstance();

        if ($role === 'GOD') {
            return $db->fetchAll(
                "SELECT n.*, COALESCE(u.nombre_completo, 'Sistema') AS autor_nombre
                 FROM notas n
                 LEFT JOIN usuarios u ON u.id = n.user_id
                 WHERE n.deleted_at IS NULL
                 ORDER BY n.is_pinned DESC, n.created_at DESC"
            );
        }

        if ($role === 'ADMIN') {
            return $db->fetchAll(
                "SELECT n.*, COALESCE(u.nombre_completo, 'Sistema') AS autor_nombre
                 FROM notas n
                 LEFT JOIN usuarios u ON u.id = n.user_id
                 WHERE n.deleted_at IS NULL
                   AND (
                     (n.scope = 'personal' AND n.user_id = ?)
                     OR (n.scope = 'proyecto' AND n.referencia_id IN (
                           SELECT id FROM proyectos WHERE (created_by = ? OR usuario_asignado_id = ?) AND deleted_at IS NULL
                         ))
                     OR (n.scope = 'tarea' AND n.referencia_id IN (
                           SELECT t.id FROM tareas t
                           JOIN proyectos p ON p.id = t.proyecto_id
                           WHERE (p.created_by = ? OR p.usuario_asignado_id = ?) AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                         ))
                     OR (n.scope = 'subtarea' AND n.referencia_id IN (
                           SELECT s.id FROM subtareas s
                           JOIN tareas t ON t.id = s.tarea_id
                           JOIN proyectos p ON p.id = t.proyecto_id
                           WHERE (p.created_by = ? OR p.usuario_asignado_id = ?) AND s.deleted_at IS NULL AND t.deleted_at IS NULL AND p.deleted_at IS NULL
                         ))
                   )
                 ORDER BY n.is_pinned DESC, n.created_at DESC",
                [$userId, $userId, $userId, $userId, $userId, $userId, $userId]
            );
        }

        // USER sees: own personal notes + notes on projects/tasks/subtasks assigned to them
        return $db->fetchAll(
            "SELECT n.*, COALESCE(u.nombre_completo, 'Sistema') AS autor_nombre
             FROM notas n
             LEFT JOIN usuarios u ON u.id = n.user_id
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
             ORDER BY n.is_pinned DESC, n.created_at DESC",
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

    /**
     * Create an automatic/system note (e.g. on estado changes).
     * actor_id can be null for truly system-generated notes.
     */
    public static function createAuto(string $scope, ?int $referenciaId, string $contenido, ?int $actorId = null): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "INSERT INTO notas (scope, referencia_id, user_id, titulo, contenido, tipo)
             VALUES (?,?,?,NULL,?,'auto') RETURNING id",
            [$scope, $referenciaId, $actorId, $contenido]
        );
        return (int)$stmt->fetchColumn();
    }

    public static function update(int $id, string $titulo, string $contenido): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE notas SET titulo = ?, contenido = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$titulo ?: null, $contenido, $id]
        );
    }

    public static function togglePin(int $id): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE notas SET is_pinned = NOT is_pinned, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Check whether a user can edit a nota.
     * ADMIN/GOD can edit any; owner can edit within 24 h of creation.
     */
    public static function canEdit(array $nota, int $userId, string $role): bool
    {
        if (in_array($role, ['ADMIN', 'GOD'], true)) return true;
        if ((int)($nota['user_id'] ?? 0) !== $userId) return false;
        if (($nota['tipo'] ?? '') === 'auto') return false; // auto-notes are read-only for users
        $created = strtotime($nota['created_at'] ?? '0');
        return (time() - $created) < 86400;
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
