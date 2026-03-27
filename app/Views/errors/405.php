<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
?>
<style nonce="<?= CSP_NONCE ?>">
  /* ============================================
     NEBULA + SCANLINES (background layers)
     ============================================ */
  .nebula {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
      radial-gradient(ellipse 80% 60% at 20% 50%, rgba(245,158,11,0.15) 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 80% 30%, rgba(251,191,36,0.1) 0%, transparent 50%),
      radial-gradient(ellipse 90% 50% at 50% 80%, rgba(252,211,77,0.08) 0%, transparent 60%),
      radial-gradient(ellipse 40% 40% at 70% 70%, rgba(253,230,138,0.06) 0%, transparent 50%);
  }

  .scanlines {
    position: fixed;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(255,255,255,0.008) 2px,
      rgba(255,255,255,0.008) 4px
    );
  }

  /* ============================================
     ERROR PAGE CONTAINER
     ============================================ */
  .error-page {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem 1rem 3rem;
    text-align: center;
  }

  /* ============================================
     WARNING ICON
     ============================================ */
  .warning-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    position: relative;
    background: radial-gradient(circle at 35% 35%, #fcd34d, #f59e0b 40%, #d97706 70%, #92400e);
    box-shadow:
      inset -15px -10px 30px rgba(0,0,0,0.8),
      inset 5px 5px 20px rgba(252,211,77,0.2),
      0 0 50px rgba(245,158,11,0.3),
      0 0 100px rgba(245,158,11,0.1);
    animation: float 6s ease-in-out infinite;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    font-size: 3.5rem;
    color: #fff;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-12px); }
  }

  /* ============================================
     ERROR CODE "405"
     ============================================ */
  .error-code {
    font-size: clamp(5rem, 18vw, 11rem);
    font-weight: 900;
    letter-spacing: 0.05em;
    line-height: 1;
    margin-bottom: 0.25rem;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 40%, #d97706 70%, #fbbf24 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: shine 4s linear infinite;
    cursor: default;
    user-select: none;
  }

  @keyframes shine {
    0%   { background-position: 0% center; }
    100% { background-position: 200% center; }
  }

  /* ============================================
     IMPERIAL DIVIDER
     ============================================ */
  .imperial-divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1rem 0 1.25rem;
    width: 100%;
    max-width: 500px;
  }

  .imperial-divider::before,
  .imperial-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(245,158,11,0.4), transparent);
  }

  .imperial-symbol {
    font-size: 0.7rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #94a3b8;
    white-space: nowrap;
    opacity: 0.7;
  }

  /* ============================================
     TEXTS
     ============================================ */
  .error-title {
    font-size: clamp(1.1rem, 3vw, 1.6rem);
    font-weight: 700;
    color: #e2e8f0;
    margin-bottom: 0.75rem;
  }

  .error-subtitle {
    font-size: clamp(0.85rem, 2vw, 1rem);
    color: #94a3b8;
    max-width: 540px;
    line-height: 1.7;
    margin-bottom: 2rem;
  }

  /* ============================================
     BUTTONS
     ============================================ */
  .btn-wrap {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 2.5rem;
  }

  .btn-space-primary,
  .btn-space-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 1.8rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.25s ease;
    cursor: pointer;
    border: none;
    outline: none;
  }

  .btn-space-primary {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    color: #fff;
    box-shadow: 0 0 20px rgba(245,158,11,0.3), 0 4px 15px rgba(245,158,11,0.2);
  }

  .btn-space-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 30px rgba(245,158,11,0.5), 0 6px 25px rgba(245,158,11,0.35);
    color: #fff;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
  }

  .btn-space-secondary {
    background: transparent;
    color: #94a3b8;
    border: 1px solid rgba(245,158,11,0.3);
    box-shadow: 0 0 10px rgba(245,158,11,0.1);
  }

  .btn-space-secondary:hover {
    transform: translateY(-2px);
    color: #e2e8f0;
    border-color: rgba(245,158,11,0.6);
    box-shadow: 0 0 20px rgba(245,158,11,0.25);
    background: rgba(245,158,11,0.08);
  }

  /* ============================================
     STATUS BAR
     ============================================ */
  .status-bar {
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 0.7rem;
    letter-spacing: 0.1em;
    color: rgba(148,163,184,0.5);
    text-transform: uppercase;
    padding: 0.75rem 1.5rem;
    border: 1px solid rgba(245,158,11,0.12);
    border-radius: 6px;
    background: rgba(0,0,0,0.3);
  }

  @media (max-width: 480px) {
    .error-page { padding: 1.5rem 0.75rem 2rem; }
    .btn-wrap { flex-direction: column; align-items: center; }
    .btn-space-primary, .btn-space-secondary { width: 100%; max-width: 260px; justify-content: center; }
    .warning-icon { width: 90px; height: 90px; font-size: 2.5rem; }
  }
</style>

<!-- Nebula background -->
<div class="nebula"></div>
<!-- Scanlines overlay -->
<div class="scanlines"></div>

<!-- Main error page content -->
<div class="error-page" role="main">

  <!-- Warning Icon -->
  <div class="warning-icon" aria-hidden="true">
    &#x26D4;
  </div>

  <!-- 405 code -->
  <h1 class="error-code" aria-label="Error 405">405</h1>

  <!-- Divider -->
  <div class="imperial-divider" aria-hidden="true">
    <span class="imperial-symbol">&#x2B21; metodo no permitido &#x2B21;</span>
  </div>

  <!-- Error messages -->
  <h2 class="error-title">Metodo no permitido</h2>
  <p class="error-subtitle">
    La accion solicitada no esta permitida con este metodo.
    Verifica la URL o intenta acceder de otra forma.
  </p>

  <!-- Action buttons -->
  <div class="btn-wrap">
    <a href="<?= htmlspecialchars($appUrl) ?>/dashboard" class="btn-space-primary" aria-label="Volver al panel principal">
      Volver al inicio
    </a>
    <button type="button" id="btn-go-back" class="btn-space-secondary" aria-label="Volver a la pagina anterior">
      Ir atras
    </button>
  </div>

  <!-- Terminal status bar -->
  <div class="status-bar" role="status">
    ERROR: MTD-405 &nbsp;|&nbsp; STATUS: METODO NO PERMITIDO &nbsp;|&nbsp; ACCION: VERIFICAR SOLICITUD
  </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
(function() {
  var btn = document.getElementById('btn-go-back');
  if (btn) {
    btn.addEventListener('click', function() {
      if (window.history.length > 1) {
        history.back();
      } else {
        window.location.href = '<?= htmlspecialchars($appUrl) ?>/dashboard';
      }
    });
  }
})();
</script>
