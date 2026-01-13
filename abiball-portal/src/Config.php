<?php
declare(strict_types=1);

// src/Config.php
final class Config
{
    /* ================= Paths ================= */

    public static function participantsCsvPath(): string
    {
        return __DIR__ . '/../storage/data/participants.csv';
    }

    public static function pricingOverridesCsvPath(): string
    {
        return __DIR__ . '/../storage/data/pricing_overrides.csv';
    }

    public static function seatingJsonPath(string $mainId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $mainId) ?: 'unknown';
        return __DIR__ . '/../storage/seating/' . $safe . '.json';
    }

    /* ================= Environment ================= */

    public static function isDev(): bool
    {
        $env = strtolower((string)(getenv('APP_ENV') ?: ''));
        if ($env === 'dev')  return true;
        if ($env === 'prod') return false;

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        return ($host === '' || $host === 'localhost' || str_starts_with($host, '127.'));
    }

    /* ================= HTTPS ================= */

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        if (
            isset($_SERVER['HTTP_CF_VISITOR']) &&
            str_contains((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"')
        ) {
            return true;
        }

        return false;
    }

    public static function baseUrl(): string
    {
        $env = trim((string)(getenv('APP_BASE_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }

        $scheme = self::isHttps() ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    /* ================= Secrets ================= */

    public static function ticketQrSecret(): string
    {
        // 1) ENV (optional)
        $env = (string)(getenv('TICKET_QR_SECRET') ?: '');
        if ($env !== '') {
            return $env;
        }

        // 2) Secret-Datei im storage
        $path = __DIR__ . '/../storage/secrets/ticket_qr_secret.php';
        if (is_file($path)) {
            $secret = require $path;
            if (is_string($secret) && $secret !== '') {
                return $secret;
            }
        }

        // 3) Dev-Fallback
        if (self::isDev()) {
            return 'dev-only-secret-change-me';
        }

        throw new RuntimeException('Missing ticket QR secret');
    }
}
