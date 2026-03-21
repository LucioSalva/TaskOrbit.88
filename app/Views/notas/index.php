<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$role   = $user['rol'] ?? '';
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Agrupar notas por scope
$notasAgrupadas = ['personal'=>[],'proyecto'=>[],'tarea'=>[],'subtarea'=>[]];
foreach ($notas ?? [] as $nota) {
    $s = $nota['scope'] ?? 'personal';
    if (isset($notasAgrupadas[$s])) {
        $notasAgrupadas[$s][] = $nota;
    }
}
$scopeLabels = ['personal'=>'Personales','proyecto'=>'De Proyecto','tarea'=>'De Tarea','subtarea'=>'De Subtarea'];
$scopeIcons  = ['personal'=>'bi-person','proyecto'=>'bi-kanban','tarea'=>'bi-list-task','subtarea'=>'bi-check2-square'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-sticky me-2 text-primary"></i>Notas</h1>
    <p class="text-muted small mb-0">Notas personales y de proyectos/tareas</p>
  </div>
</div>

<!-- Formulario nueva nota -->
<div class="card mb-4">
  <div class="card-header fw-semibold" data-bs-toggle="collapse" data-bs-target="#form-nota-collapse" style="cursor:pointer">
    <i class="bi bi-plus-circle me-2 text-primary"></i>Nueva nota
    <i class="bi bi-chevron-down float-end"></i>
  </div>
  <div class="collapse show" id="form-nota-collapse">
    <div class="card-body">
      <form method="POST" action="<?php echo $appUrl; ?>/notas" novalidate>
        <?php echo \App\Helpers\CSRF::tokenField(); ?>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold small">Tipo</label>
            <select name="scope" id="nota-scope" class="form-select form-select-sm">
              <option value="personal">Personal</option>
              <option value="proyecto">De Proyecto</option>
              <option value="tarea">De Tarea</option>
            </select>
          </div>
          <div class="col-12 col-md-4" id="nota-ref-container" style="display:none">
            <label class="form-label fw-semibold small">Referencia</label>
            <select name="referencia_id" id="nota-referencia" class="form-select form-select-sm">
              <option value="">Selecciona...</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold small">Título (opcional)</label>
            <input type="text" name="titulo" class="form-control form-control-sm" maxlength="160" placeholder="Título de la nota">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">Contenido <span class="text-danger">*</span></label>
            <textarea name="contenido" class="form-control form-control-sm" rows="3" required placeholder="Escribe tu nota..."></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-sticky me-1"></i>Guardar nota</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Tabs por tipo -->
<ul class="nav nav-tabs mb-3" id="notasTabs" role="tablist">
  <?php $first=true; foreach($scopeLabels as $scope=>$label): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $first?'active':''; ?>"
      data-bs-toggle="tab"
      data-bs-target="#tab-notas-<?php echo $scope; ?>"
      type="button">
      <i class="bi <?php echo $scopeIcons[$scope]; ?> me-1"></i>
      <?php echo $label; ?>
      <?php if(!empty($notasAgrupadas[$scope])): ?>
      <span class="badge bg-secondary ms-1"><?php echo count($notasAgrupadas[$scope]); ?></span>
      <?php endif; ?>
    </button>
  </li>
  <?php $first=false; endforeach; ?>
</ul>

<div class="tab-content">
<?php $first=true; foreach($notasAgrupadas as $scope=>$items): ?>
<div class="tab-pane fade <?php echo $first?'show active':''; ?>" id="tab-notas-<?php echo $scope; ?>">

  <?php if(empty($items)): ?>
  <div class="text-center py-4 text-muted">
    <i class="bi <?php echo $scopeIcons[$scope]; ?> display-5 mb-2 d-block"></i>
    <p class="small">Sin notas <?php echo strtolower($scopeLabels[$scope]); ?>.</p>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach($items as $nota): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body py-3">
          <?php if($nota['titulo']??''): ?>
          <h6 class="fw-semibold mb-2"><?php echo $e($nota['titulo']); ?></h6>
          <?php endif; ?>
          <p class="small mb-2" style="white-space:pre-line"><?php echo nl2br($e($nota['contenido'])); ?></p>
          <div class="d-flex align-items-center justify-content-between mt-auto">
            <small class="text-muted">
              <i class="bi bi-person me-1"></i><?php echo $e($nota['autor_nombre']??''); ?>
              <span class="mx-1">·</span>
              <?php echo \App\Helpers\DateHelper::formatDatetime($nota['created_at']??''); ?>
            </small>
            <?php if ((int)($nota['user_id']??0) === (int)($user['id']??0)): ?>
            <form method="POST" action="<?php echo $appUrl; ?>/notas/<?php echo $e($nota['id']); ?>/eliminar" class="d-inline">
              <?php echo \App\Helpers\CSRF::tokenField(); ?>
              <button type="submit" class="btn btn-link btn-sm text-danger p-0 js-confirm-delete" title="Eliminar nota" data-confirm="¿Eliminar esta nota?">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php $first=false; endforeach; ?>
</div>

<!-- Notas data bridge — no unsafe-inline needed -->
<div id="notas-data"
  data-proyectos="<?php echo htmlspecialchars(json_encode(array_values(array_map(fn($p) => ['id' => $p['id'], 'nombre' => $p['nombre']], $proyectos ?? [])), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
  data-tareas="<?php echo htmlspecialchars(json_encode(array_values(array_map(fn($t) => ['id' => $t['id'], 'nombre' => $t['nombre'], 'proyecto_id' => $t['proyecto_id']], $tareas ?? [])), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
  style="display:none"></div>
<script src="<?php echo \App\Core\View::asset('js/notas.js'); ?>"></script>
