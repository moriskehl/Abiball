<?php
// src/Repository/CsvRepository.php

declare(strict_types=1);

final class CsvRepository
{
    /**
     * Liest eine ;-getrennte CSV und gibt ein Array aus assoziativen Zeilen zurück.
     * Leere Header-Spalten werden ignoriert.
     */
    public static function readAssoc(string $filePath, string $delimiter = ';'): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        // Header lesen (mit explizitem enclosure + escape → keine Deprecation)
        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(
            static fn($v) => trim((string)$v),
            $header
        );

        // UTF-8 BOM entfernen (Excel-CSV)
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // komplett leere Zeilen überspringen
            if (count(array_filter($line, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                if ($col === '') {
                    continue;
                }
                $row[$col] = isset($line[$i]) ? trim((string)$line[$i]) : '';
            }

            // ungültige IDs überspringen
            if (($row['id'] ?? '') === '' || ($row['id'] ?? '') === '#WERT!') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Wie readAssoc(), aber gibt zusätzlich den Header (in Reihenfolge) zurück.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public static function readAssocWithHeader(string $filePath, string $delimiter = ';'): array
    {
        if (!is_readable($filePath)) {
            return [[], []];
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($header === false) {
            fclose($handle);
            return [[], []];
        }

        $header = array_map(
            static fn($v) => trim((string)$v),
            $header
        );

        // UTF-8 BOM entfernen (Excel-CSV)
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count(array_filter($line, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                if ($col === '') {
                    continue;
                }
                $row[$col] = isset($line[$i]) ? trim((string)$line[$i]) : '';
            }

            if (($row['id'] ?? '') === '' || ($row['id'] ?? '') === '#WERT!') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return [$header, $rows];
    }

    public static function parseBool(string $value): bool
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['TRUE', 'WAHR', '1'], true);
    }

    /**
     * Atomar schreiben: temp-Datei im gleichen Ordner → rename().
     *
     * @param array<int,string> $header
     * @param array<int,array<string,scalar|null>> $rows
     */
    public static function writeAssocAtomic(string $filePath, array $header, array $rows, string $delimiter = ';'): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            throw new RuntimeException('CSV directory missing: ' . $dir);
        }

        $tmp = tempnam($dir, 'csv_');
        if ($tmp === false) {
            throw new RuntimeException('Cannot create temp file in: ' . $dir);
        }

        $h = fopen($tmp, 'wb');
        if ($h === false) {
            @unlink($tmp);
            throw new RuntimeException('Cannot write temp file: ' . $tmp);
        }

        // Header (Reihenfolge bleibt stabil)
        fputcsv($h, $header, $delimiter, '"', '\\');

        // Rows in Header-Reihenfolge schreiben
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $col) {
                $line[] = array_key_exists($col, $r) ? (string)($r[$col] ?? '') : '';
            }
            fputcsv($h, $line, $delimiter, '"', '\\');
        }

        fclose($h);

        // Bestehende Datei löschen (robust, auch unter Windows)
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if (!rename($tmp, $filePath)) {
            @unlink($tmp);
            throw new RuntimeException('Atomic rename failed for: ' . $filePath);
        }
    }

    /**
     * Exklusives Update mit stabiler Lock-Datei.
     * Wichtig: Lock liegt auf "$filePath.lock", damit rename() die Sperre nicht aushebelt.
     *
     * Mutator-Signatur:
     *   function(array $header, array $rows): array{array<int,string>, array<int,array<string,string>>}
     */
    public static function updateAssocAtomic(string $filePath, callable $mutator, string $delimiter = ';'): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            throw new RuntimeException('CSV directory missing: ' . $dir);
        }

        $lockPath = $filePath . '.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Cannot open lock file: ' . $lockPath);
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire lock for: ' . $filePath);
            }

            [$header, $rows] = self::readAssocWithHeader($filePath, $delimiter);

            $out = $mutator($header, $rows);
            if (!is_array($out) || count($out) !== 2) {
                throw new RuntimeException('Mutator must return [$header, $rows]');
            }

            /** @var array<int,string> $newHeader */
            $newHeader = $out[0];
            /** @var array<int,array<string,string>> $newRows */
            $newRows = $out[1];

            self::writeAssocAtomic($filePath, $newHeader, $newRows, $delimiter);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
