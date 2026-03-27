<?php
/**
 * Reusable Evidencias Panel
 *
 * Required variables:
 *   $evidTipo           string  — 'proyecto' | 'tarea' | 'subtarea'
 *   $evidId             int     — entity ID
 *   $evidCanUpload      bool    — whether current user can upload
 *   $evidCanDelete      bool    — whether current user can delete (GOD/ADMIN)
 *   $evidEntityTerminada bool   — entity is already terminada
 *
 * Optional:
 *   $evidencias         array   — pre-loaded evidences (if empty, loaded via AJAX)
 */
$appUrl          = $appUrl ?? rtrim(getenv('APP_URL') ?: '', '/');
$evidTipo        = $evidTipo ?? '';
$evidId          = (int)($evidId ?? 0);
$evidCanUpload   = $evidCanUpload ?? true;
$evidCanDelete   = $evidCanDelete ?? false;
$evidEntityTerminada = $evidEntityTerminada ?? false;
$evidencias      = $evidencias ?? [];

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$panelUid = 'evid-' . $evidTipo . '-' . $evidId;
?>
<div class="evidencias-panel" id="<?php echo $panelUid; ?>"
     data-tipo="<?php echo $e($evidTipo); ?>"
     data-entidad-id="<?php echo $evidId; ?>"
     data-can-upload="<?php echo $evidCanUpload ? '1' : '0'; ?>"
     data-can-delete="<?php echo $evidCanDelete ? '1' : '0'; ?>"
     data-entity-terminada="<?php echo $evidEntityTerminada ? '1' : '0'; ?>">

  <div class="evidencias-header d-flex align-items-center justify-content-between mb-2">
    <span class="small fw-semibold text-muted">
      <i class="bi bi-paperclip me-1"></i>Evidencias
      <span class="evidencias-count badge bg-secondary ms-1"><?php echo count($evidencias); ?></span>
    </span>
  </div>

  <!-- Evidence list -->
  <div class="evidencias-list">
    <?php if (empty($evidencias)): ?>
    <div class="evidencias-empty text-muted small py-2">Sin evidencias adjuntas.</div>
    <?php else: ?>
    <?php foreach ($evidencias as $ev): ?>
    <div class="evidencia-item d-flex align-items-center gap-2 py-1" data-evidencia-id="<?php echo $e($ev['id']); ?>">
      <span class="evidencia-badge-<?php echo $e($ev['extension']); ?> badge"><?php echo strtoupper($e($ev['extension'])); ?></span>
      <div class="flex-fill min-w-0">
        <div class="text-truncate small fw-medium"><?php echo $e($ev['nombre_original']); ?></div>
        <div class="text-muted fs-xs">
          <?php echo $e($ev['subido_por_nombre']); ?> &middot;
          <?php echo date('d/m/Y H:i', strtotime($ev['created_at'])); ?> &middot;
          <?php echo round(((int)$ev['peso_bytes']) / 1024); ?> KB
        </div>
      </div>
      <a href="<?php echo $appUrl; ?>/evidencias/<?php echo $e($ev['id']); ?>/descargar"
         class="btn btn-outline-primary btn-sm py-0 px-1" title="Descargar">
        <i class="bi bi-download"></i>
      </a>
      <?php if ($evidCanDelete && !$evidEntityTerminada): ?>
      <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 btn-eliminar-evidencia"
              data-evidencia-id="<?php echo $e($ev['id']); ?>" title="Eliminar">
        <i class="bi bi-trash"></i>
      </button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Upload form -->
  <?php if ($evidCanUpload): ?>
  <div class="evidencia-upload-zone mt-2">
    <form class="evidencia-upload-form" enctype="multipart/form-data" data-tipo="<?php echo $e($evidTipo); ?>" data-entidad-id="<?php echo $evidId; ?>">
      <input type="hidden" name="csrf_token" value="<?php echo $e(\App\Helpers\CSRF::getToken()); ?>">
      <input type="hidden" name="tipo_entidad" value="<?php echo $e($evidTipo); ?>">
      <input type="hidden" name="entidad_id" value="<?php echo $evidId; ?>">
      <div class="d-flex align-items-center gap-2">
        <input type="file" name="archivo" accept=".pdf,.png" class="form-control form-control-sm flex-fill">
        <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">
          <i class="bi bi-upload me-1"></i>Subir
        </button>
      </div>
      <div class="form-text fs-xs">Solo PDF y PNG, max 5 MB</div>
    </form>
    <div class="evidencia-feedback mt-1 d-none"></div>
    <div class="evidencia-progress mt-1 d-none">
      <div class="progress progress-h4">
        <div class="progress-bar progress-bar-striped progress-bar-animated progress-init-0"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
