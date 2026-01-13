<?php
declare(strict_types=1);

// src/Security/TicketToken.php
require_once __DIR__ . '/../Config.php';

final class TicketToken
{
    public static function sign(string $pid): string
    {
        $pid = trim($pid);
        return hash_hmac('sha256', $pid, Config::ticketQrSecret());
    }

    public static function verify(string $pid, ?string $sig): bool
    {
        if (!is_string($sig) || $sig === '') {
            return false;
        }

        $expected = self::sign($pid);
        return hash_equals($expected, $sig);
    }
}
