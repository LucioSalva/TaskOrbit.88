<div class="text-center py-5">
  <div style="font-size:5rem;color:#ef4444;margin-bottom:1rem">
    <i class="bi bi-shield-x"></i>
  </div>
  <h1 class="h2 fw-bold text-danger">Acceso Denegado</h1>
  <p class="text-muted mt-2 mb-4">
    No tienes permisos para acceder a esta sección.<br>
    Contacta al administrador si crees que es un error.
  </p>
  <?php
  $appUrl = rtrim(getenv('APP_URL') ?: '', '/');
  $isAuth = !empty($_SESSION['user']['id']);
  ?>
  <?php if ($isAuth): ?>
    <a href="<?php echo $appUrl; ?>/dashboard" class="btn btn-primary">
      <i class="bi bi-speedometer2 me-1"></i> Ir al Dashboard
    </a>
  <?php else: ?>
    <a href="<?php echo $appUrl; ?>/login" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
    </a>
  <?php endif; ?>
</div>
