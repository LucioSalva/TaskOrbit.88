<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Núcleo del Framework
 *  Archivo: Controller.php
 *
 *  © 2025–2026 Humberto Salvador Ruiz Lucio.
 *  Todos los derechos reservados.
 *
 *  PROPIEDAD INTELECTUAL Y CONFIDENCIALIDAD:
 *  El presente código fuente, su estructura lógica,
 *  funcionalidad, arquitectura, diseño de datos,
 *  documentación y componentes asociados forman parte
 *  de un sistema propietario y confidencial.
 *
 *  Queda prohibida su copia, reproducción, distribución,
 *  adaptación, descompilación, comercialización,
 *  divulgación o utilización no autorizada, total o parcial,
 *  por cualquier medio, sin el consentimiento previo
 *  y por escrito de su titular.
 *
 *  El uso no autorizado de este software podrá dar lugar
 *  a las acciones legales civiles, mercantiles, administrativas
 *  o penales correspondientes conforme a la legislación aplicable
 *  en los Estados Unidos Mexicanos.
 *
 *  Uso interno exclusivo.
 *  Documento/código confidencial.
 * ================================================================
 */
declare(strict_types=1);

namespace App\Core;

class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'main'): void
    {
        View::render($template, $data, $layout);
    }

    protected function redirect(string $url): void
    {
        if (!str_starts_with($url, 'http')) {
            $base = rtrim(getenv('APP_URL') ?: '', '/');
            $url  = $base . '/' . ltrim($url, '/');
        }
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function request(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    protected function session(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    protected function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    protected function hasRole(string ...$roles): bool
    {
        $userRole = $_SESSION['user']['rol'] ?? '';
        return in_array($userRole, $roles, true);
    }

    protected function requireRole(string ...$roles): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
        if (!$this->hasRole(...$roles)) {
            \App\Services\AuthService::deny('No tienes permiso para realizar esta acción.');
        }
    }

    protected function denyAccess(string $message = 'Acceso denegado.'): never
    {
        \App\Services\AuthService::deny($message);
    }

    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/dashboard';
            $this->redirect('/login');
        }
    }

    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $base    = rtrim(getenv('APP_URL') ?: '', '/');
        if ($referer && $base && str_starts_with($referer, $base)) {
            header('Location: ' . $referer);
        } else {
            $this->redirect('/dashboard');
        }
        exit;
    }
}
