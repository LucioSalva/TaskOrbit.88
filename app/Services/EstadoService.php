<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * EstadoService — Fuente única de verdad para recálculo automático de estados jerárquicos.
 *
 * REGLAS DE NEGOCIO (subtareas → tarea):
 *   - Todas "por_hacer"                      → tarea = "por_hacer"
 *   - Al menos una "haciendo"                → tarea = "haciendo"
 *   - Mezcla "por_hacer" + "terminada"       → tarea = "haciendo"  (avance parcial = en progreso)
 *   - Todas "terminada"                      → tarea = "terminada"
 *   - Estados administrativos (enterado, ocupado, aceptada) NO se propagan automáticamente.
 *
 * REGLAS DE NEGOCIO (tareas → proyecto):
 *   - Misma lógica que subtareas → tarea.
 *
 * RIESGO (en_riesgo):
 *   - fecha_fin < HOY && estado ∉ {terminada, aceptada} → en_riesgo = TRUE
 *   - Completados o sin fecha_fin                        → en_riesgo = FALSE
 *   - Umbral configurable: DIAS_ANTICIPACION_RIESGO (alerta preventiva antes del vencimiento)
 *
 * OVERRIDE MANUAL:
 *   - Se permite cambio manual de estado. El próximo evento de un hijo corregirá el estado
 *     si hay inconsistencia. Este comportamiento queda documentado en audit_logs.
 */
class EstadoService
{
    /** Estados base que participan en el cálculo automático de jerarquía. */
    private const ESTADOS_BASE = ['por_hacer', 'haciendo', 'terminada'];

    /**
     * Días de anticipación para marcar en_riesgo ANTES del vencimiento (alerta preventiva).
     * 0 = solo marca en_riesgo cuando ya está vencido.
     * Cambiar a, ej., 3 para alertar 3 días antes del vencimiento.
     */
    public const DIAS_ANTICIPACION_RIESGO = 0;

    // =========================================================
    // CÁLCULO PURO (sin escrituras a DB)
    // =========================================================

    /**
     * Calcula el estado que debería tener una tarea según sus subtareas activas.
     * Retorna null si la tarea no tiene subtareas (sin cambio automático).
     */
    public static function calcularEstadoTarea(int $tareaId): ?string
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT estado FROM subtareas WHERE tarea_id = ? AND deleted_at IS NULL',
            [$tareaId]
        );

        if (empty($rows)) {
            return null; // Sin subtareas → el estado se gestiona manualmente
        }

        return self::calcularDesdeHijos(array_column($rows, 'estado'));
    }

    /**
     * Calcula el estado que debería tener un proyecto según sus tareas activas.
     * Retorna null si el proyecto no tiene tareas (sin cambio automático).
     */
    public static function calcularEstadoProyecto(int $proyectoId): ?string
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT estado FROM tareas WHERE proyecto_id = ? AND deleted_at IS NULL',
            [$proyectoId]
        );

        if (empty($rows)) {
            return null; // Sin tareas → el estado se gestiona manualmente
        }

        return self::calcularDesdeHijos(array_column($rows, 'estado'));
    }

    /**
     * Aplica la regla de jerarquía de estados sobre un array de estados hijos.
     * Solo considera estados base; ignora estados administrativos en la lógica de cálculo.
     */
    private static function calcularDesdeHijos(array $estados): string
    {
        $relevantes = array_values(array_filter(
            $estados,
            fn($e) => in_array($e, self::ESTADOS_BASE, true)
        ));

        if (empty($relevantes)) {
            // Todos los hijos tienen estados administrativos (enterado/ocupado/aceptada).
            // Decisión: conservar "haciendo" como estado neutral de actividad.
            return 'haciendo';
        }

        $hayHaciendo  = in_array('haciendo',  $relevantes, true);
        $hayPorHacer  = in_array('por_hacer', $relevantes, true);
        $hayTerminada = in_array('terminada', $relevantes, true);

        // Regla 1: Al menos uno haciendo → haciendo
        if ($hayHaciendo) {
            return 'haciendo';
        }

        // Regla 2: Todos terminados
        if ($hayTerminada && !$hayPorHacer) {
            return 'terminada';
        }

        // Regla 3: Todos por hacer
        if ($hayPorHacer && !$hayTerminada) {
            return 'por_hacer';
        }

        // Regla 4: Mezcla por_hacer + terminada → avance parcial = haciendo
        return 'haciendo';
    }

    /**
     * Determina si una entidad debe marcarse como en_riesgo.
     */
    public static function calcularRiesgo(?string $fechaFin, string $estado): bool
    {
        if (in_array($estado, ['terminada', 'aceptada'], true)) {
            return false; // Completados nunca están en riesgo
        }

        if (!$fechaFin) {
            return false; // Sin fecha fin definida → no se puede determinar riesgo
        }

        $limite = self::DIAS_ANTICIPACION_RIESGO > 0
            ? date('Y-m-d', strtotime('+' . self::DIAS_ANTICIPACION_RIESGO . ' days'))
            : date('Y-m-d');

        return $fechaFin < $limite;
    }

    // =========================================================
    // RECÁLCULO + APLICACIÓN (queries de escritura)
    // =========================================================

    /**
     * Recalcula y aplica el estado de una tarea a partir de sus subtareas.
     * Retorna el estado calculado (puede ser igual al actual), o null si no hay subtareas.
     */
    public static function recalcularTarea(int $tareaId, ?int $actorId = null): ?string
    {
        $nuevoEstado = self::calcularEstadoTarea($tareaId);
        if ($nuevoEstado === null) {
            return null; // Sin subtareas: no tocar estado manual
        }

        $db     = Database::getInstance();
        $actual = $db->fetchOne(
            'SELECT estado, fecha_fin FROM tareas WHERE id = ? AND deleted_at IS NULL',
            [$tareaId]
        );

        if (!$actual) {
            return null;
        }

        try {
            $enRiesgo = self::calcularRiesgo($actual['fecha_fin'], $nuevoEstado);
            $db->execute('UPDATE tareas SET en_riesgo = ? WHERE id = ? AND deleted_at IS NULL', [$enRiesgo, $tareaId]);
        } catch (\Throwable $e) { /* columna en_riesgo no existe aún */ }

        if ($actual['estado'] !== $nuevoEstado) {
            $db->execute(
                'UPDATE tareas SET estado = ? WHERE id = ? AND deleted_at IS NULL',
                [$nuevoEstado, $tareaId]
            );
            self::auditarAutomatico($actorId, 'AUTO_TAREA_ESTADO', $tareaId, [
                'estado_anterior' => $actual['estado'],
                'estado_nuevo'    => $nuevoEstado,
                'motivo'          => 'propagacion_subtareas',
            ]);
        }

        return $nuevoEstado;
    }

    /**
     * Recalcula y aplica el estado de un proyecto a partir de sus tareas.
     * Retorna el estado calculado, o null si no hay tareas.
     */
    public static function recalcularProyecto(int $proyectoId, ?int $actorId = null): ?string
    {
        $nuevoEstado = self::calcularEstadoProyecto($proyectoId);
        if ($nuevoEstado === null) {
            return null; // Sin tareas: no tocar estado manual
        }

        $db     = Database::getInstance();
        $actual = $db->fetchOne(
            'SELECT estado, fecha_fin FROM proyectos WHERE id = ? AND deleted_at IS NULL',
            [$proyectoId]
        );

        if (!$actual) {
            return null;
        }

        try {
            $enRiesgo = self::calcularRiesgo($actual['fecha_fin'], $nuevoEstado);
            $db->execute('UPDATE proyectos SET en_riesgo = ? WHERE id = ? AND deleted_at IS NULL', [$enRiesgo, $proyectoId]);
        } catch (\Throwable $e) { /* columna en_riesgo no existe aún */ }

        if ($actual['estado'] !== $nuevoEstado) {
            $db->execute(
                'UPDATE proyectos SET estado = ? WHERE id = ? AND deleted_at IS NULL',
                [$nuevoEstado, $proyectoId]
            );
            self::auditarAutomatico($actorId, 'AUTO_PROYECTO_ESTADO', $proyectoId, [
                'estado_anterior' => $actual['estado'],
                'estado_nuevo'    => $nuevoEstado,
                'motivo'          => 'propagacion_tareas',
            ]);
        }

        return $nuevoEstado;
    }

    /**
     * Refresca solo en_riesgo de una tarea (sin cambiar estado).
     * Útil después de editar fecha_fin o al cambio manual de estado.
     */
    public static function refreshRiesgoTarea(int $tareaId): void
    {
        $db   = Database::getInstance();
        $row  = $db->fetchOne(
            'SELECT estado, fecha_fin FROM tareas WHERE id = ? AND deleted_at IS NULL',
            [$tareaId]
        );
        if (!$row) return;

        try {
            $enRiesgo = self::calcularRiesgo($row['fecha_fin'], $row['estado']);
            $db->execute('UPDATE tareas SET en_riesgo = ? WHERE id = ?', [$enRiesgo, $tareaId]);
        } catch (\Throwable $e) { /* columna en_riesgo no existe aún */ }
    }

    /**
     * Refresca solo en_riesgo de un proyecto (sin cambiar estado).
     */
    public static function refreshRiesgoProyecto(int $proyectoId): void
    {
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT estado, fecha_fin FROM proyectos WHERE id = ? AND deleted_at IS NULL',
            [$proyectoId]
        );
        if (!$row) return;

        try {
            $enRiesgo = self::calcularRiesgo($row['fecha_fin'], $row['estado']);
            $db->execute('UPDATE proyectos SET en_riesgo = ? WHERE id = ?', [$enRiesgo, $proyectoId]);
        } catch (\Throwable $e) { /* columna en_riesgo no existe aún */ }
    }

    // =========================================================
    // PROPAGACIÓN EN CADENA
    // =========================================================

    /**
     * Propagación completa desde una subtarea: subtarea → tarea → proyecto.
     * Llamar DESPUÉS de actualizar el estado de la subtarea en DB.
     *
     * @return array{tarea_id:int, tarea_estado:string|null, proyecto_id:int, proyecto_estado:string|null}
     */
    public static function propagarDesdeSubtarea(int $subtareaId, ?int $actorId = null): array
    {
        $db  = Database::getInstance();
        $sub = $db->fetchOne(
            'SELECT s.tarea_id, t.proyecto_id
             FROM subtareas s
             JOIN tareas t ON t.id = s.tarea_id
             WHERE s.id = ?',
            [$subtareaId]
        );

        if (!$sub) {
            return [];
        }

        $tareaId     = (int)$sub['tarea_id'];
        $proyectoId  = (int)$sub['proyecto_id'];

        $tareaEstado    = self::recalcularTarea($tareaId, $actorId);
        $proyectoEstado = self::recalcularProyecto($proyectoId, $actorId);

        return [
            'tarea_id'        => $tareaId,
            'tarea_estado'    => $tareaEstado,
            'proyecto_id'     => $proyectoId,
            'proyecto_estado' => $proyectoEstado,
        ];
    }

    /**
     * Propagación desde una tarea hacia el proyecto padre.
     * Llamar DESPUÉS de actualizar el estado de la tarea en DB.
     *
     * @return array{proyecto_id:int, proyecto_estado:string|null}
     */
    public static function propagarDesdeTarea(int $tareaId, ?int $actorId = null): array
    {
        $db    = Database::getInstance();
        $tarea = $db->fetchOne(
            'SELECT proyecto_id, estado, fecha_fin FROM tareas WHERE id = ? AND deleted_at IS NULL',
            [$tareaId]
        );

        if (!$tarea) {
            return [];
        }

        // Actualizar en_riesgo de la propia tarea según su nuevo estado
        try {
            $enRiesgo = self::calcularRiesgo($tarea['fecha_fin'], $tarea['estado']);
            $db->execute('UPDATE tareas SET en_riesgo = ? WHERE id = ?', [$enRiesgo, $tareaId]);
        } catch (\Throwable $e) { /* columna en_riesgo no existe aún */ }

        $proyectoId     = (int)$tarea['proyecto_id'];
        $proyectoEstado = self::recalcularProyecto($proyectoId, $actorId);

        return [
            'proyecto_id'     => $proyectoId,
            'proyecto_estado' => $proyectoEstado,
        ];
    }

    /**
     * Propaga desde una tarea recalculando primero la tarea (desde sus subtareas) y luego el proyecto.
     * Usar cuando se crea o elimina una subtarea (sin subtareaId disponible).
     *
     * @return array{tarea_estado:string|null, proyecto_id:int, proyecto_estado:string|null}
     */
    public static function propagarDesdeTareaConSubtareas(int $tareaId, ?int $actorId = null): array
    {
        $db    = Database::getInstance();
        $tarea = $db->fetchOne(
            'SELECT proyecto_id FROM tareas WHERE id = ? AND deleted_at IS NULL',
            [$tareaId]
        );

        if (!$tarea) {
            return [];
        }

        $tareaEstado    = self::recalcularTarea($tareaId, $actorId);
        $proyectoId     = (int)$tarea['proyecto_id'];
        $proyectoEstado = self::recalcularProyecto($proyectoId, $actorId);

        return [
            'tarea_id'        => $tareaId,
            'tarea_estado'    => $tareaEstado,
            'proyecto_id'     => $proyectoId,
            'proyecto_estado' => $proyectoEstado,
        ];
    }

    // =========================================================
    // AUDITORÍA
    // =========================================================

    private static function auditarAutomatico(?int $actorId, string $action, int $targetId, array $details): void
    {
        try {
            Database::getInstance()->execute(
                'INSERT INTO audit_logs (actor_id, action, target_id, details, ip_address, method, endpoint)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $actorId,
                    $action,
                    $targetId,
                    json_encode($details),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    'AUTO',
                    $_SERVER['REQUEST_URI'] ?? null,
                ]
            );
        } catch (\Throwable) {}
    }
}
