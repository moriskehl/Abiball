<?php
declare(strict_types=1);

/**
 * Location View - Darstellung der Veranstaltungsort-Seite
 * 
 * Zeigt Adresse, Karteneinbettung und Navigationslinks zu verschiedenen
 * Kartendiensten (Waze, Google Maps, Apple Maps, OpenStreetMap).
 * 
 * Erwartete Variablen aus dem Controller:
 * $venueName, $address, $lat, $lon,
 * $waze, $googleMap, $appleMap, $osmEmbed, $osmLink
 */
?>

<main class="bg-starfield">
  <div class="container py-5" style="max-width: 1100px;">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
      <div>
        <div class="text-muted small mb-2" style="letter-spacing:.30em; text-transform:uppercase;">
          Anreise
        </div>
        <h1 class="h-serif mb-2" style="font-size: clamp(36px, 4.6vw, 64px); font-weight: 300; line-height: 1.05;">
          Location
        </h1>
        <div class="text-muted" style="font-size: 1.05rem; line-height: 1.7;">
          <div class="fw-semibold" style="color: var(--text) !important;">
            <?= htmlspecialchars($venueName, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($waze, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
          Waze
        </a>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($googleMap, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
          Google Maps
        </a>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($appleMap, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
          Apple Maps
        </a>
      </div>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-12">
        <div class="card overflow-hidden">
          <div class="card-body p-0">
            <div style="aspect-ratio: 16 / 9; width: 100%;">
              <iframe
                title="OpenStreetMap"
                src="<?= htmlspecialchars($osmEmbed, ENT_QUOTES, 'UTF-8') ?>"
                style="border: 0; width: 100%; height: 100%;"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
            </div>
          </div>

          <div class="card-body p-4 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
              <div class="text-muted" style="line-height: 1.6;">
                Karte wird extern von OpenStreetMap geladen. Für Navigation bitte oben Waze/Google/Apple nutzen.
              </div>

              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= htmlspecialchars($googleMap, ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener noreferrer">
                  Navigation starten
                </a>
                <a class="btn btn-ghost" href="<?= htmlspecialchars($osmLink, ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener noreferrer">
                  OSM öffnen
                </a>
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-12 col-md-4">
                <div class="text-muted small">Adresse</div>
                <div class="fw-semibold"><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="col-12 col-md-4">
                <div class="text-muted small">Koordinaten</div>
                <div class="fw-semibold">
                  <?= htmlspecialchars((string)$lat, ENT_QUOTES, 'UTF-8') ?>,
                  <?= htmlspecialchars((string)$lon, ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="text-muted small">Hinweis</div>
                <div class="fw-semibold">Bitte rechtzeitig anreisen.</div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

  </div>
</main>
