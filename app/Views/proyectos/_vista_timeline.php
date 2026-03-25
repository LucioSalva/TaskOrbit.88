<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$today    = new \DateTime(); $today->setTime(0,0,0);
$terminados = ['terminada','aceptada'];

// Group items into buckets
$buckets = ['vencidos'=>[],'hoy'=>[],'esta_semana'=>[],'proxima_semana'=>[],'mas_adelante'=>[],'sin_fecha'=>[]];
foreach ($proyTimeline as $p) {
    if (!($p['fecha_fin'] ?? '')) { $buckets['sin_fecha'][] = $p; continue; }
    $fin = new \DateTime($p['fecha_fin']); $fin->setTime(0,0,0);
    $diff = (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1);
    if (in_array($p['estado'], $terminados)) { $buckets['mas_adelante'][] = $p; continue; }
    if ($diff < 0)       $buckets['vencidos'][] = $p;
    elseif ($diff === 0) $buckets['hoy'][] = $p;
    elseif ($diff <= 7)  $buckets['esta_semana'][] = $p;
    elseif ($diff <= 14) $buckets['proxima_semana'][] = $p;
    else                 $buckets['mas_adelante'][] = $p;
}

$bucketLabels = [
    'vencidos'        => ['label' => 'Vencidos',        'color' => 'text-danger'],
    'hoy'             => ['label' => 'Hoy',              'color' => 'text-warning'],
    'esta_semana'     => ['label' => 'Esta semana',      'color' => 'text-primary'],
    'proxima_semana'  => ['label' => 'Pr&oacute;xima semana',   'color' => 'text-info'],
    'mas_adelante'    => ['label' => 'M&aacute;s adelante',     'color' => 'text-secondary'],
    'sin_fecha'       => ['label' => 'Sin fecha l&iacute;mite', 'color' => 'text-muted'],
];

$total = array_sum(array_map('count', $buckets));
?>
<?php if ($total === 0): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-clock-history fs-1 d-block mb-2"></i>No hay proyectos en el timeline.
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
        <?php foreach ($bItems as $p):
          $fin   = ($p['fecha_fin'] ?? '') ? new \DateTime($p['fecha_fin']) : null;
          if ($fin) $fin->setTime(0,0,0);
          $diff  = $fin ? (int)$today->diff($fin)->days * ($fin >= $today ? 1 : -1) : null;
          $semaforoNivel = $p['semaforo'] ?? 'neutral';
          $dotCls = \App\Services\SemaforoService::dotClass($semaforoNivel);
        ?>
          <div class="timeline-item">
            <div class="timeline-dot <?php echo $dotCls; ?>"></div>
            <div class="timeline-card"
                 data-proyecto-id="<?php echo $p['id']; ?>"
                 data-fecha-fin="<?php echo htmlspecialchars($p['fecha_fin'] ?? ''); ?>"
                 data-estado="<?php echo htmlspecialchars($p['estado']); ?>">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($p['nombre']); ?></div>
                  <div class="d-flex gap-1 mt-1 flex-wrap">
                    <span class="badge estado-badge badge-estado-<?php echo $p['estado']; ?>" style="font-size:0.65rem"><?php echo ucfirst(str_replace('_',' ',$p['estado'])); ?></span>
                    <span class="badge badge-prioridad-<?php echo $p['prioridad']??'media'; ?>" style="font-size:0.65rem"><?php echo ucfirst($p['prioridad']??'media'); ?></span>
                    <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
                    <?php if ($p['usuario_asignado_nombre']??''): ?>
                      <span class="badge bg-light text-dark" style="font-size:0.65rem">
                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($p['usuario_asignado_nombre']); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-end flex-shrink-0">
                  <?php if ($fin): ?>
                    <div class="timeline-card-date"><?php echo $fin->format('d/m/Y'); ?></div>
                    <?php if ($diff !== null && !in_array($p['estado'], $terminados)): ?>
                      <div class="<?php echo $diff < 0 ? 'text-danger' : ($diff <= 3 ? 'text-warning' : 'text-muted'); ?>" style="font-size:0.68rem">
                        <?php echo $diff < 0 ? 'Venci&oacute; hace '.abs($diff).'d' : ($diff === 0 ? 'Hoy' : 'En '.$diff.'d'); ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="timeline-card-date">Sin fecha</div>
                  <?php endif; ?>
                  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $p['id']; ?>/tareas" class="btn btn-xs btn-outline-primary mt-1 py-0 px-1" style="font-size:0.65rem">
                    <i class="bi bi-list-task"></i>
                  </a>
                </div>
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
