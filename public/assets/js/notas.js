/* =============================================
   TaskOrbit - Notas JS
   Reads project/task data from #notas-data
   element to populate the reference dropdown
   without needing unsafe-inline scripts.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  const dataEl = document.getElementById('notas-data');
  if (!dataEl) return;

  const notaProyectos = JSON.parse(dataEl.dataset.proyectos || '[]');
  const notaTareas    = JSON.parse(dataEl.dataset.tareas    || '[]');
  const notaSubtareas = JSON.parse(dataEl.dataset.subtareas || '[]');

  // ---- Scope → reference dropdown ----
  function updateNotaRef() {
    const scopeEl   = document.getElementById('nota-scope');
    const container = document.getElementById('nota-ref-container');
    const select    = document.getElementById('nota-referencia');

    if (!scopeEl || !container || !select) return;

    const scope = scopeEl.value;

    if (scope === 'personal') {
      container.classList.add('d-none');
      select.innerHTML = '<option value="">-</option>';
      return;
    }

    container.classList.remove('d-none');
    select.innerHTML = '<option value="">Selecciona...</option>';

    let items;
    if (scope === 'proyecto') {
      items = notaProyectos;
    } else if (scope === 'tarea') {
      items = notaTareas;
    } else if (scope === 'subtarea') {
      items = notaSubtareas;
    } else {
      items = [];
    }

    items.forEach(item => {
      const opt       = document.createElement('option');
      opt.value       = item.id;
      opt.textContent = item.nombre;
      select.appendChild(opt);
    });
  }

  const scopeSelect = document.getElementById('nota-scope');
  if (scopeSelect) {
    scopeSelect.addEventListener('change', updateNotaRef);
    updateNotaRef(); // initialize on page load
  }

  // ---- Confirm before deleting a note (replaces inline onclick) ----
  document.querySelectorAll('.js-confirm-delete').forEach(btn => {
    btn.addEventListener('click', function (e) {
      const message = this.dataset.confirm || '¿Confirmar eliminación?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

});
