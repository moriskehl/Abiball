<?php
declare(strict_types=1);

// src/Security/Csrf.php
require_once __DIR__ . '/../Bootstrap.php';

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Bootstrap::init();

        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        Bootstrap::init();

        if (!is_string($token) || $token === '') return false;
        $sessionToken = $_SESSION[self::KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') return false;

        return hash_equals($sessionToken, $token);
    }

    public static function inputField(): string
    {
        $t = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}
