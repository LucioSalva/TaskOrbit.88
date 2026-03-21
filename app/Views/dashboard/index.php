<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role = $user['rol'] ?? '';
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estadoLabel = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
    <p class="text-muted small mb-0">Resumen de productividad</p>
  </div>
  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Nuevo Proyecto</a>
  <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card metric-card metric-active p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-kanban"></i></div>
        <div><div class="fs-3 fw-bold"><?php echo (int)($summary['proyectosActivos']??0); ?></div><div class="text-muted small">Proyectos Activos</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card metric-card metric-pending p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="metric-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-list-task"></i></div>
        <div><div class="fs-3 fw-bold"><?php echo (int)($summary['tareasPendientes']??0); ?></div><div class="text-muted small">Tareas Pendientes</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card metric-card metric-done p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
        <div><div class="fs-3 fw-bold"><?php echo (int)($summary['tareasTerminadas']??0); ?></div><div class="text-muted small">Tareas Terminadas</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card metric-card metric-overdue p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div><div class="fs-3 fw-bold"><?php echo (int)($summary['subtareasVencidas']??0); ?></div><div class="text-muted small">Subtareas Vencidas</div></div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<?php if (in_array($role,['ADMIN','GOD'])): ?>
<div class="card mb-4"><div class="card-body py-2">
  <form method="GET" action="<?php echo $appUrl; ?>/dashboard" class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small fw-semibold mb-1">Estado</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach($estadoLabel as $val=>$lbl): ?>
        <option value="<?php echo $e($val); ?>" <?php echo ($filterStatus??'')===$val?'selected':''; ?>><?php echo $e($lbl); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold mb-1">Usuario</label>
      <select name="userId" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach($usuarios??[] as $u): ?>
        <option value="<?php echo $e($u['id']); ?>" <?php echo ($filterUserId??'')==$u['id']?'selected':''; ?>><?php echo $e($u['nombre_completo']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label><input type="date" name="dateStart" class="form-control form-control-sm" value="<?php echo $e($dateStart??''); ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label><input type="date" name="dateEnd" class="form-control form-control-sm" value="<?php echo $e($dateEnd??''); ?>"></div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filtrar</button>
      <a href="<?php echo $appUrl; ?>/dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<!-- Charts -->
<?php if(!empty($productividadPorProyecto)): ?>
<div class="row g-3 mb-4">
  <div class="col-12 col-lg-7"><div class="card h-100">
    <div class="card-header fw-semibold small"><i class="bi bi-bar-chart-fill me-1 text-primary"></i> Productividad por Proyecto</div>
    <div class="card-body" style="height:260px"><canvas id="chart-proyectos"></canvas></div>
  </div></div>
  <div class="col-12 col-lg-5"><div class="card h-100">
    <div class="card-header fw-semibold small"><i class="bi bi-pie-chart-fill me-1 text-success"></i> Estado de Tareas</div>
    <div class="card-body d-flex align-items-center justify-content-center" style="height:260px"><canvas id="chart-estados" style="max-width:220px"></canvas></div>
  </div></div>
</div>
<?php endif; ?>
<!-- Dashboard chart data passed via data attributes (no unsafe-inline needed) -->
<div id="dashboard-data"
  data-productividad-proyectos="<?php echo htmlspecialchars(json_encode(array_values($productividadPorProyecto ?? [])), ENT_QUOTES, 'UTF-8'); ?>"
  data-productividad-usuarios="<?php echo htmlspecialchars(json_encode(array_values($productividadPorUsuario ?? [])), ENT_QUOTES, 'UTF-8'); ?>"
  data-tareas="<?php echo htmlspecialchars(json_encode(array_values($tareas ?? [])), ENT_QUOTES, 'UTF-8'); ?>"
  style="display:none"></div>

<!-- Productivity by User Chart (ADMIN/GOD only, shows USER-role users) -->
<?php if(in_array($role, ['ADMIN','GOD'])): ?>
<div class="card mb-4">
  <div class="card-header fw-semibold small">
    <i class="bi bi-person-bar-chart me-1 text-indigo"></i>
    <i class="bi bi-people-fill me-1 text-primary"></i> Productividad por Usuario
    <span class="text-muted fw-normal ms-1">(tareas terminadas)</span>
  </div>
  <div class="card-body">
    <?php if(empty($productividadPorUsuario)): ?>
      <div class="text-center py-4 text-muted small">
        <i class="bi bi-inbox display-6 d-block mb-2"></i>
        No hay datos de productividad aún
      </div>
    <?php else: ?>
      <div style="height:280px">
        <canvas id="chart-productividad-usuarios"></canvas>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Productivity Table -->
<?php if(!empty($productividadPorTarea)): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-semibold small"><i class="bi bi-table me-1 text-primary"></i>Productividad por Tarea</span>
    <span class="badge bg-secondary"><?php echo count($productividadPorTarea); ?> tareas</span>
  </div>
  <div class="table-responsive"><table class="table table-hover table-sm mb-0">
    <thead><tr><th>Tarea</th><th class="d-none d-md-table-cell">Proyecto</th><th class="d-none d-lg-table-cell">Responsable</th><th>Estado</th><th style="width:130px">Progreso</th><th class="text-center">Pendientes</th></tr></thead>
    <tbody>
    <?php foreach($productividadPorTarea as $pt):
      $proy = array_filter($proyectos??[], fn($p) => (int)$p['id']===(int)$pt['proyecto_id']);
      $proy = reset($proy);
    ?>
    <tr class="<?php echo $pt['isOverdue']?'table-danger':''; ?>">
      <td><div class="fw-medium small"><?php echo $e($pt['nombre']); ?></div><?php if($pt['isOverdue']): ?><span class="badge bg-danger" style="font-size:.65rem">Vencida</span><?php endif; ?></td>
      <td class="d-none d-md-table-cell small text-muted"><?php echo $e($proy['nombre']??'-'); ?></td>
      <td class="d-none d-lg-table-cell small text-muted"><?php echo $e($pt['usuario_nombre']??'-'); ?></td>
      <td><span class="badge badge-estado-<?php echo $e($pt['estado']); ?>"><?php echo $e($estadoLabel[$pt['estado']]??$pt['estado']); ?></span></td>
      <td><div class="d-flex align-items-center gap-1"><div class="progress flex-fill" style="height:6px"><div class="progress-bar bg-primary" style="width:<?php echo $e($pt['progreso']); ?>%"></div></div><small class="text-muted"><?php echo $e($pt['progreso']); ?>%</small></div></td>
      <td class="text-center"><span class="badge <?php echo $pt['subtareasPendientes']>0?'bg-warning text-dark':'bg-success'; ?>"><?php echo (int)$pt['subtareasPendientes']; ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Recent Projects -->
<?php if(!empty($proyectos)): ?>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-semibold small"><i class="bi bi-clock-history me-1 text-primary"></i>Proyectos Recientes</span>
    <a href="<?php echo $appUrl; ?>/proyectos" class="btn btn-outline-primary btn-sm">Ver todos</a>
  </div>
  <div class="row g-0 p-3">
    <?php foreach(array_slice($proyectos,0,6) as $proyecto): ?>
    <div class="col-12 col-md-6 col-xl-4 p-2">
      <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>" class="text-decoration-none">
        <div class="card border h-100 card-proyecto"><div class="card-body p-3">
          <div class="d-flex align-items-start justify-content-between mb-2">
            <h6 class="fw-semibold mb-0 text-truncate me-2"><?php echo $e($proyecto['nombre']); ?></h6>
            <span class="badge badge-estado-<?php echo $e($proyecto['estado']); ?> flex-shrink-0"><?php echo $e($estadoLabel[$proyecto['estado']]??$proyecto['estado']); ?></span>
          </div>
          <?php if($proyecto['descripcion']): ?><p class="text-muted small text-truncate-2 mb-2"><?php echo $e($proyecto['descripcion']); ?></p><?php endif; ?>
          <div class="d-flex align-items-center justify-content-between">
            <span class="badge badge-prioridad-<?php echo $e($proyecto['prioridad']); ?>"><?php echo ucfirst($e($proyecto['prioridad'])); ?></span>
            <?php if($proyecto['fecha_fin']): ?><small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo \App\Helpers\DateHelper::formatDate($proyecto['fecha_fin']); ?></small><?php endif; ?>
          </div>
        </div></div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-inbox display-4 mb-3 d-block"></i>
  <h5>Sin proyectos asignados</h5>
  <?php if(in_array($role,['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-lg me-1"></i> Crear primer proyecto</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="<?php echo \App\Core\View::asset('js/dashboard.js'); ?>"></script>
