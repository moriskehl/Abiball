<?php
// src/Repository/CsvRepository.php

final class CsvRepository
{
    /**
     * Liest eine ; getrennte CSV und gibt ein Array aus assoziativen Zeilen zurück.
     * Leere Header-Spalten werden ignoriert.
     */
    public static function readAssoc(string $filePath, string $delimiter = ';'): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $h = fopen($filePath, 'r');
        if ($h === false) {
            return [];
        }

        $header = fgetcsv($h, 0, $delimiter);
        if ($header === false) {
            fclose($h);
            return [];
        }

        $header = array_map(fn($x) => trim((string)$x), $header);

        // UTF-8 BOM entfernen (häufig bei "CSV UTF-8" aus Excel)
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $rows = [];
        while (($line = fgetcsv($h, 0, $delimiter)) !== false) {
            // komplett leere Zeilen skippen
            if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                if ($col === '') continue;
                $row[$col] = isset($line[$i]) ? trim((string)$line[$i]) : '';
            }

            // ungültige id skippen
            if (($row['id'] ?? '') === '' || ($row['id'] ?? '') === '#WERT!') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($h);
        return $rows;
    }

    public static function parseBool(string $v): bool
    {
        $v = strtoupper(trim($v));
        return in_array($v, ['TRUE', 'WAHR', '1'], true);
    }
}
