<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Notificaciones
 *  Archivo: NotificacionesController.php
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
use App\Models\Notificacion;

class NotificacionesController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user  = $this->currentUser();
        $items = Notificacion::getByUser($user['id'], 20);
        $this->json([
            'ok'    => true,
            'items' => $items,
            'unread'=> Notificacion::getUnreadCount($user['id']),
        ]);
    }

    public function markRead(string $id): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();
        $user = $this->currentUser();
        Notificacion::markRead((int)$id, $user['id']);
        $this->json(['ok' => true]);
    }

    public function markAllRead(): void
    {
        $this->requireAuth();
        CSRF::verifyRequest();
        $user = $this->currentUser();
        Notificacion::markAllRead($user['id']);
        $this->json(['ok' => true]);
    }
}
