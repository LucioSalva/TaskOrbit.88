<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Notificacion;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $user   = $this->currentUser();
        $role   = $user['rol'];
        $userId = $user['id'];
        $db     = Database::getInstance();

        // Filtered params — validate before use
        $allowedStatuses = ['por_hacer', 'haciendo', 'terminada', 'enterado', 'ocupado', 'aceptada', 'todos'];
        $rawStatus       = $_GET['status'] ?? '';
        $filterStatus    = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : '';

        $filterUserId    = $_GET['userId'] ?? '';
        $filterProjectId = $_GET['projectId'] ?? '';

        $rawDateStart = $_GET['dateStart'] ?? '';
        $rawDateEnd   = $_GET['dateEnd'] ?? '';
        $dateStart    = ($rawDateStart && \App\Helpers\Validator::isDate($rawDateStart)) ? $rawDateStart : '';
        $dateEnd      = ($rawDateEnd   && \App\Helpers\Validator::isDate($rawDateEnd))   ? $rawDateEnd   : '';

        // ---- Projects (role-based + filters) ----
        $projSql    = 'SELECT * FROM vw_proyectos WHERE 1=1';
        $projParams = [];

        if ($role === 'USER') {
            $projSql .= ' AND usuario_asignado_id = ?';
            $projParams[] = $userId;
        } elseif ($role === 'ADMIN') {
            $projSql .= ' AND created_by = ?';
            $projParams[] = $userId;
        }

        if ($filterStatus && $filterStatus !== 'todos') {
            $projSql .= ' AND estado = ?';
            $projParams[] = $filterStatus;
        }
        if ($filterUserId && $filterUserId !== 'todos' && is_numeric($filterUserId)) {
            $projSql .= ' AND usuario_asignado_id = ?';
            $projParams[] = (int)$filterUserId;
        }
        if ($filterProjectId && $filterProjectId !== 'todos' && is_numeric($filterProjectId)) {
            $projSql .= ' AND id = ?';
            $projParams[] = (int)$filterProjectId;
        }
        if ($dateStart) {
            $projSql .= ' AND fecha_inicio >= ?';
            $projParams[] = $dateStart;
        }
        if ($dateEnd) {
            $projSql .= ' AND fecha_fin <= ?';
            $projParams[] = $dateEnd;
        }

        $projSql .= ' ORDER BY created_at DESC LIMIT 100';
        $proyectos  = $db->fetchAll($projSql, $projParams);
        $projectIds = array_column($proyectos, 'id');

        // ---- Tasks ----
        $tareas    = [];
        $subtareas = [];
        if ($projectIds) {
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            $tareas = $db->fetchAll(
                "SELECT t.*, COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS effective_user_id,
                        COALESCE(ut.nombre_completo, up.nombre_completo) AS usuario_nombre
                 FROM vw_tareas t
                 JOIN vw_proyectos p ON t.proyecto_id = p.id
                 LEFT JOIN usuarios ut ON ut.id = t.usuario_asignado_id
                 LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
                 WHERE t.proyecto_id IN ($placeholders)",
                $projectIds
            );

            $taskIds = array_column($tareas, 'id');
            if ($taskIds) {
                $ph2       = implode(',', array_fill(0, count($taskIds), '?'));
                $subtareas = $db->fetchAll(
                    "SELECT * FROM vw_subtareas WHERE tarea_id IN ($ph2)",
                    $taskIds
                );
            }
        }

        // ---- Summary ----
        $summary = [
            'proyectosActivos'  => count(array_filter($proyectos, fn($p) => $p['estado'] !== 'terminada')),
            'tareasPendientes'  => count(array_filter($tareas,    fn($t) => $t['estado'] !== 'terminada')),
            'tareasTerminadas'  => count(array_filter($tareas,    fn($t) => $t['estado'] === 'terminada')),
            'subtareasVencidas' => 0,
        ];

        // ---- Productivity by task ----
        $productividadPorTarea = [];
        foreach ($tareas as $tarea) {
            $taskSubs  = array_filter($subtareas, fn($s) => (int)$s['tarea_id'] === (int)$tarea['id']);
            $total     = count($taskSubs);
            $done      = count(array_filter($taskSubs, fn($s) => $s['estado'] === 'terminada'));
            $progreso  = $total > 0 ? round(($done / $total) * 100) : ($tarea['estado'] === 'terminada' ? 100 : 0);
            $isOverdue = !empty($tarea['fecha_fin']) && strtotime($tarea['fecha_fin']) < time() && $tarea['estado'] !== 'terminada';
            $pendientes = $total - $done;
            if ($isOverdue) $summary['subtareasVencidas'] += $pendientes;

            $productividadPorTarea[] = [
                'id'                  => $tarea['id'],
                'nombre'              => $tarea['nombre'],
                'estado'              => $tarea['estado'],
                'proyecto_id'         => $tarea['proyecto_id'],
                'usuario_nombre'      => $tarea['usuario_nombre'] ?? '',
                'progreso'            => $progreso,
                'subtareasTotal'      => $total,
                'subtareasDone'       => $done,
                'subtareasPendientes' => $pendientes,
                'isOverdue'           => $isOverdue,
            ];
        }

        // ---- Productivity by project ----
        $productividadPorProyecto = [];
        foreach ($proyectos as $proyecto) {
            $pTareas  = array_filter($tareas, fn($t) => (int)$t['proyecto_id'] === (int)$proyecto['id']);
            $total    = count($pTareas);
            $done     = count(array_filter($pTareas, fn($t) => $t['estado'] === 'terminada'));
            $progreso = $total > 0 ? round(($done / $total) * 100) : 0;

            $productividadPorProyecto[] = [
                'id'       => $proyecto['id'],
                'nombre'   => $proyecto['nombre'],
                'estado'   => $proyecto['estado'],
                'total'    => $total,
                'done'     => $done,
                'progreso' => $progreso,
            ];
        }

        // ---- Users list for filters ----
        $usuarios = [];
        if (in_array($role, ['ADMIN', 'GOD'])) {
            $usuarios = $db->fetchAll(
                'SELECT id, nombre_completo FROM usuarios WHERE activo = TRUE ORDER BY nombre_completo'
            );
        }

        // ---- Productivity by USER-role users (completed tasks) ----
        $productividadPorUsuario = [];
        if (in_array($role, ['ADMIN', 'GOD'])) {
            $productividadPorUsuario = $db->fetchAll(
                "SELECT
                    u.id,
                    u.nombre_completo,
                    COUNT(t.id) FILTER (WHERE t.estado = 'terminada') AS tareas_terminadas,
                    COUNT(t.id) AS tareas_total
                 FROM usuarios u
                 JOIN usuarios_roles ur ON ur.usuario_id = u.id
                 JOIN roles r ON r.id = ur.rol_id
                 LEFT JOIN tareas t ON (
                     COALESCE(t.usuario_asignado_id, (
                         SELECT p.usuario_asignado_id FROM proyectos p WHERE p.id = t.proyecto_id
                     )) = u.id
                     AND t.deleted_at IS NULL
                 )
                 WHERE u.activo = TRUE AND r.nombre = 'USER'
                 GROUP BY u.id, u.nombre_completo
                 ORDER BY tareas_terminadas DESC, u.nombre_completo ASC"
            );
        }

        // ---- Unread notifications ----
        $notificacionesCount = Notificacion::getUnreadCount($userId);

        $this->view('dashboard/index', [
            'flash'                    => $this->getFlash(),
            'summary'                  => $summary,
            'proyectos'                => $proyectos,
            'tareas'                   => $tareas,
            'productividadPorTarea'    => $productividadPorTarea,
            'productividadPorProyecto' => $productividadPorProyecto,
            'productividadPorUsuario'  => $productividadPorUsuario,
            'usuarios'                 => $usuarios,
            'notificacionesCount'      => $notificacionesCount,
            'filterStatus'             => $filterStatus,
            'filterUserId'             => $filterUserId,
            'filterProjectId'          => $filterProjectId,
            'dateStart'                => $dateStart,
            'dateEnd'                  => $dateEnd,
        ]);
    }
}
