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

// ---- Flash Auto-dismiss ----
(function initFlash() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flash-alert').forEach(alert => {
      setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        if (bsAlert) bsAlert.close();
      }, 5000);
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
              <div class="notif-item ${!n.read ? 'unread' : ''}" data-id="${n.id}">
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
        .then(r => r.json())
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
          .then(r => r.json())
          .then(() => loadNotifications())
          .catch(() => {});
      });
    }

    loadNotifications();
    setInterval(loadNotifications, 60000); // Refresh every minute
  });
})();

// ---- Estado Quick Change ----
function changeEstado(formEl) {
  const url    = formEl.action;
  const data   = new FormData(formEl);
  const params = new URLSearchParams(data).toString();

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: params,
  })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        // Update UI without full reload
        const card = formEl.closest('[data-tarea-id], [data-subtarea-id]');
        if (card) {
          const badgeEl = card.querySelector('.estado-badge');
          if (badgeEl) {
            badgeEl.className = `badge estado-badge badge-estado-${res.estado}`;
            badgeEl.textContent = estadoLabel(res.estado);
          }
          // Update active button
          formEl.closest('.estado-btn-group')?.querySelectorAll('.btn').forEach(btn => {
            btn.classList.toggle('active-estado', btn.dataset.estado === res.estado);
          });
        }
      } else {
        alert(res.message || 'Error al cambiar estado');
      }
    })
    .catch(() => {
      formEl.submit(); // fallback to full form submit
    });

  return false;
}

// ---- Delete Confirmation Modal ----
(function initDeleteConfirm() {
  document.addEventListener('DOMContentLoaded', () => {
    const modal     = document.getElementById('modal-confirm-delete');
    const formEl    = document.getElementById('form-confirm-delete');
    const titleEl   = document.getElementById('modal-delete-title');
    const msgEl     = document.getElementById('modal-delete-msg');
    const reasonEl  = document.getElementById('modal-delete-reason');

    if (!modal) return;

    document.querySelectorAll('[data-delete-url]').forEach(btn => {
      btn.addEventListener('click', () => {
        const url    = btn.dataset.deleteUrl;
        const title  = btn.dataset.deleteTitle || '¿Eliminar?';
        const msg    = btn.dataset.deleteMsg   || 'Esta acción no se puede deshacer.';
        const reason = btn.dataset.showReason  === 'true';

        if (formEl)  formEl.action = url;
        if (titleEl) titleEl.textContent = title;
        if (msgEl)   msgEl.textContent   = msg;
        if (reasonEl) {
          reasonEl.closest('.mb-3').style.display = reason ? '' : 'none';
          reasonEl.value = '';
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
