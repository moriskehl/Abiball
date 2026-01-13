<?php
declare(strict_types=1);

// public/ticket/verify.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Security/TicketToken.php';
require_once __DIR__ . '/../../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../../src/Service/PricingService.php';
require_once __DIR__ . '/../../src/View/Helpers.php';

Bootstrap::init();

function badge(string $text, string $variant = 'ok'): string
{
    $variant = ($variant === 'bad') ? 'bad' : 'ok';
    return '<span class="pill pill-' . $variant . '">' . e($text) . '</span>';
}

function renderPage(string $state, array $data): void
{
    // $state: ok|bad
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

            .body{
                padding: 18px;
            }

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
                            <?php if ($personName !== ''): ?>
                                <div class="row">
                                    <div class="label">Name</div>
                                    <div class="value"><?= e($personName) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($personId !== ''): ?>
                                <div class="row">
                                    <div class="label">Ticket-ID</div>
                                    <div class="value"><?= e($personId) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($mainName !== ''): ?>
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

// --- Input ---
$pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
$sig = (string)($_GET['sig'] ?? '');

if ($pid === '') {
    renderPage('bad', [
        'reason' => 'Ticket-ID fehlt oder QR-Code ist beschädigt.',
    ]);
}

// --- QR-Signatur prüfen ---
if (!TicketToken::verify($pid, $sig)) {
    renderPage('bad', [
        'reason' => 'QR-Code ist ungültig oder wurde manipuliert.',
    ]);
}

// --- Person laden ---
$person = ParticipantsRepository::findById($pid);
if (!$person) {
    renderPage('bad', [
        'reason' => 'Ticket existiert nicht (unbekannte Ticket-ID).',
    ]);
}

// --- Gruppe / Hauptgast laden ---
$mainId = (string)($person['main_id'] ?? '');
if ($mainId === '') {
    renderPage('bad', [
        'reason' => 'Ticketdaten sind unvollständig (main_id fehlt).',
    ]);
}

$group = ParticipantsRepository::getGroupByMainId($mainId);
$main  = $group['main'] ?? null;
if (!$main) {
    renderPage('bad', [
        'reason' => 'Hauptgast konnte nicht geladen werden.',
    ]);
}

// --- Zahlung & Gesamtbetrag (inkl. Overrides) ---
$paid = (int)ParticipantsRepository::amountPaidForMainId($mainId);

$dueInfo = PricingService::amountDueForMainId($mainId);
$due = (int)($dueInfo['amount_due'] ?? 0);

if ($due < 0) {
    renderPage('bad', [
        'person_name' => (string)($person['name'] ?? ''),
        'person_id'   => (string)($person['id'] ?? $pid),
        'main_name'   => (string)($main['name'] ?? ''),
        'reason'      => 'Preislogik fehlerhaft (Gesamtbetrag < 0).',
        'paid'        => $paid,
        'due'         => $due,
    ]);
}

$open = max(0, $due - $paid);

if ($due > 0 && $open > 0) {
    renderPage('bad', [
        'person_name' => (string)($person['name'] ?? ''),
        'person_id'   => (string)($person['id'] ?? $pid),
        'main_name'   => (string)($main['name'] ?? ''),
        'reason'      => 'Zahlung unvollständig – Ticket ist erst nach vollständiger Zahlung gültig.',
        'paid'        => $paid,
        'due'         => $due,
        'open'        => $open,
    ]);
}

// --- Gültig ---
renderPage('ok', [
    'person_name' => (string)($person['name'] ?? ''),
    'person_id'   => (string)($person['id'] ?? $pid),
    'main_name'   => (string)($main['name'] ?? ''),
    'paid'        => $paid,
    'due'         => $due,
    'open'        => $open,
]);
