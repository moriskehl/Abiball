<?php
declare(strict_types=1);

// public/ticket/pdf.php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Http/Request.php';
require_once __DIR__ . '/../../src/Http/Response.php';

require_once __DIR__ . '/../../src/Security/SessionGuard.php';
require_once __DIR__ . '/../../src/Repository/TicketRepository.php';
require_once __DIR__ . '/../../src/Service/PricingService.php';
require_once __DIR__ . '/../../src/Config.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

Bootstrap::init();
requireLogin();

// In DB-Welt: canonical session key (empfohlen)
$sessionUserId = (string)($_SESSION['user_id'] ?? $_SESSION['main_id'] ?? '');
if ($sessionUserId === '') {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Input:
 * - Übergangsweise akzeptieren wir weiterhin pid (Person-ID) aus deinem alten System.
 * - Empfohlen: zukünftig ticket_id oder person_id (intern identisch möglich).
 */
$pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
if ($pid === '') {
    http_response_code(400);
    exit('Missing pid');
}

/**
 * DB: Ticket + Person + Main nur laden, wenn sie dem eingeloggten Nutzer gehören.
 * Diese Funktion MUSS Ownership serverseitig durchsetzen:
 * - ticket.main_id === sessionUserId
 */
$ticket = TicketRepository::findOwnedTicketForPdf($pid, $sessionUserId);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found');
}

/**
 * Token für QR:
 * - QR enthält t=<token> (Klartext)
 * - DB speichert nur token_hash
 * TicketRepository::ensureVerifyToken() soll:
 * - falls noch kein Token existiert: generieren, Hash speichern
 * - Klartext-Token zurückgeben (nur für PDF-Ausgabe)
 */
$token = TicketRepository::ensureVerifyToken($ticket);
if (!is_string($token) || $token === '') {
    http_response_code(500);
    exit('Token error');
}

// --------------------
// Preis / Override (DB)
// --------------------
$personId = (string)($ticket['person_id'] ?? $pid);

$priceInfo = PricingService::ticketPriceForPersonId($personId);
// Erwartet: ['price'=>int, 'has_override'=>bool, 'reason'=>?string]
$ticketPrice = (int)($priceInfo['price'] ?? 0);
$hasOverride = (bool)($priceInfo['has_override'] ?? false);
$overrideReason = trim((string)($priceInfo['reason'] ?? ''));

// --------------------
// Logo (Data-URI)
// --------------------
$logoDataUri = '';
$logoPngPath = realpath(__DIR__ . '/../favicon.png'); // public/favicon.png
if ($logoPngPath && is_file($logoPngPath)) {
    $bin = file_get_contents($logoPngPath);
    if ($bin !== false) {
        $logoDataUri = 'data:image/png;base64,' . base64_encode($bin);
    }
}
$logoHtml = ($logoDataUri !== '')
    ? '<img class="logo" src="' . $logoDataUri . '" alt="Logo">'
    : '';

// --------------------
// Verify URL + QR (Base URL aus Config, nicht HTTP_HOST)
// --------------------
$base = rtrim((string)Config::baseUrl(), '/');
if ($base === '') {
    http_response_code(500);
    exit('Base URL not configured');
}
$verifyUrl = $base . '/ticket/verify.php?t=' . rawurlencode($token);

$qr = Builder::create()
    ->writer(new PngWriter())
    ->data($verifyUrl)
    ->size(340)
    ->margin(10)
    ->build();

$qrDataUri = $qr->getDataUri();

// --------------------
// Daten
// --------------------
$personName = (string)($ticket['person_name'] ?? '');
$personPublicId = (string)($ticket['person_public_id'] ?? $ticket['person_id'] ?? $pid); // optional
$mainName   = (string)($ticket['main_name'] ?? '');

$priceText = (string)$ticketPrice . ' €';
$priceNote = '';
if ($hasOverride && $overrideReason !== '') {
    $priceNote = ' (' . htmlspecialchars($overrideReason, ENT_QUOTES, 'UTF-8') . ')';
} elseif ($hasOverride) {
    $priceNote = ' (Override)';
}

// --------------------
// Skalierung (wie bisher)
// --------------------
$SCALE = 0.90;

$PAGE_W = 148.0;
$PAGE_H = 210.0;

$BASE_W = 128.0;
$BASE_H = 190.0;

$TICKET_W = $BASE_W * $SCALE;
$TICKET_H = $BASE_H * $SCALE;

$mm = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . 'mm';
$pt = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . 'pt';

$pad = 10.0 * $SCALE;
$radius = 10.0 * $SCALE;
$border = 2.0 * $SCALE;

$logoSize = 14.0 * $SCALE;
$logoGap  = 4.0  * $SCALE;

$titlePt = 16.0 * $SCALE;
$subPt   =  9.0 * $SCALE;
$textPt  = 10.5 * $SCALE;
$pricePt = 11.0 * $SCALE;

$qrBoxPad = 4.0  * $SCALE;
$qrRadius = 6.0  * $SCALE;
$qrImg    = 52.0 * $SCALE;

$ruleH = 0.6 * $SCALE;
$ruleMargin = 6.0 * $SCALE;

$footPt = 8.5 * $SCALE;

// --------------------
// HTML (Dompdf-stabil)
// --------------------
$html = '
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A5 portrait; margin: 0; }
    html, body { margin: 0; padding: 0; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; }

    table.page {
      width: ' . $mm($PAGE_W) . ';
      height: ' . $mm($PAGE_H) . ';
      border-collapse: collapse;
    }
    td.cell {
      width: ' . $mm($PAGE_W) . ';
      height: ' . $mm($PAGE_H) . ';
      text-align: center;
      vertical-align: middle;
      padding: 0;
    }

    .ticket {
      display: inline-block;
      width: ' . $mm($TICKET_W) . ';
      height: ' . $mm($TICKET_H) . ';
      border: ' . $mm($border) . ' solid #111;
      border-radius: ' . $mm($radius) . ';
      padding: ' . $mm($pad) . ';
      overflow: hidden;
    }

    h1, p { margin: 0; padding: 0; }

    table.head { width: 100%; border-collapse: collapse; }
    table.head td { vertical-align: top; padding: 0; }
    td.left { width: 100%; }
    td.right { width: 1%; text-align: right; white-space: nowrap; }

    .logo {
      width: ' . $mm($logoSize) . ';
      height: ' . $mm($logoSize) . ';
      vertical-align: top;
      margin-right: ' . $mm($logoGap) . ';
    }

    .title { font-size: ' . $pt($titlePt) . '; line-height: 1.1; }
    .sub { margin-top: ' . $mm(1.5*$SCALE) . '; font-size: ' . $pt($subPt) . '; color: #444; }

    .pill {
      display: inline-block;
      padding: ' . $mm(1.2*$SCALE) . ' ' . $mm(3.0*$SCALE) . ';
      border: ' . $mm(1.0*$SCALE) . ' solid #111;
      border-radius: 999px;
      font-size: ' . $pt(9.0*$SCALE) . ';
      font-weight: 700;
    }

    .rule {
      height: ' . $mm($ruleH) . ';
      background: #111;
      opacity: .15;
      margin: ' . $mm($ruleMargin) . ' 0;
    }

    table.grid { width: 100%; border-collapse: collapse; font-size: ' . $pt($textPt) . '; }
    table.grid td { padding: ' . $mm(2.2*$SCALE) . ' 0; vertical-align: top; }
    td.label { width: ' . $mm(32.0*$SCALE) . '; font-weight: 700; }

    .price-line { font-size: ' . $pt($pricePt) . '; }
    .price-strong { font-weight: 800; }

    .qr-wrap { margin-top: ' . $mm(7.0*$SCALE) . '; text-align: center; }
    .qr-title { font-size: ' . $pt(10.0*$SCALE) . '; font-weight: 700; margin-bottom: ' . $mm(3.0*$SCALE) . '; }

    .qr {
      display: inline-block;
      border: ' . $mm(1.0*$SCALE) . ' solid #111;
      border-radius: ' . $mm($qrRadius) . ';
      padding: ' . $mm($qrBoxPad) . ';
    }
    .qr img {
      width: ' . $mm($qrImg) . ';
      height: ' . $mm($qrImg) . ';
      display: block;
    }

    .verify {
      margin-top: ' . $mm(3.0*$SCALE) . ';
      font-size: ' . $pt(8.0*$SCALE) . ';
      color: #555;
      word-break: break-all;
    }

    .foot {
      margin-top: ' . $mm(6.0*$SCALE) . ';
      font-size: ' . $pt($footPt) . ';
      color: #555;
      text-align: left;
    }
  </style>
</head>
<body>
  <table class="page">
    <tr>
      <td class="cell">
        <div class="ticket">

          <table class="head">
            <tr>
              <td class="left">
                ' . $logoHtml . '
                <span style="display:inline-block; vertical-align: top;">
                  <h1 class="title">Abi Ball 2026 – Ticket</h1>
                  <div class="sub">BSZ Leonberg</div>
                </span>
              </td>
              <td class="right">
                <span class="pill">' . htmlspecialchars($personPublicId, ENT_QUOTES, "UTF-8") . '</span>
              </td>
            </tr>
          </table>

          <div class="rule"></div>

          <table class="grid">
            <tr>
              <td class="label">Name</td>
              <td>' . htmlspecialchars($personName, ENT_QUOTES, "UTF-8") . '</td>
            </tr>
            <tr>
              <td class="label">Hauptgast</td>
              <td>' . htmlspecialchars($mainName, ENT_QUOTES, "UTF-8") . '</td>
            </tr>
            <tr>
              <td class="label">Ticketpreis</td>
              <td class="price-line"><span class="price-strong">' . htmlspecialchars($priceText, ENT_QUOTES, "UTF-8") . '</span>' . $priceNote . '</td>
            </tr>
          </table>

          <div class="qr-wrap">
            <div class="qr-title">QR-Check (Einlass)</div>
            <div class="qr">
              <img src="' . $qrDataUri . '" alt="QR Code">
            </div>
            <div class="verify">' . htmlspecialchars($verifyUrl, ENT_QUOTES, "UTF-8") . '</div>
          </div>

          <div class="foot">
            Dieses Ticket ist personalisiert. Bitte beim Einlass bereithalten.
          </div>

        </div>
      </td>
    </tr>
  </table>
</body>
</html>
';

// --------------------
// Dompdf
// --------------------
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

$filename = 'ticket_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$personPublicId) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
