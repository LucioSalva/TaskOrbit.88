<?php
$proyectosFiltrados = $proyectos ?? [];
?>

<?php if (empty($proyectosFiltrados)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-inbox display-4 mb-3 d-block opacity-50"></i>
    <?php if (!empty($_GET['estado']) || !empty($_GET['prioridad']) || !empty($_GET['q'])): ?>
      <h5>Sin resultados</h5>
      <p class="small">No se encontraron proyectos con los filtros aplicados. Prueba limpiando los filtros.</p>
      <a href="<?php echo $appUrl; ?>/proyectos" class="btn btn-outline-secondary btn-sm mt-1">
        <i class="bi bi-x-lg me-1"></i>Limpiar filtros
      </a>
    <?php else: ?>
      <h5>Aun no hay proyectos</h5>
      <p class="small">Los proyectos organizan el trabajo del equipo. Crea el primero para comenzar.</p>
      <?php if (in_array($role, ['ADMIN', 'GOD'])): ?>
        <a href="<?php echo $appUrl; ?>/proyectos/crear" class="btn btn-primary btn-sm mt-1">
          <i class="bi bi-plus-lg me-1"></i>Crear primer proyecto
        </a>
      <?php else: ?>
        <p class="small text-muted mt-2">Contacta a un administrador para que te asignen proyectos.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($proyectosFiltrados as $proyecto):
      $semaforoNivel = $proyecto['semaforo'] ?? 'neutral';
    ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card card-proyecto h-100 <?php echo \App\Services\SemaforoService::riskClass($semaforoNivel); ?>"
             data-proyecto-id="<?php echo $e($proyecto['id']); ?>"
             data-fecha-fin="<?php echo $e($proyecto['fecha_fin'] ?? ''); ?>"
             data-estado="<?php echo $e($proyecto['estado']); ?>"
             data-nombre="<?php echo $e($proyecto['nombre']); ?>"
             data-prioridad="<?php echo $e($proyecto['prioridad']); ?>"
             data-usuario-id="<?php echo $e($proyecto['usuario_asignado_id'] ?? ''); ?>"
             data-usuario-nombre="<?php echo $e($proyecto['usuario_asignado_nombre'] ?? ''); ?>"
             data-descripcion="<?php echo $e($proyecto['descripcion'] ?? ''); ?>">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <h5 class="card-title mb-0 fw-semibold text-truncate me-2">
                <a href="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>" class="text-decoration-none stretched-link-inner">
                  <?php echo $e($proyecto['nombre']); ?>
                </a>
              </h5>
              <?php
              $estadoTooltips = [
                'por_hacer' => 'Pendiente de iniciar',
                'haciendo'  => 'En progreso activo',
                'terminada' => 'Completada, pendiente de revision',
                'enterado'  => 'Notificado / visto',
                'ocupado'   => 'En pausa por otro trabajo',
                'aceptada'  => 'Revisada y aprobada',
              ];
              ?>
              <span class="badge estado-badge badge-estado-<?php echo $e($proyecto['estado']); ?> flex-shrink-0"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    title="<?php echo $e($estadoTooltips[$proyecto['estado']] ?? ''); ?>">
                <?php echo $e($estadoLabel[$proyecto['estado']] ?? $proyecto['estado']); ?>
              </span>
              <?php echo \App\Services\SemaforoService::badge($semaforoNivel); ?>
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

            <!-- Quick estado change -->
            <div class="estado-btn-group btn-group btn-group-sm w-100 mb-2">
              <?php foreach(['por_hacer' => 'PH', 'haciendo' => 'H', 'terminada' => 'T'] as $est => $lbl): ?>
              <form method="POST" action="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/estado"
                    class="d-inline flex-fill" onsubmit="return changeEstado(this)">
                <?php echo \App\Helpers\CSRF::tokenField(); ?>
                <input type="hidden" name="estado" value="<?php echo $e($est); ?>">
                <button type="submit"
                  class="btn btn-outline-secondary btn-sm w-100 <?php echo $proyecto['estado'] === $est ? 'active-estado' : ''; ?>"
                  data-estado="<?php echo $e($est); ?>"
                  title="<?php echo $e($estadoLabel[$est]); ?>">
                  <?php echo $e($lbl); ?>
                </button>
              </form>
              <?php endforeach; ?>
            </div>

            <div class="d-flex gap-1 mb-2 flex-wrap">
              <?php if (in_array($role, ['ADMIN','GOD'])): ?>
              <button type="button" class="btn btn-xs btn-outline-primary"
                title="Edicion rapida"
                onclick="openQuickEdit('proyecto', <?php echo $e($proyecto['id']); ?>, {
                  nombre:<?php echo json_encode($proyecto['nombre']); ?>,
                  descripcion:<?php echo json_encode($proyecto['descripcion']??''); ?>,
                  fechaFin:<?php echo json_encode($proyecto['fecha_fin']??''); ?>,
                  prioridad:<?php echo json_encode($proyecto['prioridad']??'media'); ?>
                })"><i class="bi bi-pencil-square me-1"></i><span class="d-none d-sm-inline">Editar</span></button>
              <button type="button" class="btn btn-xs btn-outline-secondary"
                title="Reasignar"
                onclick="openQuickAssign('proyecto', <?php echo $e($proyecto['id']); ?>, <?php echo (int)($proyecto['usuario_asignado_id']??0); ?>, <?php echo json_encode($proyecto['nombre']); ?>)">
                <i class="bi bi-person-check me-1"></i><span class="d-none d-sm-inline">Asignar</span></button>
              <?php endif; ?>
              <button type="button" class="btn btn-xs btn-outline-info"
                title="Nota rapida"
                onclick="openQuickNota('proyecto', <?php echo $e($proyecto['id']); ?>, <?php echo json_encode($proyecto['nombre']); ?>)">
                <i class="bi bi-sticky me-1"></i><span class="d-none d-sm-inline">Nota</span></button>
            </div>
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
                <button type="button" class="btn btn-outline-danger btn-sm ms-auto"
                  data-delete-url="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/eliminar"
                  data-delete-preview-url="<?php echo $appUrl; ?>/proyectos/<?php echo $e($proyecto['id']); ?>/eliminar-preview"
                  data-delete-title="Eliminar proyecto &quot;<?php echo $e($proyecto['nombre']); ?>&quot;?"
                  data-delete-msg="Se eliminaran TODAS las tareas, subtareas y notas del proyecto. Esta accion es irreversible."
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
