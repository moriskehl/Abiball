<?php
declare(strict_types=1);

/**
 * AuthContext - Verwaltet die Gast-Session
 * 
 * Zuständig für Login-Status, Session-Daten und Timeout-Handling
 * für normale Gäste (Abiturienten und Begleitpersonen).
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class AuthContext
{
    // Session-Keys
    private const K_PARTICIPANT_ID   = 'participant_id';
    private const K_PARTICIPANT_NAME = 'participant_name';
    private const K_TICKET_COUNT     = 'ticket_count';
    private const K_LAST_ACTIVITY    = '_last_activity';

    // Legacy-Keys für Abwärtskompatibilität
    private const K_MAIN_ID    = 'main_id';
    private const K_GUEST_ID   = 'guest_id';
    private const K_GUEST_NAME = 'guest_name';

    // Session läuft nach 1 Stunde Inaktivität ab
    private const SESSION_TIMEOUT = 3600;

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
     * Prüft ob ein Gast eingeloggt ist.
     */
    public static function isLoggedIn(): bool
    {
        self::init();
        
        if (!self::checkTimeout()) {
            return false;
        }
        
        return !empty($_SESSION[self::K_PARTICIPANT_ID]) || !empty($_SESSION[self::K_MAIN_ID]);
    }

    /**
     * Prüft und aktualisiert den Session-Timeout.
     * Loggt automatisch aus wenn die Session abgelaufen ist.
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
            self::logout('/login.php');
            return false;
        }
        
        $_SESSION[self::K_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Gibt die Haupt-ID des eingeloggten Gastes zurück.
     * Diese ID identifiziert die gesamte Gruppe (Abiturient + Begleitpersonen).
     */
    public static function mainId(): string
    {
        self::init();
        $mid = (string)($_SESSION[self::K_PARTICIPANT_ID] ?? '');
        if ($mid !== '') return $mid;

        return (string)($_SESSION[self::K_MAIN_ID] ?? '');
    }

    /**
     * Gibt den Namen des eingeloggten Gastes zurück.
     */
    public static function name(): string
    {
        self::init();
        $n = (string)($_SESSION[self::K_PARTICIPANT_NAME] ?? '');
        if ($n !== '') return $n;

        return (string)($_SESSION[self::K_GUEST_NAME] ?? '');
    }

    /**
     * Gibt die Anzahl der Tickets für diese Gruppe zurück.
     */
    public static function ticketCount(): int
    {
        self::init();
        $v = $_SESSION[self::K_TICKET_COUNT] ?? null;
        if (is_numeric($v)) return (int)$v;

        // Fallback: aus CSV laden falls nicht in Session
        $mid = self::mainId();
        if ($mid === '') return 0;

        return ParticipantsRepository::ticketCountForMainId($mid);
    }

    /**
     * Extrahiert Initialen aus dem Namen (z.B. "Max Mustermann" -> "MM").
     */
    public static function userInitials(): string
    {
        $name = trim(self::name());
        if ($name === '') return '';

        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = trim((string)($parts[0] ?? ''));
        $last  = trim((string)($parts[count($parts) - 1] ?? ''));

        if ($first === '') return '';

        $i1 = mb_substr($first, 0, 1, 'UTF-8');
        $i2 = ($last !== '' && $last !== $first) ? mb_substr($last, 0, 1, 'UTF-8') : '';

        $ini = mb_strtoupper($i1 . $i2, 'UTF-8');
        $ini = preg_replace('/[^A-ZÄÖÜ]/u', '', $ini) ?? $ini;
        
        return $ini;
    }

    /**
     * Leitet zur Login-Seite weiter wenn nicht eingeloggt.
     */
    public static function requireLogin(string $redirectTo = '/login.php'): void
    {
        if (!self::isLoggedIn()) {
            Response::redirect($redirectTo);
        }
    }

    /**
     * Meldet einen Hauptgast an und setzt alle Session-Werte.
     */
    public static function loginAsMain(array $mainUserRow): void
    {
        self::init();
        session_regenerate_id(true);

        $mainId = ParticipantsRepository::resolveMainIdFromRow($mainUserRow);
        $ticketCount = ParticipantsRepository::ticketCountForMainId($mainId);

        $_SESSION[self::K_PARTICIPANT_ID]   = $mainId;
        $_SESSION[self::K_PARTICIPANT_NAME] = (string)($mainUserRow['name'] ?? '');
        $_SESSION[self::K_TICKET_COUNT]     = $ticketCount;
        $_SESSION[self::K_LAST_ACTIVITY]    = time();

        // Legacy-Keys für ältere Code-Teile
        $_SESSION[self::K_MAIN_ID]    = $mainId;
        $_SESSION[self::K_GUEST_ID]   = (string)($mainUserRow['id'] ?? '');
        $_SESSION[self::K_GUEST_NAME] = (string)($mainUserRow['name'] ?? '');
    }

    /**
     * Meldet den Gast ab und zerstört die Session komplett.
     */
    public static function logout(string $redirectTo = '/login.php'): void
    {
        self::init();

        $_SESSION = [];

        // Session-Cookie löschen
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
