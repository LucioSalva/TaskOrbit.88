/* =============================================
   TaskOrbit - Dashboard Charts
   Reads data from #dashboard-data element.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  // Collapse charts section on mobile by default
  if (window.innerWidth < 768) {
    const chartsCollapse = document.getElementById('collapseCharts');
    if (chartsCollapse && chartsCollapse.classList.contains('show')) {
      chartsCollapse.classList.remove('show');
    }
  }

  const dataEl = document.getElementById('dashboard-data');
  if (!dataEl || typeof Chart === 'undefined') return;

  const distribucion      = JSON.parse(dataEl.dataset.distribucion      || '{}');
  const metricasUsuarios  = JSON.parse(dataEl.dataset.metricasUsuarios  || '[]');
  const metricasProyectos = JSON.parse(dataEl.dataset.metricasProyectos || '[]');

  // ---- Task state doughnut chart ----
  const ctxEstados = document.getElementById('chart-estados');
  if (ctxEstados) {
    const keys   = ['por_hacer', 'haciendo', 'terminada', 'enterado', 'ocupado', 'aceptada'];
    const labels = ['Por Hacer', 'Haciendo', 'Terminada', 'Enterado', 'Ocupado', 'Aceptada'];
    const colors = ['#6c757d', '#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#20c997'];
    const values = keys.map(k => parseInt(distribucion[k] || 0, 10));
    const total  = values.reduce((a, b) => a + b, 0);

    if (total > 0) {
      new Chart(ctxEstados, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data:            values,
            backgroundColor: colors,
            borderWidth:     2,
          }],
        },
        options: {
          responsive:          true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const pct = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                  return ` ${ctx.label}: ${ctx.raw} (${pct}%)`;
                },
              },
            },
          },
        },
      });
    } else {
      ctxEstados.closest('.chart-wrap')?.classList.add('d-none');
    }
  }

  // ---- User cumplimiento horizontal bar chart ----
  const ctxUsuarios = document.getElementById('chart-usuarios');
  if (ctxUsuarios && metricasUsuarios.length > 0) {
    const labels     = metricasUsuarios.map(u => u.nombre_completo);
    const cumplimiento = metricasUsuarios.map(u => parseFloat(u.porcentaje_cumplimiento) || 0);
    const carga        = metricasUsuarios.map(u => parseInt(u.carga_actual, 10) || 0);

    new Chart(ctxUsuarios, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label:           '% Cumplimiento',
            data:            cumplimiento,
            backgroundColor: cumplimiento.map(v =>
              v >= 75 ? 'rgba(25,135,84,0.75)' : v >= 50 ? 'rgba(255,193,7,0.75)' : 'rgba(220,53,69,0.75)'
            ),
            borderColor: cumplimiento.map(v =>
              v >= 75 ? 'rgb(25,135,84)' : v >= 50 ? 'rgb(255,193,7)' : 'rgb(220,53,69)'
            ),
            borderWidth:  1,
            borderRadius: 4,
            yAxisID:      'yCumplimiento',
          },
          {
            label:           'Carga Actual',
            data:            carga,
            backgroundColor: 'rgba(13,110,253,0.35)',
            borderColor:     'rgb(13,110,253)',
            borderWidth:     1,
            borderRadius:    4,
            yAxisID:         'yCarga',
          },
        ],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { font: { size: 12 }, padding: 12 } },
        },
        scales: {
          x: { ticks: { font: { size: 11 } } },
          yCumplimiento: {
            type:     'linear',
            position: 'left',
            min:      0,
            max:      100,
            ticks:    { callback: v => v + '%', stepSize: 25 },
            grid:     { drawOnChartArea: true },
          },
          yCarga: {
            type:       'linear',
            position:   'right',
            beginAtZero: true,
            ticks:      { stepSize: 1 },
            grid:       { drawOnChartArea: false },
          },
        },
      },
    });
  }
});
