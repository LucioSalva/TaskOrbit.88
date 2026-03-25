<?php
declare(strict_types=1);

namespace App\Services;

/**
 * NotificacionTemplates — Registro centralizado de plantillas de mensajes.
 *
 * Cada evento tiene:
 *   - title:    título de la notificación interna
 *   - body:     cuerpo del mensaje (in-app y WhatsApp)
 *   - severity: info | success | warning | danger
 *
 * Las variables {placeholder} se reemplazan con los valores del contexto.
 * Contexto estándar esperado por evento:
 *   {nombre}          — nombre completo del destinatario
 *   {tarea}           — nombre de la tarea
 *   {subtarea}        — nombre de la subtarea
 *   {proyecto}        — nombre del proyecto
 *   {fecha_fin}       — fecha límite formateada
 *   {dias}            — días de diferencia (vencidos o restantes)
 *   {estado}          — etiqueta del estado
 *   {actor}           — quien realizó la acción
 */
class NotificacionTemplates
{
    /** @var array<string, array{title: string, body: string, severity: string}> */
    private static array $templates = [

        // ── ASIGNACIÓN ─────────────────────────────────────────────────
        'tarea_asignada' => [
            'title'    => 'Nueva tarea asignada',
            'body'     => 'Hola {nombre}, se te asignó la tarea "{tarea}" en el proyecto "{proyecto}". Fecha límite: {fecha_fin}.',
            'severity' => 'info',
        ],
        'tarea_reasignada' => [
            'title'    => 'Tarea reasignada',
            'body'     => 'Hola {nombre}, ahora eres responsable de la tarea "{tarea}" en "{proyecto}". Fecha límite: {fecha_fin}.',
            'severity' => 'info',
        ],
        'subtarea_asignada' => [
            'title'    => 'Nueva subtarea en tu tarea',
            'body'     => 'Hola {nombre}, se agregó la subtarea "{subtarea}" a la tarea "{tarea}" del proyecto "{proyecto}".',
            'severity' => 'info',
        ],
        'proyecto_asignado' => [
            'title'    => 'Nuevo proyecto asignado',
            'body'     => 'Hola {nombre}, se te asignó el proyecto "{proyecto}". Fecha límite: {fecha_fin}.',
            'severity' => 'info',
        ],

        // ── VENCIMIENTO ────────────────────────────────────────────────
        'tarea_proxima_vencer' => [
            'title'    => 'Tarea próxima a vencer',
            'body'     => 'Hola {nombre}, la tarea "{tarea}" vence en {dias} día(s) ({fecha_fin}). Revisa su avance.',
            'severity' => 'warning',
        ],
        'subtarea_proxima_vencer' => [
            'title'    => 'Subtarea próxima a vencer',
            'body'     => 'Hola {nombre}, la subtarea "{subtarea}" de la tarea "{tarea}" vence en {dias} día(s) ({fecha_fin}).',
            'severity' => 'warning',
        ],
        'tarea_vencida' => [
            'title'    => 'Tarea vencida',
            'body'     => 'Atención {nombre}: la tarea "{tarea}" venció hace {dias} día(s) y aún no está terminada.',
            'severity' => 'danger',
        ],
        'subtarea_vencida' => [
            'title'    => 'Subtarea vencida',
            'body'     => 'Atención {nombre}: la subtarea "{subtarea}" de "{tarea}" venció hace {dias} día(s) y sigue pendiente.',
            'severity' => 'danger',
        ],

        // ── INACTIVIDAD ────────────────────────────────────────────────
        'tarea_sin_iniciar' => [
            'title'    => 'Tarea sin iniciar',
            'body'     => 'Hola {nombre}, la tarea "{tarea}" lleva {dias} día(s) asignada y aún no se ha iniciado.',
            'severity' => 'warning',
        ],
        'subtarea_sin_iniciar' => [
            'title'    => 'Subtarea sin iniciar',
            'body'     => 'Hola {nombre}, la subtarea "{subtarea}" lleva {dias} día(s) sin iniciarse.',
            'severity' => 'warning',
        ],
        'tarea_sin_movimiento' => [
            'title'    => 'Tarea sin actividad reciente',
            'body'     => 'Hola {nombre}, la tarea "{tarea}" no registra actividad desde hace {dias} día(s). ¿Necesitas apoyo?',
            'severity' => 'warning',
        ],
        'subtarea_sin_movimiento' => [
            'title'    => 'Subtarea sin actividad',
            'body'     => 'Hola {nombre}, la subtarea "{subtarea}" de "{tarea}" no tiene actividad desde hace {dias} día(s).',
            'severity' => 'warning',
        ],

        // ── ESTADO ────────────────────────────────────────────────────
        'tarea_terminada' => [
            'title'    => 'Tarea completada',
            'body'     => 'La tarea "{tarea}" del proyecto "{proyecto}" fue marcada como terminada por {actor}.',
            'severity' => 'success',
        ],
        'tarea_aceptada' => [
            'title'    => 'Tarea aceptada',
            'body'     => 'Hola {nombre}, tu tarea "{tarea}" fue aceptada. ¡Buen trabajo!',
            'severity' => 'success',
        ],
        'cambio_estado_tarea' => [
            'title'    => 'Estado de tarea actualizado',
            'body'     => 'La tarea "{tarea}" cambió a estado "{estado}" en el proyecto "{proyecto}".',
            'severity' => 'info',
        ],
        'cambio_estado_proyecto' => [
            'title'    => 'Estado de proyecto actualizado',
            'body'     => 'El proyecto "{proyecto}" cambió a estado "{estado}".',
            'severity' => 'info',
        ],

        // ── ESCALAMIENTO ───────────────────────────────────────────────
        'tarea_escalada' => [
            'title'    => 'Tarea requiere atención',
            'body'     => 'ALERTA: La tarea "{tarea}" en "{proyecto}" lleva {dias} día(s) vencida sin resolución. Responsable: {nombre}.',
            'severity' => 'danger',
        ],
        'proyecto_en_riesgo' => [
            'title'    => 'Proyecto en riesgo',
            'body'     => 'El proyecto "{proyecto}" está en riesgo: tiene {dias} tarea(s) vencida(s) sin resolver.',
            'severity' => 'danger',
        ],

        // ── ACCIONES DE USUARIO ───────────────────────────────────────
        'user_accion_tarea' => [
            'title'    => 'Acción de usuario en tarea',
            'body'     => '{actor} realizó un cambio en la tarea "{tarea}" del proyecto "{proyecto}".',
            'severity' => 'info',
        ],
    ];

    /**
     * Obtiene la plantilla de un evento dado.
     * Retorna null si el evento no existe.
     */
    public static function get(string $event): ?array
    {
        return self::$templates[$event] ?? null;
    }

    /**
     * Construye el mensaje sustituyendo {placeholders} con valores del contexto.
     * Retorna array con title, body y severity ya procesados.
     */
    public static function build(string $event, array $context): ?array
    {
        $tpl = self::get($event);
        if (!$tpl) {
            return null;
        }

        $defaults = [
            'nombre'    => 'Usuario',
            'tarea'     => '—',
            'subtarea'  => '—',
            'proyecto'  => '—',
            'fecha_fin' => 'Sin fecha',
            'dias'      => '0',
            'estado'    => '—',
            'actor'     => 'Sistema',
        ];

        $vars = array_merge($defaults, $context);
        $keys = array_map(fn($k) => '{' . $k . '}', array_keys($vars));
        $vals = array_values($vars);

        return [
            'title'    => str_replace($keys, $vals, $tpl['title']),
            'body'     => str_replace($keys, $vals, $tpl['body']),
            'severity' => $tpl['severity'],
        ];
    }

    /**
     * Lista de todos los eventos registrados (para documentación/debug).
     */
    public static function allEvents(): array
    {
        return array_keys(self::$templates);
    }
}
