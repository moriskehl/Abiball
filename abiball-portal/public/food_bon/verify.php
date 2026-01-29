<?php
declare(strict_types=1);

// public/food_bon/verify.php
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Security/FoodBonToken.php';
require_once __DIR__ . '/../../src/Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../../src/View/Layout.php';
require_once __DIR__ . '/../../src/Http/Request.php';

Bootstrap::init();

// Unterstütze sowohl altes token-Format als auch neues order_id+sig Format
$orderId = trim(Request::getString('order_id'));
$sig = trim(Request::getString('sig'));
$token = trim(Request::getString('token'));

$validation = null;
$order = null;

// Neues Format: order_id + sig (wie bei Tickets)
if ($orderId !== '' && $sig !== '') {
    if (FoodBonToken::verify($orderId, $sig)) {
        $order = FoodOrderRepository::findByOrderId($orderId);
        if ($order) {
            $validation = [
                'order' => $order,
                'valid' => in_array($order['status'], ['paid', 'redeemed'])
            ];
        }
    }
}
// Fallback: altes token-Format
elseif ($token !== '') {
    $tokenData = FoodBonToken::validate($token);
    if ($tokenData && isset($tokenData['order_id'])) {
        $order = FoodOrderRepository::findByOrderId($tokenData['order_id']);
        if ($order) {
            $validation = [
                'order' => $order,
                'valid' => in_array($order['status'], ['paid', 'redeemed'])
            ];
        }
    }
}

Layout::header('Bon-Validierung');
?>

<main class="bg-starfield">
  <!-- Star layers -->
  <div class="stars-layer-1"></div>
  <div class="stars-layer-2"></div>
  <div class="stars-layer-3"></div>

  <div class="container py-5" style="max-width: 600px;">
    <div class="glass-hero-header sm mb-5 animate-fade-up text-center mx-auto" style="max-width: 560px;">
      <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
        Bon-Validierung
      </h1>
    </div>

    <div class="card">
      <div class="card-body p-5 text-center">
        <?php if ($validation === null): ?>
          <div class="alert alert-danger">
            <h3>Ungültiger Bon</h3>
            <p>Dieser QR-Code ist ungültig oder abgelaufen.</p>
          </div>
        <?php elseif (!$validation['valid']): ?>
          <div class="alert alert-warning">
            <h3>Bon nicht bezahlt</h3>
            <p>Bestellung <?= e($validation['order']['order_id']) ?> ist noch nicht bezahlt.</p>
          </div>
        <?php else: ?>
          <?php $order = $validation['order']; ?>
          <div class="alert alert-success">
            <h3>Bon gültig</h3>
          </div>
          <h4 class="mb-3">Bestellung <?= e($order['order_id']) ?></h4>
          <div class="text-start">
            <strong>Bestellte Artikel:</strong>
            <?php if (!empty($order['items']) && is_array($order['items'])): ?>
            <ul class="mt-2">
              <?php foreach ($order['items'] as $item): ?>
                <li><?= e((string)($item['quantity'] ?? 1)) ?>× <?= e($item['name'] ?? 'Unbekannt') ?></li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted mt-2">Keine Artikeldetails verfügbar.</p>
            <?php endif; ?>
          </div>
          <p class="mt-3">
            <strong>Status:</strong> 
            <?= $order['status'] === 'redeemed' ? 'Bereits eingelöst' : 'Bereit zur Ausgabe' ?>
          </p>
          <?php if (!empty($order['total_price'])): ?>
          <p>
            <strong>Gesamtpreis:</strong> 
            <?= number_format((float)$order['total_price'], 2, ',', '.') ?> €
          </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php Layout::footer(); ?>
