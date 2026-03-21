<div class="card shadow-lg border-0 rounded-4 p-4">
  <div class="text-center mb-4">
    <div class="auth-logo mb-3">
      <i class="bi bi-diagram-3-fill"></i>
    </div>
    <h1 class="h4 fw-bold mb-1">TaskOrbit</h1>
    <p class="text-muted small mb-0">Gestión de proyectos y tareas</p>
  </div>

  <?php foreach (($flash ?? []) as $f): ?>
    <?php $type = $f['type'] === 'error' ? 'danger' : $f['type']; ?>
    <div class="alert alert-<?php echo htmlspecialchars($type); ?> py-2 small">
      <?php echo htmlspecialchars($f['message']); ?>
    </div>
  <?php endforeach; ?>

  <form method="POST" action="<?php echo \App\Core\View::url('login'); ?>" novalidate>
    <?php echo \App\Helpers\CSRF::tokenField(); ?>

    <div class="mb-3">
      <label for="username" class="form-label fw-semibold small">Usuario</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          placeholder="Tu nombre de usuario"
          autocomplete="username"
          required
          autofocus
        >
      </div>
    </div>

    <div class="mb-4">
      <label for="password" class="form-label fw-semibold small">Contraseña</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Tu contraseña"
          autocomplete="current-password"
          required
        >
        <button type="button" class="btn btn-outline-secondary" id="toggle-password" tabindex="-1">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
      <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
    </button>
  </form>

  <p class="text-center text-muted mt-3 mb-0" style="font-size:0.75rem">
    <i class="bi bi-shield-lock me-1"></i>Acceso seguro · TaskOrbit &copy; <?php echo date('Y'); ?>
  </p>
</div>

<script>
document.getElementById('toggle-password')?.addEventListener('click', function() {
  const pwd = document.getElementById('password');
  const icon = this.querySelector('i');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    pwd.type = 'password';
    icon.className = 'bi bi-eye';
  }
});
</script>
