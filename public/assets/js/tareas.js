/* =============================================
   TaskOrbit - Tareas JS
   Handles date validation on task create/edit.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ---- Date range: enforce t_fecha_inicio <= t_fecha_fin ----
  const fechaInicio = document.getElementById('t_fecha_inicio');
  const fechaFin    = document.getElementById('t_fecha_fin');

  if (fechaInicio && fechaFin) {
    fechaInicio.addEventListener('change', function () {
      if (this.value) {
        if (!fechaFin.min || this.value > fechaFin.min) fechaFin.min = this.value;
        if (fechaFin.value && fechaFin.value < this.value) fechaFin.value = '';
      }
    });
  }

});
