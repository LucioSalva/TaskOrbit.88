<?php
/**
 * Regression test: USER role must be blocked (403) from SubtareasController::assign.
 * Runs inside the Docker web container.
 *
 * CLI:
 *   php bin/test_subtarea_assign_user_role.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
        $_ENV[trim($k)] = trim($v);
    }
}
if (!defined('CSP_NONCE')) define('CSP_NONCE', 'cli-test');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

// Pick any USER-role user
$db = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=TaskOrbit", "postgres", "admin");
$userRow = $db->query("
    SELECT u.id, u.username, u.nombre_completo, r.nombre AS rol
    FROM usuarios u
    JOIN usuarios_roles ur ON ur.usuario_id=u.id
    JOIN roles r ON r.id=ur.rol_id
    WHERE UPPER(r.nombre) = 'USER' AND u.activo = TRUE
    ORDER BY u.id LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    fwrite(STDERR, "SKIP: no USER-role user in DB\n");
    exit(0);
}

$sub = $db->query("SELECT id FROM subtareas WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$sub) { fwrite(STDERR, "FATAL: no subtareas\n"); exit(2); }
$subtareaId = (int)$sub['id'];

session_id('fake-cli');
$_SESSION = [];
$_SESSION['user'] = [
    'id'              => (int)$userRow['id'],
    'username'        => $userRow['username'],
    'nombre_completo' => $userRow['nombre_completo'],
    'rol'             => 'USER',
];
$_SESSION['csrf_token'] = 'cli-csrf-fixed';

$_SERVER['REQUEST_METHOD']        = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_X_CSRF_TOKEN']     = $_SESSION['csrf_token'];
$_SERVER['REMOTE_ADDR']           = '127.0.0.1';
$_SERVER['REQUEST_URI']           = '/taskorbit/subtareas/cli-test-user';

$_POST = [
    'csrf_token'          => $_SESSION['csrf_token'],
    'usuario_asignado_id' => '11',
];

register_shutdown_function(function() {
    $body = '';
    while (ob_get_level() > 0) $body = ob_get_clean() . $body;
    $status = http_response_code();
    if ($status === false) $status = 200;
    fwrite(STDERR, "USER_ROLE_STATUS=$status BODY=" . substr($body, 0, 300) . "\n");
    fwrite(STDERR, $status === 403 ? "RESULT=PASS (USER blocked)\n" : "RESULT=FAIL (expected 403)\n");
});

ob_start();
try {
    (new \App\Controllers\SubtareasController())->assign((string)$subtareaId);
} catch (\Throwable $e) {
    fwrite(STDERR, "EXCEPTION: " . $e->getMessage() . "\n");
}
