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
      <span id="proyecto-estado-badge" class="badge estado-badge badge-estado-<?php echo $e($p['estado']??''); ?>"><?php echo $e($estadoLabel[$p['estado']??'']??$p['estado']??''); ?></span>
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
  <i class="bi bi-list-task display-4 mb-3 d-block opacity-50"></i>
  <h5>Este proyecto aun no tiene tareas</h5>
  <p class="small">Las tareas permiten dividir el trabajo del proyecto en pasos concretos y asignarlos a personas.</p>
  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($p['id']??''); ?>/tareas/crear" class="btn btn-primary btn-sm mt-2">
    <i class="bi bi-plus-lg me-1"></i>Crear la primera tarea
  </a>
  <?php else: ?>
  <p class="small text-muted mt-2">Contacta a un administrador para agregar tareas a este proyecto.</p>
  <?php endif; ?>
</div>
<?php else: ?>

<!-- View Switcher -->
<div id="vistas-container" data-page="tareas">
  <ul class="nav nav-pills view-switcher mb-3">
    <li class="nav-item"><a class="nav-link" href="#lista"    data-view="lista">   <i class="bi bi-list-ul me-1"></i><span>Lista</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#kanban"   data-view="kanban">  <i class="bi bi-kanban me-1"></i><span>Kanban</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#usuario"  data-view="usuario"> <i class="bi bi-people me-1"></i><span>Por Usuario</span></a></li>
    <?php if ($role !== 'USER'): ?>
    <li class="nav-item"><a class="nav-link" href="#timeline" data-view="timeline"><i class="bi bi-clock-history me-1"></i><span>Timeline</span></a></li>
    <?php endif; ?>
  </ul>
  <div class="vista-panel" data-vista="lista">
    <?php include __DIR__ . '/_vista_lista.php'; ?>
  </div>
  <div class="vista-panel d-none" data-vista="kanban">
    <?php include __DIR__ . '/_vista_kanban.php'; ?>
  </div>
  <div class="vista-panel d-none" data-vista="usuario">
    <?php include __DIR__ . '/_vista_usuario.php'; ?>
  </div>
  <div class="vista-panel d-none" data-vista="timeline">
    <?php include __DIR__ . '/_vista_timeline.php'; ?>
  </div>
</div>

<?php endif; ?>

<?php if (!empty($usuarios)): ?>
<script nonce="<?= CSP_NONCE ?>">
var USUARIOS_ASIGNABLES = <?php echo json_encode(array_map(function($u) {
    return ['id' => $u['id'], 'nombre_completo' => $u['nombre_completo'], 'rol' => $u['rol'] ?? ''];
}, $usuarios)); ?>;
</script>
<?php else: ?>
<script nonce="<?= CSP_NONCE ?>">var USUARIOS_ASIGNABLES = [];</script>
<?php endif; ?>

<!-- Notas / Bitácora del Proyecto -->
<div class="mt-4">
  <button class="btn btn-outline-secondary btn-sm w-100 text-start d-flex align-items-center gap-2 mb-2"
          type="button" data-bs-toggle="collapse" data-bs-target="#panel-notas-proyecto"
          aria-expanded="false">
    <i class="bi bi-journal-text text-primary"></i>
    <span>Bitácora del Proyecto</span>
    <i class="bi bi-chevron-down ms-auto"></i>
  </button>
  <div class="collapse" id="panel-notas-proyecto">
    <div class="card">
      <div class="card-body py-3">
        <?php
        $notasScope      = 'proyecto';
        $notasRefId      = (int)($p['id'] ?? 0);
        $notas           = [];
        $notasLazy       = true;
        $notasCanWrite   = true;
        $notasRole       = $role;
        $notasUserId     = (int)($user['id'] ?? 0);
        $notasPanelTitle = 'Bitácora del Proyecto';
        include __DIR__ . '/../partials/_notas_panel.php';
        ?>
      </div>
    </div>
  </div>
</div>
<script nonce="<?= CSP_NONCE ?>">
(function() {
  var collapseEl = document.getElementById('panel-notas-proyecto');
  if (!collapseEl) return;
  collapseEl.addEventListener('show.bs.collapse', function() {
    var panelEl = collapseEl.querySelector('.notas-panel');
    if (panelEl && typeof loadLazyNotasPanel === 'function') {
      loadLazyNotasPanel(panelEl);
    }
  }, { once: true });
})();
</script>
