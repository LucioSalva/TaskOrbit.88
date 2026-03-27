<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Gestión de Tareas
 *  Archivo: TareasController.php
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
use App\Models\{Proyecto, Tarea, Subtarea, Usuario, Notificacion, Nota, Evidencia};
use App\Services\{EstadoService, NotificacionService, SemaforoService};

class TareasController extends Controller
{
    public function index(string $proyectoId): void
    {
        $this->requireAuth();
        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$proyectoId);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $tareas = Tarea::getByProyecto((int)$proyectoId, $user['rol'], $user['id']);

        // Attach subtareas to each task
        foreach ($tareas as &$tarea) {
            $tarea['subtareas'] = Subtarea::getByTarea((int)$tarea['id']);
        }
        unset($tarea);

        // Attach semáforo to each tarea
        SemaforoService::attachToAll($tareas);

        $usuarios = ($user['rol'] !== 'USER') ? Usuario::getAssignableUsers() : [];

        // Group by estado
        $tarByEstado = [];
        $estadosKanban = ['por_hacer','haciendo','enterado','ocupado','terminada','aceptada'];
        foreach ($estadosKanban as $e) $tarByEstado[$e] = [];
        foreach ($tareas as $t) {
            $e = $t['estado'] ?? 'por_hacer';
            $tarByEstado[$e][] = $t;
        }

        // Group by usuario
        $tarByUsuario = [];
        foreach ($tareas as $t) {
            $key = $t['usuario_asignado_nombre'] ?? 'Sin asignar';
            if (!isset($tarByUsuario[$key])) {
                $tarByUsuario[$key] = ['nombre' => $key, 'items' => []];
            }
            $tarByUsuario[$key]['items'][] = $t;
        }
        ksort($tarByUsuario);
        if (isset($tarByUsuario['Sin asignar'])) {
            $sa = $tarByUsuario['Sin asignar'];
            unset($tarByUsuario['Sin asignar']);
            $tarByUsuario['Sin asignar'] = $sa;
        }

        // Timeline sorted
        $tarTimeline = $tareas;
        usort($tarTimeline, function($a, $b) {
            $af = $a['fecha_fin'] ?? null;
            $bf = $b['fecha_fin'] ?? null;
            if ($af && $bf) return strcmp($af, $bf);
            if ($af) return -1;
            if ($bf) return 1;
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        $this->view('tareas/index', [
            'flash'        => $this->getFlash(),
            'proyecto'     => $proyecto,
            'tareas'       => $tareas,
            'usuarios'     => $usuarios,
            'tarByEstado'  => $tarByEstado,
            'tarByUsuario' => $tarByUsuario,
            'tarTimeline'  => $tarTimeline,
        ]);
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        if (!ctype_digit($id)) {
            $this->flash('error', 'ID de tarea inválido.');
            $this->redirect('/proyectos');
        }

        $tarea = Tarea::getById((int)$id);

        if (!$tarea) {
            $this->flash('error', 'Tarea no encontrada.');
            $this->redirect('/proyectos');
        }

        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'No tienes acceso a esta tarea.');
            $this->redirect('/proyectos');
        }

        try {
            $subtareas = Subtarea::getByTarea((int)$id);
            $tarea['semaforo'] = SemaforoService::calcular($tarea);
            $tarea['subtareas'] = $subtareas;
            $notas = Nota::getByScope('tarea', (int)$id);
        } catch (\Throwable $e) {
            error_log('[TareasController::show] Error loading task data id=' . $id . ': ' . $e->getMessage());
            $subtareas = [];
            $tarea['subtareas'] = [];
            $notas = [];
            $tarea['semaforo'] = 'neutral';
        }

        $this->view('tareas/show', [
            'flash'    => $this->getFlash(),
            'tarea'    => $tarea,
            'proyecto' => $proyecto,
            'notas'    => $notas,
        ]);
    }

    public function create(string $proyectoId): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$proyectoId);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $usuarios = Usuario::getAssignableUsers();
        $this->view('tareas/create', [
            'flash'    => $this->getFlash(),
            'proyecto' => $proyecto,
            'usuarios' => $usuarios,
        ]);
    }

    public function store(string $proyectoId): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$proyectoId);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $errors = [];
        $nombre            = trim($_POST['nombre'] ?? '');
        $descripcion       = trim($_POST['descripcion'] ?? '');
        $prioridad         = $_POST['prioridad'] ?? 'media';
        $estado            = 'por_hacer'; // Business rule: new tasks always start as "Por hacer"
        $fechaInicio       = $_POST['fecha_inicio'] ?? '';
        $fechaFin          = $_POST['fecha_fin'] ?? '';
        $usuarioAsignadoId = (int)($_POST['usuario_asignado_id'] ?? 0) ?: null;

        if (!Validator::required($nombre) || !Validator::minLength($nombre, 3)) {
            $errors[] = 'El nombre es requerido (mínimo 3 caracteres).';
        }
        if (mb_strlen($descripcion) > 2000) {
            $errors[] = 'La descripción no puede superar los 2000 caracteres.';
        }
        if (!Validator::isValidPrioridad($prioridad)) $errors[] = 'Prioridad inválida.';
        if (!Validator::isValidEstado($estado))       $errors[] = 'Estado inválido.';
        if ($fechaInicio && $fechaFin && strtotime($fechaFin) <= strtotime($fechaInicio)) {
            $errors[] = 'Rango de fechas inválido.';
        }

        // Validate task dates within project dates using DateTime for correct comparison
        $dtInicio  = !empty($fechaInicio)              ? new \DateTime($fechaInicio)              : null;
        $dtFin     = !empty($fechaFin)                 ? new \DateTime($fechaFin)                 : null;
        $dtProjIni = !empty($proyecto['fecha_inicio']) ? new \DateTime($proyecto['fecha_inicio']) : null;
        $dtProjFin = !empty($proyecto['fecha_fin'])    ? new \DateTime($proyecto['fecha_fin'])    : null;

        if ($dtInicio && $dtProjIni && $dtInicio < $dtProjIni) {
            $errors[] = 'La fecha de inicio no puede ser anterior al inicio del proyecto (' . $proyecto['fecha_inicio'] . ').';
        }
        if ($dtFin && $dtProjFin && $dtFin > $dtProjFin) {
            $errors[] = 'La fecha de fin no puede ser posterior al fin del proyecto (' . $proyecto['fecha_fin'] . ').';
        }

        if ($usuarioAsignadoId) {
            $role = Usuario::getRoleById($usuarioAsignadoId);
            if (!$role) $errors[] = 'Usuario asignado no existe.';
            elseif ($role === 'GOD') $errors[] = 'No se puede asignar tareas a GOD.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect("/proyectos/$proyectoId/tareas/crear");
        }

        $tareaId = Tarea::create([
            'proyecto_id'        => (int)$proyectoId,
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'prioridad'          => $prioridad,
            'estado'             => $estado,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'usuario_asignado_id'=> $usuarioAsignadoId,
        ], $user['id']);

        // Notificación centralizada de asignación
        $effectiveUserId = $usuarioAsignadoId ?? (int)$proyecto['usuario_asignado_id'];
        if ($effectiveUserId) {
            $assignedUser = Usuario::findById($effectiveUserId);
            NotificacionService::dispatch(NotificacionService::TAREA_ASIGNADA, [
                'entity_type' => 'tarea',
                'entity_id'   => $tareaId,
                'user_id'     => $effectiveUserId,
                'user_nombre' => $assignedUser['nombre_completo'] ?? '',
                'tarea'       => $nombre,
                'proyecto'    => $proyecto['nombre'],
                'fecha_fin'   => $fechaFin ? date('d/m/Y', strtotime($fechaFin)) : 'Sin fecha',
                'actor'       => $user['nombre_completo'],
            ]);
        }

        // Notificar a admins si un USER crea tarea
        if ($user['rol'] === 'USER') {
            NotificacionService::dispatchToAdmins(NotificacionService::CAMBIO_ESTADO_TAREA, [
                'entity_type' => 'tarea',
                'entity_id'   => $tareaId,
                'tarea'       => $nombre,
                'proyecto'    => $proyecto['nombre'],
                'estado'      => 'Por Hacer (nueva)',
                'actor'       => $user['nombre_completo'],
            ]);
        }

        // Auto-note: tarea created
        $asignadoNombre = '';
        if ($usuarioAsignadoId) {
            $au = Usuario::findById($usuarioAsignadoId);
            $asignadoNombre = $au['nombre_completo'] ?? '';
        }
        $autoMsg = "Tarea creada por {$user['nombre_completo']}.";
        if ($asignadoNombre) $autoMsg .= " Asignada a: $asignadoNombre.";
        if ($fechaFin) $autoMsg .= ' Fecha límite: ' . date('d/m/Y', strtotime($fechaFin)) . '.';
        Nota::createAuto('tarea', $tareaId, $autoMsg, $user['id']);

        $this->flash('success', "Tarea \"$nombre\" creada exitosamente.");
        $this->redirect("/proyectos/$proyectoId/tareas");
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        $user  = $this->currentUser();
        $tarea = Tarea::getById((int)$id);

        if (!$tarea) {
            $this->flash('error', 'Tarea no encontrada.');
            $this->redirect('/proyectos');
        }

        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Acceso denegado.');
            $this->redirect('/proyectos');
        }

        $usuarios = Usuario::getAssignableUsers();
        $this->view('tareas/edit', [
            'flash'    => $this->getFlash(),
            'tarea'    => $tarea,
            'proyecto' => $proyecto,
            'usuarios' => $usuarios,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user  = $this->currentUser();
        $tarea = Tarea::getById((int)$id);

        if (!$tarea) {
            $this->flash('error', 'Tarea no encontrada.');
            $this->redirect('/proyectos');
        }

        // Validate project access for all roles
        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'No tienes acceso a esta tarea.');
            $this->redirect('/proyectos');
        }

        $data = [];

        if ($user['rol'] === 'USER') {
            // USER can only update estado
            $efectivoAsignado = (int)($tarea['usuario_asignado_id'] ?? 0);
            if ($efectivoAsignado !== $user['id']) {
                $this->flash('error', 'No tienes permiso para modificar esta tarea.');
                $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
            }
            $estado = $_POST['estado'] ?? '';
            if (!Validator::isValidEstado($estado)) {
                $this->flash('error', 'Estado inválido.');
                $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
            }
            $data['estado'] = $estado;
        } else {
            $allowed = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin', 'usuario_asignado_id'];
            foreach ($allowed as $field) {
                if (isset($_POST[$field])) {
                    $data[$field] = trim($_POST[$field]);
                }
            }

            // Validate fields for ADMIN/GOD update
            $errors = [];
            if (isset($data['nombre']) && (!Validator::required($data['nombre']) || !Validator::minLength($data['nombre'], 3))) {
                $errors[] = 'El nombre es requerido (mínimo 3 caracteres).';
            }
            if (isset($data['descripcion']) && mb_strlen($data['descripcion']) > 2000) {
                $errors[] = 'La descripción no puede superar los 2000 caracteres.';
            }
            if (isset($data['prioridad']) && $data['prioridad'] !== '' && !Validator::isValidPrioridad($data['prioridad'])) {
                $errors[] = 'Prioridad inválida.';
            }
            if (isset($data['estado']) && $data['estado'] !== '' && !Validator::isValidEstado($data['estado'])) {
                $errors[] = 'Estado inválido.';
            }
            if (!empty($errors)) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    $this->json(['ok' => false, 'message' => implode(' ', $errors)], 422);
                }
                foreach ($errors as $e) $this->flash('error', $e);
                $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
            }
        }

        $data['updated_by'] = $user['id'];
        Tarea::update((int)$id, $data);

        // If estado changed, propagate up to proyecto
        if (isset($data['estado'])) {
            EstadoService::propagarDesdeTarea((int)$id, $user['id']);
        }

        // Notify admins when a USER updates a task
        if ($user['rol'] === 'USER') {
            $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
            $proyNombre = $proyecto['nombre'] ?? "#{$tarea['proyecto_id']}";
            Notificacion::notifyAdmins([
                'type'        => 'user_tarea_actualizada',
                'title'       => 'Tarea actualizada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} actualizó la tarea \"{$tarea['nombre']}\" en el proyecto \"$proyNombre\".",
                'severity'    => 'info',
                'entity_type' => 'tarea',
                'entity_id'   => (int)$id,
            ]);
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // Fetch refreshed tarea for the response
            $updatedTarea = Tarea::getById((int)$id);
            $this->json([
                'ok'    => true,
                'tarea' => [
                    'id'                     => (int)$id,
                    'nombre'                 => $updatedTarea['nombre'] ?? '',
                    'descripcion'            => $updatedTarea['descripcion'] ?? '',
                    'prioridad'              => $updatedTarea['prioridad'] ?? '',
                    'fecha_fin'              => $updatedTarea['fecha_fin'] ?? '',
                    'usuario_asignado_id'    => $updatedTarea['usuario_asignado_id'] ?? null,
                    'usuario_asignado_nombre'=> $updatedTarea['usuario_asignado_nombre'] ?? '',
                ],
            ]);
        }
        $this->flash('success', 'Tarea actualizada correctamente.');
        $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
    }

    public function updateEstado(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user  = $this->currentUser();
        $tarea = Tarea::getById((int)$id);

        if (!$tarea) {
            $this->json(['ok' => false, 'message' => 'No encontrada'], 404);
        }

        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->json(['ok' => false, 'message' => 'Acceso denegado'], 403);
        }

        // GOD can always change any task status.
        // ADMIN and USER can only change status if assigned to the task.
        if ($user['rol'] !== 'GOD') {
            if ((int)($tarea['usuario_asignado_id'] ?? 0) !== (int)$user['id']) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    $this->json(['ok' => false, 'message' => 'No tienes acceso a esta tarea.'], 403);
                }
                $this->flash('error', 'No tienes acceso a esta tarea.');
                $this->back();
            }
        }

        $estado = trim($_POST['estado'] ?? '');
        if (!Validator::isValidEstado($estado)) {
            $this->json(['ok' => false, 'message' => 'Estado inválido'], 400);
        }

        // Require evidence before marking as terminada
        if ($estado === 'terminada' && !Evidencia::tieneEvidencia('tarea', (int)$id)) {
            $this->json(['ok' => false, 'message' => 'Debes adjuntar al menos una evidencia PDF o PNG antes de marcar como terminada.', 'requires_evidencia' => true], 400);
        }

        Tarea::updateEstado((int)$id, $estado, $user['id']);

        // Propagate state up to proyecto
        $propagacion = EstadoService::propagarDesdeTarea((int)$id, $user['id']);

        // Auto-note: estado change
        if ($tarea['estado'] !== $estado) {
            $estadoLabels = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada',
                             'enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
            $oldLbl = $estadoLabels[$tarea['estado']] ?? $tarea['estado'];
            $newLbl = $estadoLabels[$estado] ?? $estado;
            Nota::createAuto('tarea', (int)$id,
                "Estado cambiado de \"$oldLbl\" a \"$newLbl\" por {$user['nombre_completo']}.",
                $user['id']
            );
        }

        // Notificaciones centralizadas por cambio de estado
        if ($tarea['estado'] !== $estado) {
            $estadoLabels = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada',
                             'enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
            $efectivoUserId = (int)($tarea['usuario_asignado_id'] ?? 0);
            $baseCtx = [
                'entity_type' => 'tarea',
                'entity_id'   => (int)$id,
                'tarea'       => $tarea['nombre'],
                'proyecto'    => $proyecto['nombre'],
                'estado'      => $estadoLabels[$estado] ?? $estado,
                'actor'       => $user['nombre_completo'],
                'fecha_fin'   => $tarea['fecha_fin'] ? date('d/m/Y', strtotime($tarea['fecha_fin'])) : 'Sin fecha',
            ];

            // Notificar al asignado del nuevo estado
            if ($efectivoUserId) {
                $assignedUser = Usuario::findById($efectivoUserId);
                $event = ($estado === 'aceptada') ? NotificacionService::TAREA_ACEPTADA
                       : (($estado === 'terminada') ? NotificacionService::TAREA_TERMINADA
                       : NotificacionService::CAMBIO_ESTADO_TAREA);
                NotificacionService::dispatch($event, array_merge($baseCtx, [
                    'user_id'     => $efectivoUserId,
                    'user_nombre' => $assignedUser['nombre_completo'] ?? '',
                    'nombre'      => $assignedUser['nombre_completo'] ?? '',
                ]));
            }

            // Si un USER cambia estado, notificar a admins también
            if ($user['rol'] === 'USER') {
                NotificacionService::dispatchToAdmins(NotificacionService::CAMBIO_ESTADO_TAREA, $baseCtx);
            }
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json([
                'ok'             => true,
                'estado'         => $estado,
                'proyecto_id'    => $propagacion['proyecto_id'] ?? null,
                'proyecto_estado'=> $propagacion['proyecto_estado'] ?? null,
            ]);
        }

        $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user  = $this->currentUser();
        $tarea = Tarea::getById((int)$id);

        if (!$tarea) {
            $this->flash('error', 'Tarea no encontrada.');
            $this->redirect('/proyectos');
        }

        $proyectoId = $tarea['proyecto_id'];
        $proyecto = Proyecto::getById((int)$proyectoId);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'No tienes acceso a esta tarea.');
            $this->back();
        }

        $reason     = trim($_POST['reason'] ?? '');
        Tarea::softDelete((int)$id, $user['id'], $reason);

        $this->flash('success', "Tarea \"{$tarea['nombre']}\" eliminada.");
        $this->redirect("/proyectos/$proyectoId/tareas");
    }
}
