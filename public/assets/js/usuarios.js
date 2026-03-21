/* =============================================
   TaskOrbit - Usuarios JS
   Modal create/edit logic + toggle password
   visibility + auto-submit status checkboxes.
   ============================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  const modal     = document.getElementById('modal-usuario');
  const form      = document.getElementById('form-usuario');
  const titleEl   = document.getElementById('modal-usuario-title');
  const pwdLabel  = document.getElementById('f-password-label');
  const pwdHint   = document.getElementById('f-password-hint');
  const pwdInput  = document.getElementById('f-password');
  const togglePwd = document.getElementById('btn-toggle-pwd');
  const appUrl    = window.APP_URL || '';

  // ---- Toggle password visibility ----
  if (togglePwd && pwdInput) {
    togglePwd.addEventListener('click', () => {
      const t = pwdInput.type === 'password' ? 'text' : 'password';
      pwdInput.type = t;
      const icon = togglePwd.querySelector('i');
      if (icon) icon.className = t === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  }

  // ---- Populate modal for create / edit ----
  if (modal) {
    modal.addEventListener('show.bs.modal', function (e) {
      const btn    = e.relatedTarget;
      const editId = btn?.dataset?.editId;

      if (editId) {
        // Edit mode
        if (titleEl) titleEl.textContent = 'Editar usuario';
        if (form)    form.action = appUrl + '/admin/usuarios/' + editId + '/editar';

        const fNombre   = document.getElementById('f-nombre');
        const fUsername = document.getElementById('f-username');
        const fTelefono = document.getElementById('f-telefono');
        const fRol      = document.getElementById('f-rol');

        if (fNombre)   fNombre.value   = btn.dataset.editNombre   || '';
        if (fUsername) fUsername.value = btn.dataset.editUsername  || '';
        if (fTelefono) fTelefono.value = btn.dataset.editTelefono  || '';
        if (fRol)      fRol.value      = btn.dataset.editRol       || 'USER';

        if (pwdInput)  { pwdInput.required = false; pwdInput.value = ''; }
        if (pwdLabel)  pwdLabel.innerHTML  = 'Nueva contraseña <small class="text-muted">(opcional)</small>';
        if (pwdHint)   pwdHint.textContent = 'Déjalo vacío para no cambiar la contraseña.';
      } else {
        // Create mode
        if (titleEl)  titleEl.textContent = 'Nuevo usuario';
        if (form)     { form.action = appUrl + '/admin/usuarios'; form.reset(); }
        if (pwdInput) pwdInput.required = true;
        if (pwdLabel) pwdLabel.innerHTML  = 'Contraseña <span class="text-danger">*</span>';
        if (pwdHint)  pwdHint.textContent = 'Mínimo 8 caracteres.';
      }
    });
  }

  // ---- Auto-submit checkboxes with .js-autosubmit-checkbox ----
  document.querySelectorAll('.js-autosubmit-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      const parentForm = this.closest('form');
      if (parentForm) parentForm.submit();
    });
  });

});
