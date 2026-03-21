<?php
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
