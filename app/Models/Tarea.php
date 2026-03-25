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
 *  Archivo: Tarea.php
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
use App\Helpers\DateHelper;

class Tarea
{
    public static function getByProyecto(int $proyectoId, string $role, int $userId): array
    {
        $db = Database::getInstance();
        $params = [$proyectoId];
        $where  = ['t.proyecto_id = ?', 't.deleted_at IS NULL', 'p.deleted_at IS NULL'];

        if ($role === 'USER') {
            $where[]  = '(COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?)';
            $params[] = $userId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        return $db->fetchAll(
            "SELECT t.id, t.proyecto_id, t.nombre, t.descripcion, t.prioridad, t.estado,
                    t.fecha_inicio, t.fecha_fin, t.estimacion_minutos, t.created_by,
                    COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
                    COALESCE(ut.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
                    t.created_at, t.updated_at
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id
             LEFT JOIN usuarios ut ON ut.id = t.usuario_asignado_id
             LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
             $whereClause
             ORDER BY t.created_at DESC",
            $params
        );
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT t.id, t.proyecto_id, t.nombre, t.descripcion, t.prioridad, t.estado,
                    t.fecha_inicio, t.fecha_fin, t.estimacion_minutos, t.created_by,
                    p.created_by AS proyecto_created_by,
                    p.usuario_asignado_id AS proyecto_usuario_asignado_id,
                    p.fecha_inicio AS proyecto_fecha_inicio,
                    p.fecha_fin AS proyecto_fecha_fin,
                    p.nombre AS proyecto_nombre,
                    COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
                    COALESCE(ut.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
                    t.created_at, t.updated_at
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id
             LEFT JOIN usuarios ut ON ut.id = t.usuario_asignado_id
             LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
             WHERE t.id = ? AND t.deleted_at IS NULL AND p.deleted_at IS NULL",
            [$id]
        );
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
                'INSERT INTO tareas
                   (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio,
                    fecha_fin, estimacion_minutos, usuario_asignado_id, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING id',
                [
                    $data['proyecto_id'],
                    $data['nombre'],
                    $data['descripcion'] ?? null,
                    $data['prioridad'] ?? 'media',
                    $data['estado'] ?? 'por_hacer',
                    $data['fecha_inicio'] ?: null,
                    $data['fecha_fin'] ?: null,
                    $estimacion,
                    $data['usuario_asignado_id'] ?: null,
                    $createdBy,
                ]
            );
            $raw = $stmt->fetchColumn();
            error_log('[Tarea::create] RETURNING id raw=' . var_export($raw, true));
            $id = (int)$raw;

            if ($id <= 0) {
                $db->rollBack();
                throw new \RuntimeException(
                    'INSERT en tareas no devolvió un ID válido (RETURNING id retornó: ' . var_export($raw, true) . ')'
                );
            }

            $db->commit();
            error_log('[Tarea::create] Tarea creada con ID: ' . $id);
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        self::logAudit($createdBy, 'TAREA_CREATE', $id, $data);
        return $id;
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

            // updated_at se actualiza automáticamente vía trigger o DEFAULT

            if (empty($fields)) return false;

            $params[] = $id;
            $db->execute(
                'UPDATE tareas SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL',
                $params
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        self::logAudit($data['updated_by'] ?? null, 'TAREA_UPDATE', $id, $data);
        return true;
    }

    public static function updateEstado(int $id, string $estado, int $userId): bool
    {
        $db = Database::getInstance();
        $sql = 'UPDATE tareas SET estado = ? WHERE id = ? AND deleted_at IS NULL';
        return $db->execute($sql, [$estado, $id]);
    }

    public static function softDelete(int $id, int $actorId, ?string $reason = null): array
    {
        $db  = Database::getInstance();
        $db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            $subtaskRows = $db->fetchAll(
                'SELECT id FROM subtareas WHERE tarea_id = ? AND deleted_at IS NULL',
                [$id]
            );
            $subtaskIds = array_column($subtaskRows, 'id');

            $db->execute('UPDATE tareas SET deleted_at = ? WHERE id = ?', [$now, $id]);

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
                "UPDATE notas SET deleted_at = ? WHERE scope = 'tarea' AND referencia_id = ? AND deleted_at IS NULL",
                [$now, $id]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        self::logAudit($actorId, 'TAREA_DELETE', $id, ['reason' => $reason, 'subtareas' => $deletedSubtareas]);
        return ['subtareas' => $deletedSubtareas];
    }

    public static function getDeletePreview(int $id): array
    {
        $db = Database::getInstance();
        $tarea = self::getById($id);
        $subtareas = $db->fetchOne(
            'SELECT COUNT(*)::int AS total FROM subtareas WHERE tarea_id = ? AND deleted_at IS NULL',
            [$id]
        );
        $notas = $db->fetchOne(
            "SELECT COUNT(*)::int AS total FROM notas WHERE scope = 'tarea' AND referencia_id = ? AND deleted_at IS NULL",
            [$id]
        );

        return [
            'tarea'     => $tarea,
            'subtareas' => $subtareas['total'] ?? 0,
            'notas'     => $notas['total'] ?? 0,
        ];
    }

    /**
     * Fetch lightweight tarea rows for multiple proyecto IDs.
     * Used by SemaforoService for batch hierarchical calculation.
     */
    public static function getByProyectoIds(array $proyectoIds): array
    {
        if (empty($proyectoIds)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($proyectoIds), '?'));
        return $db->fetchAll(
            "SELECT t.id, t.proyecto_id, t.estado, t.fecha_fin, t.updated_at
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
             WHERE t.proyecto_id IN ($placeholders)
               AND t.deleted_at IS NULL",
            $proyectoIds
        );
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
