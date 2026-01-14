<?php
declare(strict_types=1);

// src/Controller/AdminController.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Repository/PricingOverridesRepository.php';
require_once __DIR__ . '/../Repository/AdminAuditLogRepository.php';
require_once __DIR__ . '/../Service/PricingService.php';
require_once __DIR__ . '/../Service/SeatingService.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';
require_once __DIR__ . '/../Auth/AdminContext.php';

// dompdf (Composer)
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

final class AdminController
{
    /* =========================
     * AUTH
     * ========================= */

    public static function showLoginForm(string $error = '', string $identifier = ''): void
    {
        Bootstrap::init();

        if (AdminContext::isAdmin()) {
            Response::redirect('/admin_dashboard.php');
        }

        Layout::header('Admin – Login');
        ?>
        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 560px;">

            <div class="text-center mx-auto" style="padding-top: 10px; padding-bottom: 18px;">
              <h1 class="h-serif mb-2" style="font-size: clamp(28px, 4vw, 44px); font-weight: 300;">
                Admin Login
              </h1>
              <p class="text-muted mb-0" style="line-height: 1.6;">
                Anmeldung für Organisations-Team.
              </p>
            </div>

            <div class="card">
              <div class="card-body p-4 p-md-5">

                <?php if ($error !== ''): ?>
                  <div class="alert alert-danger mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/admin_login.php" autocomplete="on">
                  <?= Csrf::inputField() ?>

                  <div class="mb-3">
                    <label class="form-label">Admin-Name oder Admin-ID</label>
                    <input class="form-control" name="identifier" value="<?= e($identifier) ?>" required autocomplete="username" inputmode="text">
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Admin-Code</label>
                    <input class="form-control" type="password" name="password" required autocomplete="current-password">
                  </div>

                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-save" type="submit">Einloggen</button>
                    <a class="btn btn-outline-secondary" href="/" style="border-radius: 12px;">Zur Landing Page</a>
                  </div>
                </form>

              </div>
            </div>

          </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function login(): void
    {
        Bootstrap::init();

        if (AdminContext::isAdmin()) {
            Response::redirect('/admin_dashboard.php');
        }

        $ipKey = 'admin_login_' . Request::ip();
        if (!RateLimiter::allow($ipKey, 8, 60)) {
            self::showLoginForm('Zu viele Versuche. Bitte 1 Minute warten.');
            return;
        }

        if (!Csrf::validate(Request::postString('_csrf'))) {
            self::showLoginForm('Ungültige Anfrage (CSRF). Seite neu laden und erneut versuchen.');
            return;
        }

        $identifier = trim(Request::postString('identifier'));
        $password   = trim(Request::postString('password'));

        if ($identifier === '' || $password === '') {
            self::showLoginForm('Bitte Daten eingeben.', $identifier);
            return;
        }

        $admin = ParticipantsRepository::findAdminByIdOrName($identifier);
        if (!$admin) {
            self::showLoginForm('Admin nicht gefunden.', $identifier);
            return;
        }

        $stored = (string)($admin['login_code'] ?? '');
        if ($stored === '' || $stored === '0') {
            self::showLoginForm('Admin-Code fehlt in CSV.', $identifier);
            return;
        }

        if (!hash_equals($stored, $password)) {
            self::showLoginForm('Admin-Code ist falsch.', $identifier);
            return;
        }

        AdminContext::loginAsAdmin($admin);
        Response::redirect('/admin_dashboard.php');
    }

    public static function logout(): void
    {
        AdminContext::logout('/admin_login.php');
    }

    /* =========================
     * ACTIONS
     * ========================= */

    public static function updatePaid(): void
    {
        AdminContext::requireAdmin();

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/admin_dashboard.php?err=csrf#edit');
        }

        $mainId  = trim(Request::postString('main_id'));
        $paidRaw = trim(Request::postString('amount_paid'));

        if ($mainId === '') {
            Response::redirect('/admin_dashboard.php?err=main#edit');
       
        }

        if ($paidRaw === '' || !preg_match('/^\d+$/', $paidRaw)) {
            Response::redirect('/admin_dashboard.php?err=paid#edit');
        }

        $paid = (int)$paidRaw;
        if ($paid < 0) $paid = 0;

        $oldPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);

        try {
            ParticipantsRepository::updateAmountPaidForMainId($mainId, $paid);

            AdminAuditLogRepository::append('update_paid', [
                'main_id' => $mainId,
                'old' => $oldPaid,
                'new' => $paid,
            ]);

            Response::redirect('/admin_dashboard.php?ok=1#edit');
        } catch (Throwable $e) {
            Response::redirect('/admin_dashboard.php?err=save#edit');
        }
    }

    public static function saveOverride(): void
    {
        AdminContext::requireAdmin();

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/admin_dashboard.php?err=csrf#ovr');
        }

        $id = trim(Request::postString('override_id'));
        $priceRaw = trim(Request::postString('ticket_price'));
        $reason = trim(Request::postString('reason'));

        if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            Response::redirect('/admin_dashboard.php?err=override_id#ovr');
        }

        if ($priceRaw === '' || !preg_match('/^\d+$/', $priceRaw)) {
            Response::redirect('/admin_dashboard.php?err=override_price#ovr');
        }

        $price = (int)$priceRaw;
        if ($price < 0) $price = 0;

        $before = PricingOverridesRepository::mapById();
        $old = $before[$id] ?? null;

        try {
            PricingOverridesRepository::upsertOverrideForId($id, $price, $reason);

            AdminAuditLogRepository::append('override_save', [
                'id' => $id,
                'old' => $old,
                'new' => ['ticket_price' => $price, 'reason' => $reason],
            ]);

            Response::redirect('/admin_dashboard.php?ok_override=1#ovr');
        } catch (Throwable $e) {
            Response::redirect('/admin_dashboard.php?err=override_save#ovr');
        }
    }

    public static function deleteOverride(): void
    {
        AdminContext::requireAdmin();

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/admin_dashboard.php?err=csrf#ovr');
        }

        $id = trim(Request::postString('override_id'));
        if ($id === '') {
            Response::redirect('/admin_dashboard.php?err=override_id#ovr');
        }

        $before = PricingOverridesRepository::mapById();
        $old = $before[$id] ?? null;

        try {
            PricingOverridesRepository::deleteOverrideForId($id);

            AdminAuditLogRepository::append('override_delete', [
                'id' => $id,
                'old' => $old,
            ]);

            Response::redirect('/admin_dashboard.php?ok_override=1#ovr');
        } catch (Throwable $e) {
            Response::redirect('/admin_dashboard.php?err=override_delete#ovr');
        }
    }

    public static function createMainGuest(): void
    {
        AdminContext::requireAdmin();

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/admin_dashboard.php?err=csrf#create');
        }

        $id = trim(Request::postString('id'));
        $name = trim(Request::postString('name'));
        $login = trim(Request::postString('login_code'));

        try {
            ParticipantsRepository::createMainGuest($id, $name, $login, 'USER');

            AdminAuditLogRepository::append('create_main_guest', [
                'id' => $id,
                'name' => $name,
                'login_code_set' => ($login !== ''),
            ]);

            Response::redirect('/admin_dashboard.php?ok_create=1#create');
        } catch (Throwable $e) {
            Response::redirect('/admin_dashboard.php?err=create_main#create');
        }
    }

    public static function createCompanion(): void
    {
        AdminContext::requireAdmin();

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/admin_dashboard.php?err=csrf#create');
        }

        $mainId = trim(Request::postString('main_id'));
        $name   = trim(Request::postString('name'));
        $login  = trim(Request::postString('login_code')); // optional

        try {
            $newId = ParticipantsRepository::createCompanion($mainId, $name, $login);

            AdminAuditLogRepository::append('create_companion', [
                'main_id' => $mainId,
                'new_id' => $newId,
                'name' => $name,
                'login_code_set' => ($login !== ''),
            ]);

            Response::redirect('/admin_dashboard.php?ok_create=1#create');
        } catch (Throwable $e) {
            Response::redirect('/admin_dashboard.php?err=create_companion#create');
        }
    }

    /* =========================
     * PDF
     * ========================= */

    public static function printMainLoginsPdf(): void
    {
        AdminContext::requireAdmin();

        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '180');

        $all = ParticipantsRepository::all();

        $mains = array_values(array_filter($all, static function (array $p): bool {
            return !empty($p['is_main_bool']) && strtoupper((string)($p['role'] ?? 'USER')) !== 'ADMIN';
        }));

        usort($mains, static function ($a, $b): int {
            return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        });

        $perPage = 12;
        $pages = array_values(array_filter(array_chunk($mains, $perPage), static fn($ch) => is_array($ch) && count($ch) > 0));

        $faviconDataUri = '';
        $publicDir = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
        $favPng = $publicDir . '/favicon.png';
        $favIco = $publicDir . '/favicon.ico';

        if (is_file($favPng)) {
            $b = file_get_contents($favPng);
            if ($b !== false) $faviconDataUri = 'data:image/png;base64,' . base64_encode($b);
        } elseif (is_file($favIco)) {
            $b = file_get_contents($favIco);
            if ($b !== false) $faviconDataUri = 'data:image/x-icon;base64,' . base64_encode($b);
        }

        $opts = new Options();
        $opts->set('isRemoteEnabled', false);
        $opts->set('defaultFont', 'DejaVu Sans');
        $opts->set('isHtml5ParserEnabled', false);

        $dompdf = new Dompdf($opts);

        $title = 'Abiball – Login-Codes Hauptgäste';

        $css = '
            @page { size: A4 portrait; margin: 7mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color:#000; margin:0; padding:0; }
            .h { font-size: 8.5pt; margin: 0 0 2mm 0; text-align: right; color:#333; }
            table, tr, td { page-break-inside: avoid; }
            .wrap { transform: scale(0.97); transform-origin: top left; width: 103%; }
            table{ width:100%; border-collapse:collapse; table-layout:fixed; }
            td{
                width:33.333%;
                height:62mm;
                border:1px solid #000;
                padding:3mm;
                text-align:center;
                vertical-align:middle;
            }
            img{ display:block; margin:0 auto 2mm auto; width:9mm; height:9mm; }
            .n{ font-size:12.5pt; font-weight:700; margin:0 0 1.5mm 0; }
            .m{ font-size:10pt; line-height:1.3; margin:0.3mm 0; }
            .c{ font-size:11.5pt; font-weight:700; letter-spacing:.35px; }
            .s{ font-size:9pt; }
        ';

        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';
        $totalPages = count($pages);

        foreach ($pages as $pi => $chunk) {
            $pageNo = $pi + 1;

            $html .= '<div class="h">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                  . ' — Seite ' . $pageNo . ' / ' . $totalPages . '</div>';

            $html .= '<div class="wrap"><table><tbody>';

            for ($r = 0; $r < 4; $r++) {
                $html .= '<tr>';
                for ($c = 0; $c < 3; $c++) {
                    $idx = $r * 3 + $c;

                    if (!isset($chunk[$idx])) {
                        $html .= '<td></td>';
                        continue;
                    }

                    $p = $chunk[$idx];

                    $name = htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $id   = htmlspecialchars((string)($p['id'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $code = htmlspecialchars((string)($p['login_code'] ?? ''), ENT_QUOTES, 'UTF-8');

                    $html .= '<td>';
                    if ($faviconDataUri !== '') {
                        $html .= '<img src="' . $faviconDataUri . '" alt="Logo">';
                    }
                    $html .= '<div class="n">' . $name . '</div>';
                    $html .= '<div class="m"><span class="s">ID:</span> ' . $id . '</div>';
                    $html .= '<div class="m"><span class="s">Login-Code:</span> <span class="c">' . $code . '</span></div>';
                    $html .= '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';

            if ($pageNo < $totalPages) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $html .= '</body></html>';

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="hauptgaeste_login_codes.pdf"');
        header('X-Content-Type-Options: nosniff');
        echo $dompdf->output();
        exit;
    }

    /* =========================
     * DASHBOARD
     * ========================= */

    public static function dashboard(): void
    {
        AdminContext::requireAdmin();

        // -------- Stats (Charts) --------
        $allForStats = ParticipantsRepository::all();

        $kindCounts = [];
        $classTotals = [];

        foreach ($allForStats as $p) {
            $id = trim((string)($p['id'] ?? ''));
            if ($id === '') continue;

            $kind = 'UNKNOWN';
            if (preg_match('/^ADMIN/i', $id)) $kind = 'ADMIN';
            elseif (preg_match('/B\d+$/i', $id)) $kind = 'COMPANION';
            elseif (preg_match('/L$/i', $id)) $kind = 'TEACHER';
            elseif (preg_match('/^E.*S$/i', $id)) $kind = 'ALUMNI';
            elseif (preg_match('/S$/i', $id)) $kind = 'MAIN';

            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

            // class labels:
            // - SGGS/SSGS + ERSTE Zahl => separat (SGGS1, SSGS2)
            // - TEACHER => LEHRER
            // - sonst: prefix ohne zahlen
            $classLabel = '';

            if ($kind === 'TEACHER') {
                $classLabel = 'LEHRER';
            } elseif (preg_match('/^(SGGS|SSGS)(\d)/i', $id, $mm)) {
                // Nur erste Zahl zählt: SGGS104S -> SGGS1
                $classLabel = strtoupper($mm[1]) . $mm[2];
            } else {
                $classId = $id;
                $classId = preg_replace('/B\d+$/i', '', $classId);
                $classId = preg_replace('/S$/i', '', $classId);
                $classId = preg_replace('/L$/i', '', $classId);
                $classId = preg_replace('/\d+/', '', $classId);
                $classLabel = trim((string)$classId);
            }

            if ($classLabel !== '' && $kind !== 'ADMIN') {
                $classTotals[$classLabel] = ($classTotals[$classLabel] ?? 0) + 1;
            }
        }

        ksort($kindCounts);
        arsort($classTotals);
        $classTotals = array_slice($classTotals, 0, 12, true);

        // -------- Search + grouping --------
        $q   = Request::getString('q');
        $all = ParticipantsRepository::all();

        $filtered = $all;
        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $filtered = array_values(array_filter($all, static function ($p) use ($qLower) {
                $id   = mb_strtolower((string)($p['id'] ?? ''), 'UTF-8');
                $name = mb_strtolower((string)($p['name'] ?? ''), 'UTF-8');
                $main = mb_strtolower((string)($p['main_id'] ?? ''), 'UTF-8');
                return str_contains($id, $qLower) || str_contains($name, $qLower) || str_contains($main, $qLower);
            }));
        }

        $groups = [];
        foreach ($filtered as $p) {
            $mainId = ParticipantsRepository::resolveMainIdFromRow($p);
            if ($mainId === '') continue;
            $groups[$mainId][] = $p;
        }
        ksort($groups);

        // Totals
        $totalDue = 0;
        $totalPaid = 0;
        foreach (array_keys($groups) as $mid) {
            $dueArr = PricingService::amountDueForMainId($mid);
            $totalDue += (int)($dueArr['amount_due'] ?? 0);
            $totalPaid += (int)ParticipantsRepository::amountPaidForMainId($mid);
        }
        $totalOpen = max(0, $totalDue - $totalPaid);

        $overrides = PricingOverridesRepository::mapById();
        $audit = AdminAuditLogRepository::latest(200);

        // UI flags
        $ok  = Request::getString('ok');
        $err = Request::getString('err');
        $okOverride = Request::getString('ok_override');
        $okCreate = Request::getString('ok_create');

        Layout::header('Admin – Dashboard');
        ?>
        <main class="bg-starfield admin-dashboard">
          <div class="container py-4">

            <div class="admin-head d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div>
                <h1 class="h-serif mb-1" style="font-size: 1.6rem; font-weight: 300;">Admin Dashboard</h1>
                <div class="text-muted" style="font-size:.95rem;">Eingeloggt als <?= e(AdminContext::adminName()) ?></div>
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="/admin_main_logins_pdf.php" target="_blank" rel="noopener">
                  PDF: Login-Codes
                </a>
                <a class="btn btn-outline-danger btn-sm" href="/admin_logout.php">Logout</a>
              </div>
            </div>

            <?php if ($ok !== ''): ?>
              <div class="alert alert-success">Gespeichert.</div>
            <?php endif; ?>

            <?php if ($okOverride !== ''): ?>
              <div class="alert alert-success">Override gespeichert.</div>
            <?php endif; ?>

            <?php if ($okCreate !== ''): ?>
              <div class="alert alert-success">Teilnehmer angelegt.</div>
            <?php endif; ?>

            <?php if ($err !== ''): ?>
              <div class="alert alert-danger">
                <?php
                echo match ($err) {
                    'csrf' => 'Ungültige Anfrage (CSRF).',
                    'main' => 'main_id fehlt.',
                    'paid' => 'Ungültiger Betrag (nur ganze Zahl).',
                    'save' => 'Speichern fehlgeschlagen.',
                    'override_id' => 'Override-ID ist ungültig.',
                    'override_price' => 'Override-Preis ist ungültig (nur ganze Zahl).',
                    'override_save' => 'Override speichern fehlgeschlagen.',
                    'override_delete' => 'Override löschen fehlgeschlagen.',
                    'create_main' => 'Hauptgast anlegen fehlgeschlagen (ID ungültig oder existiert).',
                    'create_companion' => 'Begleiter anlegen fehlgeschlagen (main_id nicht gefunden/ungültig).',
                    default => 'Fehler.'
                };
                ?>
              </div>
            <?php endif; ?>

            <!-- Always visible: Overview + Charts -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-lg-5">
                <div class="card admin-card h-100">
                  <div class="card-body p-4">
                    <div class="text-muted small admin-kicker">Übersicht</div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div class="text-muted">Gesamt Soll</div>
                      <div class="fw-semibold"><?= (int)$totalDue ?> €</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div class="text-muted">Gesamt Bezahlt</div>
                      <div class="fw-semibold"><?= (int)$totalPaid ?> €</div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                      <div class="text-muted">Gesamt Offen</div>
                      <?php if ($totalOpen === 0): ?>
                        <span class="badge text-bg-success">0 €</span>
                      <?php else: ?>
                        <span class="badge text-bg-danger"><?= (int)$totalOpen ?> €</span>
                      <?php endif; ?>
                    </div>

                    <div class="mt-3">
                      <label class="form-label mb-1">Bereich</label>
                      <select id="adminSectionSelect" class="form-select">
                        <option value="create">Teilnehmer hinzufügen</option>
                        <option value="ovr">Pricing Overrides</option>
                        <option value="edit" selected>Teilnehmer & Paid bearbeiten</option>
                        <option value="seating">Sitzgruppen & Notizen</option>
                        <option value="logs">Änderungsprotokoll</option>
                      </select>
                      <div class="text-muted small mt-2">
                        Auswertung bleibt immer sichtbar.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-7">
                <div class="card admin-card h-100">
                  <div class="card-body p-4">
                    <div class="text-muted small admin-kicker">Statistiken</div>

                    <div class="row g-3 mt-1">
                      <div class="col-12 col-md-6">
                        <div class="admin-panel p-3">
                          <div class="text-muted" style="font-size:.9rem;">Teilnehmerarten</div>
                          <canvas id="chartKinds" height="150"></canvas>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="admin-panel p-3">
                          <div class="text-muted" style="font-size:.9rem;">Top Klassen (SGGS1/SSGS2 separat, Lehrer = LEHRER)</div>
                          <canvas id="chartClasses" height="150"></canvas>
                        </div>
                      </div>
                    </div>

                    <script>
                      (function () {
                        if (!window.Chart) return;

                        const kindLabels = <?= json_encode(array_keys($kindCounts), JSON_UNESCAPED_UNICODE) ?>;
                        const kindData   = <?= json_encode(array_values($kindCounts), JSON_UNESCAPED_UNICODE) ?>;

                        const classLabels = <?= json_encode(array_keys($classTotals), JSON_UNESCAPED_UNICODE) ?>;
                        const classData   = <?= json_encode(array_values($classTotals), JSON_UNESCAPED_UNICODE) ?>;

                        const c1 = document.getElementById('chartKinds');
                        const c2 = document.getElementById('chartClasses');

                        if (c1) {
                          new Chart(c1, {
                            type: 'doughnut',
                            data: { labels: kindLabels, datasets: [{ data: kindData }] },
                            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                          });
                        }

                        if (c2) {
                          new Chart(c2, {
                            type: 'bar',
                            data: { labels: classLabels, datasets: [{ data: classData }] },
                            options: {
                              responsive: true,
                              plugins: { legend: { display: false } },
                              scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                            }
                          });
                        }
                      })();
                    </script>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sections -->
            <section class="admin-section" data-section="create" id="create">
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                      <div class="text-muted small admin-kicker">Teilnehmer</div>
                      <div class="h6 mb-0">Teilnehmer hinzufügen</div>
                      <div class="text-muted" style="font-size:.9rem;">
                        Hauptgast endet auf <code>S</code>. Begleiter-ID wird automatisch (<code>B1/B2/…</code>) vergeben.
                      </div>
                    </div>
                  </div>

                  <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-6">
                      <div class="admin-panel p-3">
                        <div class="fw-semibold mb-2">Neuer Hauptgast</div>
                        <form method="post" action="/admin_create_main_guest.php" class="row g-2">
                          <?= Csrf::inputField() ?>
                          <div class="col-12">
                            <input class="form-control" name="id" placeholder="ID z.B. WGW00S" required>
                          </div>
                          <div class="col-12">
                            <input class="form-control" name="name" placeholder="Name" required>
                          </div>
                          <div class="col-12">
                            <input class="form-control" name="login_code" placeholder="Login-Code" required>
                          </div>
                          <div class="col-12">
                            <button class="btn btn-save" type="submit">Hauptgast anlegen</button>
                          </div>
                        </form>
                      </div>
                    </div>

                    <div class="col-12 col-lg-6">
                      <div class="admin-panel p-3">
                        <div class="fw-semibold mb-2">Neue Begleitperson</div>
                        <form method="post" action="/admin_create_companion.php" class="row g-2">
                          <?= Csrf::inputField() ?>
                          <div class="col-12">
                            <input class="form-control" name="main_id" placeholder="main_id z.B. WGW00S" required>
                          </div>
                          <div class="col-12">
                            <input class="form-control" name="name" placeholder="Name" required>
                          </div>
                          <div class="col-12">
                            <input class="form-control" name="login_code" placeholder="Login-Code (leer = vom Hauptgast übernehmen)">
                          </div>
                          <div class="col-12">
                            <button class="btn btn-save" type="submit">Begleiter anlegen</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </section>

            <section class="admin-section" data-section="ovr" id="ovr">
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                      <div class="text-muted small admin-kicker">Preise</div>
                      <div class="h6 mb-0">Pricing Overrides</div>
                      <div class="text-muted" style="font-size:.9rem;">Ticketpreis pro ID überschreiben (z.B. Lehrer = 0€).</div>
                    </div>
                  </div>

                  <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                      <thead>
                        <tr>
                          <th style="width:160px;">ID</th>
                          <th style="width:140px;">Ticketpreis (€)</th>
                          <th>Reason</th>
                          <th style="width:220px;">Aktion</th>
                        </tr>
                      </thead>
                      <tbody>

                        <tr>
                          <form method="post" action="/admin_override_save.php">
                            <?= Csrf::inputField() ?>
                            <td><input class="form-control form-control-sm" name="override_id" placeholder="z.B. ABI00S"></td>
                            <td><input class="form-control form-control-sm" name="ticket_price" inputmode="numeric" pattern="\d+" placeholder="z.B. 0"></td>
                            <td><input class="form-control form-control-sm" name="reason" placeholder="z.B. Lehrer"></td>
                            <td><button class="btn btn-sm btn-save" type="submit">Hinzufügen</button></td>
                          </form>
                        </tr>

                        <?php foreach ($overrides as $id => $ov): ?>
                          <tr>
                            <form method="post" action="/admin_override_save.php">
                              <?= Csrf::inputField() ?>
                              <td>
                                <input type="hidden" name="override_id" value="<?= e($id) ?>">
                                <span class="text-nowrap"><?= e($id) ?></span>
                              </td>
                              <td>
                                <input class="form-control form-control-sm" name="ticket_price" inputmode="numeric" pattern="\d+"
                                       value="<?= e((string)((int)($ov['ticket_price'] ?? 0))) ?>">
                              </td>
                              <td>
                                <input class="form-control form-control-sm" name="reason" value="<?= e((string)($ov['reason'] ?? '')) ?>">
                              </td>
                              <td class="d-flex gap-2">
                                <button class="btn btn-sm btn-save" type="submit">Speichern</button>
                            </form>

                            <form method="post" action="/admin_override_delete.php" onsubmit="return confirm('Override wirklich löschen?');">
                              <?= Csrf::inputField() ?>
                              <input type="hidden" name="override_id" value="<?= e($id) ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                              </td>
                          </tr>
                        <?php endforeach; ?>

                      </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </section>

            <section class="admin-section" data-section="edit" id="edit">
              <form class="card admin-card mb-3" method="get" action="/admin_dashboard.php">
                <div class="card-body p-4 d-flex gap-2 flex-wrap">
                  <input class="form-control" name="q" placeholder="Suche nach Name, ID, main_id ..." value="<?= e($q) ?>">
                  <button class="btn btn-primary" type="submit">Suchen</button>
                  <a class="btn btn-outline-secondary" href="/admin_dashboard.php#edit">Zurücksetzen</a>
                </div>
              </form>

              <?php if (count($groups) === 0): ?>
                <div class="alert alert-warning">Keine Treffer.</div>
              <?php else: ?>
                <?php foreach ($groups as $mainId => $members): ?>
                  <?php
                  $main = null;
                  $companions = [];
                  foreach ($members as $m) {
                      if (!empty($m['is_main_bool'])) $main = $m;
                      else $companions[] = $m;
                  }

                  $ticketCount = 0;
                  if (!empty($main)) $ticketCount++;
                  $ticketCount += count($companions);

                  $dueArr     = PricingService::amountDueForMainId($mainId);
                  $amountDue  = (int)($dueArr['amount_due'] ?? 0);
                  $amountPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);
                  $open       = max(0, $amountDue - $amountPaid);
                  ?>
                  <div class="card admin-card mb-3">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                          <div class="text-muted small">main_id</div>
                          <div class="fw-semibold"><?= e($mainId) ?></div>
                        </div>
                        <div>
                          <div class="text-muted small">Hauptgast</div>
                          <div class="fw-semibold">
                            <?= e((string)($main['name'] ?? '—')) ?> <span class="text-muted">·</span> <?= e((string)($main['id'] ?? '—')) ?>
                          </div>
                        </div>
                        <div class="text-muted small">Begleiter: <?= count($companions) ?></div>
                      </div>

                      <div class="table-responsive mt-3">
                        <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                          <thead>
                            <tr>
                              <th>Tickets</th>
                              <th>Soll</th>
                              <th>Bezahlt</th>
                              <th>Offen</th>
                              <th style="width: 280px;">paid ändern</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td><?= (int)$ticketCount ?></td>
                              <td><?= (int)$amountDue ?> €</td>
                              <td><?= (int)$amountPaid ?> €</td>
                              <td>
                                <?php if ($open === 0): ?>
                                  <span class="badge text-bg-success">OK</span>
                                <?php else: ?>
                                  <span class="badge text-bg-danger"><?= (int)$open ?> € offen</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <form class="d-flex gap-2" method="post" action="/admin_update_paid.php">
                                  <?= Csrf::inputField() ?>
                                  <input type="hidden" name="main_id" value="<?= e($mainId) ?>">
                                  <input class="form-control form-control-sm" name="amount_paid" inputmode="numeric" pattern="\d+"
                                         value="<?= e((string)$amountPaid) ?>">
                                  <button class="btn btn-sm btn-save" type="submit">Speichern</button>
                                </form>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                      <?php if (count($companions) > 0): ?>
                        <div class="table-responsive mt-3">
                          <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                            <thead>
                              <tr>
                                <th>Begleiter Name</th>
                                <th class="text-nowrap">Begleiter ID</th>
                              </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($companions as $c): ?>
                              <tr>
                                <td><?= e((string)($c['name'] ?? '')) ?></td>
                                <td class="text-nowrap"><?= e((string)($c['id'] ?? '')) ?></td>
                              </tr>
                            <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>

                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </section>

            <section class="admin-section" data-section="seating" id="seating">
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                      <div class="text-muted small admin-kicker">Sitzordnung</div>
                      <div class="h6 mb-0">Sitzgruppen & Notizen (pro main_id)</div>
                      <div class="text-muted" style="font-size:.9rem;">
                        Anzeige der gespeicherten Sitzgruppen aus <code>/storage/seating/{main_id}.json</code> inkl. Group-Notes und Person-Notes.
                      </div>
                    </div>
                  </div>

                  <div class="mt-3 d-flex flex-column gap-3">
                    <?php foreach (array_keys($groups) as $mainId): ?>
                      <?php
                        $grp = ParticipantsRepository::getGroupByMainId($mainId);
                        $main = $grp['main'] ?? null;
                        $companions = $grp['companions'] ?? [];

                        $idToName = [];
                        if (is_array($main) && !empty($main['id'])) $idToName[(string)$main['id']] = (string)($main['name'] ?? '');
                        if (is_array($companions)) {
                          foreach ($companions as $c) {
                            if (!is_array($c) || empty($c['id'])) continue;
                            $idToName[(string)$c['id']] = (string)($c['name'] ?? '');
                          }
                        }

                        $seat = SeatingService::load($mainId);
                        $seatGroups = $seat['groups'] ?? [];
                        $groupNotes = $seat['group_notes'] ?? [];
                        $personNotes = $seat['person_notes'] ?? [];

                        if (!is_array($seatGroups)) $seatGroups = [];
                        if (!is_array($groupNotes)) $groupNotes = [];
                        if (!is_array($personNotes)) $personNotes = [];
                      ?>

                      <div class="admin-panel p-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <div class="fw-semibold">
                            <?= e($mainId) ?>
                            <?php if (is_array($main)): ?>
                              <span class="text-muted">·</span> <?= e((string)($main['name'] ?? '')) ?>
                            <?php endif; ?>
                          </div>
                          <a class="btn btn-sm btn-outline-secondary" href="/seating.php?mid=<?= e($mainId) ?>" target="_blank" rel="noopener" style="border-radius:12px;">
                            Öffnen
                          </a>
                        </div>

                        <div class="row g-3 mt-1">
                          <?php foreach ($seatGroups as $gid => $g): ?>
                            <?php
                              if (!is_array($g)) continue;
                              $gName = trim((string)($g['name'] ?? $gid));
                              if ($gName === '') $gName = $gid;
                              $members = $g['members'] ?? [];
                              if (!is_array($members)) $members = [];
                              $gNote = trim((string)($groupNotes[$gid] ?? ''));
                            ?>
                            <div class="col-12 col-lg-4">
                              <div class="admin-seatgroup p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                  <div class="fw-semibold"><?= e($gName) ?></div>
                                  <span class="badge text-bg-secondary"><?= count($members) ?></span>
                                </div>

                                <?php if ($gNote !== ''): ?>
                                  <div class="mt-2 text-muted small">
                                    <span class="fw-semibold">Gruppen-Notiz:</span>
                                    <?= e($gNote) ?>
                                  </div>
                                <?php endif; ?>

                                <div class="mt-3 d-flex flex-column gap-2">
                                  <?php if (count($members) === 0): ?>
                                    <div class="text-muted small">Keine Mitglieder.</div>
                                  <?php else: ?>
                                    <?php foreach ($members as $pid): ?>
                                      <?php
                                        $pid = trim((string)$pid);
                                        if ($pid === '') continue;
                                        $pName = $idToName[$pid] ?? $pid;
                                        $pNote = trim((string)($personNotes[$pid] ?? ''));
                                      ?>
                                      <div class="admin-seatperson">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                          <div class="fw-semibold"><?= e($pName) ?></div>
                                          <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                                        </div>
                                        <?php if ($pNote !== ''): ?>
                                          <div class="text-muted small mt-1">
                                            <span class="fw-semibold">Notiz:</span> <?= e($pNote) ?>
                                          </div>
                                        <?php endif; ?>
                                      </div>
                                    <?php endforeach; ?>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>

                    <?php endforeach; ?>
                  </div>

                </div>
              </div>
            </section>

            <section class="admin-section" data-section="logs" id="logs">
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="text-muted small admin-kicker">Sicherheit</div>
                  <div class="h6 mb-0">Änderungsprotokoll (letzte 200)</div>

                  <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                      <thead>
                        <tr>
                          <th style="width: 190px;">Zeit</th>
                          <th style="width: 220px;">Admin</th>
                          <th style="width: 160px;">Aktion</th>
                          <th>Details</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php if (count($audit) === 0): ?>
                        <tr><td colspan="4" class="text-muted">Noch keine Einträge.</td></tr>
                      <?php else: ?>
                        <?php foreach ($audit as $row): ?>
                          <tr>
                            <td class="text-nowrap"><?= e((string)($row['ts'] ?? '')) ?></td>
                            <td class="text-nowrap">
                              <?= e((string)(($row['admin']['name'] ?? '') ?: '')) ?>
                              <?php if (!empty($row['admin']['id'])): ?>
                                <span class="text-muted">·</span> <?= e((string)$row['admin']['id']) ?>
                              <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e((string)($row['action'] ?? '')) ?></td>
                            <td style="white-space: normal;">
                              <code style="font-size: .82rem;"><?= e(json_encode($row['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></code>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </section>

          </div>
        </main>

        <script>
          (function(){
            const sel = document.getElementById('adminSectionSelect');
            const sections = Array.from(document.querySelectorAll('.admin-section'));

            function show(key){
              sections.forEach(s => {
                s.style.display = (s.dataset.section === key) ? '' : 'none';
              });
              if (key) history.replaceState(null, '', '#'+key);
            }

            function init(){
              const hash = (location.hash || '').replace('#','').trim();
              const valid = ['create','ovr','edit','seating','logs'];
              const start = valid.includes(hash) ? hash : (sel ? sel.value : 'edit');
              if (sel) sel.value = start;
              show(start);
            }

            if (sel) sel.addEventListener('change', () => show(sel.value));
            window.addEventListener('hashchange', init);
            init();
          })();
        </script>
        <?php
        Layout::footer();
    }
}
