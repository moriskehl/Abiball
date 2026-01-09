<?php
declare(strict_types=1);

// public/ticket/verify.php
//
// DB-kompatible & sicherere Version:
// - QR nutzt token ?t=... statt ?pid=... (verhindert einfache Enumeration)
// - Rate-Limit gegen Scans/Bruteforce
// - Keine detailreichen Fehlermeldungen (verhindert Enumeration/Side-Channel)
// - Optional: Self-Scan zeigt keine sensiblen Daten (konfigurierbar)
// - Pricing/Payments über DB-Services/Repos
//
// Voraussetzungen:
// - src/Bootstrap.php
// - src/Security/RateLimiter.php
// - src/Security/TicketToken.php
// - src/Repository/TicketRepository.php
// - src/Service/PricingService.php
// - src/View/Helpers.php
// - src/Config.php (baseUrl, ticketTokenSecret, etc.)

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Config.php';

require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Security/TicketToken.php';

require_once __DIR__ . '/../../src/Repository/TicketRepository.php';
require_once __DIR__ . '/../../src/Service/PricingService.php';

require_once __DIR__ . '/../../src/View/Helpers.php';

Bootstrap::init();

function badge(string $text, string $variant = 'ok'): string
{
    $variant = ($variant === 'bad') ? 'bad' : 'ok';
    return '<span class="pill pill-' . $variant . '">' . e($text) . '</span>';
}

/**
 * Konfiguration:
 * - Bei Self-Scan ist es sicherer, KEINE personenbezogenen Daten anzuzeigen.
 * - Wenn ihr am Einlass wirklich Name/ID sehen wollt: auf true setzen, aber idealerweise nur im "Staff-Modus".
 */
const SHOW_PERSONAL_DATA = true;

function renderPage(string $state, array $data): void
{
    http_response_code(200);

    $title = ($state === 'ok') ? 'Ticket Prüfung – Gültig' : 'Ticket Prüfung – Ungültig';
    $headline = ($state === 'ok') ? 'Gültiges Ticket' : 'Ungültiges Ticket';
    $icon = ($state === 'ok') ? '✓' : '✕';

    $pill = ($state === 'ok') ? badge('GÜLTIG', 'ok') : badge('UNGÜLTIG', 'bad');

    $personName = (string)($data['person_name'] ?? '');
    $personId   = (string)($data['person_id'] ?? '');
    $mainName   = (string)($data['main_name'] ?? '');

    $paid = $data['paid'] ?? null;
    $due  = $data['due'] ?? null;
    $open = $data['open'] ?? null;

    $reason = (string)($data['reason'] ?? '');

    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?></title>
        <style>
            :root{
                --bg:#fbfbfd;
                --surface:rgba(255,255,255,.88);
                --surface2:rgba(255,255,255,.68);
                --text:#0b0b0f;
                --muted:rgba(11,11,15,.62);
                --border:rgba(11,11,15,.14);

                --gold:#c9a227;
                --ok:#2bd47d;
                --bad:#ff4d5a;

                --shadow:0 12px 34px rgba(0,0,0,.10);
                --radius:18px;
            }

            @media (prefers-color-scheme: dark){
                :root{
                    --bg:#07070a;
                    --surface:rgba(18,18,24,.84);
                    --surface2:rgba(18,18,24,.62);
                    --text:#f3f3f6;
                    --muted:rgba(243,243,246,.70);
                    --border:rgba(243,243,246,.14);
                    --shadow:0 14px 40px rgba(0,0,0,.35);
                }
            }

            html, body{
                height:100%;
                margin:0;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans", sans-serif;
                color:var(--text);
                background:var(--bg);
            }

            .bg{
                min-height:100%;
                position:relative;
                overflow:hidden;
                padding: 22px 16px;
            }

            .bg::before{
                content:"";
                position:absolute;
                inset:-15%;
                pointer-events:none;
                z-index:0;
                background:
                    radial-gradient(760px 520px at 50% 22%, rgba(201,162,39,.14), transparent 62%),
                    radial-gradient(980px 720px at 50% 42%, rgba(255,255,255,.08), transparent 70%),
                    radial-gradient(1120px 860px at 50% 50%, transparent 58%, rgba(0,0,0,.10) 100%);
                opacity:.9;
            }

            @media (prefers-color-scheme: dark){
                .bg::before{
                    background:
                        radial-gradient(760px 520px at 50% 22%, rgba(201,162,39,.16), transparent 64%),
                        radial-gradient(980px 720px at 50% 42%, rgba(201,162,39,.07), transparent 72%),
                        radial-gradient(1120px 860px at 50% 50%, transparent 56%, rgba(0,0,0,.34) 100%);
                    opacity:.85;
                }
            }

            .wrap{
                position:relative;
                z-index:1;
                max-width: 720px;
                margin: 0 auto;
            }

            .card{
                background:var(--surface);
                border:1px solid var(--border);
                border-radius:var(--radius);
                box-shadow:var(--shadow);
                backdrop-filter: blur(10px);
                overflow:hidden;
            }

            .head{
                padding: 18px 18px 14px 18px;
                border-bottom:1px solid var(--border);
                background: linear-gradient(180deg, var(--surface2), transparent);
            }

            .brand{
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap: 12px;
                flex-wrap:wrap;
            }

            .title{
                display:flex;
                align-items:baseline;
                gap:10px;
                flex-wrap:wrap;
            }

            .h{
                margin:0;
                font-weight: 700;
                letter-spacing: .2px;
                font-size: 1.1rem;
            }

            .sub{
                margin:6px 0 0 0;
                color:var(--muted);
                font-size:.92rem;
                line-height:1.5;
            }

            .pill{
                display:inline-flex;
                align-items:center;
                gap:8px;
                padding: 6px 10px;
                border-radius: 999px;
                font-weight: 800;
                letter-spacing: .10em;
                font-size: .72rem;
                text-transform: uppercase;
                border: 1px solid var(--border);
                background: rgba(11,11,15,.06);
                color: var(--text);
            }
            @media (prefers-color-scheme: dark){
                .pill{ background: rgba(243,243,246,.08); }
            }
            .pill-ok{
                border-color: rgba(43,212,125,.35);
                background: rgba(43,212,125,.10);
            }
            .pill-bad{
                border-color: rgba(255,77,90,.35);
                background: rgba(255,77,90,.10);
            }

            .body{ padding: 18px; }

            .grid{
                display:grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .row{
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap: 12px;
                padding: 12px;
                border: 1px solid var(--border);
                border-radius: 14px;
                background: rgba(255,255,255,.42);
            }
            @media (prefers-color-scheme: dark){
                .row{ background: rgba(255,255,255,.03); }
            }

            .label{
                color:var(--muted);
                font-size:.86rem;
                min-width: 110px;
                white-space:nowrap;
            }

            .value{
                font-weight:700;
                font-size: .98rem;
                text-align:right;
                word-break: break-word;
            }

            .statusline{
                margin-top: 12px;
                display:flex;
                justify-content:space-between;
                gap: 10px;
                padding-top: 12px;
                border-top: 1px solid var(--border);
                color: var(--muted);
                font-size: .9rem;
                flex-wrap:wrap;
            }

            .strong{
                color: var(--text);
                font-weight: 800;
            }

            .reason{
                margin-top: 10px;
                padding: 12px;
                border-radius: 14px;
                border: 1px solid var(--border);
                background: rgba(201,162,39,.08);
                color: var(--text);
                font-size: .92rem;
                line-height: 1.55;
            }

            .reason .k{
                color: var(--muted);
                font-size: .82rem;
                text-transform: uppercase;
                letter-spacing: .12em;
                font-weight: 800;
                display:block;
                margin-bottom: 6px;
            }

            .muted-small{
                margin-top: 14px;
                color: var(--muted);
                font-size: .82rem;
                line-height: 1.5;
            }
        </style>
    </head>
    <body>
        <div class="bg">
            <div class="wrap">
                <div class="card">
                    <div class="head">
                        <div class="brand">
                            <div class="title">
                                <?= $pill ?>
                                <h1 class="h"><?= e($icon . ' ' . $headline) ?></h1>
                            </div>
                            <div class="pill" style="border-color: rgba(201,162,39,.35); background: rgba(201,162,39,.10);">
                                Abi Ball 2026
                            </div>
                        </div>
                        <p class="sub">Ticketprüfung per QR-Code. Diese Seite ist für den Einlass gedacht.</p>
                    </div>

                    <div class="body">
                        <div class="grid">
                            <?php if (SHOW_PERSONAL_DATA && $personName !== ''): ?>
                                <div class="row">
                                    <div class="label">Name</div>
                                    <div class="value"><?= e($personName) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (SHOW_PERSONAL_DATA && $personId !== ''): ?>
                                <div class="row">
                                    <div class="label">Ticket-ID</div>
                                    <div class="value"><?= e($personId) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (SHOW_PERSONAL_DATA && $mainName !== ''): ?>
                                <div class="row">
                                    <div class="label">Hauptgast</div>
                                    <div class="value"><?= e($mainName) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($reason !== ''): ?>
                            <div class="reason">
                                <span class="k">Hinweis</span>
                                <?= e($reason) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($paid !== null && $due !== null): ?>
                            <div class="statusline">
                                <div>Zahlung: <span class="strong"><?= e((string)$paid) ?> €</span> / <?= e((string)$due) ?> €</div>
                                <?php if ($open !== null): ?>
                                    <div>Offen: <span class="strong"><?= e((string)$open) ?> €</span></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="muted-small">
                            Hinweis: Wenn der Gesamtbetrag (Soll) 0 € ist (z.B. Lehrer/Ehemalige via Pricing-Override), gilt das Ticket ohne Zahlung als gültig.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --------------------
// Rate-Limit (wichtig)
// --------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlKey = 'ticket_verify_' . $ip;
if (!RateLimiter::allow($rlKey, 60, 60)) {
    // 60 Requests / Minute pro IP
    renderPage('bad', [
        'reason' => 'Zu viele Anfragen. Bitte kurz warten.',
    ]);
}

// --------------------
// Input: token ?t=...
// --------------------
$t = isset($_GET['t']) ? trim((string)$_GET['t']) : '';
if ($t === '') {
    // Übergangsweise: alte QR-Codes mit pid akzeptieren, aber NICHT bevorzugen.
    $pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
    if ($pid !== '') {
        // Fallback: unsicherer Altpfad (sollte nach Migration entfernt werden)
        $legacy = TicketRepository::findLegacyByPersonId($pid);
        if (!$legacy) {
            renderPage('bad', ['reason' => 'Ungültiges Ticket.']);
        }
        // Preis-/Zahlungsprüfung wie unten
        $mainId = (string)($legacy['main_id'] ?? '');
        if ($mainId === '') {
            renderPage('bad', ['reason' => 'Ungültiges Ticket.']);
        }

        $paid = (int)TicketRepository::amountPaidForMain($mainId);
        $dueInfo = PricingService::amountDueForMainId($mainId);
        $due = (int)($dueInfo['amount_due'] ?? 0);

        if ($due < 0) {
            renderPage('bad', ['reason' => 'Ungültiges Ticket.']);
        }
        $open = max(0, $due - $paid);
        if ($due > 0 && $open > 0) {
            renderPage('bad', ['reason' => 'Zahlung unvollständig.', 'paid' => $paid, 'due' => $due, 'open' => $open]);
        }

        renderPage('ok', [
            'person_name' => (string)($legacy['person_name'] ?? ''),
            'person_id'   => (string)($legacy['person_id'] ?? $pid),
            'main_name'   => (string)($legacy['main_name'] ?? ''),
            'paid'        => $paid,
            'due'         => $due,
            'open'        => $open,
        ]);
    }

    renderPage('bad', [
        'reason' => 'Ungültiges Ticket.',
    ]);
}

// Token hash -> DB lookup
$tokenHash = TicketToken::hash($t);

$ticket = TicketRepository::findByTokenHash($tokenHash);
if (!$ticket) {
    renderPage('bad', [
        'reason' => 'Ungültiges Ticket.',
    ]);
}

$mainId = (string)($ticket['main_id'] ?? '');
if ($mainId === '') {
    renderPage('bad', [
        'reason' => 'Ungültiges Ticket.',
    ]);
}

// Zahlung & Gesamtbetrag (DB-Services)
$paid = (int)TicketRepository::amountPaidForMain($mainId);

$dueInfo = PricingService::amountDueForMainId($mainId);
$due = (int)($dueInfo['amount_due'] ?? 0);

// due kann 0 sein => gültig
if ($due < 0) {
    renderPage('bad', [
        'reason' => 'Ungültiges Ticket.',
        'paid'   => $paid,
        'due'    => $due,
    ]);
}

$open = max(0, $due - $paid);

if ($due > 0 && $open > 0) {
    renderPage('bad', [
        'reason' => 'Zahlung unvollständig – Ticket ist erst nach vollständiger Zahlung gültig.',
        'paid'   => $paid,
        'due'    => $due,
        'open'   => $open,
    ]);
}

// Gültig
renderPage('ok', [
    'person_name' => (string)($ticket['person_name'] ?? ''),
    'person_id'   => (string)($ticket['ticket_public_id'] ?? $ticket['person_id'] ?? ''),
    'main_name'   => (string)($ticket['main_name'] ?? ''),
    'paid'        => $paid,
    'due'         => $due,
    'open'        => $open,
]);
