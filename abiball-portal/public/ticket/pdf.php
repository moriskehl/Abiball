<?php
declare(strict_types=1);

// public/ticket/pdf.php
// WICHTIG: verhindert "headers already sent" (z.B. durch Deprecation/Whitespace/BOM)
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Auth/AuthContext.php';
require_once __DIR__ . '/../../src/Security/TicketToken.php';
require_once __DIR__ . '/../../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../../src/Repository/PricingOverridesRepository.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

Bootstrap::init();
AuthContext::requireLogin('/login.php');

$pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
if ($pid === '') { http_response_code(400); exit('Missing pid'); }

$person = ParticipantsRepository::findById($pid);
if (!$person) { http_response_code(404); exit('Person not found'); }

$mainId = (string)($person['main_id'] ?? '');
if ($mainId === '') { http_response_code(500); exit('Invalid main_id'); }

// Ownership: eingeloggter Hauptgast muss zur Gruppe gehören
$sessionMainId = AuthContext::mainId();
if ($sessionMainId === '' || $sessionMainId !== $mainId) {
    http_response_code(403);
    exit('Forbidden');
}

$main = ParticipantsRepository::findById($mainId);
if (!$main) { http_response_code(500); exit('Main user not found'); }

// --------------------
// Preis / Override
// --------------------
$DEFAULT_PRICE = 17;
$override = PricingOverridesRepository::mapById()[$pid] ?? null;

$ticketPrice = $DEFAULT_PRICE;
$overrideReason = '';
$hasOverride = false;

if (is_array($override)) {
    $hasOverride = true;
    $ticketPrice = (int)($override['ticket_price'] ?? $DEFAULT_PRICE);
    $overrideReason = trim((string)($override['reason'] ?? ''));
}

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
// Verify URL + QR (signiert)
// --------------------
$verifyBase = Config::baseUrl() . '/ticket/verify.php';

$sig = TicketToken::sign($pid);

$verifyUrl = $verifyBase
    . '?pid=' . rawurlencode($pid)
    . '&sig=' . rawurlencode($sig);

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
$personName = (string)($person['name'] ?? '');
$personId   = (string)($person['id'] ?? $pid);
$mainName   = (string)($main['name'] ?? '');

$priceText = (string)$ticketPrice . ' €';
$priceNote = '';
if ($hasOverride && $overrideReason !== '') {
    $priceNote = ' (' . htmlspecialchars($overrideReason, ENT_QUOTES, 'UTF-8') . ')';
} elseif ($hasOverride) {
    $priceNote = ' (Override)';
}

// --------------------
// Skalierung
// --------------------
$SCALE = 0.90;

// A5 in mm
$PAGE_W = 148.0;
$PAGE_H = 210.0;

// "Design"-Innenmaß (wie früher bei 10mm Rand): 128 x 190
$BASE_W = 128.0;
$BASE_H = 190.0;

// Finale Ticket-Maße (mm) – garantiert kleiner als Seite
$TICKET_W = $BASE_W * $SCALE;
$TICKET_H = $BASE_H * $SCALE;

// Hilfsfunktionen für saubere CSS-Werte
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
      margin-top: <?= $mm(3.0*$SCALE) ?>;
      font-size: <?= $pt(4.8*$SCALE) ?>;
      color: #555;
      line-height: 1;

      /* entscheidend für Umbruch */
      word-break: break-all;
      overflow-wrap: anywhere;

      /* optisch sauber */
      font-family: DejaVu Sans Mono, monospace;
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
                <span class="pill">' . htmlspecialchars($personId, ENT_QUOTES, "UTF-8") . '</span>
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

// WICHTIG: alles entfernen, was vor dem PDF evtl. ausgegeben wurde
if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'ticket_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $personId) . '.pdf';

// Header
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo $dompdf->output();
exit;
