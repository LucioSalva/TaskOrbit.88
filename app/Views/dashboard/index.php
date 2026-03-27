<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role = $user['rol'] ?? '';
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estadoLabel = [
    'por_hacer' => 'Por Hacer',
    'haciendo'  => 'Haciendo',
    'terminada' => 'Terminada',
    'enterado'  => 'Enterado',
    'ocupado'   => 'Ocupado',
    'aceptada'  => 'Aceptada',
];
$prioridadLabel = [
    'baja'    => 'Baja',
    'media'   => 'Media',
    'alta'    => 'Alta',
    'critica' => 'Critica',
];

$resumen             = $resumen ?? [];
$metricasUsuarios    = $metricasUsuarios ?? [];
$metricasProyectos   = $metricasProyectos ?? [];
$tareasVencidas      = $tareasVencidas ?? [];
$tareasSinMov        = $tareasSinMov ?? [];
$distribucion        = $distribucion ?? [];
$misDatos            = $misDatos ?? [];
$flash               = $flash ?? [];
$notificacionesCount = $notificacionesCount ?? 0;

$totalVencidas      = (int)($resumen['total_tareas_vencidas'] ?? 0);
$totalSinMovimiento = (int)($resumen['total_tareas_sin_movimiento'] ?? 0);
?>

<!-- ===================== Page Header ===================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 mb-md-4">
    <div>
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-speedometer2 me-2 text-primary d-none d-sm-inline"></i>Dashboard
        </h1>
        <p class="text-muted small mb-0">
            Bienvenido, <?php echo $e($user['nombre_completo'] ?? ''); ?>
            <span class="badge ms-1 <?php
                echo match ($role) {
                    'GOD'   => 'role-badge-god',
                    'ADMIN' => 'role-badge-admin',
                    default => 'role-badge-user',
                };
            ?>"><?php echo $e($role); ?></span>
        </p>
    </div>
    <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
    <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Nuevo </span>Proyecto
    </a>
    <?php endif; ?>
</div>

<!-- ===================== Flash Messages ===================== -->
<?php if (!empty($flash)): ?>
<div class="mb-4">
    <?php foreach ($flash as $f): ?>
    <?php $alertType = (($f['type'] ?? '') === 'error') ? 'danger' : $e($f['type'] ?? 'info'); ?>
    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
        <?php echo $e($f['message'] ?? ''); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===================== KPI Summary Cards ===================== -->
<div class="row g-3 mb-4">
    <!-- Proyectos Activos -->
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary kpi-icon-box">
                        <i class="bi bi-kanban fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1"><?php echo (int)($resumen['total_proyectos_activos'] ?? 0); ?></div>
                        <div class="text-muted small">Proyectos Activos</div>
                        <div class="text-muted small opacity-75">
                            <?php echo (int)($resumen['total_proyectos'] ?? 0); ?> totales
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tareas Activas -->
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary kpi-icon-box">
                        <i class="bi bi-list-task fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1"><?php echo (int)($resumen['total_tareas_activas'] ?? 0); ?></div>
                        <div class="text-muted small">Tareas Activas</div>
                        <div class="text-muted small opacity-75">
                            en proceso ahora
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terminadas -->
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success kpi-icon-box">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1"><?php echo (int)($resumen['total_tareas_terminadas'] ?? 0); ?></div>
                        <div class="text-muted small">Terminadas</div>
                        <div class="text-muted small opacity-75">
                            tareas completadas
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vencidas -->
    <div class="col-6 col-lg-3">
        <div class="card h-100 <?php echo $totalVencidas > 0 ? 'bg-danger text-white' : ''; ?>">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center kpi-icon-box <?php echo $totalVencidas > 0
                        ? 'bg-white bg-opacity-25 text-white'
                        : 'bg-danger bg-opacity-10 text-danger'; ?>">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1"><?php echo $totalVencidas; ?></div>
                        <div class="<?php echo $totalVencidas > 0 ? 'text-white' : 'text-muted'; ?> small">Vencidas</div>
                        <div class="<?php echo $totalVencidas > 0 ? 'text-white opacity-75' : 'text-muted opacity-75'; ?> small">
                            <?php echo $totalSinMovimiento; ?> sin movimiento
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Mi Productividad ===================== -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-person-circle me-1 text-primary"></i>Mi productividad
        </h6>
    </div>
    <div class="card-body">
        <?php
        $miCumplimiento   = (int)($misDatos['porcentaje_cumplimiento'] ?? 0);
        $miAsignadas      = (int)($misDatos['total_tareas_asignadas'] ?? 0);
        $miTerminadas     = (int)($misDatos['total_tareas_terminadas'] ?? 0);
        $miVencidas       = (int)($misDatos['total_tareas_vencidas'] ?? 0);
        $miCarga          = (int)($misDatos['carga_actual'] ?? 0);
        $miTiempoPromedio = $misDatos['tiempo_promedio_dias'] ?? null;

        $cumplColor = 'bg-danger';
        if ($miCumplimiento >= 75) {
            $cumplColor = 'bg-success';
        } elseif ($miCumplimiento >= 50) {
            $cumplColor = 'bg-warning';
        }
        ?>
        <div class="row align-items-center g-2 g-md-3">
            <div class="col-12 col-md-5">
                <div class="mb-1 d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">Cumplimiento</span>
                    <span class="small fw-bold"><?php echo $miCumplimiento; ?>%</span>
                </div>
                <div class="progress progress-h10">
                    <div class="progress-bar <?php echo $cumplColor; ?>"
                         role="progressbar"
                         aria-valuenow="<?php echo $miCumplimiento; ?>"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         data-progress-pct="<?php echo max($miCumplimiento, 2); ?>">
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-7">
                <div class="d-flex flex-wrap gap-3 text-center justify-content-around">
                    <div>
                        <div class="fs-6 fs-md-5 fw-bold"><?php echo $miAsignadas; ?></div>
                        <div class="text-muted text-xxs">Asignadas</div>
                    </div>
                    <div>
                        <div class="fs-6 fs-md-5 fw-bold text-success"><?php echo $miTerminadas; ?></div>
                        <div class="text-muted text-xxs">Terminadas</div>
                    </div>
                    <div>
                        <div class="fs-6 fs-md-5 fw-bold <?php echo $miVencidas > 0 ? 'text-danger' : ''; ?>"><?php echo $miVencidas; ?></div>
                        <div class="text-muted text-xxs">Vencidas</div>
                    </div>
                    <div>
                        <div class="fs-6 fs-md-5 fw-bold"><?php echo $miCarga; ?></div>
                        <div class="text-muted text-xxs">Carga</div>
                    </div>
                    <div>
                        <div class="fs-6 fs-md-5 fw-bold">
                            <?php echo $miTiempoPromedio !== null ? $e($miTiempoPromedio) . 'd' : '&mdash;'; ?>
                        </div>
                        <div class="text-muted text-xxs">Prom.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Productividad por Usuario (ADMIN/GOD) ===================== -->
<?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-people me-1 text-primary"></i>Productividad por Usuario
        </h6>
        <?php if (!empty($metricasUsuarios)): ?>
        <span class="badge bg-secondary"><?php echo count($metricasUsuarios); ?> usuarios</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($metricasUsuarios)): ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            <span class="small">Sin datos de usuarios.</span>
        </div>
        <?php else: ?>
        <!-- Desktop: table -->
        <div class="table-responsive dashboard-table-desktop">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th class="text-center">Asignadas</th>
                        <th class="text-center">Terminadas</th>
                        <th class="text-center">En Proceso</th>
                        <th class="text-center">Vencidas</th>
                        <th class="text-center">Cumplimiento (%)</th>
                        <th class="text-center">Sin Movimiento</th>
                        <th class="text-center">Carga Actual</th>
                        <th class="text-center">Tiempo Prom (dias)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metricasUsuarios as $mu): ?>
                    <?php
                    $muCumpl = (int)($mu['porcentaje_cumplimiento'] ?? 0);
                    $muVenc  = (int)($mu['total_tareas_vencidas'] ?? 0);
                    $muSinM  = (int)($mu['tareas_sin_movimiento'] ?? 0);
                    $muTiempo = $mu['tiempo_promedio_dias'] ?? null;

                    $cumplClass = 'text-danger';
                    if ($muCumpl >= 75) {
                        $cumplClass = 'text-success';
                    } elseif ($muCumpl >= 50) {
                        $cumplClass = 'text-warning';
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="fw-medium"><?php echo $e($mu['nombre_completo'] ?? ''); ?></span>
                        </td>
                        <td class="text-center"><?php echo (int)($mu['total_tareas_asignadas'] ?? 0); ?></td>
                        <td class="text-center"><?php echo (int)($mu['total_tareas_terminadas'] ?? 0); ?></td>
                        <td class="text-center"><?php echo (int)($mu['total_tareas_en_proceso'] ?? 0); ?></td>
                        <td class="text-center">
                            <?php if ($muVenc > 0): ?>
                            <span class="badge bg-danger"><?php echo $muVenc; ?></span>
                            <?php else: ?>
                            <?php echo $muVenc; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold <?php echo $cumplClass; ?>"><?php echo $muCumpl; ?>%</span>
                        </td>
                        <td class="text-center">
                            <span class="<?php echo $muSinM > 0 ? 'text-warning fw-semibold' : ''; ?>"><?php echo $muSinM; ?></span>
                        </td>
                        <td class="text-center"><?php echo (int)($mu['carga_actual'] ?? 0); ?></td>
                        <td class="text-center">
                            <?php echo $muTiempo !== null ? $e($muTiempo) . ' dias' : '&mdash;'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Mobile: cards -->
        <div class="dashboard-cards-mobile p-2">
            <?php foreach ($metricasUsuarios as $mu): ?>
            <?php
            $muCumpl = (int)($mu['porcentaje_cumplimiento'] ?? 0);
            $muVenc  = (int)($mu['total_tareas_vencidas'] ?? 0);
            $muSinM  = (int)($mu['tareas_sin_movimiento'] ?? 0);
            $muCarga = (int)($mu['carga_actual'] ?? 0);

            $cumplColor = 'bg-danger';
            if ($muCumpl >= 75) { $cumplColor = 'bg-success'; }
            elseif ($muCumpl >= 50) { $cumplColor = 'bg-warning'; }
            $borderColor = str_replace('bg-', 'border-', $cumplColor);
            ?>
            <div class="dashboard-user-card card dashboard-user-card--<?php echo str_replace('bg-', '', $cumplColor); ?>">
              <div class="d-flex align-items-center gap-2">
                <span class="avatar"><?php echo mb_substr($e($mu['nombre_completo'] ?? ''), 0, 1); ?></span>
                <div class="flex-fill min-w-0">
                  <div class="fw-semibold small text-truncate"><?php echo $e($mu['nombre_completo'] ?? ''); ?></div>
                  <div class="d-flex align-items-center gap-2 mt-1">
                    <div class="progress flex-fill progress-h6">
                      <div class="progress-bar <?php echo $cumplColor; ?>" data-progress-pct="<?php echo max($muCumpl, 2); ?>"></div>
                    </div>
                    <span class="fw-bold small mnw-35"><?php echo $muCumpl; ?>%</span>
                  </div>
                </div>
              </div>
              <div class="user-metrics">
                <div class="user-metric">
                  <span class="user-metric-value"><?php echo (int)($mu['total_tareas_asignadas'] ?? 0); ?></span>
                  <span class="user-metric-label">Asig.</span>
                </div>
                <div class="user-metric">
                  <span class="user-metric-value text-success"><?php echo (int)($mu['total_tareas_terminadas'] ?? 0); ?></span>
                  <span class="user-metric-label">Term.</span>
                </div>
                <div class="user-metric">
                  <span class="user-metric-value <?php echo $muVenc > 0 ? 'text-danger' : ''; ?>"><?php echo $muVenc; ?></span>
                  <span class="user-metric-label">Venc.</span>
                </div>
                <div class="user-metric">
                  <span class="user-metric-value"><?php echo $muCarga; ?></span>
                  <span class="user-metric-label">Carga</span>
                </div>
                <?php if ($muSinM > 0): ?>
                <div class="user-metric">
                  <span class="user-metric-value text-warning"><?php echo $muSinM; ?></span>
                  <span class="user-metric-label">Sin Mov.</span>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===================== Avance por Proyecto ===================== -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-kanban me-1 text-primary"></i>Avance por Proyecto
        </h6>
        <?php if (!empty($metricasProyectos)): ?>
        <span class="badge bg-secondary"><?php echo count($metricasProyectos); ?> proyectos</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($metricasProyectos)): ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            <span class="small">Sin proyectos.</span>
        </div>
        <?php else: ?>
        <!-- Desktop: table -->
        <div class="table-responsive dashboard-table-desktop">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Proyecto</th>
                        <th>Responsable</th>
                        <th class="mnw-140">Avance</th>
                        <th class="text-center">Tareas</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Semaforo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metricasProyectos as $mp): ?>
                    <?php
                    $mpAvance    = (int)($mp['porcentaje_avance'] ?? 0);
                    $mpTotal     = (int)($mp['total_tareas'] ?? 0);
                    $mpTermin    = (int)($mp['total_tareas_terminadas'] ?? 0);
                    $mpVenc      = (int)($mp['total_tareas_vencidas'] ?? 0);
                    $mpEnRiesgo  = !empty($mp['proyecto_en_riesgo']) || $mpVenc > 0;

                    $barColor = 'bg-danger';
                    if ($mpAvance >= 75) {
                        $barColor = 'bg-success';
                    } elseif ($mpAvance >= 40) {
                        $barColor = 'bg-warning';
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="fw-medium"><?php echo $e($mp['nombre'] ?? ''); ?></span>
                            <?php if (!empty($mp['prioridad'])): ?>
                            <br>
                            <span class="badge badge-prioridad-<?php echo $e($mp['prioridad']); ?> mt-1">
                                <?php echo $e($prioridadLabel[$mp['prioridad']] ?? $mp['prioridad']); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?php echo $e($mp['responsable'] ?? '&mdash;'); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-fill progress-h8">
                                    <div class="progress-bar <?php echo $barColor; ?>"
                                         role="progressbar"
                                         aria-valuenow="<?php echo $mpAvance; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         data-progress-pct="<?php echo max($mpAvance, 2); ?>">
                                    </div>
                                </div>
                                <small class="fw-semibold text-nowrap"><?php echo $mpAvance; ?>%</small>
                            </div>
                        </td>
                        <td class="text-center small">
                            <?php if ($mpTotal === 0): ?>
                            <span class="text-muted">Sin tareas</span>
                            <?php else: ?>
                            <span class="fw-semibold"><?php echo $mpTotal; ?></span>
                            <span class="text-muted">total</span><br>
                            <span class="text-success"><?php echo $mpTermin; ?></span> /
                            <?php if ($mpVenc > 0): ?>
                            <span class="text-danger fw-semibold"><?php echo $mpVenc; ?> venc.</span>
                            <?php else: ?>
                            <span class="text-muted">0 venc.</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($mp['proyecto_estado'])): ?>
                            <span class="badge badge-estado-<?php echo $e($mp['proyecto_estado']); ?>">
                                <?php echo $e($estadoLabel[$mp['proyecto_estado']] ?? $mp['proyecto_estado']); ?>
                            </span>
                            <?php else: ?>
                            &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $mpSemaforo = 'neutral';
                            if (!empty($mp['proyecto_estado']) && in_array($mp['proyecto_estado'], ['terminada','aceptada'])) {
                                $mpSemaforo = 'verde';
                            } elseif ($mpVenc > 0) {
                                $mpSemaforo = 'rojo';
                            } elseif ($mpEnRiesgo) {
                                $mpSemaforo = 'amarillo';
                            } elseif ($mpAvance > 0) {
                                $mpSemaforo = 'verde';
                            }
                            echo \App\Services\SemaforoService::badge($mpSemaforo);
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Mobile: project cards -->
        <div class="dashboard-cards-mobile p-2">
            <?php foreach ($metricasProyectos as $mp): ?>
            <?php
            $mpAvance    = (int)($mp['porcentaje_avance'] ?? 0);
            $mpTotal     = (int)($mp['total_tareas'] ?? 0);
            $mpTermin    = (int)($mp['total_tareas_terminadas'] ?? 0);
            $mpVenc      = (int)($mp['total_tareas_vencidas'] ?? 0);
            $mpEnRiesgo  = !empty($mp['proyecto_en_riesgo']) || $mpVenc > 0;

            $barColor = 'bg-danger';
            if ($mpAvance >= 75) { $barColor = 'bg-success'; }
            elseif ($mpAvance >= 40) { $barColor = 'bg-warning'; }

            $mpSemaforo = 'neutral';
            if (!empty($mp['proyecto_estado']) && in_array($mp['proyecto_estado'], ['terminada','aceptada'])) {
                $mpSemaforo = 'verde';
            } elseif ($mpVenc > 0) {
                $mpSemaforo = 'rojo';
            } elseif ($mpEnRiesgo) {
                $mpSemaforo = 'amarillo';
            } elseif ($mpAvance > 0) {
                $mpSemaforo = 'verde';
            }
            ?>
            <div class="dashboard-project-card">
              <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="flex-fill min-w-0">
                  <div class="fw-semibold small text-truncate"><?php echo $e($mp['nombre'] ?? ''); ?></div>
                  <div class="d-flex gap-1 flex-wrap mt-1 align-items-center">
                    <?php if (!empty($mp['proyecto_estado'])): ?>
                    <span class="badge badge-estado-<?php echo $e($mp['proyecto_estado']); ?> text-2xs"><?php echo $e($estadoLabel[$mp['proyecto_estado']] ?? $mp['proyecto_estado']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($mp['prioridad'])): ?>
                    <span class="badge badge-prioridad-<?php echo $e($mp['prioridad']); ?> text-2xs"><?php echo $e($prioridadLabel[$mp['prioridad']] ?? $mp['prioridad']); ?></span>
                    <?php endif; ?>
                    <?php echo \App\Services\SemaforoService::badge($mpSemaforo); ?>
                  </div>
                </div>
                <div class="text-end flex-shrink-0">
                  <div class="fw-bold <?php echo $mpAvance >= 75 ? 'text-success' : ($mpAvance >= 40 ? 'text-warning' : 'text-danger'); ?>"><?php echo $mpAvance; ?>%</div>
                  <div class="text-muted text-2xs"><?php echo $mpTermin; ?>/<?php echo $mpTotal; ?> tareas</div>
                </div>
              </div>
              <div class="progress progress-h6 margin-top-sm">
                <div class="progress-bar <?php echo $barColor; ?>" data-progress-pct="<?php echo max($mpAvance, 2); ?>"></div>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="text-muted text-2xs">
                  <?php if (!empty($mp['responsable'])): ?>
                  <i class="bi bi-person me-1"></i><?php echo $e($mp['responsable']); ?>
                  <?php endif; ?>
                </span>
                <?php if ($mpVenc > 0): ?>
                <span class="badge bg-danger text-3xs"><?php echo $mpVenc; ?> venc.</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== Semáforo Summary ===================== -->
<?php
$semProy = $semaforoProyectos ?? ['verde'=>0,'amarillo'=>0,'rojo'=>0,'neutral'=>0];
$semTar  = $semaforoTareas    ?? ['verde'=>0,'amarillo'=>0,'rojo'=>0,'neutral'=>0];
$semConfig = [
    'verde'   => ['label'=>'Verde',   'icon'=>'bi-circle-fill', 'color'=>'#28a745'],
    'amarillo'=> ['label'=>'Amarillo','icon'=>'bi-circle-fill', 'color'=>'#ffc107'],
    'rojo'    => ['label'=>'Rojo',    'icon'=>'bi-circle-fill', 'color'=>'#dc3545'],
    'neutral' => ['label'=>'Neutral', 'icon'=>'bi-circle',      'color'=>'#adb5bd'],
];
?>
<div class="row g-3 mb-4">
  <div class="col-12 col-md-6">
    <div class="card h-100">
      <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold small"><i class="bi bi-circle-fill text-primary me-1 text-3xs"></i>Semáforo Proyectos</h6>
      </div>
      <div class="card-body py-2">
        <div class="d-flex gap-3 flex-wrap">
          <?php foreach ($semConfig as $nivel => $cfg): ?>
          <div class="text-center">
            <div class="fw-bold fs-5" data-color="<?php echo htmlspecialchars($cfg['color']); ?>"><?php echo (int)($semProy[$nivel] ?? 0); ?></div>
            <div class="small text-muted"><?php echo $cfg['label']; ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card h-100">
      <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold small"><i class="bi bi-circle-fill text-info me-1 text-3xs"></i>Semáforo Tareas</h6>
      </div>
      <div class="card-body py-2">
        <div class="d-flex gap-3 flex-wrap">
          <?php foreach ($semConfig as $nivel => $cfg): ?>
          <div class="text-center">
            <div class="fw-bold fs-5" data-color="<?php echo htmlspecialchars($cfg['color']); ?>"><?php echo (int)($semTar[$nivel] ?? 0); ?></div>
            <div class="small text-muted"><?php echo $cfg['label']; ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===================== Alertas: Vencidas + Sin Movimiento ===================== -->
<div class="row g-3 mb-4">
    <!-- Tareas Vencidas -->
    <div class="col-12 col-lg-6">
        <div class="card h-100 border-danger border-opacity-25">
            <div class="card-header bg-danger bg-opacity-10">
                <h6 class="mb-0 fw-semibold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Tareas Vencidas
                    <?php if (!empty($tareasVencidas)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($tareasVencidas); ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tareasVencidas)): ?>
                <div class="text-center py-4 text-success">
                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                    <span class="small fw-medium">Sin tareas vencidas</span>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($tareasVencidas, 0, 12) as $tv): ?>
                    <?php
                    $tvLink = in_array($role, ['ADMIN', 'GOD'])
                        ? $appUrl . '/tareas/' . (int)($tv['id'] ?? 0) . '/editar'
                        : '#';
                    ?>
                    <a href="<?php echo $tvLink; ?>"
                       class="list-group-item list-group-item-action <?php echo in_array($role, ['ADMIN', 'GOD']) ? '' : 'pe-none'; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2">
                                <div class="fw-medium small"><?php echo $e($tv['nombre'] ?? ''); ?></div>
                                <div class="text-muted small">
                                    <?php echo $e($tv['proyecto_nombre'] ?? ''); ?>
                                    <?php if (!empty($tv['responsable'])): ?>
                                    &middot; <?php echo $e($tv['responsable']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge bg-danger"><?php echo (int)($tv['dias_vencida'] ?? 0); ?>d</span>
                                <?php if (!empty($tv['prioridad'])): ?>
                                <br>
                                <span class="badge badge-prioridad-<?php echo $e($tv['prioridad']); ?> mt-1">
                                    <?php echo $e($prioridadLabel[$tv['prioridad']] ?? $tv['prioridad']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tareas Sin Movimiento -->
    <div class="col-12 col-lg-6">
        <div class="card h-100 border-warning border-opacity-25">
            <div class="card-header bg-warning bg-opacity-10">
                <h6 class="mb-0 fw-semibold text-warning">
                    <i class="bi bi-clock-history me-1"></i>Sin Movimiento
                    <?php if (!empty($tareasSinMov)): ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo count($tareasSinMov); ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tareasSinMov)): ?>
                <div class="text-center py-4 text-success">
                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                    <span class="small fw-medium">Todo en movimiento</span>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($tareasSinMov, 0, 10) as $tsm): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2">
                                <div class="fw-medium small"><?php echo $e($tsm['nombre'] ?? ''); ?></div>
                                <div class="text-muted small">
                                    <?php echo $e($tsm['proyecto_nombre'] ?? ''); ?>
                                    <?php if (!empty($tsm['responsable'])): ?>
                                    &middot; <?php echo $e($tsm['responsable']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge bg-warning text-dark">
                                    <?php echo (int)($tsm['dias_sin_actividad'] ?? 0); ?>d sin actividad
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Charts Data Container ===================== -->
<?php
$distTotal = array_sum(array_map('intval', $distribucion));
$hasChartData = $distTotal > 0 || !empty($metricasUsuarios) || !empty($metricasProyectos);

$chartDistribucion = json_encode($distribucion, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$chartUsuarios = json_encode(array_map(function ($mu) {
    return [
        'usuario_id'              => $mu['usuario_id'] ?? null,
        'nombre_completo'         => $mu['nombre_completo'] ?? '',
        'total_tareas_asignadas'  => (int)($mu['total_tareas_asignadas'] ?? 0),
        'total_tareas_terminadas' => (int)($mu['total_tareas_terminadas'] ?? 0),
        'porcentaje_cumplimiento' => (float)($mu['porcentaje_cumplimiento'] ?? 0),
        'carga_actual'            => (int)($mu['carga_actual'] ?? 0),
    ];
}, $metricasUsuarios), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$chartProyectos = json_encode(array_map(function ($mp) {
    return [
        'proyecto_id'      => $mp['proyecto_id'] ?? null,
        'nombre'           => $mp['nombre'] ?? '',
        'porcentaje_avance' => (int)($mp['porcentaje_avance'] ?? 0),
    ];
}, $metricasProyectos), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<?php if ($hasChartData): ?>
<div id="dashboard-data"
     class="d-none"
     data-distribucion="<?php echo $e($chartDistribucion); ?>"
     data-metricas-usuarios="<?php echo $e($chartUsuarios); ?>"
     data-metricas-proyectos="<?php echo $e($chartProyectos); ?>">
</div>

<!-- ===================== Charts Section ===================== -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">
            <a class="text-decoration-none text-reset d-flex align-items-center justify-content-between"
               data-bs-toggle="collapse"
               href="#collapseCharts"
               role="button"
               aria-expanded="true"
               aria-controls="collapseCharts">
                <span><i class="bi bi-bar-chart-line me-1 text-primary"></i>Graficas</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
        </h6>
    </div>
    <div class="collapse show" id="collapseCharts" data-mobile-collapse="true">
        <div class="card-body">
            <div class="row g-3">
                <!-- Doughnut: Distribucion de Tareas -->
                <?php if ($distTotal > 0): ?>
                <div class="col-md-6">
                    <h6 class="small fw-semibold text-center mb-3">
                        <i class="bi bi-pie-chart me-1"></i>Distribucion de Tareas
                    </h6>
                    <div class="d-flex justify-content-center">
                        <canvas id="chart-estados" class="chart-max-h"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bar: Cumplimiento por Usuario (ADMIN/GOD) -->
                <?php if (in_array($role, ['ADMIN', 'GOD']) && !empty($metricasUsuarios)): ?>
                <div class="col-md-6">
                    <h6 class="small fw-semibold text-center mb-3">
                        <i class="bi bi-people me-1"></i>Cumplimiento por Usuario
                    </h6>
                    <div>
                        <canvas id="chart-usuarios" class="chart-max-h"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================== Dashboard JS ===================== -->
<script src="<?php echo \App\Core\View::asset('js/dashboard.js'); ?>"></script>
