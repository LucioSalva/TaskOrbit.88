/* ===================================
   VISTAS OPERATIVAS — TaskOrbit
   =================================== */

(function () {
  'use strict';

  // Risk level helper (returns 'overdue'|'warning'|'ok'|'none')
  window.riskLevel = function (fechaFin, estado) {
    var terminados = ['terminada', 'aceptada'];
    if (terminados.includes(estado)) return 'done';
    if (!fechaFin) return 'none';
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var fin = new Date(fechaFin); fin.setHours(0, 0, 0, 0);
    var diff = Math.round((fin - today) / 86400000);
    if (diff < 0) return 'overdue';
    if (diff <= 3) return 'warning';
    return 'ok';
  };

  // Format date as dd/mm/yyyy
  window.fmtDate = function (d) {
    if (!d) return '\u2014';
    var dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  // Relative date label
  window.relDate = function (fechaFin) {
    if (!fechaFin) return null;
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var fin = new Date(fechaFin); fin.setHours(0, 0, 0, 0);
    var diff = Math.round((fin - today) / 86400000);
    if (diff < 0) return 'Venci\u00f3 hace ' + Math.abs(diff) + 'd';
    if (diff === 0) return 'Vence hoy';
    if (diff === 1) return 'Vence ma\u00f1ana';
    if (diff <= 7) return 'Vence en ' + diff + 'd';
    return null;
  };

  // ---- View Switcher ----
  function initViewSwitcher(containerSelector) {
    var container = document.querySelector(containerSelector);
    if (!container) return;

    var panels = container.querySelectorAll('.vista-panel');
    var links  = container.querySelectorAll('.view-switcher .nav-link');
    var page   = container.dataset.page || 'generic';
    var key    = 'taskorbit.vista.' + page;

    function showView(viewId) {
      var found = false;
      panels.forEach(function (p) {
        var active = p.dataset.vista === viewId;
        p.classList.toggle('d-none', !active);
        if (active) found = true;
      });
      if (!found) {
        panels.forEach(function (p, i) { p.classList.toggle('d-none', i !== 0); });
        viewId = panels[0] ? panels[0].dataset.vista : 'lista';
      }
      links.forEach(function (l) { l.classList.toggle('active', l.dataset.view === viewId); });
      localStorage.setItem(key, viewId);
      // Update URL hash without scroll
      var url = new URL(location.href);
      url.hash = viewId;
      history.replaceState(null, '', url.toString());
    }

    links.forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        showView(link.dataset.view);
      });
    });

    // Restore view — fallback 'lista' if USER role has 'timeline' saved
    var hash      = location.hash.replace('#', '');
    var saved     = localStorage.getItem(key);
    var userRole  = document.body ? document.body.dataset.userRole || '' : '';
    var preferred = hash || saved || 'lista';
    if (preferred === 'timeline' && userRole === 'USER') {
      preferred = 'lista';
    }
    showView(preferred);
  }

  // ---- User group collapse ----
  function initUserGroups() {
    document.querySelectorAll('.user-group-header').forEach(function (header) {
      header.addEventListener('click', function () {
        var body = header.nextElementSibling;
        if (!body) return;
        var icon = header.querySelector('.collapse-icon');
        var expanded = body.style.display !== 'none';
        body.style.display = expanded ? 'none' : '';
        if (icon) icon.style.transform = expanded ? 'rotate(-90deg)' : '';
      });
    });
  }

  // ---- Risk badges on kanban/timeline cards ----
  function applyRiskClasses() {
    document.querySelectorAll('[data-fecha-fin][data-estado]').forEach(function (el) {
      var risk = riskLevel(el.dataset.fechaFin, el.dataset.estado);
      if (risk === 'overdue') el.classList.add('risk-overdue');
      else if (risk === 'warning') el.classList.add('risk-warning');
    });
  }

  // ---- Auto-submit filters ----
  function initAutoSubmit() {
    document.querySelectorAll('.js-autosubmit').forEach(function (el) {
      el.addEventListener('change', function () {
        var form = el.closest('form');
        if (form) form.submit();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initViewSwitcher('#vistas-container');
    initUserGroups();
    applyRiskClasses();
    initAutoSubmit();
  });
})();
