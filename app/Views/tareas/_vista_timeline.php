<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$p      = $p ?? ($proyecto ?? []);
$today    = new \DateTime(); $today->setTime(0,0,0);
$terminados = ['terminada','aceptada'];

// Group items into buckets
$buckets = ['vencidos'=>[],'hoy'=>[],'esta_semana'=>[],'proxima_semana'=>[],'mas_adelante'=>[],'sin_fecha'=>[]];
foreach ($tarTimeline as $t) {
    if (!($t['fecha_fin'] ?? '')) { $buckets['sin_fecha'][] = $t; continue; }
    $fin = new \DateTime($t['fecha_fin']); $fin->setTime(0,0,0);
    $diff = (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1);
    if (in_array($t['estado'], $terminados)) { $buckets['mas_adelante'][] = $t; continue; }
    if ($diff < 0)       $buckets['vencidos'][] = $t;
    elseif ($diff === 0) $buckets['hoy'][] = $t;
    elseif ($diff <= 7)  $buckets['esta_semana'][] = $t;
    elseif ($diff <= 14) $buckets['proxima_semana'][] = $t;
    else                 $buckets['mas_adelante'][] = $t;
}

$bucketLabels = [
    'vencidos'        => ['label' => 'Vencidos',        'color' => 'text-danger'],
    'hoy'             => ['label' => 'Hoy',              'color' => 'text-warning'],
    'esta_semana'     => ['label' => 'Esta semana',      'color' => 'text-primary'],
    'proxima_semana'  => ['label' => 'Pr&oacute;xima semana',   'color' => 'text-info'],
    'mas_adelante'    => ['label' => 'M&aacute;s adelante',     'color' => 'text-secondary'],
    'sin_fecha'       => ['label' => 'Sin fecha l&iacute;mite', 'color' => 'text-muted'],
];

$totalTimeline = array_sum(array_map('count', $buckets));
?>
<?php if ($totalTimeline === 0): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-clock-history fs-1 d-block mb-2"></i>No hay tareas en el timeline.
  </div>
<?php else: ?>
<div class="row">
  <div class="col-lg-8">
    <div class="timeline">
      <?php foreach ($buckets as $bKey => $bItems):
        if (empty($bItems)) continue;
        $bc = $bucketLabels[$bKey];
      ?>
        <div class="timeline-group-label <?php echo $bc['color']; ?>"><?php echo $bc['label']; ?> (<?php echo count($bItems); ?>)</div>
        <?php foreach ($bItems as $t):
          $fin   = ($t['fecha_fin'] ?? '') ? new \DateTime($t['fecha_fin']) : null;
          if ($fin) $fin->setTime(0,0,0);
          $diff  = $fin ? (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1) : null;
          $semaforoNivel = $t['semaforo'] ?? 'neutral';
          $dotCls = \App\Services\SemaforoService::dotClass($semaforoNivel);
          $subtareas = $t['subtareas'] ?? [];
          $subTotal  = count($subtareas);
          $subDone   = count(array_filter($subtareas, fn($s) => in_array($s['estado'], ['terminada','aceptada'])));
        ?>
          <div class="timeline-item">
            <div class="timeline-dot <?php echo $dotCls; ?>"></div>
            <div class="timeline-card"
                 data-tarea-id="<?php echo $t['id']; ?>"
                 data-fecha-fin="<?php echo htmlspecialchars($t['fecha_fin'] ?? ''); ?>"
                 data-estado="<?php echo htmlspecialchars($t['estado']); ?>">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($t['nombre']); ?></div>
                  <div class="d-flex gap-1 mt-1 flex-wrap">
                    <span class="badge estado-badge badge-estado-<?php echo $t['estado']; ?> text-2xs"><?php echo ucfirst(str_replace('_',' ',$t['estado'])); ?></span>
                    <span class="badge badge-prioridad-<?php echo $t['prioridad']??'media'; ?> text-2xs"><?php echo ucfirst($t['prioridad']??'media'); ?></span>
                    <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
                    <?php if ($t['usuario_asignado_nombre']??''): ?>
                      <span class="badge bg-light text-dark text-2xs">
                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($t['usuario_asignado_nombre']); ?>
                      </span>
                    <?php endif; ?>
                    <?php if ($subTotal > 0): ?>
                      <span class="badge bg-light text-dark text-2xs">
                        <i class="bi bi-check2-square me-1"></i><?php echo $subDone; ?>/<?php echo $subTotal; ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-end flex-shrink-0">
                  <?php if ($fin): ?>
                    <div class="timeline-card-date"><?php echo $fin->format('d/m/Y'); ?></div>
                    <?php if ($diff !== null && !in_array($t['estado'], $terminados)): ?>
                      <div class="<?php echo $diff < 0 ? 'text-danger' : ($diff <= 3 ? 'text-warning' : 'text-muted'); ?> text-sm-px2">
                        <?php echo $diff < 0 ? 'Venci&oacute; hace '.abs($diff).'d' : ($diff === 0 ? 'Hoy' : 'En '.$diff.'d'); ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="timeline-card-date">Sin fecha</div>
                  <?php endif; ?>
                  <?php if (in_array($role, ['ADMIN','GOD'])): ?>
                  <a href="<?php echo $appUrl; ?>/tareas/<?php echo $t['id']; ?>/editar" class="btn btn-xs btn-outline-primary mt-1 py-0 px-1 text-2xs">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </div>
              <div class="kanban-card-actions mt-2 d-flex flex-wrap gap-1 align-items-center">
                <!-- Estado buttons (only if user can change this task's status) -->
                <?php
                $canChangeEstado = ($role === 'GOD') ||
                                   (in_array($role, ['ADMIN', 'USER']) &&
                                    (int)($t['usuario_asignado_id'] ?? 0) === (int)($user['id'] ?? 0));
                ?>
                <?php if ($canChangeEstado): ?>
                <div class="estado-btn-group d-flex gap-1" data-tarea-id="<?php echo $t['id']; ?>">
                  <?php foreach (['por_hacer'=>'PH','haciendo'=>'H','terminada'=>'T'] as $estVal=>$estLbl): ?>
                  <form method="POST" action="<?php echo $appUrl; ?>/tareas/<?php echo $t['id']; ?>/estado" class="d-inline" data-change-estado>
                    <?php echo \App\Helpers\CSRF::tokenField(); ?>
                    <input type="hidden" name="estado" value="<?php echo $estVal; ?>">
                    <button type="submit"
                      class="btn btn-xs py-0 px-1 text-2xs <?php echo $t['estado'] === $estVal ? 'btn-primary active-estado' : 'btn-outline-secondary'; ?>"
                      data-estado="<?php echo $estVal; ?>"
                      title="<?php echo ['por_hacer'=>'Por Hacer','haciendo'=>'Haciendo','terminada'=>'Terminada'][$estVal]; ?>"><?php echo $estLbl; ?></button>
                  </form>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <!-- Quick actions -->
                <?php if (in_array($role, ['ADMIN','GOD'])): ?>
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 ms-auto text-2xs"
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
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Mobile: compact summary bar -->
  <div class="col-12 timeline-summary-mobile mb-3">
    <div class="d-flex gap-2 flex-wrap">
      <?php foreach ($buckets as $bKey => $bItems):
        if (empty($bItems)) continue;
        $bc = $bucketLabels[$bKey];
      ?>
        <span class="badge bg-light text-dark border small"><?php echo $bc['label']; ?>: <strong><?php echo count($bItems); ?></strong></span>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Desktop: sidebar summary -->
  <div class="col-lg-4 d-none d-lg-block">
    <div class="card">
      <div class="card-body p-3">
        <h6 class="card-title mb-3"><i class="bi bi-bar-chart-line me-2"></i>Resumen</h6>
        <?php foreach ($buckets as $bKey => $bItems):
          if (empty($bItems)) continue;
          $bc = $bucketLabels[$bKey];
        ?>
          <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <span class="small <?php echo $bc['color']; ?>"><?php echo $bc['label']; ?></span>
            <span class="badge bg-secondary"><?php echo count($bItems); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
