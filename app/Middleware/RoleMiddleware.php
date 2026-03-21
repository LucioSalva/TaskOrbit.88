<?php
declare(strict_types=1);

namespace App\Middleware;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $roles = [])
    {
        $this->allowedRoles = $roles;
    }

    public function handle(array $params = []): void
    {
        if (empty($_SESSION['user']['id'])) {
            $base = rtrim(getenv('APP_URL') ?: '', '/');
            header('Location: ' . $base . '/login');
            exit;
        }

        $userRole = $_SESSION['user']['rol'] ?? '';
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles, true)) {
            $base = rtrim(getenv('APP_URL') ?: '', '/');
            header('Location: ' . $base . '/acceso-denegado');
            exit;
        }
    }
}
