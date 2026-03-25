/* =============================================
   TaskOrbit - Evidencias Panel JS
   ============================================= */
'use strict';

(function() {

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.evidencias-panel').forEach(function(panelEl) {
      initEvidenciasPanel(panelEl);
    });
  });

  function initEvidenciasPanel(panelEl) {
    var tipo      = panelEl.dataset.tipo;
    var entidadId = parseInt(panelEl.dataset.entidadId, 10);
    var canUpload = panelEl.dataset.canUpload === '1';
    var canDelete = panelEl.dataset.canDelete === '1';

    // Upload form handler
    var form = panelEl.querySelector('.evidencia-upload-form');
    if (form && canUpload) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        handleUpload(panelEl, form, tipo, entidadId);
      });
    }

    // Delete buttons
    if (canDelete) {
      bindDeleteButtons(panelEl, tipo, entidadId);
    }
  }

  function handleUpload(panelEl, form, tipo, entidadId) {
    var fileInput = form.querySelector('input[type="file"]');
    var submitBtn = form.querySelector('button[type="submit"]');
    var feedback  = panelEl.querySelector('.evidencia-feedback');
    var progress  = panelEl.querySelector('.evidencia-progress');
    var progressBar = progress ? progress.querySelector('.progress-bar') : null;

    if (!fileInput || !fileInput.files.length) {
      showFeedback(feedback, 'Selecciona un archivo antes de subir.', 'warning');
      return;
    }

    var file = fileInput.files[0];

    // Client-side size check
    if (file.size > 5 * 1024 * 1024) {
      showFeedback(feedback, 'El archivo supera el tamano maximo de 5 MB.', 'danger');
      return;
    }

    // Client-side extension check
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'pdf' && ext !== 'png') {
      showFeedback(feedback, 'Solo se permiten archivos PDF y PNG.', 'danger');
      return;
    }

    // Disable button, show progress
    submitBtn.disabled = true;
    var origHtml = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subiendo...';
    if (progress) progress.style.display = '';
    if (feedback) feedback.style.display = 'none';

    var formData = new FormData(form);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.APP_URL + '/evidencias/subir', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());

    xhr.upload.addEventListener('progress', function(e) {
      if (e.lengthComputable && progressBar) {
        var pct = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = pct + '%';
      }
    });

    xhr.addEventListener('load', function() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origHtml;
      if (progress) progress.style.display = 'none';
      if (progressBar) progressBar.style.width = '0%';

      if (xhr.status === 419) {
        alert('La sesión expiró. Recarga la página e intenta de nuevo.');
        location.reload();
        return;
      }

      try {
        var res = JSON.parse(xhr.responseText);
        if (res.ok) {
          showFeedback(feedback, 'Evidencia subida exitosamente.', 'success');
          fileInput.value = '';
          // Reload list
          refreshEvidenciasList(panelEl, tipo, entidadId);
        } else {
          showFeedback(feedback, res.error || 'Error al subir evidencia.', 'danger');
        }
      } catch (e) {
        showFeedback(feedback, 'Error de conexion al subir evidencia.', 'danger');
      }
    });

    xhr.addEventListener('error', function() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origHtml;
      if (progress) progress.style.display = 'none';
      showFeedback(feedback, 'Error de conexion. Intenta de nuevo.', 'danger');
    });

    xhr.send(formData);
  }

  function refreshEvidenciasList(panelEl, tipo, entidadId) {
    var listEl  = panelEl.querySelector('.evidencias-list');
    var countEl = panelEl.querySelector('.evidencias-count');

    fetch(window.APP_URL + '/evidencias/entidad?tipo=' + encodeURIComponent(tipo) + '&id=' + entidadId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) return;

      if (countEl) countEl.textContent = data.total;

      if (!data.evidencias || data.evidencias.length === 0) {
        listEl.innerHTML = '<div class="evidencias-empty text-muted small py-2">Sin evidencias adjuntas.</div>';
        return;
      }

      var canDelete = panelEl.dataset.canDelete === '1';
      var terminada = panelEl.dataset.entityTerminada === '1';

      listEl.innerHTML = data.evidencias.map(function(ev) {
        var sizeKB = Math.round(ev.peso_bytes / 1024);
        var dateStr = '';
        try {
          var d = new Date(ev.created_at);
          dateStr = d.toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' }) + ' ' +
                    d.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });
        } catch(e) { dateStr = ev.created_at; }

        var deleteBtn = '';
        if (canDelete && !terminada) {
          deleteBtn = '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 btn-eliminar-evidencia" data-evidencia-id="' + ev.id + '" title="Eliminar"><i class="bi bi-trash"></i></button>';
        }

        return '<div class="evidencia-item d-flex align-items-center gap-2 py-1" data-evidencia-id="' + ev.id + '">' +
          '<span class="evidencia-badge-' + escapeHtml(ev.extension) + ' badge">' + ev.extension.toUpperCase() + '</span>' +
          '<div class="flex-fill min-w-0">' +
            '<div class="text-truncate small fw-medium">' + escapeHtml(ev.nombre_original) + '</div>' +
            '<div class="text-muted" style="font-size:.7rem">' + escapeHtml(ev.subido_por_nombre) + ' &middot; ' + dateStr + ' &middot; ' + sizeKB + ' KB</div>' +
          '</div>' +
          '<a href="' + window.APP_URL + '/evidencias/' + ev.id + '/descargar" class="btn btn-outline-primary btn-sm py-0 px-1" title="Descargar"><i class="bi bi-download"></i></a>' +
          deleteBtn +
        '</div>';
      }).join('');

      bindDeleteButtons(panelEl, tipo, entidadId);
    })
    .catch(function() {});
  }

  function bindDeleteButtons(panelEl, tipo, entidadId) {
    panelEl.querySelectorAll('.btn-eliminar-evidencia').forEach(function(btn) {
      // Remove old listeners by cloning
      var newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);

      newBtn.addEventListener('click', function() {
        var evidId = newBtn.dataset.evidenciaId;
        if (!confirm('Eliminar esta evidencia? Esta accion no se puede deshacer.')) return;

        fetch(window.APP_URL + '/evidencias/' + evidId + '/eliminar', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken()
          },
          body: 'csrf_token=' + encodeURIComponent(getCsrfToken())
        })
        .then(function(r) {
          if (r.status === 419) {
            alert('La sesión expiró. Recarga la página e intenta de nuevo.');
            location.reload();
            return Promise.reject('csrf_expired');
          }
          return r.json();
        })
        .then(function(res) {
          if (res.ok) {
            refreshEvidenciasList(panelEl, tipo, entidadId);
            if (typeof showActionFeedback === 'function') {
              showActionFeedback('Evidencia eliminada.', 'success');
            }
          } else {
            if (typeof showActionFeedback === 'function') {
              showActionFeedback(res.error || 'Error al eliminar.', 'error');
            } else {
              alert(res.error || 'Error al eliminar.');
            }
          }
        })
        .catch(function() {
          alert('Error de conexion.');
        });
      });
    });
  }

  function showFeedback(el, msg, type) {
    if (!el) return;
    el.style.display = '';
    el.className = 'evidencia-feedback mt-1 small text-' + (type === 'danger' ? 'danger' : type === 'warning' ? 'warning' : 'success');
    el.innerHTML = '<i class="bi bi-' + (type === 'success' ? 'check-circle' : 'exclamation-triangle') + ' me-1"></i>' + escapeHtml(msg);
    if (type === 'success') {
      setTimeout(function() { el.style.display = 'none'; }, 4000);
    }
  }

  // Expose globally for use by changeEstado interceptor
  window.checkEvidenciasBeforeTerminada = function(tipo, entidadId) {
    return fetch(window.APP_URL + '/evidencias/entidad?tipo=' + encodeURIComponent(tipo) + '&id=' + entidadId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ok && data.total > 0) {
        return { hasEvidencia: true, total: data.total };
      }
      return { hasEvidencia: false, total: 0 };
    })
    .catch(function() {
      return { hasEvidencia: false, total: 0 };
    });
  };

})();
