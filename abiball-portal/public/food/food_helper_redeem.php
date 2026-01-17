<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Auth/FoodHelperContext.php';
require_once __DIR__ . '/../../src/Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../../src/Repository/MenuRepository.php';
require_once __DIR__ . '/../../src/Security/FoodBonToken.php';
require_once __DIR__ . '/../../src/Http/Request.php';

Bootstrap::init();

header('Content-Type: application/json');

// Nur für eingeloggte Food Helper
if (!FoodHelperContext::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Nicht autorisiert. Bitte einloggen.'
    ]);
    exit;
}

// Nur POST erlaubt
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Methode nicht erlaubt.'
    ]);
    exit;
}

$repo = new FoodOrderRepository();
$menu = MenuRepository::load();

$orderId = null;

// Token aus QR-Code oder manuelle Eingabe?
$token = Request::postString('token');
$manualOrderId = Request::postString('order_id');

try {
    if ($token) {
        // Token-basiert (QR-Code gescannt)
        $tokenData = FoodBonToken::validate($token);
        
        if (!$tokenData) {
            throw new Exception('Ungültiger oder abgelaufener Token.');
        }
        
        $orderId = $tokenData['order_id'];
    } elseif ($manualOrderId) {
        // Manuelle Eingabe
        $orderId = strtoupper(trim($manualOrderId));
        
        if (!preg_match('/^FOOD\d{3,}$/', $orderId)) {
            throw new Exception('Ungültige Bestellnummer. Format: FOOD001');
        }
    } else {
        throw new Exception('Kein Token oder Bestellnummer angegeben.');
    }

    // Bestellung laden
    $order = $repo->findByOrderId($orderId);
    
    if (!$order) {
        throw new Exception('Bestellung nicht gefunden.');
    }

    // Status prüfen
    if ($order['status'] === 'redeemed') {
        throw new Exception('Diese Bestellung wurde bereits eingelöst am ' . 
                          date('d.m.Y H:i', strtotime($order['redeemed_at'])) . ' Uhr von ' . 
                          htmlspecialchars($order['redeemed_by']) . '.');
    }

    if ($order['status'] === 'cancelled') {
        throw new Exception('Diese Bestellung wurde storniert.');
    }

    if ($order['status'] !== 'paid') {
        throw new Exception('Diese Bestellung ist noch nicht bezahlt. Status: ' . $order['status']);
    }

    // Bestellung einlösen
    $helperName = FoodHelperContext::helperName() ?: FoodHelperContext::helperId();
    $success = $repo->redeem($orderId, $helperName);

    if (!$success) {
        throw new Exception('Fehler beim Einlösen der Bestellung.');
    }

    // Items für Anzeige vorbereiten (Items sind jetzt direkt im Order-Array)
    $items = $order['items'] ?? [];
    $itemCount = 0;
    $formattedItems = [];
    
    foreach ($items as $item) {
        $quantity = (int)($item['quantity'] ?? 1);
        $itemCount += $quantity;
        $formattedItems[] = [
            'name' => $item['name'] ?? 'Unbekannt',
            'quantity' => $quantity
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Bestellung erfolgreich eingelöst!',
        'order_id' => $orderId,
        'itemCount' => $itemCount,
        'items' => $formattedItems,
        'total' => number_format((float)($order['total_price'] ?? 0), 2, ',', '.') . ' €',
        'redeemedBy' => $helperName,
        'redeemedAt' => date('d.m.Y H:i')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
