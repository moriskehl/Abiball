<?php
declare(strict_types=1);

/**
 * FoodBonService - Verwaltung von Essensgutscheinen
 * 
 * Generiert und validiert Token für Essensbestellungen,
 * die als QR-Codes auf Gutscheinen gedruckt werden.
 */

require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../Security/FoodBonToken.php';

final class FoodBonService
{
    /**
     * Erzeugt einen signierten Token für eine Bestellung.
     */
    public static function generateToken(string $orderId): string
    {
        return FoodBonToken::generate($orderId);
    }

    /**
     * Validiert einen Bon-Token und lädt die zugehörige Bestellung.
     */
    public static function validateToken(string $token): ?array
    {
        $tokenData = FoodBonToken::validate($token);
        if (!$tokenData) {
            return null;
        }

        $orderId = $tokenData['order_id'];
        $order = FoodOrderRepository::findByOrderId($orderId);

        if (!$order) {
            return null;
        }

        return [
            'order' => $order,
            'valid' => in_array($order['status'], ['paid', 'redeemed'])
        ];
    }

    /**
     * Prüft, ob ein PDF-Bon erstellt werden darf.
     * Nur bezahlte oder bereits eingelöste eigene Bestellungen sind erlaubt.
     */
    public static function canGeneratePdf(string $orderId, string $mainId): bool
    {
        $order = FoodOrderRepository::findByOrderId($orderId);

        if (!$order) {
            return false;
        }

        if ($order['main_id'] !== $mainId) {
            return false;
        }

        return in_array($order['status'], ['paid', 'redeemed']);
    }
}
