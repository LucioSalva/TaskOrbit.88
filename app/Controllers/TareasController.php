<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\{CSRF, Validator};
use App\Models\{Proyecto, Tarea, Subtarea, Usuario, Notificacion};
use App\Services\WhatsAppService;

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

        $usuarios = ($user['rol'] !== 'USER') ? Usuario::getAssignableUsers() : [];

        $this->view('tareas/index', [
            'flash'     => $this->getFlash(),
            'proyecto'  => $proyecto,
            'tareas'    => $tareas,
            'usuarios'  => $usuarios,
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
        if (!Validator::isValidPrioridad($prioridad)) $errors[] = 'Prioridad inválida.';
        if (!Validator::isValidEstado($estado))       $errors[] = 'Estado inválido.';
        if ($fechaInicio && $fechaFin && strtotime($fechaFin) <= strtotime($fechaInicio)) {
            $errors[] = 'Rango de fechas inválido.';
        }

        // Validate task dates within project dates
        if ($fechaInicio && $proyecto['fecha_inicio'] && $fechaInicio < $proyecto['fecha_inicio']) {
            $errors[] = 'La fecha de inicio no puede ser anterior al inicio del proyecto.';
        }
        if ($fechaFin && $proyecto['fecha_fin'] && $fechaFin > $proyecto['fecha_fin']) {
            $errors[] = 'La fecha de fin no puede ser posterior al fin del proyecto.';
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

        // Notification + WhatsApp
        $effectiveUserId = $usuarioAsignadoId ?? (int)$proyecto['usuario_asignado_id'];
        if ($effectiveUserId) {
            Notificacion::create([
                'user_id'     => $effectiveUserId,
                'type'        => 'asignacion_tarea',
                'title'       => 'Nueva tarea asignada',
                'message'     => "Se te asignó la tarea \"$nombre\" en el proyecto \"{$proyecto['nombre']}\".",
                'severity'    => 'info',
                'channel'     => 'in_app',
                'entity_type' => 'tarea',
                'entity_id'   => $tareaId,
            ]);

            $assignedUser = Usuario::findById($effectiveUserId);
            if ($assignedUser && $assignedUser['telefono']) {
                (new WhatsAppService())->sendTaskAssigned(
                    $assignedUser['telefono'],
                    $assignedUser['nombre_completo'],
                    $nombre,
                    $proyecto['nombre']
                );
            }
        }

        // Notify admins when a USER creates a task
        if ($user['rol'] === 'USER') {
            Notificacion::notifyAdmins([
                'type'        => 'user_tarea_creada',
                'title'       => 'Nueva tarea creada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} creó la tarea \"$nombre\" en el proyecto \"{$proyecto['nombre']}\".",
                'severity'    => 'info',
                'entity_type' => 'tarea',
                'entity_id'   => $tareaId,
            ]);
        }

        $this->flash('success', "Tarea \"$nombre\" creada exitosamente.");
        $this->redirect("/proyectos/$proyectoId/tareas");
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
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
            if (isset($data['prioridad']) && $data['prioridad'] !== '' && !Validator::isValidPrioridad($data['prioridad'])) {
                $errors[] = 'Prioridad inválida.';
            }
            if (isset($data['estado']) && $data['estado'] !== '' && !Validator::isValidEstado($data['estado'])) {
                $errors[] = 'Estado inválido.';
            }
            if (!empty($errors)) {
                foreach ($errors as $e) $this->flash('error', $e);
                $this->redirect("/proyectos/{$tarea['proyecto_id']}/tareas");
            }
        }

        $data['updated_by'] = $user['id'];
        Tarea::update((int)$id, $data);

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

        $this->flash('success', 'Tarea actualizada.');
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

        $estado = trim($_POST['estado'] ?? '');
        if (!Validator::isValidEstado($estado)) {
            $this->json(['ok' => false, 'message' => 'Estado inválido'], 400);
        }

        Tarea::updateEstado((int)$id, $estado, $user['id']);

        // Notification on change
        $efectivoUserId = (int)($tarea['usuario_asignado_id'] ?? 0);
        if ($efectivoUserId && $tarea['estado'] !== $estado) {
            Notificacion::create([
                'user_id'     => $efectivoUserId,
                'type'        => 'cambio_estado_tarea',
                'title'       => 'Estado de tarea actualizado',
                'message'     => "La tarea \"{$tarea['nombre']}\" cambió a $estado.",
                'severity'    => 'info',
                'channel'     => 'in_app',
                'entity_type' => 'tarea',
                'entity_id'   => (int)$id,
            ]);
        }

        // Notify admins when a USER changes task status
        if ($user['rol'] === 'USER' && $tarea['estado'] !== $estado) {
            $estadoLabel = [
                'por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada',
                'enterado'  => 'Enterado',  'ocupado'  => 'Ocupado',  'aceptada'  => 'Aceptada',
            ];
            $estadoNombre = $estadoLabel[$estado] ?? $estado;
            Notificacion::notifyAdmins([
                'type'        => 'user_estado_tarea',
                'title'       => 'Estado de tarea cambiado por usuario',
                'message'     => "El usuario {$user['nombre_completo']} cambió el estado de la tarea \"{$tarea['nombre']}\" a \"$estadoNombre\" en el proyecto \"{$proyecto['nombre']}\".",
                'severity'    => 'info',
                'entity_type' => 'tarea',
                'entity_id'   => (int)$id,
            ]);
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['ok' => true, 'estado' => $estado]);
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
        $reason     = trim($_POST['reason'] ?? '');
        Tarea::softDelete((int)$id, $user['id'], $reason);

        $this->flash('success', "Tarea \"{$tarea['nombre']}\" eliminada.");
        $this->redirect("/proyectos/$proyectoId/tareas");
    }
}
