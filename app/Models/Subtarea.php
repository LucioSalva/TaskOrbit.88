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
 *  Archivo: Subtarea.php
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

class Subtarea
{
    public static function getByTarea(int $tareaId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT id, tarea_id, nombre, descripcion, prioridad, estado,
                    fecha_inicio, fecha_fin, usuario_asignado_id, usuario_asignado_nombre,
                    created_by, created_at, updated_at
             FROM vw_subtareas WHERE tarea_id = ? ORDER BY created_at ASC',
            [$tareaId]
        );
    }

    /**
     * Lightweight subtarea rows for multiple tarea IDs.
     * Used by NotasController para dropdown de scope=subtarea.
     */
    public static function getListByTareaIds(array $tareaIds): array
    {
        if (empty($tareaIds)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($tareaIds), '?'));
        return $db->fetchAll(
            "SELECT id, nombre, tarea_id, usuario_asignado_id, usuario_asignado_nombre
             FROM vw_subtareas WHERE tarea_id IN ($placeholders) ORDER BY nombre",
            $tareaIds
        );
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT id, tarea_id, nombre, descripcion, prioridad, estado,
                    fecha_inicio, fecha_fin, usuario_asignado_id, usuario_asignado_nombre,
                    created_by, created_at, updated_at
             FROM vw_subtareas WHERE id = ?',
            [$id]
        );
    }

    public static function create(array $data, int $createdBy): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            'INSERT INTO subtareas
                (tarea_id, nombre, descripcion, prioridad, estado,
                 fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $data['tarea_id'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['prioridad'] ?? 'media',
                $data['estado'] ?? 'por_hacer',
                $data['fecha_inicio'] ?: null,
                $data['fecha_fin'] ?: null,
                isset($data['usuario_asignado_id']) && $data['usuario_asignado_id'] !== '' && (int)$data['usuario_asignado_id'] > 0
                    ? (int)$data['usuario_asignado_id']
                    : null,
                $createdBy,
            ]
        );
        $id = (int)$stmt->fetchColumn();
        self::logAudit($createdBy, 'SUBTAREA_CREATE', $id, $data);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [];

        $stringFields = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin'];
        $intFields    = ['usuario_asignado_id'];
        $allowed      = array_merge($stringFields, $intFields);

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;

            if (in_array($field, $intFields, true)) {
                // Empty string / null / 0 → set to NULL (un-assign / inherit from parent)
                if ($data[$field] === '' || $data[$field] === null || (int)$data[$field] === 0) {
                    $fields[] = "$field = NULL";
                } else {
                    $fields[] = "$field = ?";
                    $params[] = (int)$data[$field];
                }
            } else {
                $fields[] = "$field = ?";
                $params[]  = isset($data[$field]) ? (trim((string)$data[$field]) === '' ? null : $data[$field]) : null;
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $db->execute(
            'UPDATE subtareas SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL',
            $params
        );

        self::logAudit($data['updated_by'] ?? null, 'SUBTAREA_UPDATE', $id, $data);
        return true;
    }

    /**
     * Reassign a subtarea to a user (or unassign by passing null/0).
     * Returns the new effective row or null if subtarea does not exist.
     */
    public static function assign(int $id, ?int $usuarioAsignadoId, ?int $actorId = null): ?array
    {
        $db = Database::getInstance();

        if ($usuarioAsignadoId !== null && $usuarioAsignadoId > 0) {
            $db->execute(
                'UPDATE subtareas SET usuario_asignado_id = ? WHERE id = ? AND deleted_at IS NULL',
                [$usuarioAsignadoId, $id]
            );
        } else {
            $db->execute(
                'UPDATE subtareas SET usuario_asignado_id = NULL WHERE id = ? AND deleted_at IS NULL',
                [$id]
            );
        }

        self::logAudit($actorId, 'SUBTAREA_ASSIGN', $id, ['usuario_asignado_id' => $usuarioAsignadoId]);

        return self::getById($id);
    }

    public static function updateEstado(int $id, string $estado): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE subtareas SET estado = ? WHERE id = ? AND deleted_at IS NULL',
            [$estado, $id]
        );
    }

    public static function softDelete(int $id, int $actorId): bool
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $db->execute(
                'UPDATE subtareas SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL',
                [$now, $id]
            );
            $db->execute(
                "UPDATE notas SET deleted_at = ? WHERE scope = 'subtarea' AND referencia_id = ? AND deleted_at IS NULL",
                [$now, $id]
            );
            $db->execute(
                "UPDATE evidencias SET deleted_at = ? WHERE tipo_entidad = 'subtarea' AND entidad_id = ? AND deleted_at IS NULL",
                [$now, $id]
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        self::logAudit($actorId, 'SUBTAREA_DELETE', $id, []);
        return true;
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
