<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\{CSRF, Validator};
use App\Models\{Subtarea, Tarea, Proyecto, Notificacion};

class SubtareasController extends Controller
{
    public function store(string $tareaId): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user  = $this->currentUser();
        $tarea = Tarea::getById((int)$tareaId);

        if (!$tarea) {
            $this->flash('error', 'Tarea no encontrada.');
            $this->back();
        }

        $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'No tienes acceso al proyecto de esta tarea.');
            $this->back();
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
        if (!Validator::isValidPrioridad($prioridad)) $errors[] = 'Prioridad inválida.';
        if (!Validator::isValidEstado($estado))        $errors[] = 'Estado inválido.';

        if (!empty($errors)) {
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

        // Notify admins when a USER creates a subtask
        if ($user['rol'] === 'USER') {
            Notificacion::notifyAdmins([
                'type'        => 'user_subtarea_creada',
                'title'       => 'Nueva subtarea creada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} creó la subtarea \"$nombre\" en la tarea \"{$tarea['nombre']}\".",
                'severity'    => 'info',
                'entity_type' => 'subtarea',
                'entity_id'   => $subtareaId,
            ]);
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
        $subtarea = Subtarea::getById((int)$id);

        if (!$subtarea) {
            $this->flash('error', 'Subtarea no encontrada.');
            $this->back();
        }

        $tarea = Tarea::getById((int)$subtarea['tarea_id']);
        if ($tarea) {
            $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
            if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
                $this->flash('error', 'No tienes acceso al proyecto de esta subtarea.');
                $this->back();
            }
        }

        // ---- Validation ----
        $errors = [];

        $nombre      = isset($_POST['nombre'])      ? trim($_POST['nombre'])      : null;
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : null;
        $prioridad   = isset($_POST['prioridad'])   ? trim($_POST['prioridad'])   : null;
        $estado      = isset($_POST['estado'])      ? trim($_POST['estado'])      : null;
        $fechaInicio = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : null;
        $fechaFin    = isset($_POST['fecha_fin'])    ? trim($_POST['fecha_fin'])    : null;

        // nombre: required when submitted, 3–200 chars
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
                $errors[] = 'La descripción no puede superar los 2000 caracteres.';
            }
        }

        // prioridad: must be a valid enum value
        if ($prioridad !== null && $prioridad !== '') {
            if (!Validator::isValidPrioridad($prioridad)) {
                $errors[] = 'Prioridad inválida.';
            }
        }

        // estado: must be a valid enum value
        if ($estado !== null && $estado !== '') {
            if (!Validator::isValidEstado($estado)) {
                $errors[] = 'Estado inválido.';
            }
        }

        // fecha_inicio: must be a valid date if provided
        if ($fechaInicio !== null && $fechaInicio !== '') {
            if (!Validator::isDate($fechaInicio)) {
                $errors[] = 'La fecha de inicio no es válida.';
            }
        }

        // fecha_fin: must be a valid date if provided; must not precede fecha_inicio
        if ($fechaFin !== null && $fechaFin !== '') {
            if (!Validator::isDate($fechaFin)) {
                $errors[] = 'La fecha de fin no es válida.';
            } elseif (
                $fechaInicio !== null &&
                $fechaInicio !== '' &&
                Validator::isDate($fechaInicio) &&
                $fechaFin < $fechaInicio
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

        // Notify admins when a USER updates a subtask
        if ($user['rol'] === 'USER') {
            Notificacion::notifyAdmins([
                'type'        => 'user_subtarea_actualizada',
                'title'       => 'Subtarea actualizada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} actualizó la subtarea \"{$subtarea['nombre']}\".",
                'severity'    => 'info',
                'entity_type' => 'subtarea',
                'entity_id'   => (int)$id,
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
        $subtarea = Subtarea::getById((int)$id);
        if (!$subtarea) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'No encontrada'], 404);
            }
            $this->back();
        }

        $tarea = Tarea::getById((int)$subtarea['tarea_id']);
        if ($tarea) {
            $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
            if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    $this->json(['ok' => false, 'message' => 'Acceso denegado'], 403);
                }
                $this->back();
            }
        }

        $estado = trim($_POST['estado'] ?? '');
        if (!Validator::isValidEstado($estado)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'Estado inválido'], 400);
            }
            $this->back();
        }

        $oldEstado = $subtarea['estado'];
        Subtarea::updateEstado((int)$id, $estado);

        // Notify admins when a USER changes subtask status
        if ($user['rol'] === 'USER' && $oldEstado !== $estado) {
            $estadoLabel = [
                'por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada',
                'enterado'  => 'Enterado',  'ocupado'  => 'Ocupado',  'aceptada'  => 'Aceptada',
            ];
            $estadoNombre = $estadoLabel[$estado] ?? $estado;
            Notificacion::notifyAdmins([
                'type'        => 'user_estado_subtarea',
                'title'       => 'Estado de subtarea cambiado por usuario',
                'message'     => "El usuario {$user['nombre_completo']} cambió el estado de la subtarea \"{$subtarea['nombre']}\" a \"$estadoNombre\".",
                'severity'    => 'info',
                'entity_type' => 'subtarea',
                'entity_id'   => (int)$id,
            ]);
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['ok' => true, 'estado' => $estado]);
        }

        $this->back();
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $subtarea = Subtarea::getById((int)$id);

        if (!$subtarea) {
            $this->flash('error', 'Subtarea no encontrada.');
            $this->back();
        }

        $tarea = Tarea::getById((int)$subtarea['tarea_id']);
        if ($tarea) {
            $proyecto = Proyecto::getById((int)$tarea['proyecto_id']);
            if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
                $this->flash('error', 'No tienes acceso al proyecto de esta subtarea.');
                $this->back();
            }
        }

        Subtarea::softDelete((int)$id, $user['id']);
        $this->flash('success', "Subtarea \"{$subtarea['nombre']}\" eliminada.");
        $this->back();
    }
}
