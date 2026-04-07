<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$e      = $e ?? fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estadoLabel = $estadoLabel ?? ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
$p      = $p ?? ($proyecto ?? []);

// Filter tareas in this view
$filterEstado   = $_GET['estado_t']   ?? '';
$filterPrioridad= $_GET['prioridad_t']?? '';
$filterUsuario  = $_GET['usuario_t']  ?? '';
$filteredTareas = array_filter($tareas ?? [], function($t) use ($filterEstado, $filterPrioridad, $filterUsuario) {
    if ($filterEstado    && $t['estado']              !== $filterEstado)    return false;
    if ($filterPrioridad && ($t['prioridad'] ?? '')   !== $filterPrioridad) return false;
    if ($filterUsuario   && ((string)($t['usuario_asignado_id'] ?? '')) !== $filterUsuario) return false;
    return true;
});
?>
<!-- Filter bar for tasks -->
<?php
$hasTaskFilters = $filterEstado || $filterPrioridad || $filterUsuario;
$taskFilterCount = ($filterEstado ? 1 : 0) + ($filterPrioridad ? 1 : 0) + ($filterUsuario ? 1 : 0);
?>
<!-- Mobile filter toggle -->
<button class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-between mb-2 mobile-filters-toggle"
        type="button" id="tarea-filter-toggle">
  <span><i class="bi bi-funnel me-1"></i>Filtros<?php if ($hasTaskFilters): ?> <span class="badge bg-primary ms-1"><?php echo $taskFilterCount; ?></span><?php endif; ?></span>
  <i class="bi bi-chevron-down"></i>
</button>
<?php if ($hasTaskFilters): ?>
<div class="filter-chips d-md-none mb-2">
  <?php if ($filterEstado): ?>
    <span class="filter-chip"><?php echo htmlspecialchars(['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','enterado'=>'Enterado','ocupado'=>'Ocupado','terminada'=>'Terminada','aceptada'=>'Aceptada'][$filterEstado] ?? $filterEstado); ?></span>
  <?php endif; ?>
  <?php if ($filterPrioridad): ?>
    <span class="filter-chip"><?php echo ucfirst(htmlspecialchars($filterPrioridad)); ?></span>
  <?php endif; ?>
  <?php if ($filterUsuario): ?>
    <span class="filter-chip">Usuario</span>
  <?php endif; ?>
</div>
<?php endif; ?>
<div class="mobile-filters-content <?php echo $hasTaskFilters ? 'show' : ''; ?>" id="tarea-filter-content">
<form method="GET" class="row g-2 mb-3 filter-bar" id="tarea-filter-form">
  <?php foreach ($_GET as $k => $v): if (in_array($k, ['estado_t','prioridad_t','usuario_t'])) continue; ?>
    <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
  <?php endforeach; ?>
  <div class="col-6 col-md-auto">
    <select name="estado_t" class="form-select form-select-sm js-autosubmit">
      <option value="">Todos los estados</option>
      <?php foreach (['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','enterado'=>'Enterado','ocupado'=>'Ocupado','terminada'=>'Terminada','aceptada'=>'Aceptada'] as $v => $l): ?>
        <option value="<?php echo $v; ?>" <?php echo $filterEstado === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-auto">
    <select name="prioridad_t" class="form-select form-select-sm js-autosubmit">
      <option value="">Todas las prioridades</option>
      <?php foreach (['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $v => $l): ?>
        <option value="<?php echo $v; ?>" <?php echo $filterPrioridad === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (in_array($role, ['ADMIN','GOD']) && !empty($usuarios)): ?>
  <div class="col-12 col-md-auto">
    <select name="usuario_t" class="form-select form-select-sm js-autosubmit">
      <option value="">Todos los usuarios</option>
      <?php foreach ($usuarios as $u): ?>
        <option value="<?php echo $u['id']; ?>" <?php echo $filterUsuario === (string)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if ($hasTaskFilters): ?>
    <div class="col-auto">
      <a href="?" class="btn btn-sm btn-outline-secondary">Limpiar</a>
    </div>
  <?php endif; ?>
</form>
</div>
<script nonce="<?= CSP_NONCE ?>">
document.getElementById('tarea-filter-toggle')?.addEventListener('click', function() {
  document.getElementById('tarea-filter-content')?.classList.toggle('show');
});
</script>

<?php if (empty($filteredTareas)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-list-task display-4 mb-3 d-block"></i>
  <h5>Sin tareas</h5>
  <p class="small">No hay tareas que coincidan con los filtros.</p>
  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas/crear" class="btn btn-primary btn-sm mt-2">Agregar primera tarea</a>
  <?php endif; ?>
</div>
<?php else: ?>
<?php
// Batch prefetch de evidencias para evitar N+1 (1 query por tarea + 1 por subtarea)
$_tareaIds    = [];
$_subtareaIds = [];
foreach ($filteredTareas as $_t) {
    $_tareaIds[] = (int)$_t['id'];
    foreach (($_t['subtareas'] ?? []) as $_s) {
        $_subtareaIds[] = (int)$_s['id'];
    }
}
$evidenciasByTareaId    = \App\Models\Evidencia::getByEntidades('tarea',    $_tareaIds);
$evidenciasBySubtareaId = \App\Models\Evidencia::getByEntidades('subtarea', $_subtareaIds);
?>
<div class="accordion" id="tareasAccordion">
  <?php foreach ($filteredTareas as $idx => $tarea): ?>
  <div class="card mb-3" data-tarea-id="<?php echo $e($tarea['id']); ?>"
       data-nombre="<?php echo $e($tarea['nombre']); ?>"
       data-prioridad="<?php echo $e($tarea['prioridad']); ?>"
       data-usuario-id="<?php echo $e($tarea['usuario_asignado_id'] ?? ''); ?>"
       data-usuario-nombre="<?php echo $e($tarea['usuario_asignado_nombre'] ?? ''); ?>"
       data-descripcion="<?php echo $e($tarea['descripcion'] ?? ''); ?>">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 py-2">
      <div class="d-flex align-items-center gap-2 flex-fill min-w-0">
        <button class="btn btn-sm btn-outline-secondary p-1" type="button" data-bs-toggle="collapse" data-bs-target="#subtareas-<?php echo $e($tarea['id']); ?>">
          <i class="bi bi-chevron-down text-xs-custom"></i>
        </button>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate"><?php echo $e($tarea['nombre']); ?></div>
          <?php if($tarea['usuario_asignado_nombre']??''): ?>
          <div class="text-muted text-xs-custom"><i class="bi bi-person me-1"></i><?php echo $e($tarea['usuario_asignado_nombre']); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Badges row -->
      <div class="d-flex align-items-center gap-1 flex-shrink-0 flex-wrap">
        <span class="badge badge-prioridad-<?php echo $e($tarea['prioridad']); ?>"><?php echo ucfirst($e($tarea['prioridad'])); ?></span>
        <?php
        $estadoTooltips = [
          'por_hacer' => 'Pendiente de iniciar',
          'haciendo'  => 'En progreso activo',
          'terminada' => 'Completada, pendiente de revision',
          'enterado'  => 'Notificado / visto',
          'ocupado'   => 'En pausa por otro trabajo',
          'aceptada'  => 'Revisada y aprobada',
        ];
        ?>
        <span class="badge estado-badge badge-estado-<?php echo $e($tarea['estado']); ?>"
              data-bs-toggle="tooltip" data-bs-placement="top"
              title="<?php echo $e($estadoTooltips[$tarea['estado']] ?? ''); ?>">
          <?php echo $e($estadoLabel[$tarea['estado']]??$tarea['estado']); ?>
        </span>
        <?php echo \App\Services\SemaforoService::badge($tarea['semaforo'] ?? 'neutral'); ?>

        <?php if($tarea['fecha_fin']??''): ?>
        <?php $isOverdue = \App\Helpers\DateHelper::isOverdue($tarea['fecha_fin'], $tarea['estado']); ?>
        <small class="text-<?php echo $isOverdue?'danger':'muted'; ?> d-none d-md-inline">
          <i class="bi bi-calendar3 me-1"></i><?php echo \App\Helpers\DateHelper::formatDate($tarea['fecha_fin']); ?>
        </small>
        <?php endif; ?>
      </div>

      <!-- Actions row -->
      <div class="d-flex align-items-center gap-1 flex-shrink-0 flex-wrap">
        <!-- Quick estado change (only if user can change this task's status) -->
        <?php
        $canChangeEstado = ($role === 'GOD') ||
                           (in_array($role, ['ADMIN', 'USER']) &&
                            (int)($tarea['usuario_asignado_id'] ?? 0) === (int)($user['id'] ?? 0));
        ?>
        <?php if ($canChangeEstado): ?>
        <div class="estado-btn-group btn-group btn-group-sm">
          <?php foreach(['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $est=>$lbl): ?>
          <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/estado" class="d-inline" data-change-estado>
            <?php echo \App\Helpers\CSRF::tokenField(); ?>
            <input type="hidden" name="estado" value="<?php echo $e($est); ?>">
            <button type="submit" class="btn btn-outline-secondary <?php echo $tarea['estado']===$est?'active-estado':''; ?>" data-estado="<?php echo $e($est); ?>" title="<?php echo $e($estadoLabel[$est]); ?>">
              <?php echo $e($lbl); ?>
            </button>
          </form>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array($role, ['ADMIN','GOD'])): ?>
        <button type="button"
          class="btn btn-xs btn-outline-primary"
          title="Edicion rapida"
          data-action="quick-edit"
          data-entity-type="tarea"
          data-entity-id="<?php echo (int)$tarea['id']; ?>"
          data-entity-data="<?php echo htmlspecialchars(json_encode(['nombre'=>$tarea['nombre'],'descripcion'=>$tarea['descripcion']??'','fechaFin'=>$tarea['fecha_fin']??'','prioridad'=>$tarea['prioridad']??'media']), ENT_QUOTES); ?>">
          <i class="bi bi-pencil-square"></i>
        </button>
        <button type="button"
          class="btn btn-xs btn-outline-secondary"
          title="Reasignar"
          data-action="quick-assign"
          data-entity-type="tarea"
          data-entity-id="<?php echo (int)$tarea['id']; ?>"
          data-assignee-id="<?php echo (int)($tarea['usuario_asignado_id']??0); ?>"
          data-entity-name="<?php echo htmlspecialchars($tarea['nombre'], ENT_QUOTES); ?>">
          <i class="bi bi-person-check"></i>
        </button>
        <a href="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/editar" class="btn btn-xs btn-outline-warning"><i class="bi bi-pencil"></i></a>
        <button type="button" class="btn btn-xs btn-outline-danger ms-auto"
          data-delete-url="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/eliminar"
          data-delete-title="Eliminar tarea &quot;<?php echo $e($tarea['nombre']); ?>&quot;?"
          data-delete-msg="Se eliminaran todas las subtareas y notas asociadas a esta tarea. Esta accion es irreversible."
          data-show-reason="true">
          <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
        <button type="button"
          class="btn btn-xs btn-outline-info"
          title="Nota rapida"
          data-action="quick-nota"
          data-entity-type="tarea"
          data-entity-id="<?php echo (int)$tarea['id']; ?>"
          data-entity-name="<?php echo htmlspecialchars($tarea['nombre'], ENT_QUOTES); ?>">
          <i class="bi bi-sticky"></i>
        </button>
      </div>
    </div>

    <?php if($tarea['descripcion']??''): ?>
    <div class="px-3 py-1 small text-muted border-bottom"><?php echo $e($tarea['descripcion']); ?></div>
    <?php endif; ?>

    <!-- Subtareas collapse -->
    <div id="subtareas-<?php echo $e($tarea['id']); ?>" class="collapse show">
      <div class="card-body pt-2 pb-2">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-semibold text-muted">Subtareas (<?php echo count($tarea['subtareas']??[]); ?>)</span>
          <?php if (in_array($role, ['ADMIN','GOD'])): ?>
          <button class="btn btn-outline-primary btn-sm py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#form-subtarea-<?php echo $e($tarea['id']); ?>">
            <i class="bi bi-plus-lg"></i> Agregar
          </button>
          <?php endif; ?>
        </div>

        <!-- Add subtarea form -->
        <?php if (in_array($role, ['ADMIN','GOD'])): ?>
        <div class="collapse mb-2" id="form-subtarea-<?php echo $e($tarea['id']); ?>">
          <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/subtareas" class="card card-body py-2 bg-light" data-ajax-subtarea data-tarea-id="<?php echo $e($tarea['id']); ?>">
            <?php echo \App\Helpers\CSRF::tokenField(); ?>
            <div class="row g-2">
              <div class="col-12 col-md-6">
                <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre de subtarea *" required minlength="3">
              </div>
              <div class="col-6 col-md-3">
                <select name="prioridad" class="form-select form-select-sm">
                  <option value="baja">Baja</option>
                  <option value="media" selected>Media</option>
                  <option value="alta">Alta</option>
                  <option value="critica">Cr&iacute;tica</option>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">Agregar</button>
              </div>
              <div class="col-12">
                <textarea name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Notas informativas (opcional)"></textarea>
              </div>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- Subtareas list -->
        <div id="subtareas-list-<?php echo $e($tarea['id']); ?>">
        <?php if (empty($tarea['subtareas'])): ?>
        <div class="text-muted small py-2">Sin subtareas.</div>
        <?php else: ?>
        <?php foreach ($tarea['subtareas'] as $sub): ?>
        <div class="subtarea-item <?php echo $sub['estado']==='terminada'?'terminada':''; ?>"
             data-subtarea-id="<?php echo $e($sub['id']); ?>"
             data-usuario-id="<?php echo (int)($sub['usuario_asignado_id'] ?? 0); ?>"
             data-usuario-nombre="<?php echo $e($sub['usuario_asignado_nombre'] ?? ''); ?>">
          <div class="d-flex align-items-center gap-2">
            <div class="flex-fill">
              <span class="subtarea-nombre small fw-medium"><?php echo $e($sub['nombre']); ?></span>
              <span class="text-muted fs-xs2 ms-1 assignee-name"><?php
                echo !empty($sub['usuario_asignado_nombre']) ? '('.$e($sub['usuario_asignado_nombre']).')' : '';
              ?></span>
              <?php if($sub['descripcion']??''): ?>
              <div class="text-muted fs-xs2"><?php echo $e($sub['descripcion']); ?></div>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
              <span class="badge estado-badge badge-estado-<?php echo $e($sub['estado']); ?> text-xxs">
                <?php echo $e($estadoLabel[$sub['estado']]??$sub['estado']); ?>
              </span>
              <!-- Quick subtarea estado (only if user can change this task's status) -->
              <?php
              $canChangeSubEstado = ($role === 'GOD') ||
                                    (in_array($role, ['ADMIN', 'USER']) &&
                                     (int)($tarea['usuario_asignado_id'] ?? 0) === (int)($user['id'] ?? 0));
              ?>
              <?php if ($canChangeSubEstado): ?>
              <div class="btn-group btn-group-sm">
                <?php foreach(['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $est=>$lbl): ?>
                <form method="POST" action="<?php echo $appUrl; ?>/subtareas/<?php echo $e($sub['id']); ?>/estado" class="d-inline" data-change-estado>
                  <?php echo \App\Helpers\CSRF::tokenField(); ?>
                  <input type="hidden" name="estado" value="<?php echo $e($est); ?>">
                  <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1 fs-xs <?php echo $sub['estado']===$est?'active-estado':''; ?>" data-estado="<?php echo $e($est); ?>" title="<?php echo $e($estadoLabel[$est]); ?>">
                    <?php echo $e($lbl); ?>
                  </button>
                </form>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <?php if (in_array($role, ['ADMIN','GOD'])): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1"
                title="Reasignar subtarea"
                data-action="quick-assign"
                data-entity-type="subtarea"
                data-entity-id="<?php echo (int)$sub['id']; ?>"
                data-assignee-id="<?php echo (int)($sub['usuario_asignado_id'] ?? 0); ?>"
                data-entity-name="<?php echo $e($sub['nombre']); ?>">
                <i class="bi bi-person-check"></i>
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1"
                title="Eliminar subtarea"
                data-delete-url="<?php echo $appUrl; ?>/subtareas/<?php echo $e($sub['id']); ?>/eliminar"
                data-delete-title="Eliminar subtarea?"
                data-delete-msg="Se eliminara la subtarea &quot;<?php echo $e($sub['nombre']); ?>&quot;. Esta accion no se puede deshacer."
                data-show-reason="false">
                <i class="bi bi-x"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
          <!-- Subtarea evidencias (collapsible) -->
          <div class="mt-1">
            <button class="btn btn-link btn-sm p-0 text-muted fs-xs" type="button"
                    data-bs-toggle="collapse" data-bs-target="#evid-sub-<?php echo $e($sub['id']); ?>">
              <i class="bi bi-paperclip me-1"></i>Evidencias
            </button>
            <div class="collapse" id="evid-sub-<?php echo $e($sub['id']); ?>">
              <?php
              $evidTipo             = 'subtarea';
              $evidId               = (int)$sub['id'];
              $evidencias           = $evidenciasBySubtareaId[(int)$sub['id']] ?? [];
              $evidCanUpload        = true;
              $evidCanDelete        = in_array($role, ['GOD', 'ADMIN']);
              $evidEntityTerminada  = ($sub['estado'] ?? '') === 'terminada';
              include __DIR__ . '/../partials/_evidencias_panel.php';
              ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <!-- Tarea evidencias -->
        <div class="mt-2 pt-2 border-top">
          <button class="btn btn-link btn-sm p-0 text-muted" type="button"
                  data-bs-toggle="collapse" data-bs-target="#evid-tarea-<?php echo $e($tarea['id']); ?>">
            <i class="bi bi-paperclip me-1"></i>Evidencias de tarea
          </button>
          <div class="collapse" id="evid-tarea-<?php echo $e($tarea['id']); ?>">
            <?php
            $evidTipo             = 'tarea';
            $evidId               = (int)$tarea['id'];
            $evidencias           = $evidenciasByTareaId[(int)$tarea['id']] ?? [];
            $evidCanUpload        = true;
            $evidCanDelete        = in_array($role, ['GOD', 'ADMIN']);
            $evidEntityTerminada  = ($tarea['estado'] ?? '') === 'terminada';
            include __DIR__ . '/../partials/_evidencias_panel.php';
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
