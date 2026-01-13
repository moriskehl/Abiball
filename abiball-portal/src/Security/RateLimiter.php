<?php
declare(strict_types=1);

// src/Security/RateLimiter.php

require_once __DIR__ . '/../Bootstrap.php';

final class RateLimiter
{
    private static function init(): void
    {
        Bootstrap::init();
    }

    /**
     * Erlaubt max. $maxAttempts innerhalb von $windowSeconds pro Key.
     */
    public static function allow(
        string $key,
        int $maxAttempts = 8,
        int $windowSeconds = 60
    ): bool {
        self::init();

        $now      = time();
        $storeKey = '_rl_' . $key;

        $data = $_SESSION[$storeKey] ?? null;

        if (
            !is_array($data) ||
            !isset($data['start'], $data['count']) ||
            !is_int($data['start']) ||
            !is_int($data['count'])
        ) {
            $data = ['start' => $now, 'count' => 0];
        }

        // Zeitfenster abgelaufen → Reset
        if (($now - $data['start']) > $windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        // Prüfen, bevor hochgezählt wird
        if ($data['count'] >= $maxAttempts) {
            $_SESSION[$storeKey] = $data;
            return false;
        }

        $data['count']++;
        $_SESSION[$storeKey] = $data;

        return true;
    }
}
