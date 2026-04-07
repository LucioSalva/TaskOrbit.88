/* =============================================
   TaskOrbit — Acciones Rapidas Module
   Requires: app.js (getCsrfToken, escapeHtml, estadoLabel)
   ============================================= */
'use strict';

// ---- Bootstrap instance cache ----
var _qaAssignModal = null;
var _qaEditOffcanvas = null;
var _qaNotaModal = null;

function _getAssignModal() {
  if (!_qaAssignModal) _qaAssignModal = new bootstrap.Modal(document.getElementById('modal-quick-assign'));
  return _qaAssignModal;
}
function _getEditOffcanvas() {
  if (!_qaEditOffcanvas) _qaEditOffcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvas-quick-edit'));
  return _qaEditOffcanvas;
}
function _getNotaModal() {
  if (!_qaNotaModal) _qaNotaModal = new bootstrap.Modal(document.getElementById('modal-quick-nota'));
  return _qaNotaModal;
}

// ================================================================
// TOAST FEEDBACK
// ================================================================

/**
 * Show a toast message.
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 */
function showActionFeedback(message, type) {
  type = type || 'success';
  var iconMap = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  var colorMap = { success: 'text-success', error: 'text-danger', warning: 'text-warning', info: 'text-info' };

  // Crear container dinámicamente si no existe en el DOM
  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }

  var id = 'toast-' + Date.now();
  var html = '<div id="' + id + '" class="toast align-items-center border-0 shadow-sm mb-2" role="alert" aria-live="assertive" data-bs-delay="4000">' +
    '<div class="d-flex">' +
    '<div class="toast-body d-flex align-items-center gap-2">' +
    '<i class="bi ' + (iconMap[type] || iconMap.info) + ' ' + (colorMap[type] || colorMap.info) + '"></i>' +
    '<span>' + escapeHtml(message) + '</span>' +
    '</div>' +
    '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>' +
    '</div></div>';

  container.insertAdjacentHTML('beforeend', html);
  var toastEl = document.getElementById(id);

  if (toastEl && typeof bootstrap !== 'undefined') {
    var toast = new bootstrap.Toast(toastEl);
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', function() { toastEl.remove(); });
  } else if (toastEl) {
    // Fallback si Bootstrap no está disponible
    toastEl.style.display = 'block';
    setTimeout(function() { toastEl.remove(); }, 4000);
  }
}

// ================================================================
// DOM UPDATE — update card UI across ALL views after any action
// ================================================================

/**
 * After a quick action, update ALL matching card DOM elements.
 * Looks for elements with data-{type}-id="{id}" everywhere on the page.
 */
function updateCardUI(entityType, entityId, fields) {
  var attr = 'data-' + entityType + '-id';
  var selector = '[' + attr + '="' + entityId + '"]';
  document.querySelectorAll(selector).forEach(function(card) {
    // Update nombre
    if (fields.nombre !== undefined) {
      var nameEl = card.querySelector('.card-nombre, .kanban-card-title, .entity-nombre');
      if (nameEl) nameEl.textContent = fields.nombre;
      // Update data attribute too
      card.dataset.nombre = fields.nombre;
    }
    // Update prioridad badge
    if (fields.prioridad !== undefined) {
      var priorEl = card.querySelector('.badge[class*="badge-prioridad"]');
      if (priorEl) {
        priorEl.className = 'badge badge-prioridad-' + fields.prioridad;
        priorEl.textContent = fields.prioridad.charAt(0).toUpperCase() + fields.prioridad.slice(1);
      }
      card.dataset.prioridad = fields.prioridad;
    }
    // Update fecha_fin display
    if (fields.fecha_fin !== undefined) {
      var fechaEl = card.querySelector('.card-fecha-fin, .fecha-fin-display');
      if (fechaEl) {
        fechaEl.textContent = fields.fecha_fin ? formatDateDisplay(fields.fecha_fin) : 'Sin fecha';
      }
      card.dataset.fechaFin = fields.fecha_fin || '';
    }
    // Update assignee display
    if (fields.usuario_asignado_nombre !== undefined) {
      var assigneeEl = card.querySelector('.card-assignee, .assignee-name');
      if (assigneeEl) assigneeEl.textContent = fields.usuario_asignado_nombre || 'Sin asignar';
      var avatarEl = card.querySelector('.user-avatar');
      if (avatarEl && fields.usuario_asignado_nombre) {
        avatarEl.textContent = fields.usuario_asignado_nombre.charAt(0).toUpperCase();
        avatarEl.title = fields.usuario_asignado_nombre;
      }
      card.dataset.usuarioNombre = fields.usuario_asignado_nombre || '';
      if (fields.usuario_asignado_id !== undefined) {
        card.dataset.usuarioId = fields.usuario_asignado_id || '';
      }
    }
  });
}

function formatDateDisplay(dateStr) {
  if (!dateStr) return '';
  try {
    var d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } catch(e) { return dateStr; }
}

// ================================================================
// SPINNER helpers
// ================================================================
function _showSpinner(spinnerId, btnId) {
  var sp = document.getElementById(spinnerId);
  var btn = document.getElementById(btnId);
  if (sp) sp.classList.remove('d-none');
  if (btn) btn.disabled = true;
}
function _hideSpinner(spinnerId, btnId) {
  var sp = document.getElementById(spinnerId);
  var btn = document.getElementById(btnId);
  if (sp) sp.classList.add('d-none');
  if (btn) btn.disabled = false;
}

// ================================================================
// QUICK ASSIGN
// ================================================================

/**
 * @param {string} entityType  'tarea' | 'proyecto' | 'subtarea'
 * @param {number} entityId
 * @param {number|null} currentAssigneeId
 * @param {string} entityNombre
 */
function openQuickAssign(entityType, entityId, currentAssigneeId, entityNombre) {
  document.getElementById('qa-assign-entity-type').value = entityType;
  document.getElementById('qa-assign-entity-id').value = entityId;
  document.getElementById('qa-assign-entity-name').textContent = entityNombre || '';

  var select = document.getElementById('qa-assign-select');
  select.innerHTML = '<option value="">— Sin asignar —</option>';

  var usuarios = (typeof USUARIOS_ASIGNABLES !== 'undefined') ? USUARIOS_ASIGNABLES : [];
  usuarios.forEach(function(u) {
    var opt = document.createElement('option');
    opt.value = u.id;
    opt.textContent = u.nombre_completo + (u.rol ? ' (' + u.rol + ')' : '');
    if (u.id == currentAssigneeId) opt.selected = true;
    select.appendChild(opt);
  });

  _getAssignModal().show();
}

/**
 * Build the correct assignment URL for the given entity type.
 * - proyecto -> POST /proyectos/{id}/editar  (handled by ProyectosController@update)
 * - tarea    -> POST /tareas/{id}/editar     (handled by TareasController@update)
 * - subtarea -> POST /subtareas/{id}/asignar (handled by SubtareasController@assign)
 */
function _buildAssignUrl(appUrl, entityType, entityId) {
  if (entityType === 'subtarea') {
    return appUrl + '/subtareas/' + encodeURIComponent(entityId) + '/asignar';
  }
  if (entityType === 'tarea') {
    return appUrl + '/tareas/' + encodeURIComponent(entityId) + '/editar';
  }
  // Default: proyecto
  return appUrl + '/proyectos/' + encodeURIComponent(entityId) + '/editar';
}

function submitQuickAssign() {
  var entityType = document.getElementById('qa-assign-entity-type').value;
  var entityId   = document.getElementById('qa-assign-entity-id').value;
  var usuarioId  = document.getElementById('qa-assign-select').value;
  var appUrl     = document.body.dataset.appUrl || '';

  var url = _buildAssignUrl(appUrl, entityType, entityId);

  _showSpinner('qa-assign-spinner', 'qa-assign-submit');

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: 'csrf_token=' + encodeURIComponent(getCsrfToken()) + '&usuario_asignado_id=' + encodeURIComponent(usuarioId)
  })
  .then(function(r) {
    if (r.status === 419) {
      console.warn('CSRF token mismatch on submitQuickAssign.');
      _hideSpinner('qa-assign-spinner', 'qa-assign-submit');
      showActionFeedback('Error de sesión. Intenta de nuevo.', 'error');
      return Promise.reject('csrf_expired');
    }
    return r.json().catch(function() { return { ok: false, message: 'Respuesta inválida del servidor.' }; });
  })
  .then(function(res) {
    if (!res) return;
    refreshCsrfToken(res);
    _hideSpinner('qa-assign-spinner', 'qa-assign-submit');
    if (res.ok) {
      _getAssignModal().hide();
      var data = res.subtarea || res.tarea || res.proyecto || {};
      updateCardUI(entityType, parseInt(entityId, 10), {
        usuario_asignado_id:     data.usuario_asignado_id,
        usuario_asignado_nombre: data.usuario_asignado_nombre
      });
      showActionFeedback('Responsable actualizado', 'success');
    } else {
      showActionFeedback(res.message || 'Error al reasignar', 'error');
    }
  })
  .catch(function() {
    _hideSpinner('qa-assign-spinner', 'qa-assign-submit');
    showActionFeedback('Error de conexion', 'error');
  });
}

// ================================================================
// QUICK EDIT
// ================================================================

/**
 * @param {string} entityType  'tarea' | 'proyecto'
 * @param {number} entityId
 * @param {object} currentData  {nombre, descripcion, fecha_fin, prioridad}
 */
function openQuickEdit(entityType, entityId, currentData) {
  currentData = currentData || {};
  document.getElementById('qa-edit-entity-type').value = entityType;
  document.getElementById('qa-edit-entity-id').value = entityId;
  document.getElementById('qa-edit-entity-name').textContent =
    (entityType === 'tarea' ? 'Tarea' : 'Proyecto') + ' #' + entityId;

  document.getElementById('qa-edit-nombre').value      = currentData.nombre      || '';
  document.getElementById('qa-edit-descripcion').value = currentData.descripcion  || '';
  document.getElementById('qa-edit-fecha-fin').value   = currentData.fechaFin     || '';
  document.getElementById('qa-edit-prioridad').value   = currentData.prioridad    || 'media';

  _getEditOffcanvas().show();
}

function submitQuickEdit() {
  var entityType  = document.getElementById('qa-edit-entity-type').value;
  var entityId    = document.getElementById('qa-edit-entity-id').value;
  var nombre      = document.getElementById('qa-edit-nombre').value.trim();
  var descripcion = document.getElementById('qa-edit-descripcion').value.trim();
  var fechaFin    = document.getElementById('qa-edit-fecha-fin').value;
  var prioridad   = document.getElementById('qa-edit-prioridad').value;
  var appUrl      = document.body.dataset.appUrl || '';

  if (!nombre) {
    showActionFeedback('El nombre es requerido', 'warning');
    document.getElementById('qa-edit-nombre').focus();
    return;
  }

  var url = appUrl + '/' + (entityType === 'tarea' ? 'tareas' : 'proyectos') + '/' + entityId + '/editar';

  _showSpinner('qa-edit-spinner', 'qa-edit-submit');

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: 'csrf_token=' + encodeURIComponent(getCsrfToken()) +
          '&nombre='      + encodeURIComponent(nombre) +
          '&descripcion=' + encodeURIComponent(descripcion) +
          '&fecha_fin='   + encodeURIComponent(fechaFin) +
          '&prioridad='   + encodeURIComponent(prioridad)
  })
  .then(function(r) {
    if (r.status === 419) {
      console.warn('CSRF token mismatch on submitQuickEdit.');
      _hideSpinner('qa-edit-spinner', 'qa-edit-submit');
      showActionFeedback('Error de sesión. Intenta de nuevo.', 'error');
      return Promise.reject('csrf_expired');
    }
    return r.json();
  })
  .then(function(res) {
    refreshCsrfToken(res);
    _hideSpinner('qa-edit-spinner', 'qa-edit-submit');
    if (res.ok) {
      _getEditOffcanvas().hide();
      var data = res.tarea || res.proyecto || {};
      updateCardUI(entityType, parseInt(entityId), {
        nombre:    data.nombre,
        prioridad: data.prioridad,
        fecha_fin: data.fecha_fin
      });
      showActionFeedback('Cambios guardados', 'success');
    } else {
      showActionFeedback(res.message || 'Error al guardar', 'error');
    }
  })
  .catch(function() {
    _hideSpinner('qa-edit-spinner', 'qa-edit-submit');
    showActionFeedback('Error de conexion', 'error');
  });
}

// ================================================================
// QUICK NOTA
// ================================================================

/**
 * @param {string} entityType  'proyecto' | 'tarea' | 'subtarea'
 * @param {number} entityId
 * @param {string} entityNombre
 */
function openQuickNota(entityType, entityId, entityNombre) {
  document.getElementById('qa-nota-entity-type').value = entityType;
  document.getElementById('qa-nota-entity-id').value   = entityId;
  document.getElementById('qa-nota-entity-name').textContent = entityNombre || '';
  document.getElementById('qa-nota-titulo').value    = '';
  document.getElementById('qa-nota-contenido').value = '';
  document.getElementById('qa-nota-contenido').classList.remove('is-invalid');

  _getNotaModal().show();
  setTimeout(function() { document.getElementById('qa-nota-contenido').focus(); }, 300);
}

function submitQuickNota() {
  var entityType = document.getElementById('qa-nota-entity-type').value;
  var entityId   = document.getElementById('qa-nota-entity-id').value;
  var titulo     = document.getElementById('qa-nota-titulo').value.trim();
  var contenido  = document.getElementById('qa-nota-contenido').value.trim();
  var appUrl     = document.body.dataset.appUrl || '';

  if (!contenido) {
    document.getElementById('qa-nota-contenido').classList.add('is-invalid');
    return;
  }
  document.getElementById('qa-nota-contenido').classList.remove('is-invalid');

  _showSpinner('qa-nota-spinner', 'qa-nota-submit');

  fetch(appUrl + '/notas', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: 'csrf_token='  + encodeURIComponent(getCsrfToken()) +
          '&scope='       + encodeURIComponent(entityType) +
          '&referencia_id=' + encodeURIComponent(entityId) +
          '&titulo='      + encodeURIComponent(titulo) +
          '&contenido='   + encodeURIComponent(contenido)
  })
  .then(function(r) {
    if (r.status === 419) {
      console.warn('CSRF token mismatch on submitQuickNota.');
      _hideSpinner('qa-nota-spinner', 'qa-nota-submit');
      showActionFeedback('Error de sesión. Intenta de nuevo.', 'error');
      return Promise.reject('csrf_expired');
    }
    return r.json();
  })
  .then(function(res) {
    refreshCsrfToken(res);
    _hideSpinner('qa-nota-spinner', 'qa-nota-submit');
    if (res.ok) {
      _getNotaModal().hide();
      // Try to prepend the note to a visible notes list if present
      var notesList = document.getElementById('notas-list-' + entityType + '-' + entityId);
      if (notesList && res.nota) {
        var nota = res.nota;
        var noteHtml = '<div class="nota-item border rounded p-2 mb-2 fade-in" style="font-size:0.82rem">' +
          '<div class="d-flex justify-content-between gap-1 mb-1">' +
          '<strong class="text-truncate">' + escapeHtml(nota.titulo || 'Sin titulo') + '</strong>' +
          '<span class="text-muted text-nowrap" style="font-size:0.7rem">' + escapeHtml(nota.created_at) + '</span>' +
          '</div>' +
          '<div>' + escapeHtml(nota.contenido) + '</div>' +
          '<div class="text-muted mt-1" style="font-size:0.7rem"><i class="bi bi-person me-1"></i>' + escapeHtml(nota.autor_nombre) + '</div>' +
          '</div>';
        notesList.insertAdjacentHTML('afterbegin', noteHtml);
        // Update counter if exists
        var ctr = document.getElementById('notas-count-' + entityType + '-' + entityId);
        if (ctr) ctr.textContent = (parseInt(ctr.textContent) || 0) + 1;
      }
      showActionFeedback('Nota guardada', 'success');
    } else {
      showActionFeedback(res.message || 'Error al guardar nota', 'error');
    }
  })
  .catch(function() {
    _hideSpinner('qa-nota-spinner', 'qa-nota-submit');
    showActionFeedback('Error de conexion', 'error');
  });
}

// ================================================================
// QUICK SUBTAREA (AJAX intercept)
// ================================================================

function submitQuickSubtarea(formEl) {
  var tareaId = formEl.dataset.tareaId;
  var appUrl  = document.body.dataset.appUrl || '';
  var nombre  = formEl.querySelector('[name="nombre"]');
  var fechaFin = formEl.querySelector('[name="fecha_fin"]');
  var prioridad = formEl.querySelector('[name="prioridad"]');
  var descripcion = formEl.querySelector('[name="descripcion"]');

  if (!nombre || !nombre.value.trim()) {
    showActionFeedback('El nombre de la subtarea es requerido', 'warning');
    if (nombre) nombre.focus();
    return;
  }

  var submitBtn = formEl.querySelector('[type="submit"]');
  if (submitBtn) submitBtn.disabled = true;

  var body = 'csrf_token=' + encodeURIComponent(getCsrfToken()) +
             '&nombre='     + encodeURIComponent(nombre.value.trim()) +
             '&prioridad='  + encodeURIComponent((prioridad ? prioridad.value : 'media')) +
             '&fecha_fin='  + encodeURIComponent((fechaFin ? fechaFin.value : '')) +
             '&descripcion=' + encodeURIComponent((descripcion ? descripcion.value.trim() : ''));

  fetch(appUrl + '/tareas/' + tareaId + '/subtareas', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body
  })
  .then(function(r) {
    if (r.status === 419) {
      console.warn('CSRF token mismatch on submitQuickSubtarea.');
      if (submitBtn) submitBtn.disabled = false;
      showActionFeedback('Error de sesión. Intenta de nuevo.', 'error');
      return Promise.reject('csrf_expired');
    }
    return r.json();
  })
  .then(function(res) {
    refreshCsrfToken(res);
    if (submitBtn) submitBtn.disabled = false;
    if (res.ok && res.subtarea) {
      // Clear form
      formEl.reset();
      // Collapse the form
      var collapseTarget = formEl.closest('.collapse');
      if (collapseTarget) {
        var bsCollapse = bootstrap.Collapse.getInstance(collapseTarget);
        if (bsCollapse) bsCollapse.hide();
      }
      // Append new subtarea to the list
      var subList = document.getElementById('subtareas-list-' + tareaId);
      if (subList) {
        var s = res.subtarea;
        var estadoLbl = { por_hacer: 'Por Hacer', haciendo: 'Haciendo', terminada: 'Terminada', enterado: 'Enterado', ocupado: 'Ocupado', aceptada: 'Aceptada' };
        var html = '<div class="subtarea-item d-flex align-items-center gap-2 py-1 px-2 rounded border-start border-2 border-secondary-subtle mb-1 fade-in" data-subtarea-id="' + s.id + '">' +
          '<span class="badge badge-estado-' + s.estado + ' badge-sm flex-shrink-0">' + (estadoLbl[s.estado] || s.estado) + '</span>' +
          '<span class="flex-grow-1 small">' + escapeHtml(s.nombre) + '</span>' +
          '</div>';
        subList.insertAdjacentHTML('beforeend', html);
        // Update subtarea counter
        var ctr = document.querySelector('[data-tarea-id="' + tareaId + '"] .subtareas-count');
        if (ctr) ctr.textContent = (parseInt(ctr.textContent) || 0) + 1;
      }
      showActionFeedback('Subtarea creada', 'success');
    } else {
      showActionFeedback(res.message || 'Error al crear subtarea', 'error');
    }
  })
  .catch(function() {
    if (submitBtn) submitBtn.disabled = false;
    showActionFeedback('Error de conexion', 'error');
  });
}

// ================================================================
// EVENT DELEGATION — intercept subtarea forms marked with data-ajax-subtarea
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (form && form.matches('[data-ajax-subtarea]')) {
      e.preventDefault();
      submitQuickSubtarea(form);
    }
  });

  // ----------------------------------------------------------------
  // Modal/offcanvas submit buttons (replaces onclick handlers removed
  // from _quick_action_modals.php)
  // ----------------------------------------------------------------
  var submitAssignBtn = document.getElementById('qa-assign-submit');
  if (submitAssignBtn) submitAssignBtn.addEventListener('click', submitQuickAssign);
  var submitEditBtn = document.getElementById('qa-edit-submit');
  if (submitEditBtn) submitEditBtn.addEventListener('click', submitQuickEdit);
  var submitNotaBtn = document.getElementById('qa-nota-submit');
  if (submitNotaBtn) submitNotaBtn.addEventListener('click', submitQuickNota);

  // ----------------------------------------------------------------
  // data-action delegation — replaces all onclick on quick-action
  // buttons (quick-edit, quick-assign, quick-nota) across all views
  // ----------------------------------------------------------------
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.dataset.action;
    if (action === 'quick-edit') {
      var data = {};
      try { data = JSON.parse(btn.dataset.entityData || '{}'); } catch(ex) {}
      openQuickEdit(btn.dataset.entityType, btn.dataset.entityId, data);
    } else if (action === 'quick-assign') {
      openQuickAssign(
        btn.dataset.entityType,
        btn.dataset.entityId,
        btn.dataset.assigneeId,
        btn.dataset.entityName
      );
    } else if (action === 'quick-nota') {
      openQuickNota(
        btn.dataset.entityType,
        btn.dataset.entityId,
        btn.dataset.entityName
      );
    }
  });
});
