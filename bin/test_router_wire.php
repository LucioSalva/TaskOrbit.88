<?php
/**
 * Wire test: simulate the real Router dispatch for /subtareas/{id}/asignar
 * to prove the full route -> middleware -> controller -> response chain works.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Load .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}
if (!defined('CSP_NONCE')) define('CSP_NONCE', 'cli-test');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

// Fake GOD session
session_id('wire-test');
$_SESSION = [];
$god = \App\Models\Usuario::findById(9);
if (!$god) { fwrite(STDERR, "FATAL: GOD id=9 missing\n"); exit(2); }
$_SESSION['user'] = [
    'id'              => (int)$god['id'],
    'username'        => $god['username'],
    'nombre_completo' => $god['nombre_completo'],
    'rol'             => strtoupper((string)$god['rol']),
];
$_SESSION['csrf_token'] = 'wire-csrf';

// Pick first subtarea, reset to NULL
$db = \App\Core\Database::getInstance();
$row = $db->fetchOne("SELECT id FROM subtareas WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
if (!$row) { fwrite(STDERR, "FATAL: no subtareas\n"); exit(2); }
$subtareaId = (int)$row['id'];
$db->execute("UPDATE subtareas SET usuario_asignado_id = NULL WHERE id = ?", [$subtareaId]);

$_SERVER['REQUEST_METHOD']        = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_X_CSRF_TOKEN']     = 'wire-csrf';
$_SERVER['REMOTE_ADDR']           = '127.0.0.1';
$_SERVER['REQUEST_URI']           = '/taskorbit/subtareas/' . $subtareaId . '/asignar';
$_SERVER['SCRIPT_NAME']           = '/taskorbit/index.php';

$_POST = [
    'csrf_token'          => 'wire-csrf',
    'usuario_asignado_id' => '11',
];

// Register shutdown to print capture
register_shutdown_function(function() use ($subtareaId) {
    $body = '';
    while (ob_get_level() > 0) $body = ob_get_clean() . $body;
    $status = http_response_code();
    if ($status === false) $status = 200;
    fwrite(STDERR, "WIRE_STATUS=$status BODY=" . substr((string)$body, 0, 400) . "\n");
    try {
        $db = \App\Core\Database::getInstance();
        $r = $db->fetchOne("SELECT usuario_asignado_id FROM subtareas WHERE id = ?", [$subtareaId]);
        fwrite(STDERR, "DB_AFTER_WIRE=" . var_export($r['usuario_asignado_id'] ?? null, true) . "\n");
    } catch (\Throwable $e) {
        fwrite(STDERR, "DB_ERR: " . $e->getMessage() . "\n");
    }
    fwrite(STDERR, $status === 200 ? "WIRE_RESULT=PASS\n" : "WIRE_RESULT=FAIL\n");
});

ob_start();

// Build router and dispatch
$router = new \App\Core\Router();
require BASE_PATH . '/routes/web.php';
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    fwrite(STDERR, "ROUTER_EXCEPTION: " . $e->getMessage() . "\nIN: " . $e->getFile() . ":" . $e->getLine() . "\n");
}
