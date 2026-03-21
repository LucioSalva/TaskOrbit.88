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
    <li class="breadcrumb-item active"><?php echo $e($p['nombre']??''); ?></li>
  </ol>
</nav>

<!-- Project Header -->
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <h1 class="h3 fw-bold mb-1"><?php echo $e($p['nombre']??''); ?></h1>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge badge-estado-<?php echo $e($p['estado']??''); ?>"><?php echo $e($estadoLabel[$p['estado']??'']??$p['estado']??''); ?></span>
          <span class="badge badge-prioridad-<?php echo $e($p['prioridad']??''); ?>"><?php echo ucfirst($e($p['prioridad']??'')); ?></span>
          <?php if($p['usuario_asignado_nombre']??''): ?>
          <span class="text-muted small"><i class="bi bi-person me-1"></i><?php echo $e($p['usuario_asignado_nombre']); ?></span>
          <?php endif; ?>
        </div>
        <?php if($p['descripcion']??''): ?>
        <p class="text-muted mt-2 mb-0"><?php echo $e($p['descripcion']); ?></p>
        <?php endif; ?>
      </div>
      <?php if (in_array($role, ['ADMIN','GOD'])): ?>
      <div class="d-flex gap-2">
        <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/editar" class="btn btn-warning btn-sm">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <button type="button" class="btn btn-danger btn-sm"
          data-delete-url="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/eliminar"
          data-delete-title="¿Eliminar proyecto?"
          data-delete-msg="Se eliminarán todas las tareas, subtareas y notas del proyecto."
          data-show-reason="true">
          <i class="bi bi-trash me-1"></i>Eliminar
        </button>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3 mt-2">
      <?php if($p['fecha_inicio']??''): ?>
      <div class="col-auto">
        <div class="small text-muted">Inicio</div>
        <div class="fw-semibold small"><?php echo \App\Helpers\DateHelper::formatDate($p['fecha_inicio']); ?></div>
      </div>
      <?php endif; ?>
      <?php if($p['fecha_fin']??''): ?>
      <div class="col-auto">
        <div class="small text-muted">Fin estimado</div>
        <div class="fw-semibold small"><?php echo \App\Helpers\DateHelper::formatDate($p['fecha_fin']); ?></div>
      </div>
      <?php endif; ?>
      <?php if($p['estimacion_minutos']??0): ?>
      <div class="col-auto">
        <div class="small text-muted">Duración estimada</div>
        <div class="fw-semibold small"><?php echo round(($p['estimacion_minutos']??0)/60); ?>h</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="proyectoTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-tareas">
      <i class="bi bi-list-task me-1"></i>Tareas
      <span class="badge bg-secondary ms-1"><?php echo count($tareas??[]); ?></span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notas">
      <i class="bi bi-sticky me-1"></i>Notas
      <span class="badge bg-secondary ms-1"><?php echo count($notas??[]); ?></span>
    </button>
  </li>
</ul>

<div class="tab-content">
  <!-- Tareas Tab -->
  <div class="tab-pane fade show active" id="tab-tareas">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small"><?php echo count($tareas??[]); ?> tarea(s)</span>
      <?php if (in_array($role, ['ADMIN','GOD'])): ?>
      <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/tareas/crear" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva Tarea
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($tareas)): ?>
    <div class="text-center py-4 text-muted">
      <i class="bi bi-list-task display-5 mb-2 d-block"></i>
      <p>Sin tareas en este proyecto.</p>
      <?php if (in_array($role, ['ADMIN','GOD'])): ?>
      <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/tareas/crear" class="btn btn-primary btn-sm">Agregar primera tarea</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="list-group">
      <?php foreach ($tareas as $tarea): ?>
      <div class="list-group-item list-group-item-action">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div class="flex-fill">
            <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/tareas" class="fw-semibold text-decoration-none">
              <?php echo $e($tarea['nombre']); ?>
            </a>
            <?php if($tarea['usuario_asignado_nombre']??''): ?>
            <div class="text-muted small"><i class="bi bi-person me-1"></i><?php echo $e($tarea['usuario_asignado_nombre']); ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <span class="badge badge-estado-<?php echo $e($tarea['estado']); ?>"><?php echo $e($estadoLabel[$tarea['estado']]??$tarea['estado']); ?></span>
            <span class="badge badge-prioridad-<?php echo $e($tarea['prioridad']); ?>"><?php echo ucfirst($e($tarea['prioridad'])); ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3">
      <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/tareas" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-list-task me-1"></i>Ver todas las tareas
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Notas Tab -->
  <div class="tab-pane fade" id="tab-notas">
    <?php if (in_array($role, ['ADMIN','GOD','USER'])): ?>
    <div class="card mb-3">
      <div class="card-body">
        <form method="POST" action="<?php echo $appUrl; ?>/notas">
          <?php echo \App\Helpers\CSRF::tokenField(); ?>
          <input type="hidden" name="scope" value="proyecto">
          <input type="hidden" name="referencia_id" value="<?php echo $e($p['id']); ?>">
          <div class="mb-2">
            <input type="text" name="titulo" class="form-control form-control-sm" placeholder="Título (opcional)" maxlength="160">
          </div>
          <div class="mb-2">
            <textarea name="contenido" class="form-control form-control-sm" rows="3" placeholder="Escribe una nota..." required></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-sticky me-1"></i>Agregar nota</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($notas)): ?>
    <div class="text-center py-4 text-muted"><p>Sin notas en este proyecto.</p></div>
    <?php else: ?>
    <?php foreach ($notas as $nota): ?>
    <div class="card mb-2">
      <div class="card-body py-2">
        <?php if($nota['titulo']??''): ?><div class="fw-semibold small"><?php echo $e($nota['titulo']); ?></div><?php endif; ?>
        <p class="mb-1 small"><?php echo nl2br($e($nota['contenido'])); ?></p>
        <div class="d-flex align-items-center justify-content-between">
          <small class="text-muted">
            <i class="bi bi-person me-1"></i><?php echo $e($nota['autor_nombre']??''); ?>
            &bull; <?php echo \App\Helpers\DateHelper::formatDatetime($nota['created_at']??''); ?>
          </small>
          <?php if ((int)($nota['user_id']??0) === (int)($user['id']??0)): ?>
          <form method="POST" action="<?php echo $appUrl; ?>/notas/<?php echo $e($nota['id']); ?>/eliminar" class="d-inline">
            <?php echo \App\Helpers\CSRF::tokenField(); ?>
            <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
