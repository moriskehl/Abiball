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
        return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
