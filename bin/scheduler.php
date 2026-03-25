<?php
/**
 * TaskOrbit — Scheduler de Notificaciones
 *
 * Proceso periódico que evalúa eventos programados y despacha notificaciones.
 * Diseñado para ejecutarse vía cron cada hora.
 *
 * Cron sugerido:
 *   0 * * * * php C:\laragon\www\TaskOrbit.88\bin\scheduler.php >> storage/logs/scheduler.log 2>&1
 *
 * ─── IDEMPOTENCIA ───────────────────────────────────────────────────────────
 *  Cada evento usa deduplicación por ventana de tiempo en NotificacionService.
 *  Ejecutar el scheduler múltiples veces no genera duplicados.
 *
 * ─── EVENTOS EVALUADOS ──────────────────────────────────────────────────────
 *  1. Tareas próximas a vencer       (NOTIFY_UPCOMING_DUE_HOURS, default 24h)
 *  2. Tareas vencidas                (no iniciadas/en proceso, fecha_fin < hoy)
 *  3. Tareas sin iniciar             (NOTIFY_SIN_INICIAR_HOURS, default 48h)
 *  4. Tareas sin movimiento          (NOTIFY_INACTIVITY_HOURS, default 168h = 7 días)
 *  5. Subtareas próximas a vencer    (mismo umbral)
 *  6. Subtareas vencidas
 *  7. Escalamiento                   (NOTIFY_ESCALATION_HOURS, default 72h)
 *  8. Proyectos en riesgo
 *
 * ─── USO ────────────────────────────────────────────────────────────────────
 *  php bin/scheduler.php             (desde raíz del proyecto)
 *  php bin/scheduler.php --dry-run   (solo muestra qué notificaría, sin enviar)
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────
// Solo puede ejecutarse desde CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.');
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH',  BASE_PATH . '/app');

// Cargar .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

// Timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Mexico_City');

// Autoloader PSR-4
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Services\NotificacionService;

// ── Configuración desde .env ──────────────────────────────────────────────────
$dryRun           = in_array('--dry-run', $argv ?? [], true);
$upcomingHours    = max(1, (int)(getenv('NOTIFY_UPCOMING_DUE_HOURS') ?: 24));
$sinIniciarHours  = max(1, (int)(getenv('NOTIFY_SIN_INICIAR_HOURS') ?: 48));
$inactivityHours  = max(1, (int)(getenv('NOTIFY_INACTIVITY_HOURS') ?: 168));
$escalationHours  = max(1, (int)(getenv('NOTIFY_ESCALATION_HOURS') ?: 72));

// ── Logger simple ─────────────────────────────────────────────────────────────
function logMsg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

logMsg('=== Scheduler TaskOrbit iniciado' . ($GLOBALS['dryRun'] ? ' [DRY-RUN]' : '') . ' ===');

$db      = Database::getInstance();
$stats   = ['tareas_vencidas' => 0, 'proximas_vencer' => 0, 'sin_iniciar' => 0,
            'sin_movimiento' => 0, 'escaladas' => 0, 'proyectos_riesgo' => 0];

// ────────────────────────────────────────────────────────────────────────────
// Función: obtener asignado efectivo de una tarea (con teléfono)
// ────────────────────────────────────────────────────────────────────────────
function getTareasConAsignado(Database $db, string $extraWhere, array $extraParams = []): array
{
    return $db->fetchAll(
        "SELECT
            t.id,
            t.nombre,
            t.estado,
            t.fecha_fin,
            t.created_at,
            t.updated_at,
            p.nombre AS proyecto_nombre,
            COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
            COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
            COALESCE(ua.telefono, up.telefono) AS telefono
         FROM tareas t
         JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
         LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
         LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
         WHERE t.deleted_at IS NULL
           AND t.estado NOT IN ('terminada','aceptada')
           $extraWhere",
        $extraParams
    );
}

function getSubtareasConAsignado(Database $db, string $extraWhere, array $extraParams = []): array
{
    return $db->fetchAll(
        "SELECT
            s.id,
            s.nombre,
            s.estado,
            s.fecha_fin,
            s.created_at,
            s.updated_at,
            t.nombre AS tarea_nombre,
            t.id     AS tarea_id,
            p.nombre AS proyecto_nombre,
            COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
            COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
            COALESCE(ua.telefono, up.telefono) AS telefono
         FROM subtareas s
         JOIN tareas t ON t.id = s.tarea_id AND t.deleted_at IS NULL
         JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
         LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
         LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
         WHERE s.deleted_at IS NULL
           AND s.estado NOT IN ('terminada','aceptada')
           $extraWhere",
        $extraParams
    );
}

// ────────────────────────────────────────────────────────────────────────────
// 1. TAREAS PRÓXIMAS A VENCER
// ────────────────────────────────────────────────────────────────────────────
logMsg("Evaluando tareas próximas a vencer (ventana: {$upcomingHours}h)...");
$proximasVencer = getTareasConAsignado($db,
    "AND t.fecha_fin IS NOT NULL
     AND t.fecha_fin >= CURRENT_DATE
     AND t.fecha_fin <= CURRENT_DATE + INTERVAL '1 hour' * ?",
    [$upcomingHours / 24.0]  // convertir horas a fracción de día para la comparación DATE
);

// Re-query con manejo correcto de horas
$proximasVencer = $db->fetchAll(
    "SELECT
        t.id, t.nombre, t.estado, t.fecha_fin, t.created_at, t.updated_at,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM tareas t
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE t.deleted_at IS NULL
       AND t.estado NOT IN ('terminada','aceptada')
       AND t.fecha_fin IS NOT NULL
       AND t.fecha_fin >= CURRENT_DATE
       AND t.fecha_fin <= CURRENT_DATE + INTERVAL '1 day' * ?",
    [(int)ceil($upcomingHours / 24)]
);

foreach ($proximasVencer as $tarea) {
    if (!$tarea['usuario_asignado_id']) continue;
    $dias = (new \DateTime($tarea['fecha_fin']))->diff(new \DateTime(date('Y-m-d')))->days;
    $ctx  = NotificacionService::contextFromTarea(array_merge($tarea, [
        'dias' => $dias, 'usuario_asignado_nombre' => $tarea['usuario_asignado_nombre'],
    ]));
    logMsg("  → Próxima a vencer: [{$tarea['id']}] {$tarea['nombre']} (en {$dias}d)");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::TAREA_PROXIMA_VENCER, $ctx);
    }
    $stats['proximas_vencer']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 2. TAREAS VENCIDAS
// ────────────────────────────────────────────────────────────────────────────
logMsg('Evaluando tareas vencidas...');
$vencidas = $db->fetchAll(
    "SELECT
        t.id, t.nombre, t.estado, t.fecha_fin, t.created_at, t.updated_at,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM tareas t
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE t.deleted_at IS NULL
       AND t.estado NOT IN ('terminada','aceptada')
       AND t.fecha_fin IS NOT NULL
       AND t.fecha_fin < CURRENT_DATE"
);

foreach ($vencidas as $tarea) {
    if (!$tarea['usuario_asignado_id']) continue;
    $dias = (new \DateTime(date('Y-m-d')))->diff(new \DateTime($tarea['fecha_fin']))->days;
    $ctx  = NotificacionService::contextFromTarea(array_merge($tarea, ['dias' => $dias]));
    logMsg("  → Vencida: [{$tarea['id']}] {$tarea['nombre']} (hace {$dias}d)");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::TAREA_VENCIDA, $ctx);
    }
    $stats['tareas_vencidas']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 3. TAREAS SIN INICIAR
// ────────────────────────────────────────────────────────────────────────────
logMsg("Evaluando tareas sin iniciar (umbral: {$sinIniciarHours}h)...");
$sinIniciar = $db->fetchAll(
    "SELECT
        t.id, t.nombre, t.estado, t.fecha_fin, t.created_at, t.updated_at,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM tareas t
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE t.deleted_at IS NULL
       AND t.estado = 'por_hacer'
       AND t.created_at < NOW() - INTERVAL '1 hour' * ?",
    [$sinIniciarHours]
);

foreach ($sinIniciar as $tarea) {
    if (!$tarea['usuario_asignado_id']) continue;
    $diasSinIniciar = (int)round((time() - strtotime($tarea['created_at'])) / 86400);
    $ctx = NotificacionService::contextFromTarea(array_merge($tarea, ['dias' => $diasSinIniciar]));
    logMsg("  → Sin iniciar: [{$tarea['id']}] {$tarea['nombre']} ({$diasSinIniciar}d)");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::TAREA_SIN_INICIAR, $ctx);
    }
    $stats['sin_iniciar']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 4. TAREAS SIN MOVIMIENTO
// ────────────────────────────────────────────────────────────────────────────
logMsg("Evaluando tareas sin movimiento (umbral: {$inactivityHours}h)...");
$sinMovimiento = $db->fetchAll(
    "SELECT
        t.id, t.nombre, t.estado, t.fecha_fin, t.created_at, t.updated_at,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM tareas t
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE t.deleted_at IS NULL
       AND t.estado NOT IN ('terminada','aceptada','por_hacer')
       AND t.updated_at < NOW() - INTERVAL '1 hour' * ?",
    [$inactivityHours]
);

foreach ($sinMovimiento as $tarea) {
    if (!$tarea['usuario_asignado_id']) continue;
    $diasSinMov = (int)round((time() - strtotime($tarea['updated_at'])) / 86400);
    $ctx = NotificacionService::contextFromTarea(array_merge($tarea, ['dias' => $diasSinMov]));
    logMsg("  → Sin movimiento: [{$tarea['id']}] {$tarea['nombre']} ({$diasSinMov}d)");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::TAREA_SIN_MOVIMIENTO, $ctx);
    }
    $stats['sin_movimiento']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 5. SUBTAREAS PRÓXIMAS A VENCER
// ────────────────────────────────────────────────────────────────────────────
logMsg('Evaluando subtareas próximas a vencer...');
$subProximas = $db->fetchAll(
    "SELECT
        s.id, s.nombre, s.estado, s.fecha_fin, s.created_at, s.updated_at,
        t.nombre AS tarea_nombre, t.id AS tarea_id,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM subtareas s
     JOIN tareas t ON t.id = s.tarea_id AND t.deleted_at IS NULL
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE s.deleted_at IS NULL
       AND s.estado NOT IN ('terminada','aceptada')
       AND s.fecha_fin IS NOT NULL
       AND s.fecha_fin >= CURRENT_DATE
       AND s.fecha_fin <= CURRENT_DATE + INTERVAL '1 day' * ?",
    [(int)ceil($upcomingHours / 24)]
);

foreach ($subProximas as $sub) {
    if (!$sub['usuario_asignado_id']) continue;
    $dias = (new \DateTime($sub['fecha_fin']))->diff(new \DateTime(date('Y-m-d')))->days;
    $ctx  = [
        'entity_type'   => 'subtarea',
        'entity_id'     => (int)$sub['id'],
        'user_id'       => (int)$sub['usuario_asignado_id'],
        'user_nombre'   => $sub['usuario_asignado_nombre'] ?? '',
        'user_telefono' => $sub['telefono'] ?? '',
        'subtarea'      => $sub['nombre'],
        'tarea'         => $sub['tarea_nombre'],
        'proyecto'      => $sub['proyecto_nombre'],
        'fecha_fin'     => date('d/m/Y', strtotime($sub['fecha_fin'])),
        'dias'          => (string)$dias,
        'actor'         => 'Sistema',
    ];
    logMsg("  → Sub próxima a vencer: [{$sub['id']}] {$sub['nombre']}");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::SUBTAREA_PROXIMA_VENCER, $ctx);
    }
    $stats['proximas_vencer']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 6. SUBTAREAS VENCIDAS
// ────────────────────────────────────────────────────────────────────────────
logMsg('Evaluando subtareas vencidas...');
$subVencidas = $db->fetchAll(
    "SELECT
        s.id, s.nombre, s.estado, s.fecha_fin, s.created_at, s.updated_at,
        t.nombre AS tarea_nombre, t.id AS tarea_id,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre,
        COALESCE(ua.telefono, up.telefono) AS telefono
     FROM subtareas s
     JOIN tareas t ON t.id = s.tarea_id AND t.deleted_at IS NULL
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE s.deleted_at IS NULL
       AND s.estado NOT IN ('terminada','aceptada')
       AND s.fecha_fin IS NOT NULL
       AND s.fecha_fin < CURRENT_DATE"
);

foreach ($subVencidas as $sub) {
    if (!$sub['usuario_asignado_id']) continue;
    $dias = (new \DateTime(date('Y-m-d')))->diff(new \DateTime($sub['fecha_fin']))->days;
    $ctx  = [
        'entity_type'   => 'subtarea',
        'entity_id'     => (int)$sub['id'],
        'user_id'       => (int)$sub['usuario_asignado_id'],
        'user_nombre'   => $sub['usuario_asignado_nombre'] ?? '',
        'user_telefono' => $sub['telefono'] ?? '',
        'subtarea'      => $sub['nombre'],
        'tarea'         => $sub['tarea_nombre'],
        'proyecto'      => $sub['proyecto_nombre'],
        'fecha_fin'     => date('d/m/Y', strtotime($sub['fecha_fin'])),
        'dias'          => (string)$dias,
        'actor'         => 'Sistema',
    ];
    logMsg("  → Sub vencida: [{$sub['id']}] {$sub['nombre']}");
    if (!$dryRun) {
        NotificacionService::dispatch(NotificacionService::SUBTAREA_VENCIDA, $ctx);
    }
    $stats['tareas_vencidas']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 7. ESCALAMIENTO: tareas vencidas por más de X horas sin resolución
// ────────────────────────────────────────────────────────────────────────────
logMsg("Evaluando escalamiento (umbral: {$escalationHours}h de retraso)...");
$escalacionDays = (int)ceil($escalationHours / 24);
$escaladas = $db->fetchAll(
    "SELECT
        t.id, t.nombre, t.estado, t.fecha_fin, t.created_at, t.updated_at,
        p.nombre AS proyecto_nombre,
        COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
        COALESCE(ua.nombre_completo, up.nombre_completo) AS usuario_asignado_nombre
     FROM tareas t
     JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
     LEFT JOIN usuarios ua ON ua.id = t.usuario_asignado_id
     LEFT JOIN usuarios up ON up.id = p.usuario_asignado_id
     WHERE t.deleted_at IS NULL
       AND t.estado NOT IN ('terminada','aceptada')
       AND t.fecha_fin IS NOT NULL
       AND t.fecha_fin < CURRENT_DATE - INTERVAL '1 day' * ?",
    [$escalacionDays]
);

foreach ($escaladas as $tarea) {
    $diasRetraso = (new \DateTime(date('Y-m-d')))->diff(new \DateTime($tarea['fecha_fin']))->days;
    $responsable = $tarea['usuario_asignado_nombre'] ?? 'Sin responsable';
    $ctx = [
        'entity_type' => 'tarea',
        'entity_id'   => (int)$tarea['id'],
        'tarea'       => $tarea['nombre'],
        'proyecto'    => $tarea['proyecto_nombre'],
        'dias'        => (string)$diasRetraso,
        'nombre'      => $responsable,
        'actor'       => 'Sistema',
        'fecha_fin'   => date('d/m/Y', strtotime($tarea['fecha_fin'])),
    ];
    logMsg("  → Escalando: [{$tarea['id']}] {$tarea['nombre']} ({$diasRetraso}d de retraso)");
    if (!$dryRun) {
        // Notifica a admins y GOD
        NotificacionService::dispatchToAdmins(NotificacionService::TAREA_ESCALADA, $ctx);
    }
    $stats['escaladas']++;
}

// ────────────────────────────────────────────────────────────────────────────
// 8. PROYECTOS EN RIESGO
// ────────────────────────────────────────────────────────────────────────────
logMsg('Evaluando proyectos en riesgo...');
$proyectosRiesgo = $db->fetchAll(
    "SELECT
        p.id, p.nombre AS proyecto_nombre,
        COUNT(t.id) FILTER (
            WHERE t.fecha_fin < CURRENT_DATE AND t.estado NOT IN ('terminada','aceptada')
        ) AS tareas_vencidas
     FROM proyectos p
     LEFT JOIN tareas t ON t.proyecto_id = p.id AND t.deleted_at IS NULL
     WHERE p.deleted_at IS NULL
       AND p.estado NOT IN ('terminada','aceptada')
     GROUP BY p.id, p.nombre
     HAVING COUNT(t.id) FILTER (
         WHERE t.fecha_fin < CURRENT_DATE AND t.estado NOT IN ('terminada','aceptada')
     ) > 0"
);

foreach ($proyectosRiesgo as $proyecto) {
    $ctx = [
        'entity_type' => 'proyecto',
        'entity_id'   => (int)$proyecto['id'],
        'proyecto'    => $proyecto['proyecto_nombre'],
        'dias'        => (string)$proyecto['tareas_vencidas'],
        'nombre'      => 'Equipo',
        'actor'       => 'Sistema',
        'fecha_fin'   => '—',
    ];
    logMsg("  → Proyecto en riesgo: [{$proyecto['id']}] {$proyecto['proyecto_nombre']} ({$proyecto['tareas_vencidas']} vencidas)");
    if (!$dryRun) {
        NotificacionService::dispatchToAdmins(NotificacionService::PROYECTO_EN_RIESGO, $ctx);
    }
    $stats['proyectos_riesgo']++;
}

// ── Resumen ──────────────────────────────────────────────────────────────────
logMsg('=== Resumen de ejecución ===');
foreach ($stats as $key => $count) {
    logMsg("  {$key}: {$count}");
}
logMsg('=== Scheduler finalizado ===');
