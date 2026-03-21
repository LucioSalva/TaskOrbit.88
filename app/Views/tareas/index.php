<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$p      = $proyecto ?? [];
$estadoLabel = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>"><?php echo $e($p['nombre']??''); ?></a></li>
    <li class="breadcrumb-item active">Tareas</li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-list-task me-2 text-primary"></i><?php echo $e($p['nombre']??'Tareas'); ?></h1>
    <div class="d-flex gap-2 mt-1">
      <span class="badge badge-estado-<?php echo $e($p['estado']??''); ?>"><?php echo $e($estadoLabel[$p['estado']??'']??$p['estado']??''); ?></span>
      <span class="badge badge-prioridad-<?php echo $e($p['prioridad']??''); ?>"><?php echo ucfirst($e($p['prioridad']??'')); ?></span>
    </div>
  </div>
  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas/crear" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Nueva Tarea
  </a>
  <?php endif; ?>
</div>

<?php if (empty($tareas)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-list-task display-4 mb-3 d-block"></i>
  <h5>Sin tareas</h5>
  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas/crear" class="btn btn-primary btn-sm mt-2">Agregar primera tarea</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="accordion" id="tareasAccordion">
  <?php foreach ($tareas as $idx => $tarea): ?>
  <div class="card mb-3" data-tarea-id="<?php echo $e($tarea['id']); ?>">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 py-2">
      <div class="d-flex align-items-center gap-2 flex-fill min-w-0">
        <button class="btn btn-sm btn-outline-secondary p-1" type="button" data-bs-toggle="collapse" data-bs-target="#subtareas-<?php echo $e($tarea['id']); ?>">
          <i class="bi bi-chevron-down" style="font-size:.75rem"></i>
        </button>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate"><?php echo $e($tarea['nombre']); ?></div>
          <?php if($tarea['usuario_asignado_nombre']??''): ?>
          <div class="text-muted" style="font-size:.75rem"><i class="bi bi-person me-1"></i><?php echo $e($tarea['usuario_asignado_nombre']); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <span class="badge badge-prioridad-<?php echo $e($tarea['prioridad']); ?> d-none d-md-inline"><?php echo ucfirst($e($tarea['prioridad'])); ?></span>

        <?php if($tarea['fecha_fin']??''): ?>
        <?php $isOverdue = \App\Helpers\DateHelper::isOverdue($tarea['fecha_fin'], $tarea['estado']); ?>
        <small class="text-<?php echo $isOverdue?'danger':'muted'; ?> d-none d-lg-inline">
          <i class="bi bi-calendar3 me-1"></i><?php echo \App\Helpers\DateHelper::formatDate($tarea['fecha_fin']); ?>
        </small>
        <?php endif; ?>

        <!-- Estado badge (visible) -->
        <span class="badge estado-badge badge-estado-<?php echo $e($tarea['estado']); ?>">
          <?php echo $e($estadoLabel[$tarea['estado']]??$tarea['estado']); ?>
        </span>

        <!-- Quick estado change -->
        <div class="estado-btn-group btn-group btn-group-sm">
          <?php foreach(['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $est=>$lbl): ?>
          <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/estado" class="d-inline">
            <?php echo \App\Helpers\CSRF::tokenField(); ?>
            <input type="hidden" name="estado" value="<?php echo $e($est); ?>">
            <button type="submit" class="btn btn-outline-secondary <?php echo $tarea['estado']===$est?'active-estado':''; ?>" title="<?php echo $e($estadoLabel[$est]); ?>">
              <?php echo $e($lbl); ?>
            </button>
          </form>
          <?php endforeach; ?>
        </div>

        <?php if (in_array($role, ['ADMIN','GOD'])): ?>
        <a href="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/editar" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil"></i></a>
        <button type="button" class="btn btn-outline-danger btn-sm"
          data-delete-url="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/eliminar"
          data-delete-title="¿Eliminar tarea?"
          data-delete-msg="Se eliminarán las subtareas y notas asociadas."
          data-show-reason="true">
          <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
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
          <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>/subtareas" class="card card-body py-2 bg-light">
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
                  <option value="critica">Crítica</option>
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
        <?php if (empty($tarea['subtareas'])): ?>
        <div class="text-muted small py-2">Sin subtareas.</div>
        <?php else: ?>
        <?php foreach ($tarea['subtareas'] as $sub): ?>
        <div class="subtarea-item <?php echo $sub['estado']==='terminada'?'terminada':''; ?>" data-subtarea-id="<?php echo $e($sub['id']); ?>">
          <div class="d-flex align-items-center gap-2">
            <div class="flex-fill">
              <span class="subtarea-nombre small fw-medium"><?php echo $e($sub['nombre']); ?></span>
              <?php if($sub['descripcion']??''): ?>
              <div class="text-muted" style="font-size:.75rem"><?php echo $e($sub['descripcion']); ?></div>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
              <span class="badge estado-badge badge-estado-<?php echo $e($sub['estado']); ?>" style="font-size:.7rem">
                <?php echo $e($estadoLabel[$sub['estado']]??$sub['estado']); ?>
              </span>
              <!-- Quick subtarea estado -->
              <div class="btn-group btn-group-sm">
                <?php foreach(['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $est=>$lbl): ?>
                <form method="POST" action="<?php echo $appUrl; ?>/subtareas/<?php echo $e($sub['id']); ?>/estado" class="d-inline">
                  <?php echo \App\Helpers\CSRF::tokenField(); ?>
                  <input type="hidden" name="estado" value="<?php echo $e($est); ?>">
                  <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1 <?php echo $sub['estado']===$est?'active-estado':''; ?>" title="<?php echo $e($estadoLabel[$est]); ?>" style="font-size:.7rem">
                    <?php echo $e($lbl); ?>
                  </button>
                </form>
                <?php endforeach; ?>
              </div>
              <?php if (in_array($role, ['ADMIN','GOD'])): ?>
              <form method="POST" action="<?php echo $appUrl; ?>/subtareas/<?php echo $e($sub['id']); ?>/eliminar" class="d-inline">
                <?php echo \App\Helpers\CSRF::tokenField(); ?>
                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-x"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
