<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers.php';
?>
<footer>
  <div class="container py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">

    <div class="text-muted small d-flex align-items-center gap-2">
      © <?= date('Y') ?> Abiball · Erstellt von Moris Kehl 
      <a
        href="https://www.linkedin.com/in/moris-kehl/"
        target="_blank"
        rel="noopener"
        class="footer-linkedin"
        aria-label="LinkedIn – Moris Kehl"
        title="LinkedIn – Moris Kehl"
      >
        <!-- LinkedIn Icon (inline SVG, theme-safe) -->
        <svg xmlns="http://www.w3.org/2000/svg"
             width="16"
             height="16"
             viewBox="0 0 24 24"
             fill="currentColor">
          <path d="M4.98 3.5C4.98 4.88 3.88 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5ZM.5 8h4v16h-4V8Zm7.5 0h3.8v2.2h.05c.53-1 1.83-2.2 3.77-2.2 4.03 0 4.78 2.65 4.78 6.1V24h-4v-7.6c0-1.82-.03-4.16-2.54-4.16-2.54 0-2.93 1.98-2.93 4.03V24h-4V8Z"/>
        </svg>
      </a>
    </div>

    <div class="d-flex gap-3 small">
    <a class="text-muted" href="/Location.php">Location</a>
    <a class="text-muted" href="/login.php">Login</a>

    <?php if (basename($_SERVER['PHP_SELF']) === 'login.php'): ?>
      <a class="text-muted" href="/admin_login.php">Admin</a>
    <?php endif; ?>

    <a class="text-muted" href="/zahlung.php">Zahlung</a>
    <a class="text-muted" href="/impressum.php">Impressum</a>
    <a class="text-muted" href="/dashboard.php">Dashboard</a>
  </div>



  </div>
</footer>
