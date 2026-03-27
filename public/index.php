<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

// Load .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Error reporting — always log, display only in debug mode
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');

// CSP nonce — generated fresh per request for inline scripts/styles
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP: strict nonce-based policy. All inline handlers have been migrated to
// addEventListener / data-attribute delegation — unsafe-inline is no longer required.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: blob:; connect-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// HSTS: only send when serving over HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Mexico_City');

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Session
$sessionName     = getenv('SESSION_NAME') ?: 'taskorbit_session';
$sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 7200);
$isProduction    = (getenv('APP_ENV') ?: 'local') === 'production';
$appUrlEnv       = getenv('APP_URL') ?: '';
$cookieDomain    = '';
if ($isProduction && $appUrlEnv) {
    $parsedHost = parse_url($appUrlEnv, PHP_URL_HOST);
    if ($parsedHost && $parsedHost !== 'localhost' && !filter_var($parsedHost, FILTER_VALIDATE_IP)) {
        $cookieDomain = $parsedHost;
    }
}
session_name($sessionName);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// CSRF token init
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Global exception handler — prevent raw 500 errors
set_exception_handler(function (\Throwable $e) {
    $appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($appDebug) {
        echo '<h1>500 — Error interno</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        $errorFile = APP_PATH . '/Views/errors/500.php';
        if (file_exists($errorFile)) {
            include $errorFile;
        } else {
            echo '<h1>500 — Error interno del servidor</h1>';
            echo '<p>Ha ocurrido un error inesperado. Por favor intenta de nuevo.</p>';
            echo '<p><a href="' . htmlspecialchars(rtrim(getenv('APP_URL') ?: '', '/')) . '/dashboard">Volver al inicio</a></p>';
        }
    }
    exit(1);
});

// Router
$router = new \App\Core\Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
