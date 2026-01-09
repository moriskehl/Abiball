<?php
declare(strict_types=1);

// src/Config.php
final class Config
{
    /* =========================================================
       Pfade
       ========================================================= */

    public static function participantsCsvPath(): string
    {
        return __DIR__ . '/../storage/data/participants.csv';
    }

    /* =========================================================
       Environment
       ========================================================= */

    public static function isDev(): bool
    {
        /**
         * EMPFEHLUNG:
         * - Lokal: true
         * - Server: false (oder per ENV steuern)
         *
         * Beispiel später:
         * return ($_ENV['APP_ENV'] ?? 'prod') === 'dev';
         */
        return true;
    }

    /* =========================================================
       HTTPS-Erkennung (proxy-sicher!)
       ========================================================= */

    public static function isHttps(): bool
    {
        // 1) Direktes HTTPS
        if (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== '' &&
            strtolower((string)$_SERVER['HTTPS']) !== 'off'
        ) {
            return true;
        }

        // 2) Standard HTTPS-Port
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        // 3) Reverse Proxy / Load Balancer (NGINX, DigitalOcean, etc.)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            if (strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
                return true;
            }
        }

        // 4) Cloudflare (falls aktiv)
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            if (strpos((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) {
                return true;
            }
        }

        return false;
    }
}
