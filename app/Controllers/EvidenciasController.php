<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Evidencias
 *  Archivo: EvidenciasController.php
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

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CSRF;
use App\Models\{Evidencia, Proyecto, Tarea, Subtarea};

class EvidenciasController extends Controller
{
    /**
     * POST /evidencias/subir
     * Upload evidence file for an entity.
     */
    public function upload(): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user        = $this->currentUser();
        $tipoEntidad = trim($_POST['tipo_entidad'] ?? '');
        $entidadId   = (int)($_POST['entidad_id'] ?? 0);

        // Validate tipo_entidad
        if (!in_array($tipoEntidad, Evidencia::TIPOS_PERMITIDOS, true)) {
            $this->json(['ok' => false, 'error' => 'Tipo de entidad invalido.'], 400);
        }

        if ($entidadId <= 0) {
            $this->json(['ok' => false, 'error' => 'ID de entidad invalido.'], 400);
        }

        // Validate entity exists and user has access
        if (!$this->checkEntityAccess($tipoEntidad, $entidadId, $user)) {
            $this->json(['ok' => false, 'error' => 'No tienes acceso a esta entidad.'], 403);
        }

        // Validate file
        $file = $_FILES['archivo'] ?? [];
        $validation = Evidencia::validateFile($file);
        if (!$validation['ok']) {
            $this->json(['ok' => false, 'error' => $validation['error']], 400);
        }

        // Prepare storage directory
        $storageDir = Evidencia::getStorageDir($tipoEntidad, $entidadId);
        if (!is_dir($storageDir)) {
            if (!mkdir($storageDir, 0755, true)) {
                $this->json(['ok' => false, 'error' => 'Error al crear directorio de almacenamiento.'], 500);
            }
        }

        // Generate safe filename
        $ext            = $validation['ext'];
        $mime           = $validation['mime'];
        $nombreGuardado = Evidencia::generateFilename($file['name'], $ext);
        $rutaArchivo    = $storageDir . $nombreGuardado;
        $tmpPath        = $file['tmp_name'];

        // DB primero, archivo después — si el archivo falla hacemos rollback
        try {
            $db = \App\Core\Database::getInstance();
            $db->beginTransaction();

            $evidenciaId = Evidencia::create([
                'tipo_entidad'    => $tipoEntidad,
                'entidad_id'      => $entidadId,
                'nombre_original' => $file['name'],
                'nombre_guardado' => $nombreGuardado,
                'ruta_archivo'    => $rutaArchivo,
                'extension'       => $ext,
                'mime_type'       => $mime,
                'peso_bytes'      => $file['size'],
                'subido_por'      => $user['id'],
            ]);

            if (!move_uploaded_file($tmpPath, $rutaArchivo)) {
                throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
            }

            $db->commit();
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            // Si el archivo ya fue movido en un caso límite, limpiarlo
            if (isset($rutaArchivo) && file_exists($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
            error_log('[EvidenciasController::upload] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error al subir la evidencia. Inténtalo de nuevo.'], 500);
        }

        $evidencia = Evidencia::getById($evidenciaId);

        $this->json([
            'ok'        => true,
            'evidencia' => [
                'id'              => $evidenciaId,
                'nombre_original' => $file['name'],
                'extension'       => $ext,
                'peso_bytes'      => $file['size'],
                'subido_por_nombre' => $user['nombre_completo'],
                'created_at'      => $evidencia['created_at'] ?? date('c'),
            ],
        ]);
    }

    /**
     * GET /evidencias/{id}/descargar
     * Download evidence file.
     */
    public function download(string $id): void
    {
        $this->requireAuth();

        $user      = $this->currentUser();
        $evidencia = Evidencia::getById((int)$id);

        if (!$evidencia) {
            http_response_code(404);
            echo 'Evidencia no encontrada.';
            exit;
        }

        // Verify access to the related entity
        if (!$this->checkEntityAccess($evidencia['tipo_entidad'], (int)$evidencia['entidad_id'], $user)) {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }

        $filePath = $evidencia['ruta_archivo'];
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'Archivo no encontrado en el servidor.';
            exit;
        }

        // Send file
        header('Content-Type: ' . $evidencia['mime_type']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '_', $evidencia['nombre_original']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        exit;
    }

    /**
     * POST /evidencias/{id}/eliminar
     * Soft-delete evidence. Only GOD/ADMIN. Cannot delete if entity is terminada.
     */
    public function destroy(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user = $this->currentUser();

        // Only GOD or ADMIN can delete
        if (!in_array($user['rol'], ['GOD', 'ADMIN'], true)) {
            $this->json(['ok' => false, 'error' => 'No tienes permiso para eliminar evidencias.'], 403);
        }

        $evidencia = Evidencia::getById((int)$id);
        if (!$evidencia) {
            $this->json(['ok' => false, 'error' => 'Evidencia no encontrada.'], 404);
        }

        // Check entity is not terminada
        $entityEstado = $this->getEntityEstado($evidencia['tipo_entidad'], (int)$evidencia['entidad_id']);
        if ($entityEstado === 'terminada') {
            $this->json(['ok' => false, 'error' => 'No se pueden eliminar evidencias de entidades terminadas.'], 400);
        }

        Evidencia::softDelete((int)$id, (int)$user['id']);

        $this->json(['ok' => true]);
    }

    /**
     * GET /evidencias/entidad?tipo=tarea&id=5
     * List evidences for an entity.
     */
    public function listByEntidad(): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        $tipo = trim($_GET['tipo'] ?? '');
        $id   = (int)($_GET['id'] ?? 0);

        if (!in_array($tipo, Evidencia::TIPOS_PERMITIDOS, true) || $id <= 0) {
            $this->json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
        }

        if (!$this->checkEntityAccess($tipo, $id, $user)) {
            $this->json(['ok' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $evidencias = Evidencia::getByEntidad($tipo, $id);

        $this->json([
            'ok'         => true,
            'evidencias' => array_map(function ($ev) {
                return [
                    'id'                => (int)$ev['id'],
                    'nombre_original'   => $ev['nombre_original'],
                    'extension'         => $ev['extension'],
                    'peso_bytes'        => (int)$ev['peso_bytes'],
                    'subido_por_nombre' => $ev['subido_por_nombre'],
                    'created_at'        => $ev['created_at'],
                ];
            }, $evidencias),
            'total'      => count($evidencias),
        ]);
    }

    // ---- Private helpers ----

    /**
     * Check if user has access to the given entity.
     */
    private function checkEntityAccess(string $tipo, int $entidadId, array $user): bool
    {
        switch ($tipo) {
            case 'proyecto':
                $proyecto = Proyecto::getById($entidadId);
                if (!$proyecto) return false;
                return Proyecto::checkAccess($proyecto, $user['id'], $user['rol']);

            case 'tarea':
                $tarea = Tarea::getById($entidadId);
                if (!$tarea) return false;
                $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
                if (!$proyecto) return false;
                return Proyecto::checkAccess($proyecto, $user['id'], $user['rol']);

            case 'subtarea':
                $subtarea = Subtarea::getById($entidadId);
                if (!$subtarea) return false;
                $tarea = Tarea::getById((int)$subtarea['tarea_id']);
                if (!$tarea) return false;
                $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
                if (!$proyecto) return false;
                return Proyecto::checkAccess($proyecto, $user['id'], $user['rol']);

            default:
                return false;
        }
    }

    /**
     * Get the current estado of an entity.
     */
    private function getEntityEstado(string $tipo, int $entidadId): ?string
    {
        switch ($tipo) {
            case 'proyecto':
                $entity = Proyecto::getById($entidadId);
                return $entity['estado'] ?? null;
            case 'tarea':
                $entity = Tarea::getById($entidadId);
                return $entity['estado'] ?? null;
            case 'subtarea':
                $entity = Subtarea::getById($entidadId);
                return $entity['estado'] ?? null;
            default:
                return null;
        }
    }
}
