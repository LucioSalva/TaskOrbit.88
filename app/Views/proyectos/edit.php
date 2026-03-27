<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$p      = $proyecto ?? [];
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>"><?php echo $e($p['nombre']??''); ?></a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>
</nav>

<div class="card mw-700">
  <div class="card-header fw-semibold">
    <i class="bi bi-pencil me-2 text-warning"></i>Editar Proyecto
  </div>
  <div class="card-body">
    <form method="POST" action="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/editar" novalidate>
      <?php echo \App\Helpers\CSRF::tokenField(); ?>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="120" value="<?php echo $e($p['nombre']??''); ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3" maxlength="2000"><?php echo $e($p['descripcion']??''); ?></textarea>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Prioridad</label>
          <select name="prioridad" class="form-select">
            <?php foreach(['baja'=>'Baja','media'=>'Media','alta'=>'Alta','critica'=>'Crítica'] as $val=>$lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($p['prioridad']??'')===$val?'selected':''; ?>><?php echo $e($lbl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Estado</label>
          <select name="estado" class="form-select">
            <?php foreach(['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'] as $val=>$lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($p['estado']??'')===$val?'selected':''; ?>><?php echo $e($lbl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $e($p['fecha_inicio']??''); ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha fin</label>
          <input type="date" name="fecha_fin" class="form-control" value="<?php echo $e($p['fecha_fin']??''); ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Asignado a</label>
          <select name="usuario_asignado_id" class="form-select">
            <option value="">Sin cambiar</option>
            <?php foreach ($usuarios ?? [] as $u): ?>
            <option value="<?php echo $e($u['id']); ?>" <?php echo ((int)($p['usuario_asignado_id']??0))===(int)$u['id']?'selected':''; ?>>
              <?php echo $e($u['nombre_completo']); ?> (<?php echo $e($u['rol']); ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
        <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
