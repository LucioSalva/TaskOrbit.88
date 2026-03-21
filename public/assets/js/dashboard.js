/* =============================================
   TaskOrbit - Dashboard Charts
   Reads data from #dashboard-data element to
   avoid unsafe-inline script requirements.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const dataEl = document.getElementById('dashboard-data');
  if (!dataEl || typeof Chart === 'undefined') return;

  const productividadProyectos = JSON.parse(dataEl.dataset.productividadProyectos || '[]');
  const productividadUsuarios  = JSON.parse(dataEl.dataset.productividadUsuarios  || '[]');
  const tareas                 = JSON.parse(dataEl.dataset.tareas                 || '[]');

  // ---- Productivity by user chart ----
  const ctxUsuarios = document.getElementById('chart-productividad-usuarios');
  if (ctxUsuarios && productividadUsuarios.length > 0) {
    const labels     = productividadUsuarios.map(u => u.nombre_completo);
    const terminadas = productividadUsuarios.map(u => parseInt(u.tareas_terminadas, 10) || 0);
    const pendientes = productividadUsuarios.map(u => (parseInt(u.tareas_total, 10) || 0) - (parseInt(u.tareas_terminadas, 10) || 0));

    new Chart(ctxUsuarios, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Tareas Terminadas',
            data: terminadas,
            backgroundColor: 'rgba(16,185,129,0.75)',
            borderColor: 'rgb(16,185,129)',
            borderWidth: 1,
            borderRadius: 6,
          },
          {
            label: 'Tareas Pendientes',
            data: pendientes,
            backgroundColor: 'rgba(245,158,11,0.6)',
            borderColor: 'rgb(245,158,11)',
            borderWidth: 1,
            borderRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { font: { size: 12 }, padding: 12 } },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const idx   = items[0].dataIndex;
                const total = (terminadas[idx] || 0) + (pendientes[idx] || 0);
                const pct   = total > 0 ? Math.round((terminadas[idx] / total) * 100) : 0;
                return `Completado: ${pct}%`;
              },
            },
          },
        },
        scales: {
          x: { stacked: false, ticks: { font: { size: 12 } } },
          y: { beginAtZero: true, ticks: { stepSize: 1 } },
        },
      },
    });
  }

  // ---- Productivity by project bar chart ----
  const ctxBar = document.getElementById('chart-proyectos');
  if (ctxBar && productividadProyectos.length > 0) {
    new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels:   productividadProyectos.map(p => p.nombre),
        datasets: [{
          label:           '% Completado',
          data:            productividadProyectos.map(p => p.progreso),
          backgroundColor: 'rgba(79,70,229,0.7)',
          borderColor:     'rgb(79,70,229)',
          borderWidth:     1,
          borderRadius:    6,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
        },
        plugins: { legend: { display: false } },
      },
    });
  }

  // ---- Task status doughnut chart ----
  const ctxPie = document.getElementById('chart-estados');
  if (ctxPie) {
    const keys = ['por_hacer', 'haciendo', 'terminada', 'enterado', 'ocupado', 'aceptada'];
    const lbls = ['Por Hacer', 'Haciendo', 'Terminada', 'Enterado', 'Ocupado', 'Aceptada'];

    new Chart(ctxPie, {
      type: 'doughnut',
      data: {
        labels:   lbls,
        datasets: [{
          data:            keys.map(k => tareas.filter(t => t.estado === k).length),
          backgroundColor: ['#6c757d', '#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#20c997'],
          borderWidth:     2,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
        },
      },
    });
  }
});
