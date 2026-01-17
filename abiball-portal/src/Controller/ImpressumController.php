<?php
declare(strict_types=1);

/**
 * ImpressumController - Impressum und rechtliche Angaben
 * 
 * Zeigt die gesetzlich erforderlichen Angaben gemäß TMG und MStV.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../View/Layout.php';

final class ImpressumController
{
    /**
     * Zeigt die Impressum-Seite mit rechtlichen Angaben.
     */
    public static function show(): void
    {
        Bootstrap::init();

        Layout::header('Impressum', 'Impressum und rechtliche Angaben zum Abiball 2026 Portal des BSZ Leonberg.');
        Layout::breadcrumbStructuredData(['Startseite' => '/', 'Impressum' => '/impressum.php']);
        ?>
        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 1000px;">

            <div class="text-center mx-auto mb-5" style="max-width: 820px; padding-top: 18px; padding-bottom: 24px;">
              <h1 class="h-serif mb-3"
                  style="font-size: clamp(36px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
                Impressum
              </h1>
              <p class="text-muted" style="font-size: 1.05rem; line-height: 1.7;">
                Angaben gemäß § 5 TMG und § 18 MStV
              </p>
            </div>

            <div class="card mx-auto" style="max-width: 900px;">
              <div class="card-body p-4 p-md-5">

                <!-- Verantwortlich -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Verantwortlich
                  </div>
                  <div class="fw-semibold">Abiball 2026 – BSZ Leonberg</div>
                  <div class="text-muted">
                    Organisationsteam Abiball 2026<br>
                    Berufliches Schulzentrum Leonberg
                  </div>
                </div>

                <hr class="my-4">

                <!-- Anschrift -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Anschrift
                  </div>
                  <div class="text-muted">
                    BSZ Leonberg<br>
                    Frockentalweg 8<br>
                    71229 Leonberg<br>
                    Deutschland
                  </div>
                </div>

                <hr class="my-4">

                <!-- Kontakt -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Kontakt
                  </div>
                  <div class="text-muted">
                    E-Mail: <span class="fw-semibold">abitur2026.leonberg@web.de</span><br>
                   
                  </div>
                </div>

                <hr class="my-4">

                <!-- Vertretungsberechtigt -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Vertretungsberechtigte Person
                  </div>
                  <div class="text-muted">
                    <span class="fw-semibold">Moris Kehl</span><br>
                    
                  </div>
                </div>

                <hr class="my-4">

                <!-- Haftung -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Haftung für Inhalte
                  </div>
                  <p class="text-muted mb-0" style="line-height: 1.7;">
                    Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte
                    auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich.
                    Nach §§ 8 bis 10 TMG sind wir jedoch nicht verpflichtet, übermittelte
                    oder gespeicherte fremde Informationen zu überwachen oder nach
                    Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
                  </p>
                </div>

                <hr class="my-4">

                <!-- Haftung Links -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Haftung für Links
                  </div>
                  <p class="text-muted mb-0" style="line-height: 1.7;">
                    Unser Angebot enthält Links zu externen Websites Dritter, auf deren
                    Inhalte wir keinen Einfluss haben. Deshalb können wir für diese
                    fremden Inhalte auch keine Gewähr übernehmen.
                  </p>
                </div>

                <hr class="my-4">

                <!-- Urheberrecht -->
                <div class="mb-4">
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Urheberrecht
                  </div>
                  <p class="text-muted mb-0" style="line-height: 1.7;">
                    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen
                    Seiten unterliegen dem deutschen Urheberrecht. Beiträge Dritter sind
                    als solche gekennzeichnet.
                  </p>
                  <br>
                  <div class="text-muted">
                    Veranstaltungsbild auf der Homepage:<br>
                    Stadthalle Leonberg –
                    <a href="https://www.stadthalle-leonberg.de/"
                        target="_blank"
                        rel="noopener noreferrer">
                        stadthalle-leonberg.de
                    </a>
                    <br>
                    </div>

                    <div class="text-muted mt-3">
                    Icons und technische Referenzen:
                    Ressourcen der World Wide Web Consortium (W3C),
                    <a href="https://www.w3.org/"
                        target="_blank"
                        rel="noopener noreferrer">
                        w3.org
                    </a>
                    </div>

                </div>


                <hr class="my-4">

                <!-- Technische Umsetzung -->
                <div>
                  <div class="text-muted small mb-1"
                       style="letter-spacing:.22em; text-transform:uppercase;">
                    Technische Umsetzung
                  </div>
                  <div class="text-muted">
                    Webanwendung für den Abiball 2026<br>
                    Entwicklung & Betrieb: <span class="fw-semibold">
                                                Moris Kehl
                                                ·
                                                <a href="https://www.linkedin.com/in/DEIN-LINKEDIN-USERNAME"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-decoration-none">
                                                    LinkedIn
                                                </a>
                                                </span><br>
                    Hosting: <span class="fw-semibold">DigitalOcean</span>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </main>
        <?php
        Layout::footer();
    }
}
