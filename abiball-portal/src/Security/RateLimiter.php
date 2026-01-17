<?php
declare(strict_types=1);

// src/Security/RateLimiter.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Config.php';

final class RateLimiter
{
    private static function init(): void
    {
        Bootstrap::init();
    }

    /**
     * Holt die Client-IP (auch hinter Proxy).
     */
    private static function getClientIp(): string
    {
        // Nur vertrauenswürdige Proxies berücksichtigen
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Falls hinter Reverse Proxy (nur im Production-Modus)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        }
        
        // IP validieren
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = '0.0.0.0';
        }
        
        return $ip;
    }

    /**
     * Pfad zur Rate-Limit-Datei für eine IP.
     */
    private static function getRateLimitPath(string $key, string $ip): string
    {
        $dir = __DIR__ . '/../../storage/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        // Hash aus Key + IP für Dateiname
        $hash = hash('sha256', $key . ':' . $ip);
        return $dir . '/' . $hash . '.json';
    }

    /**
     * Liest Rate-Limit-Daten aus Datei.
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
     * Schreibt Rate-Limit-Daten in Datei.
     */
    private static function writeData(string $path, array $data): void
    {
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }

    /**
     * Bereinigt alte Rate-Limit-Dateien (älter als 1 Stunde).
     * Wird nur gelegentlich aufgerufen.
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
        
        $expiry = time() - 3600; // 1 Stunde
        foreach ($files as $file) {
            if (filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }

    /**
     * Erlaubt max. $maxAttempts innerhalb von $windowSeconds pro Key + IP.
     * IP-basiert, kann nicht durch Session-Löschung umgangen werden.
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

        // Zeitfenster abgelaufen → Reset
        if (($now - $data['start']) > $windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        // Prüfen, bevor hochgezählt wird
        if ($data['count'] >= $maxAttempts) {
            self::writeData($path, $data);
            return false;
        }

        $data['count']++;
        self::writeData($path, $data);

        return true;
    }

    /**
     * Gibt zurück, wie viele Sekunden bis zum Reset verbleiben.
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
