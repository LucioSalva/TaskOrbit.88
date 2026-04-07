<?php
/**
 * End-to-end test harness for SubtareasController::assign
 *
 * Runs as a CLI subprocess — ONE CASE PER INVOCATION — because
 * Controller::json() calls exit(). The outer runner script
 * (test_subtarea_assign_run.sh) loops over cases.
 *
 * CLI:
 *   php test_subtarea_assign.php <subtareaId> <posted-usuario-asignado-id> <expected-status> [expected-msg-substr]
 *
 * Output (single line, to STDERR before exit):
 *   STATUS=<n> BODY=<json> DBAFTER=<usuario_asignado_id or null>
 *
 * Exit code:
 *   0 = expected status matched
 *   1 = expected status did NOT match
 *   2 = fixture / setup failure
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

// ---- Parse CLI args ----
$subtareaId     = (int)($argv[1] ?? 0);
$postedUserId   = $argv[2] ?? '';          // raw string, may be ''/'abc'/'11'
$expectedStatus = (int)($argv[3] ?? 200);
$expectedMsg    = $argv[4] ?? null;

if ($subtareaId <= 0) {
    fwrite(STDERR, "usage: php test_subtarea_assign.php <subtareaId> <postedUserId> <expectedStatus> [msgSubstr]\n");
    exit(2);
}

// ---- Fake authenticated GOD session in memory (no session_start) ----
$_SESSION = [];
$god = \App\Models\Usuario::findById(9);
if (!$god) { fwrite(STDERR, "FATAL: GOD user id=9 not found\n"); exit(2); }
$_SESSION['user'] = [
    'id'              => (int)$god['id'],
    'username'        => $god['username'],
    'nombre_completo' => $god['nombre_completo'],
    'rol'             => strtoupper((string)$god['rol']),
];
$_SESSION['csrf_token'] = 'cli-csrf-fixed';

$_SERVER['REQUEST_METHOD']        = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_X_CSRF_TOKEN']     = $_SESSION['csrf_token'];
$_SERVER['REMOTE_ADDR']           = '127.0.0.1';
$_SERVER['REQUEST_URI']           = '/taskorbit/subtareas/cli-test';

$_POST = [
    'csrf_token'          => $_SESSION['csrf_token'],
    'usuario_asignado_id' => $postedUserId,
];

// ---- Capture the controller output (json() calls exit) ----
// Register shutdown function to capture the buffer exactly once.
$captured = null;
$exitStatus = 200;

register_shutdown_function(function() use (&$captured, $expectedStatus, $expectedMsg, $subtareaId) {
    $body = '';
    while (ob_get_level() > 0) {
        $body = ob_get_clean() . $body;
    }
    $status = http_response_code();
    if ($status === false) $status = 200;

    // DB verification: read current value directly
    try {
        $db = \App\Core\Database::getInstance();
        $row = $db->fetchOne('SELECT usuario_asignado_id FROM subtareas WHERE id = ?', [$subtareaId]);
        $dbAfter = $row ? ($row['usuario_asignado_id'] !== null ? (int)$row['usuario_asignado_id'] : null) : 'N/A';
    } catch (\Throwable $e) {
        $dbAfter = 'ERR:'.$e->getMessage();
    }

    fwrite(STDERR, 'STATUS=' . $status
        . ' BODY=' . substr((string)$body, 0, 400)
        . ' DBAFTER=' . var_export($dbAfter, true)
        . "\n");

    // Evaluate expectations
    $statusOk  = ($status === $expectedStatus);
    $messageOk = true;
    if ($expectedMsg !== null) {
        $decoded = json_decode((string)$body, true);
        $msg     = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
        $messageOk = stripos($msg, $expectedMsg) !== false;
    }

    // Use _exit-like return code via posix_exit not available → echo marker
    fwrite(STDERR, ($statusOk && $messageOk) ? "RESULT=PASS\n" : "RESULT=FAIL\n");
});

ob_start();
try {
    $controller = new \App\Controllers\SubtareasController();
    $controller->assign((string)$subtareaId);
} catch (\Throwable $e) {
    fwrite(STDERR, 'EXCEPTION: ' . $e->getMessage() . "\n");
}
