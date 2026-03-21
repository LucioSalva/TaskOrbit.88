<?php
declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public function handle(array $params = []): void
    {
        if (empty($_SESSION['user']['id'])) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/dashboard';
            $base = rtrim(getenv('APP_URL') ?: '', '/');
            header('Location: ' . $base . '/login');
            exit;
        }
    }
}
