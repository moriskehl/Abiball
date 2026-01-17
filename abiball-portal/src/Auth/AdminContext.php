<?php
declare(strict_types=1);

/**
 * AdminContext - Verwaltet die Admin-Session
 * 
 * Für das Admin-Panel zur Verwaltung von Teilnehmern, Zahlungen
 * und Bestellungen. Hat einen längeren Timeout (2 Stunden).
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';

final class AdminContext
{
    // Session-Keys
    private const K_IS_ADMIN      = 'is_admin';
    private const K_ADMIN_ID      = 'admin_id';
    private const K_ADMIN_NAME    = 'admin_name';
    private const K_LAST_ACTIVITY = '_admin_last_activity';

    // Session läuft nach 2 Stunden Inaktivität ab
    private const SESSION_TIMEOUT = 7200;

    /**
     * Stellt sicher dass Bootstrap initialisiert ist.
     */
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

    /**
     * Prüft ob ein Admin eingeloggt ist.
     */
    public static function isAdmin(): bool
    {
        self::init();
        
        if (!self::checkTimeout()) {
            return false;
        }
        
        return !empty($_SESSION[self::K_IS_ADMIN]) && $_SESSION[self::K_IS_ADMIN] === true;
    }

    /**
     * Prüft und aktualisiert den Session-Timeout.
     */
    private static function checkTimeout(): bool
    {
        $lastActivity = $_SESSION[self::K_LAST_ACTIVITY] ?? null;
        
        if ($lastActivity === null) {
            $_SESSION[self::K_LAST_ACTIVITY] = time();
            return true;
        }
        
        $elapsed = time() - (int)$lastActivity;
        
        if ($elapsed > self::SESSION_TIMEOUT) {
            self::logout('/admin/admin_login.php');
            return false;
        }
        
        $_SESSION[self::K_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Gibt die Admin-ID zurück.
     */
    public static function adminId(): string
    {
        self::init();
        return (string)($_SESSION[self::K_ADMIN_ID] ?? '');
    }

    /**
     * Gibt den Admin-Namen zurück.
     */
    public static function adminName(): string
    {
        self::init();
        $n = (string)($_SESSION[self::K_ADMIN_NAME] ?? '');
        return $n !== '' ? $n : 'Admin';
    }

    /**
     * Leitet zur Admin-Login-Seite weiter wenn nicht eingeloggt.
     */
    public static function requireAdmin(string $redirectTo = '/admin/admin_login.php'): void
    {
        if (!self::isAdmin()) {
            Response::redirect($redirectTo);
        }
    }

    /**
     * Meldet einen Admin an und setzt alle Session-Werte.
     */
    public static function loginAsAdmin(array $adminRow): void
    {
        self::init();
        session_regenerate_id(true);

        $_SESSION[self::K_IS_ADMIN]      = true;
        $_SESSION[self::K_ADMIN_ID]      = (string)($adminRow['id'] ?? '');
        $_SESSION[self::K_ADMIN_NAME]    = (string)($adminRow['name'] ?? 'Admin');
        $_SESSION[self::K_LAST_ACTIVITY] = time();
    }

    /**
     * Meldet den Admin ab und zerstört die Session.
     */
    public static function logout(string $redirectTo = '/admin/admin_login.php'): void
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
