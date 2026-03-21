<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$roleBadge = ['GOD'=>'role-badge-god','ADMIN'=>'role-badge-admin','USER'=>'role-badge-user'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Usuarios</h1>
    <p class="text-muted small mb-0">Administración de usuarios del sistema</p>
  </div>
  <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-usuario">
    <i class="bi bi-plus-lg me-1"></i> Nuevo usuario
  </button>
</div>

<?php if(empty($usuarios)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-people display-4 mb-3 d-block"></i>
  <h5>Sin usuarios registrados</h5>
</div>
<?php else: ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr>
          <th>Usuario</th>
          <th class="d-none d-md-table-cell">Nombre</th>
          <th class="d-none d-lg-table-cell">Teléfono</th>
          <th>Rol</th>
          <th>Estado</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="avatar"><?php echo mb_strtoupper(mb_substr($u['nombre_completo']??'?', 0, 1)); ?></span>
              <span class="fw-medium"><?php echo $e($u['username']); ?></span>
            </div>
          </td>
          <td class="d-none d-md-table-cell small"><?php echo $e($u['nombre_completo']); ?></td>
          <td class="d-none d-lg-table-cell small text-muted"><?php echo $e($u['telefono']??'-'); ?></td>
          <td>
            <span class="badge <?php echo $e($roleBadge[$u['rol']]??'bg-secondary'); ?>">
              <?php echo $e($u['rol']); ?>
            </span>
          </td>
          <td>
            <form method="POST" action="<?php echo $appUrl; ?>/admin/usuarios/<?php echo $e($u['id']); ?>/estado">
              <?php echo \App\Helpers\CSRF::tokenField(); ?>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input js-autosubmit-checkbox" type="checkbox"
                  <?php echo $u['activo'] ? 'checked' : ''; ?>
                  title="<?php echo $u['activo'] ? 'Desactivar usuario' : 'Activar usuario'; ?>">
              </div>
            </form>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-warning"
                data-bs-toggle="modal"
                data-bs-target="#modal-usuario"
                data-edit-id="<?php echo $e($u['id']); ?>"
                data-edit-nombre="<?php echo $e($u['nombre_completo']); ?>"
                data-edit-username="<?php echo $e($u['username']); ?>"
                data-edit-telefono="<?php echo $e($u['telefono']??''); ?>"
                data-edit-rol="<?php echo $e($u['rol']); ?>"
                title="Editar">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if ($u['rol'] !== 'GOD'): ?>
              <button type="button" class="btn btn-outline-danger"
                data-delete-url="<?php echo $appUrl; ?>/admin/usuarios/<?php echo $e($u['id']); ?>/eliminar"
                data-delete-title="¿Eliminar usuario?"
                data-delete-msg="Se eliminará el usuario &quot;<?php echo $e($u['username']); ?>&quot;. Esta acción no se puede deshacer."
                title="Eliminar">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal crear/editar usuario -->
<div class="modal fade" id="modal-usuario" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-usuario-title">Nuevo usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="form-usuario" action="<?php echo $appUrl; ?>/admin/usuarios" novalidate>
        <?php echo \App\Helpers\CSRF::tokenField(); ?>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold small">Nombre completo <span class="text-danger">*</span></label>
              <input type="text" name="nombre_completo" id="f-nombre" class="form-control form-control-sm" required maxlength="120">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small">Usuario <span class="text-danger">*</span></label>
              <input type="text" name="username" id="f-username" class="form-control form-control-sm" required minlength="4" maxlength="60">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small">Teléfono</label>
              <input type="tel" name="telefono" id="f-telefono" class="form-control form-control-sm" maxlength="20" placeholder="+52 55...">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small">Rol <span class="text-danger">*</span></label>
              <select name="rol" id="f-rol" class="form-select form-select-sm" required>
                <option value="USER">USER</option>
                <option value="ADMIN">ADMIN</option>
                <option value="GOD">GOD</option>
              </select>
            </div>
            <div class="col-12 col-md-6" id="f-activo-group">
              <label class="form-label fw-semibold small d-block">Activo</label>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="activo" id="f-activo" value="1" checked>
                <label class="form-check-label small" for="f-activo">Cuenta activa</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small" id="f-password-label">Contraseña <span class="text-danger">*</span></label>
              <div class="input-group input-group-sm">
                <input type="password" name="password" id="f-password" class="form-control" minlength="8" placeholder="Mínimo 8 caracteres">
                <button type="button" class="btn btn-outline-secondary" id="btn-toggle-pwd" tabindex="-1">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text" id="f-password-hint">Mínimo 8 caracteres.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-usuario">
            <i class="bi bi-check-lg me-1"></i>Guardar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?php echo \App\Core\View::asset('js/usuarios.js'); ?>"></script>
