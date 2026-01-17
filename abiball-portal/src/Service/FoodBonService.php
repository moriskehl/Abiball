<?php
declare(strict_types=1);

// src/Service/FoodBonService.php
require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../Security/FoodBonToken.php';

final class FoodBonService
{
    public static function generateToken(string $orderId): string
    {
        return FoodBonToken::generate($orderId);
    }

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

    public static function canGeneratePdf(string $orderId, string $mainId): bool
    {
        $order = FoodOrderRepository::findByOrderId($orderId);

        if (!$order) {
            return false;
        }

        // Nur eigene Bestellungen
        if ($order['main_id'] !== $mainId) {
            return false;
        }

        // Nur bezahlte oder eingelöste
        return in_array($order['status'], ['paid', 'redeemed']);
    }
}
