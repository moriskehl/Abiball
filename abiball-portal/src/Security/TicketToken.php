<?php
declare(strict_types=1);

/**
 * TicketToken - Signiert und verifiziert Ticket-QR-Codes
 * 
 * Verwendet HMAC-SHA256 mit einem geheimen Schlüssel um sicherzustellen,
 * dass QR-Codes nicht gefälscht werden können.
 */

require_once __DIR__ . '/../Config.php';

final class TicketToken
{
    /**
     * Erstellt eine Signatur für eine Teilnehmer-ID.
     */
    public static function sign(string $pid): string
    {
        $pid = trim($pid);
        return hash_hmac('sha256', $pid, Config::ticketQrSecret());
    }

    /**
     * Prüft ob eine Signatur zu einer Teilnehmer-ID passt.
     */
    public static function verify(string $pid, ?string $sig): bool
    {
        if (!is_string($sig) || $sig === '') {
            return false;
        }

        $expected = self::sign($pid);
        return hash_equals($expected, $sig);
    }
}
