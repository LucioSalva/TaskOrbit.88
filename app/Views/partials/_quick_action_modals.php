<?php /** Quick Action Modals & Offcanvas — TaskOrbit Mejora #6 */ ?>

<!-- ================================================
     MODAL: Quick Assign
     Opens via: openQuickAssign(type, id, assigneeId, name)
     ================================================ -->
<div class="modal fade" id="modal-quick-assign" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">
          <i class="bi bi-person-check me-2 text-primary"></i>Asignar responsable
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-3">
        <div class="text-muted small mb-2 text-xs-custom" id="qa-assign-entity-name"></div>
        <input type="hidden" id="qa-assign-entity-type">
        <input type="hidden" id="qa-assign-entity-id">
        <label class="form-label small fw-semibold">Responsable</label>
        <select class="form-select form-select-sm" id="qa-assign-select">
          <option value="">— Seleccionar usuario —</option>
        </select>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" id="qa-assign-submit">
          <span class="spinner-border spinner-border-sm d-none me-1" id="qa-assign-spinner"></span>
          Asignar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ================================================
     OFFCANVAS: Quick Edit
     Opens via: openQuickEdit(type, id, data)
     ================================================ -->
<div class="offcanvas offcanvas-end offcanvas-w-380" tabindex="-1" id="offcanvas-quick-edit" aria-labelledby="qa-edit-title">
  <div class="offcanvas-header border-bottom py-2">
    <h6 class="offcanvas-title mb-0" id="qa-edit-title">
      <i class="bi bi-pencil-square me-2 text-warning"></i>Edici&oacute;n r&aacute;pida
    </h6>
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="text-muted small mb-3 text-xs-custom" id="qa-edit-entity-name"></div>
    <input type="hidden" id="qa-edit-entity-type">
    <input type="hidden" id="qa-edit-entity-id">

    <div class="mb-3">
      <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
      <input type="text" class="form-control form-control-sm" id="qa-edit-nombre" maxlength="200" required>
    </div>
    <div class="mb-3">
      <label class="form-label small fw-semibold">Descripci&oacute;n</label>
      <textarea class="form-control form-control-sm" id="qa-edit-descripcion" rows="3" maxlength="2000"></textarea>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-6">
        <label class="form-label small fw-semibold">Fecha l&iacute;mite</label>
        <input type="date" class="form-control form-control-sm" id="qa-edit-fecha-fin">
      </div>
      <div class="col-6">
        <label class="form-label small fw-semibold">Prioridad</label>
        <select class="form-select form-select-sm" id="qa-edit-prioridad">
          <option value="baja">Baja</option>
          <option value="media">Media</option>
          <option value="alta">Alta</option>
          <option value="critica">Cr&iacute;tica</option>
        </select>
      </div>
    </div>
  </div>
  <div class="offcanvas-footer border-top p-3 d-flex gap-2 justify-content-end">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Cancelar</button>
    <button type="button" class="btn btn-sm btn-warning" id="qa-edit-submit">
      <span class="spinner-border spinner-border-sm d-none me-1" id="qa-edit-spinner"></span>
      Guardar cambios
    </button>
  </div>
</div>

<!-- ================================================
     MODAL: Quick Nota
     Opens via: openQuickNota(type, id, name)
     ================================================ -->
<div class="modal fade" id="modal-quick-nota" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">
          <i class="bi bi-sticky me-2 text-info"></i>Nota r&aacute;pida
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-3">
        <div class="text-muted small mb-2 text-xs-custom" id="qa-nota-entity-name"></div>
        <input type="hidden" id="qa-nota-entity-type">
        <input type="hidden" id="qa-nota-entity-id">
        <div class="mb-3">
          <label class="form-label small fw-semibold">T&iacute;tulo <span class="text-muted fw-normal">(opcional)</span></label>
          <input type="text" class="form-control form-control-sm" id="qa-nota-titulo" maxlength="200" placeholder="Ej: Reuni&oacute;n, Acuerdo...">
        </div>
        <div class="mb-1">
          <label class="form-label small fw-semibold">Contenido <span class="text-danger">*</span></label>
          <textarea class="form-control form-control-sm" id="qa-nota-contenido" rows="4" maxlength="5000"
                    placeholder="Escribe tu nota aqu&iacute;..." required></textarea>
          <div class="invalid-feedback">El contenido es requerido.</div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-info text-white" id="qa-nota-submit">
          <span class="spinner-border spinner-border-sm d-none me-1" id="qa-nota-spinner"></span>
          Guardar nota
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ================================================
     TOAST CONTAINER (feedback for AJAX actions)
     ================================================ -->
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3 z-1100"></div>
