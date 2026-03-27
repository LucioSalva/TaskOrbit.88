<?php
// Standalone 403 page — no layout (may be called before session is ready)
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
$errorMessage = $errorMessage ?? 'No tienes permiso para acceder a esta sección.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 — Acceso Denegado | TaskOrbit</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
  <div class="text-center p-4 mw-420">
    <div class="display-1 text-danger mb-3"><i class="bi bi-shield-lock"></i></div>
    <h1 class="h3 mb-2">Acceso denegado</h1>
    <p class="text-muted mb-4"><?php echo htmlspecialchars($errorMessage); ?></p>
    <div class="d-flex gap-2 justify-content-center">
      <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
      </a>
      <a href="<?php echo $appUrl; ?>/dashboard" class="btn btn-primary">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
    </div>
  </div>
</body>
</html>
