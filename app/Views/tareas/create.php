<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$p      = $proyecto ?? [];
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>"><?php echo $e($p['nombre']??''); ?></a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas">Tareas</a></li>
    <li class="breadcrumb-item active">Nueva Tarea</li>
  </ol>
</nav>

<div class="card" style="max-width:700px">
  <div class="card-header fw-semibold">
    <i class="bi bi-plus-circle me-2 text-primary"></i>Nueva Tarea en <strong><?php echo $e($p['nombre']??''); ?></strong>
  </div>
  <div class="card-body">
    <form method="POST" action="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas" novalidate>
      <?php echo \App\Helpers\CSRF::tokenField(); ?>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="200" placeholder="Nombre de la tarea">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3" maxlength="2000" placeholder="Descripción de la tarea..."></textarea>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Prioridad <span class="text-danger">*</span></label>
          <select name="prioridad" class="form-select" required>
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta</option>
            <option value="critica">Crítica</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha inicio</label>
          <input type="date" name="fecha_inicio" id="t_fecha_inicio" class="form-control"
            <?php if($p['fecha_inicio']??''): ?>min="<?php echo $e($p['fecha_inicio']); ?>"<?php endif; ?>
            <?php if($p['fecha_fin']??''): ?>max="<?php echo $e($p['fecha_fin']); ?>"<?php endif; ?>>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha límite</label>
          <input type="date" name="fecha_fin" id="t_fecha_fin" class="form-control"
            <?php if($p['fecha_inicio']??''): ?>min="<?php echo $e($p['fecha_inicio']); ?>"<?php endif; ?>
            <?php if($p['fecha_fin']??''): ?>max="<?php echo $e($p['fecha_fin']); ?>"<?php endif; ?>>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Asignar a</label>
          <select name="usuario_asignado_id" class="form-select">
            <option value="">Sin asignar (heredará del proyecto)</option>
            <?php foreach ($usuarios ?? [] as $u): ?>
            <option value="<?php echo $e($u['id']); ?>"><?php echo $e($u['nombre_completo']); ?> (<?php echo $e($u['rol']); ?>)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Si no seleccionas, la tarea se asigna al usuario del proyecto.</div>
        </div>
      </div>

      <?php if($p['fecha_inicio']??'' || $p['fecha_fin']??''): ?>
      <div class="alert alert-info py-2 small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Proyecto: <?php echo \App\Helpers\DateHelper::formatDate($p['fecha_inicio']); ?> — <?php echo \App\Helpers\DateHelper::formatDate($p['fecha_fin']); ?>
      </div>
      <?php endif; ?>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear Tarea</button>
        <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script src="<?php echo \App\Core\View::asset('js/tareas.js'); ?>"></script>
