<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$terminados = ['terminada', 'aceptada'];
$today = new \DateTime(); $today->setTime(0,0,0);
?>
<?php if (empty($proyByUsuario)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people fs-1 d-block mb-2"></i>Sin proyectos asignados.
  </div>
<?php else: foreach ($proyByUsuario as $uNombre => $uData):
  $items = $uData['items'];
  $total    = count($items);
  $haciendo = count(array_filter($items, fn($x) => $x['estado'] === 'haciendo'));
  $termins  = count(array_filter($items, fn($x) => in_array($x['estado'], $terminados)));
  $pending  = count(array_filter($items, fn($x) => $x['estado'] === 'por_hacer'));
  $vencidas = count(array_filter($items, function($x) use ($today, $terminados) {
    if (in_array($x['estado'], $terminados) || !($x['fecha_fin'] ?? '')) return false;
    $fin = new \DateTime($x['fecha_fin']); $fin->setTime(0,0,0);
    return $fin < $today;
  }));
?>
  <div class="user-group">
    <div class="user-group-header">
      <div class="user-avatar"><?php echo mb_strtoupper(mb_substr((string)$uNombre, 0, 1)); ?></div>
      <div>
        <div class="fw-semibold small"><?php echo htmlspecialchars((string)$uNombre); ?></div>
        <div class="text-muted" style="font-size:0.7rem"><?php echo $total; ?> proyecto<?php echo $total !== 1 ? 's' : ''; ?></div>
      </div>
      <div class="user-group-stats">
        <?php if ($haciendo > 0): ?><span class="user-group-stat stat-haciendo" title="Haciendo"><?php echo $haciendo; ?> haciendo</span><?php endif; ?>
        <?php if ($pending  > 0): ?><span class="user-group-stat stat-pendiente" title="Por hacer"><?php echo $pending; ?> por hacer</span><?php endif; ?>
        <?php if ($termins  > 0): ?><span class="user-group-stat stat-terminada" title="Terminados"><?php echo $termins; ?> terminado<?php echo $termins!==1?'s':''; ?></span><?php endif; ?>
        <?php if ($vencidas > 0): ?><span class="user-group-stat stat-vencida" title="Vencidos"><?php echo $vencidas; ?> vencido<?php echo $vencidas!==1?'s':''; ?></span><?php endif; ?>
      </div>
      <i class="bi bi-chevron-down collapse-icon text-muted ms-2" style="transition:transform 0.2s"></i>
    </div>
    <div class="user-group-body">
      <div class="row g-2">
        <?php foreach ($items as $p):
          $semaforoNivel = $p['semaforo'] ?? 'neutral';
        ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100 <?php echo \App\Services\SemaforoService::riskClass($semaforoNivel); ?>"
                 data-proyecto-id="<?php echo $p['id']; ?>"
                 data-fecha-fin="<?php echo htmlspecialchars($p['fecha_fin'] ?? ''); ?>"
                 data-estado="<?php echo htmlspecialchars($p['estado']); ?>"
                 style="font-size:0.82rem">
              <div class="card-body p-2">
                <div class="fw-semibold mb-1 text-truncate"><?php echo htmlspecialchars($p['nombre']); ?></div>
                <div class="d-flex gap-1 flex-wrap align-items-center">
                  <span class="badge estado-badge badge-estado-<?php echo $p['estado']; ?>"><?php echo ucfirst(str_replace('_',' ',$p['estado'])); ?></span>
                  <span class="badge badge-prioridad-<?php echo $p['prioridad']??'media'; ?>"><?php echo ucfirst($p['prioridad']??'media'); ?></span>
                  <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
                  <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $p['id']; ?>/tareas" class="btn btn-xs btn-outline-secondary py-0 px-1 ms-auto" style="font-size:0.65rem">
                    <i class="bi bi-list-task me-1"></i>Tareas
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>
