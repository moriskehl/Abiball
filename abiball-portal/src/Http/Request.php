<?php
declare(strict_types=1);

// src/Http/Request.php
final class Request
{
    public static function postString(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public static function getString(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function ip(): string
    {
        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // X-Forwarded-For (Standard Proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($list[0]);
        }

        return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
