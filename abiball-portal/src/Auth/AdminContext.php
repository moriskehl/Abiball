<?php
declare(strict_types=1);

// src/Auth/AdminContext.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';

final class AdminContext
{
    private const K_IS_ADMIN   = 'is_admin';
    private const K_ADMIN_ID   = 'admin_id';
    private const K_ADMIN_NAME = 'admin_name';

    private static function init(): void
    {
        if (class_exists('Bootstrap') && method_exists('Bootstrap', 'init')) {
            Bootstrap::init();
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function isAdmin(): bool
    {
        self::init();
        return !empty($_SESSION[self::K_IS_ADMIN]) && $_SESSION[self::K_IS_ADMIN] === true;
    }

    public static function adminId(): string
    {
        self::init();
        return (string)($_SESSION[self::K_ADMIN_ID] ?? '');
    }

    public static function adminName(): string
    {
        self::init();
        $n = (string)($_SESSION[self::K_ADMIN_NAME] ?? '');
        return $n !== '' ? $n : 'Admin';
    }

    public static function requireAdmin(string $redirectTo = '/admin_login.php'): void
    {
        if (!self::isAdmin()) {
            Response::redirect($redirectTo);
        }
    }

    public static function loginAsAdmin(array $adminRow): void
    {
        self::init();
        session_regenerate_id(true);

        $_SESSION[self::K_IS_ADMIN]   = true;
        $_SESSION[self::K_ADMIN_ID]   = (string)($adminRow['id'] ?? '');
        $_SESSION[self::K_ADMIN_NAME] = (string)($adminRow['name'] ?? 'Admin');
    }

    public static function logout(string $redirectTo = '/admin_login.php'): void
    {
        self::init();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                (bool)$p['secure'],
                (bool)$p['httponly']
            );
        }

        session_destroy();
        Response::redirect($redirectTo);
    }
}
