<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * MetricasService — Fuente única de verdad para todas las métricas de productividad.
 *
 * DEFINICIONES DE NEGOCIO:
 *
 * "Asignada":      COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = userId
 *                  — usa el fallback de proyecto si la tarea no tiene asignado directo
 *
 * "Terminada":     estado IN ('terminada', 'aceptada')
 *                  — 'aceptada' es la confirmación administrativa de una tarea terminada
 *
 * "Vencida":       fecha_fin < CURRENT_DATE AND estado NOT IN ('terminada', 'aceptada')
 *                  — tareas terminadas NUNCA se cuentan como vencidas
 *                  — tareas sin fecha_fin NO se cuentan como vencidas
 *
 * "En proceso":    estado IN ('haciendo', 'ocupado', 'enterado')
 *                  — todos los estados activos no-inactivos
 *
 * "Pendiente":     estado = 'por_hacer'
 *
 * "Sin movimiento": updated_at < NOW() - INTERVAL '7 días' AND not terminada
 *                  — usa updated_at como proxy de última actividad (trigger lo mantiene fresco)
 *
 * "Cumplimiento":  terminadas / asignadas * 100  (0 si asignadas = 0)
 *
 * "Tiempo resolución": AVG(completed_at - created_at) en días
 *                      — solo tareas con completed_at IS NOT NULL
 *                      — usa created_at como inicio porque fecha_inicio frecuentemente es NULL
 *
 * "Reasignación": cuenta bajo el asignado actual (COALESCE), no el histórico
 *
 * "Subtareas": NO se cuentan en métricas de productividad principal.
 *              Solo soportan propagación de estado vía EstadoService.
 *
 * "Soft-deleted": excluidos de todas las métricas (deleted_at IS NULL).
 */
class MetricasService
{
    /** Umbral en días para considerar una tarea "sin movimiento". */
    public const DIAS_SIN_MOVIMIENTO = 7;

    // =========================================================
    // RESUMEN GLOBAL
    // =========================================================

    /**
     * Métricas globales del dashboard.
     * Role: GOD ve todo, ADMIN ve solo sus proyectos (created_by), USER ve solo los suyos.
     */
    public static function resumenGlobal(string $role, int $userId): array
    {
        $db = Database::getInstance();

        $roleWhere  = self::roleWhereProyecto($role, $userId);
        $roleParams = self::roleParamsProyecto($role, $userId);

        $diasSinMovimiento = (int) self::DIAS_SIN_MOVIMIENTO;
        $fechaLimiteSinMovimiento = date('Y-m-d H:i:s', strtotime("-{$diasSinMovimiento} days"));

        $row = $db->fetchOne(
            "SELECT
                COUNT(DISTINCT p.id) FILTER (WHERE p.estado NOT IN ('terminada','aceptada'))
                    AS total_proyectos_activos,
                COUNT(DISTINCT p.id)
                    AS total_proyectos,
                COUNT(t.id) FILTER (WHERE t.estado NOT IN ('terminada','aceptada'))
                    AS total_tareas_activas,
                COUNT(t.id) FILTER (WHERE t.estado IN ('terminada','aceptada'))
                    AS total_tareas_terminadas,
                COUNT(t.id) FILTER (
                    WHERE t.fecha_fin < CURRENT_DATE
                      AND t.estado NOT IN ('terminada','aceptada')
                )   AS total_tareas_vencidas,
                COUNT(t.id) FILTER (
                    WHERE t.updated_at < ?
                      AND t.estado NOT IN ('terminada','aceptada')
                )   AS total_tareas_sin_movimiento
             FROM proyectos p
             LEFT JOIN tareas t ON t.proyecto_id = p.id AND t.deleted_at IS NULL
             WHERE p.deleted_at IS NULL $roleWhere",
            array_merge([$fechaLimiteSinMovimiento], $roleParams)
        );

        return $row ?? [
            'total_proyectos_activos'    => 0,
            'total_proyectos'            => 0,
            'total_tareas_activas'       => 0,
            'total_tareas_terminadas'    => 0,
            'total_tareas_vencidas'      => 0,
            'total_tareas_sin_movimiento'=> 0,
        ];
    }

    // =========================================================
    // MÉTRICAS POR USUARIO
    // =========================================================

    /**
     * Métricas detalladas de productividad por usuario.
     * Retorna array de usuarios con sus métricas.
     * GOD y ADMIN ven todos los usuarios (no-GOD). USER solo se ve a sí mismo.
     */
    public static function metricasPorUsuario(string $role, int $userId): array
    {
        $db = Database::getInstance();

        $userFilter = '';
        $params     = [];

        if ($role === 'USER') {
            $userFilter = 'AND u.id = ?';
            $params[]   = $userId;
        }

        // For ADMIN: show all users but only tasks from ADMIN's projects
        $tareaFilter = '';
        if ($role === 'ADMIN') {
            $tareaFilter = 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
            $params[]    = $userId;
            $params[]    = $userId;
        }

        $diasSinMovimientoU = (int) self::DIAS_SIN_MOVIMIENTO;
        $fechaLimiteU = date('Y-m-d H:i:s', strtotime("-{$diasSinMovimientoU} days"));
        // Prepend the date placeholder param before role/user filter params
        array_unshift($params, $fechaLimiteU);

        return $db->fetchAll(
            "SELECT
                u.id                                  AS usuario_id,
                u.nombre_completo,
                COUNT(t.id)
                    AS total_tareas_asignadas,
                COUNT(t.id) FILTER (WHERE t.estado IN ('terminada','aceptada'))
                    AS total_tareas_terminadas,
                COUNT(t.id) FILTER (WHERE t.estado IN ('haciendo','ocupado','enterado'))
                    AS total_tareas_en_proceso,
                COUNT(t.id) FILTER (WHERE t.estado = 'por_hacer')
                    AS total_tareas_pendientes,
                COUNT(t.id) FILTER (
                    WHERE t.fecha_fin < CURRENT_DATE
                      AND t.estado NOT IN ('terminada','aceptada')
                )   AS total_tareas_vencidas,
                COUNT(t.id) FILTER (WHERE t.estado NOT IN ('terminada','aceptada'))
                    AS carga_actual,
                COUNT(t.id) FILTER (
                    WHERE t.updated_at < ?
                      AND t.estado NOT IN ('terminada','aceptada')
                )   AS tareas_sin_movimiento,
                ROUND(
                    CASE WHEN COUNT(t.id) > 0
                         THEN COUNT(t.id) FILTER (WHERE t.estado IN ('terminada','aceptada'))::NUMERIC
                              / COUNT(t.id) * 100
                         ELSE 0 END
                , 1)  AS porcentaje_cumplimiento,
                ROUND(
                    AVG(
                        EXTRACT(EPOCH FROM (t.updated_at - t.created_at)) / 86400.0
                    ) FILTER (
                        WHERE t.estado IN ('terminada','aceptada')
                    )
                , 1)  AS tiempo_promedio_dias
             FROM usuarios u
             JOIN usuarios_roles ur ON ur.usuario_id = u.id
             JOIN roles r ON r.id = ur.rol_id AND r.nombre != 'GOD'
             LEFT JOIN (
                 SELECT t.*,
                        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS effective_user_id
                 FROM tareas t
                 JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL $tareaFilter
                 WHERE t.deleted_at IS NULL
             ) t ON t.effective_user_id = u.id
             WHERE u.activo = TRUE $userFilter
             GROUP BY u.id, u.nombre_completo
             ORDER BY porcentaje_cumplimiento DESC, total_tareas_terminadas DESC, u.nombre_completo ASC",
            $params
        );
    }

    /**
     * Métricas del usuario autenticado (para su propio dashboard).
     */
    public static function misDatos(int $userId): array
    {
        $rows = self::metricasPorUsuario('USER', $userId);
        return $rows[0] ?? self::emptyUserMetrics($userId);
    }

    // =========================================================
    // MÉTRICAS POR PROYECTO
    // =========================================================

    /**
     * Métricas detalladas por proyecto.
     * Ordena por tareas_vencidas DESC, porcentaje_avance ASC (peores primero).
     */
    public static function metricasPorProyecto(string $role, int $userId): array
    {
        $db = Database::getInstance();

        $roleWhere  = self::roleWhereProyecto($role, $userId);
        $roleParams = self::roleParamsProyecto($role, $userId);

        return $db->fetchAll(
            "SELECT
                p.id                                AS proyecto_id,
                p.nombre,
                p.estado                            AS proyecto_estado,
                p.prioridad,
                p.fecha_fin                         AS proyecto_fecha_fin,
                FALSE                               AS proyecto_en_riesgo,
                u.nombre_completo                   AS responsable,
                COUNT(t.id)
                    AS total_tareas,
                COUNT(t.id) FILTER (WHERE t.estado IN ('terminada','aceptada'))
                    AS total_tareas_terminadas,
                COUNT(t.id) FILTER (WHERE t.estado IN ('haciendo','ocupado','enterado'))
                    AS total_tareas_en_proceso,
                COUNT(t.id) FILTER (WHERE t.estado = 'por_hacer')
                    AS total_tareas_pendientes,
                COUNT(t.id) FILTER (
                    WHERE t.fecha_fin < CURRENT_DATE
                      AND t.estado NOT IN ('terminada','aceptada')
                )   AS total_tareas_vencidas,
                COUNT(DISTINCT COALESCE(t.usuario_asignado_id, p.usuario_asignado_id))
                    FILTER (WHERE t.id IS NOT NULL)
                    AS usuarios_participando,
                ROUND(
                    CASE WHEN COUNT(t.id) > 0
                         THEN COUNT(t.id) FILTER (WHERE t.estado IN ('terminada','aceptada'))::NUMERIC
                              / COUNT(t.id) * 100
                         ELSE 0 END
                , 1)  AS porcentaje_avance,
                ROUND(
                    AVG(
                        EXTRACT(EPOCH FROM (t.updated_at - t.created_at)) / 86400.0
                    ) FILTER (
                        WHERE t.estado IN ('terminada','aceptada')
                    )
                , 1)  AS tiempo_promedio_dias,
                BOOL_OR(
                    t.fecha_fin < CURRENT_DATE AND t.estado NOT IN ('terminada','aceptada')
                )   AS tiene_tareas_vencidas
             FROM proyectos p
             LEFT JOIN usuarios u ON u.id = p.usuario_asignado_id
             LEFT JOIN tareas t ON t.proyecto_id = p.id AND t.deleted_at IS NULL
             WHERE p.deleted_at IS NULL $roleWhere
             GROUP BY p.id, p.nombre, p.estado, p.prioridad, p.fecha_fin, u.nombre_completo
             ORDER BY total_tareas_vencidas DESC, porcentaje_avance ASC",
            $roleParams
        );
    }

    // =========================================================
    // LISTADOS ACCIONABLES
    // =========================================================

    /**
     * Tareas vencidas (para listado de alertas).
     */
    public static function tareasVencidas(string $role, int $userId, int $limit = 15): array
    {
        $db = Database::getInstance();

        $roleWhere  = '';
        $params     = [];

        if ($role === 'USER') {
            $roleWhere = 'AND COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?';
            $params[]  = $userId;
        } elseif ($role === 'ADMIN') {
            $roleWhere = 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
            $params[]  = $userId;
            $params[]  = $userId;
        }

        $params[] = $limit;

        return $db->fetchAll(
            "SELECT
                t.id,
                t.nombre,
                t.estado,
                t.prioridad,
                t.fecha_fin,
                p.nombre                                              AS proyecto_nombre,
                COALESCE(ut.nombre_completo, up.nombre_completo)      AS responsable,
                CURRENT_DATE - t.fecha_fin                            AS dias_vencida
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
             LEFT JOIN usuarios ut ON ut.id = t.usuario_asignado_id
             LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
             WHERE t.deleted_at IS NULL
               AND t.fecha_fin < CURRENT_DATE
               AND t.estado NOT IN ('terminada','aceptada')
               $roleWhere
             ORDER BY t.fecha_fin ASC
             LIMIT ?",
            $params
        );
    }

    /**
     * Tareas sin actividad reciente.
     */
    public static function tareasSinMovimiento(string $role, int $userId, int $limit = 10): array
    {
        $db = Database::getInstance();

        $roleWhere  = '';
        $params     = [(int)self::DIAS_SIN_MOVIMIENTO];

        if ($role === 'USER') {
            $roleWhere = 'AND COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?';
            $params[]  = $userId;
        } elseif ($role === 'ADMIN') {
            $roleWhere = 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
            $params[]  = $userId;
            $params[]  = $userId;
        }

        $params[] = $limit;

        return $db->fetchAll(
            "SELECT
                t.id,
                t.nombre,
                t.estado,
                t.prioridad,
                t.fecha_fin,
                p.nombre                                              AS proyecto_nombre,
                COALESCE(ut.nombre_completo, up.nombre_completo)      AS responsable,
                EXTRACT(DAY FROM NOW() - t.updated_at)::INT           AS dias_sin_actividad
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
             LEFT JOIN usuarios ut ON ut.id = t.usuario_asignado_id
             LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
             WHERE t.deleted_at IS NULL
               AND t.updated_at < NOW() - INTERVAL '1 day' * ?
               AND t.estado NOT IN ('terminada','aceptada')
               $roleWhere
             ORDER BY t.updated_at ASC
             LIMIT ?",
            $params
        );
    }

    /**
     * Distribución de tareas por estado (para gráfica doughnut).
     */
    public static function distribucionEstados(string $role, int $userId): array
    {
        $db = Database::getInstance();

        $roleWhere  = '';
        $params     = [];

        if ($role === 'USER') {
            $roleWhere = 'AND COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?';
            $params[]  = $userId;
        } elseif ($role === 'ADMIN') {
            $roleWhere = 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
            $params[]  = $userId;
            $params[]  = $userId;
        }

        $rows = $db->fetchAll(
            "SELECT t.estado, COUNT(t.id) AS total
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
             WHERE t.deleted_at IS NULL $roleWhere
             GROUP BY t.estado",
            $params
        );

        // Normalize to all possible states
        $result = array_fill_keys(['por_hacer','haciendo','terminada','enterado','ocupado','aceptada'], 0);
        foreach ($rows as $row) {
            $result[$row['estado']] = (int)$row['total'];
        }
        return $result;
    }

    // =========================================================
    // SEMÁFORO RESUMEN (para dashboard)
    // =========================================================

    /**
     * Calcula la distribución de semáforos para proyectos o tareas.
     *
     * @param string $entityType 'proyectos' | 'tareas'
     * @return array ['verde'=>int, 'amarillo'=>int, 'rojo'=>int, 'neutral'=>int]
     */
    public static function semaforoResumen(string $entityType, string $role, int $userId): array
    {
        $db = Database::getInstance();

        if ($entityType === 'proyectos') {
            $roleWhere  = self::roleWhereProyecto($role, $userId);
            $roleParams = self::roleParamsProyecto($role, $userId);

            $proyectos = $db->fetchAll(
                "SELECT p.id, p.estado::TEXT AS estado, p.fecha_fin::TEXT AS fecha_fin, p.updated_at
                 FROM proyectos p
                 WHERE p.deleted_at IS NULL $roleWhere",
                $roleParams
            );

            $proyectoIds = array_column($proyectos, 'id');
            $tareasMap   = [];
            if (!empty($proyectoIds)) {
                $placeholders = implode(',', array_fill(0, count($proyectoIds), '?'));
                $allTareas = $db->fetchAll(
                    "SELECT t.id, t.proyecto_id, t.estado::TEXT AS estado, t.fecha_fin::TEXT AS fecha_fin, t.updated_at
                     FROM tareas t
                     WHERE t.proyecto_id IN ($placeholders) AND t.deleted_at IS NULL",
                    $proyectoIds
                );
                foreach ($allTareas as $t) {
                    $tareasMap[(int)$t['proyecto_id']][] = $t;
                }
            }

            SemaforoService::attachToProyectos($proyectos, $tareasMap);
            return SemaforoService::resumen($proyectos);
        }

        // tareas
        $roleWhere  = '';
        $roleParams = [];
        if ($role === 'USER') {
            $roleWhere    = 'AND COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) = ?';
            $roleParams[] = $userId;
        } elseif ($role === 'ADMIN') {
            $roleWhere    = 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
            $roleParams[] = $userId;
            $roleParams[] = $userId;
        }

        $tareas = $db->fetchAll(
            "SELECT t.id, t.estado::TEXT AS estado, t.fecha_fin::TEXT AS fecha_fin, t.updated_at
             FROM tareas t
             JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
             WHERE t.deleted_at IS NULL $roleWhere",
            $roleParams
        );

        SemaforoService::attachToAll($tareas);
        return SemaforoService::resumen($tareas);
    }

    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================

    private static function roleWhereProyecto(string $role, int $userId): string
    {
        if ($role === 'USER')  return 'AND p.usuario_asignado_id = ?';
        if ($role === 'ADMIN') return 'AND (p.created_by = ? OR p.usuario_asignado_id = ?)';
        return ''; // GOD
    }

    private static function roleParamsProyecto(string $role, int $userId): array
    {
        if ($role === 'USER')  return [$userId];
        if ($role === 'ADMIN') return [$userId, $userId];
        return [];
    }

    private static function emptyUserMetrics(int $userId): array
    {
        return [
            'usuario_id'                 => $userId,
            'nombre_completo'            => '',
            'total_tareas_asignadas'     => 0,
            'total_tareas_terminadas'    => 0,
            'total_tareas_en_proceso'    => 0,
            'total_tareas_pendientes'    => 0,
            'total_tareas_vencidas'      => 0,
            'carga_actual'               => 0,
            'tareas_sin_movimiento'      => 0,
            'porcentaje_cumplimiento'    => 0,
            'tiempo_promedio_dias'       => null,
        ];
    }
}
