<?php
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');
?>
<style>
  /* ============================================
     NEBULA + SCANLINES (background layers)
     ============================================ */
  .nebula {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
      radial-gradient(ellipse 80% 60% at 20% 50%, rgba(79,70,229,0.15) 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 80% 30%, rgba(99,102,241,0.1) 0%, transparent 50%),
      radial-gradient(ellipse 90% 50% at 50% 80%, rgba(167,139,250,0.08) 0%, transparent 60%),
      radial-gradient(ellipse 40% 40% at 70% 70%, rgba(240,171,252,0.06) 0%, transparent 50%);
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
     DEATH STAR
     ============================================ */
  .death-star-wrap {
    position: relative;
    margin-bottom: 1.5rem;
  }

  .death-star {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    position: relative;
    background: radial-gradient(circle at 35% 35%, #9ca3af, #4b5563 40%, #1f2937 70%, #111827);
    box-shadow:
      inset -15px -10px 30px rgba(0,0,0,0.8),
      inset 5px 5px 20px rgba(156,163,175,0.2),
      0 0 50px rgba(79,70,229,0.3),
      0 0 100px rgba(79,70,229,0.1);
    animation: float 6s ease-in-out infinite;
  }

  /* Trench */
  .death-star::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg,
      transparent 5%,
      #1f2937 10%,
      #111827 40%,
      #1f2937 60%,
      #111827 90%,
      transparent 95%
    );
    transform: translateY(-50%);
    border-top: 1px solid rgba(75,85,99,0.5);
    border-bottom: 1px solid rgba(75,85,99,0.3);
    z-index: 1;
  }

  /* Superlaser dish */
  .death-star::after {
    content: '';
    position: absolute;
    top: 22%;
    left: 28%;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0.4) 40%, transparent 70%);
    box-shadow: 0 0 15px rgba(96,165,250,0.6), 0 0 30px rgba(96,165,250,0.3);
    animation: pulse-laser 3s ease-in-out infinite;
    z-index: 2;
  }

  .hologram-ring {
    position: absolute;
    bottom: -18px;
    left: 50%;
    transform: translateX(-50%);
    width: 140px;
    height: 20px;
    border-radius: 50%;
    background: transparent;
    border: 1px solid rgba(96,165,250,0.25);
    box-shadow: 0 0 20px rgba(96,165,250,0.15), inset 0 0 10px rgba(96,165,250,0.1);
    animation: ring-pulse 4s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25%      { transform: translateY(-12px) rotate(0.5deg); }
    50%      { transform: translateY(-6px) rotate(-0.3deg); }
    75%      { transform: translateY(-14px) rotate(0.2deg); }
  }

  @keyframes pulse-laser {
    0%, 100% { opacity: 0.7; box-shadow: 0 0 15px rgba(96,165,250,0.5), 0 0 30px rgba(96,165,250,0.2); }
    50%      { opacity: 1;   box-shadow: 0 0 25px rgba(96,165,250,0.8), 0 0 50px rgba(96,165,250,0.4), 0 0 80px rgba(96,165,250,0.15); }
  }

  @keyframes ring-pulse {
    0%, 100% { opacity: 0.4; transform: translateX(-50%) scaleX(1); }
    50%      { opacity: 0.8; transform: translateX(-50%) scaleX(1.08); }
  }

  /* ============================================
     ERROR CODE "404"
     ============================================ */
  .error-code {
    font-size: clamp(5rem, 18vw, 11rem);
    font-weight: 900;
    letter-spacing: 0.05em;
    line-height: 1;
    margin-bottom: 0.25rem;
    background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 40%, #f0abfc 70%, #60a5fa 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: shine 4s linear infinite, flicker 8s ease-in-out infinite;
    cursor: default;
    position: relative;
    user-select: none;
  }

  .error-code:hover {
    animation: glitch 0.15s steps(2) infinite;
  }

  @keyframes shine {
    0%   { background-position: 0% center; }
    100% { background-position: 200% center; }
  }

  @keyframes flicker {
    0%, 100% { opacity: 1; }
    92%      { opacity: 1; }
    93%      { opacity: 0.8; }
    94%      { opacity: 1; }
    96%      { opacity: 0.9; }
    97%      { opacity: 1; }
  }

  @keyframes glitch {
    0%  {
      text-shadow: 2px 0 #60a5fa, -2px 0 #f0abfc;
      transform: translate(0);
    }
    25% {
      text-shadow: -2px -1px #60a5fa, 2px 1px #f0abfc;
      transform: translate(-2px, 1px);
    }
    50% {
      text-shadow: 1px 2px #60a5fa, -1px -2px #f0abfc;
      transform: translate(1px, -1px);
    }
    75% {
      text-shadow: -1px 1px #60a5fa, 1px -1px #f0abfc;
      transform: translate(2px, 1px);
    }
    100% {
      text-shadow: 2px -2px #60a5fa, -2px 2px #f0abfc;
      transform: translate(-1px, -1px);
    }
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
    background: linear-gradient(90deg, transparent, rgba(99,102,241,0.4), transparent);
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
    letter-spacing: 0.01em;
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
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff;
    box-shadow: 0 0 20px rgba(79,70,229,0.3), 0 4px 15px rgba(79,70,229,0.2);
  }

  .btn-space-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 30px rgba(79,70,229,0.5), 0 6px 25px rgba(79,70,229,0.35);
    color: #fff;
    background: linear-gradient(135deg, #6366f1, #818cf8);
  }

  .btn-space-secondary {
    background: transparent;
    color: #94a3b8;
    border: 1px solid rgba(99,102,241,0.3);
    box-shadow: 0 0 10px rgba(99,102,241,0.1);
  }

  .btn-space-secondary:hover {
    transform: translateY(-2px);
    color: #e2e8f0;
    border-color: rgba(99,102,241,0.6);
    box-shadow: 0 0 20px rgba(99,102,241,0.25);
    background: rgba(99,102,241,0.08);
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
    border: 1px solid rgba(99,102,241,0.12);
    border-radius: 6px;
    background: rgba(0,0,0,0.3);
  }

  /* ============================================
     TIE FIGHTER
     ============================================ */
  .tie-fighter {
    position: fixed;
    z-index: 1;
    width: 40px;
    height: 40px;
    top: 18%;
    pointer-events: none;
    opacity: 0.5;
    filter: drop-shadow(0 0 6px rgba(96,165,250,0.4));
    animation: tie-fly 20s linear infinite;
  }

  @keyframes tie-fly {
    0%   { left: -60px;  transform: scaleX(1);  opacity: 0; }
    2%   { opacity: 0.5; }
    48%  { left: 105%;    transform: scaleX(1);  opacity: 0.5; }
    49%  { opacity: 0; }
    50%  { left: 105%;    transform: scaleX(-1); opacity: 0; }
    52%  { opacity: 0.5; }
    98%  { left: -60px;   transform: scaleX(-1); opacity: 0.5; }
    99%  { opacity: 0; }
    100% { left: -60px;   transform: scaleX(1);  opacity: 0; }
  }

  /* ============================================
     RESPONSIVE
     ============================================ */
  @media (min-width: 768px) {
    .death-star {
      width: 160px;
      height: 160px;
    }
    .death-star::after {
      width: 36px;
      height: 36px;
    }
    .death-star::before {
      height: 8px;
    }
    .hologram-ring {
      width: 190px;
      bottom: -22px;
    }
    .tie-fighter {
      width: 55px;
      height: 55px;
    }
  }

  @media (max-width: 480px) {
    .error-page {
      padding: 1.5rem 0.75rem 2rem;
    }
    .btn-wrap {
      flex-direction: column;
      align-items: center;
    }
    .btn-space-primary,
    .btn-space-secondary {
      width: 100%;
      max-width: 260px;
      justify-content: center;
    }
    .status-bar {
      font-size: 0.6rem;
      padding: 0.5rem 0.75rem;
      word-break: break-all;
    }
    .death-star {
      width: 90px;
      height: 90px;
    }
    .death-star::after {
      width: 22px;
      height: 22px;
      top: 20%;
      left: 26%;
    }
    .hologram-ring {
      width: 110px;
      bottom: -14px;
    }
  }
</style>

<!-- Star field canvas -->
<canvas id="star-canvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>

<!-- Nebula background -->
<div class="nebula"></div>

<!-- Scanlines overlay -->
<div class="scanlines"></div>

<!-- TIE Fighter SVG -->
<svg class="tie-fighter" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <!-- Left wing panel -->
  <rect x="2" y="10" width="16" height="60" rx="2" fill="#374151" stroke="#6b7280" stroke-width="0.8"/>
  <line x1="6" y1="10" x2="6" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="10" y1="10" x2="10" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="14" y1="10" x2="14" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="2" y1="22" x2="18" y2="22" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="2" y1="34" x2="18" y2="34" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="2" y1="46" x2="18" y2="46" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="2" y1="58" x2="18" y2="58" stroke="#4b5563" stroke-width="0.4"/>

  <!-- Right wing panel -->
  <rect x="62" y="10" width="16" height="60" rx="2" fill="#374151" stroke="#6b7280" stroke-width="0.8"/>
  <line x1="66" y1="10" x2="66" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="70" y1="10" x2="70" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="74" y1="10" x2="74" y2="70" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="62" y1="22" x2="78" y2="22" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="62" y1="34" x2="78" y2="34" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="62" y1="46" x2="78" y2="46" stroke="#4b5563" stroke-width="0.4"/>
  <line x1="62" y1="58" x2="78" y2="58" stroke="#4b5563" stroke-width="0.4"/>

  <!-- Struts connecting wings to cockpit -->
  <rect x="18" y="37" width="14" height="6" fill="#4b5563"/>
  <rect x="48" y="37" width="14" height="6" fill="#4b5563"/>

  <!-- Cockpit sphere -->
  <circle cx="40" cy="40" r="12" fill="#1f2937" stroke="#6b7280" stroke-width="1"/>
  <circle cx="40" cy="40" r="6" fill="#111827" stroke="#60a5fa" stroke-width="0.6" opacity="0.8"/>
  <!-- Cockpit window glow -->
  <circle cx="40" cy="40" r="2.5" fill="#60a5fa" opacity="0.7">
    <animate attributeName="opacity" values="0.5;0.9;0.5" dur="2s" repeatCount="indefinite"/>
  </circle>
</svg>

<!-- Main error page content -->
<div class="error-page" role="main">

  <!-- Death Star -->
  <div class="death-star-wrap" aria-hidden="true">
    <div class="death-star"></div>
    <div class="hologram-ring"></div>
  </div>

  <!-- 404 code -->
  <h1 class="error-code" aria-label="Error 404">404</h1>

  <!-- Imperial divider -->
  <div class="imperial-divider" aria-hidden="true">
    <span class="imperial-symbol">&#x2B21; senal perdida &#x2B21;</span>
  </div>

  <!-- Error messages -->
  <h2 class="error-title">Esta ruta fue consumida por el Lado Oscuro</h2>
  <p class="error-subtitle">
    Los escaneres imperiales no detectan esta pagina en ningun sector conocido de la galaxia.
    Puede que haya sido destruida, movida mas alla del Borde Exterior, o simplemente... nunca existio.
  </p>

  <!-- Action buttons -->
  <div class="btn-wrap">
    <a href="<?= htmlspecialchars($appUrl) ?>/dashboard" class="btn-space-primary" aria-label="Volver al panel principal">
      <i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i> Volver al Cuartel General
    </a>
    <button type="button" id="btn-go-back" class="btn-space-secondary" aria-label="Volver a la pagina anterior">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Ir atras
    </button>
  </div>

  <!-- Terminal status bar -->
  <div class="status-bar" role="status" aria-label="Estado del error">
    ERROR: HYP-404 &nbsp;|&nbsp; SECTOR: DESCONOCIDO &nbsp;|&nbsp; COORD: NULL &nbsp;|&nbsp; STATUS: CRITICO
  </div>
</div>

<script>
(function () {
  'use strict';

  /* ========================================
     STAR FIELD CANVAS
     ======================================== */
  var canvas = document.getElementById('star-canvas');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');

  var stars = [];
  var STAR_COUNT = 300;
  var colors = ['#ffffff', '#c7d2fe', '#ddd6fe', '#e0e7ff', '#a5b4fc'];

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }

  function initStars() {
    stars = [];
    for (var i = 0; i < STAR_COUNT; i++) {
      stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: 0.3 + Math.random() * 1.5,
        color: colors[Math.floor(Math.random() * colors.length)],
        phase: Math.random() * Math.PI * 2,
        speed: 0.3 + Math.random() * 0.7
      });
    }
  }

  function draw(time) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (var i = 0; i < stars.length; i++) {
      var s = stars[i];
      var alpha = 0.4 + 0.6 * ((Math.sin(time * 0.001 * s.speed + s.phase) + 1) / 2);

      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = s.color;
      ctx.globalAlpha = alpha;
      ctx.fill();
    }

    ctx.globalAlpha = 1;
    requestAnimationFrame(draw);
  }

  resize();
  initStars();
  requestAnimationFrame(draw);

  window.addEventListener('resize', function () {
    resize();
    initStars();
  });

  /* ========================================
     GO BACK BUTTON
     ======================================== */
  var btnBack = document.getElementById('btn-go-back');
  if (btnBack) {
    btnBack.addEventListener('click', function () {
      if (window.history.length > 1) {
        history.back();
      } else {
        window.location.href = '<?= htmlspecialchars($appUrl) ?>/dashboard';
      }
    });
  }
})();
</script>
