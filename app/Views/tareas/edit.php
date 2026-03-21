<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$t      = $tarea    ?? [];
$p      = $proyecto ?? [];
$estadoLabel = ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada','enterado'=>'Enterado','ocupado'=>'Ocupado','aceptada'=>'Aceptada'];
$canEdit = in_array($role, ['ADMIN','GOD']);
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>"><?php echo $e($p['nombre']??''); ?></a></li>
    <li class="breadcrumb-item"><a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas">Tareas</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>
</nav>

<div class="card" style="max-width:700px">
  <div class="card-header fw-semibold">
    <i class="bi bi-pencil me-2 text-warning"></i>Editar Tarea
  </div>
  <div class="card-body">
    <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $e($t['id']??''); ?>/editar" novalidate>
      <?php echo \App\Helpers\CSRF::tokenField(); ?>

      <div class="row g-3">

        <?php if ($canEdit): ?>
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="200" value="<?php echo $e($t['nombre']??''); ?>">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3" maxlength="2000"><?php echo $e($t['descripcion']??''); ?></textarea>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Prioridad</label>
          <select name="prioridad" class="form-select">
            <?php foreach(['baja'=>'Baja','media'=>'Media','alta'=>'Alta','critica'=>'Crítica'] as $val=>$lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($t['prioridad']??'')===$val?'selected':''; ?>><?php echo $e($lbl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="col-12 <?php echo $canEdit?'col-md-6':''; ?>">
          <label class="form-label fw-semibold">Estado</label>
          <select name="estado" class="form-select">
            <?php foreach($estadoLabel as $val=>$lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($t['estado']??'')===$val?'selected':''; ?>><?php echo $e($lbl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($canEdit): ?>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $e($t['fecha_inicio']??''); ?>"
            <?php if($p['fecha_inicio']??''): ?>min="<?php echo $e($p['fecha_inicio']); ?>"<?php endif; ?>
            <?php if($p['fecha_fin']??''): ?>max="<?php echo $e($p['fecha_fin']); ?>"<?php endif; ?>>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold">Fecha límite</label>
          <input type="date" name="fecha_fin" class="form-control" value="<?php echo $e($t['fecha_fin']??''); ?>"
            <?php if($p['fecha_inicio']??''): ?>min="<?php echo $e($p['fecha_inicio']); ?>"<?php endif; ?>
            <?php if($p['fecha_fin']??''): ?>max="<?php echo $e($p['fecha_fin']); ?>"<?php endif; ?>>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Asignar a</label>
          <select name="usuario_asignado_id" class="form-select">
            <option value="">Sin asignar (heredará del proyecto)</option>
            <?php foreach ($usuarios ?? [] as $u): ?>
            <option value="<?php echo $e($u['id']); ?>" <?php echo ((int)($t['usuario_asignado_id']??0)===(int)$u['id'])?'selected':''; ?>>
              <?php echo $e($u['nombre_completo']); ?> (<?php echo $e($u['rol']); ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <!-- USER: solo ve el estado, los demás campos son informativos -->
        <div class="col-12">
          <label class="form-label fw-semibold text-muted">Nombre</label>
          <p class="form-control-plaintext"><?php echo $e($t['nombre']??''); ?></p>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold text-muted">Responsable</label>
          <p class="form-control-plaintext"><?php echo $e($t['usuario_asignado_nombre']??'-'); ?></p>
        </div>
        <?php endif; ?>

      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
        <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
