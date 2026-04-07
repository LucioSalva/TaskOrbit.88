<?php
declare(strict_types=1);

namespace App\Services;

/**
 * AuthService — centralized authorization for TaskOrbit
 *
 * Roles (in order of precedence):
 *   GOD   — full system access, user administration
 *   ADMIN — operational access within their scope
 *   USER  — access only to assigned/own resources
 *
 * Usage:
 *   AuthService::can('proyecto.create')
 *   AuthService::can('tarea.delete', $tareaArray)
 *   AuthService::requirePermission('usuario.list')
 *   AuthService::scopedProyectoIds()  // returns array of proyecto IDs visible to current user
 */
class AuthService
{
    // -------------------------------------------------------
    // Role constants — single source of truth for role names
    // -------------------------------------------------------
    public const ROLE_GOD   = 'GOD';
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_USER  = 'USER';

    // -------------------------------------------------------
    // Permission map — defines who can do what
    // Format: 'module.action' => [roles_allowed]
    // -------------------------------------------------------
    private static array $permissions = [
        // Dashboard
        'dashboard.view'               => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'dashboard.view_global_metrics'=> [self::ROLE_GOD, self::ROLE_ADMIN],
        'dashboard.view_own_metrics'   => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],

        // Proyectos
        'proyecto.view'                => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'proyecto.create'              => [self::ROLE_GOD, self::ROLE_ADMIN],
        'proyecto.edit'                => [self::ROLE_GOD, self::ROLE_ADMIN],
        'proyecto.delete'              => [self::ROLE_GOD, self::ROLE_ADMIN],
        'proyecto.change_estado'       => [self::ROLE_GOD, self::ROLE_ADMIN],
        'proyecto.assign'              => [self::ROLE_GOD, self::ROLE_ADMIN],
        'proyecto.view_all'            => [self::ROLE_GOD, self::ROLE_ADMIN],

        // Tareas
        'tarea.view'                   => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'tarea.create'                 => [self::ROLE_GOD, self::ROLE_ADMIN],
        'tarea.edit'                   => [self::ROLE_GOD, self::ROLE_ADMIN],
        'tarea.delete'                 => [self::ROLE_GOD, self::ROLE_ADMIN],
        'tarea.change_estado'          => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'tarea.assign'                 => [self::ROLE_GOD, self::ROLE_ADMIN],
        'tarea.view_all'               => [self::ROLE_GOD, self::ROLE_ADMIN],

        // Subtareas
        'subtarea.view'                => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'subtarea.create'              => [self::ROLE_GOD, self::ROLE_ADMIN],
        'subtarea.edit'                => [self::ROLE_GOD, self::ROLE_ADMIN],
        'subtarea.delete'              => [self::ROLE_GOD, self::ROLE_ADMIN],
        'subtarea.change_estado'       => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'subtarea.assign'              => [self::ROLE_GOD, self::ROLE_ADMIN],

        // Notas
        'nota.view'                    => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'nota.create'                  => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'nota.edit'                    => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'nota.delete'                  => [self::ROLE_GOD, self::ROLE_ADMIN],
        'nota.delete_own'              => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],

        // Usuarios (administration)
        'usuario.list'                 => [self::ROLE_GOD],
        'usuario.create'               => [self::ROLE_GOD],
        'usuario.edit'                 => [self::ROLE_GOD],
        'usuario.delete'               => [self::ROLE_GOD],
        'usuario.toggle_active'        => [self::ROLE_GOD],

        // Notificaciones
        'notificacion.view_own'        => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
        'notificacion.mark_read'       => [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER],
    ];

    // -------------------------------------------------------
    // Core permission check
    // -------------------------------------------------------

    /**
     * Check if the current authenticated user has permission.
     *
     * @param string $permission  e.g. 'proyecto.create', 'tarea.delete'
     * @param array|null $resource  The resource array (proyecto, tarea, etc.) for scope checks
     */
    public static function can(string $permission, ?array $resource = null): bool
    {
        $user = self::currentUser();
        if (!$user) return false;

        $role = $user['rol'] ?? '';

        // GOD always has access (unless explicitly restricted — none defined)
        if ($role === self::ROLE_GOD) return true;

        // Check permission map
        $allowed = self::$permissions[$permission] ?? [];
        if (!in_array($role, $allowed, true)) return false;

        // Resource-level scope check
        if ($resource !== null) {
            return self::checkScope($permission, $resource, $user);
        }

        return true;
    }

    /**
     * Require permission or abort with 403.
     */
    public static function requirePermission(string $permission, ?array $resource = null): void
    {
        if (!self::can($permission, $resource)) {
            self::deny();
        }
    }

    // -------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------

    public static function currentRole(): string
    {
        return self::currentUser()['rol'] ?? '';
    }

    public static function isGod(): bool
    {
        return self::currentRole() === self::ROLE_GOD;
    }

    public static function isAdmin(): bool
    {
        return self::currentRole() === self::ROLE_ADMIN;
    }

    public static function isUser(): bool
    {
        return self::currentRole() === self::ROLE_USER;
    }

    public static function isAtLeast(string $role): bool
    {
        $hierarchy = [self::ROLE_USER => 1, self::ROLE_ADMIN => 2, self::ROLE_GOD => 3];
        $current = $hierarchy[self::currentRole()] ?? 0;
        $required = $hierarchy[$role] ?? 99;
        return $current >= $required;
    }

    // -------------------------------------------------------
    // Resource scope check
    // -------------------------------------------------------

    /**
     * Check whether the current user can access a specific resource.
     * Called internally by can() when a resource is provided.
     */
    private static function checkScope(string $permission, array $resource, array $user): bool
    {
        $userId = (int)$user['id'];
        $role   = $user['rol'];

        // ADMIN scope: can access resources they created or are assigned to
        if ($role === self::ROLE_ADMIN) {
            // For proyectos: ADMIN can manage projects they created OR are assigned to
            if (str_starts_with($permission, 'proyecto.')) {
                return (int)($resource['created_by'] ?? -1) === $userId
                    || (int)($resource['usuario_asignado_id'] ?? -1) === $userId;
            }
            // For tareas and subtareas: check via parent proyecto
            if (str_starts_with($permission, 'tarea.') || str_starts_with($permission, 'subtarea.')) {
                // Access check delegated to Proyecto::checkAccess() — ADMIN returns true if
                // they created/are assigned to the parent project
                return true; // Validated in controller via Proyecto::checkAccess()
            }
        }

        // USER scope: can only access resources assigned to them
        if ($role === self::ROLE_USER) {
            // Users can view/change_estado on tasks assigned to them
            if (str_starts_with($permission, 'tarea.') || str_starts_with($permission, 'subtarea.')) {
                $allowedActions = ['tarea.view', 'tarea.change_estado', 'subtarea.view', 'subtarea.change_estado', 'nota.view', 'nota.create'];
                if (!in_array($permission, $allowedActions, true)) return false;
                // Scope: is the user assigned to this resource or its parent project?
                return (int)($resource['usuario_asignado_id'] ?? -1) === $userId
                    || (int)($resource['created_by'] ?? -1) === $userId;
            }
            if (str_starts_with($permission, 'proyecto.')) {
                $allowedActions = ['proyecto.view'];
                if (!in_array($permission, $allowedActions, true)) return false;
                return (int)($resource['usuario_asignado_id'] ?? -1) === $userId
                    || (int)($resource['created_by'] ?? -1) === $userId;
            }
        }

        return true;
    }

    // -------------------------------------------------------
    // Data scope helpers (for WHERE clause construction)
    // -------------------------------------------------------

    /**
     * Returns SQL WHERE fragment + params to scope proyecto queries by current user.
     * Returns ['sql' => '', 'params' => []] for GOD (no filter).
     */
    public static function proyectoScopeWhere(): array
    {
        $user = self::currentUser();
        if (!$user) return ['sql' => '1=0', 'params' => []];

        $role   = $user['rol'];
        $userId = (int)$user['id'];

        if ($role === self::ROLE_GOD) {
            return ['sql' => '1=1', 'params' => []];
        }
        if ($role === self::ROLE_ADMIN) {
            // ADMIN sees projects they created or are assigned to
            return [
                'sql'    => '(p.created_by = ? OR p.usuario_asignado_id = ?)',
                'params' => [$userId, $userId],
            ];
        }
        // USER: only projects explicitly assigned to them
        return [
            'sql'    => '(p.usuario_asignado_id = ?)',
            'params' => [$userId],
        ];
    }

    // -------------------------------------------------------
    // Deny helpers
    // -------------------------------------------------------

    /**
     * Abort with 403. For AJAX requests, returns JSON. For browser, redirects.
     */
    public static function deny(string $message = 'No tienes permiso para realizar esta acción.'): never
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => $message, 'code' => 403]);
            exit;
        }

        http_response_code(403);
        // Try to render 403 view, fallback to simple message
        $viewPath = BASE_PATH . '/app/Views/errors/403.php';
        if (file_exists($viewPath)) {
            $errorMessage = $message;
            include $viewPath;
        } else {
            $appUrl = rtrim((string)(getenv('APP_URL') ?: ''), '/');
            $dashboardUrl = htmlspecialchars($appUrl . '/dashboard', ENT_QUOTES, 'UTF-8');
            echo '<h1>403 — Acceso denegado</h1><p>' . htmlspecialchars($message) . '</p>';
            echo '<a href="' . $dashboardUrl . '">Volver al dashboard</a>';
        }
        exit;
    }

    /**
     * Require authentication (redirects to login if not logged in).
     */
    public static function requireAuth(): void
    {
        if (!self::currentUser()) {
            $loginUrl = rtrim(getenv('APP_URL') ?: '', '/') . '/login';
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    // -------------------------------------------------------
    // Session helpers
    // -------------------------------------------------------

    private static function currentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user'] ?? null;
    }

    /**
     * Refresh user data in session from DB (call after role/data changes).
     */
    public static function refreshSession(int $userId): void
    {
        $user = \App\Models\Usuario::findById($userId);
        if ($user) {
            $_SESSION['user'] = $user;
        }
    }

    /**
     * Check if a given role string is valid.
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, [self::ROLE_GOD, self::ROLE_ADMIN, self::ROLE_USER], true);
    }
}
