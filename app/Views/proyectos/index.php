<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estadoLabel = ['por_hacer' => 'Por Hacer', 'haciendo' => 'Haciendo', 'terminada' => 'Terminada', 'enterado' => 'Enterado', 'ocupado' => 'Ocupado', 'aceptada' => 'Aceptada'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i>Proyectos</h1>
    <p class="text-muted small mb-0">Gestión de proyectos asignados</p>
  </div>
  <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
    <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Nuevo Proyecto
    </a>
  <?php endif; ?>
</div>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">Estado</label>
        <select name="estado" class="form-select form-select-sm js-autosubmit">
          <option value="">Todos</option>
          <?php foreach ($estadoLabel as $val => $lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($_GET['estado'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $e($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">Prioridad</label>
        <select name="prioridad" class="form-select form-select-sm js-autosubmit">
          <option value="">Todas</option>
          <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $val => $lbl): ?>
            <option value="<?php echo $e($val); ?>" <?php echo ($_GET['prioridad'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $e($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold mb-1">Buscar</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre del proyecto..." value="<?php echo $e($_GET['q'] ?? ''); ?>">
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Buscar</button>
        <a href="<?php echo $appUrl; ?>/proyectos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
      </div>
    </form>
  </div>
</div>

<?php
// Filters are applied server-side in ProyectosController; use the array directly
$proyectosFiltrados = $proyectos ?? [];
?>

<?php if (empty($proyectosFiltrados)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-inbox display-4 mb-3 d-block"></i>
    <h5>Sin proyectos</h5>
    <p class="small">No hay proyectos que coincidan con los filtros.</p>
    <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
      <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Crear primer proyecto
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($proyectosFiltrados as $proyecto): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card card-proyecto h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <h5 class="card-title mb-0 fw-semibold text-truncate me-2">
                <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>" class="text-decoration-none stretched-link-inner">
                  <?php echo $e($proyecto['nombre']); ?>
                </a>
              </h5>
              <span class="badge badge-estado-<?php echo $e($proyecto['estado']); ?> flex-shrink-0">
                <?php echo $e($estadoLabel[$proyecto['estado']] ?? $proyecto['estado']); ?>
              </span>
            </div>

            <?php if ($proyecto['descripcion']): ?>
              <p class="card-text text-muted small text-truncate-2 mb-3"><?php echo $e($proyecto['descripcion']); ?></p>
            <?php endif; ?>

            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="badge badge-prioridad-<?php echo $e($proyecto['prioridad']); ?>">
                <?php echo ucfirst($e($proyecto['prioridad'])); ?>
              </span>
              <?php if ($proyecto['usuario_asignado_nombre']): ?>
                <span class="text-muted small">
                  <i class="bi bi-person me-1"></i><?php echo $e($proyecto['usuario_asignado_nombre']); ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($proyecto['fecha_inicio'] || $proyecto['fecha_fin']): ?>
              <div class="small text-muted mb-3">
                <i class="bi bi-calendar3 me-1"></i>
                <?php echo \App\Helpers\DateHelper::formatDate($proyecto['fecha_inicio']); ?>
                <?php if ($proyecto['fecha_fin']): ?> — <?php echo \App\Helpers\DateHelper::formatDate($proyecto['fecha_fin']); ?><?php endif; ?>
                  <?php if ($proyecto['fecha_fin'] && $proyecto['estado'] !== 'terminada'): ?>
                    <?php $dias = \App\Helpers\DateHelper::daysRemaining($proyecto['fecha_fin']); ?>
                    <?php if ($dias < 0): ?>
                      <span class="badge bg-danger ms-1" style="font-size:.65rem">Vencido <?php echo abs($dias); ?>d</span>
                    <?php elseif ($dias <= 3): ?>
                      <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Vence en <?php echo $dias; ?>d</span>
                    <?php endif; ?>
                  <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-auto">
              <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>" class="btn btn-outline-primary btn-sm flex-fill">
                <i class="bi bi-eye me-1"></i>Ver
              </a>
              <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/tareas" class="btn btn-outline-secondary btn-sm flex-fill">
                <i class="bi bi-list-task me-1"></i>Tareas
              </a>
              <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
                <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/editar" class="btn btn-outline-warning btn-sm">
                  <i class="bi bi-pencil"></i>
                </a>
                <button type="button" class="btn btn-outline-danger btn-sm"
                  data-delete-url="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/eliminar"
                  data-delete-title="¿Eliminar proyecto?"
                  data-delete-msg="Se eliminarán todas las tareas, subtareas y notas del proyecto."
                  data-show-reason="true">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<script src="<?php echo \App\Core\View::asset('js/proyectos.js'); ?>"></script>