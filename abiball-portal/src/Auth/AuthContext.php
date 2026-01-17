<?php
declare(strict_types=1);

// src/Auth/AuthContext.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class AuthContext
{
    // Aktuelle Session-Keys (wie in AuthController bereits gesetzt)
    private const K_PARTICIPANT_ID   = 'participant_id';     // = mainId
    private const K_PARTICIPANT_NAME = 'participant_name';
    private const K_TICKET_COUNT     = 'ticket_count';
    private const K_LAST_ACTIVITY    = '_last_activity';     // SECURITY: Session timeout tracking

    // Backwards-Compatibility Keys (werden teils noch verwendet)
    private const K_MAIN_ID  = 'main_id';
    private const K_GUEST_ID = 'guest_id';
    private const K_GUEST_NAME = 'guest_name';

    // SECURITY: Session timeout in seconds (1 hour)
    private const SESSION_TIMEOUT = 3600;

    private static function init(): void
    {
        // In deinem Projekt existiert Bootstrap::init().
        // Fallback ist trotzdem eingebaut, falls du diese Datei mal isoliert testest.
        if (class_exists('Bootstrap') && method_exists('Bootstrap', 'init')) {
            Bootstrap::init();
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::init();
        
        // SECURITY: Check for session timeout
        if (!self::checkTimeout()) {
            return false;
        }
        
        return !empty($_SESSION[self::K_PARTICIPANT_ID]) || !empty($_SESSION[self::K_MAIN_ID]);
    }

    /**
     * SECURITY: Check and update session timeout
     */
    private static function checkTimeout(): bool
    {
        $lastActivity = $_SESSION[self::K_LAST_ACTIVITY] ?? null;
        
        if ($lastActivity === null) {
            // First activity in this session
            $_SESSION[self::K_LAST_ACTIVITY] = time();
            return true;
        }
        
        $elapsed = time() - (int)$lastActivity;
        
        if ($elapsed > self::SESSION_TIMEOUT) {
            // Timeout! End session
            self::logout('/login.php');
            return false;
        }
        
        // Update activity timestamp
        $_SESSION[self::K_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Liefert die mainId / participant_id (für alle gruppenbezogenen Abfragen).
     */
    public static function mainId(): string
    {
        self::init();
        $mid = (string)($_SESSION[self::K_PARTICIPANT_ID] ?? '');
        if ($mid !== '') return $mid;

        return (string)($_SESSION[self::K_MAIN_ID] ?? '');
    }

    public static function name(): string
    {
        self::init();
        $n = (string)($_SESSION[self::K_PARTICIPANT_NAME] ?? '');
        if ($n !== '') return $n;

        return (string)($_SESSION[self::K_GUEST_NAME] ?? '');
    }

    public static function ticketCount(): int
    {
        self::init();
        $v = $_SESSION[self::K_TICKET_COUNT] ?? null;
        if (is_numeric($v)) return (int)$v;

        // Fallback: aus CSV rekonstruieren (robust)
        $mid = self::mainId();
        if ($mid === '') return 0;

        return ParticipantsRepository::ticketCountForMainId($mid);
    }

    public static function userInitials(): string
    {
        $name = trim(self::name());
        if ($name === '') return '';

        // Mehrere Leerzeichen, Bindestrich etc. robust
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = trim((string)($parts[0] ?? ''));
        $last  = trim((string)($parts[count($parts) - 1] ?? ''));

        if ($first === '') return '';

        $i1 = mb_substr($first, 0, 1, 'UTF-8');
        $i2 = ($last !== '' && $last !== $first) ? mb_substr($last, 0, 1, 'UTF-8') : '';

        $ini = mb_strtoupper($i1 . $i2, 'UTF-8');

        // nur Buchstaben behalten (inkl. Umlaute)
        $ini = preg_replace('/[^A-ZÄÖÜ]/u', '', $ini) ?? $ini;
        return $ini;
    }

    public static function requireLogin(string $redirectTo = '/login.php'): void
    {
        if (!self::isLoggedIn()) {
            Response::redirect($redirectTo);
        }
    }

    /**
     * Setzt Session konsistent (ersetzt Session-Setzlogik im Controller).
     * Erwartet Hauptgast-Row (ParticipantsRepository::findMainByIdOrName()).
     */
    public static function loginAsMain(array $mainUserRow): void
    {
        self::init();
        session_regenerate_id(true);

        $mainId = ParticipantsRepository::resolveMainIdFromRow($mainUserRow);
        $ticketCount = ParticipantsRepository::ticketCountForMainId($mainId);

        // neue Keys
        $_SESSION[self::K_PARTICIPANT_ID]   = $mainId;
        $_SESSION[self::K_PARTICIPANT_NAME] = (string)($mainUserRow['name'] ?? '');
        $_SESSION[self::K_TICKET_COUNT]     = $ticketCount;
        $_SESSION[self::K_LAST_ACTIVITY]    = time();  // SECURITY: Initialize timeout tracker

        // alte Keys
        $_SESSION[self::K_MAIN_ID]    = $mainId;
        $_SESSION[self::K_GUEST_ID]   = (string)($mainUserRow['id'] ?? '');
        $_SESSION[self::K_GUEST_NAME] = (string)($mainUserRow['name'] ?? '');
    }

    public static function logout(string $redirectTo = '/login.php'): void
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
