<?php

declare(strict_types=1);

/**
 * Config - Zentrale Konfiguration der Anwendung
 * 
 * Enthält alle Pfade zu Datendateien, Umgebungserkennung und Secret-Management.
 * Keine Instanziierung nötig - alle Methoden sind statisch.
 */
final class Config
{
    // =========================================================================
    // Event-Konfiguration
    // =========================================================================

    /** Das Datum des Abiballs (YYYY-MM-DD Format) */
    public const EVENT_DATE = '2026-07-10';

    /** Ob das Voting aktuell freigeschaltet ist */
    public const VOTING_ENABLED = true;

    /**
     * Prüft ob das Voting aktuell geöffnet ist.
     */
    public static function isVotingOpen(): bool
    {
        return self::VOTING_ENABLED && self::isVotingChangeAllowed();
    }

    /**
     * Prüft ob Voting-Änderungen noch erlaubt sind (bis 18:00 Uhr am Tag des Abiballs).
     */
    public static function isVotingChangeAllowed(): bool
    {
        $eventDate = new DateTime(self::EVENT_DATE);
        $deadline = $eventDate->setTime(18, 0, 0);
        $now = new DateTime();

        return $now < $deadline;
    }

    /**
     * Prüft ob Voting-Ergebnisse sichtbar sein dürfen (erst nach der Deadline).
     */
    public static function areResultsVisible(): bool
    {
        return !self::isVotingChangeAllowed();
    }

    // =========================================================================
    // Dateipfade
    // =========================================================================

    /**
     * Pfad zur Teilnehmer-CSV mit allen Gästen und Login-Codes.
     */
    public static function participantsCsvPath(): string
    {
        return __DIR__ . '/../storage/data/participants.csv';
    }

    /**
     * Pfad zur CSV mit manuellen Preis-Überschreibungen.
     */
    public static function pricingOverridesCsvPath(): string
    {
        return __DIR__ . '/../storage/data/pricing_overrides.csv';
    }

    /**
     * Pfad zur Sitzgruppen-JSON eines Teilnehmers.
     */
    public static function seatingJsonPath(string $mainId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $mainId) ?: 'unknown';
        return __DIR__ . '/../storage/seating/' . $safe . '.json';
    }

    // =========================================================================
    // Umgebungserkennung
    // =========================================================================

    /**
     * Prüft ob die Anwendung im Entwicklungsmodus läuft.
     * Erkennt localhost automatisch als Dev-Umgebung.
     */
    public static function isDev(): bool
    {
        $env = strtolower((string)(getenv('APP_ENV') ?: ''));
        if ($env === 'dev')  return true;
        if ($env === 'prod') return false;

        // Ohne explizite Umgebung: localhost = Dev
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        return ($host === '' || $host === 'localhost' || str_starts_with($host, '127.'));
    }

    // =========================================================================
    // HTTPS-Erkennung
    // =========================================================================

    /**
     * Erkennt ob die Verbindung über HTTPS läuft.
     * Berücksichtigt auch Reverse-Proxies und Cloudflare.
     */
    public static function isHttps(): bool
    {
        // Direktes HTTPS
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // Port 443
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        // Hinter Reverse-Proxy (nginx, Apache)
        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        // Hinter Cloudflare
        if (
            isset($_SERVER['HTTP_CF_VISITOR']) &&
            str_contains((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Gibt die Basis-URL der Anwendung zurück (ohne trailing slash).
     */
    public static function baseUrl(): string
    {
        $env = trim((string)(getenv('APP_BASE_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }

        $scheme = self::isHttps() ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    // =========================================================================
    // Secrets
    // =========================================================================

    /**
     * Lädt das Secret für QR-Code-Signaturen.
     * Sucht in: 1) ENV-Variable, 2) Secret-Datei, 3) Dev-Fallback.
     * 
     * @throws RuntimeException wenn kein Secret gefunden wird (nur in Produktion)
     */
    public static function ticketQrSecret(): string
    {
        // Aus Umgebungsvariable
        $env = (string)(getenv('TICKET_QR_SECRET') ?: '');
        if ($env !== '') {
            return $env;
        }

        // Aus Secret-Datei
        $path = __DIR__ . '/../storage/secrets/ticket_qr_secret.php';
        if (is_file($path)) {
            $secret = require $path;
            if (is_string($secret) && $secret !== '') {
                return $secret;
            }
        }

        // Nur in Dev-Umgebung: Fallback-Secret
        if (self::isDev()) {
            return 'dev-only-secret-change-me';
        }

        throw new RuntimeException('Missing ticket QR secret');
    }
}
