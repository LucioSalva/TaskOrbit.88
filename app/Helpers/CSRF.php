<?php
declare(strict_types=1);

namespace App\Helpers;

class CSRF
{
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function getToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            return self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function tokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyRequest(): void
    {
        $token = $_POST['csrf_token']
               ?? $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? '';

        if (!self::validateToken($token)) {
            $isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $acceptsJson = isset($_SERVER['HTTP_ACCEPT'])
                        && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
            $sendsJson   = isset($_SERVER['CONTENT_TYPE'])
                        && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');

            http_response_code(419);

            if ($isAjax || $acceptsJson || $sendsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'      => false,
                    'error'   => 'csrf_expired',
                    'message' => 'La sesión expiró. Recarga la página e intenta de nuevo.',
                ]);
            } else {
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
                echo '<p>Token de seguridad inválido. <a href="javascript:history.back()">Volver</a> y recargar la página.</p>';
                echo '</body></html>';
            }
            exit;
        }
    }

    public static function metaTag(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
