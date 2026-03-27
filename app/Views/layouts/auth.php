<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskOrbit</title>
  <link rel="shortcut icon" href="<?php echo \App\Core\View::url('favicon.ico'); ?>" type="image/x-icon">
  <?php echo \App\Helpers\CSRF::metaTag(); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/app.css'); ?>">
  <link rel="stylesheet" href="<?php echo \App\Core\View::asset('css/mobile.css'); ?>">
  <style nonce="<?= CSP_NONCE ?>">
    body { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4f46e5 100%); min-height: 100vh; }
    [data-bs-theme="dark"] body { background: linear-gradient(135deg, #0f0e1a 0%, #1e1b4b 50%, #312e81 100%); }
    .auth-card { max-width: 420px; width: 100%; }
    .auth-logo { width: 56px; height: 56px; background: #4f46e5; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin: 0 auto 1rem; }
    .auth-logo-img { width: 40px; height: 40px; object-fit: contain; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3 min-vh-100">

  <button id="btn-theme-toggle" class="btn btn-sm btn-outline-light pos-tr-fixed" title="Cambiar modo">
    <i class="bi bi-moon-fill"></i>
  </button>

  <div class="auth-card login-container">
    <?php echo $content; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo \App\Core\View::asset('js/app.js'); ?>"></script>
</body>
</html>
