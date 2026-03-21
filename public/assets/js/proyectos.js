/* =============================================
   TaskOrbit - Proyectos JS
   Handles date validation and filter autosubmit
   for project list and create/edit forms.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ---- Date range: enforce fecha_inicio <= fecha_fin on create form ----
  const fechaInicio = document.getElementById('fecha_inicio');
  const fechaFin    = document.getElementById('fecha_fin');

  if (fechaInicio && fechaFin) {
    fechaInicio.addEventListener('change', function () {
      if (this.value) fechaFin.min = this.value;
    });
  }

  // ---- Auto-submit selects with .js-autosubmit (filter bar) ----
  document.querySelectorAll('select.js-autosubmit').forEach(select => {
    select.addEventListener('change', function () {
      const form = this.closest('form');
      if (form) form.submit();
    });
  });

});
