<?php
declare(strict_types=1);

// src/Security/FoodBonToken.php
require_once __DIR__ . '/../Config.php';

final class FoodBonToken
{
    private static function getSecret(): string
    {
        // Nutze das gleiche Secret wie für Tickets
        return Config::ticketQrSecret() . '_foodbon';
    }

    public static function generate(string $orderId): string
    {
        $orderId = trim($orderId);
        $signature = hash_hmac('sha256', $orderId, self::getSecret());
        return $orderId . '.' . $signature;
    }

    public static function validate(string $token): ?array
    {
        try {
            $parts = explode('.', $token, 2);
            if (count($parts) !== 2) {
                return null;
            }

            [$orderId, $signature] = $parts;
            $orderId = trim($orderId);

            // Signatur prüfen
            $expectedSignature = hash_hmac('sha256', $orderId, self::getSecret());

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            return [
                'order_id' => $orderId
            ];

        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Einfache Signatur-Verifizierung (wie TicketToken::verify)
     */
    public static function verify(string $orderId, ?string $sig): bool
    {
        if (!is_string($sig) || $sig === '') {
            return false;
        }

        $expected = hash_hmac('sha256', trim($orderId), self::getSecret());
        return hash_equals($expected, $sig);
    }

    /**
     * Signatur für eine Order-ID generieren
     */
    public static function sign(string $orderId): string
    {
        return hash_hmac('sha256', trim($orderId), self::getSecret());
    }
}
