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
          <span class="badge badge-estado-<?php echo $e($p['estado']??''); ?>"
                data-bs-toggle="tooltip" title="<?php echo $e($estadoTooltips[$p['estado']??''] ?? ''); ?>">
            <?php echo $e($estadoLabel[$p['estado']??'']??$p['estado']??''); ?>
          </span>
          <span class="badge badge-prioridad-<?php echo $e($p['prioridad']??''); ?>"><?php echo ucfirst($e($p['prioridad']??'')); ?></span>
          <?php echo \App\Services\SemaforoService::badge($p['semaforo'] ?? 'neutral'); ?>
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
          data-delete-preview-url="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']); ?>/eliminar-preview"
          data-delete-title="Eliminar proyecto &quot;<?php echo $e($p['nombre']); ?>&quot;?"
          data-delete-msg="Se eliminaran TODAS las tareas, subtareas y notas del proyecto. Esta accion es irreversible."
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
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-evidencias">
      <i class="bi bi-paperclip me-1"></i>Evidencias
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
      <a href="<?php echo $appUrl; ?>/tareas/<?php echo $e($tarea['id']); ?>" class="list-group-item list-group-item-action text-decoration-none">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div class="flex-fill">
            <span class="fw-semibold">
              <?php echo $e($tarea['nombre']); ?>
            </span>
            <?php if($tarea['usuario_asignado_nombre']??''): ?>
            <div class="text-muted small"><i class="bi bi-person me-1"></i><?php echo $e($tarea['usuario_asignado_nombre']); ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <span class="badge badge-estado-<?php echo $e($tarea['estado']); ?>"><?php echo $e($estadoLabel[$tarea['estado']]??$tarea['estado']); ?></span>
            <span class="badge badge-prioridad-<?php echo $e($tarea['prioridad']); ?>"><?php echo ucfirst($e($tarea['prioridad'])); ?></span>
          </div>
        </div>
      </a>
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
    <?php
    $notasScope      = 'proyecto';
    $notasRefId      = (int)$p['id'];
    $notasCanWrite   = true;
    $notasRole       = $role;
    $notasUserId     = (int)($user['id'] ?? 0);
    $notasPanelTitle = 'Bitácora del Proyecto';
    include __DIR__ . '/../partials/_notas_panel.php';
    ?>
  </div>

  <!-- Evidencias Tab -->
  <div class="tab-pane fade" id="tab-evidencias">
    <?php
    $evidTipo             = 'proyecto';
    $evidId               = (int)$p['id'];
    $evidencias           = \App\Models\Evidencia::getByEntidad('proyecto', (int)$p['id']);
    $evidCanUpload        = true;
    $evidCanDelete        = in_array($role, ['GOD', 'ADMIN']);
    $evidEntityTerminada  = ($p['estado'] ?? '') === 'terminada';
    include __DIR__ . '/../partials/_evidencias_panel.php';
    ?>
  </div>
</div>
