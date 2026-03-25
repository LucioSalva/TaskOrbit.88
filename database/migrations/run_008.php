<?php
declare(strict_types=1);

/**
 * Run migration 008_evidencias.sql
 * Usage: php database/migrations/run_008.php
 */

// Bootstrap: load .env and DB config the same way public/index.php does
define('BASE_PATH', dirname(__DIR__, 2));

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
        }
    }
}

$config = require BASE_PATH . '/config/database.php';

try {
    $pdo = new PDO(
        $config['dsn'],
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $sql = file_get_contents(__DIR__ . '/008_evidencias.sql');
    $pdo->exec($sql);

    echo "[OK] Migration 008_evidencias.sql applied successfully.\n";
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
