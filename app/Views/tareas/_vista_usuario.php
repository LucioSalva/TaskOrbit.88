<?php
$appUrl = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$role   = $role ?? ($user['rol'] ?? '');
$p      = $p ?? ($proyecto ?? []);
$terminados = ['terminada', 'aceptada'];
$today = new \DateTime(); $today->setTime(0,0,0);
?>
<?php if (empty($tarByUsuario)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people fs-1 d-block mb-2"></i>Sin tareas asignadas.
  </div>
<?php else: foreach ($tarByUsuario as $uNombre => $uData):
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
        <div class="text-muted text-xxs"><?php echo $total; ?> tarea<?php echo $total !== 1 ? 's' : ''; ?></div>
      </div>
      <div class="user-group-stats">
        <?php if ($haciendo > 0): ?><span class="user-group-stat stat-haciendo" title="Haciendo"><?php echo $haciendo; ?> haciendo</span><?php endif; ?>
        <?php if ($pending  > 0): ?><span class="user-group-stat stat-pendiente" title="Por hacer"><?php echo $pending; ?> por hacer</span><?php endif; ?>
        <?php if ($termins  > 0): ?><span class="user-group-stat stat-terminada" title="Terminados"><?php echo $termins; ?> terminada<?php echo $termins!==1?'s':''; ?></span><?php endif; ?>
        <?php if ($vencidas > 0): ?><span class="user-group-stat stat-vencida" title="Vencidos"><?php echo $vencidas; ?> vencida<?php echo $vencidas!==1?'s':''; ?></span><?php endif; ?>
      </div>
      <i class="bi bi-chevron-down collapse-icon text-muted ms-2 transition-transform"></i>
    </div>
    <div class="user-group-body">
      <div class="row g-2">
        <?php foreach ($items as $t):
          $semaforoNivel = $t['semaforo'] ?? 'neutral';
          $subtareas = $t['subtareas'] ?? [];
          $subTotal  = count($subtareas);
          $subDone   = count(array_filter($subtareas, fn($s) => in_array($s['estado'], ['terminada','aceptada'])));
        ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100 text-sm-px <?php echo \App\Services\SemaforoService::riskClass($semaforoNivel); ?>"
                 data-tarea-id="<?php echo $t['id']; ?>"
                 data-fecha-fin="<?php echo htmlspecialchars($t['fecha_fin'] ?? ''); ?>"
                 data-estado="<?php echo htmlspecialchars($t['estado']); ?>"
                 data-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                 data-prioridad="<?php echo htmlspecialchars($t['prioridad'] ?? 'media'); ?>"
                 data-usuario-id="<?php echo htmlspecialchars($t['usuario_asignado_id'] ?? ''); ?>"
                 data-usuario-nombre="<?php echo htmlspecialchars($t['usuario_asignado_nombre'] ?? ''); ?>">
              <div class="card-body p-2">
                <div class="fw-semibold mb-1 text-truncate"><?php echo htmlspecialchars($t['nombre']); ?></div>
                <div class="d-flex gap-1 flex-wrap align-items-center">
                  <span class="badge estado-badge badge-estado-<?php echo $t['estado']; ?>"><?php echo ucfirst(str_replace('_',' ',$t['estado'])); ?></span>
                  <span class="badge badge-prioridad-<?php echo $t['prioridad']??'media'; ?>"><?php echo ucfirst($t['prioridad']??'media'); ?></span>
                  <?php if ($subTotal > 0): ?>
                    <span class="badge bg-light text-dark text-2xs"><i class="bi bi-check2-square me-1"></i><?php echo $subDone; ?>/<?php echo $subTotal; ?></span>
                  <?php endif; ?>
                  <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
                </div>
                <?php if ($subTotal > 0): ?>
                <div class="subtask-progress mt-1">
                  <div class="subtask-progress-fill" data-progress-pct="<?php echo $subTotal > 0 ? round($subDone / $subTotal * 100) : 0; ?>"></div>
                </div>
                <?php endif; ?>
                <div class="kanban-card-actions mt-2">
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
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>
