<?php

declare(strict_types=1);

/**
 * RateLimiter - Schutz vor Brute-Force-Angriffen
 * 
 * Begrenzt die Anzahl der Versuche pro IP-Adresse und Zeitfenster.
 * IP-basiert (nicht Session-basiert), kann also nicht durch
 * Löschen von Cookies umgangen werden.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Config.php';

final class RateLimiter
{
    private static function init(): void
    {
        Bootstrap::init();
    }

    /**
     * Ermittelt die Client-IP-Adresse.
     * Nginx setzt REMOTE_ADDR korrekt über die Proxy-Konfiguration.
     * Client-Header wie X-Forwarded-For werden NICHT vertraut,
     * da sie vom Client gefälscht werden können.
     */
    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // IP-Format validieren
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Generiert den Dateipfad für die Rate-Limit-Daten.
     */
    private static function getRateLimitPath(string $key, string $ip): string
    {
        $dir = __DIR__ . '/../../storage/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $hash = hash('sha256', $key . ':' . $ip);
        return $dir . '/' . $hash . '.json';
    }

    /**
     * Liest die gespeicherten Rate-Limit-Daten.
     */
    private static function readData(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['start'], $data['count'])) {
            return null;
        }

        return $data;
    }

    /**
     * Speichert die Rate-Limit-Daten.
     */
    private static function writeData(string $path, array $data): void
    {
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }

    /**
     * Räumt alte Rate-Limit-Dateien auf (älter als 1 Stunde).
     * Wird nur gelegentlich ausgeführt um Performance zu schonen.
     */
    private static function cleanup(): void
    {
        $dir = __DIR__ . '/../../storage/rate_limits';
        if (!is_dir($dir)) {
            return;
        }

        // Nur mit 1% Wahrscheinlichkeit ausführen
        if (mt_rand(1, 100) > 1) {
            return;
        }

        $files = glob($dir . '/*.json');
        if ($files === false) {
            return;
        }

        $expiry = time() - 3600;
        foreach ($files as $file) {
            if (filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }

    /**
     * Prüft ob eine Aktion erlaubt ist.
     * 
     * @param string $key     Eindeutiger Schlüssel für die Aktion (z.B. "login")
     * @param int $maxAttempts Maximale Versuche im Zeitfenster
     * @param int $windowSeconds Zeitfenster in Sekunden
     * @return bool true wenn erlaubt, false wenn Limit erreicht
     */
    public static function allow(
        string $key,
        int $maxAttempts = 8,
        int $windowSeconds = 60
    ): bool {
        self::init();
        self::cleanup();

        $ip = self::getClientIp();
        $path = self::getRateLimitPath($key, $ip);
        $now = time();

        $data = self::readData($path);

        if ($data === null) {
            $data = ['start' => $now, 'count' => 0];
        }

        // Zeitfenster abgelaufen - zurücksetzen
        if (($now - $data['start']) > $windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        // Limit erreicht?
        if ($data['count'] >= $maxAttempts) {
            self::writeData($path, $data);
            return false;
        }

        $data['count']++;
        self::writeData($path, $data);

        return true;
    }

    /**
     * Gibt zurück wie viele Sekunden bis zum Reset verbleiben.
     */
    public static function getRetryAfter(string $key, int $windowSeconds = 60): int
    {
        self::init();

        $ip = self::getClientIp();
        $path = self::getRateLimitPath($key, $ip);

        $data = self::readData($path);
        if ($data === null) {
            return 0;
        }

        $elapsed = time() - $data['start'];
        $remaining = $windowSeconds - $elapsed;

        return max(0, $remaining);
    }
}
