<?php
declare(strict_types=1);

// src/Controller/ZahlungController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Service/PricingService.php';
require_once __DIR__ . '/../Auth/AuthContext.php';

final class ZahlungController
{
    public static function show(): void
    {
        Bootstrap::init();

        // Robust: Login-Status über mainId (statt nur isLoggedIn)
        $participantId = trim(AuthContext::mainId());
        $isLoggedIn    = ($participantId !== '');

        $name    = trim(AuthContext::name());
        $tickets = AuthContext::ticketCount();

        // -------------------------------------------------------
        // Beträge berechnen (Overrides berücksichtigt)
        // -------------------------------------------------------
        $amountDue  = null;
        $amountPaid = null;
        $amountOpen = null;

        if ($participantId !== '') {
            $due       = PricingService::amountDueForMainId($participantId);
            $amountDue = (int)($due['amount_due'] ?? 0);

            $amountPaid = ParticipantsRepository::amountPaidForMainId($participantId);
            $amountOpen = max(0, $amountDue - (int)$amountPaid);
        }

        // -------------------------------------------------------
        // Orga-Daten
        // -------------------------------------------------------
        $recipient = 'Bahaa Albasha';
        $iban      = 'DE76 6035 0130 1002 6462 65';
        $bic       = 'BBKRDE6BXXX';
        $bankName  = 'Kreissparkasse Böblingen';

        // -------------------------------------------------------
        // Verwendungszweck
        // -------------------------------------------------------
        if ($participantId !== '' && $name !== '' && $tickets > 0) {
            $purpose =
                'Abiball 2026'
                . ' | Name: ' . $name
                . ' | ID: ' . $participantId
                . ' | Tickets: ' . $tickets;
        } else {
            $purpose =
                'Abiball 2026'
                . ' | Name: <NAME>'
                . ' | ID: <TEILNEHMER-ID>'
                . ' | Tickets: <ANZAHL>';
        }

        Layout::header('Abiball – Zahlung');
        ?>
        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 1100px;">

            <div class="text-center mx-auto" style="max-width: 820px; padding-top: 18px; padding-bottom: 26px;">
              <h1 class="h-serif mb-3" style="font-size: clamp(34px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
                Zahlung<br>
                <span style="font-style: italic;">Überweisung</span>
              </h1>

              <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.7;">
                Hier findest du die Bankdaten sowie ein Beispiel für den Verwendungszweck.
              </p>

              <style>
                .zahlung-cta{
                  display:inline-flex;
                  align-items:center;
                  justify-content:center;
                  white-space:nowrap;
                }
                @media (max-width: 575.98px){
                  .zahlung-cta{ width:100%; }
                  .zahlung-cta-wrap{
                    width:100%;
                    padding-left:.25rem;
                    padding-right:.25rem;
                  }
                }
              </style>

              <div class="d-flex justify-content-center gap-3 flex-wrap pt-2 zahlung-cta-wrap">
                <?php if ($isLoggedIn): ?>
                  <a class="btn btn-cta btn-cta-lg zahlung-cta" href="/dashboard.php">Zum Dashboard</a>
                <?php else: ?>
                  <a class="btn btn-cta btn-cta-lg zahlung-cta" href="/login.php">Zum Login</a>
                <?php endif; ?>
                <a class="btn btn-cta btn-cta-lg zahlung-cta" href="/">Zur Startseite</a>
              </div>
            </div>

            <div class="card mx-auto" style="max-width: 980px;">
              <div class="card-body p-4 p-md-5">

                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">
                      Bankverbindung
                    </div>

                    <div class="h-serif" style="font-size: 1.7rem; font-weight: 300; margin-top: 6px;">
                      Abiball 2026
                    </div>

                    <div class="text-muted" style="margin-top: 6px; font-size: 1.05rem;">
                      Bitte überweise den fälligen Betrag mit korrektem Verwendungszweck.
                    </div>

                    <?php if ($amountDue !== null): ?>
                      <div class="mt-2 text-muted" style="font-size: 1.05rem;">
                        Fällig:
                        <span class="fw-semibold"><?= number_format((float)$amountDue, 2, ',', '.') ?> €</span>
                        <span class="text-muted"> · Bezahlt: <?= number_format((float)($amountPaid ?? 0), 2, ',', '.') ?> €</span>
                        <span class="text-muted"> · Offen: <?= number_format((float)($amountOpen ?? 0), 2, ',', '.') ?> €</span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <span class="badge rounded-pill"
                        style="background: rgba(201,162,39,.12); border: 1px solid rgba(201,162,39,.28); color: var(--primary);">
                    Abiball 2026
                  </span>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                  <div class="col-12 col-lg-6">
                    <div class="text-muted small mb-1">Empfänger</div>
                    <div class="fw-semibold"><?= htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="mt-3 text-muted small mb-1">IBAN</div>
                    <div class="fw-semibold"
                         style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                      <?= htmlspecialchars($iban, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="mt-3 text-muted small mb-1">BIC</div>
                    <div class="fw-semibold"
                         style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                      <?= htmlspecialchars($bic, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="mt-3 text-muted small mb-1">Bank</div>
                    <div class="fw-semibold"><?= htmlspecialchars($bankName, ENT_QUOTES, 'UTF-8') ?></div>
                  </div>

                  <div class="col-12 col-lg-6">
                    <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">
                      Beispiel Verwendungszweck
                    </div>

                    <div class="p-soft mt-2"
                         style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                      <?= nl2br(htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8')) ?>
                    </div>

                    <?php if ($participantId !== '' && $name !== '' && $tickets > 0): ?>
                      <div class="text-muted small mt-2">
                        Automatisch erkannt aus deinem Konto.
                      </div>
                    <?php else: ?>
                      <div class="text-muted small mt-2">
                        Hinweis: Bitte ersetze die Platzhalter im Verwendungszweck manuell.
                      </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <ul class="text-muted mb-0" style="line-height: 1.7;">
                      <li>Verwendungszweck muss korrekt sein.</li>
                      <li>Ohne korrekten Verwendungszweck keine automatische Zuordnung.</li>
                      <li>Bearbeitungszeit: 1–2 Werktage.</li>
                    </ul>
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
