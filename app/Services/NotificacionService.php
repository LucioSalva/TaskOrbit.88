<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * NotificacionService — Fuente única de verdad para el envío de notificaciones.
 *
 * ─── FLUJO ───────────────────────────────────────────────────────────────────
 *  Controller / Scheduler
 *       │
 *       ▼
 *  dispatch(event, context)          ← Punto de entrada único
 *       │
 *       ├─ isDuplicate()             ← Deduplicación por ventana de tiempo
 *       │
 *       ├─ NotificacionTemplates     ← Construye título + mensaje
 *       │
 *       ├─ sendInApp()              ← Canal: notificación interna
 *       │
 *       └─ logToNotifLogs()         ← Registro de auditoría y deduplicación
 *
 * ─── REGLAS ANTI-RUIDO ───────────────────────────────────────────────────────
 *  - Mismo evento + entidad + usuario → bloqueado dentro de la ventana definida
 *  - Entidades terminadas/aceptadas → nunca reciben alertas de vencimiento/inactividad
 *  - Usuarios inactivos (activo=false) → salteados
 *
 * ─── VENTANAS DE DEDUPLICACIÓN (horas) ──────────────────────────────────────
 *  Eventos de asignación        : 1 h
 *  Cambios de estado notables   : 0 (no se deduplican, son one-shot)
 *  Alertas de vencimiento/prox  : 24 h
 *  Inactividad / sin iniciar    : 48 h
 *  Escalamiento                 : 48 h
 *
 * ─── CANAL ───────────────────────────────────────────────────────────────────
 *  in_app   → tabla notifications (siempre, si no hay duplicado)
 */
class NotificacionService
{
    // ── Constantes de eventos ─────────────────────────────────────────────────
    public const TAREA_ASIGNADA          = 'tarea_asignada';
    public const TAREA_REASIGNADA        = 'tarea_reasignada';
    public const SUBTAREA_ASIGNADA       = 'subtarea_asignada';
    public const PROYECTO_ASIGNADO       = 'proyecto_asignado';

    public const TAREA_PROXIMA_VENCER    = 'tarea_proxima_vencer';
    public const SUBTAREA_PROXIMA_VENCER = 'subtarea_proxima_vencer';
    public const TAREA_VENCIDA           = 'tarea_vencida';
    public const SUBTAREA_VENCIDA        = 'subtarea_vencida';

    public const TAREA_SIN_INICIAR       = 'tarea_sin_iniciar';
    public const SUBTAREA_SIN_INICIAR    = 'subtarea_sin_iniciar';
    public const TAREA_SIN_MOVIMIENTO    = 'tarea_sin_movimiento';
    public const SUBTAREA_SIN_MOVIMIENTO = 'subtarea_sin_movimiento';

    public const TAREA_TERMINADA         = 'tarea_terminada';
    public const TAREA_ACEPTADA          = 'tarea_aceptada';
    public const CAMBIO_ESTADO_TAREA     = 'cambio_estado_tarea';
    public const CAMBIO_ESTADO_PROYECTO  = 'cambio_estado_proyecto';

    public const TAREA_ESCALADA          = 'tarea_escalada';
    public const PROYECTO_EN_RIESGO      = 'proyecto_en_riesgo';

    /**
     * Ventanas de deduplicación en horas.
     * 0 = sin deduplicación (one-shot).
     */
    private static array $dedupWindows = [
        self::TAREA_ASIGNADA          => 1,
        self::TAREA_REASIGNADA        => 1,
        self::SUBTAREA_ASIGNADA       => 1,
        self::PROYECTO_ASIGNADO       => 1,
        self::TAREA_PROXIMA_VENCER    => 24,
        self::SUBTAREA_PROXIMA_VENCER => 24,
        self::TAREA_VENCIDA           => 24,
        self::SUBTAREA_VENCIDA        => 24,
        self::TAREA_SIN_INICIAR       => 48,
        self::SUBTAREA_SIN_INICIAR    => 48,
        self::TAREA_SIN_MOVIMIENTO    => 48,
        self::SUBTAREA_SIN_MOVIMIENTO => 48,
        self::TAREA_TERMINADA         => 0,
        self::TAREA_ACEPTADA          => 0,
        self::CAMBIO_ESTADO_TAREA     => 0,
        self::CAMBIO_ESTADO_PROYECTO  => 0,
        self::TAREA_ESCALADA          => 48,
        self::PROYECTO_EN_RIESGO      => 24,
    ];

    // ── Punto de entrada principal ────────────────────────────────────────────

    /**
     * Despacha una notificación para un evento de negocio.
     *
     * @param string $event      Constante del evento (self::TAREA_ASIGNADA, etc.)
     * @param array  $context    Datos del evento:
     *   - user_id       (int)     destinatario
     *   - user_nombre   (string)  nombre del destinatario
     *   - entity_type   (string)  'tarea' | 'subtarea' | 'proyecto'
     *   - entity_id     (int)     ID de la entidad
     *   - tarea         (string)  nombre de la tarea
     *   - subtarea      (string)  nombre de la subtarea (si aplica)
     *   - proyecto      (string)  nombre del proyecto
     *   - fecha_fin     (string)  fecha límite formateada
     *   - dias          (int|string) días relevantes
     *   - estado        (string)  etiqueta del estado (si aplica)
     *   - actor         (string)  quien realizó la acción (si aplica)
     *   - actor_id      (int)     ID del actor (opcional)
     */
    public static function dispatch(string $event, array $context): void
    {
        try {
            $userId     = (int)($context['user_id'] ?? 0);
            $entityType = $context['entity_type'] ?? 'tarea';
            $entityId   = (int)($context['entity_id'] ?? 0);

            if ($userId <= 0 || $entityId <= 0) {
                return; // Datos mínimos insuficientes
            }

            // 1. Verificar deduplicación
            if (self::isDuplicate($event, $entityType, $entityId, $userId)) {
                return;
            }

            // 2. Construir mensaje desde plantilla
            $msg = NotificacionTemplates::build($event, $context);
            if (!$msg) {
                return; // Evento sin plantilla registrada
            }

            // 3. Enviar in-app
            $notifId = self::sendInApp($userId, $event, $entityType, $entityId, $msg);

            // 4. Log in-app
            self::logToNotifLogs($event, $entityType, $entityId, $userId, 'in_app', $notifId, 'sent');

        } catch (\Throwable $e) {
            // Las notificaciones nunca deben romper el flujo principal
            error_log('[NotificacionService] Error en dispatch: ' . $e->getMessage());
        }
    }

    /**
     * Despacha a múltiples destinatarios (ej.: notificación a todos los admins).
     */
    public static function dispatchToAdmins(string $event, array $context): void
    {
        try {
            $db     = Database::getInstance();
            $admins = $db->fetchAll(
                "SELECT u.id, u.nombre_completo
                 FROM usuarios u
                 JOIN usuarios_roles ur ON ur.usuario_id = u.id
                 JOIN roles r ON r.id = ur.rol_id
                 WHERE u.activo = TRUE AND r.nombre IN ('ADMIN','GOD')"
            );

            foreach ($admins as $admin) {
                self::dispatch($event, array_merge($context, [
                    'user_id'     => $admin['id'],
                    'user_nombre' => $admin['nombre_completo'],
                ]));
            }
        } catch (\Throwable $e) {
            error_log('[NotificacionService] Error en dispatchToAdmins: ' . $e->getMessage());
        }
    }

    // ── Canales ───────────────────────────────────────────────────────────────

    /**
     * Crea notificación in-app en la tabla notifications.
     * Retorna el ID de la notificación creada.
     */
    private static function sendInApp(
        int $userId,
        string $event,
        string $entityType,
        int $entityId,
        array $msg
    ): ?int {
        try {
            $db   = Database::getInstance();
            $stmt = $db->query(
                'INSERT INTO notifications
                   (user_id, type, title, message, severity, channel, entity_type, entity_id)
                 VALUES (?,?,?,?,?,?,?,?) RETURNING id',
                [
                    $userId,
                    $event,
                    $msg['title'],
                    $msg['body'],
                    $msg['severity'],
                    'in_app',
                    $entityType,
                    $entityId,
                ]
            );
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[NotificacionService] Error in-app: ' . $e->getMessage());
            return null;
        }
    }

    // ── Deduplicación ─────────────────────────────────────────────────────────

    /**
     * Verifica si ya se envió esta notificación dentro de la ventana de tiempo.
     * Retorna true si es duplicado (no debe enviarse).
     */
    private static function isDuplicate(
        string $event,
        string $entityType,
        int $entityId,
        int $userId
    ): bool {
        $windowHours = self::$dedupWindows[$event] ?? 24;
        if ($windowHours === 0) {
            return false; // Sin deduplicación para este evento
        }

        try {
            $db  = Database::getInstance();
            $row = $db->fetchOne(
                "SELECT COUNT(*)::int AS total
                 FROM notification_logs
                 WHERE entity_type = ?
                   AND entity_id   = ?
                   AND event_type  = ?
                   AND user_id     = ?
                   AND status      = 'sent'
                   AND created_at  > NOW() - INTERVAL '1 hour' * ?",
                [$entityType, $entityId, $event, $userId, $windowHours]
            );
            return ($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            error_log('[NotificacionService] Error isDuplicate: ' . $e->getMessage());
            return false; // Si falla la verificación, permitir el envío
        }
    }

    // ── Auditoría ─────────────────────────────────────────────────────────────

    /**
     * Registra el resultado del envío en notification_logs.
     */
    private static function logToNotifLogs(
        string $event,
        string $entityType,
        int $entityId,
        int $userId,
        string $channel,
        ?int $notificationId,
        string $status,
        ?string $error = null
    ): void {
        try {
            Database::getInstance()->execute(
                'INSERT INTO notification_logs
                   (entity_type, entity_id, event_type, user_id, channel, notification_id, status, error_message)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$entityType, $entityId, $event, $userId, $channel, $notificationId, $status, $error]
            );
        } catch (\Throwable $e) {
            error_log('[NotificacionService] Error logToNotifLogs: ' . $e->getMessage());
        }
    }

    // ── Helpers de contexto ───────────────────────────────────────────────────

    /**
     * Construye el contexto estándar a partir de una fila de tarea.
     * Útil para los controladores y el scheduler.
     *
     * @param array $tarea        Fila de tareas con campos: id, nombre, fecha_fin,
     *                            usuario_asignado_id, usuario_asignado_nombre,
     *                            proyecto_nombre (si disponible)
     * @param string|null $actor  Nombre de quien dispara el evento
     */
    public static function contextFromTarea(array $tarea, ?string $actor = null): array
    {
        $fechaFin = $tarea['fecha_fin'] ?? null;
        $fechaStr = $fechaFin ? date('d/m/Y', strtotime($fechaFin)) : 'Sin fecha';

        $dias = 0;
        if ($fechaFin) {
            $diff = (new \DateTime($fechaFin))->diff(new \DateTime(date('Y-m-d')));
            $dias = $diff->invert ? -$diff->days : $diff->days; // negative = future
        }

        return [
            'entity_type' => 'tarea',
            'entity_id'   => (int)($tarea['id'] ?? 0),
            'user_id'     => (int)($tarea['usuario_asignado_id'] ?? 0),
            'user_nombre' => $tarea['usuario_asignado_nombre'] ?? $tarea['nombre_usuario'] ?? '',
            'tarea'       => $tarea['nombre'] ?? '',
            'proyecto'    => $tarea['proyecto_nombre'] ?? $tarea['proyecto'] ?? '',
            'fecha_fin'   => $fechaStr,
            'dias'        => (string)abs($dias),
            'actor'       => $actor ?? 'Sistema',
        ];
    }

    /**
     * Construye el contexto estándar a partir de una fila de subtarea.
     */
    public static function contextFromSubtarea(array $subtarea, array $tarea, ?string $actor = null): array
    {
        $ctx = self::contextFromTarea($tarea, $actor);
        $ctx['entity_type'] = 'subtarea';
        $ctx['entity_id']   = (int)($subtarea['id'] ?? 0);
        $ctx['subtarea']    = $subtarea['nombre'] ?? '';
        return $ctx;
    }
}
