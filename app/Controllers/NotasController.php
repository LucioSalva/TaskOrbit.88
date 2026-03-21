<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\{CSRF, Validator};
use App\Models\{Nota, Notificacion};

class NotasController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $db   = Database::getInstance();

        $notas     = Nota::getAllForUser($user['id'], $user['rol']);
        $proyectos = $db->fetchAll(
            $user['rol'] === 'GOD'
                ? 'SELECT id, nombre FROM vw_proyectos ORDER BY nombre'
                : 'SELECT id, nombre FROM vw_proyectos WHERE ' .
                  ($user['rol'] === 'ADMIN' ? 'created_by' : 'usuario_asignado_id') . ' = ? ORDER BY nombre',
            $user['rol'] === 'GOD' ? [] : [$user['id']]
        );

        $proyectoIds = array_column($proyectos, 'id');
        $tareas = [];
        if ($proyectoIds) {
            $ph = implode(',', array_fill(0, count($proyectoIds), '?'));
            $tareas = $db->fetchAll(
                "SELECT id, nombre, proyecto_id FROM vw_tareas WHERE proyecto_id IN ($ph) ORDER BY nombre",
                $proyectoIds
            );
        }

        $this->view('notas/index', [
            'flash'     => $this->getFlash(),
            'notas'     => $notas,
            'proyectos' => $proyectos,
            'tareas'    => $tareas,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user        = $this->currentUser();
        $scope       = trim($_POST['scope'] ?? 'personal');
        $referenciaId = (int)($_POST['referencia_id'] ?? 0) ?: null;
        $titulo      = trim($_POST['titulo'] ?? '');
        $contenido   = trim($_POST['contenido'] ?? '');

        $validScopes = ['personal', 'proyecto', 'tarea', 'subtarea'];
        if (!in_array($scope, $validScopes, true)) {
            $this->flash('error', 'Tipo de nota inválido.');
            $this->redirect('/notas');
        }

        if (!Validator::required($contenido)) {
            $this->flash('error', 'El contenido de la nota es requerido.');
            $this->redirect('/notas');
        }

        $notaId = Nota::create([
            'scope'        => $scope,
            'referencia_id'=> $referenciaId,
            'titulo'       => $titulo,
            'contenido'    => $contenido,
            'tipo'         => $scope === 'personal' ? 'personal' : 'actividad',
        ], $user['id']);

        // Notify admins when a USER adds a non-personal note
        if ($user['rol'] === 'USER' && $scope !== 'personal') {
            $scopeLabel = [
                'proyecto'  => 'proyecto',
                'tarea'     => 'tarea',
                'subtarea'  => 'subtarea',
            ];
            $entidad = $scopeLabel[$scope] ?? $scope;
            $tituloNota = $titulo ? "\"$titulo\"" : 'sin título';
            Notificacion::notifyAdmins([
                'type'        => 'user_nota_creada',
                'title'       => 'Nueva nota agregada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} agregó una nota ($tituloNota) en un $entidad (ID: $referenciaId).",
                'severity'    => 'info',
                'entity_type' => 'nota',
                'entity_id'   => $notaId,
            ]);
        }

        $this->flash('success', 'Nota guardada correctamente.');
        $this->redirect('/notas');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user = $this->currentUser();

        $db   = Database::getInstance();
        $nota = $db->fetchOne(
            'SELECT id, user_id FROM notas WHERE id = ? AND deleted_at IS NULL',
            [(int)$id]
        );

        if (!$nota) {
            $this->flash('error', 'Nota no encontrada.');
            $this->redirect('/notas');
        }

        if ($nota['user_id'] !== $user['id'] && $user['rol'] !== 'GOD') {
            http_response_code(403);
            $this->flash('error', 'No tienes permiso para eliminar esta nota.');
            $this->redirect('/notas');
        }

        $adminOverride = $user['rol'] === 'GOD';
        Nota::softDelete((int)$id, $user['id'], $adminOverride);

        $this->flash('success', 'Nota eliminada.');
        $this->redirect('/notas');
    }
}
