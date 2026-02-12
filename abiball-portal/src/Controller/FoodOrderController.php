<?php

declare(strict_types=1);

/**
 * FoodOrderController - Essensbestellungen für den Abiball
 * 
 * Ermöglicht Gästen das Bestellen, Stornieren und Anzeigen ihrer Essensbestellungen.
 */

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
  /**
   * Zeigt die Essensbestellungs-Seite mit Menü und bestehenden Bestellungen.
   */
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

    // Bestellungen nach Erstellungsdatum sortieren (neueste zuerst)
    usort($myOrders, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

    $ok = Request::getString('ok');
    $err = Request::getString('err');

    $main = ParticipantsRepository::findById($mainId);
    $mainName = $main ? ($main['name'] ?? 'Unbekannt') : 'Unbekannt';

    Layout::header('Essensbestellung');
    self::renderView($mainId, $username, $mainName, $menu, $myOrders, $ok, $err);
    Layout::footer();
  }

  /**
   * Erstellt eine neue Essensbestellung mit Validierung der Artikel.
   */
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

    // Eingabe muss ein Array sein
    if (!is_array($itemsRaw)) {
      Response::redirect('/food/food_order.php?err=invalid');
    }

    // Nur gültige Menü-Artikel akzeptieren
    $menu = MenuRepository::load();
    $validItemIds = [];
    foreach ($menu['categories'] ?? [] as $category) {
      foreach ($category['items'] ?? [] as $item) {
        $validItemIds[(string)($item['id'] ?? '')] = true;
      }
    }

    // Artikel validieren und aufbereiten
    $items = [];
    foreach ($itemsRaw as $itemId => $data) {
      if (!is_array($data)) {
        continue;
      }

      if (!empty($data['selected'])) {
        $id = trim((string)($data['id'] ?? ''));
        $qty = (int)($data['quantity'] ?? 1);

        // Nur bekannte Artikel akzeptieren
        if (!isset($validItemIds[$id])) {
          continue;
        }

        // Menge muss zwischen 1 und 50 liegen
        if ($qty < 1 || $qty > 50) {
          continue;
        }

        // Keine Duplikate in einer Bestellung
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

  /**
   * Storniert eine offene Essensbestellung.
   */
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

  /**
   * Rendert die Essensbestellungs-View mit Menü und Bestellungen.
   */
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
      /* Food Order Page Styles - Premium Design System */

      /* Menu Categories */
      .menu-category {
        margin-bottom: 2.5rem;
      }

      .menu-category h3 {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
      }

      .menu-category h3::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 40px;
        height: 2px;
        background: var(--gold);
        border-radius: 2px;
      }

      /* Menu Items */
      .menu-item {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        margin-bottom: 12px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        background: var(--surface);
        backdrop-filter: blur(8px);
      }

      .menu-item:hover {
        background: rgba(201, 162, 39, .08);
        border-color: rgba(201, 162, 39, .45);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, .08);
      }

      html.dark .menu-item:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, .25);
      }

      .menu-item input[type="checkbox"] {
        width: 22px;
        height: 22px;
        margin-right: 16px;
        cursor: pointer;
        accent-color: var(--gold);
        border-radius: 6px;
      }

      .menu-item-name {
        flex: 1;
        font-weight: 500;
        cursor: pointer;
        line-height: 1.4;
      }

      .menu-item-price {
        color: var(--gold);
        font-weight: 700;
        margin-right: 16px;
        font-size: 1.1rem;
        white-space: nowrap;
      }

      .menu-item-qty {
        width: 80px;
        text-align: center;
      }

      /* Order Cards */
      .order-card {
        border-left: 4px solid var(--border);
        margin-bottom: 1.25rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .order-card:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .10);
        transform: translateY(-2px);
      }

      html.dark .order-card:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
      }

      .order-card.status-open {
        border-left-color: #ffa726;
      }

      .order-card.status-paid {
        border-left-color: var(--success);
      }

      .order-card.status-redeemed {
        border-left-color: #78909c;
      }

      .order-card.status-cancelled {
        border-left-color: var(--danger);
      }

      /* Segment Switch - Premium Tab Design */
      .segment-switch {
        display: inline-flex;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 5px;
        gap: 4px;
        margin-bottom: 1.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        backdrop-filter: blur(8px);
      }

      html.dark .segment-switch {
        box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
      }

      .segment-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: var(--muted);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
      }

      .segment-btn:hover {
        color: var(--text);
        background: rgba(201, 162, 39, .08);
      }

      .segment-btn.active {
        background: linear-gradient(180deg, var(--gold-2), var(--gold));
        color: #0b0b0f;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(201, 162, 39, .30);
      }

      .segment-btn .badge {
        background: rgba(0, 0, 0, .12);
        color: inherit;
        font-size: 0.75rem;
        padding: 0.25rem 0.55rem;
        border-radius: 6px;
        font-weight: 700;
      }

      .segment-btn.active .badge {
        background: rgba(0, 0, 0, .18);
      }

      /* Tab Content */
      .food-tab-content {
        display: none;
        animation: fadeIn 0.3s ease-out;
      }

      .food-tab-content.active {
        display: block;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(8px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      /* Payment Info Box */
      .payment-info-box {
        background: rgba(201, 162, 39, .08);
        border: 1px solid rgba(201, 162, 39, .30);
        border-radius: var(--radius);
        padding: 1.75rem;
        backdrop-filter: blur(8px);
      }

      html.dark .payment-info-box {
        background: rgba(201, 162, 39, .12);
        border-color: rgba(201, 162, 39, .35);
      }

      /* Total Card */
      .total-card {
        background: linear-gradient(135deg, rgba(201, 162, 39, .10), rgba(201, 162, 39, .04));
        border: 1px solid rgba(201, 162, 39, .28);
        border-radius: var(--radius);
        padding: 1.75rem;
        backdrop-filter: blur(8px);
      }

      html.dark .total-card {
        background: linear-gradient(135deg, rgba(201, 162, 39, .15), rgba(201, 162, 39, .08));
      }

      /* Create Order Button */
      .btn-create-order {
        background: linear-gradient(180deg, var(--gold-2), var(--gold));
        border: 1px solid rgba(0, 0, 0, .12);
        color: #0b0b0f !important;
        font-weight: 700;
        padding: 0.95rem 1.75rem;
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(201, 162, 39, .28);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 1rem;
        letter-spacing: 0.01em;
      }

      .btn-create-order:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(201, 162, 39, .38);
        filter: brightness(1.04);
      }

      .btn-create-order:active {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(201, 162, 39, .32);
      }

      /* Soft Button Enhancement */
      .btn-soft {
        border-radius: 12px;
        padding: 0.55rem 1.15rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .btn-soft:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, .10);
      }

      html.dark .btn-soft:hover {
        box-shadow: 0 6px 16px rgba(0, 0, 0, .25);
      }

      /* Empty State Enhancement */
      .empty-state-icon {
        opacity: 0.25;
        transition: all 0.3s ease;
      }

      .text-center:hover .empty-state-icon {
        opacity: 0.35;
        transform: scale(1.05);
      }

      /* Section Label Consistency */
      .section-label {
        font-size: 0.75rem;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 500;
      }
    </style>

    <main class="bg-starfield" id="main-content">
      <!-- Star layers -->
      <div class="stars-layer-1" aria-hidden="true"></div>
      <div class="stars-layer-2" aria-hidden="true"></div>
      <div class="stars-layer-3" aria-hidden="true"></div>

      <div class="container py-4" style="max-width: 1200px;">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">Essensbestellung</div>
            <h1 class="h-serif mb-1 reveal-text" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300; line-height: 1.1;">
              Speisekarte & Bestellungen
            </h1>
            <div class="text-muted" style="font-size:.95rem; line-height:1.6; max-width: 68ch;">
              Verwalte deine Essensbestellungen für den Abiball
            </div>
          </div>
          <a class="btn btn-outline-secondary btn-soft" href="/dashboard.php">Zurück</a>
        </div>

        <!-- Bestellungsschluss -->
        <div class="alert mb-3" style="background: rgba(201,162,39,.10); border: 1px solid rgba(201,162,39,.35); border-radius: 14px;">
          <div class="d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="var(--gold)" viewBox="0 0 16 16" style="flex-shrink: 0;">
              <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z" />
              <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z" />
            </svg>
            <div class="fw-semibold" style="font-size: .95rem;">Bestellungsschluss: <span style="color: var(--gold);">27.&nbsp;Februar&nbsp;2026</span></div>
          </div>
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
            <div class="card">
              <div class="card-body p-5">
                <div class="text-center text-muted">
                  <div class="mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-basket empty-state-icon" viewBox="0 0 16 16">
                      <path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h9.234L10.07 1.757a.5.5 0 1 1 .858-.514L13.783 6H15a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1v4.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 13.5V9a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h1.217L5.07 1.243a.5.5 0 0 1 .686-.172zM2 9v4.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V9H2zM1 7v1h14V7H1zm3 3a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 4 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 6 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 8 10zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5zm2 0a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5z" />
                    </svg>
                  </div>
                  <h4>Noch keine Bestellungen</h4>
                  <p class="mb-4">Du hast noch keine Essensbestellung für den Abiball aufgegeben.</p>
                  <button class="btn btn-cta btn-cta-lg" onclick="switchToTab('new')" type="button">
                    Neue Bestellung erstellen
                  </button>
                </div>
              </div>
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

              $statusText = match ($status) {
                'open' => 'Offen',
                'paid' => 'Bezahlt',
                'redeemed' => 'Eingelöst',
                'cancelled' => 'Storniert',
                default => $status
              };

              $badgeClass = match ($status) {
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
                        <strong style="color: var(--gold);">Essensbestellung; Name: <?= e($username) ?>; ID: <?= e($orderId) ?></strong>
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
                <path d="M4 9.42h1.063C5.4 12.323 7.317 14 10.34 14c.622 0 1.167-.068 1.659-.185v-1.3c-.484.119-1.045.17-1.659.17-2.1 0-3.455-1.198-3.775-3.264h4.017v-.928H6.497v-.936q0-.263.02-.496h3.926v-.928H6.64C6.978 3.937 8.318 2.8 10.34 2.8c.614 0 1.175.05 1.659.177V1.68A6.4 6.4 0 0 0 10.34 1.5c-3.022 0-4.94 1.684-5.277 4.581H4v.928h1.019q-.016.266-.016.543v.389H4z" />
              </svg>
              <div>
                <h5 class="mb-2" style="font-weight: 600;">Zahlung per Überweisung</h5>
                <p class="mb-2" style="line-height: 1.6;">Nach der Bestellung überweise bitte den Gesamtbetrag mit folgendem Verwendungszweck:</p>
              </div>
            </div>
            <div class="p-soft" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.9rem;">
              <strong style="color: var(--gold);">Essensbestellung; Name: <?= e($mainName) ?>; ID: <?= e($mainId) ?></strong>
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

            <!-- Menu Card Container -->
            <div class="card mb-4">
              <div class="card-body p-4">
                <div class="text-muted small mb-3" style="letter-spacing:.18em;text-transform:uppercase;">Speisekarte</div>

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
                          onchange="updateTotal()">
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
                          onchange="updateTotal()">
                        <input type="hidden" name="items[<?= e($item['id']) ?>][id]" value="<?= e($item['id']) ?>">
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="total-card mt-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0" style="font-weight: 600;">Gesamtsumme:</h4>
                <h2 class="mb-0" style="color: var(--gold); font-weight: 700;" id="total-price">0,00 €</h2>
              </div>
              <button type="submit" class="btn btn-cta btn-cta-lg w-100">Bestellung aufgeben</button>
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
