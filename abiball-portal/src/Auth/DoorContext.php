<?php
declare(strict_types=1);

// src/Auth/DoorContext.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';

final class DoorContext
{
    private const K_IS_DOOR = 'is_door';
    private const K_DOOR_ID = 'door_id';
    private const K_DOOR_NAME = 'door_name';
    private const K_DOOR_LOGIN_TIME = 'door_login_time';
    private const K_LAST_ACTIVITY = '_door_last_activity';  // SECURITY: Session timeout tracking

    // SECURITY: Session timeout in seconds (30 minutes for door personnel)
    private const SESSION_TIMEOUT = 1800;

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

    public static function isDoor(): bool
    {
        self::init();
        return !empty($_SESSION[self::K_IS_DOOR]) && $_SESSION[self::K_IS_DOOR] === true;
    }

    public static function doorId(): string
    {
        self::init();
        return (string)($_SESSION[self::K_DOOR_ID] ?? '');
    }

    public static function doorName(): string
    {
        self::init();
        $n = (string)($_SESSION[self::K_DOOR_NAME] ?? '');
        return $n !== '' ? $n : 'Door Personnel';
    }

    public static function loginTime(): int
    {
        self::init();
        $t = $_SESSION[self::K_DOOR_LOGIN_TIME] ?? null;
        return is_numeric($t) ? (int)$t : 0;
    }

    /**
     * Setzt die Door-Session (30 Minuten Timeout)
     */
    public static function loginAsDoor(string $doorId, string $doorName): void
    {
        self::init();
        session_regenerate_id(true);

        $_SESSION[self::K_IS_DOOR] = true;
        $_SESSION[self::K_DOOR_ID] = $doorId;
        $_SESSION[self::K_DOOR_NAME] = $doorName;
        $_SESSION[self::K_DOOR_LOGIN_TIME] = time();
        $_SESSION[self::K_LAST_ACTIVITY] = time();  // SECURITY: Initialize timeout tracker

        // Session-Timeout: 30 Minuten
        ini_set('session.gc_maxlifetime', '1800');
    }

    public static function logout(): void
    {
        self::init();
        $_SESSION[self::K_IS_DOOR] = false;
        $_SESSION[self::K_DOOR_ID] = '';
        $_SESSION[self::K_DOOR_NAME] = '';
        $_SESSION[self::K_DOOR_LOGIN_TIME] = 0;
        session_destroy();
    }

    /**
     * Prüft Inaktivität und zerstört Session falls nötig (30 Min Timeout)
     */
    public static function checkTimeout(): bool
    {
        if (!self::isDoor()) {
            return false;
        }

        $lastActivity = $_SESSION[self::K_LAST_ACTIVITY] ?? null;
        
        if ($lastActivity === null) {
            // First activity in this session
            $_SESSION[self::K_LAST_ACTIVITY] = time();
            return true;
        }
        
        $elapsed = time() - (int)$lastActivity;
        
        if ($elapsed > self::SESSION_TIMEOUT) {
            // Timeout! End session
            self::logout();
            return false;
        }
        
        // Update activity timestamp
        $_SESSION[self::K_LAST_ACTIVITY] = time();
        return true;
    }

    public static function requireDoor(string $redirectTo = '/door/door_login.php'): void
    {
        if (!self::isDoor()) {
            Response::redirect($redirectTo);
        }

        if (!self::checkTimeout()) {
            Response::redirect($redirectTo);
        }
    }
}
