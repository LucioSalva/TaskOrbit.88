<?php
/**
 * ================================================================
 *  TASKORBIT
 *  Humberto Salvador Ruiz Lucio
 * ================================================================
 *  Plataforma privada de gestión de proyectos, tareas,
 *  subtareas, procesos y colaboración interna.
 *
 *  Módulo: Dashboard
 *  Archivo: DashboardController.php
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
use App\Models\Notificacion;
use App\Services\{MetricasService, SemaforoService};

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $user   = $this->currentUser();
        $role   = $user['rol'];
        $userId = (int)$user['id'];

        // ---- Métricas ----
        $resumen           = MetricasService::resumenGlobal($role, $userId);
        $metricasUsuarios  = MetricasService::metricasPorUsuario($role, $userId);
        $metricasProyectos = MetricasService::metricasPorProyecto($role, $userId);
        $tareasVencidas    = MetricasService::tareasVencidas($role, $userId, 12);
        $tareasSinMov      = MetricasService::tareasSinMovimiento($role, $userId, 10);
        $distribucion      = MetricasService::distribucionEstados($role, $userId);

        // Mi propio perfil de métricas (para USER o para la fila destacada en ADMIN/GOD)
        $misDatos = MetricasService::misDatos($userId);

        // ---- Semáforo summary ----
        $semaforoProyectos = MetricasService::semaforoResumen('proyectos', $role, $userId);
        $semaforoTareas    = MetricasService::semaforoResumen('tareas',    $role, $userId);

        // ---- Notificaciones ----
        $notificacionesCount = Notificacion::getUnreadCount($userId);

        $this->view('dashboard/index', [
            'flash'               => $this->getFlash(),
            'resumen'             => $resumen,
            'metricasUsuarios'    => $metricasUsuarios,
            'metricasProyectos'   => $metricasProyectos,
            'tareasVencidas'      => $tareasVencidas,
            'tareasSinMov'        => $tareasSinMov,
            'distribucion'        => $distribucion,
            'misDatos'            => $misDatos,
            'semaforoProyectos'   => $semaforoProyectos,
            'semaforoTareas'      => $semaforoTareas,
            'notificacionesCount' => $notificacionesCount,
        ]);
    }
}
