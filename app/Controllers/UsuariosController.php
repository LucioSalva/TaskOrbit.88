<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Administración de Usuarios
 *  Archivo: UsuariosController.php
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
use App\Helpers\{CSRF, Validator};
use App\Models\Usuario;

class UsuariosController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('GOD');

        $usuarios = Usuario::getAll();
        $this->view('usuarios/index', [
            'flash'    => $this->getFlash(),
            'usuarios' => $usuarios,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('GOD');
        CSRF::verifyRequest();

        $currentUser = $this->currentUser();
        $errors      = [];

        $nombre   = trim($_POST['nombre_completo'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol      = strtoupper(trim($_POST['rol'] ?? ''));
        $activo   = isset($_POST['activo']) ? (bool)$_POST['activo'] : true;

        if (!Validator::required($nombre))   $errors[] = 'El nombre completo es requerido.';
        if (!Validator::required($username) || !Validator::minLength($username, 4)) {
            $errors[] = 'El usuario debe tener al menos 4 caracteres.';
        }
        if (!Validator::required($telefono)) $errors[] = 'El teléfono es requerido.';
        if (!Validator::required($password)) {
            $errors[] = 'La contrasena es requerida.';
        } else {
            $pwErrors = Validator::validatePassword($password);
            $errors = array_merge($errors, $pwErrors);
        }
        if (!Validator::isValidRole($rol)) $errors[] = 'Rol inválido.';

        if ($rol === 'GOD' && $currentUser['rol'] !== 'GOD') {
            $errors[] = 'Solo GOD puede crear usuarios GOD.';
        }
        if ($currentUser['rol'] === 'ADMIN' && $rol === 'GOD') {
            $errors[] = 'ADMIN no puede crear usuarios GOD.';
        }

        if (empty($errors) && Usuario::usernameExists($username)) {
            $errors[] = 'El nombre de usuario ya está en uso.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect('/admin/usuarios');
        }

        Usuario::create([
            'nombre_completo' => $nombre,
            'username'        => $username,
            'telefono'        => $telefono,
            'password'        => $password,
            'rol'             => $rol,
            'activo'          => $activo,
            'created_by'      => $currentUser['id'],
        ]);

        $this->flash('success', "Usuario \"$username\" creado exitosamente.");
        $this->redirect('/admin/usuarios');
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('GOD');
        CSRF::verifyRequest();

        $currentUser = $this->currentUser();
        $target      = Usuario::findById((int)$id);

        if (!$target) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('/admin/usuarios');
        }

        if ($currentUser['rol'] === 'ADMIN' && $target['rol'] === 'GOD') {
            $this->flash('error', 'ADMIN no puede modificar a GOD.');
            $this->redirect('/admin/usuarios');
        }

        $errors   = [];
        $nombre   = trim($_POST['nombre_completo'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol      = strtoupper(trim($_POST['rol'] ?? ''));
        $activo   = isset($_POST['activo']) && $_POST['activo'] === '1';

        if (!Validator::required($nombre))   $errors[] = 'El nombre completo es requerido.';
        if (!Validator::required($username) || !Validator::minLength($username, 4)) {
            $errors[] = 'El usuario debe tener al menos 4 caracteres.';
        }
        if (!Validator::isValidRole($rol))   $errors[] = 'Rol inválido.';
        if ($password) {
            $pwErrors = Validator::validatePassword($password);
            $errors = array_merge($errors, $pwErrors);
        }
        if ($rol === 'GOD' && $currentUser['rol'] !== 'GOD') {
            $errors[] = 'Solo GOD puede asignar rol GOD.';
        }

        if (empty($errors) && Usuario::usernameExists($username, (int)$id)) {
            $errors[] = 'El nombre de usuario ya está en uso.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) $this->flash('error', $e);
            $this->redirect('/admin/usuarios');
        }

        $data = [
            'nombre_completo' => $nombre,
            'username'        => $username,
            'telefono'        => $telefono,
            'rol'             => $rol,
            'activo'          => $activo,
            'updated_by'      => $currentUser['id'],
        ];
        if ($password) $data['password'] = $password;

        Usuario::update((int)$id, $data);
        $this->flash('success', "Usuario actualizado correctamente.");
        $this->redirect('/admin/usuarios');
    }

    public function toggleEstado(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('GOD');
        CSRF::verifyRequest();

        $currentUser = $this->currentUser();
        $target      = Usuario::findById((int)$id);

        if (!$target) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('/admin/usuarios');
        }

        if ($currentUser['rol'] === 'ADMIN' && $target['rol'] === 'GOD') {
            $this->flash('error', 'ADMIN no puede desactivar a GOD.');
            $this->redirect('/admin/usuarios');
        }

        $activo = !(bool)$target['activo'];
        Usuario::toggleEstado((int)$id, $activo);

        $estado = $activo ? 'activado' : 'desactivado';
        $this->flash('success', "Usuario {$target['username']} $estado.");
        $this->redirect('/admin/usuarios');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('GOD');
        CSRF::verifyRequest();

        $target = Usuario::findById((int)$id);
        if (!$target) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('/admin/usuarios');
        }

        if ($target['rol'] === 'GOD') {
            $this->flash('error', 'No se puede eliminar un usuario GOD.');
            $this->redirect('/admin/usuarios');
        }

        try {
            Usuario::delete((int)$id);
            $this->flash('success', "Usuario \"{$target['username']}\" eliminado.");
        } catch (\PDOException $e) {
            if ($e->getCode() === '23503') {
                $this->flash('error', 'No se puede eliminar este usuario porque tiene proyectos, tareas o datos asociados. Desactívalo en su lugar.');
            } else {
                error_log('[UsuariosController::destroy] PDOException: ' . $e->getMessage());
                $this->flash('error', 'Ocurrió un error al intentar eliminar el usuario.');
            }
        } catch (\Throwable $e) {
            error_log('[UsuariosController::destroy] Error: ' . $e->getMessage());
            $this->flash('error', 'Ocurrió un error inesperado.');
        }
        $this->redirect('/admin/usuarios');
    }
}
