<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item active">Nuevo Proyecto</li>
  </ol>
</nav>

<div class="card" style="max-width:700px">
  <div class="card-header fw-semibold">
    <i class="bi bi-plus-circle me-2 text-primary"></i>Nuevo Proyecto
  </div>
  <div class="card-body">
    <form method="POST" action="<?php echo $appUrl; ?>/proyectos" novalidate>
      <?php echo \App\Helpers\CSRF::tokenField(); ?>

      <div class="row g-3">
        <div class="col-12">
          <label for="nombre" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" id="nombre" name="nombre" class="form-control" required minlength="3" maxlength="120" placeholder="Nombre del proyecto">
        </div>

        <div class="col-12">
          <label for="descripcion" class="form-label fw-semibold">Descripción</label>
          <textarea id="descripcion" name="descripcion" class="form-control" rows="3" maxlength="2000" placeholder="Descripción del proyecto..."></textarea>
        </div>

        <div class="col-12 col-md-6">
          <label for="prioridad" class="form-label fw-semibold">Prioridad <span class="text-danger">*</span></label>
          <select id="prioridad" name="prioridad" class="form-select" required>
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta</option>
            <option value="critica">Crítica</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label for="fecha_inicio" class="form-label fw-semibold">Fecha de inicio</label>
          <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control">
        </div>

        <div class="col-12 col-md-6">
          <label for="fecha_fin" class="form-label fw-semibold">Fecha de fin</label>
          <input type="date" id="fecha_fin" name="fecha_fin" class="form-control">
        </div>

        <div class="col-12">
          <label for="usuario_asignado_id" class="form-label fw-semibold">Asignar a <span class="text-danger">*</span></label>
          <select id="usuario_asignado_id" name="usuario_asignado_id" class="form-select" required>
            <option value="">Seleccionar usuario...</option>
            <?php foreach ($usuarios ?? [] as $u): ?>
            <option value="<?php echo $e($u['id']); ?>"><?php echo $e($u['nombre_completo']); ?> (<?php echo $e($u['rol']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear Proyecto</button>
        <a href="<?php echo $appUrl; ?>/proyectos" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script src="<?php echo \App\Core\View::asset('js/proyectos.js'); ?>"></script>
