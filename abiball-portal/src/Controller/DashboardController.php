<?php

declare(strict_types=1);

/**
 * DashboardController - Das Haupt-Dashboard für eingeloggte Gäste
 * 
 * Zeigt Ticket, Begleitpersonen, Sitzplatzwünsche und Essensbestellungen an.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Service/ParticipantService.php';
require_once __DIR__ . '/../Service/PricingService.php';
require_once __DIR__ . '/../Service/SeatingService.php';
require_once __DIR__ . '/../Service/PasswordService.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';
require_once __DIR__ . '/../Auth/AuthContext.php';

final class DashboardController
{
  /**
   * Rendert das Dashboard mit allen Teilnehmer-Informationen.
   */
  public static function show(): void
  {
    Bootstrap::init();

    AuthContext::requireLogin('/login.php');

    // Passwort-Hinweis nur einmal pro Session anzeigen
    $showPwPrompt = !empty($_SESSION['show_pw_prompt']);
    $_SESSION['show_pw_prompt'] = 0;

    $pwOk  = Request::getString('pw_ok');
    $pwErr = Request::getString('pw_err');

    $mainId = AuthContext::mainId();

    $grp = ParticipantService::getMainAndCompanions($mainId);
    $main = $grp['main'] ?? null;
    $companions = $grp['companions'] ?? [];

    $calc = PricingService::amountDueForMainId($mainId);
    $amountDue = (int)($calc['amount_due'] ?? 0);

    $amountPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);
    $open = max(0, $amountDue - $amountPaid);

    $seating = SeatingService::load($mainId);
    $personNotes = $seating['person_notes'] ?? [];
    if (!is_array($personNotes)) $personNotes = [];

    $seatingGroups = $seating['groups'] ?? [];
    if (!is_array($seatingGroups)) $seatingGroups = [];

    // Essensbestellungen laden und nach Datum sortieren
    $foodOrders = FoodOrderRepository::findByMainId($mainId);
    usort($foodOrders, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

    $idToName = [];
    if (is_array($main) && !empty($main['id'])) {
      $idToName[(string)$main['id']] = (string)($main['name'] ?? '');
    }
    foreach ($companions as $c) {
      if (!is_array($c) || empty($c['id'])) continue;
      $idToName[(string)$c['id']] = (string)($c['name'] ?? '');
    }

    Layout::header('Abiball – Dashboard');
?>
    <main class="bg-starfield" id="main-content">
      <!-- Star layers -->
      <div class="stars-layer-1" aria-hidden="true"></div>
      <div class="stars-layer-2" aria-hidden="true"></div>
      <div class="stars-layer-3" aria-hidden="true"></div>

      <div class="container py-4" style="max-width: 1100px;">

        <?php if ($pwOk !== ''): ?>
          <div class="alert alert-success">Passwort wurde geändert.</div>
        <?php endif; ?>

        <?php if ($pwErr !== ''): ?>
          <div class="alert alert-danger">
            <?php
            echo match ($pwErr) {
              'csrf' => 'Ungültige Anfrage (CSRF).',
              'empty' => 'Bitte alle Felder ausfüllen.',
              'match' => 'Die neuen Passwörter stimmen nicht überein.',
              'len' => 'Neues Passwort muss 6–64 Zeichen haben.',
              'old' => 'Aktuelles Passwort ist falsch.',
              'main' => 'Hauptgast nicht gefunden.',
              'save' => 'Speichern fehlgeschlagen.',
              default => 'Fehler.'
            };
            ?>
          </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h1 class="h-serif mb-1 reveal-text" style="font-size: 2rem; font-weight: 300;">Dein Bereich</h1>
            <div class="text-muted" style="font-size:.95rem;">
              Übersicht deiner Daten, Sitzgruppen und Zahlungsstand.
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
                    <?php if (is_array($main)): ?>
                      <div class="fw-semibold" style="font-size:1.15rem;"><?= e((string)($main['name'] ?? '')) ?></div>
                    <?php else: ?>
                      <div class="text-muted">Hauptgast nicht gefunden.</div>
                    <?php endif; ?>
                  </div>

                  <?php if (is_array($main)): ?>
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
                        title="Notiz"
                        aria-label="Notiz bearbeiten">
                        <span class="note-icon" aria-hidden="true">
                          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" />
                          </svg>
                        </span>
                      </button>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if (is_array($main)): ?>
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
                    <form method="post" action="/dashboard/dashboard_notes_save.php">
                      <?= Csrf::inputField() ?>
                      <input type="hidden" name="pid" value="<?= e($pid) ?>">
                      <label class="form-label mb-1">Notiz (im Portal)</label>
                      <textarea class="form-control form-control-sm" rows="2" name="note"
                        placeholder="z.B. Hinweise …"><?= e((string)($personNotes[$pid] ?? '')) ?></textarea>
                      <button class="btn btn-save btn-sm mt-2" type="submit">Notiz speichern</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Begleitpersonen -->
            <div class="card mb-3">
              <div class="card-body p-4">
                <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: 0.5rem;">Begleitpersonen</div>
                <div class="h6 mb-0">Übersicht</div>

                <?php if (empty($companions)): ?>
                  <div class="text-muted mt-3">Keine Begleitpersonen.</div>
                <?php else: ?>
                  <div class="list-group list-group-flush mt-3">
                    <?php foreach ($companions as $c): ?>
                      <?php
                      if (!is_array($c)) continue;
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
                                  <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" />
                                </svg>
                              </span>
                            </button>
                          </div>
                        </div>

                        <div class="collapse mt-3" id="<?= e($collapseId) ?>">
                          <form method="post" action="/dashboard/dashboard_notes_save.php">
                            <?= Csrf::inputField() ?>
                            <input type="hidden" name="pid" value="<?= e($pid) ?>">
                            <label class="form-label mb-1">Notiz (im Portal)</label>
                            <textarea class="form-control form-control-sm" rows="2" name="note"
                              placeholder="z.B. Hinweise …"><?= e((string)($personNotes[$pid] ?? '')) ?></textarea>
                            <button class="btn btn-save btn-sm mt-2" type="submit">Notiz speichern</button>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <!-- Wichtiger Hinweis -->
                <div class="alert mt-4 mb-0" style="background: rgba(201,162,39,.10); border: 1px solid rgba(201,162,39,.35); border-radius: 14px;">
                  <div class="d-flex align-items-start gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="var(--gold)" viewBox="0 0 16 16" style="flex-shrink: 0; margin-top: 2px;">
                      <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                      <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
                    </svg>
                    <div>
                      <div class="fw-semibold mb-2">Änderungen bitte per E-Mail mitteilen</div>
                      <div class="small" style="line-height: 1.7;">
                        <strong>Vor der Überweisung</strong> bei Bedarf melden:
                        <a href="mailto:moris.kehl@gmail.com" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                          moris.kehl@gmail.com
                        </a>
                      </div>
                      <ul class="small mt-2 mb-0" style="line-height: 1.6; padding-left: 1.2rem;">
                        <li>Begleitpersonen an-/abmelden</li>
                        <li>Rechtschreibfehler korrigieren</li>
                        <li><strong>Befreiung:</strong> Kinder unter 4 Jahren & behinderte Personen (bitte melden)</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Passwort ändern (Desktop: hier in linker Spalte) -->
            <div class="card mb-3 d-none d-lg-block">
              <div class="card-body p-4">
                <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Sicherheit</div>
                <div class="h6 mb-0">Passwort ändern</div>

                <?php if ($showPwPrompt): ?>
                  <div class="text-muted mt-2" style="font-size:.95rem;">Erste Anmeldung: Möchtest du deinen Login-Code jetzt ändern?</div>
                <?php endif; ?>

                <form class="mt-3" method="post" action="/dashboard/dashboard_password_change.php">
                  <?= Csrf::inputField() ?>
                  <div class="row g-2" style="font-size: 0.875rem;">
                    <div class="col-12">
                      <label class="form-label">Aktuelles Passwort</label>
                      <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label">Neues Passwort</label>
                      <input class="form-control" type="password" name="new_password" required autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label">Neues Passwort wiederholen</label>
                      <input class="form-control" type="password" name="new_password2" required autocomplete="new-password">
                    </div>
                  </div>

                  <button class="btn btn-save mt-3" type="submit">Speichern</button>
                </form>
              </div>
            </div>

          </div>

          <div class="col-12 col-lg-5">

            <!-- Zahlung -->
            <div class="card mb-3">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Zahlung</div>
                    <div class="h6 mb-0">Zahlungsübersicht</div>
                  </div>
                  <a class="btn btn-outline-secondary btn-soft btn-sm" href="/zahlung.php">Verwalten</a>
                </div>

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

            <!-- Fristen & Hinweise -->
            <div class="card mb-3">
              <div class="card-body p-4">
                <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Fristen</div>
                <div class="h6 mb-3">Wichtige Termine</div>
                <div class="d-flex flex-column gap-2" style="font-size: .95rem; line-height: 1.6;">
                  <div class="d-flex align-items-start gap-2">
                    <span style="color: var(--gold); flex-shrink: 0;">&#9679;</span>
                    <div><strong>Ticketpreis:</strong> 17&nbsp;€ bis 14.02. · danach 20&nbsp;€</div>
                  </div>
                  <div class="d-flex align-items-start gap-2">
                    <span style="color: var(--gold); flex-shrink: 0;">&#9679;</span>
                    <div><strong>Zahlungsschluss:</strong> 01.&nbsp;März&nbsp;2026</div>
                  </div>
                  <div class="d-flex align-items-start gap-2">
                    <span style="color: var(--gold); flex-shrink: 0;">&#9679;</span>
                    <div><strong>Bestellungsschluss Essen:</strong> 27.&nbsp;Februar&nbsp;2026</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sitzgruppen (Anzeige) -->
            <div class="card mb-3">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Sitzordnung</div>
                    <div class="h6 mb-0">Aktuelle Sitzgruppen</div>
                  </div>
                  <a class="btn btn-outline-secondary btn-soft btn-sm" href="/seating/seating.php">Verwalten</a>
                </div>

                <?php if (empty($seatingGroups)): ?>
                  <div class="text-muted">Keine Sitzgruppen gespeichert.</div>
                <?php else: ?>
                  <div class="d-flex flex-column gap-3">
                    <?php foreach ($seatingGroups as $gid => $g): ?>
                      <?php
                      if (!is_array($g)) continue;
                      $gidStr = (string)$gid;

                      $groupName = trim((string)($g['name'] ?? ''));
                      if ($groupName === '') $groupName = 'Gruppe ' . $gidStr;

                      $members = $g['members'] ?? [];
                      if (!is_array($members)) $members = [];
                      ?>
                      <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <div class="fw-semibold"><?= e($groupName) ?></div>
                          <span class="badge text-bg-secondary"><?= count($members) ?> Mitglied<?= count($members) !== 1 ? 'er' : '' ?></span>
                        </div>

                        <?php if (empty($members)): ?>
                          <div class="text-muted small">Keine Mitglieder</div>
                        <?php else: ?>
                          <div class="d-flex flex-column gap-1">
                            <?php foreach ($members as $mid): ?>
                              <?php
                              $mid = trim((string)$mid);
                              if ($mid === '') continue;
                              $memberName = $idToName[$mid] ?? $mid;
                              ?>
                              <div class="d-flex justify-content-between align-items-center small">
                                <span><?= e($memberName) ?></span>
                                <span class="badge text-bg-secondary" style="font-size:0.7rem;"><?= e($mid) ?></span>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Essensbestellungen -->
            <div class="card mb-3">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <div>
                    <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Verpflegung</div>
                    <div class="h6 mb-0">Essensbestellungen</div>
                  </div>
                  <a class="btn btn-outline-secondary btn-soft btn-sm" href="/food/food_order.php">Verwalten</a>
                </div>

                <?php if (empty($foodOrders)): ?>
                  <div class="text-muted">Keine Essensbestellungen vorhanden.</div>
                <?php else: ?>
                  <div class="d-flex flex-column gap-3">
                    <?php foreach ($foodOrders as $foodOrder): ?>
                      <?php
                      $foId = $foodOrder['order_id'] ?? '';
                      $foStatus = $foodOrder['status'] ?? 'unknown';
                      $foTotal = (float)($foodOrder['total_price'] ?? 0);
                      $foItems = $foodOrder['items'] ?? [];
                      $foCreated = $foodOrder['created_at'] ?? '';

                      $statusLabels = [
                        'open' => ['Offen', 'bg-warning text-dark'],
                        'paid' => ['Bezahlt', 'bg-info text-dark'],
                        'redeemed' => ['Eingeloest', 'bg-success'],
                        'cancelled' => ['Storniert', 'bg-danger']
                      ];
                      $statusLabel = $statusLabels[$foStatus][0] ?? $foStatus;
                      $statusClass = $statusLabels[$foStatus][1] ?? 'bg-secondary';
                      ?>
                      <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <div class="fw-semibold"><?= e($foId) ?></div>
                          <span class="badge <?= $statusClass ?>"><?= e($statusLabel) ?></span>
                        </div>

                        <?php if (!empty($foItems)): ?>
                          <div class="d-flex flex-column gap-1 mb-2">
                            <?php foreach ($foItems as $foItem): ?>
                              <div class="d-flex justify-content-between align-items-center small">
                                <span><?= e((string)($foItem['quantity'] ?? 1)) ?>x <?= e($foItem['name'] ?? '') ?></span>
                                <span class="text-muted"><?= number_format((float)($foItem['subtotal'] ?? 0), 2, ',', '.') ?> €</span>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center small border-top pt-2">
                          <span class="fw-semibold">Gesamt</span>
                          <span class="fw-semibold"><?= number_format($foTotal, 2, ',', '.') ?> €</span>
                        </div>

                        <?php if ($foStatus === 'paid' || $foStatus === 'redeemed'): ?>
                          <div class="mt-2">
                            <a href="/food_bon/pdf.php?order_id=<?= urlencode($foId) ?>" class="btn btn-sm btn-outline-primary" target="_blank">Bon (PDF)</a>
                          </div>
                        <?php endif; ?>

                        <?php if ($foCreated): ?>
                          <div class="text-muted small mt-2">
                            Erstellt: <?= e(date('d.m.Y H:i', strtotime($foCreated))) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card">
              <div class="card-body p-4">
                <div class="section-label mb-3">Aktionen</div>
                <div class="d-flex gap-2 flex-wrap">
                  <a class="btn btn-outline-secondary btn-soft" href="/seating/seating.php">Sitzgruppen</a>
                  <a class="btn btn-outline-secondary btn-soft" href="/food/food_order.php">Essensbestellung</a>
                  <a class="btn btn-outline-secondary btn-soft" href="/zahlung.php">Zahlung</a>
                  <a class="btn btn-outline-secondary btn-soft" href="/voting/index.php">Lehrer Voting</a>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Passwort ändern (Mobil: ganz unten) -->
        <div class="card mt-3 d-lg-none">
          <div class="card-body p-4">
            <div class="text-muted small" style="letter-spacing:.18em;text-transform:uppercase; margin-bottom: .5rem;">Sicherheit</div>
            <div class="h6 mb-0">Passwort ändern</div>

            <?php if ($showPwPrompt): ?>
              <div class="text-muted mt-2" style="font-size:.95rem;">Erste Anmeldung: Möchtest du deinen Login-Code jetzt ändern?</div>
            <?php endif; ?>

            <form class="mt-3" method="post" action="/dashboard/dashboard_password_change.php">
              <?= Csrf::inputField() ?>
              <div class="row g-2" style="font-size: 0.875rem;">
                <div class="col-12">
                  <label class="form-label">Aktuelles Passwort</label>
                  <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Neues Passwort</label>
                  <input class="form-control" type="password" name="new_password" required autocomplete="new-password">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Neues Passwort wiederholen</label>
                  <input class="form-control" type="password" name="new_password2" required autocomplete="new-password">
                </div>
              </div>

              <button class="btn btn-save mt-3" type="submit">Speichern</button>
            </form>
          </div>
        </div>

      </div>
    </main>
<?php
    Layout::footer();
  }

  /**
   * Verarbeitet die Passwortänderung des eingeloggten Benutzers.
   */
  public static function changePassword(): void
  {
    Bootstrap::init();
    AuthContext::requireLogin('/login.php');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/dashboard.php?pw_err=csrf');
    }

    $mainId = AuthContext::mainId();
    $current = trim(Request::postString('current_password'));
    $new1 = trim(Request::postString('new_password'));
    $new2 = trim(Request::postString('new_password2'));

    // Delegate to PasswordService for validation and password change
    $result = PasswordService::changePassword($mainId, $current, $new1, $new2);

    if ($result['success']) {
      $_SESSION['show_pw_prompt'] = 0;
      Response::redirect('/dashboard.php?pw_ok=1');
    } else {
      $error = $result['error'] ?? 'unknown';
      Response::redirect('/dashboard.php?pw_err=' . urlencode($error));
    }
  }
}
