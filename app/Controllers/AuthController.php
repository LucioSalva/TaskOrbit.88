<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Autenticación
 *  Archivo: AuthController.php
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

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CSRF;
use App\Helpers\LoginRateLimiter;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', ['flash' => $this->getFlash()], 'auth');
    }

    public function login(): void
    {
        CSRF::verifyRequest();

        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $this->flash('error', 'Usuario y contraseña son requeridos.');
            $this->redirect('/login');
        }

        // DB-based rate limiting: check before touching credentials
        $rateLimitActivo = true;
        try {
            if (LoginRateLimiter::isBlocked($username, $ip)) {
                $minutes = LoginRateLimiter::minutesRemaining($username, $ip);
                $this->flash('error', "Demasiados intentos fallidos. Intente de nuevo en $minutes minuto(s).");
                $this->redirect('/login');
            }
        } catch (\Throwable $e) {
            // La tabla login_attempts no existe aún — continuar sin rate limiting
            error_log('[AuthController] LoginRateLimiter no disponible: ' . $e->getMessage());
            $rateLimitActivo = false;
        }

        $user = Usuario::findByUsername($username);

        if (!$user) {
            if ($rateLimitActivo) {
                try { LoginRateLimiter::recordFailure($username, $ip); } catch (\Throwable $e) {}
            }
            $this->flash('error', 'Credenciales inválidas.');
            $this->redirect('/login');
        }

        if (!$user['activo']) {
            $this->flash('error', 'Cuenta inactiva. Contacte al administrador.');
            $this->redirect('/login');
        }

        if (!Usuario::verifyPassword($password, $user['password_hash'])) {
            if ($rateLimitActivo) {
                try { LoginRateLimiter::recordFailure($username, $ip); } catch (\Throwable $e) {}
            }
            $this->flash('error', 'Credenciales inválidas.');
            $this->redirect('/login');
        }

        // Successful login — clear failed attempts and record success
        if ($rateLimitActivo) {
            try { LoginRateLimiter::recordSuccess($username, $ip); } catch (\Throwable $e) {}
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'             => (int)$user['id'],
            'username'       => $user['username'],
            'nombre_completo'=> $user['nombre_completo'],
            'rol'            => strtoupper($user['rol']),
            'telefono'       => $user['telefono'] ?? '',
        ];

        Usuario::logAudit((int)$user['id'], 'LOGIN', (int)$user['id'], ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

        $intended = $_SESSION['intended'] ?? '/dashboard';
        unset($_SESSION['intended']);

        $this->redirect($intended);
    }

    public function logoutGet(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $base = rtrim(getenv('APP_URL') ?: '', '/');
        header('Location: ' . $base . '/login');
        exit;
    }

    public function logout(): void
    {
        CSRF::verifyRequest();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId) {
            Usuario::logAudit($userId, 'LOGOUT', $userId, []);
        }

        // Limpiar todos los datos de sesión
        $_SESSION = [];

        // Eliminar la cookie de sesión del cliente
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $base = rtrim(getenv('APP_URL') ?: '', '/');
        header('Location: ' . $base . '/login');
        exit;
    }

    public function accessDenied(): void
    {
        http_response_code(403);
        $layout = $this->isAuthenticated() ? 'main' : 'auth';
        $this->view('auth/access-denied', [], $layout);
    }
}
