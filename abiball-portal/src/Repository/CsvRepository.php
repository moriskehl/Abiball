<?php
declare(strict_types=1);

/**
 * CsvRepository - Basis-Klasse für CSV-Datei-Operationen
 * 
 * Stellt Methoden zum Lesen und Schreiben von CSV-Dateien bereit.
 * Unterstützt atomares Schreiben mit Locks um Race-Conditions zu vermeiden.
 */

final class CsvRepository
{
    /**
     * Liest eine CSV-Datei und gibt ein Array aus assoziativen Zeilen zurück.
     * Die erste Zeile wird als Header verwendet.
     * 
     * @param string $filePath  Pfad zur CSV-Datei
     * @param string $delimiter Trennzeichen (Standard: Semikolon)
     * @return array Array von Zeilen, jede Zeile ist ein assoziatives Array
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

        // Header lesen
        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(
            static fn($v) => trim((string)$v),
            $header
        );

        // UTF-8 BOM entfernen (häufig bei Excel-Export)
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // Leere Zeilen überspringen
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

            // Zeilen mit ungültiger ID überspringen
            if (($row['id'] ?? '') === '' || ($row['id'] ?? '') === '#WERT!') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Wie readAssoc(), gibt aber zusätzlich den Header zurück.
     * Nützlich wenn der Header beim Schreiben erhalten bleiben soll.
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

        // UTF-8 BOM entfernen
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

    /**
     * Konvertiert einen String-Wert zu Boolean.
     * Akzeptiert: TRUE, WAHR, 1 als true.
     */
    public static function parseBool(string $value): bool
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['TRUE', 'WAHR', '1'], true);
    }

    /**
     * Schützt einen Wert vor CSV-Injection (Formula Injection).
     * Excel/LibreOffice können Zellen die mit =, +, -, @ beginnen
     * als Formeln interpretieren - das kann gefährlich sein.
     * 
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     */
    public static function sanitizeCsvValue(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Schreibt eine CSV-Datei atomar.
     * Schreibt zuerst in eine temporäre Datei und benennt diese dann um.
     * So wird verhindert dass bei einem Fehler die Datei korrupt wird.
     * 
     * @param array<int,string> $header
     * @param array<int,array<string,scalar|null>> $rows
     */
    public static function writeAssocAtomic(string $filePath, array $header, array $rows, string $delimiter = ';'): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            throw new RuntimeException('CSV-Verzeichnis existiert nicht: ' . $dir);
        }

        $tmp = tempnam($dir, 'csv_');
        if ($tmp === false) {
            throw new RuntimeException('Kann temp-Datei nicht erstellen in: ' . $dir);
        }

        $h = fopen($tmp, 'wb');
        if ($h === false) {
            @unlink($tmp);
            throw new RuntimeException('Kann temp-Datei nicht schreiben: ' . $tmp);
        }

        // Header schreiben
        fputcsv($h, $header, $delimiter, '"', '\\');

        // Zeilen in Header-Reihenfolge schreiben
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $col) {
                $val = array_key_exists($col, $r) ? (string)($r[$col] ?? '') : '';
                
                // CSV-Injection-Schutz für Benutzereingaben
                // System-Felder wie IDs werden nicht escaped
                if (!in_array($col, ['id', 'main_id', 'login_code', 'password_changed'], true)) {
                    $val = self::sanitizeCsvValue($val);
                }
                $line[] = $val;
            }
            fputcsv($h, $line, $delimiter, '"', '\\');
        }

        fclose($h);

        // Alte Datei entfernen und neue einsetzen
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if (!rename($tmp, $filePath)) {
            @unlink($tmp);
            throw new RuntimeException('Atomares Umbenennen fehlgeschlagen für: ' . $filePath);
        }
    }

    /**
     * Aktualisiert eine CSV-Datei atomar mit einem Lock.
     * Verhindert Race-Conditions wenn mehrere Requests gleichzeitig schreiben.
     * 
     * Der Mutator bekommt Header und Rows und muss [newHeader, newRows] zurückgeben.
     * 
     * @param callable $mutator function(array $header, array $rows): array{array, array}
     */
    public static function updateAssocAtomic(string $filePath, callable $mutator, string $delimiter = ';'): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            throw new RuntimeException('CSV-Verzeichnis existiert nicht: ' . $dir);
        }

        // Lock auf separate Datei um rename() nicht zu blockieren
        $lockPath = $filePath . '.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Kann Lock-Datei nicht öffnen: ' . $lockPath);
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Kann Lock nicht erhalten für: ' . $filePath);
            }

            [$header, $rows] = self::readAssocWithHeader($filePath, $delimiter);

            $out = $mutator($header, $rows);
            if (!is_array($out) || count($out) !== 2) {
                throw new RuntimeException('Mutator muss [$header, $rows] zurückgeben');
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
