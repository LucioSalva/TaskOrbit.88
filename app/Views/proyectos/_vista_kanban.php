<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');

$estadosConfig = [
  'por_hacer' => ['label' => 'Por Hacer', 'icon' => 'bi-circle'],
  'haciendo'  => ['label' => 'Haciendo',  'icon' => 'bi-play-circle'],
  'enterado'  => ['label' => 'Enterado',  'icon' => 'bi-eye'],
  'ocupado'   => ['label' => 'Ocupado',   'icon' => 'bi-hourglass-split'],
  'terminada' => ['label' => 'Terminada', 'icon' => 'bi-check-circle'],
  'aceptada'  => ['label' => 'Aceptada',  'icon' => 'bi-check-circle-fill'],
];

// Only show columns that have items OR the 3 main states
$colsToShow = ['por_hacer', 'haciendo', 'terminada'];
foreach ($proyByEstado as $est => $items) {
    if (!empty($items) && !in_array($est, $colsToShow)) $colsToShow[] = $est;
}
?>
<div class="kanban-scroll-hint">
  <span>Desliza para ver mas columnas</span>
  <i class="bi bi-arrow-right"></i>
</div>
<div class="kanban-board">
<?php foreach ($colsToShow as $est):
    $items = $proyByEstado[$est] ?? [];
    $cfg   = $estadosConfig[$est] ?? ['label' => $est, 'icon' => 'bi-circle'];
?>
  <div class="kanban-column">
    <div class="kanban-col-header estado-<?php echo $est; ?>">
      <span><i class="bi <?php echo $cfg['icon']; ?> me-1"></i><?php echo $cfg['label']; ?></span>
      <span class="badge bg-white bg-opacity-50 text-dark ms-1"><?php echo count($items); ?></span>
    </div>
    <div class="kanban-col-body">
      <?php if (empty($items)): ?>
        <div class="kanban-empty"><i class="bi bi-inbox d-block mb-1 icon-md"></i>Sin proyectos</div>
      <?php else: foreach ($items as $p):
        $semaforoNivel = $p['semaforo'] ?? 'neutral';
        $fin   = ($p['fecha_fin'] ?? '') ? new \DateTime($p['fecha_fin']) : null;
        if ($fin) $fin->setTime(0,0,0);
        $today = new \DateTime(); $today->setTime(0,0,0);
        $diff  = $fin ? (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1) : null;
      ?>
        <div class="kanban-card <?php echo \App\Services\SemaforoService::riskClass($semaforoNivel); ?>"
             data-proyecto-id="<?php echo $p['id']; ?>"
             data-fecha-fin="<?php echo htmlspecialchars($p['fecha_fin'] ?? ''); ?>"
             data-estado="<?php echo htmlspecialchars($p['estado']); ?>">

          <div class="kanban-card-title"><?php echo htmlspecialchars($p['nombre']); ?></div>

          <div class="kanban-card-meta">
            <?php if ($p['usuario_asignado_nombre'] ?? ''): ?>
              <span class="user-avatar sm" title="<?php echo htmlspecialchars($p['usuario_asignado_nombre']); ?>">
                <?php echo mb_strtoupper(mb_substr($p['usuario_asignado_nombre'], 0, 1)); ?>
              </span>
              <small class="text-muted text-truncate mw-90"><?php echo htmlspecialchars($p['usuario_asignado_nombre']); ?></small>
            <?php endif; ?>
            <span class="badge badge-prioridad-<?php echo $p['prioridad'] ?? 'media'; ?> ms-auto"><?php echo ucfirst($p['prioridad'] ?? 'media'); ?></span>
            <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
          </div>

          <?php if ($fin): ?>
          <div class="kanban-card-meta mt-1">
            <small class="<?php echo $semaforoNivel === 'rojo' ? 'text-danger fw-semibold' : ($semaforoNivel === 'amarillo' ? 'text-warning fw-semibold' : 'text-muted'); ?>">
              <i class="bi bi-calendar2 me-1"></i>
              <?php if ($diff !== null && $diff < 0): ?>Venci&oacute; hace <?php echo abs($diff); ?>d
              <?php elseif ($diff !== null && $diff <= 3): ?>Vence en <?php echo $diff; ?>d
              <?php else: ?><?php echo $fin->format('d/m/Y'); ?>
              <?php endif; ?>
            </small>
          </div>
          <?php endif; ?>

          <?php if (in_array($role, ['ADMIN','GOD'])): ?>
          <div class="kanban-card-actions">
            <div class="estado-btn-group d-flex gap-1">
              <?php foreach (['por_hacer'=>['PH','Por Hacer'],'haciendo'=>['H','Haciendo'],'terminada'=>['T','Terminada']] as $estVal=>[$estLbl,$estTitle]): ?>
              <form method="POST" action="<?php echo $appUrl; ?>/proyectos/<?php echo $p['id']; ?>/estado"
                    class="d-inline" data-change-estado>
                <?php echo \App\Helpers\CSRF::tokenField(); ?>
                <input type="hidden" name="estado" value="<?php echo $estVal; ?>">
                <button type="submit"
                  class="btn btn-xs py-0 px-1 fs-2xs <?php echo $p['estado'] === $estVal ? 'btn-primary active-estado' : 'btn-outline-secondary'; ?>"
                  data-estado="<?php echo $estVal; ?>"
                  title="<?php echo $estTitle; ?>">
                  <?php echo $estLbl; ?>
                </button>
              </form>
              <?php endforeach; ?>
            </div>
            <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $p['id']; ?>/tareas" class="btn btn-xs btn-outline-primary py-0 px-1 ms-auto fs-2xs" title="Ver tareas">
              <i class="bi bi-list-task"></i>
            </a>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
