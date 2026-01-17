<?php
declare(strict_types=1);

/**
 * FoodBonToken - Signiert und verifiziert Essens-Bon-QR-Codes
 * 
 * Ähnlich wie TicketToken, aber für Essensbestellungen.
 * Nutzt ein leicht abgewandeltes Secret für zusätzliche Sicherheit.
 */

require_once __DIR__ . '/../Config.php';

final class FoodBonToken
{
    /**
     * Generiert das Secret für Essens-Bons.
     */
    private static function getSecret(): string
    {
        return Config::ticketQrSecret() . '_foodbon';
    }

    /**
     * Erstellt einen signierten Token aus einer Bestell-ID.
     * Format: "orderId.signatur"
     */
    public static function generate(string $orderId): string
    {
        $orderId = trim($orderId);
        $signature = hash_hmac('sha256', $orderId, self::getSecret());
        return $orderId . '.' . $signature;
    }

    /**
     * Validiert einen Token und extrahiert die Bestell-ID.
     * 
     * @return array|null Array mit 'order_id' oder null wenn ungültig
     */
    public static function validate(string $token): ?array
    {
        try {
            $parts = explode('.', $token, 2);
            if (count($parts) !== 2) {
                return null;
            }

            [$orderId, $signature] = $parts;
            $orderId = trim($orderId);

            $expectedSignature = hash_hmac('sha256', $orderId, self::getSecret());

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            return ['order_id' => $orderId];

        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Prüft ob eine Signatur zu einer Bestell-ID passt.
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
     * Erstellt eine Signatur für eine Bestell-ID.
     */
    public static function sign(string $orderId): string
    {
        return hash_hmac('sha256', trim($orderId), self::getSecret());
    }
}
