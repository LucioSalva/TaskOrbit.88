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
            'SELECT * FROM vw_subtareas WHERE tarea_id = ? ORDER BY created_at ASC',
            [$tareaId]
        );
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT * FROM vw_subtareas WHERE id = ?',
            [$id]
        );
    }

    public static function create(array $data, int $createdBy): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            'INSERT INTO subtareas (tarea_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, created_by)
             VALUES (?,?,?,?,?,?,?,?) RETURNING id',
            [
                $data['tarea_id'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['prioridad'] ?? 'media',
                $data['estado'] ?? 'por_hacer',
                $data['fecha_inicio'] ?: null,
                $data['fecha_fin'] ?: null,
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

        $allowed = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
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
        $db->execute('UPDATE subtareas SET deleted_at = ? WHERE id = ?', [$now, $id]);
        $db->execute(
            "UPDATE notas SET deleted_at = ? WHERE scope = 'subtarea' AND referencia_id = ? AND deleted_at IS NULL",
            [$now, $id]
        );
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
