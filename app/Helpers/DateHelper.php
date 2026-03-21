<?php
declare(strict_types=1);

namespace App\Helpers;

class DateHelper
{
    public static function calculateEstimatedMinutes(?string $fechaInicio, ?string $fechaFin): ?int
    {
        if (!$fechaInicio || !$fechaFin) return null;
        try {
            $start = new \DateTime($fechaInicio);
            $end   = new \DateTime($fechaFin);
            if ($end <= $start) return null;
            $diff = $end->diff($start);
            return ($diff->days * 8 * 60); // 8 working hours per day
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function formatDate(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) return '';
        try {
            $d = new \DateTime($date);
            return $d->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }

    public static function daysRemaining(?string $fechaFin): int
    {
        if (!$fechaFin) return 0;
        try {
            $now = new \DateTime('today');
            $end = new \DateTime($fechaFin);
            $diff = (int)$now->diff($end)->format('%r%a');
            return $diff;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function isOverdue(?string $fechaFin, string $estado = ''): bool
    {
        if (!$fechaFin) return false;
        if ($estado === 'terminada') return false;
        return self::daysRemaining($fechaFin) < 0;
    }

    public static function humanDate(?string $date): string
    {
        if (!$date) return '';
        $days = self::daysRemaining($date);
        if ($days === 0) return 'hoy';
        if ($days === 1) return 'mañana';
        if ($days === -1) return 'ayer';
        if ($days > 0) return "en $days días";
        return 'hace ' . abs($days) . ' días';
    }

    public static function formatDatetime(?string $datetime): string
    {
        if (!$datetime) return '';
        try {
            $d = new \DateTime($datetime);
            return $d->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return '';
        }
    }
}
