<?php
declare(strict_types=1);

// src/Controller/LandingController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../Auth/AuthContext.php';

final class LandingController
{
    public static function show(): void
    {
        Bootstrap::init();

        $mainId     = trim(AuthContext::mainId());
        $isLoggedIn = ($mainId !== '');

        Layout::header('Abiball');
        ?>
        <style>
          /* CTA responsive */
          .landing-cta{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            white-space:nowrap;
          }
          @media (max-width: 770px){
            .landing-cta{ width:100%; }
            .landing-cta-wrap{
              width:100%;
              padding-left:.25rem;
              padding-right:.25rem;
            }
          }

          /* Mobile: info blocks aligned (icons under each other, text starts same x) */
          @media (max-width: 770px){
            .landing-info-row{
              text-align:left !important;
            }

            .landing-info-item{
              display:grid;
              grid-template-columns: 64px 1fr;
              column-gap: 16px;
              align-items:center;
              padding: 16px 14px;
              border: 1px solid var(--border);
              border-radius: 16px;
              background: rgba(255,255,255,.04);
            }
            .landing-info-item + .landing-info-item{
              margin-top: 12px;
            }

            .landing-info-icon{
              width:64px;
              height:64px;
              border-radius:50%;
              display:flex;
              align-items:center;
              justify-content:center;
              background: rgba(201,162,39,.12);
            }

            /* Prevent "stretched" feeling / keep icon crisp */
            .landing-info-icon svg{
              width:26px;
              height:26px;
              display:block;
              flex: 0 0 auto;
            }

            .landing-info-text{
              min-width:0;
            }
            .landing-info-text .text-muted.small{
              margin-bottom: 2px;
            }
            .landing-info-text .fw-semibold{
              line-height: 1.25;
            }
          }
        </style>

        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 1100px;">
            <div class="text-center mx-auto" style="max-width: 820px; padding-top: 24px; padding-bottom: 28px;">

              <h1 class="h-serif mb-3" style="font-size: clamp(42px, 5.5vw, 86px); font-weight: 300; line-height: .95;">
                Abiball<br>
                <span style="font-style: italic;">Ein Abend der Eleganz</span>
              </h1>

              <!-- Countdown Timer -->
              <div class="countdown-container mb-4" style="display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
                <div class="countdown-box" style="min-width: 80px; padding: 12px 16px; border-radius: 12px; background: rgba(201,162,39,.08); border: 1px solid rgba(201,162,39,.2);">
                  <div id="countdown-days" style="font-size: 2rem; font-weight: 600; color: var(--gold); line-height: 1;">--</div>
                  <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text); opacity: 0.6; margin-top: 4px;">Tage</div>
                </div>
                <div class="countdown-box" style="min-width: 80px; padding: 12px 16px; border-radius: 12px; background: rgba(201,162,39,.08); border: 1px solid rgba(201,162,39,.2);">
                  <div id="countdown-hours" style="font-size: 2rem; font-weight: 600; color: var(--gold); line-height: 1;">--</div>
                  <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text); opacity: 0.6; margin-top: 4px;">Stunden</div>
                </div>
                <div class="countdown-box" style="min-width: 80px; padding: 12px 16px; border-radius: 12px; background: rgba(201,162,39,.08); border: 1px solid rgba(201,162,39,.2);">
                  <div id="countdown-minutes" style="font-size: 2rem; font-weight: 600; color: var(--gold); line-height: 1;">--</div>
                  <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text); opacity: 0.6; margin-top: 4px;">Minuten</div>
                </div>
                <div class="countdown-box" style="min-width: 80px; padding: 12px 16px; border-radius: 12px; background: rgba(201,162,39,.08); border: 1px solid rgba(201,162,39,.2);">
                  <div id="countdown-seconds" style="font-size: 2rem; font-weight: 600; color: var(--gold); line-height: 1;">--</div>
                  <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text); opacity: 0.6; margin-top: 4px;">Sekunden</div>
                </div>
              </div>

              <script>
                (function() {
                  // Event-Datum: 10.07.2026 um 17:00 Uhr
                  const eventDate = new Date('2026-07-10T17:00:00').getTime();
                  
                  function updateCountdown() {
                    const now = new Date().getTime();
                    const distance = eventDate - now;
                    
                    if (distance < 0) {
                      document.getElementById('countdown-days').textContent = '0';
                      document.getElementById('countdown-hours').textContent = '0';
                      document.getElementById('countdown-minutes').textContent = '0';
                      document.getElementById('countdown-seconds').textContent = '0';
                      return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    document.getElementById('countdown-days').textContent = days;
                    document.getElementById('countdown-hours').textContent = hours;
                    document.getElementById('countdown-minutes').textContent = minutes;
                    document.getElementById('countdown-seconds').textContent = seconds;
                  }
                  
                  updateCountdown();
                  setInterval(updateCountdown, 1000);
                })();
              </script>

              <p class="text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7;">
                Alle wichtigen Informationen rund um Tickets, Sitzplätze und organisatorische Hinweise findest du hier im Portal.
                Melde dich an, um deinen Status einzusehen und Details im Dashboard aufzurufen.
              </p>

              <div class="d-flex justify-content-center gap-3 flex-wrap pt-2 landing-cta-wrap">
                <?php if ($isLoggedIn): ?>
                  <a class="btn btn-cta btn-cta-lg landing-cta" href="/dashboard.php">Zum Dashboard</a>
                <?php else: ?>
                  <a class="btn btn-cta btn-cta-lg landing-cta" href="/login.php">Zum Login</a>
                <?php endif; ?>
              </div>
            </div>

            <div class="card mx-auto" style="max-width: 900px;">
              <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap margin-bottom-4">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">
                      Kurzüberblick
                    </div>
                    <p class="text-muted mb-4" style="font-size:1.1rem;line-height:1.7;">
                      Der Abiball 2026 markiert den feierlichen Abschluss der gesamten Stufe
                      des Beruflichen Gymnasiums des BSZ Leonberg.
                      In festlichem Rahmen finden an diesem Abend die Zeugnisvergabe
                      sowie ein gemeinsames Programm statt, das den schulischen Weg würdigt
                      und den Übergang in einen neuen Lebensabschnitt feiert.
                    </p>
                  </div>
                </div>

                <hr class="my-4">

                <div class="row g-4 text-center mt-4 landing-info-row">

                  <!-- Datum -->
                  <div class="col-12 col-md-4">
                    <div class="landing-info-item">
                      <div class="landing-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="var(--primary)" viewBox="0 0 24 24">
                          <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1.5A2.5 2.5 0 0 1 22 6.5v13A2.5 2.5 0 0 1 19.5 22h-15A2.5 2.5 0 0 1 2 19.5v-13A2.5 2.5 0 0 1 4.5 4H6V3a1 1 0 0 1 1-1Zm12.5 8h-15v9.5a.5.5 0 0 0 .5.5h14a.5.5 0 0 0 .5-.5V10Z"/>
                        </svg>
                      </div>
                      <div class="landing-info-text">
                        <div class="text-muted small">Datum</div>
                        <div class="fw-semibold">10.07.2026</div>
                      </div>
                    </div>
                  </div>

                  <!-- Einlass -->
                  <div class="col-12 col-md-4">
                    <div class="landing-info-item">
                      <div class="landing-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="var(--primary)" viewBox="0 0 24 24">
                          <path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm1 5a1 1 0 0 0-2 0v5.4l3.3 3.3a1 1 0 1 0 1.4-1.4L13 11.6V7Z"/>
                        </svg>
                      </div>
                      <div class="landing-info-text">
                        <div class="text-muted small">Einlass</div>
                        <div class="fw-semibold">17:00 Uhr</div>
                      </div>
                    </div>
                  </div>

                  <!-- Ort -->
                  <div class="col-12 col-md-4">
                    <a href="/location/location.php" class="d-block text-decoration-none" style="color:inherit;">
                      <div class="landing-info-item">
                        <div class="landing-info-icon">
                          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="var(--primary)" viewBox="0 0 24 24">
                            <path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/>
                          </svg>
                        </div>
                        <div class="landing-info-text">
                          <div class="text-muted small">Ort</div>
                          <div class="fw-semibold">
                            Stadthalle Leonberg<br>
                            <span class="text-muted">Römerstraße 110</span>
                          </div>
                        </div>
                      </div>
                    </a>
                  </div>

                </div>

                <hr class="my-4">

                <div class="text-muted small mb-2" style="letter-spacing:.22em; text-transform:uppercase;">
                  Die Stadthalle Leonberg
                </div>

                <div style="border-radius: 18px; overflow: hidden; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.03);">
                  <img
                    src="/images/saal.jpeg"
                    alt="Saalplan"
                    loading="lazy"
                    style="display:block; width:100%; height:auto; max-width:100%;"
                  >
                </div>

              </div>
            </div>

          </div>
        </main>
        <?php
        Layout::footer();
    }
}
