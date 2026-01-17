<?php
declare(strict_types=1);

// public/food_bon/pdf.php
// WICHTIG: Fehler anzeigen für Debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Output buffer für PDF
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Auth/AuthContext.php';
require_once __DIR__ . '/../../src/Auth/AdminContext.php';
require_once __DIR__ . '/../../src/Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../../src/Security/FoodBonToken.php';
require_once __DIR__ . '/../../src/Http/Request.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    Bootstrap::init();

    $orderId = trim(Request::getString('order_id'));
    if ($orderId === '') {
        ob_end_clean();
        http_response_code(400);
        exit('Bestellnummer fehlt');
    }

    // Bestellung laden
    $order = FoodOrderRepository::findByOrderId($orderId);
    if (!$order) {
        ob_end_clean();
        http_response_code(404);
        exit('Bestellung nicht gefunden');
    }

    // Zugriffskontrolle: Admin ODER Besitzer mit bezahlter Bestellung
    $isAdmin = AdminContext::isAdmin();
    $isOwner = false;

    if (!$isAdmin) {
        // Normale User müssen eingeloggt sein
        if (!AuthContext::isLoggedIn()) {
            ob_end_clean();
            AuthContext::requireLogin('/login.php');
        }
        
        $mainId = AuthContext::mainId();
        $isOwner = ($order['main_id'] === $mainId);
        
        // Nur eigene Bestellungen
        if (!$isOwner) {
            ob_end_clean();
            http_response_code(403);
            exit('Zugriff verweigert');
        }
        
        // Nur bezahlte oder eingelöste
        if (!in_array($order['status'], ['paid', 'redeemed'])) {
            ob_end_clean();
            http_response_code(403);
            exit('Bestellung muss bezahlt sein');
        }
    }

    $orderMainId = $order['main_id'] ?? '';
    $main = ParticipantsRepository::findById($orderMainId);
    $mainName = $main ? ($main['name'] ?? 'Unbekannt') : 'Unbekannt';

    // QR-Code generieren (wie bei Tickets: mit Signatur)
    $sig = FoodBonToken::sign($orderId);
    $verifyUrl = Config::baseUrl() . '/food_bon/verify.php'
        . '?order_id=' . rawurlencode($orderId)
        . '&sig=' . rawurlencode($sig);

    $qrResult = Builder::create()
        ->writer(new PngWriter())
        ->data($verifyUrl)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(ErrorCorrectionLevel::High)
        ->size(300)
        ->margin(10)
        ->build();

    $qrDataUri = 'data:image/png;base64,' . base64_encode($qrResult->getString());

    // HTML für PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .bon { border: 3px solid #c9a227; padding: 30px; border-radius: 10px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #c9a227; padding-bottom: 20px; }
            .header h1 { font-size: 28px; color: #c9a227; margin: 0 0 10px 0; }
            .order-id { font-size: 24px; font-weight: bold; }
            .items { margin: 20px 0; }
            .item { padding: 8px 0; border-bottom: 1px dashed #ccc; }
            .total { font-size: 20px; font-weight: bold; text-align: right; margin-top: 20px; padding-top: 10px; border-top: 2px solid #333; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            .qr { text-align: center; margin: 20px 0; }
            .status { background: #28a745; color: white; padding: 8px 15px; border-radius: 5px; display: inline-block; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="bon">
            <div class="header">
                <h1>ESSENSBON</h1>
                <div class="order-id">Bestellung: ' . htmlspecialchars($orderId) . '</div>
                <p><strong>' . htmlspecialchars($mainName) . '</strong> (ID: ' . htmlspecialchars($orderMainId) . ')</p>
                <div class="status">BEZAHLT &amp; GUELTIG</div>
            </div>

            <div class="items">
                <h3>Bestellte Artikel:</h3>';

    foreach ($order['items'] as $item) {
        $html .= '<div class="item">
            <strong>' . htmlspecialchars((string)($item['quantity'] ?? '1')) . 'x</strong> ' .
            htmlspecialchars($item['name'] ?? 'Unbekannt') . '
            <span style="float: right;">' . number_format((float)($item['subtotal'] ?? 0), 2, ',', '.') . ' EUR</span>
        </div>';
    }

    $html .= '
            </div>

            <div class="total">
                Gesamtpreis: ' . number_format((float)($order['total_price'] ?? 0), 2, ',', '.') . ' EUR
            </div>

            <div class="qr">
                <img src="' . $qrDataUri . '" alt="QR Code" width="250">
                <p style="font-size: 12px; margin-top: 10px;">Zur Validierung scannen</p>
            </div>

            <div class="footer">
                <p><strong>Abiball 2026 - BSZ Leonberg</strong></p>
                <p>10.07.2026 | Stadthalle Leonberg</p>
                <p style="font-size: 10px;">Dieser Bon ist nur einmalig einloesbar. Bitte bei der Essensausgabe vorzeigen.</p>
            </div>
        </div>
    </body>
    </html>';

    // PDF generieren
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output buffer leeren vor PDF-Ausgabe
    ob_end_clean();

    // Ausgabe
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Essensbon_' . $orderId . '.pdf"');
    echo $dompdf->output();

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo 'Fehler beim Generieren des PDFs: ' . htmlspecialchars($e->getMessage());
    if (Config::isDev()) {
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
}
