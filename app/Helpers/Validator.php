<?php
declare(strict_types=1);

namespace App\Helpers;

class Validator
{
    private static array $validEstados   = ['por_hacer', 'haciendo', 'terminada', 'enterado', 'ocupado', 'aceptada'];
    private static array $validPrioridad = ['baja', 'media', 'alta', 'critica'];
    private static array $validRoles     = ['GOD', 'ADMIN', 'USER'];

    public static function required(mixed $value): bool
    {
        return $value !== null && $value !== '' && trim((string)$value) !== '';
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen(trim($value)) >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen(trim($value)) <= $max;
    }

    public static function isNumericId(mixed $value): bool
    {
        return is_numeric($value) && (int)$value > 0;
    }

    public static function isValidEstado(string $estado): bool
    {
        return in_array($estado, self::$validEstados, true);
    }

    public static function isValidPrioridad(string $prioridad): bool
    {
        return in_array($prioridad, self::$validPrioridad, true);
    }

    public static function isValidRole(string $rol): bool
    {
        return in_array($rol, self::$validRoles, true);
    }

    public static function isBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true);
    }

    public static function sanitize(mixed $value): string
    {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeAll(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key] = is_string($value) ? self::sanitize($value) : $value;
        }
        return $result;
    }

    public static function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }

    public static function isEmail(string $value): bool
    {
        return (bool)filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function isPhone(string $value): bool
    {
        return (bool)preg_match('/^[\d\s\+\-\(\)]{7,20}$/', trim($value));
    }
}
