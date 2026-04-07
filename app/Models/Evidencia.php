<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

class Evidencia
{
    public const TIPOS_PERMITIDOS = ['proyecto', 'tarea', 'subtarea'];
    public const MIME_PERMITIDOS  = ['application/pdf', 'image/png'];
    public const EXT_PERMITIDAS   = ['pdf', 'png'];
    public const MAX_BYTES        = 5 * 1024 * 1024; // 5 MB

    public static function getByEntidad(string $tipo, int $entidadId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT e.id, e.tipo_entidad, e.entidad_id, e.nombre_original, e.nombre_guardado,
                    e.ruta_archivo, e.extension, e.mime_type, e.peso_bytes, e.subido_por,
                    e.created_at, u.nombre_completo AS subido_por_nombre
             FROM evidencias e
             JOIN usuarios u ON u.id = e.subido_por
             WHERE e.tipo_entidad = ? AND e.entidad_id = ? AND e.deleted_at IS NULL
             ORDER BY e.created_at DESC",
            [$tipo, $entidadId]
        );
    }

    /**
     * Batch fetch evidencias indexadas por entidad_id, para evitar N+1.
     * @return array<int, array<int, array>> entidad_id => [evidencia, ...]
     */
    public static function getByEntidades(string $tipo, array $entidadIds): array
    {
        if (empty($entidadIds)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($entidadIds), '?'));
        $rows = $db->fetchAll(
            "SELECT e.id, e.tipo_entidad, e.entidad_id, e.nombre_original, e.nombre_guardado,
                    e.ruta_archivo, e.extension, e.mime_type, e.peso_bytes, e.subido_por,
                    e.created_at, u.nombre_completo AS subido_por_nombre
             FROM evidencias e
             JOIN usuarios u ON u.id = e.subido_por
             WHERE e.tipo_entidad = ? AND e.entidad_id IN ($placeholders) AND e.deleted_at IS NULL
             ORDER BY e.created_at DESC",
            array_merge([$tipo], $entidadIds)
        );

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['entidad_id']][] = $row;
        }
        return $byId;
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT e.id, e.tipo_entidad, e.entidad_id, e.nombre_original, e.nombre_guardado,
                    e.ruta_archivo, e.extension, e.mime_type, e.peso_bytes, e.subido_por,
                    e.created_at, u.nombre_completo AS subido_por_nombre
             FROM evidencias e
             JOIN usuarios u ON u.id = e.subido_por
             WHERE e.id = ? AND e.deleted_at IS NULL",
            [$id]
        );
    }

    public static function tieneEvidencia(string $tipo, int $entidadId): bool
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT COUNT(*)::int AS total FROM evidencias WHERE tipo_entidad = ? AND entidad_id = ? AND deleted_at IS NULL",
            [$tipo, $entidadId]
        );
        return ($row['total'] ?? 0) > 0;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "INSERT INTO evidencias (tipo_entidad, entidad_id, nombre_original, nombre_guardado, ruta_archivo, extension, mime_type, peso_bytes, subido_por)
             VALUES (?,?,?,?,?,?,?,?,?) RETURNING id",
            [
                $data['tipo_entidad'],
                $data['entidad_id'],
                $data['nombre_original'],
                $data['nombre_guardado'],
                $data['ruta_archivo'],
                $data['extension'],
                $data['mime_type'],
                $data['peso_bytes'],
                $data['subido_por'],
            ]
        );
        $id = (int)$stmt->fetchColumn();
        // Audit log
        try {
            $db->execute(
                "INSERT INTO audit_logs (actor_id, action, target_id, details) VALUES (?,?,?,?)",
                [$data['subido_por'], 'EVIDENCIA_UPLOAD', $id, json_encode(['tipo_entidad'=>$data['tipo_entidad'],'entidad_id'=>$data['entidad_id'],'nombre'=>$data['nombre_original']])]
            );
        } catch (\Throwable $e) { /* swallow audit errors */ }
        return $id;
    }

    public static function softDelete(int $id, int $actorId): bool
    {
        $db = Database::getInstance();
        $ok = $db->execute(
            "UPDATE evidencias SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
        try {
            $db->execute(
                "INSERT INTO audit_logs (actor_id, action, target_id, details) VALUES (?,?,?,?)",
                [$actorId, 'EVIDENCIA_DELETE', $id, json_encode([])]
            );
        } catch (\Throwable $e) {}
        return $ok;
    }

    /**
     * Validate uploaded file. Returns ['ok'=>true] or ['ok'=>false, 'error'=>'...']
     */
    public static function validateFile(array $file): array
    {
        if (!isset($file['tmp_name']) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK) {
            $msg = match($file['error'] ?? -1) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamano maximo permitido (5 MB).',
                UPLOAD_ERR_NO_FILE => 'No se selecciono ningun archivo.',
                default => 'Error al subir el archivo.',
            };
            return ['ok' => false, 'error' => $msg];
        }

        if ($file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'El archivo supera el tamano maximo permitido (5 MB).'];
        }

        // Validate real MIME via finfo (not just extension)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);
        if (!in_array($realMime, self::MIME_PERMITIDOS, true)) {
            return ['ok' => false, 'error' => 'Solo se permiten archivos PDF y PNG. El archivo detectado es: ' . $realMime];
        }

        // Also validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT_PERMITIDAS, true)) {
            return ['ok' => false, 'error' => 'Extension no permitida. Solo .pdf y .png'];
        }

        // Cross-check mime vs extension to prevent spoofing
        $mimeExtMap = ['application/pdf' => 'pdf', 'image/png' => 'png'];
        if (($mimeExtMap[$realMime] ?? '') !== $ext) {
            return ['ok' => false, 'error' => 'El tipo de archivo no coincide con su extension.'];
        }

        return ['ok' => true, 'mime' => $realMime, 'ext' => $ext];
    }

    /**
     * Generate safe unique filename
     */
    public static function generateFilename(string $originalName, string $ext): string
    {
        $hash = bin2hex(random_bytes(16));
        $timestamp = date('YmdHis');
        return $timestamp . '_' . $hash . '.' . $ext;
    }

    /**
     * Get storage path for entity
     */
    public static function getStorageDir(string $tipo, int $entidadId): string
    {
        return BASE_PATH . '/storage/evidencias/' . $tipo . 's/' . $entidadId . '/';
    }
}
