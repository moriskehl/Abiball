<?php
declare(strict_types=1);

/**
 * FoodOrderService - Geschäftslogik für Essensbestellungen
 * 
 * Verarbeitet das Erstellen, Stornieren und Einlösen von Bestellungen.
 */

require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../Repository/MenuRepository.php';

final class FoodOrderService
{
    /**
     * Erstellt eine neue Essensbestellung für einen Teilnehmer.
     */
    public static function createOrder(string $mainId, array $items): array
    {
        if (empty($items)) {
            return ['success' => false, 'error' => 'empty'];
        }

        $validatedItems = [];
        $totalPrice = 0.0;

        foreach ($items as $item) {
            $itemId = $item['id'] ?? '';
            $quantity = (int)($item['quantity'] ?? 1);

            if ($quantity < 1) continue;

            $menuItem = MenuRepository::getItem($itemId);
            if (!$menuItem || !MenuRepository::isAvailable($itemId)) {
                continue;
            }

            $price = (float)($menuItem['price'] ?? 0);
            $subtotal = $price * $quantity;

            $validatedItems[] = [
                'item_id' => $itemId,
                'name' => $menuItem['name'],
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];

            $totalPrice += $subtotal;
        }

        if (empty($validatedItems)) {
            return ['success' => false, 'error' => 'no_valid_items'];
        }

        try {
            $orderId = FoodOrderRepository::create($mainId, $validatedItems, $totalPrice);
            return ['success' => true, 'order_id' => $orderId];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'create_failed'];
        }
    }

    /**
     * Storniert eine offene Bestellung.
     * Nur der Besitzer kann seine eigenen, noch nicht bezahlten Bestellungen stornieren.
     */
    public static function cancelOrder(string $orderId, string $mainId): bool
    {
        $order = FoodOrderRepository::findByOrderId($orderId);

        if (!$order) {
            return false;
        }

        if ($order['main_id'] !== $mainId) {
            return false;
        }

        if ($order['status'] !== 'open') {
            return false;
        }

        return FoodOrderRepository::cancel($orderId);
    }

    /**
     * Prüft, ob eine Bestellung eingelöst werden kann.
     * Nur bezahlte Bestellungen sind einlösbar.
     */
    public static function canRedeem(string $orderId): bool
    {
        $order = FoodOrderRepository::findByOrderId($orderId);

        if (!$order) {
            return false;
        }

        if ($order['status'] !== 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Berechnet den Gesamtpreis für eine Liste von Menü-Items.
     */
    public static function calculateTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $itemId = $item['id'] ?? '';
            $quantity = (int)($item['quantity'] ?? 1);

            if ($quantity < 1) continue;

            $menuItem = MenuRepository::getItem($itemId);
            if (!$menuItem) continue;

            $price = (float)($menuItem['price'] ?? 0);
            $total += $price * $quantity;
        }

        return $total;
    }

    /**
     * Formatiert Bestellpositionen als lesbaren Text für Anzeige oder Druck.
     */
    public static function formatOrderItems(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 1;
            $name = $item['name'] ?? 'Unbekannt';
            $price = number_format($item['subtotal'] ?? 0, 2, ',', '.');
            $lines[] = "$qty x $name ($price €)";
        }
        return implode("\n", $lines);
    }
}
