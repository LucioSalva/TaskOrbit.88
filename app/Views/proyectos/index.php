<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estadoLabel = ['por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada', 'enterado' => 'Enterado', 'ocupado' => 'Ocupado', 'aceptada' => 'Aceptada'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i>Proyectos</h1>
    <p class="text-muted small mb-0">Gesti&oacute;n de proyectos asignados</p>
  </div>
  <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
    <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Nuevo Proyecto
    </a>
  <?php endif; ?>
</div>

<!-- Filter bar -->
<?php
$filtros = $filtros ?? [];
$hasActiveFilters = !empty($filtros['estado']) || !empty($filtros['prioridad']) || !empty($filtros['q']);
$activeFilterCount = (!empty($filtros['estado']) ? 1 : 0) + (!empty($filtros['prioridad']) ? 1 : 0) + (!empty($filtros['q']) ? 1 : 0);
?>
<div class="card mb-3">
  <div class="card-body py-2">
    <!-- Mobile filter toggle -->
    <button class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-between mobile-filters-toggle"
            type="button" onclick="this.nextElementSibling.classList.toggle('show')">
      <span><i class="bi bi-funnel me-1"></i>Filtros<?php if ($hasActiveFilters): ?> <span class="badge bg-primary ms-1"><?php echo $activeFilterCount; ?></span><?php endif; ?></span>
      <i class="bi bi-chevron-down"></i>
    </button>
    <!-- Filter content -->
    <div class="mobile-filters-content <?php echo $hasActiveFilters ? 'show' : ''; ?>">
    <?php if ($hasActiveFilters): ?>
    <div class="filter-chips d-md-none mt-2">
      <?php if (!empty($filtros['estado'])): ?>
        <span class="filter-chip"><?php echo $e($estadoLabel[$filtros['estado']] ?? $filtros['estado']); ?></span>
      <?php endif; ?>
      <?php if (!empty($filtros['prioridad'])): ?>
        <span class="filter-chip"><?php echo ucfirst($e($filtros['prioridad'])); ?></span>
      <?php endif; ?>
      <?php if (!empty($filtros['q'])): ?>
        <span class="filter-chip">"<?php echo $e($filtros['q']); ?>"</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <form method="GET" class="row g-2 align-items-end mt-md-0 mt-2">
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">Estado</label>
        <select name="estado" class="form-select form-select-sm js-autosubmit">
          <option value="">Todos</option>
          <?php foreach ($estadoLabel as $val => $lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($filtros['estado'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $e($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">Prioridad</label>
        <select name="prioridad" class="form-select form-select-sm js-autosubmit">
          <option value="">Todas</option>
          <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Cr&iacute;tica'] as $val => $lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($filtros['prioridad'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold mb-1">Buscar</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre del proyecto..." value="<?php echo $e($filtros['q'] ?? ''); ?>">
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-fill flex-md-grow-0"><i class="bi bi-search me-1"></i>Buscar</button>
        <a href="<?php echo $appUrl; ?>/proyectos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
      </div>
    </form>
    </div>
  </div>
</div>

<?php
// Filters are applied server-side in ProyectosController; use the array directly
$proyectosFiltrados = $proyectos ?? [];
?>

<!-- Estado Legend (collapsed by default) -->
<div class="mb-3">
  <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none" type="button"
          data-bs-toggle="collapse" data-bs-target="#estado-legend">
    <i class="bi bi-question-circle me-1"></i>Que significan los estados?
  </button>
  <div class="collapse mt-2" id="estado-legend">
    <div class="card card-body py-2 small">
      <div class="row g-2">
        <div class="col-6 col-md-4"><span class="badge badge-estado-por_hacer me-1">Por Hacer</span> Pendiente de iniciar</div>
        <div class="col-6 col-md-4"><span class="badge badge-estado-haciendo me-1">Haciendo</span> En progreso activo</div>
        <div class="col-6 col-md-4"><span class="badge badge-estado-terminada me-1">Terminada</span> Completada, pendiente revision</div>
        <div class="col-6 col-md-4"><span class="badge badge-estado-enterado me-1">Enterado</span> Notificado / visto</div>
        <div class="col-6 col-md-4"><span class="badge badge-estado-ocupado me-1">Ocupado</span> En pausa por otro trabajo</div>
        <div class="col-6 col-md-4"><span class="badge badge-estado-aceptada me-1">Aceptada</span> Revisada y aprobada</div>
      </div>
    </div>
  </div>
</div>

<!-- View Switcher -->
<div id="vistas-container" data-page="proyectos">
  <ul class="nav nav-pills view-switcher mb-3">
    <li class="nav-item">
      <a class="nav-link" href="#lista" data-view="lista">
        <i class="bi bi-list-ul me-1"></i><span>Lista</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="#kanban" data-view="kanban">
        <i class="bi bi-kanban me-1"></i><span>Kanban</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="#usuario" data-view="usuario">
        <i class="bi bi-people me-1"></i><span>Por Usuario</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="#timeline" data-view="timeline">
        <i class="bi bi-clock-history me-1"></i><span>Timeline</span>
      </a>
    </li>
  </ul>

  <div class="vista-panel" data-vista="lista">
    <?php include __DIR__ . '/_vista_lista.php'; ?>
  </div>
  <div class="vista-panel" data-vista="kanban" style="display:none">
    <?php include __DIR__ . '/_vista_kanban.php'; ?>
  </div>
  <div class="vista-panel" data-vista="usuario" style="display:none">
    <?php include __DIR__ . '/_vista_usuario.php'; ?>
  </div>
  <div class="vista-panel" data-vista="timeline" style="display:none">
    <?php include __DIR__ . '/_vista_timeline.php'; ?>
  </div>
</div>
<?php if (!empty($usuarios ?? [])): ?>
<script>var USUARIOS_ASIGNABLES = <?php echo json_encode(array_map(fn($u) => ['id'=>$u['id'],'nombre_completo'=>$u['nombre_completo'],'rol'=>$u['rol']??''], $usuarios ?? [])); ?>;</script>
<?php elseif (!isset($GLOBALS['USUARIOS_ASIGNABLES_SET'])): ?>
<script>if(typeof USUARIOS_ASIGNABLES === 'undefined') var USUARIOS_ASIGNABLES = [];</script>
<?php endif; ?>
<script src="<?php echo \App\Core\View::asset('js/proyectos.js'); ?>"></script>
