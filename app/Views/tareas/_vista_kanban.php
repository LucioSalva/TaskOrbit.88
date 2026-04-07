<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$p      = $p ?? ($proyecto ?? []);

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
foreach ($tarByEstado as $est => $items) {
    if (!empty($items) && !in_array($est, $colsToShow)) $colsToShow[] = $est;
}
?>
<div class="kanban-scroll-hint">
  <span>Desliza para ver mas columnas</span>
  <i class="bi bi-arrow-right"></i>
</div>
<div class="kanban-board">
<?php foreach ($colsToShow as $est):
    $items = $tarByEstado[$est] ?? [];
    $cfg   = $estadosConfig[$est] ?? ['label' => $est, 'icon' => 'bi-circle'];
?>
  <div class="kanban-column">
    <div class="kanban-col-header estado-<?php echo $est; ?>">
      <span><i class="bi <?php echo $cfg['icon']; ?> me-1"></i><?php echo $cfg['label']; ?></span>
      <span class="badge bg-white bg-opacity-50 text-dark ms-1"><?php echo count($items); ?></span>
    </div>
    <div class="kanban-col-body">
      <?php if (empty($items)): ?>
        <div class="kanban-empty"><i class="bi bi-inbox d-block mb-1 icon-md"></i>Sin tareas</div>
      <?php else: foreach ($items as $t):
        $semaforoNivel = $t['semaforo'] ?? 'neutral';
        $fin   = ($t['fecha_fin'] ?? '') ? new \DateTime($t['fecha_fin']) : null;
        if ($fin) $fin->setTime(0,0,0);
        $today = new \DateTime(); $today->setTime(0,0,0);
        $diff  = $fin ? (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1) : null;
        $subtareas = $t['subtareas'] ?? [];
        $subTotal  = count($subtareas);
        $subDone   = count(array_filter($subtareas, fn($s) => in_array($s['estado'], ['terminada','aceptada'])));
        $subPct    = $subTotal > 0 ? round($subDone / $subTotal * 100) : 0;
      ?>
        <div class="kanban-card <?php echo \App\Services\SemaforoService::riskClass($semaforoNivel); ?>"
             data-tarea-id="<?php echo $t['id']; ?>"
             data-fecha-fin="<?php echo htmlspecialchars($t['fecha_fin'] ?? ''); ?>"
             data-estado="<?php echo htmlspecialchars($t['estado']); ?>">

          <div class="kanban-card-title"><?php echo htmlspecialchars($t['nombre']); ?></div>

          <div class="kanban-card-meta">
            <?php if ($t['usuario_asignado_nombre'] ?? ''): ?>
              <span class="user-avatar sm" title="<?php echo htmlspecialchars($t['usuario_asignado_nombre']); ?>">
                <?php echo mb_strtoupper(mb_substr($t['usuario_asignado_nombre'], 0, 1)); ?>
              </span>
              <small class="text-muted text-truncate mw-90"><?php echo htmlspecialchars($t['usuario_asignado_nombre']); ?></small>
            <?php endif; ?>
            <span class="badge badge-prioridad-<?php echo $t['prioridad'] ?? 'media'; ?> ms-auto"><?php echo ucfirst($t['prioridad'] ?? 'media'); ?></span>
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

          <?php if ($subTotal > 0): ?>
          <div class="kanban-card-meta mt-1">
            <small class="text-muted"><i class="bi bi-check2-square me-1"></i><?php echo $subDone; ?>/<?php echo $subTotal; ?> subtareas</small>
          </div>
          <div class="subtask-progress">
            <div class="subtask-progress-fill" data-progress-pct="<?php echo $subPct; ?>"></div>
          </div>
          <?php endif; ?>

          <div class="kanban-card-actions">
            <?php
            $canChangeEstado = ($role === 'GOD') ||
                               (in_array($role, ['ADMIN', 'USER']) &&
                                (int)($t['usuario_asignado_id'] ?? 0) === (int)($user['id'] ?? 0));
            ?>
            <?php if ($canChangeEstado): ?>
            <div class="estado-btn-group d-flex gap-1">
              <?php foreach(['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $estVal=>$estLbl): ?>
              <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $t['id']; ?>/estado" class="d-inline" data-change-estado>
                <?php echo \App\Helpers\CSRF::tokenField(); ?>
                <input type="hidden" name="estado" value="<?php echo $estVal; ?>">
                <button type="submit"
                  class="btn btn-xs py-0 px-1 text-2xs <?php echo $t['estado'] === $estVal ? 'btn-primary active-estado' : 'btn-outline-secondary'; ?>"
                  data-estado="<?php echo $estVal; ?>"
                  title="<?php echo $estadosConfig[$estVal]['label']; ?>">
                  <?php echo $estLbl; ?>
                </button>
              </form>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (in_array($role, ['ADMIN','GOD'])): ?>
            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 ms-1 text-2xs"
              title="Editar"
              data-action="quick-edit"
              data-entity-type="tarea"
              data-entity-id="<?php echo (int)$t['id']; ?>"
              data-entity-data="<?php echo htmlspecialchars(json_encode(['nombre'=>$t['nombre'],'descripcion'=>$t['descripcion']??'','fechaFin'=>$t['fecha_fin']??'','prioridad'=>$t['prioridad']??'media']), ENT_QUOTES); ?>">
              <i class="bi bi-pencil-square"></i></button>
            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 text-2xs"
              title="Reasignar"
              data-action="quick-assign"
              data-entity-type="tarea"
              data-entity-id="<?php echo (int)$t['id']; ?>"
              data-assignee-id="<?php echo (int)($t['usuario_asignado_id']??0); ?>"
              data-entity-name="<?php echo htmlspecialchars($t['nombre'], ENT_QUOTES); ?>">
              <i class="bi bi-person-check"></i></button>
            <a href="<?php echo $appUrl; ?>/tareas/<?php echo $t['id']; ?>/editar" class="btn btn-xs btn-outline-warning py-0 px-1 ms-auto text-2xs" title="Editar">
              <i class="bi bi-pencil"></i>
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-xs btn-outline-info py-0 px-1 text-2xs"
              title="Nota"
              data-action="quick-nota"
              data-entity-type="tarea"
              data-entity-id="<?php echo (int)$t['id']; ?>"
              data-entity-name="<?php echo htmlspecialchars($t['nombre'], ENT_QUOTES); ?>">
              <i class="bi bi-sticky"></i></button>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
