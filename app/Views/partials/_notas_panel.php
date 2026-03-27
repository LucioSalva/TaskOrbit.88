<?php
/**
 * Reusable Notas / Bitácora Panel
 *
 * Required variables:
 *   $notasScope       string  — 'proyecto' | 'tarea' | 'subtarea'
 *   $notasRefId       int     — referencia_id
 *   $notas            array   — fetched from Nota::getByScope / getByEntity
 *   $notasCanWrite    bool    — whether current user can add notes
 *   $notasRole        string  — current user role ('GOD', 'ADMIN', 'USER')
 *   $notasUserId      int     — current user id
 *   $notasPanelTitle  string  — panel title (optional, default 'Bitácora')
 */
$appUrl          = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$notasPanelTitle = $notasPanelTitle ?? 'Bitácora';
$notasCanWrite   = $notasCanWrite ?? true;
$notasRole       = $notasRole ?? '';
$notasUserId     = (int)($notasUserId ?? 0);
$notasLazy       = $notasLazy ?? false;
$notas           = $notas ?? [];

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$tipoLabel = [
    'personal'  => ['label' => 'Personal',  'class' => 'bg-secondary'],
    'actividad' => ['label' => 'Actividad', 'class' => 'bg-primary'],
    'auto'      => ['label' => 'Sistema',   'class' => 'bg-info text-dark'],
    'sistema'   => ['label' => 'Sistema',   'class' => 'bg-info text-dark'],
];

$panelId = 'notas-panel-' . $notasScope . '-' . $notasRefId;
$listId  = 'notas-list-' . $notasScope . '-' . $notasRefId;
$countId = 'notas-count-' . $notasScope . '-' . $notasRefId;
$formId  = 'nota-form-' . $notasScope . '-' . $notasRefId;
?>
<div class="notas-panel" id="<?php echo $e($panelId); ?>"
     data-scope="<?php echo $e($notasScope); ?>"
     data-ref-id="<?php echo $e($notasRefId); ?>"
     data-role="<?php echo $e($notasRole); ?>"
     data-user-id="<?php echo $e($notasUserId); ?>"
     <?php if ($notasLazy): ?>data-lazy="true"<?php endif; ?>>

  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-semibold mb-0">
      <i class="bi bi-journal-text me-1 text-primary"></i><?php echo $e($notasPanelTitle); ?>
      <span class="badge bg-secondary ms-1 fw-normal" id="<?php echo $e($countId); ?>"><?php echo count($notas); ?></span>
    </h6>
  </div>

  <?php if ($notasCanWrite): ?>
  <div class="mb-3">
    <form id="<?php echo $e($formId); ?>"
          method="POST" action="<?php echo $appUrl; ?>/notas"
          class="notas-add-form"
          data-scope="<?php echo $e($notasScope); ?>"
          data-ref-id="<?php echo $e($notasRefId); ?>"
          data-list-id="<?php echo $e($listId); ?>"
          data-count-id="<?php echo $e($countId); ?>">
      <?php echo \App\Helpers\CSRF::tokenField(); ?>
      <input type="hidden" name="scope" value="<?php echo $e($notasScope); ?>">
      <input type="hidden" name="referencia_id" value="<?php echo $e($notasRefId); ?>">
      <div class="mb-1">
        <input type="text" name="titulo" class="form-control form-control-sm"
               placeholder="Título (opcional)" maxlength="200">
      </div>
      <div class="mb-1">
        <textarea name="contenido" class="form-control form-control-sm nota-contenido-input"
                  rows="2" placeholder="Escribe una nota o comentario..." required></textarea>
      </div>
      <div class="d-flex align-items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Agregar
        </button>
        <span class="nota-spinner d-none spinner-border spinner-border-sm text-primary" role="status"></span>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div id="<?php echo $e($listId); ?>" class="notas-list">
    <?php if (empty($notas)): ?>
    <div class="text-center py-3 text-muted small notas-empty-msg" id="<?php echo $e($listId); ?>-empty">
      <i class="bi bi-journal d-block mb-1 icon-sm"></i>
      Sin notas registradas.
    </div>
    <?php else: foreach ($notas as $nota):
        $esAuto     = in_array($nota['tipo'] ?? '', ['auto', 'sistema'], true);
        $canEdit    = \App\Models\Nota::canEdit($nota, $notasUserId, $notasRole);
        $canDelete  = (int)($nota['user_id'] ?? 0) === $notasUserId || in_array($notasRole, ['GOD', 'ADMIN'], true);
        $canPin     = in_array($notasRole, ['ADMIN', 'GOD'], true);
        $isPinned   = !empty($nota['is_pinned']);
        $tipoInfo   = $tipoLabel[$nota['tipo'] ?? 'actividad'] ?? $tipoLabel['actividad'];
        $notaId     = (int)$nota['id'];
    ?>
    <div class="nota-item rounded border-start border-2 <?php echo $isPinned ? 'border-warning bg-warning-subtle' : 'border-secondary-subtle'; ?> ps-2 py-2 mb-2"
         id="nota-item-<?php echo $notaId; ?>"
         data-nota-id="<?php echo $notaId; ?>">

      <div class="d-flex align-items-start justify-content-between gap-1 mb-1">
        <div class="flex-fill min-w-0">
          <?php if ($isPinned): ?><i class="bi bi-pin-fill text-warning me-1" title="Fijada"></i><?php endif; ?>
          <span class="badge <?php echo $e($tipoInfo['class']); ?> badge-sm text-2xs"><?php echo $e($tipoInfo['label']); ?></span>
          <?php if ($nota['titulo'] ?? ''): ?>
          <strong class="small ms-1 nota-titulo-display"><?php echo $e($nota['titulo']); ?></strong>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
          <?php if ($canPin): ?>
          <form method="POST" action="<?php echo $appUrl; ?>/notas/<?php echo $notaId; ?>/pin"
                class="nota-pin-form d-inline" data-nota-id="<?php echo $notaId; ?>">
            <?php echo \App\Helpers\CSRF::tokenField(); ?>
            <button type="submit" class="btn btn-link btn-sm p-0 <?php echo $isPinned ? 'text-warning' : 'text-muted'; ?> text-xs-custom"
                    title="<?php echo $isPinned ? 'Desfijar' : 'Fijar'; ?>">
              <i class="bi <?php echo $isPinned ? 'bi-pin-fill' : 'bi-pin'; ?>"></i>
            </button>
          </form>
          <?php endif; ?>
          <?php if ($canEdit && !$esAuto): ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-muted nota-edit-btn text-xs-custom"
                  data-nota-id="<?php echo $notaId; ?>"
                  data-titulo="<?php echo $e($nota['titulo'] ?? ''); ?>"
                  data-contenido="<?php echo $e($nota['contenido']); ?>"
                  title="Editar">
            <i class="bi bi-pencil"></i>
          </button>
          <?php endif; ?>
          <?php if ($canDelete): ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-danger text-xs-custom"
                  title="Eliminar nota"
                  data-delete-url="<?php echo $appUrl; ?>/notas/<?php echo $notaId; ?>/eliminar"
                  data-delete-title="Eliminar nota?"
                  data-delete-msg="Se eliminara esta nota. Esta accion no se puede deshacer."
                  data-show-reason="false">
            <i class="bi bi-trash"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="nota-contenido-display small"><?php echo nl2br($e($nota['contenido'])); ?></div>

      <!-- Inline edit form (hidden by default) -->
      <?php if ($canEdit && !$esAuto): ?>
      <div class="nota-edit-form d-none mt-2" data-nota-id="<?php echo $notaId; ?>">
        <form method="POST" action="<?php echo $appUrl; ?>/notas/<?php echo $notaId; ?>/editar"
              class="nota-update-form" data-nota-id="<?php echo $notaId; ?>">
          <?php echo \App\Helpers\CSRF::tokenField(); ?>
          <input type="text" name="titulo" class="form-control form-control-sm mb-1"
                 placeholder="Título (opcional)" maxlength="200"
                 value="<?php echo $e($nota['titulo'] ?? ''); ?>">
          <textarea name="contenido" class="form-control form-control-sm mb-1"
                    rows="2" required><?php echo $e($nota['contenido']); ?></textarea>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm text-xs-custom">Guardar</button>
            <button type="button" class="btn btn-outline-secondary btn-sm nota-cancel-edit text-xs-custom"
                    data-nota-id="<?php echo $notaId; ?>">Cancelar</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="text-muted mt-1 fs-xs">
        <i class="bi bi-person me-1"></i><?php echo $e($nota['autor_nombre'] ?? 'Sistema'); ?>
        &bull;
        <?php echo \App\Helpers\DateHelper::formatDatetime($nota['created_at'] ?? ''); ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
