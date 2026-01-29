<?php
declare(strict_types=1);

/**
 * LocationController - Standort und Anfahrt zur Veranstaltung
 * 
 * Zeigt die Adresse der Stadthalle Leonberg mit interaktiver Karte (Leaflet/OpenStreetMap).
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../View/Layout.php';

final class LocationController
{
    /**
     * Zeigt die Location-Seite mit Adresse und interaktiver Karte.
     */
    public static function show(): void
    {
        Bootstrap::init();

        // Feste Koordinaten der Stadthalle Leonberg
        $venueName = 'Stadthalle Leonberg';
        $address   = 'Römerstraße 110, 71229 Leonberg';


        $lat = 48.795269;
        $lon = 9.0147972;

        // Links für verschiedene Navigations-Apps generieren
        $latEnc  = rawurlencode((string)$lat);
        $lonEnc  = rawurlencode((string)$lon);
        $addrEnc = rawurlencode($address);

        $googleMap = "https://www.google.com/maps/search/?api=1&query={$addrEnc}";
        $appleMap  = "https://maps.apple.com/?q={$addrEnc}&ll={$latEnc},{$lonEnc}";
        $waze      = "https://waze.com/ul?ll={$latEnc}%2C{$lonEnc}&navigate=yes";
        $osmLink   = "https://www.openstreetmap.org/?mlat={$latEnc}&mlon={$lonEnc}#map=17/{$latEnc}/{$lonEnc}";

        Layout::header(
            'Abiball 2026 – Location & Anfahrt',
            'Alle Infos zur Stadthalle Leonberg: Adresse, Anfahrt und interaktive Karte für den Abiball 2026.'
        );
        Layout::breadcrumbStructuredData(['Startseite' => '/', 'Location' => '/location/location.php']);
        ?>

        <!-- Leaflet CSS -->
        <link
          rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""
        >
        <style>
          .map-shell{
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,.12);
            background: #fff;
          }
          #leafletMap{
            width: 100%;
            height: 440px; /* WICHTIG: ohne Höhe bleibt Leaflet unsichtbar */
          }
        </style>

        <main class="bg-starfield">
          <!-- Star layers -->
          <div class="stars-layer-1"></div>
          <div class="stars-layer-2"></div>
          <div class="stars-layer-3"></div>

          <div class="container py-5" style="max-width: 1100px;">

            <div class="text-center mx-auto" style="max-width: 820px; padding-top: 18px; padding-bottom: 24px;">
              <div class="glass-hero-header sm mb-5 animate-fade-up">
                <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
                  Location<br>
                  <span style="font-style: italic;"><?= htmlspecialchars($venueName) ?></span>
                </h1>

                <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.7;">
                  Adresse, Anfahrt und Kartenansicht für den Abiball 2026.
                </p>


              </div>
            </div>

            <div class="card mx-auto" style="max-width: 980px;">
              <div class="card-body p-4 p-md-5">

                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">
                      Adresse
                    </div>
                    <div class="h-serif" style="font-size: 1.7rem; font-weight: 300; margin-top: 6px;">
                      <?= htmlspecialchars($venueName) ?>
                    </div>
                    <div class="text-muted" style="margin-top: 6px; font-size: 1.05rem;">
                      <?= htmlspecialchars($address) ?>
                    </div>
                  </div>

                  <span class="badge badge-gold rounded-pill">
                    Abiball 2026
                  </span>
                </div>

                <hr class="my-4">

                <div class="row g-4 align-items-stretch">
                  <div class="col-12 col-lg-7">
                    <div class="map-shell">
                      <div id="leafletMap"></div>
                    </div>
                    <div class="text-muted small mt-2">
                      <a class="text-muted" href="<?= htmlspecialchars($osmLink, ENT_QUOTES) ?>" target="_blank" rel="noopener" style="text-decoration:underline;">
                        In OpenStreetMap öffnen
                      </a>
                    </div>
                  </div>

                  <div class="col-12 col-lg-5">
                    <div class="card h-100">
                      <div class="card-body p-4">
                        <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">
                          Navigation
                        </div>
                        <div class="h-serif" style="font-size: 1.4rem; font-weight: 300; margin-top: 6px;">
                          Route starten
                        </div>

                        <p class="text-muted mt-2" style="line-height:1.7;">
                          Wähle deinen Kartendienst.
                        </p>

                        <div class="d-grid gap-2 mt-3">
                          <a class="btn btn-cta btn-shimmer" href="<?= htmlspecialchars($googleMap, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                            Google Maps
                          </a>
                          <a class="btn btn-cta btn-shimmer" href="<?= htmlspecialchars($appleMap, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                            Apple Karten
                          </a>
                          <a class="btn btn-cta btn-shimmer" href="<?= htmlspecialchars($waze, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                            Waze
                          </a>
                        </div>

                        <hr class="my-4">

                        <div class="text-muted small">Adresse</div>
                        <div class="fw-semibold"><?= htmlspecialchars($address) ?></div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </main>

        <!-- Leaflet JS -->
        <script
          src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""
        ></script>

        <script>
        (function () {
          const lat = <?= json_encode((float)$lat) ?>;
          const lon = <?= json_encode((float)$lon) ?>;

          const map = L.map("leafletMap", {
            zoomControl: true,
            scrollWheelZoom: false
          }).setView([lat, lon], 16);

          L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 20,
            subdomains: "abc",
            detectRetina: true,
            attribution: "&copy; OpenStreetMap"
          }).addTo(map);

          L.marker([lat, lon]).addTo(map).bindPopup(<?= json_encode($venueName) ?>);

          // Korrektes Rendering nach dem Laden der Seite sicherstellen
          setTimeout(() => map.invalidateSize(), 80);
        })();
        </script>

        <?php
        Layout::footer();
    }
}
