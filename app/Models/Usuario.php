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
 *  Archivo: Usuario.php
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

class Usuario
{
    public static function findByUsername(string $username): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT u.id, u.username, u.password_hash, u.nombre_completo,
                    UPPER(r.nombre) AS rol, u.activo,
                    COALESCE(u.telefono, \'\') AS telefono
             FROM usuarios u
             JOIN usuarios_roles ur ON ur.usuario_id = u.id
             JOIN roles r           ON r.id = ur.rol_id
             WHERE u.username = ?
             LIMIT 1',
            [$username]
        );
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT u.id, u.username, u.nombre_completo, u.telefono, u.activo, r.nombre AS rol
             FROM usuarios u
             JOIN usuarios_roles ur ON ur.usuario_id = u.id
             JOIN roles r ON r.id = ur.rol_id
             WHERE u.id = ?',
            [$id]
        );
    }

    public static function getAll(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll('SELECT * FROM vw_usuarios_roles ORDER BY id DESC');
    }

    public static function getAllActive(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT id, username, nombre_completo, rol
             FROM vw_usuarios_roles
             WHERE activo = TRUE AND rol != 'GOD'
             ORDER BY nombre_completo ASC"
        );
    }

    public static function getAssignableUsers(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT u.id, u.username, u.nombre_completo, r.nombre AS rol
             FROM usuarios u
             JOIN usuarios_roles ur ON ur.usuario_id = u.id
             JOIN roles r ON r.id = ur.rol_id
             WHERE u.activo = TRUE AND r.nombre != 'GOD'
             ORDER BY u.nombre_completo ASC"
        );
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->query(
                'INSERT INTO usuarios (nombre_completo, username, telefono, password_hash, activo)
                 VALUES (?, ?, ?, ?, ?) RETURNING id',
                [
                    $data['nombre_completo'],
                    $data['username'],
                    $data['telefono'] ?? null,
                    $hash,
                    isset($data['activo']) ? (bool)$data['activo'] : true,
                ]
            );
            $userId = (int)$stmt->fetchColumn();

            $roleRow = $db->fetchOne('SELECT id FROM roles WHERE nombre = ?', [$data['rol']]);
            if (!$roleRow) throw new \RuntimeException('Rol no encontrado');

            $db->execute(
                'INSERT INTO usuarios_roles (usuario_id, rol_id) VALUES (?, ?)',
                [$userId, $roleRow['id']]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        self::logAudit($data['created_by'] ?? null, 'CREATE_USER', $userId, [
            'username' => $data['username'],
            'rol'      => $data['rol'],
        ]);
        return $userId;
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $fields = [
                'nombre_completo = ?',
                'username = ?',
                'telefono = ?',
                'activo = ?',
            ];
            $params = [
                $data['nombre_completo'],
                $data['username'],
                $data['telefono'] ?? null,
                isset($data['activo']) ? (bool)$data['activo'] : true,
            ];

            if (!empty($data['password'])) {
                $fields[] = 'password_hash = ?';
                $params[]  = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $params[] = $id;

            $db->execute(
                'UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ?',
                $params
            );

            if (!empty($data['rol'])) {
                $roleRow = $db->fetchOne('SELECT id FROM roles WHERE nombre = ?', [$data['rol']]);
                if ($roleRow) {
                    $db->execute(
                        'UPDATE usuarios_roles SET rol_id = ? WHERE usuario_id = ?',
                        [$roleRow['id'], $id]
                    );
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $auditData = array_diff_key($data, array_flip(['password', 'password_hash']));
        self::logAudit($data['updated_by'] ?? null, 'UPDATE_USER', $id, $auditData);
        return true;
    }

    public static function toggleEstado(int $id, bool $activo): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE usuarios SET activo = ? WHERE id = ?',
            [$activo, $id]
        );
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM usuarios WHERE id = ?', [$id]);
    }

    public static function usernameExists(string $username, int $excludeId = 0): bool
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id FROM usuarios WHERE username = ? AND id != ?',
            [$username, $excludeId]
        );
        return $row !== null;
    }

    public static function getRoleById(int $id): ?string
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT r.nombre AS rol
             FROM usuarios_roles ur
             JOIN roles r ON r.id = ur.rol_id
             WHERE ur.usuario_id = ?
             ORDER BY CASE
               WHEN UPPER(r.nombre) LIKE '%GOD%' THEN 1
               WHEN UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR') THEN 2
               ELSE 3
             END LIMIT 1",
            [$id]
        );
        return $row ? strtoupper($row['rol']) : null;
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
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
        } catch (\Throwable $e) {
            // Audit failures should not break main flow
        }
    }
}
