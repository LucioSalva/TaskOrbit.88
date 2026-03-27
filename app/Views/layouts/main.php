<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskOrbit</title>
  <?php echo \App\Helpers\CSRF::metaTag(); ?>
  <link rel="icon" href="<?php echo \App\Core\View::url('img/taskorbit.png'); ?>" type="image/png">
  <link rel="shortcut icon" href="<?php echo \App\Core\View::url('favicon.ico'); ?>" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/app.css'); ?>">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/vistas.css'); ?>">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/mobile.css'); ?>">
</head>
<body data-app-url="<?php echo rtrim(getenv('APP_URL') ?: '', '/'); ?>" data-user-role="<?php echo htmlspecialchars($_SESSION['user']['rol'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<?php
$appUrl   = rtrim(getenv('APP_URL') ?: '', '/');
$userRole = $_SESSION['user']['rol'] ?? '';
$userName = $_SESSION['user']['nombre_completo'] ?? '';
$userId   = $_SESSION['user']['id'] ?? 0;
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptDir  = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir !== '/' && str_starts_with($currentUri, $scriptDir)) {
    $currentUri = substr($currentUri, strlen($scriptDir));
}
$currentUri = '/' . ltrim($currentUri, '/');

function isActive(string $prefix, string $current): string {
    return str_starts_with($current, $prefix) ? 'active' : '';
}
?>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<nav id="sidebar">
  <a href="<?php echo $appUrl; ?>/dashboard" class="sidebar-brand">
    <span class="brand-icon">
      <img src="<?php echo $appUrl; ?>/img/taskorbit.png" alt="TaskOrbit" class="sidebar-brand-img">
    </span>
    <span class="brand-text">TaskOrbit</span>
  </a>

  <nav>
    <a href="<?php echo $appUrl; ?>/dashboard" class="nav-link <?php echo isActive('/dashboard', $currentUri); ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?php echo $appUrl; ?>/proyectos" class="nav-link <?php echo isActive('/proyectos', $currentUri); ?>">
      <i class="bi bi-kanban"></i> Proyectos
    </a>
    <a href="<?php echo $appUrl; ?>/notas" class="nav-link <?php echo isActive('/notas', $currentUri); ?>">
      <i class="bi bi-sticky"></i> Notas
    </a>
    <?php if ($userRole === 'GOD'): ?>
    <hr class="my-2 sidebar-sep">
    <div class="sidebar-section-lbl px-3 py-1">Administración</div>
    <a href="<?php echo $appUrl; ?>/admin/usuarios" class="nav-link <?php echo isActive('/admin/usuarios', $currentUri); ?>">
      <i class="bi bi-people"></i> Usuarios
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <span class="avatar"><?php echo mb_substr(htmlspecialchars($userName), 0, 1); ?></span>
      <div>
        <div class="sidebar-user-name text-white"><?php echo htmlspecialchars($userName); ?></div>
        <span class="badge badge-sm <?php
          echo $userRole === 'GOD' ? 'role-badge-god' : ($userRole === 'ADMIN' ? 'role-badge-admin' : 'role-badge-user');
        ?>"><?php echo htmlspecialchars($userRole); ?></span>
      </div>
    </div>
  </div>
</nav>

<!-- Main content -->
<div id="main-content">

  <!-- Topbar -->
  <header class="topbar d-flex align-items-center px-3 gap-2">
    <button id="sidebar-toggle" class="btn btn-sm btn-outline-secondary d-lg-none" title="Menu">
      <i class="bi bi-list fs-5"></i>
    </button>

    <div class="fw-semibold text-muted small d-none d-sm-block">
      <?php echo htmlspecialchars(getenv('APP_NAME') ?: 'TaskOrbit'); ?>
    </div>

    <!-- Mobile: compact page title -->
    <span class="fw-semibold small text-truncate d-sm-none mw-120">
      <?php
      $pageTitles = [
        '/dashboard' => 'Dashboard',
        '/proyectos' => 'Proyectos',
        '/notas' => 'Notas',
        '/admin/usuarios' => 'Usuarios',
      ];
      $pageTitle = 'TaskOrbit';
      foreach ($pageTitles as $prefix => $title) {
        if (str_starts_with($currentUri, $prefix)) { $pageTitle = $title; break; }
      }
      echo $pageTitle;
      ?>
    </span>

    <div class="ms-auto d-flex align-items-center gap-2">

      <!-- Notifications -->
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary notif-bell position-relative" data-bs-toggle="dropdown" title="Notificaciones">
          <i class="bi bi-bell fs-5"></i>
          <span id="notif-badge" class="badge bg-danger d-none">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 dropdown-notifications">
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <strong class="small">Notificaciones</strong>
            <a href="#" id="notif-mark-all" class="small text-primary">Marcar todas leidas</a>
          </div>
          <div id="notif-list">
            <div class="text-center py-3 text-muted small">Cargando...</div>
          </div>
        </div>
      </div>

      <!-- Dark mode -->
      <button id="btn-theme-toggle" class="btn btn-sm btn-outline-secondary" title="Modo oscuro">
        <i class="bi bi-moon-fill"></i>
      </button>

      <!-- Logout -->
      <form method="POST" action="<?php echo $appUrl; ?>/logout" class="d-inline" data-logout-form>
        <?php echo \App\Helpers\CSRF::tokenField(); ?>
        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cerrar sesion">
          <i class="bi bi-box-arrow-right"></i>
          <span class="d-none d-md-inline ms-1">Salir</span>
        </button>
      </form>
    </div>
  </header>

  <!-- Flash messages -->
  <div class="flash-container">
    <?php
    // Use the $flash variable already extracted by View::render() from $data['flash'].
    // Falling back to $_SESSION['flash'] ensures messages are shown even when a view
    // does not pass 'flash' explicitly, then clears the session copy.
    $flashes = (!empty($flash) && is_array($flash)) ? $flash : ($_SESSION['flash'] ?? []);
    unset($_SESSION['flash']);
    foreach ($flashes as $f):
      $type = ($f['type'] ?? '') === 'error' ? 'danger' : ($f['type'] ?? 'info');
    ?>
    <div class="alert alert-<?php echo htmlspecialchars($type); ?> alert-dismissible fade show flash-alert shadow-sm" role="alert">
      <?php echo htmlspecialchars($f['message'] ?? ''); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Page content -->
  <main class="page-content">
    <?php echo $content; ?>
  </main>

</div>

<!-- Bottom Navigation Bar (mobile only) -->
<nav class="bottom-nav" id="bottom-nav">
  <a href="<?php echo $appUrl; ?>/dashboard" class="bottom-nav-item <?php echo isActive('/dashboard', $currentUri); ?>">
    <i class="bi bi-speedometer2"></i>
    <span>Inicio</span>
  </a>
  <a href="<?php echo $appUrl; ?>/proyectos" class="bottom-nav-item <?php echo isActive('/proyectos', $currentUri); ?>">
    <i class="bi bi-kanban"></i>
    <span>Proyectos</span>
  </a>
  <a href="<?php echo $appUrl; ?>/notas" class="bottom-nav-item <?php echo isActive('/notas', $currentUri); ?>">
    <i class="bi bi-sticky"></i>
    <span>Notas</span>
  </a>
  <a href="#" class="bottom-nav-item" id="bottom-nav-menu" aria-label="Menu">
    <i class="bi bi-three-dots"></i>
    <span>Menu</span>
  </a>
</nav>

<!-- Delete confirmation modal -->
<div class="modal fade" id="modal-confirm-delete" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger border-top border-3">
      <div class="modal-header bg-danger bg-opacity-10">
        <h5 class="modal-title text-danger" id="modal-delete-title">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar eliminacion
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="modal-delete-msg" class="fw-medium">Esta accion no se puede deshacer.</p>
        <div id="modal-delete-preview"></div>
        <div class="mb-3">
          <label for="modal-delete-reason" class="form-label small fw-semibold">
            Motivo de la eliminacion <span class="text-muted fw-normal">(opcional pero recomendado)</span>
          </label>
          <input type="text" class="form-control form-control-sm" id="modal-delete-reason" name="reason"
                 maxlength="200" placeholder="Ej: Ya no se necesita, duplicado, error...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i>No, cancelar
        </button>
        <form id="form-confirm-delete" method="POST">
          <?php echo \App\Helpers\CSRF::tokenField(); ?>
          <input type="hidden" name="reason" id="modal-reason-field">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-1"></i>Si, eliminar permanentemente
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo \App\Core\View::asset('js/app.js'); ?>"></script>
<script src="<?php echo \App\Core\View::asset('js/vistas.js'); ?>"></script>

<?php include BASE_PATH . '/app/Views/partials/_quick_action_modals.php'; ?>
<script src="<?php echo \App\Core\View::asset('js/acciones-rapidas.js'); ?>"></script>
<script src="<?php echo \App\Core\View::asset('js/notas-panel.js'); ?>"></script>
<script src="<?php echo \App\Core\View::asset('js/evidencias.js'); ?>"></script>

<script nonce="<?php echo CSP_NONCE; ?>">
document.querySelector('[data-logout-form]')?.addEventListener('submit', function() {
  Object.keys(localStorage).forEach(function(k) {
    if (k.startsWith('taskorbit.vista.')) localStorage.removeItem(k);
  });
});
</script>
</body>
</html>
