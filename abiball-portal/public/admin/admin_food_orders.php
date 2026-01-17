<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Security/AdminGuard.php';
require_once __DIR__ . '/../../src/Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../../src/Repository/MenuRepository.php';
require_once __DIR__ . '/../../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../../src/Http/Request.php';
require_once __DIR__ . '/../../src/View/Layout.php';

Bootstrap::init();
requireAdmin();

$repo = new FoodOrderRepository();
$menu = MenuRepository::load();
$participants = ParticipantsRepository::all();

// Statistiken laden
$stats = $repo->getStatistics();

// Filter
$filterStatus = Request::getString('status', 'all');
$searchQuery = Request::getString('search', '');

// Alle Bestellungen laden (gruppiert nach OrderID)
$allOrders = FoodOrderRepository::getAllOrders();

// Filter anwenden
$allOrders = array_filter($allOrders, function($order) use ($filterStatus, $searchQuery) {
    // Status-Filter
    if ($filterStatus !== 'all' && $order['status'] !== $filterStatus) {
        return false;
    }
    
    // Such-Filter
    if ($searchQuery && 
        stripos($order['order_id'], $searchQuery) === false && 
        stripos($order['main_id'], $searchQuery) === false) {
        return false;
    }
    
    return true;
});

$allOrders = array_values($allOrders);

// Nach Erstellungsdatum sortieren (neueste zuerst)
usort($allOrders, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

Layout::header('Essensbestellungen verwalten');
?>

<style>
.status-badge {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
    border-radius: 4px;
    font-weight: 500;
}
.status-open { background: #ffc107; color: #000; }
.status-paid { background: #0dcaf0; color: #000; }
.status-redeemed { background: #198754; color: #fff; }
.status-cancelled { background: #dc3545; color: #fff; }

.order-card {
    border-left: 4px solid var(--bs-primary);
    transition: all 0.2s;
}
.order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}
.order-items {
    background: var(--bs-light);
    padding: 0.75rem;
    border-radius: 4px;
    margin: 0.5rem 0;
}
.order-items li {
    margin-bottom: 0.25rem;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h-serif mb-0">Essensbestellungen</h1>
        <a href="/admin/admin_dashboard.php" class="btn btn-outline-secondary">← Zurück zum Dashboard</a>
    </div>

    <!-- Statistiken -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Suche (Bestellnr. / Hauptgast-ID)</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= htmlspecialchars($searchQuery) ?>" 
                           placeholder="z.B. FOOD001 oder ABI001">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Suchen
                    </button>
                    <a href="/admin/admin_food_orders.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Zurücksetzen
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bestellungen -->
    <div class="row">
        <?php if (empty($allOrders)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Keine Bestellungen gefunden.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($allOrders as $order): ?>
                <?php
                // Hauptgast-Name ermitteln
                $participant = array_filter($participants, fn($p) => $p['main_id'] === $order['main_id']);
                $participantName = !empty($participant) ? reset($participant)['name'] : 'Unbekannt';
                
                // Items sind jetzt direkt im Order-Array
                $items = $order['items'] ?? [];
                ?>
                <div class="col-lg-6 mb-3">
                    <div class="card order-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <strong><?= htmlspecialchars($order['order_id']) ?></strong>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($participantName) ?>
                                        <small>(<?= htmlspecialchars($order['main_id']) ?>)</small>
                                    </p>
                                </div>
                                <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                    <?php
                                    $statusLabels = [
                                        'open' => 'Offen',
                                        'paid' => 'Bezahlt',
                                        'redeemed' => 'Eingelöst',
                                        'cancelled' => 'Storniert'
                                    ];
                                    echo $statusLabels[$order['status']] ?? $order['status'];
                                    ?>
                                </span>
                            </div>

                            <!-- Items -->
                            <div class="order-items">
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($items as $item): ?>
                                        <li>
                                            <span class="badge bg-secondary"><?= (int)$item['quantity'] ?>x</span>
                                            <?= htmlspecialchars($item['name']) ?>
                                            <span class="float-end"><?= number_format((float)$item['subtotal'], 2) ?>€</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <strong>Gesamt:</strong>
                                    <strong><?= number_format((float)$order['total_price'], 2) ?>€</strong>
                                </div>
                            </div>

                            <!-- Timestamps -->
                            <div class="small text-muted mt-2">
                                <div><i class="bi bi-clock"></i> Erstellt: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                                <?php if ($order['status'] === 'redeemed' && $order['redeemed_at']): ?>
                                    <div><i class="bi bi-check-circle"></i> Eingelöst: <?= date('d.m.Y H:i', strtotime($order['redeemed_at'])) ?> 
                                        von <?= htmlspecialchars($order['redeemed_by']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Aktionen -->
                            <div class="mt-3 d-flex gap-2">
                                <?php if ($order['status'] === 'open'): ?>
                                    <form method="post" action="/admin/admin_food_order_update_paid.php" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                        <input type="hidden" name="paid_amount" value="<?= htmlspecialchars($order['total_price']) ?>">
                                        <button type="submit" class="btn btn-sm btn-success" 
                                                onclick="return confirm('Zahlung als erhalten markieren?')">
                                            <i class="bi bi-check-circle"></i> Als bezahlt markieren
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['status'], ['paid', 'redeemed'])): ?>
                                    <a href="/food_bon/pdf.php?order_id=<?= urlencode($order['order_id']) ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-file-pdf"></i> Bon anzeigen
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php Layout::footer(); ?>
