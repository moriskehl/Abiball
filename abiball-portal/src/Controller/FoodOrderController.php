<?php
declare(strict_types=1);

// src/Controller/FoodOrderController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Auth/AuthContext.php';
require_once __DIR__ . '/../Repository/MenuRepository.php';
require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Service/FoodOrderService.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class FoodOrderController
{
    public static function show(): void
    {
        Bootstrap::init();
        AuthContext::requireLogin('/login.php');

        $mainId = AuthContext::mainId();
        if ($mainId === '') {
            header('Location: /login.php');
            exit;
        }

        $username = AuthContext::name();
        $menu = MenuRepository::load();
        $orderRepo = new FoodOrderRepository();
        $myOrders = $orderRepo->findByMainId($mainId);

        // Sort orders by created_at DESC
        usort($myOrders, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        $ok = Request::getString('ok');
        $err = Request::getString('err');

        // Hauptgast-Name holen
        $main = ParticipantsRepository::findById($mainId);
        $mainName = $main ? ($main['name'] ?? 'Unbekannt') : 'Unbekannt';

        Layout::header('Essensbestellung');
        self::renderView($mainId, $username, $mainName, $menu, $myOrders, $ok, $err);
        Layout::footer();
    }

    public static function create(): void
    {
        Bootstrap::init();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('/food/food_order.php');
        }

        AuthContext::requireLogin('/login.php');

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/food/food_order.php?err=csrf');
        }

        $mainId = AuthContext::mainId();
        $itemsRaw = $_POST['items'] ?? [];

        // SECURITY: Validate that itemsRaw is an array
        if (!is_array($itemsRaw)) {
            Response::redirect('/food/food_order.php?err=invalid');
        }

        // SECURITY: Get all valid menu items first
        $menu = MenuRepository::load();
        $validItemIds = [];
        foreach ($menu['categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $validItemIds[(string)($item['id'] ?? '')] = true;
            }
        }

        // Items aufbereiten with full validation
        $items = [];
        foreach ($itemsRaw as $itemId => $data) {
            if (!is_array($data)) {
                continue; // Skip invalid entries
            }

            if (!empty($data['selected'])) {
                $id = trim((string)($data['id'] ?? ''));
                $qty = (int)($data['quantity'] ?? 1);

                // SECURITY: Only accept known menu items
                if (!isset($validItemIds[$id])) {
                    continue; // Skip unknown item
                }

                // SECURITY: Validate quantity (between 1 and 50)
                if ($qty < 1 || $qty > 50) {
                    continue; // Skip invalid quantity
                }

                // SECURITY: Prevent duplicate items in same order
                $alreadyAdded = false;
                foreach ($items as $item) {
                    if ($item['id'] === $id) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if ($alreadyAdded) {
                    continue;
                }

                $items[] = [
                    'id' => $id,
                    'quantity' => $qty
                ];
            }
        }

        if (empty($items)) {
            Response::redirect('/food/food_order.php?err=empty');
        }

        $result = FoodOrderService::createOrder($mainId, $items);

        if ($result['success']) {
            Response::redirect('/food/food_order.php?ok=created');
        } else {
            $error = $result['error'] ?? 'unknown';
            Response::redirect('/food/food_order.php?err=' . urlencode($error));
        }
    }

    public static function cancel(): void
    {
        Bootstrap::init();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('/food/food_order.php');
        }

        AuthContext::requireLogin('/login.php');

        if (!Csrf::validate(Request::postString('_csrf'))) {
            Response::redirect('/food/food_order.php?err=csrf');
        }

        $mainId = AuthContext::mainId();
        $orderId = Request::postString('order_id');

        if ($orderId === '') {
            Response::redirect('/food/food_order.php?err=not_found');
        }

        $success = FoodOrderService::cancelOrder($orderId, $mainId);

        if ($success) {
            Response::redirect('/food/food_order.php?ok=cancelled');
        } else {
            Response::redirect('/food/food_order.php?err=not_open');
        }
    }

    private static function renderView(
        string $mainId,
        string $username,
        string $mainName,
        array $menu,
        array $myOrders,
        string $ok,
        string $err
    ): void {
        ?>
<style>
.menu-category { margin-bottom: 2rem; }
.menu-item {
  display: flex;
  align-items: center;
  padding: 14px 16px;
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 10px;
  transition: all 0.2s;
  background: var(--surface);
}
.menu-item:hover {
  background: rgba(201,162,39,.06);
  border-color: var(--gold);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.menu-item input[type="checkbox"] {
  width: 20px;
  height: 20px;
  margin-right: 14px;
  cursor: pointer;
  accent-color: var(--gold);
}
.menu-item-name {
  flex: 1;
  font-weight: 500;
  cursor: pointer;
}
.menu-item-price {
  color: var(--gold);
  font-weight: 600;
  margin-right: 14px;
  font-size: 1.05rem;
}
.menu-item-qty {
  width: 75px;
}
.order-card {
  border-left: 4px solid var(--border);
  margin-bottom: 1rem;
  transition: all 0.2s;
}
.order-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  transform: translateY(-1px);
}
.order-card.status-open { border-left-color: #ffa500; }
.order-card.status-paid { border-left-color: var(--success); }
.order-card.status-redeemed { border-left-color: #6c757d; }
.order-card.status-cancelled { border-left-color: var(--danger); }

/* Segment Switch - moderner Toggle zwischen Ansichten */
.segment-switch {
  display: inline-flex;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 4px;
  gap: 4px;
  margin-bottom: 1.5rem;
}

.segment-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1.25rem;
  border: none;
  border-radius: 9px;
  background: transparent;
  color: var(--muted);
  font-weight: 500;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.segment-btn:hover {
  color: var(--text);
  background: rgba(201,162,39,.06);
}

.segment-btn.active {
  background: var(--gold);
  color: #0b0b0f;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(201,162,39,.25);
}

.segment-btn .badge {
  background: rgba(0,0,0,.15);
  color: inherit;
  font-size: 0.7rem;
  padding: 0.2rem 0.45rem;
  border-radius: 5px;
  font-weight: 600;
}

.segment-btn.active .badge {
  background: rgba(0,0,0,.2);
}

.food-tab-content {
  display: none;
}

.food-tab-content.active {
  display: block;
}

.payment-info-box {
  background: rgba(201,162,39,.10);
  border: 1px solid rgba(201,162,39,.35);
  border-radius: 14px;
  padding: 1.5rem;
}
.total-card {
  background: linear-gradient(135deg, rgba(201,162,39,.08), rgba(201,162,39,.04));
  border: 1px solid rgba(201,162,39,.25);
  border-radius: 16px;
  padding: 1.5rem;
}
.btn-create-order {
  background: linear-gradient(180deg, var(--gold-2), var(--gold));
  border: 1px solid rgba(0,0,0,.12);
  color: #0b0b0f !important;
  font-weight: 700;
  padding: 0.85rem 1.5rem;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(201,162,39,.25);
  transition: all 0.2s;
}
.btn-create-order:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(201,162,39,.35);
  filter: brightness(1.05);
}
.btn-create-order:active {
  transform: translateY(0);
}
.btn-soft {
  border-radius: 10px;
  padding: 0.5rem 1rem;
  transition: all 0.2s;
}
.btn-soft:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.12);
}
</style>

<main class="bg-starfield">
  <div class="container py-4" style="max-width: 1200px;">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">Essensbestellung</div>
        <h1 class="h-serif mb-1" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300; line-height: 1.1;">
          Speisekarte & Bestellungen
        </h1>
        <div class="text-muted" style="font-size:.95rem; line-height:1.6; max-width: 68ch;">
          Verwalte deine Essensbestellungen für den Abiball
        </div>
      </div>
      <a class="btn btn-outline-secondary btn-soft" href="/dashboard.php">Zurück</a>
    </div>

    <?php if ($ok === 'created'): ?>
      <div class="alert alert-success">Bestellung wurde erstellt! Bitte überweise den Betrag, um den Bon freizuschalten.</div>
    <?php endif; ?>

    <?php if ($ok === 'cancelled'): ?>
      <div class="alert alert-success">Bestellung wurde storniert.</div>
    <?php endif; ?>

    <?php if ($err !== ''): ?>
      <div class="alert alert-danger">
        <?php
          echo match ($err) {
            'csrf' => 'Ungültige Anfrage (CSRF).',
            'empty' => 'Bitte wähle mindestens ein Produkt aus.',
            'no_valid_items' => 'Keine gültigen Artikel ausgewählt.',
            'create_failed' => 'Speichern fehlgeschlagen.',
            'not_found' => 'Bestellung nicht gefunden.',
            'not_open' => 'Nur offene Bestellungen können storniert werden.',
            default => 'Ein Fehler ist aufgetreten.'
          };
        ?>
      </div>
    <?php endif; ?>

    <!-- Segment Switch -->
    <div class="segment-switch">
      <button class="segment-btn active" data-tab="orders" type="button">
        Meine Bestellungen
        <?php if (count($myOrders) > 0): ?>
          <span class="badge"><?= count($myOrders) ?></span>
        <?php endif; ?>
      </button>
      <button class="segment-btn" data-tab="new" type="button">
        Neue Bestellung
      </button>
    </div>

    <!-- Tab Content -->
    <div id="tab-orders" class="food-tab-content active">
        <?php if (empty($myOrders)): ?>
          <div class="text-center text-muted py-5">
            <div class="mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-basket" viewBox="0 0 16 16" style="opacity: 0.3;">
                <path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h9.234L10.07 1.757a.5.5 0 1 1 .858-.514L13.783 6H15a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1v4.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 13.5V9a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h1.217L5.07 1.243a.5.5 0 0 1 .686-.172zM2 9v4.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V9H2zM1 7v1h14V7H1zm3 3a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 4 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 6 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 8 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5z"/>
              </svg>
            </div>
            <h4>Noch keine Bestellungen</h4>
            <p>Du hast noch keine Essensbestellung für den Abiball aufgegeben.</p>
            <button class="btn btn-create-order mt-3" onclick="switchToTab('new')" type="button">
              Erste Bestellung erstellen
            </button>
          </div>
        <?php else: ?>
          <?php foreach ($myOrders as $order): ?>
            <?php
              $orderId = $order['order_id'] ?? 'UNKNOWN';
              $status = $order['status'] ?? 'unknown';
              
              // Items direkt aus dem Order-Array (bereits vom Repository gruppiert)
              $orderItems = $order['items'] ?? [];
              
              $totalPrice = (float)($order['total_price'] ?? 0);
              $createdAt = !empty($order['created_at']) ? date('d.m.Y H:i', strtotime($order['created_at'])) : 'Unbekannt';
              $redeemedAt = !empty($order['redeemed_at']) ? date('d.m.Y H:i', strtotime($order['redeemed_at'])) : '';

              $statusText = match($status) {
                'open' => 'Offen',
                'paid' => 'Bezahlt',
                'redeemed' => 'Eingelöst',
                'cancelled' => 'Storniert',
                default => $status
              };
              
              $badgeClass = match($status) {
                'open' => 'bg-warning text-dark',
                'paid' => 'bg-success',
                'redeemed' => 'bg-secondary',
                'cancelled' => 'bg-danger',
                default => 'bg-secondary'
              };
            ?>

            <div class="card order-card status-<?= e($status) ?>">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <h5 class="mb-1">Bestellung <?= e($orderId) ?></h5>
                    <div class="text-muted small">Erstellt am <?= e($createdAt) ?></div>
                  </div>
                  <div><span class="badge <?= $badgeClass ?>"><?= e($statusText) ?></span></div>
                </div>

                <?php if (!empty($orderItems)): ?>
                <div class="mb-3">
                  <strong>Bestellte Artikel:</strong>
                  <ul class="mb-0 mt-1" style="line-height: 1.8;">
                    <?php foreach ($orderItems as $item): ?>
                      <li>
                        <?= e((string)((int)($item['quantity'] ?? 1))) ?>× <?= e($item['name'] ?? 'Unbekannt') ?>
                        <span class="text-muted">(<?= number_format(((float)($item['price'] ?? 0)) * ((int)($item['quantity'] ?? 1)), 2, ',', '.') ?> €)</span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span><strong>Gesamtpreis:</strong></span>
                  <span style="font-size: 1.2rem; font-weight: 600; color: var(--gold);">
                    <?= number_format($totalPrice, 2, ',', '.') ?> €
                  </span>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-3">
                  <small>Keine Artikeldetails verfügbar.</small>
                </div>
                <?php endif; ?>

                <?php if ($status === 'open'): ?>
                  <div class="alert alert-warning mb-3" style="font-size: 0.9rem;">
                    <strong>Bezahlung ausstehend</strong><br>
                    Bitte überweise <strong><?= number_format($totalPrice, 2, ',', '.') ?> €</strong> mit folgendem Verwendungszweck:<br>
                    <div class="p-soft mt-2" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.85rem;">
                      <strong style="color: var(--gold);">Essensbestellung | Name: <?= e($username) ?> | ID: <?= e($orderId) ?></strong>
                    </div>
                    <hr class="my-2">
                    <small>
                      <strong>Empfänger:</strong> Bahaa Albasha<br>
                      <strong>IBAN:</strong> <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">DE76 6035 0130 1002 6462 65</span><br>
                      Nach Zahlungseingang (1-2 Werktage) wird die Bestellung vom Admin freigegeben.
                    </small>
                  </div>
                  <form method="post" action="/food/food_order_cancel.php" onsubmit="return confirm('Bestellung wirklich stornieren?');">
                    <?= Csrf::inputField() ?>
                    <input type="hidden" name="order_id" value="<?= e($orderId) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger btn-soft">Bestellung stornieren</button>
                  </form>
                <?php endif; ?>

                <?php if ($status === 'paid' || $status === 'redeemed'): ?>
                  <div class="d-flex gap-2">
                    <a href="/food_bon/pdf.php?order_id=<?= urlencode($orderId) ?>" class="btn btn-outline-primary btn-soft" target="_blank">
                      Bon-PDF herunterladen
                    </a>
                  </div>
                <?php endif; ?>

                <?php if ($status === 'redeemed' && $redeemedAt): ?>
                  <div class="text-muted small mt-2">
                    Eingelöst am <?= e($redeemedAt) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Tab: Neue Bestellung -->
      <div class="food-tab-content" id="tab-new">
        
        <!-- Zahlungsinformation -->
        <div class="payment-info-box mb-4">
          <div class="d-flex align-items-start gap-2 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="var(--gold)" viewBox="0 0 16 16" style="flex-shrink: 0; margin-top: 2px;">
              <path d="M4 9.42h1.063C5.4 12.323 7.317 14 10.34 14c.622 0 1.167-.068 1.659-.185v-1.3c-.484.119-1.045.17-1.659.17-2.1 0-3.455-1.198-3.775-3.264h4.017v-.928H6.497v-.936q0-.263.02-.496h3.926v-.928H6.64C6.978 3.937 8.318 2.8 10.34 2.8c.614 0 1.175.05 1.659.177V1.68A6.4 6.4 0 0 0 10.34 1.5c-3.022 0-4.94 1.684-5.277 4.581H4v.928h1.019q-.016.266-.016.543v.389H4z"/>
            </svg>
            <div>
              <h5 class="mb-2" style="font-weight: 600;">Zahlung per Überweisung</h5>
              <p class="mb-2" style="line-height: 1.6;">Nach der Bestellung überweise bitte den Gesamtbetrag mit folgendem Verwendungszweck:</p>
            </div>
          </div>
          <div class="p-soft" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.9rem;">
            <strong style="color: var(--gold);">Essensbestellung | Name: <?= e($mainName) ?> | ID: <?= e($mainId) ?></strong>
          </div>
          <hr class="my-3">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="mb-2"><strong>Empfänger:</strong> <span class="text-muted">Bahaa Albasha</span></div>
              <div class="mb-2"><strong>IBAN:</strong> <span class="fw-semibold" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">DE76 6035 0130 1002 6462 65</span></div>
              <div><strong>BIC:</strong> <span class="fw-semibold" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">BBKRDE6BXXX</span></div>
            </div>
            <div class="col-md-6">
              <div class="small" style="line-height: 1.7;">
                <strong>Hinweis:</strong><br>
                Nach Zahlungseingang (1-2 Werktage) wird deine Bestellung als "Bezahlt" markiert 
                und du kannst den Bon mit QR-Code herunterladen. Diesen zeigst du bei der Essensausgabe vor.
              </div>
            </div>
          </div>
        </div>

        <form method="post" action="/food/food_order_create.php" id="order-form">
          <?= Csrf::inputField() ?>

          <?php foreach ($menu['categories'] ?? [] as $category): ?>
            <div class="menu-category">
              <h3 class="h5 mb-3"><?= e($category['name']) ?></h3>
              <?php if (!empty($category['description'])): ?>
                <div class="text-muted small mb-3"><?= e($category['description']) ?></div>
              <?php endif; ?>

              <?php foreach ($category['items'] ?? [] as $item): ?>
                <?php if (!($item['available'] ?? false)) continue; ?>

                <div class="menu-item">
                  <input
                    type="checkbox"
                    name="items[<?= e($item['id']) ?>][selected]"
                    value="1"
                    id="item_<?= e($item['id']) ?>"
                    onchange="updateTotal()"
                  >
                  <label class="menu-item-name" for="item_<?= e($item['id']) ?>">
                    <?= e($item['name']) ?>
                    <?php if ($item['fish'] ?? false): ?>
                      <span class="badge text-bg-info" style="font-size: 0.7rem;">Fisch</span>
                    <?php endif; ?>
                  </label>
                  <div class="menu-item-price" data-price="<?= $item['price'] ?>">
                    <?= number_format($item['price'], 2, ',', '.') ?> €
                  </div>
                  <input
                    type="number"
                    name="items[<?= e($item['id']) ?>][quantity]"
                    class="form-control form-control-sm menu-item-qty"
                    value="1"
                    min="1"
                    max="99"
                    id="qty_<?= e($item['id']) ?>"
                    onchange="updateTotal()"
                  >
                  <input type="hidden" name="items[<?= e($item['id']) ?>][id]" value="<?= e($item['id']) ?>">
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <div class="total-card mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="mb-0" style="font-weight: 600;">Gesamtsumme:</h4>
              <h2 class="mb-0" style="color: var(--gold); font-weight: 700;" id="total-price">0,00 €</h2>
            </div>
            <button type="submit" class="btn btn-create-order w-100">Bestellung erstellen</button>
            <div class="text-muted small mt-3 text-center" style="line-height: 1.6;">
              Die Bestellung ist nach Überweisung des Betrags gültig und kann dann als Bon-PDF heruntergeladen werden.
            </div>
          </div>
        </form>
      </div>

    </div>

  </div>
</main>

<script>
function updateTotal() {
  let total = 0;
  document.querySelectorAll('.menu-item').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const qtyInput = item.querySelector('.menu-item-qty');
    const priceElem = item.querySelector('.menu-item-price');

    if (checkbox && checkbox.checked) {
      const price = parseFloat(priceElem.dataset.price || 0);
      const qty = parseInt(qtyInput.value || 1);
      total += price * qty;
    }
  });

  document.getElementById('total-price').textContent = total.toFixed(2).replace('.', ',') + ' €';
}

// Tab-Switching Funktion für Segment Switch
function switchToTab(tabName) {
  // Alle Segment-Buttons deaktivieren
  document.querySelectorAll('.segment-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Alle Tab-Contents verstecken
  document.querySelectorAll('.food-tab-content').forEach(content => {
    content.classList.remove('active');
  });
  
  // Aktiven Button aktivieren
  const activeButton = document.querySelector(`.segment-btn[data-tab="${tabName}"]`);
  if (activeButton) {
    activeButton.classList.add('active');
  }
  
  // Ziel-Tab aktivieren
  const targetContent = document.getElementById('tab-' + tabName);
  if (targetContent) {
    targetContent.classList.add('active');
  }
}

// Event-Listener
(function() {
  // Initial calculation
  updateTotal();
  
  // Segment-Buttons mit Click-Handler versehen
  document.querySelectorAll('.segment-btn').forEach(function(button) {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const tabName = this.getAttribute('data-tab');
      switchToTab(tabName);
    });
  });
})();
</script>
        <?php
    }
}
