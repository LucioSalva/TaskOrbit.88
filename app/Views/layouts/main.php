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
</head>
<body data-app-url="<?php echo rtrim(getenv('APP_URL') ?: '', '/'); ?>">

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
      <img src="<?php echo $appUrl; ?>/img/taskorbit.png" alt="TaskOrbit" style="width:24px;height:24px;object-fit:contain;">
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
    <?php if (in_array($userRole, ['GOD', 'ADMIN'])): ?>
    <hr class="my-2" style="border-color:rgba(255,255,255,0.1)">
    <div class="px-3 py-1" style="font-size:0.7rem;color:#a5b4fc;text-transform:uppercase;letter-spacing:1px;">Administración</div>
    <?php if (in_array($userRole, ['GOD', 'ADMIN'])): ?>
    <a href="<?php echo $appUrl; ?>/admin/usuarios" class="nav-link <?php echo isActive('/admin/usuarios', $currentUri); ?>">
      <i class="bi bi-people"></i> Usuarios
    </a>
    <?php endif; ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <span class="avatar"><?php echo mb_substr(htmlspecialchars($userName), 0, 1); ?></span>
      <div>
        <div class="text-white" style="font-size:0.8rem;font-weight:600"><?php echo htmlspecialchars($userName); ?></div>
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
    <button id="sidebar-toggle" class="btn btn-sm btn-outline-secondary d-lg-none" title="Menú">
      <i class="bi bi-list fs-5"></i>
    </button>

    <div class="fw-semibold text-muted small d-none d-sm-block">
      <?php echo htmlspecialchars(getenv('APP_NAME') ?: 'TaskOrbit'); ?>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">

      <!-- Notifications -->
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary notif-bell position-relative" data-bs-toggle="dropdown" title="Notificaciones">
          <i class="bi bi-bell fs-5"></i>
          <span id="notif-badge" class="badge bg-danger" style="display:none">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 dropdown-notifications">
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <strong class="small">Notificaciones</strong>
            <a href="#" id="notif-mark-all" class="small text-primary">Marcar todas leídas</a>
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
      <form method="POST" action="<?php echo $appUrl; ?>/logout" class="d-inline">
        <?php echo \App\Helpers\CSRF::tokenField(); ?>
        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cerrar sesión">
          <i class="bi bi-box-arrow-right"></i>
          <span class="d-none d-md-inline ms-1">Salir</span>
        </button>
      </form>
    </div>
  </header>

  <!-- Flash messages -->
  <div class="flash-container">
    <?php
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    foreach ($flashes as $f):
      $type = $f['type'] === 'error' ? 'danger' : $f['type'];
    ?>
    <div class="alert alert-<?php echo htmlspecialchars($type); ?> alert-dismissible fade show flash-alert shadow-sm" role="alert">
      <?php echo htmlspecialchars($f['message']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Page content -->
  <main class="page-content">
    <?php echo $content; ?>
  </main>

</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="modal-confirm-delete" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-title">¿Confirmar eliminación?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="modal-delete-msg">Esta acción no se puede deshacer.</p>
        <div class="mb-3">
          <label for="modal-delete-reason" class="form-label small fw-semibold">Motivo (opcional)</label>
          <input type="text" class="form-control form-control-sm" id="modal-delete-reason" name="reason" maxlength="200">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form id="form-confirm-delete" method="POST">
          <?php echo \App\Helpers\CSRF::tokenField(); ?>
          <input type="hidden" name="reason" id="modal-reason-field">
          <button type="submit" class="btn btn-danger">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo \App\Core\View::asset('js/app.js'); ?>"></script>

</body>
</html>
