<?php
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
        if (LoginRateLimiter::isBlocked($username, $ip)) {
            $minutes = LoginRateLimiter::minutesRemaining($username, $ip);
            $this->flash('error', "Demasiados intentos fallidos. Intente de nuevo en $minutes minuto(s).");
            $this->redirect('/login');
        }

        $user = Usuario::findByUsername($username);

        if (!$user) {
            LoginRateLimiter::recordFailure($username, $ip);
            $this->flash('error', 'Credenciales inválidas.');
            $this->redirect('/login');
        }

        if (!$user['activo']) {
            $this->flash('error', 'Cuenta inactiva. Contacte al administrador.');
            $this->redirect('/login');
        }

        if (!Usuario::verifyPassword($password, $user['password_hash'])) {
            LoginRateLimiter::recordFailure($username, $ip);
            $this->flash('error', 'Credenciales inválidas.');
            $this->redirect('/login');
        }

        // Successful login — clear failed attempts and record success
        LoginRateLimiter::recordSuccess($username, $ip);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'             => (int)$user['id'],
            'username'       => $user['username'],
            'nombre_completo'=> $user['nombre_completo'],
            'rol'            => strtoupper($user['rol']),
        ];

        Usuario::logAudit((int)$user['id'], 'LOGIN', (int)$user['id'], ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

        $intended = $_SESSION['intended'] ?? '/dashboard';
        unset($_SESSION['intended']);

        $this->redirect($intended);
    }

    public function logout(): void
    {
        CSRF::verifyRequest();

        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId) {
            Usuario::logAudit($userId, 'LOGOUT', $userId, []);
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
