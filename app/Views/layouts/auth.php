<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskOrbit</title>
  <?php echo \App\Helpers\CSRF::metaTag(); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/app.css'); ?>">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/mobile.css'); ?>">
  <style>
    body { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4f46e5 100%); min-height: 100vh; }
    [data-bs-theme="dark"] body { background: linear-gradient(135deg, #0f0e1a 0%, #1e1b4b 50%, #312e81 100%); }
    .auth-card { max-width: 420px; width: 100%; }
    .auth-logo { width: 56px; height: 56px; background: #4f46e5; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin: 0 auto 1rem; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height:100vh">

  <button id="btn-theme-toggle" class="btn btn-sm btn-outline-light position-fixed" style="top:1rem;right:1rem;z-index:9999" title="Cambiar modo">
    <i class="bi bi-moon-fill"></i>
  </button>

  <div class="auth-card login-container">
    <?php echo $content; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo \App\Core\View::asset('js/app.js'); ?>"></script>
</body>
</html>
