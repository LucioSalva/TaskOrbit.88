<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SemaforoService — Calcula el nivel de semáforo (verde/amarillo/rojo/neutral)
 * para tareas y proyectos basado en fecha_fin, estado y actividad reciente.
 *
 * Cálculo dinámico — NO persiste en DB.
 *
 * Reglas de negocio:
 *  VERDE:    estado terminada/aceptada, o fecha_fin con holgura > AMARILLO_DIAS y actividad reciente
 *  AMARILLO: no completada y (fecha_fin ≤ AMARILLO_DIAS ahead) o (inactividad ≥ INACTIVIDAD_HORAS)
 *  ROJO:     no completada y (fecha_fin < hoy) o (inactividad ≥ INACTIVIDAD_ROJO_HORAS)
 *  NEUTRAL:  no completada, sin fecha_fin, actividad reciente
 *
 * Proyecto: hereda el peor semáforo entre su propio cálculo y el de sus tareas hijas.
 *
 * Config (.env):
 *  SEMAFORO_AMARILLO_DIAS=3
 *  SEMAFORO_INACTIVIDAD_HORAS=48
 *  SEMAFORO_INACTIVIDAD_ROJO_HORAS=120
 */
class SemaforoService
{
    public const VERDE    = 'verde';
    public const AMARILLO = 'amarillo';
    public const ROJO     = 'rojo';
    public const NEUTRAL  = 'neutral';

    /** Priority for worst-wins logic (higher = worse) */
    private const PRIORITY = [
        self::NEUTRAL  => 0,
        self::VERDE    => 1,
        self::AMARILLO => 2,
        self::ROJO     => 3,
    ];

    private static ?int $amarilloDias        = null;
    private static ?int $inactividadHoras    = null;
    private static ?int $inactividadRojoHoras = null;

    // =========================================================
    // CONFIG
    // =========================================================

    private static function amarilloDias(): int
    {
        if (self::$amarilloDias === null) {
            self::$amarilloDias = (int)(getenv('SEMAFORO_AMARILLO_DIAS') ?: 3);
        }
        return self::$amarilloDias;
    }

    private static function inactividadHoras(): int
    {
        if (self::$inactividadHoras === null) {
            self::$inactividadHoras = (int)(getenv('SEMAFORO_INACTIVIDAD_HORAS') ?: 48);
        }
        return self::$inactividadHoras;
    }

    private static function inactividadRojoHoras(): int
    {
        if (self::$inactividadRojoHoras === null) {
            self::$inactividadRojoHoras = (int)(getenv('SEMAFORO_INACTIVIDAD_ROJO_HORAS') ?: 120);
        }
        return self::$inactividadRojoHoras;
    }

    // =========================================================
    // CORE CALCULATION
    // =========================================================

    /**
     * Calculate semáforo for a single entity (tarea, subtarea, or proyecto row).
     *
     * Expects array with keys: 'estado', 'fecha_fin' (nullable string), 'updated_at'.
     *
     * @return string One of: verde, amarillo, rojo, neutral
     */
    public static function calcular(array $entity): string
    {
        $estado    = $entity['estado'] ?? 'por_hacer';
        $fechaFin  = $entity['fecha_fin'] ?? null;
        $updatedAt = $entity['updated_at'] ?? null;

        $terminados = ['terminada', 'aceptada'];

        // Completed => always verde
        if (in_array($estado, $terminados, true)) {
            return self::VERDE;
        }

        $today = new \DateTime('today');
        $now   = new \DateTime();

        // Hours since last activity
        $horasInactivo = 0;
        if ($updatedAt) {
            try {
                $updated = new \DateTime($updatedAt);
                $horasInactivo = max(0, (int)(($now->getTimestamp() - $updated->getTimestamp()) / 3600));
            } catch (\Throwable) {
                $horasInactivo = 0;
            }
        }

        // Days remaining until deadline (negative = overdue)
        $diasRestantes = null;
        if ($fechaFin) {
            try {
                $fin = new \DateTime($fechaFin);
                $fin->setTime(0, 0, 0);
                $diff = (int)$today->diff($fin)->days;
                $diasRestantes = $fin >= $today ? $diff : -$diff;
            } catch (\Throwable) {
                $diasRestantes = null;
            }
        }

        // ROJO: overdue OR extreme inactivity
        if ($diasRestantes !== null && $diasRestantes < 0) {
            return self::ROJO;
        }
        if ($horasInactivo >= self::inactividadRojoHoras()) {
            return self::ROJO;
        }

        // AMARILLO: approaching deadline OR moderate inactivity
        if ($diasRestantes !== null && $diasRestantes <= self::amarilloDias()) {
            return self::AMARILLO;
        }
        if ($horasInactivo >= self::inactividadHoras()) {
            return self::AMARILLO;
        }

        // VERDE: has fecha_fin with enough margin and recent activity
        if ($diasRestantes !== null && $diasRestantes > self::amarilloDias()) {
            return self::VERDE;
        }

        // NEUTRAL: no fecha_fin, not inactive enough to be amarillo/rojo
        return self::NEUTRAL;
    }

    /**
     * Calculate semáforo for a proyecto, considering child tareas (worst-wins).
     *
     * @param array $proyecto  Proyecto row
     * @param array $tareas    Tarea rows for this proyecto
     * @return string
     */
    public static function calcularProyecto(array $proyecto, array $tareas): string
    {
        $own = self::calcular($proyecto);

        if (empty($tareas)) {
            return $own;
        }

        $worst = $own;
        foreach ($tareas as $tarea) {
            $tareaLevel = self::calcular($tarea);
            if (self::PRIORITY[$tareaLevel] > self::PRIORITY[$worst]) {
                $worst = $tareaLevel;
            }
            // Short-circuit: can't get worse than rojo
            if ($worst === self::ROJO) {
                return self::ROJO;
            }
        }

        return $worst;
    }

    // =========================================================
    // VIEW HELPERS
    // =========================================================

    /**
     * Returns HTML badge string for a semáforo level.
     */
    public static function badge(string $nivel): string
    {
        $config = self::badgeConfig($nivel);
        return '<span class="badge semaforo-badge semaforo-' . htmlspecialchars($nivel, ENT_QUOTES) . '" title="' . htmlspecialchars($config['title'], ENT_QUOTES) . '">'
             . '<i class="bi ' . $config['icon'] . ' me-1"></i>'
             . htmlspecialchars($config['label'], ENT_QUOTES)
             . '</span>';
    }

    /**
     * Returns CSS risk class compatible with existing `.risk-*` classes.
     */
    public static function riskClass(string $nivel): string
    {
        return match ($nivel) {
            self::ROJO     => 'risk-overdue',
            self::AMARILLO => 'risk-warning',
            self::VERDE    => 'risk-ok',
            default        => '',
        };
    }

    /**
     * Returns timeline dot class.
     */
    public static function dotClass(string $nivel): string
    {
        return match ($nivel) {
            self::ROJO     => 'dot-overdue',
            self::AMARILLO => 'dot-warning',
            self::VERDE    => 'dot-ok',
            default        => 'dot-none',
        };
    }

    /**
     * Bootstrap text color class for inline coloring.
     */
    public static function textClass(string $nivel): string
    {
        return match ($nivel) {
            self::ROJO     => 'text-danger fw-semibold',
            self::AMARILLO => 'text-warning fw-semibold',
            self::VERDE    => 'text-success',
            default        => 'text-muted',
        };
    }

    private static function badgeConfig(string $nivel): array
    {
        return match ($nivel) {
            self::ROJO => [
                'label' => 'Crítico',
                'icon'  => 'bi-exclamation-triangle-fill',
                'title' => 'Vencido o con inactividad crítica',
            ],
            self::AMARILLO => [
                'label' => 'Atención',
                'icon'  => 'bi-exclamation-circle',
                'title' => 'Próximo a vencer o inactivo',
            ],
            self::VERDE => [
                'label' => 'Al día',
                'icon'  => 'bi-check-circle-fill',
                'title' => 'En tiempo y con actividad reciente',
            ],
            default => [
                'label' => 'Sin fecha',
                'icon'  => 'bi-dash-circle',
                'title' => 'Sin fecha límite definida',
            ],
        };
    }

    // =========================================================
    // BATCH HELPERS
    // =========================================================

    /**
     * Attach 'semaforo' key to every item in the array (modifies in-place).
     */
    public static function attachToAll(array &$items): void
    {
        foreach ($items as &$item) {
            $item['semaforo'] = self::calcular($item);
        }
        unset($item);
    }

    /**
     * Attach semáforo to each proyecto using hierarchical worst-wins logic.
     *
     * @param array $proyectos Array of proyecto rows (by reference)
     * @param array $tareasMap Map of proyecto_id => tarea rows
     */
    public static function attachToProyectos(array &$proyectos, array $tareasMap = []): void
    {
        foreach ($proyectos as &$p) {
            $pid = (int)($p['id'] ?? 0);
            $childTareas = $tareasMap[$pid] ?? [];
            $p['semaforo'] = self::calcularProyecto($p, $childTareas);
        }
        unset($p);
    }

    // =========================================================
    // SUMMARY FOR DASHBOARD
    // =========================================================

    /**
     * Count entities by semáforo level (entities must already have 'semaforo' key).
     *
     * @return array ['verde'=>int, 'amarillo'=>int, 'rojo'=>int, 'neutral'=>int]
     */
    public static function resumen(array $items): array
    {
        $counts = [self::VERDE => 0, self::AMARILLO => 0, self::ROJO => 0, self::NEUTRAL => 0];
        foreach ($items as $item) {
            $level = $item['semaforo'] ?? self::NEUTRAL;
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }
        return $counts;
    }
}
