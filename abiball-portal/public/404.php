<?php
declare(strict_types=1);

// public/404.php
http_response_code(404);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/View/Layout.php';

Bootstrap::init();

Layout::header('Seite nicht gefunden – 404', 'Die angeforderte Seite existiert nicht.');
?>
<main class="bg-starfield" id="main-content">
  <div class="container py-5" style="max-width: 800px; min-height: 60vh; display: flex; align-items: center;">
    
    <div class="text-center mx-auto w-100">
      <div style="font-size: clamp(80px, 15vw, 160px); font-weight: 300; line-height: 1; color: var(--gold); opacity: 0.3;">
        404
      </div>
      
      <h1 class="h-serif mb-3 mt-4" style="font-size: clamp(32px, 5vw, 48px); font-weight: 300;">
        Seite nicht gefunden
      </h1>
      
      <p class="text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7; max-width: 600px; margin-left: auto; margin-right: auto;">
        Die angeforderte Seite existiert nicht oder wurde verschoben.
        Bitte überprüfe die URL oder kehre zur Startseite zurück.
      </p>
      
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
        <a class="btn btn-cta btn-cta-lg" href="/">Zur Startseite</a>
        <a class="btn btn-outline-secondary" href="javascript:history.back()">Zurück</a>
      </div>
      
      <div class="mt-5">
        <div class="text-muted small">Hilfreiche Links:</div>
        <div class="d-flex justify-content-center gap-3 flex-wrap mt-2">
          <a href="/login.php" class="text-muted small">Login</a>
          <span class="text-muted">•</span>
          <a href="/location/location.php" class="text-muted small">Location</a>
          <span class="text-muted">•</span>
          <a href="/zahlung.php" class="text-muted small">Zahlung</a>
          <span class="text-muted">•</span>
          <a href="/impressum.php" class="text-muted small">Impressum</a>
        </div>
      </div>
    </div>
    
  </div>
</main>
<?php
Layout::footer();
