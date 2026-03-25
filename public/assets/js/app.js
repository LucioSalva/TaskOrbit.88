/* =============================================
   TaskOrbit - Client JavaScript
   ES6+, No jQuery
   ============================================= */

'use strict';

// ---- APP_URL from data attribute (replaces inline window.APP_URL) ----
window.APP_URL = document.body ? document.body.dataset.appUrl || '' : '';

// ---- Delete Modal — Sync reason field ----
(function initReasonSync() {
  document.addEventListener('DOMContentLoaded', () => {
    const reasonInput = document.getElementById('modal-delete-reason');
    const reasonField = document.getElementById('modal-reason-field');
    if (reasonInput && reasonField) {
      reasonInput.addEventListener('input', () => {
        reasonField.value = reasonInput.value;
      });
    }
  });
})();

// ---- Dark Mode ----
(function initDarkMode() {
  const html  = document.documentElement;
  const saved = localStorage.getItem('taskorbit.theme') || 'light';
  html.setAttribute('data-bs-theme', saved);

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-theme-toggle');
    if (!btn) return;

    updateThemeIcon(btn, saved);

    btn.addEventListener('click', () => {
      const current = html.getAttribute('data-bs-theme');
      const next    = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-bs-theme', next);
      localStorage.setItem('taskorbit.theme', next);
      updateThemeIcon(btn, next);
    });
  });

  function updateThemeIcon(btn, theme) {
    const icon = btn.querySelector('i');
    if (!icon) return;
    if (theme === 'dark') {
      icon.className = 'bi bi-sun-fill';
      btn.title = 'Cambiar a modo claro';
    } else {
      icon.className = 'bi bi-moon-fill';
      btn.title = 'Cambiar a modo oscuro';
    }
  }
})();

// ---- Sidebar Toggle ----
(function initSidebar() {
  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebar-overlay');

    if (!toggleBtn || !sidebar) return;

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('show');
    });

    if (overlay) {
      overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      });
    }
  });
})();

// ---- Bottom Nav Menu (mobile) ----
(function initBottomNav() {
  document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.getElementById('bottom-nav-menu');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!menuBtn || !sidebar) return;

    menuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('show');
    });
  });
})();

// ---- Flash Auto-dismiss (error=8s, success/info=4s) ----
(function initFlash() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flash-alert').forEach(alert => {
      const isError = alert.classList.contains('alert-danger') || alert.classList.contains('alert-warning');
      const delay = isError ? 8000 : 4000;
      setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        if (bsAlert) bsAlert.close();
      }, delay);
    });
  });
})();

// ---- CSRF Token ----
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

// ---- Notifications ----
(function initNotifications() {
  document.addEventListener('DOMContentLoaded', () => {
    const badge   = document.getElementById('notif-badge');
    const list    = document.getElementById('notif-list');
    const markAll = document.getElementById('notif-mark-all');

    if (!badge && !list) return;

    function loadNotifications() {
      fetch(window.APP_URL + '/notificaciones', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(data => {
          if (!data.ok) return;

          // Update badge
          if (badge) {
            if (data.unread > 0) {
              badge.textContent = data.unread > 99 ? '99+' : data.unread;
              badge.style.display = '';
            } else {
              badge.style.display = 'none';
            }
          }

          // Render list
          if (list) {
            if (!data.items || data.items.length === 0) {
              list.innerHTML = '<div class="text-center py-3 text-muted small">Sin notificaciones</div>';
              return;
            }
            list.innerHTML = data.items.map(n => `
              <div class="notif-item ${!n.is_read ? 'unread' : ''}" data-id="${n.id}">
                <div class="fw-semibold">${escapeHtml(n.title)}</div>
                <div class="text-muted">${escapeHtml(n.message)}</div>
                <div class="text-muted mt-1" style="font-size:0.75rem">${formatDate(n.created_at)}</div>
              </div>
            `).join('');

            // Click to mark read
            list.querySelectorAll('.notif-item.unread').forEach(item => {
              item.addEventListener('click', () => {
                const id = item.dataset.id;
                markNotifRead(id, item);
              });
            });
          }
        })
        .catch(() => {});
    }

    function markNotifRead(id, el) {
      fetch(window.APP_URL + `/notificaciones/${id}/leer`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: 'csrf_token=' + encodeURIComponent(getCsrfToken()),
      })
        .then(r => {
          if (r.status === 419) {
            alert('La sesión expiró. Recarga la página e intenta de nuevo.');
            location.reload();
            return Promise.reject('csrf_expired');
          }
          return r.json();
        })
        .then(data => {
          if (data.ok && el) el.classList.remove('unread');
          loadNotifications();
        })
        .catch(() => {});
    }

    if (markAll) {
      markAll.addEventListener('click', e => {
        e.preventDefault();
        fetch(window.APP_URL + '/notificaciones/leer-todas', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: 'csrf_token=' + encodeURIComponent(getCsrfToken()),
        })
          .then(r => {
            if (r.status === 419) {
              alert('La sesión expiró. Recarga la página e intenta de nuevo.');
              location.reload();
              return Promise.reject('csrf_expired');
            }
            return r.json();
          })
          .then(() => loadNotifications())
          .catch(() => {});
      });
    }

    loadNotifications();
    setInterval(loadNotifications, 60000); // Refresh every minute
  });
})();

// ---- Anti Double Submit for standard POST forms ----
(function initAntiDoubleSubmit() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[method="POST"]').forEach(form => {
      // Skip forms with AJAX handlers (onsubmit=changeEstado, data-ajax-subtarea, notas forms)
      if (form.getAttribute('onsubmit') ||
          form.hasAttribute('data-ajax-subtarea') ||
          form.classList.contains('notas-add-form') ||
          form.classList.contains('notas-update-form') ||
          form.classList.contains('nota-pin-form') ||
          form.classList.contains('nota-delete-form') ||
          form.id === 'form-confirm-delete') return;

      form.addEventListener('submit', function(e) {
        const btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        if (btn.dataset.submitting === 'true') {
          e.preventDefault();
          return;
        }
        btn.dataset.submitting = 'true';
        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + origHtml;
        // Restore after 6s as safety net
        setTimeout(() => {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          btn.dataset.submitting = '';
        }, 6000);
      });
    });
  });
})();

// ---- Date Validation for forms with fecha_inicio / fecha_fin ----
(function initDateValidation() {
  document.addEventListener('DOMContentLoaded', () => {
    // Find all forms with both date fields
    document.querySelectorAll('form').forEach(form => {
      const inicio = form.querySelector('[name="fecha_inicio"]');
      const fin    = form.querySelector('[name="fecha_fin"]');
      if (!inicio || !fin) return;

      function validateDates() {
        // Remove previous warnings
        form.querySelectorAll('.date-validation-warning').forEach(w => w.remove());
        fin.classList.remove('is-invalid');

        if (!inicio.value || !fin.value) return;

        if (fin.value < inicio.value) {
          fin.classList.add('is-invalid');
          const warn = document.createElement('div');
          warn.className = 'date-validation-warning text-danger small mt-1';
          warn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>La fecha de fin no puede ser anterior a la fecha de inicio.';
          fin.parentNode.appendChild(warn);
        }

        // Warn if fecha_fin is in the past (non-blocking)
        const today = new Date();
        today.setHours(0,0,0,0);
        const finDate = new Date(fin.value + 'T00:00:00');
        if (finDate < today && fin.value >= inicio.value) {
          const pastWarn = document.createElement('div');
          pastWarn.className = 'date-validation-warning text-warning small mt-1';
          pastWarn.innerHTML = '<i class="bi bi-info-circle me-1"></i>La fecha de fin esta en el pasado.';
          fin.parentNode.appendChild(pastWarn);
        }
      }

      inicio.addEventListener('change', validateDates);
      fin.addEventListener('change', validateDates);

      // Block submit if dates are invalid
      form.addEventListener('submit', function(e) {
        if (inicio.value && fin.value && fin.value < inicio.value) {
          e.preventDefault();
          fin.focus();
          if (typeof showActionFeedback === 'function') {
            showActionFeedback('La fecha de fin no puede ser anterior a la fecha de inicio.', 'error');
          }
        }
      });
    });
  });
})();

// ---- Unsaved Changes Warning (beforeunload) ----
(function initUnsavedWarning() {
  document.addEventListener('DOMContentLoaded', () => {
    // Only apply to create/edit forms (not filters, not inline AJAX)
    const editForms = document.querySelectorAll('form[method="POST"][action*="/crear"], form[method="POST"][action*="/proyectos"][action$="/editar"], form[method="POST"][action*="/tareas"]');
    editForms.forEach(form => {
      if (form.hasAttribute('data-ajax-subtarea') || form.getAttribute('onsubmit')) return;
      // Check if this is a proper create/edit form (has name field)
      if (!form.querySelector('[name="nombre"]')) return;

      let isDirty = false;
      const inputs = form.querySelectorAll('input, textarea, select');

      inputs.forEach(input => {
        input.addEventListener('input', () => { isDirty = true; });
        input.addEventListener('change', () => { isDirty = true; });
      });

      form.addEventListener('submit', () => { isDirty = false; });

      window.addEventListener('beforeunload', (e) => {
        if (isDirty) {
          e.preventDefault();
          e.returnValue = '';
        }
      });
    });
  });
})();

// ---- Estado Quick Change (with loading feedback) ----
function changeEstado(formEl) {
  const url    = formEl.action;
  const data   = new FormData(formEl);
  const params = new URLSearchParams(data).toString();
  const submitBtn = formEl.querySelector('[type="submit"]');
  const estado = formEl.querySelector('[name="estado"]')?.value || '';

  // Confirmation for critical state transitions
  if (estado === 'terminada' || estado === 'aceptada') {
    const labels = { terminada: 'Terminada', aceptada: 'Aceptada' };
    if (!confirm('Cambiar estado a "' + labels[estado] + '". Confirmar?')) {
      return false;
    }
  }

  // Intercept "terminada": check for evidencias first
  if (estado === 'terminada' && typeof window.checkEvidenciasBeforeTerminada === 'function') {
    // Detect entity type and ID from the form action URL or parent card
    const entityInfo = detectEntityFromForm(formEl);
    if (entityInfo) {
      // Disable button while checking
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
      }
      window.checkEvidenciasBeforeTerminada(entityInfo.tipo, entityInfo.id)
        .then(function(result) {
          if (!result.hasEvidencia) {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.style.opacity = '';
            }
            showEvidenciaRequiredModal(entityInfo.tipo, entityInfo.id);
            return;
          }
          // Has evidence, proceed with actual state change
          doChangeEstado(formEl, url, params, submitBtn, estado);
        });
      return false;
    }
  }

  doChangeEstado(formEl, url, params, submitBtn, estado);
  return false;
}

function detectEntityFromForm(formEl) {
  const action = formEl.action || '';
  // Match /subtareas/{id}/estado
  let m = action.match(/\/subtareas\/(\d+)\/estado/);
  if (m) return { tipo: 'subtarea', id: parseInt(m[1], 10) };
  // Match /tareas/{id}/estado
  m = action.match(/\/tareas\/(\d+)\/estado/);
  if (m) return { tipo: 'tarea', id: parseInt(m[1], 10) };
  // Match /proyectos/{id}/estado
  m = action.match(/\/proyectos\/(\d+)\/estado/);
  if (m) return { tipo: 'proyecto', id: parseInt(m[1], 10) };
  return null;
}

function showEvidenciaRequiredModal(tipo, entidadId) {
  // Check if modal already exists, remove it
  let existing = document.getElementById('modal-evidencia-required');
  if (existing) existing.remove();

  const tipoLabels = { proyecto: 'proyecto', tarea: 'tarea', subtarea: 'subtarea' };
  const tipoLabel = tipoLabels[tipo] || tipo;

  const modalHtml = `
    <div class="modal fade" id="modal-evidencia-required" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning border-top border-3">
          <div class="modal-header bg-warning bg-opacity-10">
            <h5 class="modal-title text-warning">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>Evidencia requerida
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Debes adjuntar al menos una evidencia (PDF o PNG) antes de marcar esta ${escapeHtml(tipoLabel)} como <strong>Terminada</strong>.</p>
            <div id="modal-evidencia-upload-zone" class="mt-3">
              <form class="evidencia-upload-form-modal" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                <input type="hidden" name="tipo_entidad" value="${escapeHtml(tipo)}">
                <input type="hidden" name="entidad_id" value="${entidadId}">
                <div class="d-flex align-items-center gap-2">
                  <input type="file" name="archivo" accept=".pdf,.png" class="form-control form-control-sm flex-fill">
                  <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">
                    <i class="bi bi-upload me-1"></i>Subir
                  </button>
                </div>
                <div class="form-text" style="font-size:.7rem">Solo PDF y PNG, max 5 MB</div>
              </form>
              <div class="modal-evidencia-feedback mt-2" style="display:none"></div>
              <div class="modal-evidencia-progress mt-1" style="display:none">
                <div class="progress" style="height:4px">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>`;

  document.body.insertAdjacentHTML('beforeend', modalHtml);

  const modalEl = document.getElementById('modal-evidencia-required');
  const bsModal = new bootstrap.Modal(modalEl);
  bsModal.show();

  // Handle upload inside modal
  const modalForm = modalEl.querySelector('.evidencia-upload-form-modal');
  const modalFeedback = modalEl.querySelector('.modal-evidencia-feedback');
  const modalProgress = modalEl.querySelector('.modal-evidencia-progress');
  const modalProgressBar = modalProgress ? modalProgress.querySelector('.progress-bar') : null;

  modalForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = modalForm.querySelector('input[type="file"]');
    const submitBtn = modalForm.querySelector('button[type="submit"]');

    if (!fileInput || !fileInput.files.length) {
      showModalFeedback(modalFeedback, 'Selecciona un archivo.', 'warning');
      return;
    }

    var file = fileInput.files[0];
    if (file.size > 5 * 1024 * 1024) {
      showModalFeedback(modalFeedback, 'El archivo supera 5 MB.', 'danger');
      return;
    }
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'pdf' && ext !== 'png') {
      showModalFeedback(modalFeedback, 'Solo PDF y PNG.', 'danger');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subiendo...';
    if (modalProgress) modalProgress.style.display = '';

    var formData = new FormData(modalForm);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.APP_URL + '/evidencias/subir', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());

    xhr.upload.addEventListener('progress', function(ev) {
      if (ev.lengthComputable && modalProgressBar) {
        modalProgressBar.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
      }
    });

    xhr.addEventListener('load', function() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Subir';
      if (modalProgress) modalProgress.style.display = 'none';

      try {
        var res = JSON.parse(xhr.responseText);
        if (res.ok) {
          showModalFeedback(modalFeedback, 'Evidencia subida. Ahora puedes marcar como terminada.', 'success');
          // Refresh any evidencias panel on the page
          document.querySelectorAll('.evidencias-panel[data-tipo="' + tipo + '"][data-entidad-id="' + entidadId + '"]').forEach(function(panel) {
            var countEl = panel.querySelector('.evidencias-count');
            if (countEl) countEl.textContent = parseInt(countEl.textContent || '0', 10) + 1;
          });
          // Close modal after 1.5s
          setTimeout(function() {
            bsModal.hide();
            modalEl.remove();
          }, 1500);
        } else {
          showModalFeedback(modalFeedback, res.error || 'Error al subir.', 'danger');
        }
      } catch(e) {
        showModalFeedback(modalFeedback, 'Error de conexion.', 'danger');
      }
    });

    xhr.addEventListener('error', function() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Subir';
      if (modalProgress) modalProgress.style.display = 'none';
      showModalFeedback(modalFeedback, 'Error de conexion.', 'danger');
    });

    xhr.send(formData);
  });

  // Cleanup on hide
  modalEl.addEventListener('hidden.bs.modal', function() {
    modalEl.remove();
  });
}

function showModalFeedback(el, msg, type) {
  if (!el) return;
  el.style.display = '';
  el.className = 'modal-evidencia-feedback mt-2 small alert alert-' + (type === 'danger' ? 'danger' : type === 'warning' ? 'warning' : 'success') + ' py-1 px-2 mb-0';
  el.textContent = msg;
}

function doChangeEstado(formEl, url, params, submitBtn, estado) {
  // Loading state
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.dataset.origText = submitBtn.textContent;
    submitBtn.style.opacity = '0.6';
  }

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: params,
  })
    .then(r => {
      if (r.status === 419) {
        alert('La sesión expiró. Recarga la página e intenta de nuevo.');
        location.reload();
        return Promise.reject('csrf_expired');
      }
      return r.json();
    })
    .then(res => {
      // Restore button
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '';
      }

      if (res.ok) {
        // Show toast feedback
        if (typeof showActionFeedback === 'function') {
          showActionFeedback('Estado actualizado a "' + estadoLabel(res.estado) + '"', 'success');
        }

        // Update the card that owns this form
        const card = formEl.closest('[data-tarea-id], [data-subtarea-id], [data-proyecto-id]');
        if (card) {
          const badgeEl = card.querySelector('.estado-badge');
          if (badgeEl) {
            badgeEl.className = `badge estado-badge badge-estado-${res.estado}`;
            badgeEl.textContent = estadoLabel(res.estado);
          }
          formEl.closest('.estado-btn-group')?.querySelectorAll('.btn').forEach(btn => {
            btn.classList.toggle('active-estado', btn.dataset.estado === res.estado);
          });
        }

        // If a subtask changed, update the parent task badge
        if (res.tarea_estado && res.tarea_id) {
          const tareaCard = document.querySelector(`[data-tarea-id="${res.tarea_id}"]`);
          if (tareaCard) {
            const tareaBadge = tareaCard.querySelector('.estado-badge');
            if (tareaBadge) {
              tareaBadge.className = `badge estado-badge badge-estado-${res.tarea_estado}`;
              tareaBadge.textContent = estadoLabel(res.tarea_estado);
            }
          }
        }

        // If tarea or subtarea changed, update the project badge
        if (res.proyecto_estado) {
          const proyBadge = document.getElementById('proyecto-estado-badge');
          if (proyBadge) {
            proyBadge.className = `badge estado-badge badge-estado-${res.proyecto_estado}`;
            proyBadge.textContent = estadoLabel(res.proyecto_estado);
          }
          if (res.proyecto_id) {
            const proyCard = document.querySelector(`[data-proyecto-id="${res.proyecto_id}"]`);
            if (proyCard) {
              const proyCardBadge = proyCard.querySelector('.estado-badge');
              if (proyCardBadge) {
                proyCardBadge.className = `badge estado-badge badge-estado-${res.proyecto_estado}`;
                proyCardBadge.textContent = estadoLabel(res.proyecto_estado);
              }
              proyCard.querySelectorAll('.estado-btn-group .btn').forEach(btn => {
                btn.classList.toggle('active-estado', btn.dataset.estado === res.proyecto_estado);
              });
            }
          }
        }

        // Refresh evidencias panels on the page after state change
        document.querySelectorAll('.evidencias-panel').forEach(function(panel) {
          var pTipo = panel.dataset.tipo;
          var pId   = parseInt(panel.dataset.entidadId, 10);
          if (pTipo && pId) {
            // Re-fetch just to keep counts updated
            fetch(window.APP_URL + '/evidencias/entidad?tipo=' + encodeURIComponent(pTipo) + '&id=' + pId, {
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); }).then(function(data) {
              if (data.ok) {
                var countEl = panel.querySelector('.evidencias-count');
                if (countEl) countEl.textContent = data.total;
              }
            }).catch(function() {});
          }
        });
      } else {
        // Handle "requires_evidencia" error from backend
        if (res.requires_evidencia) {
          const entityInfo = detectEntityFromForm(formEl);
          if (entityInfo) {
            showEvidenciaRequiredModal(entityInfo.tipo, entityInfo.id);
            return;
          }
        }
        if (typeof showActionFeedback === 'function') {
          showActionFeedback(res.message || 'Error al cambiar estado', 'error');
        } else {
          alert(res.message || 'Error al cambiar estado');
        }
      }
    })
    .catch(() => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '';
      }
      if (typeof showActionFeedback === 'function') {
        showActionFeedback('Error de conexion. Intenta de nuevo.', 'error');
      } else {
        formEl.submit();
      }
    });
}

// ---- Delete Confirmation Modal (enhanced) ----
(function initDeleteConfirm() {
  document.addEventListener('DOMContentLoaded', () => {
    const modal     = document.getElementById('modal-confirm-delete');
    const formEl    = document.getElementById('form-confirm-delete');
    const titleEl   = document.getElementById('modal-delete-title');
    const msgEl     = document.getElementById('modal-delete-msg');
    const reasonEl  = document.getElementById('modal-delete-reason');
    const previewEl = document.getElementById('modal-delete-preview');

    if (!modal) return;

    document.querySelectorAll('[data-delete-url]').forEach(btn => {
      btn.addEventListener('click', () => {
        const url    = btn.dataset.deleteUrl;
        const title  = btn.dataset.deleteTitle || 'Confirmar eliminacion';
        const msg    = btn.dataset.deleteMsg   || 'Esta accion no se puede deshacer.';
        const reason = btn.dataset.showReason  === 'true';
        const previewUrl = btn.dataset.deletePreviewUrl || '';

        if (formEl)  formEl.action = url;
        if (titleEl) titleEl.textContent = title;
        if (msgEl)   msgEl.innerHTML = '<i class="bi bi-exclamation-triangle text-danger me-1"></i>' + escapeHtml(msg);
        if (reasonEl) {
          reasonEl.closest('.mb-3').style.display = reason ? '' : 'none';
          reasonEl.value = '';
          if (reason) reasonEl.setAttribute('placeholder', 'Ej: Ya no se necesita, duplicado, error...');
        }

        // Load cascade preview if available
        if (previewEl) {
          previewEl.innerHTML = '';
          if (previewUrl) {
            loadDeletePreview(previewUrl, previewEl);
          }
        }

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      });
    });
  });
})();

// ---- Preview delete (show counts) ----
function loadDeletePreview(url, containerEl) {
  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      if (!containerEl) return;
      containerEl.innerHTML = `
        <div class="alert alert-warning mt-2 mb-0 small">
          <strong>Se eliminarán en cascada:</strong>
          <ul class="mb-0 mt-1">
            <li>${data.tareas || 0} tarea(s)</li>
            <li>${data.subtareas || 0} subtarea(s)</li>
            <li>${data.notas || 0} nota(s)</li>
          </ul>
        </div>`;
    })
    .catch(() => {});
}

// ---- Helpers ----
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
  } catch {
    return dateStr;
  }
}

function estadoLabel(estado) {
  const labels = {
    por_hacer: 'Por Hacer',
    haciendo:  'Haciendo',
    terminada: 'Terminada',
    enterado:  'Enterado',
    ocupado:   'Ocupado',
    aceptada:  'Aceptada',
  };
  return labels[estado] || estado;
}

// ---- Bootstrap Tooltips init ----
(function initTooltips() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  });
})();

// ---- Character Counter for textareas with maxlength ----
(function initCharCounters() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
      const max = parseInt(textarea.getAttribute('maxlength'), 10);
      if (!max || max < 100) return; // Only for substantial fields
      const counter = document.createElement('div');
      counter.className = 'form-text text-end char-counter';
      counter.style.fontSize = '0.7rem';
      counter.textContent = '0 / ' + max;
      textarea.parentNode.insertBefore(counter, textarea.nextSibling);

      function update() {
        const len = textarea.value.length;
        counter.textContent = len + ' / ' + max;
        if (len > max * 0.9) {
          counter.classList.add('text-warning');
          counter.classList.remove('text-danger');
        } else if (len >= max) {
          counter.classList.add('text-danger');
          counter.classList.remove('text-warning');
        } else {
          counter.classList.remove('text-warning', 'text-danger');
        }
      }
      textarea.addEventListener('input', update);
      update();
    });
  });
})();
