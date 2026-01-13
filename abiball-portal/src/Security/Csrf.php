<?php
declare(strict_types=1);

// src/Security/Csrf.php

require_once __DIR__ . '/../Bootstrap.php';

final class Csrf
{
    private const KEY = '_csrf_token';

    private static function init(): void
    {
        Bootstrap::init();
    }

    public static function token(): string
    {
        self::init();

        if (
            !isset($_SESSION[self::KEY]) ||
            !is_string($_SESSION[self::KEY]) ||
            strlen($_SESSION[self::KEY]) < 32
        ) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        self::init();

        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION[self::KEY] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function inputField(): string
    {
        $t = self::token();

        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
        );
    }
}
