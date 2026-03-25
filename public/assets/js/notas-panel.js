/* =============================================
   TaskOrbit — Notas Panel Module
   Requires: app.js (getCsrfToken, escapeHtml)
            acciones-rapidas.js (showActionFeedback)
   ============================================= */
'use strict';

(function() {

  // -------------------------------------------------------
  // Helpers
  // -------------------------------------------------------
  function getAppUrl() {
    return document.body.dataset.appUrl || '';
  }

  function buildNoteHtml(nota, panelEl) {
    var appUrl   = getAppUrl();
    var isPinned = nota.is_pinned;
    var esAuto   = nota.tipo === 'auto' || nota.tipo === 'sistema';

    var tipoLabel = { personal: 'Personal', actividad: 'Actividad', auto: 'Sistema', sistema: 'Sistema' };
    var tipoClass = { personal: 'bg-secondary', actividad: 'bg-primary', auto: 'bg-info text-dark', sistema: 'bg-info text-dark' };

    var pinBtn = nota.can_pin
      ? '<form method="POST" action="' + appUrl + '/notas/' + nota.id + '/pin" class="nota-pin-form d-inline" data-nota-id="' + nota.id + '">' +
        '<input type="hidden" name="csrf_token" value="' + escapeHtml(getCsrfToken()) + '">' +
        '<button type="submit" class="btn btn-link btn-sm p-0 ' + (isPinned ? 'text-warning' : 'text-muted') + '" title="' + (isPinned ? 'Desfijar' : 'Fijar') + '" style="font-size:0.75rem">' +
        '<i class="bi ' + (isPinned ? 'bi-pin-fill' : 'bi-pin') + '"></i></button></form>'
      : '';

    var editBtn = nota.can_edit && !esAuto
      ? '<button type="button" class="btn btn-link btn-sm p-0 text-muted nota-edit-btn" data-nota-id="' + nota.id + '" data-titulo="' + escapeHtml(nota.titulo || '') + '" data-contenido="' + escapeHtml(nota.contenido || '') + '" title="Editar" style="font-size:0.75rem"><i class="bi bi-pencil"></i></button>'
      : '';

    var delBtn = nota.can_delete
      ? '<form method="POST" action="' + appUrl + '/notas/' + nota.id + '/eliminar" class="nota-delete-form d-inline" data-nota-id="' + nota.id + '">' +
        '<input type="hidden" name="csrf_token" value="' + escapeHtml(getCsrfToken()) + '">' +
        '<button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="Eliminar" style="font-size:0.75rem"><i class="bi bi-trash"></i></button></form>'
      : '';

    var editForm = nota.can_edit && !esAuto
      ? '<div class="nota-edit-form d-none mt-2" data-nota-id="' + nota.id + '">' +
        '<form method="POST" action="' + appUrl + '/notas/' + nota.id + '/editar" class="nota-update-form" data-nota-id="' + nota.id + '">' +
        '<input type="hidden" name="csrf_token" value="' + escapeHtml(getCsrfToken()) + '">' +
        '<input type="text" name="titulo" class="form-control form-control-sm mb-1" placeholder="Título (opcional)" maxlength="200" value="' + escapeHtml(nota.titulo || '') + '">' +
        '<textarea name="contenido" class="form-control form-control-sm mb-1" rows="2" required>' + escapeHtml(nota.contenido || '') + '</textarea>' +
        '<div class="d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm" style="font-size:0.75rem">Guardar</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-sm nota-cancel-edit" data-nota-id="' + nota.id + '" style="font-size:0.75rem">Cancelar</button></div>' +
        '</form></div>'
      : '';

    var borderClass = isPinned ? 'border-warning bg-warning-subtle' : 'border-secondary-subtle';

    return '<div class="nota-item rounded border-start border-2 ' + borderClass + ' ps-2 py-2 mb-2 fade-in" id="nota-item-' + nota.id + '" data-nota-id="' + nota.id + '">' +
      '<div class="d-flex align-items-start justify-content-between gap-1 mb-1">' +
      '<div class="flex-fill" style="min-width:0">' +
      (isPinned ? '<i class="bi bi-pin-fill text-warning me-1"></i>' : '') +
      '<span class="badge ' + (tipoClass[nota.tipo] || 'bg-primary') + ' badge-sm" style="font-size:0.65rem">' + escapeHtml(tipoLabel[nota.tipo] || 'Actividad') + '</span>' +
      (nota.titulo ? '<strong class="small ms-1 nota-titulo-display">' + escapeHtml(nota.titulo) + '</strong>' : '') +
      '</div>' +
      '<div class="d-flex gap-1 flex-shrink-0">' + pinBtn + editBtn + delBtn + '</div>' +
      '</div>' +
      '<div class="nota-contenido-display small">' + escapeHtml(nota.contenido || '').replace(/\n/g, '<br>') + '</div>' +
      editForm +
      '<div class="text-muted mt-1" style="font-size:0.7rem"><i class="bi bi-person me-1"></i>' + escapeHtml(nota.autor || 'Sistema') + ' &bull; ' + escapeHtml(nota.created_at || '') + '</div>' +
      '</div>';
  }

  // -------------------------------------------------------
  // Add nota form (AJAX submit)
  // -------------------------------------------------------
  function handleAddForm(formEl) {
    formEl.addEventListener('submit', function(e) {
      e.preventDefault();
      var contenidoEl = formEl.querySelector('[name="contenido"]');
      if (!contenidoEl || !contenidoEl.value.trim()) {
        if (contenidoEl) contenidoEl.classList.add('is-invalid');
        return;
      }
      if (contenidoEl) contenidoEl.classList.remove('is-invalid');

      var spinner = formEl.querySelector('.nota-spinner');
      var submitBtn = formEl.querySelector('[type="submit"]');
      if (spinner) spinner.classList.remove('d-none');
      if (submitBtn) submitBtn.disabled = true;

      var body = new URLSearchParams(new FormData(formEl)).toString();

      fetch(getAppUrl() + '/notas', {
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
          alert('La sesión expiró. Recarga la página e intenta de nuevo.');
          location.reload();
          return Promise.reject('csrf_expired');
        }
        return r.json();
      })
      .then(function(res) {
        if (spinner) spinner.classList.add('d-none');
        if (submitBtn) submitBtn.disabled = false;

        if (res.ok && res.nota) {
          formEl.reset();
          var listId  = formEl.dataset.listId;
          var countId = formEl.dataset.countId;
          var listEl  = listId ? document.getElementById(listId) : null;
          var countEl = countId ? document.getElementById(countId) : null;

          if (listEl) {
            // Remove empty message if present
            var emptyMsg = listEl.querySelector('.notas-empty-msg');
            if (emptyMsg) emptyMsg.remove();

            // Prepend new nota
            listEl.insertAdjacentHTML('afterbegin', buildNoteHtml(res.nota, null));
            // Re-bind events for the new note
            var newItem = listEl.querySelector('#nota-item-' + res.nota.id);
            if (newItem) bindNoteEvents(newItem);
          }
          if (countEl) countEl.textContent = (parseInt(countEl.textContent) || 0) + 1;

          if (typeof showActionFeedback === 'function') showActionFeedback('Nota guardada', 'success');
        } else {
          if (typeof showActionFeedback === 'function') showActionFeedback(res.message || 'Error al guardar nota', 'error');
        }
      })
      .catch(function() {
        if (spinner) spinner.classList.add('d-none');
        if (submitBtn) submitBtn.disabled = false;
        if (typeof showActionFeedback === 'function') showActionFeedback('Error de conexión', 'error');
      });
    });
  }

  // -------------------------------------------------------
  // Per-note events (edit, delete, pin)
  // -------------------------------------------------------
  function bindNoteEvents(itemEl) {
    // Edit button
    var editBtn = itemEl.querySelector('.nota-edit-btn');
    if (editBtn) {
      editBtn.addEventListener('click', function() {
        var notaId  = editBtn.dataset.notaId;
        var editDiv = itemEl.querySelector('.nota-edit-form[data-nota-id="' + notaId + '"]');
        var display = itemEl.querySelector('.nota-contenido-display');
        if (editDiv) editDiv.classList.remove('d-none');
        if (display) display.classList.add('d-none');
        editBtn.classList.add('d-none');
      });
    }

    // Cancel edit
    itemEl.querySelectorAll('.nota-cancel-edit').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var notaId  = btn.dataset.notaId;
        var editDiv = itemEl.querySelector('.nota-edit-form[data-nota-id="' + notaId + '"]');
        var display = itemEl.querySelector('.nota-contenido-display');
        var editBtnEl = itemEl.querySelector('.nota-edit-btn');
        if (editDiv) editDiv.classList.add('d-none');
        if (display) display.classList.remove('d-none');
        if (editBtnEl) editBtnEl.classList.remove('d-none');
      });
    });

    // Update form submit
    itemEl.querySelectorAll('.nota-update-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var notaId     = form.dataset.notaId;
        var contenidoEl = form.querySelector('[name="contenido"]');
        if (!contenidoEl || !contenidoEl.value.trim()) {
          if (contenidoEl) contenidoEl.classList.add('is-invalid');
          return;
        }

        var body = new URLSearchParams(new FormData(form)).toString();

        fetch(getAppUrl() + '/notas/' + notaId + '/editar', {
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
            alert('La sesión expiró. Recarga la página e intenta de nuevo.');
            location.reload();
            return Promise.reject('csrf_expired');
          }
          return r.json();
        })
        .then(function(res) {
          if (res.ok) {
            var tituloEl   = form.querySelector('[name="titulo"]');
            var newTitulo  = tituloEl ? tituloEl.value.trim() : '';
            var newContent = contenidoEl.value.trim();

            // Update display
            var displayEl = itemEl.querySelector('.nota-contenido-display');
            if (displayEl) displayEl.innerHTML = escapeHtml(newContent).replace(/\n/g, '<br>');
            var tituloDisplay = itemEl.querySelector('.nota-titulo-display');
            if (tituloDisplay) tituloDisplay.textContent = newTitulo;

            // Close edit form
            var editDiv = itemEl.querySelector('.nota-edit-form[data-nota-id="' + notaId + '"]');
            if (editDiv) editDiv.classList.add('d-none');
            if (displayEl) displayEl.classList.remove('d-none');
            var editBtnEl = itemEl.querySelector('.nota-edit-btn');
            if (editBtnEl) editBtnEl.classList.remove('d-none');

            if (typeof showActionFeedback === 'function') showActionFeedback('Nota actualizada', 'success');
          } else {
            if (typeof showActionFeedback === 'function') showActionFeedback(res.message || 'Error al actualizar', 'error');
          }
        })
        .catch(function() {
          if (typeof showActionFeedback === 'function') showActionFeedback('Error de conexión', 'error');
        });
      });
    });

    // Delete form
    itemEl.querySelectorAll('.nota-delete-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!confirm('Eliminar esta nota? Esta accion no se puede deshacer.')) return;
        var notaId = form.dataset.notaId;

        fetch(getAppUrl() + '/notas/' + notaId + '/eliminar', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
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
            // Find parent panel to update counter
            var panel   = itemEl.closest('.notas-panel');
            var listEl  = itemEl.closest('.notas-list');
            var countEl = panel ? panel.querySelector('[id^="notas-count-"]') : null;

            itemEl.remove();

            if (countEl) {
              var cur = parseInt(countEl.textContent) || 0;
              countEl.textContent = Math.max(0, cur - 1);
            }

            // Show empty message if no notes remain
            if (listEl && listEl.querySelectorAll('.nota-item').length === 0) {
              listEl.innerHTML = '<div class="text-center py-3 text-muted small notas-empty-msg"><i class="bi bi-journal d-block mb-1" style="font-size:1.4rem"></i>Sin notas registradas.</div>';
            }

            if (typeof showActionFeedback === 'function') showActionFeedback('Nota eliminada', 'success');
          } else {
            if (typeof showActionFeedback === 'function') showActionFeedback(res.message || 'Error al eliminar', 'error');
          }
        })
        .catch(function() {
          if (typeof showActionFeedback === 'function') showActionFeedback('Error de conexión', 'error');
        });
      });
    });

    // Pin form
    itemEl.querySelectorAll('.nota-pin-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var notaId = form.dataset.notaId;

        fetch(getAppUrl() + '/notas/' + notaId + '/pin', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
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
            var pinBtn = form.querySelector('button');
            var icon   = pinBtn ? pinBtn.querySelector('i') : null;

            if (res.is_pinned) {
              itemEl.classList.add('border-warning', 'bg-warning-subtle');
              itemEl.classList.remove('border-secondary-subtle');
              if (pinBtn) { pinBtn.classList.add('text-warning'); pinBtn.classList.remove('text-muted'); pinBtn.title = 'Desfijar'; }
              if (icon) { icon.className = 'bi bi-pin-fill'; }
            } else {
              itemEl.classList.remove('border-warning', 'bg-warning-subtle');
              itemEl.classList.add('border-secondary-subtle');
              if (pinBtn) { pinBtn.classList.remove('text-warning'); pinBtn.classList.add('text-muted'); pinBtn.title = 'Fijar'; }
              if (icon) { icon.className = 'bi bi-pin'; }
            }
          } else {
            if (typeof showActionFeedback === 'function') showActionFeedback(res.message || 'Error al fijar nota', 'error');
          }
        })
        .catch(function() {
          if (typeof showActionFeedback === 'function') showActionFeedback('Error de conexión', 'error');
        });
      });
    });
  }

  // -------------------------------------------------------
  // Lazy-load panel: fetch notes via AJAX for a panel that
  // has data-lazy="true" attribute.
  // -------------------------------------------------------
  function loadLazyPanel(panelEl) {
    var scope  = panelEl.dataset.scope;
    var refId  = panelEl.dataset.refId;
    var listId = 'notas-list-' + scope + '-' + refId;
    var listEl = document.getElementById(listId);
    if (!listEl || panelEl.dataset.loaded === '1') return;

    panelEl.dataset.loaded = '1';
    listEl.innerHTML = '<div class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando notas...</div>';

    fetch(getAppUrl() + '/notas/entidad?scope=' + encodeURIComponent(scope) + '&referencia_id=' + encodeURIComponent(refId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok) { listEl.innerHTML = '<p class="text-danger small">Error al cargar notas.</p>'; return; }

      var countEl = document.getElementById('notas-count-' + scope + '-' + refId);
      if (countEl) countEl.textContent = res.notas.length;

      if (res.notas.length === 0) {
        listEl.innerHTML = '<div class="text-center py-3 text-muted small notas-empty-msg"><i class="bi bi-journal d-block mb-1" style="font-size:1.4rem"></i>Sin notas registradas.</div>';
        return;
      }

      listEl.innerHTML = '';
      res.notas.forEach(function(nota) {
        listEl.insertAdjacentHTML('beforeend', buildNoteHtml(nota, panelEl));
        var newItem = document.getElementById('nota-item-' + nota.id);
        if (newItem) bindNoteEvents(newItem);
      });
    })
    .catch(function() {
      listEl.innerHTML = '<p class="text-danger small">Error de conexión.</p>';
    });
  }

  // -------------------------------------------------------
  // Init all panels on page
  // -------------------------------------------------------
  function initNotasPanels() {
    // Bind add forms
    document.querySelectorAll('.notas-add-form').forEach(function(form) {
      handleAddForm(form);
    });

    // Bind events for server-rendered note items
    document.querySelectorAll('.nota-item').forEach(function(item) {
      bindNoteEvents(item);
    });

    // Init lazy panels
    document.querySelectorAll('.notas-panel[data-lazy="true"]').forEach(function(panelEl) {
      loadLazyPanel(panelEl);
    });
  }

  document.addEventListener('DOMContentLoaded', initNotasPanels);

  // Expose for dynamic panels (e.g. after Kanban card expand)
  window.initNotasPanels = initNotasPanels;
  window.loadLazyNotasPanel = loadLazyPanel;

})();
