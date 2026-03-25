<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Notas
 *  Archivo: NotasController.php
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
use App\Core\Database;
use App\Helpers\{CSRF, Validator, DateHelper};
use App\Models\{Nota, Notificacion, Proyecto, Subtarea, Tarea};

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
                  ($user['rol'] === 'ADMIN' ? '(created_by = ? OR usuario_asignado_id = ?)' : 'usuario_asignado_id = ?') . ' ORDER BY nombre',
            $user['rol'] === 'GOD' ? [] : ($user['rol'] === 'ADMIN' ? [$user['id'], $user['id']] : [$user['id']])
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
        $titulo      = mb_substr(trim($_POST['titulo'] ?? ''), 0, 200);
        $contenido   = trim($_POST['contenido'] ?? '');

        $validScopes = ['personal', 'proyecto', 'tarea', 'subtarea'];
        if (!in_array($scope, $validScopes, true)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'Tipo de nota inválido.'], 422);
            }
            $this->flash('error', 'Tipo de nota inválido.');
            $this->redirect('/notas');
        }

        if (!Validator::required($contenido)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'El contenido de la nota es requerido.'], 422);
            }
            $this->flash('error', 'El contenido de la nota es requerido.');
            $this->redirect('/notas');
        }

        if (mb_strlen($contenido) > 5000) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'El contenido no puede superar los 5000 caracteres.'], 422);
            }
            $this->flash('error', 'El contenido no puede superar los 5000 caracteres.');
            $this->redirect('/notas');
        }

        // Validate referencia access
        if (!empty($referenciaId)) {
            if ($scope === 'proyecto') {
                $proyectoRef = Proyecto::getById((int)$referenciaId);
                if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        $this->json(['ok' => false, 'message' => 'No tienes acceso al proyecto referenciado.'], 403);
                    }
                    $this->flash('error', 'No tienes acceso al proyecto referenciado.');
                    $this->redirect('/notas');
                }
            } elseif ($scope === 'tarea') {
                $tareaRef = Tarea::getById((int)$referenciaId);
                if (!$tareaRef) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        $this->json(['ok' => false, 'message' => 'Tarea no encontrada.'], 404);
                    }
                    $this->flash('error', 'Tarea no encontrada.');
                    $this->redirect('/notas');
                }
                $proyectoRef = Proyecto::getById((int)$tareaRef['proyecto_id']);
                if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        $this->json(['ok' => false, 'message' => 'No tienes acceso a la tarea referenciada.'], 403);
                    }
                    $this->flash('error', 'No tienes acceso a la tarea referenciada.');
                    $this->redirect('/notas');
                }
            } elseif ($scope === 'subtarea') {
                $subtareaRef = Subtarea::getById((int)$referenciaId);
                if (!$subtareaRef) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        $this->json(['ok' => false, 'message' => 'Subtarea no encontrada.'], 404);
                    }
                    $this->flash('error', 'Subtarea no encontrada.');
                    $this->redirect('/notas');
                }
                $tareaRef = Tarea::getById((int)$subtareaRef['tarea_id']);
                $proyectoRef = $tareaRef ? Proyecto::getById((int)$tareaRef['proyecto_id']) : null;
                if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        $this->json(['ok' => false, 'message' => 'No tienes acceso a la subtarea referenciada.'], 403);
                    }
                    $this->flash('error', 'No tienes acceso a la subtarea referenciada.');
                    $this->redirect('/notas');
                }
            }
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
            $tituloNota = $titulo ? "\"$titulo\"" : 'sin título';
            Notificacion::notifyAdmins([
                'type'        => 'user_nota_creada',
                'title'       => 'Nueva nota agregada por usuario',
                'message'     => "El usuario {$user['nombre_completo']} agregó una nota ($tituloNota) en un $scope (ID: $referenciaId).",
                'severity'    => 'info',
                'entity_type' => 'nota',
                'entity_id'   => $notaId,
            ]);
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json([
                'ok'   => true,
                'nota' => [
                    'id'           => $notaId,
                    'titulo'       => $titulo,
                    'contenido'    => $contenido,
                    'scope'        => $scope,
                    'referencia_id'=> (int)($referenciaId ?? 0),
                    'tipo'         => $scope === 'personal' ? 'personal' : 'actividad',
                    'is_pinned'    => false,
                    'autor_nombre' => $user['nombre_completo'],
                    'created_at'   => date('d/m/Y H:i'),
                    'can_edit'     => true,
                    'can_delete'   => true,
                    'can_pin'      => in_array($user['rol'], ['ADMIN', 'GOD']),
                ],
            ]);
        }
        $this->flash('success', 'Nota guardada correctamente.');
        $this->redirect('/notas');
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user = $this->currentUser();
        $nota = Nota::getById((int)$id);

        if (!$nota) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'Nota no encontrada.'], 404);
            }
            $this->flash('error', 'Nota no encontrada.');
            $this->redirect('/notas');
        }

        if (!Nota::canEdit($nota, $user['id'], $user['rol'])) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'No puedes editar esta nota.'], 403);
            }
            $this->flash('error', 'No puedes editar esta nota.');
            $this->redirect('/notas');
        }

        $titulo    = mb_substr(trim($_POST['titulo'] ?? ''), 0, 200);
        $contenido = trim($_POST['contenido'] ?? '');

        if (!Validator::required($contenido)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'El contenido es requerido.'], 422);
            }
            $this->flash('error', 'El contenido es requerido.');
            $this->redirect('/notas');
        }

        if (mb_strlen($contenido) > 5000) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'El contenido no puede superar los 5000 caracteres.'], 422);
            }
            $this->flash('error', 'El contenido no puede superar los 5000 caracteres.');
            $this->redirect('/notas');
        }

        Nota::update((int)$id, $titulo, $contenido);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['ok' => true, 'titulo' => $titulo, 'contenido' => $contenido]);
        }
        $this->flash('success', 'Nota actualizada.');
        $this->redirect('/notas');
    }

    public function pin(string $id): void
    {
        $this->requireAuth();
        $this->requireRole('ADMIN', 'GOD');
        CSRF::verifyRequest();

        $nota = Nota::getById((int)$id);
        if (!$nota) {
            $this->json(['ok' => false, 'message' => 'Nota no encontrada.'], 404);
        }

        Nota::togglePin((int)$id);
        $updated = Nota::getById((int)$id);
        $this->json(['ok' => true, 'is_pinned' => (bool)($updated['is_pinned'] ?? false)]);
    }

    /**
     * AJAX: return JSON list of notes for a given scope+referencia_id.
     * GET /notas/entidad?scope=tarea&referencia_id=42
     */
    public function listByEntity(): void
    {
        $this->requireAuth();

        $user     = $this->currentUser();
        $scope    = trim($_GET['scope'] ?? '');
        $refId    = (int)($_GET['referencia_id'] ?? 0);

        $validScopes = ['proyecto', 'tarea', 'subtarea'];
        if (!in_array($scope, $validScopes, true) || !$refId) {
            $this->json(['ok' => false, 'message' => 'Parámetros inválidos.'], 400);
        }

        // Access check
        if ($scope === 'proyecto') {
            $proyectoRef = Proyecto::getById($refId);
            if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                $this->json(['ok' => false, 'message' => 'Acceso denegado.'], 403);
            }
        } elseif ($scope === 'tarea') {
            $tareaRef = Tarea::getById($refId);
            if (!$tareaRef) {
                $this->json(['ok' => false, 'message' => 'Entidad no encontrada.'], 404);
            }
            $proyectoRef = Proyecto::getById((int)$tareaRef['proyecto_id']);
            if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                $this->json(['ok' => false, 'message' => 'Acceso denegado.'], 403);
            }
        } else {
            // scope === 'subtarea'
            $subtareaRef = Subtarea::getById($refId);
            if (!$subtareaRef) {
                $this->json(['ok' => false, 'message' => 'Entidad no encontrada.'], 404);
            }
            $tareaRef    = Tarea::getById((int)$subtareaRef['tarea_id']);
            $proyectoRef = $tareaRef ? Proyecto::getById((int)$tareaRef['proyecto_id']) : null;
            if (!$proyectoRef || !Proyecto::checkAccess($proyectoRef, $user['id'], $user['rol'])) {
                $this->json(['ok' => false, 'message' => 'Acceso denegado.'], 403);
            }
        }

        $notas  = Nota::getByEntity($scope, $refId);
        $userId = (int)$user['id'];
        $role   = $user['rol'];

        $result = array_map(function($n) use ($userId, $role) {
            return [
                'id'         => (int)$n['id'],
                'titulo'     => $n['titulo'],
                'contenido'  => $n['contenido'],
                'tipo'       => $n['tipo'],
                'is_pinned'  => (bool)($n['is_pinned'] ?? false),
                'autor'      => $n['autor_nombre'],
                'created_at' => isset($n['created_at'])
                    ? (new \DateTime($n['created_at']))->format('d/m/Y H:i')
                    : '',
                'can_edit'   => Nota::canEdit($n, $userId, $role),
                'can_delete' => (int)($n['user_id'] ?? 0) === $userId || in_array($role, ['GOD', 'ADMIN'], true),
                'can_pin'    => in_array($role, ['ADMIN', 'GOD'], true),
            ];
        }, $notas);

        $this->json(['ok' => true, 'notas' => $result]);
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();

        $user = $this->currentUser();
        $nota = Nota::getById((int)$id);

        if (!$nota) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'Nota no encontrada.'], 404);
            }
            $this->flash('error', 'Nota no encontrada.');
            $this->redirect('/notas');
        }

        // Owner can always delete their own; ADMIN can delete any; GOD can delete any
        $canDelete = (int)($nota['user_id'] ?? 0) === (int)$user['id']
                  || in_array($user['rol'], ['ADMIN', 'GOD'], true);

        if (!$canDelete) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['ok' => false, 'message' => 'No tienes permiso para eliminar esta nota.'], 403);
            }
            $this->flash('error', 'No tienes permiso para eliminar esta nota.');
            $this->redirect('/notas');
        }

        $adminOverride = in_array($user['rol'], ['ADMIN', 'GOD'], true);
        Nota::softDelete((int)$id, $user['id'], $adminOverride);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['ok' => true]);
        }
        $this->flash('success', 'Nota eliminada.');
        $this->redirect('/notas');
    }
}
