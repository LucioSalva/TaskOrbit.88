<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\DateHelper;

class Proyecto
{
    public static function getAll(string $role, int $userId, array $filters = []): array
    {
        $db     = Database::getInstance();
        $sql    = 'SELECT * FROM vw_proyectos WHERE 1=1';
        $params = [];

        if ($role === 'USER') {
            $sql .= ' AND usuario_asignado_id = ?';
            $params[] = $userId;
        } elseif ($role === 'ADMIN') {
            $sql .= ' AND created_by = ?';
            $params[] = $userId;
        }

        if (!empty($filters['estado'])) {
            $sql .= ' AND estado = ?';
            $params[] = $filters['estado'];
        }
        if (!empty($filters['prioridad'])) {
            $sql .= ' AND prioridad = ?';
            $params[] = $filters['prioridad'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND nombre ILIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY created_at DESC';

        return $db->fetchAll($sql, $params);
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne('SELECT * FROM vw_proyectos WHERE id = ?', [$id]);
    }

    public static function create(array $data, int $createdBy): int
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $estimacion = DateHelper::calculateEstimatedMinutes(
                $data['fecha_inicio'] ?? null,
                $data['fecha_fin'] ?? null
            );

            $stmt = $db->query(
                'INSERT INTO proyectos
                   (nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin,
                    estimacion_minutos, usuario_asignado_id, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?) RETURNING id',
                [
                    $data['nombre'],
                    $data['descripcion'] ?? null,
                    $data['prioridad'] ?? 'media',
                    $data['estado'] ?? 'por_hacer',
                    $data['fecha_inicio'] ?: null,
                    $data['fecha_fin'] ?: null,
                    $estimacion,
                    $data['usuario_asignado_id'],
                    $createdBy,
                ]
            );
            $id = (int)$stmt->fetchColumn();

            self::logAudit($createdBy, 'PROYECTO_CREATE', $id, $data);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $fields = [];
            $params = [];

            $stringFields  = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin'];
            $intFields     = ['usuario_asignado_id'];
            $allowed       = array_merge($stringFields, $intFields);
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    if (in_array($field, $intFields, true)) {
                        $params[] = isset($data[$field]) && $data[$field] !== '' ? (int)$data[$field] : null;
                    } else {
                        $params[] = isset($data[$field]) ? (trim((string)$data[$field]) === '' ? null : $data[$field]) : null;
                    }
                }
            }

            if (array_key_exists('fecha_inicio', $data) || array_key_exists('fecha_fin', $data)) {
                $current = self::getById($id);
                $inicio  = $data['fecha_inicio'] ?? $current['fecha_inicio'] ?? null;
                $fin     = $data['fecha_fin'] ?? $current['fecha_fin'] ?? null;
                $est     = DateHelper::calculateEstimatedMinutes($inicio, $fin);
                $fields[] = "estimacion_minutos = ?";
                $params[]  = $est;
            }

            if (empty($fields)) return false;

            $params[] = $id;
            $db->execute(
                'UPDATE proyectos SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL',
                $params
            );

            self::logAudit($data['updated_by'] ?? null, 'PROYECTO_UPDATE', $id, $data);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function softDelete(int $id, int $actorId, ?string $reason = null): array
    {
        $db  = Database::getInstance();
        $db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            $taskRows = $db->fetchAll(
                'SELECT id FROM tareas WHERE proyecto_id = ? AND deleted_at IS NULL',
                [$id]
            );
            $taskIds = array_column($taskRows, 'id');

            $subtaskIds = [];
            if ($taskIds) {
                $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                $subtaskRows  = $db->fetchAll(
                    "SELECT id FROM subtareas WHERE tarea_id IN ($placeholders) AND deleted_at IS NULL",
                    $taskIds
                );
                $subtaskIds = array_column($subtaskRows, 'id');
            }

            $db->execute('UPDATE proyectos SET deleted_at = ? WHERE id = ?', [$now, $id]);

            $deletedTareas = 0;
            if ($taskIds) {
                $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                $deleted = $db->query(
                    "UPDATE tareas SET deleted_at = ? WHERE id IN ($placeholders) AND deleted_at IS NULL RETURNING id",
                    array_merge([$now], $taskIds)
                );
                $deletedTareas = $deleted->rowCount();
            }

            $deletedSubtareas = 0;
            if ($subtaskIds) {
                $placeholders = implode(',', array_fill(0, count($subtaskIds), '?'));
                $deleted = $db->query(
                    "UPDATE subtareas SET deleted_at = ? WHERE id IN ($placeholders) AND deleted_at IS NULL RETURNING id",
                    array_merge([$now], $subtaskIds)
                );
                $deletedSubtareas = $deleted->rowCount();
            }

            $db->execute(
                'UPDATE notas SET deleted_at = ? WHERE scope = ? AND referencia_id = ? AND deleted_at IS NULL',
                [$now, 'proyecto', $id]
            );

            self::logAudit($actorId, 'PROYECTO_DELETE', $id, [
                'reason'    => $reason,
                'tareas'    => $deletedTareas,
                'subtareas' => $deletedSubtareas,
            ]);

            $db->commit();
            return ['tareas' => $deletedTareas, 'subtareas' => $deletedSubtareas];
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function getDeletePreview(int $id): array
    {
        $db = Database::getInstance();

        $proyecto = self::getById($id);
        $tareas   = $db->fetchOne(
            'SELECT COUNT(*)::int AS total FROM tareas WHERE proyecto_id = ? AND deleted_at IS NULL',
            [$id]
        );
        $subtareas = $db->fetchOne(
            'SELECT COUNT(*)::int AS total FROM subtareas s
             JOIN tareas t ON t.id = s.tarea_id
             WHERE t.proyecto_id = ? AND t.deleted_at IS NULL AND s.deleted_at IS NULL',
            [$id]
        );
        $notas = $db->fetchOne(
            "SELECT COUNT(*)::int AS total FROM notas WHERE scope = 'proyecto' AND referencia_id = ? AND deleted_at IS NULL",
            [$id]
        );

        return [
            'proyecto'  => $proyecto,
            'tareas'    => $tareas['total'] ?? 0,
            'subtareas' => $subtareas['total'] ?? 0,
            'notas'     => $notas['total'] ?? 0,
        ];
    }

    public static function checkAccess(array $proyecto, int $userId, string $role): bool
    {
        if ($role === 'GOD') return true;
        if ($role === 'ADMIN') return (int)$proyecto['created_by'] === $userId;
        if ($role === 'USER') return (int)$proyecto['usuario_asignado_id'] === $userId;
        return false;
    }

    public static function logAudit(?int $actorId, string $action, int $targetId, array $details = []): void
    {
        try {
            $db = Database::getInstance();
            $db->execute(
                'INSERT INTO audit_logs (actor_id, action, target_id, details, ip_address, method, endpoint)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $actorId,
                    $action,
                    $targetId,
                    json_encode($details),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['REQUEST_METHOD'] ?? null,
                    $_SERVER['REQUEST_URI'] ?? null,
                ]
            );
        } catch (\Throwable $e) {}
    }
}
