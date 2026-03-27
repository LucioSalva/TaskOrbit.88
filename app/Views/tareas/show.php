<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$t      = $tarea    ?? [];
$p      = $proyecto ?? [];
$estadoLabel = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>"><?php echo $e($p['nombre']??''); ?></a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas">Tareas</a></li>
    <li class="breadcrumb-item active"><?php echo $e($t['nombre']??''); ?></li>
  </ol>
</nav>

<!-- Task Header -->
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <h1 class="h3 fw-bold mb-1"><?php echo $e($t['nombre']??''); ?></h1>
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
          <span class="badge badge-estado-<?php echo $e($t['estado']??''); ?>"
                data-bs-toggle="tooltip" title="<?php echo $e($estadoTooltips[$t['estado']??''] ?? ''); ?>">
            <?php echo $e($estadoLabel[$t['estado']??'']??$t['estado']??''); ?>
          </span>
          <span class="badge badge-prioridad-<?php echo $e($t['prioridad']??''); ?>"><?php echo ucfirst($e($t['prioridad']??'')); ?></span>
          <?php echo \App\Services\SemaforoService::badge($t['semaforo'] ?? 'neutral'); ?>
          <?php if($t['usuario_asignado_nombre']??''): ?>
          <span class="text-muted small"><i class="bi bi-person me-1"></i><?php echo $e($t['usuario_asignado_nombre']); ?></span>
          <?php endif; ?>
        </div>
        <?php if($t['descripcion']??''): ?>
        <p class="text-muted mt-2 mb-0"><?php echo $e($t['descripcion']); ?></p>
        <?php endif; ?>
      </div>
      <?php if (in_array($role, ['ADMIN','GOD'])): ?>
      <div class="d-flex gap-2">
        <a href="<?php echo $appUrl; ?>/tareas/<?php echo $e($t['id']); ?>/editar" class="btn btn-warning btn-sm">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <button type="button" class="btn btn-danger btn-sm"
          data-delete-url="<?php echo $appUrl; ?>/tareas/<?php echo $e($t['id']); ?>/eliminar"
          data-delete-title="Eliminar tarea &quot;<?php echo $e($t['nombre']); ?>&quot;?"
          data-delete-msg="Se eliminaran todas las subtareas y notas asociadas a esta tarea. Esta accion es irreversible."
          data-show-reason="true">
          <i class="bi bi-trash me-1"></i>Eliminar
        </button>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-auto">
        <div class="small text-muted">Proyecto</div>
        <div class="fw-semibold small">
          <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>" class="text-decoration-none">
            <?php echo $e($p['nombre']??''); ?>
          </a>
        </div>
      </div>
      <?php if($t['fecha_inicio']??''): ?>
      <div class="col-auto">
        <div class="small text-muted">Inicio</div>
        <div class="fw-semibold small"><?php echo \App\Helpers\DateHelper::formatDate($t['fecha_inicio']); ?></div>
      </div>
      <?php endif; ?>
      <?php if($t['fecha_fin']??''): ?>
      <div class="col-auto">
        <div class="small text-muted">Fin estimado</div>
        <div class="fw-semibold small"><?php echo \App\Helpers\DateHelper::formatDate($t['fecha_fin']); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="tareaTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-subtareas">
      <i class="bi bi-list-check me-1"></i>Subtareas
      <span class="badge bg-secondary ms-1"><?php echo count($t['subtareas']??[]); ?></span>
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
  <!-- Subtareas Tab -->
  <div class="tab-pane fade show active" id="tab-subtareas">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small"><?php echo count($t['subtareas']??[]); ?> subtarea(s)</span>
    </div>

    <?php if (empty($t['subtareas'])): ?>
    <div class="text-center py-4 text-muted">
      <i class="bi bi-list-check display-5 mb-2 d-block"></i>
      <p>Sin subtareas en esta tarea.</p>
    </div>
    <?php else: ?>
    <div class="list-group">
      <?php foreach ($t['subtareas'] as $sub): ?>
      <div class="list-group-item <?php echo ($sub['estado']??'')==='terminada'?'list-group-item-light':''; ?>">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div class="flex-fill">
            <span class="fw-semibold"><?php echo $e($sub['nombre']); ?></span>
            <?php if($sub['descripcion']??''): ?>
            <div class="text-muted small"><?php echo $e($sub['descripcion']); ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <span class="badge badge-estado-<?php echo $e($sub['estado']); ?>"><?php echo $e($estadoLabel[$sub['estado']]??$sub['estado']); ?></span>
            <span class="badge badge-prioridad-<?php echo $e($sub['prioridad']??'media'); ?>"><?php echo ucfirst($e($sub['prioridad']??'media')); ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Notas Tab -->
  <div class="tab-pane fade" id="tab-notas">
    <?php
    $notasScope      = 'tarea';
    $notasRefId      = (int)$t['id'];
    $notasCanWrite   = true;
    $notasRole       = $role;
    $notasUserId     = (int)($user['id'] ?? 0);
    $notasPanelTitle = 'Bitacora de la Tarea';
    include __DIR__ . '/../partials/_notas_panel.php';
    ?>
  </div>

  <!-- Evidencias Tab -->
  <div class="tab-pane fade" id="tab-evidencias">
    <?php
    $evidTipo             = 'tarea';
    $evidId               = (int)$t['id'];
    $evidencias           = \App\Models\Evidencia::getByEntidad('tarea', (int)$t['id']);
    $evidCanUpload        = true;
    $evidCanDelete        = in_array($role, ['GOD', 'ADMIN']);
    $evidEntityTerminada  = ($t['estado'] ?? '') === 'terminada';
    include __DIR__ . '/../partials/_evidencias_panel.php';
    ?>
  </div>
</div>
