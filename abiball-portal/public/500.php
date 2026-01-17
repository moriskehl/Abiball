<?php
/**
 * Fehlerseite 500 - Interner Serverfehler
 */
declare(strict_types=1);

http_response_code(500);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/View/Layout.php';

Bootstrap::init();

Layout::header('Serverfehler – 500', 'Ein interner Serverfehler ist aufgetreten.');
?>
<main class="bg-starfield" id="main-content">
  <div class="container py-5" style="max-width: 800px; min-height: 60vh; display: flex; align-items: center;">
    
    <div class="text-center mx-auto w-100">
      <div style="font-size: clamp(80px, 15vw, 160px); font-weight: 300; line-height: 1; color: var(--danger); opacity: 0.3;">
        500
      </div>
      
      <h1 class="h-serif mb-3 mt-4" style="font-size: clamp(32px, 5vw, 48px); font-weight: 300;">
        Interner Serverfehler
      </h1>
      
      <p class="text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7; max-width: 600px; margin-left: auto; margin-right: auto;">
        Es ist ein unerwarteter Fehler aufgetreten. 
        Wir arbeiten bereits an der Lösung. Bitte versuche es in wenigen Minuten erneut.
      </p>
      
      <div class="card mx-auto mb-4" style="max-width: 600px; background: rgba(255,77,90,.08); border-color: rgba(255,77,90,.25);">
        <div class="card-body p-4">
          <div class="text-muted small" style="line-height: 1.7;">
            <strong>Was kannst du tun?</strong>
            <ul class="mt-2 mb-0 text-start" style="padding-left: 1.5rem;">
              <li>Seite neu laden (F5)</li>
              <li>Browser-Cache leeren</li>
              <li>In wenigen Minuten erneut versuchen</li>
              <li>Falls das Problem weiterhin besteht, kontaktiere uns: 
                <a href="mailto:moris.kehl@gmail.com" class="text-danger">moris.kehl@gmail.com</a>
              </li>
            </ul>
          </div>
        </div>
      </div>
      
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
        <a class="btn btn-cta btn-cta-lg" href="/">Zur Startseite</a>
        <button class="btn btn-outline-secondary" onclick="window.location.reload()">Seite neu laden</button>
      </div>
    </div>
    
  </div>
</main>
<?php
Layout::footer();
