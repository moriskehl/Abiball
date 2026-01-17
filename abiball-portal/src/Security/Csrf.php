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

    /**
     * Validiert CSRF-Token aus verschiedenen Quellen (POST, Header, JSON).
     * Nützlich für API-Endpunkte die sowohl Formulare als auch JSON akzeptieren.
     */
    public static function validateRequest(): bool
    {
        self::init();

        // 1. Standard POST-Parameter
        if (isset($_POST['_csrf'])) {
            return self::validate($_POST['_csrf']);
        }

        // 2. Custom Header (für AJAX/JSON-Requests)
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken !== null) {
            return self::validate($headerToken);
        }

        // 3. JSON-Body mit _csrf Feld
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['_csrf'])) {
                return self::validate($input['_csrf']);
            }
        }

        return false;
    }

    public static function inputField(): string
    {
        $t = self::token();

        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Gibt ein Meta-Tag aus für JavaScript-Zugriff auf das Token.
     */
    public static function metaTag(): string
    {
        $t = self::token();

        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
        );
    }
}
