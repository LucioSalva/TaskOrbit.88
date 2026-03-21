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

  // ---- Scope → reference dropdown ----
  function updateNotaRef() {
    const scopeEl   = document.getElementById('nota-scope');
    const container = document.getElementById('nota-ref-container');
    const select    = document.getElementById('nota-referencia');

    if (!scopeEl || !container || !select) return;

    const scope = scopeEl.value;

    if (scope === 'personal') {
      container.style.display = 'none';
      select.innerHTML = '<option value="">-</option>';
      return;
    }

    container.style.display = '';
    select.innerHTML = '<option value="">Selecciona...</option>';

    const items = scope === 'proyecto' ? notaProyectos : notaTareas;
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
