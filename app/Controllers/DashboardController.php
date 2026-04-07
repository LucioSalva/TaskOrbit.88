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

        // Defaults seguros: si una metrica truena, el dashboard sigue cargando
        // en vez de explotar al usuario en la cara. Cada bloque se aisla.
        $resumen             = ['total_proyectos'=>0,'total_tareas'=>0,'total_subtareas'=>0,'tareas_vencidas'=>0,'proyectos_riesgo'=>0];
        $metricasUsuarios    = [];
        $metricasProyectos   = [];
        $tareasVencidas      = [];
        $tareasSinMov        = [];
        $distribucion        = ['por_hacer'=>0,'haciendo'=>0,'terminada'=>0,'enterado'=>0,'ocupado'=>0,'aceptada'=>0];
        $misDatos            = ['total'=>0,'por_hacer'=>0,'haciendo'=>0,'terminada'=>0,'vencidas'=>0];
        $semaforoProyectos   = ['verde'=>0,'amarillo'=>0,'rojo'=>0,'neutral'=>0];
        $semaforoTareas      = ['verde'=>0,'amarillo'=>0,'rojo'=>0,'neutral'=>0];
        $notificacionesCount = 0;
        $dashboardErrors     = [];

        try { $resumen           = MetricasService::resumenGlobal($role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'resumen';           error_log('[Dashboard] resumenGlobal: '.$e->getMessage()); }

        try { $metricasUsuarios  = MetricasService::metricasPorUsuario($role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'usuarios';          error_log('[Dashboard] metricasPorUsuario: '.$e->getMessage()); }

        try { $metricasProyectos = MetricasService::metricasPorProyecto($role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'proyectos';         error_log('[Dashboard] metricasPorProyecto: '.$e->getMessage()); }

        try { $tareasVencidas    = MetricasService::tareasVencidas($role, $userId, 12); }
        catch (\Throwable $e) { $dashboardErrors[] = 'vencidas';          error_log('[Dashboard] tareasVencidas: '.$e->getMessage()); }

        try { $tareasSinMov      = MetricasService::tareasSinMovimiento($role, $userId, 10); }
        catch (\Throwable $e) { $dashboardErrors[] = 'sin_movimiento';    error_log('[Dashboard] tareasSinMovimiento: '.$e->getMessage()); }

        try { $distribucion      = MetricasService::distribucionEstados($role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'distribucion';      error_log('[Dashboard] distribucionEstados: '.$e->getMessage()); }

        try { $misDatos          = MetricasService::misDatos($userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'mis_datos';         error_log('[Dashboard] misDatos: '.$e->getMessage()); }

        try { $semaforoProyectos = MetricasService::semaforoResumen('proyectos', $role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'semaforo_proyectos';error_log('[Dashboard] semaforoProyectos: '.$e->getMessage()); }

        try { $semaforoTareas    = MetricasService::semaforoResumen('tareas',    $role, $userId); }
        catch (\Throwable $e) { $dashboardErrors[] = 'semaforo_tareas';   error_log('[Dashboard] semaforoTareas: '.$e->getMessage()); }

        try { $notificacionesCount = Notificacion::getUnreadCount($userId); }
        catch (\Throwable $e) { error_log('[Dashboard] notificacionesCount: '.$e->getMessage()); }

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
            'dashboardErrors'     => $dashboardErrors,
        ]);
    }
}
