<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Subtareas
 *  Archivo: SubtareasController.php
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
use App\Helpers\{CSRF, Validator};
use App\Models\{Subtarea, Tarea, Proyecto, Evidencia};
use App\Services\{EstadoService, NotificacionService};

class SubtareasController extends Controller
{
    public function store(string $tareaId): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user   = $this->currentUser();
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $tarea  = Tarea::getById((int)$tareaId);

        if (!$tarea || !empty($tarea['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Tarea no encontrada.'], 404); return; }
            $this->flash('error', 'Tarea no encontrada.');
            $this->redirect('/proyectos'); return;
        }

        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !empty($proyecto['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'El proyecto asociado no está disponible.'], 403); return; }
            $this->flash('error', 'El proyecto asociado no está disponible.');
            $this->redirect('/proyectos'); return;
        }

        if (!Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'No tienes acceso al proyecto de esta tarea.'], 403); return; }
            $this->flash('error', 'No tienes acceso al proyecto de esta tarea.');
            $this->redirect('/proyectos'); return;
        }

        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $prioridad   = $_POST['prioridad'] ?? 'media';
        $estado      = 'por_hacer'; // Business rule: new subtasks always start as "Por hacer"
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin    = $_POST['fecha_fin'] ?? '';

        $errors = [];
        if (!Validator::required($nombre) || !Validator::minLength($nombre, 3)) {
            $errors[] = 'El nombre de la subtarea es requerido.';
        }
        if (mb_strlen($descripcion) > 2000) {
            $errors[] = 'La descripción no puede superar los 2000 caracteres.';
        }
        if (!Validator::isValidPrioridad($prioridad)) $errors[] = 'Prioridad inválida.';
        if (!Validator::isValidEstado($estado))        $errors[] = 'Estado inválido.';

        if (!empty($errors)) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => implode(' ', $errors)], 422); return; }
            foreach ($errors as $e) $this->flash('error', $e);
            $this->back();
        }

        $subtareaId = Subtarea::create([
            'tarea_id'    => (int)$tareaId,
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'prioridad'   => $prioridad,
            'estado'      => $estado,
            'fecha_inicio'=> $fechaInicio,
            'fecha_fin'   => $fechaFin,
        ], $user['id']);

        // Propagate state up: tarea -> proyecto
        EstadoService::propagarDesdeTareaConSubtareas((int)$tareaId, $user['id']);

        // Notify admins when a USER creates a subtask
        if ($user['rol'] === 'USER') {
            NotificacionService::dispatchToAdmins(NotificacionService::CAMBIO_ESTADO_TAREA, [
                'entity_type' => 'subtarea',
                'entity_id'   => $subtareaId,
                'tarea'       => $nombre,
                'proyecto'    => $proyecto['nombre'],
                'estado'      => 'Por Hacer (nueva)',
                'actor'       => $user['nombre_completo'],
            ]);
        }

        if ($isAjax) {
            $this->json([
                'ok'      => true,
                'subtarea' => [
                    'id'          => $subtareaId,
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion,
                    'prioridad'   => $prioridad,
                    'estado'      => $estado,
                    'fecha_fin'   => $fechaFin,
                    'tarea_id'    => (int)$tareaId,
                ],
            ]);
            return;
        }
        $this->flash('success', "Subtarea \"$nombre\" creada.");
        $this->back();
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $isAjax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $subtarea = Subtarea::getById((int)$id);

        if (!$subtarea) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Subtarea no encontrada.'], 404); return; }
            $this->flash('error', 'Subtarea no encontrada.');
            $this->redirect('/proyectos'); return;
        }

        $tarea = Tarea::getById((int)($subtarea['tarea_id'] ?? 0));
        if (!$tarea || !empty($tarea['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'La tarea asociada no está disponible.'], 403); return; }
            $this->flash('error', 'La tarea asociada no está disponible.');
            $this->redirect('/proyectos'); return;
        }

        $proyecto = Proyecto::getById((int)($tarea['proyecto_id'] ?? 0));
        if (!$proyecto || !empty($proyecto['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'El proyecto asociado no está disponible.'], 403); return; }
            $this->flash('error', 'El proyecto asociado no está disponible.');
            $this->redirect('/proyectos'); return;
        }

        if (!Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Sin permisos suficientes.'], 403); return; }
            $this->flash('error', 'No tienes permisos para esta operacion.');
            $this->redirect('/proyectos'); return;
        }

        // ---- Validation ----
        $errors = [];

        $nombre      = isset($_POST['nombre'])      ? trim($_POST['nombre'])      : null;
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : null;
        $prioridad   = isset($_POST['prioridad'])   ? trim($_POST['prioridad'])   : null;
        $estado      = isset($_POST['estado'])      ? trim($_POST['estado'])      : null;
        $fechaInicio = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : null;
        $fechaFin    = isset($_POST['fecha_fin'])    ? trim($_POST['fecha_fin'])    : null;

        // nombre: required when submitted, 3-200 chars
        if ($nombre !== null) {
            if (!Validator::required($nombre)) {
                $errors[] = 'El nombre de la subtarea es requerido.';
            } elseif (!Validator::minLength($nombre, 3)) {
                $errors[] = 'El nombre debe tener al menos 3 caracteres.';
            } elseif (!Validator::maxLength($nombre, 200)) {
                $errors[] = 'El nombre no puede superar los 200 caracteres.';
            }
        }

        // descripcion: optional, max 2000 chars
        if ($descripcion !== null && $descripcion !== '') {
            if (!Validator::maxLength($descripcion, 2000)) {
                $errors[] = 'La descripcion no puede superar los 2000 caracteres.';
            }
        }

        // prioridad: must be a valid enum value
        if ($prioridad !== null && $prioridad !== '') {
            if (!Validator::isValidPrioridad($prioridad)) {
                $errors[] = 'Prioridad invalida.';
            }
        }

        // estado: must be a valid enum value
        if ($estado !== null && $estado !== '') {
            if (!Validator::isValidEstado($estado)) {
                $errors[] = 'Estado invalido.';
            }
        }

        // fecha_inicio: must be a valid date if provided
        if ($fechaInicio !== null && $fechaInicio !== '') {
            if (!Validator::isDate($fechaInicio)) {
                $errors[] = 'La fecha de inicio no es valida.';
            }
        }

        // fecha_fin: must be a valid date if provided; must not precede fecha_inicio
        if ($fechaFin !== null && $fechaFin !== '') {
            if (!Validator::isDate($fechaFin)) {
                $errors[] = 'La fecha de fin no es valida.';
            } elseif (
                $fechaInicio !== null &&
                $fechaInicio !== '' &&
                Validator::isDate($fechaInicio) &&
                (new \DateTime($fechaFin)) < (new \DateTime($fechaInicio))
            ) {
                $errors[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $e) {
                $this->flash('error', $e);
            }
            $this->back();
        }

        // ---- Build update data from validated fields only ----
        $data    = [];
        $allowed = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin'];
        $posted  = [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion,
            'prioridad'    => $prioridad,
            'estado'       => $estado,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
        ];

        foreach ($allowed as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $posted[$field];
            }
        }

        $data['updated_by'] = $user['id'];
        Subtarea::update((int)$id, $data);

        // If estado changed, propagate up
        if (isset($data['estado'])) {
            EstadoService::propagarDesdeSubtarea((int)$id, $user['id']);
        }

        // Notify admins when a USER updates a subtask
        if ($user['rol'] === 'USER' && isset($data['estado'])) {
            $estadoLabels = [
                'por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada',
                'enterado'  => 'Enterado',  'ocupado'  => 'Ocupado',  'aceptada'  => 'Aceptada',
            ];
            NotificacionService::dispatchToAdmins(NotificacionService::CAMBIO_ESTADO_TAREA, [
                'entity_type' => 'subtarea',
                'entity_id'   => (int)$id,
                'tarea'       => $subtarea['nombre'],
                'proyecto'    => $tarea['nombre'] ?? "tarea #{$subtarea['tarea_id']}",
                'estado'      => $estadoLabels[$data['estado']] ?? $data['estado'],
                'actor'       => $user['nombre_completo'],
            ]);
        }

        $this->flash('success', 'Subtarea actualizada.');
        $this->back();
    }

    public function updateEstado(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $isAjax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $subtarea = Subtarea::getById((int)$id);

        if (!$subtarea) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'No encontrada'], 404); return; }
            $this->redirect('/proyectos'); return;
        }

        $tarea = Tarea::getById((int)($subtarea['tarea_id'] ?? 0));
        if (!$tarea || !empty($tarea['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Acceso denegado: la tarea padre no esta disponible.'], 403); return; }
            $this->flash('error', 'La tarea asociada no esta disponible.');
            $this->redirect('/proyectos'); return;
        }

        $proyecto = Proyecto::getById((int)($tarea['proyecto_id'] ?? 0));
        if (!$proyecto || !empty($proyecto['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Acceso denegado: el proyecto no esta disponible.'], 403); return; }
            $this->flash('error', 'El proyecto asociado no esta disponible.');
            $this->redirect('/proyectos'); return;
        }

        if (!Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Acceso denegado'], 403); return; }
            $this->flash('error', 'No tienes acceso a esta subtarea.');
            $this->redirect('/proyectos'); return;
        }

        // USER can only change estado of subtasks on tasks/projects assigned to them
        if ($user['rol'] === 'USER') {
            $efectivo = (int)($tarea['usuario_asignado_id'] ?? 0) ?: (int)($proyecto['usuario_asignado_id'] ?? 0);
            if ($efectivo !== (int)$user['id']) {
                if ($isAjax) { $this->json(['ok' => false, 'message' => 'No tienes acceso a esta subtarea.'], 403); return; }
                $this->flash('error', 'No tienes acceso a esta subtarea.');
                $this->back(); return;
            }
        }

        $estado = trim($_POST['estado'] ?? '');
        if (!Validator::isValidEstado($estado)) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Estado invalido'], 400); return; }
            $this->back(); return;
        }

        // Require evidence before marking as terminada
        if ($estado === 'terminada' && !Evidencia::tieneEvidencia('subtarea', (int)$id)) {
            if ($isAjax) {
                $this->json(['ok' => false, 'message' => 'Debes adjuntar al menos una evidencia PDF o PNG antes de marcar como terminada.', 'requires_evidencia' => true], 400);
                return;
            }
            $this->flash('error', 'Debes adjuntar al menos una evidencia PDF o PNG antes de marcar como terminada.');
            $this->back();
        }

        $oldEstado = $subtarea['estado'];
        Subtarea::updateEstado((int)$id, $estado);

        // Propagate state up: tarea -> proyecto
        $propagacion = EstadoService::propagarDesdeSubtarea((int)$id, $user['id']);

        // Notify admins when a USER changes subtask status
        if ($user['rol'] === 'USER' && $oldEstado !== $estado) {
            $estadoLabels = [
                'por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada',
                'enterado'  => 'Enterado',  'ocupado'  => 'Ocupado',  'aceptada'  => 'Aceptada',
            ];
            NotificacionService::dispatchToAdmins(NotificacionService::CAMBIO_ESTADO_TAREA, [
                'entity_type' => 'subtarea',
                'entity_id'   => (int)$id,
                'tarea'       => $subtarea['nombre'],
                'proyecto'    => $tarea['nombre'] ?? "tarea #{$subtarea['tarea_id']}",
                'estado'      => $estadoLabels[$estado] ?? $estado,
                'actor'       => $user['nombre_completo'],
            ]);
        }

        if ($isAjax) {
            $this->json([
                'ok'              => true,
                'estado'          => $estado,
                'tarea_id'        => $propagacion['tarea_id'] ?? null,
                'tarea_estado'    => $propagacion['tarea_estado'] ?? null,
                'proyecto_id'     => $propagacion['proyecto_id'] ?? null,
                'proyecto_estado' => $propagacion['proyecto_estado'] ?? null,
            ]);
            return;
        }

        $this->back();
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $isAjax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $subtarea = Subtarea::getById((int)$id);

        if (!$subtarea) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Subtarea no encontrada.'], 404); return; }
            $this->flash('error', 'Subtarea no encontrada.');
            $this->redirect('/proyectos'); return;
        }

        $tarea = Tarea::getById((int)($subtarea['tarea_id'] ?? 0));
        if (!$tarea || !empty($tarea['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'La tarea asociada no esta disponible.'], 403); return; }
            $this->flash('error', 'La tarea asociada no esta disponible.');
            $this->redirect('/proyectos'); return;
        }

        $proyecto = Proyecto::getById((int)($tarea['proyecto_id'] ?? 0));
        if (!$proyecto || !empty($proyecto['deleted_at'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'El proyecto asociado no esta disponible.'], 403); return; }
            $this->flash('error', 'El proyecto asociado no esta disponible.');
            $this->redirect('/proyectos'); return;
        }

        if (!Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            if ($isAjax) { $this->json(['ok' => false, 'message' => 'Sin permisos suficientes.'], 403); return; }
            $this->flash('error', 'No tienes acceso al proyecto de esta subtarea.');
            $this->redirect('/proyectos'); return;
        }

        $tareaId = (int)$subtarea['tarea_id'];
        Subtarea::softDelete((int)$id, $user['id']);

        // Propagate state up after deletion
        EstadoService::propagarDesdeTareaConSubtareas($tareaId, $user['id']);

        $this->flash('success', "Subtarea \"{$subtarea['nombre']}\" eliminada.");
        $this->back();
    }
}
