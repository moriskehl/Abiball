<?php
declare(strict_types=1);

// src/Security/RateLimiter.php
require_once __DIR__ . '/../Bootstrap.php';

final class RateLimiter
{
    /**
     * Sehr simpel: erlaubt $maxVersuche pro $windowSeconds pro IP.
     */
    public static function allow(string $key, int $maxVersuche = 8, int $windowSeconds = 60): bool
    {
        Bootstrap::init();

        $now = time();
        $storeKey = '_rl_' . $key;

        $data = $_SESSION[$storeKey] ?? ['start' => $now, 'count' => 0];

        if (!is_array($data) || !isset($data['start'], $data['count'])) {
            $data = ['start' => $now, 'count' => 0];
        }

        if (($now - (int)$data['start']) > $windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        $data['count'] = (int)$data['count'] + 1;
        $_SESSION[$storeKey] = $data;

        return $data['count'] <= $maxVersuche;
    }
}
