<?php
declare(strict_types=1);

// src/Controller/DashboardController.php

require_once __DIR__ . '/../Security/SessionGuard.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Service/ParticipantService.php';
require_once __DIR__ . '/../Service/PricingService.php';
require_once __DIR__ . '/../Service/SeatingService.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class DashboardController
{
    public static function show(): void
    {
        requireLogin();

        $mainId = (string)($_SESSION['main_id'] ?? '');

        $grp = ParticipantService::getMainAndCompanions($mainId);
        $main = $grp['main'];
        $companions = $grp['companions'];

        // Gesamtpreis (Soll) via PricingService (inkl. Overrides)
        $calc = PricingService::amountDueForMainId($mainId);
        $amountDue = (int)($calc['amount_due'] ?? 0);

        $amountPaid = ParticipantsRepository::amountPaidForMainId($mainId);
        $open = $amountDue - $amountPaid;
        if ($open < 0) $open = 0;

        // Notizen (aus SeatingService)
        $seating = SeatingService::load($mainId);
        $personNotes = $seating['person_notes'] ?? [];
        if (!is_array($personNotes)) $personNotes = [];

        Layout::header('Abiball – Dashboard');
        ?>
        <main class="bg-starfield">
          <div class="container py-4" style="max-width: 1100px;">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div>
                <h1 class="h-serif mb-1" style="font-size: 2rem; font-weight: 300;">Dein Bereich</h1>
                <div class="text-muted" style="font-size:.95rem;">
                  Übersicht deiner Daten, Ticketpreise (Standard: <?= (int)PricingService::DEFAULT_TICKET_PRICE ?> €), Sitzgruppen und Zahlungsstand.
                </div>
              </div>
              <a class="btn btn-outline-danger btn-sm" href="/logout.php">Logout</a>
            </div>

            <div class="row g-3">
              <div class="col-12 col-lg-7">

                <!-- Hauptgast -->
                <div class="card mb-3">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                      <div>
                        <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: 0.5rem;">Hauptgast</div>
                        <?php if ($main): ?>
                          <div class="fw-semibold" style="font-size:1.15rem;"><?= e((string)($main['name'] ?? '')) ?></div>
                        <?php else: ?>
                          <div class="text-muted">Hauptgast nicht gefunden.</div>
                        <?php endif; ?>
                      </div>

                      <?php if ($main): ?>
                        <?php
                          $pid = (string)($main['id'] ?? '');
                          $collapseId = 'note_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pid);
                          $ticket = PricingService::ticketForParticipantId($pid);
                          $pPrice = (int)($ticket['price'] ?? PricingService::DEFAULT_TICKET_PRICE);
                          $pReason = trim((string)($ticket['reason'] ?? ''));
                        ?>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                          <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                          <a href="/ticket/pdf.php?pid=<?= e($pid) ?>" class="btn btn-sm btn-outline-primary" target="_blank">Ticket (PDF)</a>

                          <button class="note-btn" type="button"
                                  data-bs-toggle="collapse"
                                  data-bs-target="#<?= e($collapseId) ?>"
                                  aria-expanded="false"
                                  aria-controls="<?= e($collapseId) ?>"
                                  title="Notiz">
                            <span class="note-icon" aria-hidden="true">
                              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
                              </svg>
                            </span>
                          </button>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if ($main): ?>
                      <div class="mt-3 p-soft">
                        <div class="d-flex justify-content-between align-items-center">
                          <div class="text-muted">Ticketpreis</div>
                          <div class="fw-semibold"><?= e((string)$pPrice) ?> €</div>
                        </div>

                        <?php if ($pReason !== ''): ?>
                          <div class="mt-2">
                            <div class="text-muted small">Grund</div>
                            <div class="small"><?= e($pReason) ?></div>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="collapse mt-3" id="<?= e($collapseId) ?>">
                        <form method="post" action="/dashboard_notes_save.php">
                          <?= Csrf::inputField() ?>
                          <input type="hidden" name="pid" value="<?= e($pid) ?>">
                          <label class="form-label mb-1">Notiz (im Portal)</label>
                          <textarea class="form-control form-control-sm" rows="2" name="note"
                            placeholder="z.B. Hinweise, Ansprechpartner, Besonderheiten …"><?= e((string)($personNotes[$pid] ?? '')) ?></textarea>
                          <button class="btn btn-save btn-sm mt-2" type="submit">Notiz speichern</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Begleitpersonen -->
                <div class="card mb-3">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div>
                        <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: 0.5rem;">Begleitpersonen</div>
                        <div class="h6 mb-0">Übersicht</div>
                      </div>
                    </div>

                    <?php if (!$companions): ?>
                      <div class="text-muted mt-3">Keine Begleitpersonen.</div>
                    <?php else: ?>
                      <div class="list-group list-group-flush mt-3">
                        <?php foreach ($companions as $c): ?>
                          <?php
                            $pid = (string)($c['id'] ?? '');
                            $collapseId = 'note_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pid);

                            $ticket = PricingService::ticketForParticipantId($pid);
                            $pPrice = (int)($ticket['price'] ?? PricingService::DEFAULT_TICKET_PRICE);
                            $pReason = trim((string)($ticket['reason'] ?? ''));
                          ?>
                          <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                              <div>
                                <div class="fw-semibold"><?= e((string)($c['name'] ?? '')) ?></div>
                                <div class="text-muted small">
                                  ID: <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                                  <span class="mx-2">•</span>
                                  Ticketpreis: <span class="fw-semibold"><?= e((string)$pPrice) ?> €</span>
                                </div>

                                <?php if ($pReason !== ''): ?>
                                  <div class="mt-2">
                                    <div class="text-muted small">Grund</div>
                                    <div class="small"><?= e($pReason) ?></div>
                                  </div>
                                <?php endif; ?>
                              </div>

                              <div class="d-flex gap-2 align-items-center flex-wrap">
                                <a href="/ticket/pdf.php?pid=<?= e($pid) ?>" class="btn btn-sm btn-outline-primary" target="_blank">Ticket (PDF)</a>

                                <button class="note-btn" type="button"
                                  data-bs-toggle="collapse"
                                  data-bs-target="#<?= e($collapseId) ?>"
                                  aria-expanded="false"
                                  aria-controls="<?= e($collapseId) ?>"
                                  title="Notiz">
                                  <span class="note-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                      <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
                                    </svg>
                                  </span>
                                </button>
                              </div>
                            </div>

                            <div class="collapse mt-3" id="<?= e($collapseId) ?>">
                              <form method="post" action="/dashboard_notes_save.php">
                                <?= Csrf::inputField() ?>
                                <input type="hidden" name="pid" value="<?= e($pid) ?>">
                                <label class="form-label mb-1">Notiz (im Portal)</label>
                                <textarea class="form-control form-control-sm" rows="2" name="note"
                                  placeholder="z.B. Hinweise, Allergien, Sitzwunsch …"><?= e((string)($personNotes[$pid] ?? '')) ?></textarea>
                                <button class="btn btn-save btn-sm mt-2" type="submit">Notiz speichern</button>
                              </form>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

              </div>

              <div class="col-12 col-lg-5">
                <!-- Zahlung -->
                <div class="card mb-3">
                  <div class="card-body p-4">
                    <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: 0.5rem;">Zahlung</div>
                    <div class="h6 mb-3">Zahlungsübersicht</div>

                    <div class="d-flex justify-content-between align-items-center">
                      <div class="text-muted">Gesamtpreis (Soll)</div>
                      <div class="fw-semibold"><?= e((string)$amountDue) ?> €</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div class="text-muted">Gezahlt (Ist)</div>
                      <div class="fw-semibold"><?= e((string)$amountPaid) ?> €</div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                      <div class="text-muted">Offen</div>
                      <div class="fw-bold <?= $open > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= e((string)$open) ?> €
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Aktionen -->
                <div class="card">
                  <div class="card-body p-4">
                    <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: 0.5rem;">Aktionen</div>
                    
                    <div class="d-flex gap-2 flex-wrap">
                      <a class="btn btn-cta btn-cta-sm" href="/seating.php">Sitzgruppen</a>
                      <a class="btn btn-outline-secondary btn-sm" href="/location.php" style="border-radius:12px;">Location</a>
                    </div>
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
