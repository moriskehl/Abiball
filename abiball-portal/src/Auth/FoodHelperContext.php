<?php

declare(strict_types=1);

/**
 * FoodHelperContext - Verwaltet die Session für Essens-Helfer
 * 
 * Für das Einlösen von Essens-Bons bei der Ausgabe.
 * Hat einen mittleren Timeout (45 Minuten).
 */

require_once __DIR__ . '/../Bootstrap.php';

final class FoodHelperContext
{
    // Session-Keys
    private const K_HELPER_ID      = 'food_helper_id';
    private const K_HELPER_NAME    = 'food_helper_name';
    private const K_IS_FOOD_HELPER = 'is_food_helper';
    private const K_LOGIN_TIME     = 'food_helper_login_time';
    private const K_LAST_ACTIVITY  = 'food_helper_last_activity';

    // Session läuft nach 45 Minuten Inaktivität ab
    private const SESSION_TIMEOUT = 2700;

    /**
     * Stellt sicher dass Bootstrap initialisiert ist.
     */
    private static function init(): void
    {
        Bootstrap::init();
    }

    /**
     * Prüft ob ein Food-Helper eingeloggt ist (ohne Timeout-Check).
     */
    public static function isFoodHelper(): bool
    {
        self::init();
        return !empty($_SESSION[self::K_IS_FOOD_HELPER]) && $_SESSION[self::K_IS_FOOD_HELPER] === true;
    }

    /**
     * Prüft ob eingeloggt UND Session nicht abgelaufen.
     */
    public static function isLoggedIn(): bool
    {
        return self::isFoodHelper() && self::checkTimeout();
    }

    /**
     * Gibt die Helper-ID zurück.
     */
    public static function helperId(): string
    {
        self::init();
        return (string)($_SESSION[self::K_HELPER_ID] ?? '');
    }

    /**
     * Gibt den Helper-Namen zurück.
     */
    public static function helperName(): string
    {
        self::init();
        return (string)($_SESSION[self::K_HELPER_NAME] ?? '');
    }

    /**
     * Meldet einen Food-Helper an.
     */
    public static function loginAsFoodHelper(string $helperId, string $helperName): void
    {
        self::init();
        session_regenerate_id(true);
        $_SESSION[self::K_HELPER_ID]      = $helperId;
        $_SESSION[self::K_HELPER_NAME]    = $helperName;
        $_SESSION[self::K_IS_FOOD_HELPER] = true;
        $_SESSION[self::K_LOGIN_TIME]     = time();
        $_SESSION[self::K_LAST_ACTIVITY]  = time();
    }

    /**
     * Meldet den Food-Helper ab.
     */
    public static function logout(string $redirectTo = '/food/food_helper_login.php'): void
    {
        self::init();
        unset(
            $_SESSION[self::K_HELPER_ID],
            $_SESSION[self::K_HELPER_NAME],
            $_SESSION[self::K_IS_FOOD_HELPER],
            $_SESSION[self::K_LOGIN_TIME],
            $_SESSION[self::K_LAST_ACTIVITY]
        );
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    /**
     * Prüft den Timeout und aktualisiert den Zeitstempel.
     */
    public static function checkTimeout(): bool
    {
        self::init();

        if (empty($_SESSION[self::K_IS_FOOD_HELPER])) {
            return false;
        }

        $lastActivity = $_SESSION[self::K_LAST_ACTIVITY] ?? null;

        if ($lastActivity === null) {
            $_SESSION[self::K_LAST_ACTIVITY] = time();
            return true;
        }

        $elapsed = time() - (int)$lastActivity;

        if ($elapsed > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        $_SESSION[self::K_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Leitet zur Login-Seite weiter wenn nicht eingeloggt oder Timeout.
     */
    public static function requireFoodHelper(string $redirectTo = '/food/food_helper_login.php'): void
    {
        self::init();

        if (!self::isFoodHelper()) {
            header('Location: ' . $redirectTo, true, 302);
            exit;
        }

        if (!self::checkTimeout()) {
            header('Location: ' . $redirectTo . '?timeout=1', true, 302);
            exit;
        }
    }
}
