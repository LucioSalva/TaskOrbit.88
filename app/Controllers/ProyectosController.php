<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Gestión de Proyectos
 *  Archivo: ProyectosController.php
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
use App\Models\{Proyecto, Usuario, Notificacion, Nota, Evidencia};
use App\Services\{NotificacionService, SemaforoService};

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

        // Attach semáforo (hierarchical: worst-wins between proyecto and its tareas)
        $proyectoIds = array_column($proyectos, 'id');
        $tareasMap   = [];
        if (!empty($proyectoIds)) {
            $allTareas = \App\Models\Tarea::getByProyectoIds($proyectoIds);
            foreach ($allTareas as $t) {
                $tareasMap[(int)$t['proyecto_id']][] = $t;
            }
        }
        SemaforoService::attachToProyectos($proyectos, $tareasMap);

        // Group by estado
        $proyByEstado = [];
        $estadosKanban = ['por_hacer','haciendo','enterado','ocupado','terminada','aceptada'];
        foreach ($estadosKanban as $e) $proyByEstado[$e] = [];
        foreach ($proyectos as $p) {
            $e = $p['estado'] ?? 'por_hacer';
            $proyByEstado[$e][] = $p;
        }

        // Group by usuario
        $proyByUsuario = [];
        foreach ($proyectos as $p) {
            $key = $p['usuario_asignado_nombre'] ?? 'Sin asignar';
            if (!isset($proyByUsuario[$key])) {
                $proyByUsuario[$key] = ['nombre' => $key, 'items' => []];
            }
            $proyByUsuario[$key]['items'][] = $p;
        }
        ksort($proyByUsuario);
        if (isset($proyByUsuario['Sin asignar'])) {
            $sa = $proyByUsuario['Sin asignar'];
            unset($proyByUsuario['Sin asignar']);
            $proyByUsuario['Sin asignar'] = $sa;
        }

        // Sort timeline: items with fecha_fin first (ASC), then by updated_at DESC
        $proyTimeline = $proyectos;
        usort($proyTimeline, function($a, $b) {
            $af = $a['fecha_fin'] ?? null;
            $bf = $b['fecha_fin'] ?? null;
            if ($af && $bf) return strcmp($af, $bf);
            if ($af) return -1;
            if ($bf) return 1;
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        $usuarios = Usuario::getAssignableUsers();

        $this->view('proyectos/index', [
            'flash'         => $this->getFlash(),
            'proyectos'     => $proyectos,
            'proyByEstado'  => $proyByEstado,
            'proyByUsuario' => $proyByUsuario,
            'proyTimeline'  => $proyTimeline,
            'usuarios'      => $usuarios,
            'filtros'       => $filters,
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

        // Tasks — scoped by role
        $tareas = \App\Models\Tarea::getByProyecto((int)$id, $user['rol'], (int)$user['id']);
        SemaforoService::attachToAll($tareas);
        $proyecto['semaforo'] = SemaforoService::calcularProyecto($proyecto, $tareas);

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

        error_log('[ProyectosController::store] POST recibido: ' . json_encode($_POST));

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
        if (!Validator::maxLength($nombre, 120)) {
            $errors[] = 'El nombre no puede superar 120 caracteres.';
        }
        if (mb_strlen($descripcion) > 2000) {
            $errors[] = 'La descripción no puede superar los 2000 caracteres.';
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
            error_log('[ProyectosController::store] Validación fallida: ' . json_encode($errors));
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect('/proyectos/crear');
        }

        $data = [
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'prioridad'          => $prioridad,
            'estado'             => $estado,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'usuario_asignado_id'=> $usuarioAsignadoId,
        ];

        error_log('[ProyectosController::store] Datos validados, ejecutando insert: ' . json_encode($data));

        $proyectoId = 0;
        try {
            $proyectoId = Proyecto::create($data, $user['id']);
            error_log('[ProyectosController::store] Resultado insert: proyectoId=' . $proyectoId);
        } catch (\Throwable $e) {
            error_log('[ProyectosController::store] Excepción al crear proyecto: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $this->flash('error', 'No se pudo crear el proyecto. Por favor intenta de nuevo.');
            $this->redirect('/proyectos/crear');
        }

        if ($proyectoId <= 0) {
            error_log('[ProyectosController::store] Insert devolvió ID inválido: ' . $proyectoId);
            $this->flash('error', 'No se pudo crear el proyecto (ID inválido). Por favor intenta de nuevo.');
            $this->redirect('/proyectos/crear');
        }

        try {
            $assignedUser = Usuario::findById($usuarioAsignadoId);
            NotificacionService::dispatch(NotificacionService::PROYECTO_ASIGNADO, [
                'entity_type'   => 'proyecto',
                'entity_id'     => $proyectoId,
                'user_id'       => $usuarioAsignadoId,
                'user_nombre'   => $assignedUser['nombre_completo'] ?? '',
                'user_telefono' => $assignedUser['telefono'] ?? '',
                'proyecto'      => $nombre,
                'fecha_fin'     => $fechaFin ? date('d/m/Y', strtotime($fechaFin)) : 'Sin fecha',
                'actor'         => $user['nombre_completo'],
            ]);
        } catch (\Throwable $e) {
            // Notification failure must never roll back a successful project creation
            error_log('[ProyectosController::store] Fallo en notificación (no crítico): ' . $e->getMessage());
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
        if (isset($data['descripcion']) && mb_strlen($data['descripcion']) > 2000) {
            $errors[] = 'La descripción no puede superar los 2000 caracteres.';
        }
        if (isset($data['prioridad']) && !Validator::isValidPrioridad($data['prioridad'])) {
            $errors[] = 'Prioridad inválida.';
        }
        if (isset($data['estado']) && !Validator::isValidEstado($data['estado'])) {
            $errors[] = 'Estado inválido.';
        }

        if (!empty($errors)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => implode(' ', $errors)], 422);
            }
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect("/proyectos/$id/editar");
        }

        $data['updated_by'] = $user['id'];
        Proyecto::update((int)$id, $data);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $updatedProyecto = Proyecto::getById((int)$id);
            $this->json([
                'ok'      => true,
                'proyecto' => [
                    'id'                     => (int)$id,
                    'nombre'                 => $updatedProyecto['nombre'] ?? '',
                    'descripcion'            => $updatedProyecto['descripcion'] ?? '',
                    'prioridad'              => $updatedProyecto['prioridad'] ?? '',
                    'fecha_fin'              => $updatedProyecto['fecha_fin'] ?? '',
                    'usuario_asignado_id'    => $updatedProyecto['usuario_asignado_id'] ?? null,
                    'usuario_asignado_nombre'=> $updatedProyecto['usuario_asignado_nombre'] ?? '',
                ],
            ]);
        }
        $this->flash('success', 'Proyecto actualizado correctamente.');
        $this->redirect("/proyectos/$id");
    }

    public function updateEstado(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $user    = $this->currentUser();
        $proyecto = Proyecto::getById((int)$id);

        if (!$proyecto) {
            $this->json(['ok' => false, 'message' => 'Proyecto no encontrado'], 404);
        }

        if (!Proyecto::checkAccess($proyecto, $user['id'], $user['rol'])) {
            $this->json(['ok' => false, 'message' => 'Acceso denegado'], 403);
        }

        $estado = trim($_POST['estado'] ?? '');
        if (!Validator::isValidEstado($estado)) {
            $this->json(['ok' => false, 'message' => 'Estado inválido'], 400);
        }

        // Require evidence before marking as terminada
        if ($estado === 'terminada' && !Evidencia::tieneEvidencia('proyecto', (int)$id)) {
            $this->json(['ok' => false, 'message' => 'Debes adjuntar al menos una evidencia PDF o PNG antes de marcar como terminada.', 'requires_evidencia' => true], 400);
        }

        $oldEstado = $proyecto['estado'];
        Proyecto::updateEstado((int)$id, $estado, $user['id']);

        // Auto-note: proyecto estado change
        if ($oldEstado !== $estado) {
            $estadoLabels = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada',
                             'enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
            $oldLbl = $estadoLabels[$oldEstado] ?? $oldEstado;
            $newLbl = $estadoLabels[$estado] ?? $estado;
            Nota::createAuto('proyecto', (int)$id,
                "Estado cambiado de \"$oldLbl\" a \"$newLbl\" por {$user['nombre_completo']}.",
                $user['id']
            );
        }

        // Notify assigned user of status change
        $assignedUserId = (int)($proyecto['usuario_asignado_id'] ?? 0);
        if ($assignedUserId && $oldEstado !== $estado) {
            $estadoLabels = [
                'por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada',
                'enterado'  => 'Enterado',  'ocupado'  => 'Ocupado',  'aceptada'  => 'Aceptada',
            ];
            $assignedUser = Usuario::findById($assignedUserId);
            NotificacionService::dispatch(NotificacionService::CAMBIO_ESTADO_PROYECTO, [
                'entity_type'   => 'proyecto',
                'entity_id'     => (int)$id,
                'user_id'       => $assignedUserId,
                'user_nombre'   => $assignedUser['nombre_completo'] ?? '',
                'user_telefono' => $assignedUser['telefono'] ?? '',
                'proyecto'      => $proyecto['nombre'],
                'estado'        => $estadoLabels[$estado] ?? $estado,
                'actor'         => $user['nombre_completo'],
            ]);
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['ok' => true, 'estado' => $estado]);
        }

        $this->redirect('/proyectos');
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
