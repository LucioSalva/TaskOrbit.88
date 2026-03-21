<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\{CSRF, Validator};
use App\Models\{Proyecto, Usuario, Notificacion};
use App\Services\WhatsAppService;

class ProyectosController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $allowedEstados    = ['por_hacer', 'haciendo', 'terminada', 'enterado', 'ocupado', 'aceptada'];
        $allowedPrioridades = ['baja', 'media', 'alta', 'critica'];

        $rawEstado    = $_GET['estado']    ?? '';
        $rawPrioridad = $_GET['prioridad'] ?? '';
        $rawQ         = $_GET['q']         ?? '';

        $filters = [
            'estado'    => in_array($rawEstado,    $allowedEstados,    true) ? $rawEstado    : '',
            'prioridad' => in_array($rawPrioridad, $allowedPrioridades, true) ? $rawPrioridad : '',
            'q'         => mb_substr(trim($rawQ), 0, 100),
        ];

        $proyectos = Proyecto::getAll($user['rol'], $user['id'], array_filter($filters));

        $this->view('proyectos/index', [
            'flash'     => $this->getFlash(),
            'proyectos' => $proyectos,
        ]);
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $user    = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado o sin acceso.');
            $this->redirect('/proyectos');
        }

        // Tasks count
        $db    = \App\Core\Database::getInstance();
        $tareas = $db->fetchAll(
            'SELECT * FROM vw_tareas WHERE proyecto_id = ? ORDER BY created_at DESC',
            [(int)$id]
        );

        // Notes for project
        $notas = \App\Models\Nota::getByScope('proyecto', (int)$id);

        $this->view('proyectos/show', [
            'flash'    => $this->getFlash(),
            'proyecto' => $proyecto,
            'tareas'   => $tareas,
            'notas'    => $notas,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');

        $usuarios = Usuario::getAssignableUsers();
        $this->view('proyectos/create', [
            'flash'    => $this->getFlash(),
            'usuarios' => $usuarios,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user  = $this->currentUser();
        $errors = [];

        $nombre             = trim($_POST['nombre'] ?? '');
        $descripcion        = trim($_POST['descripcion'] ?? '');
        $prioridad          = $_POST['prioridad'] ?? 'media';
        $estado             = 'por_hacer'; // Business rule: new projects always start as "Por hacer"
        $fechaInicio        = $_POST['fecha_inicio'] ?? '';
        $fechaFin           = $_POST['fecha_fin'] ?? '';
        $usuarioAsignadoId  = (int)($_POST['usuario_asignado_id'] ?? 0);

        if (!Validator::required($nombre) || !Validator::minLength($nombre, 3)) {
            $errors[] = 'El nombre es requerido (mínimo 3 caracteres).';
        }
        if (!Validator::isValidPrioridad($prioridad)) {
            $errors[] = 'Prioridad inválida.';
        }
        if (!Validator::isValidEstado($estado)) {
            $errors[] = 'Estado inválido.';
        }
        if (!Validator::isNumericId($usuarioAsignadoId)) {
            $errors[] = 'Debe seleccionar un usuario asignado.';
        }
        if ($fechaInicio && $fechaFin && strtotime($fechaFin) <= strtotime($fechaInicio)) {
            $errors[] = 'La fecha de fin debe ser posterior a la de inicio.';
        }

        if (empty($errors)) {
            $assignedRole = Usuario::getRoleById($usuarioAsignadoId);
            if (!$assignedRole) {
                $errors[] = 'El usuario asignado no existe.';
            } elseif ($assignedRole === 'GOD') {
                $errors[] = 'No se puede asignar proyectos a un usuario GOD.';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect('/proyectos/crear');
        }

        $proyectoId = Proyecto::create([
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'prioridad'          => $prioridad,
            'estado'             => $estado,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'usuario_asignado_id'=> $usuarioAsignadoId,
        ], $user['id']);

        // Notification
        Notificacion::create([
            'user_id'     => $usuarioAsignadoId,
            'type'        => 'asignacion_proyecto',
            'title'       => 'Nuevo proyecto asignado',
            'message'     => "Se te asignó el proyecto \"$nombre\".",
            'severity'    => 'info',
            'channel'     => 'in_app',
            'entity_type' => 'proyecto',
            'entity_id'   => $proyectoId,
        ]);

        // WhatsApp
        $assignedUser = Usuario::findById($usuarioAsignadoId);
        if ($assignedUser && $assignedUser['telefono']) {
            (new WhatsAppService())->sendProjectAssigned(
                $assignedUser['telefono'],
                $assignedUser['nombre_completo'],
                $nombre
            );
        }

        $this->flash('success', "Proyecto \"$nombre\" creado exitosamente.");
        $this->redirect('/proyectos');
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $usuarios = Usuario::getAssignableUsers();
        $this->view('proyectos/edit', [
            'flash'    => $this->getFlash(),
            'proyecto' => $proyecto,
            'usuarios' => $usuarios,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $data = [];
        $fields = ['nombre', 'descripcion', 'prioridad', 'estado', 'fecha_inicio', 'fecha_fin', 'usuario_asignado_id'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = trim($_POST[$field]);
            }
        }

        $errors = [];
        if (isset($data['nombre']) && !Validator::minLength($data['nombre'], 3)) {
            $errors[] = 'El nombre debe tener al menos 3 caracteres.';
        }
        if (isset($data['prioridad']) && !Validator::isValidPrioridad($data['prioridad'])) {
            $errors[] = 'Prioridad inválida.';
        }
        if (isset($data['estado']) && !Validator::isValidEstado($data['estado'])) {
            $errors[] = 'Estado inválido.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect("/proyectos/$id/editar");
        }

        $data['updated_by'] = $user['id'];
        Proyecto::update((int)$id, $data);

        $this->flash('success', 'Proyecto actualizado correctamente.');
        $this->redirect("/proyectos/$id");
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->flash('error', 'Proyecto no encontrado.');
            $this->redirect('/proyectos');
        }

        $reason = trim($_POST['reason'] ?? '');
        Proyecto::softDelete((int)$id, $user['id'], $reason);

        $this->flash('success', "Proyecto \"{$proyecto['nombre']}\" eliminado.");
        $this->redirect('/proyectos');
    }

    public function deletePreview(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');

        $user     = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto || !Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->json(['error' => 'No encontrado'], 404);
        }

        $preview = Proyecto::getDeletePreview((int)$id);
        $this->json($preview);
    }
}
