<?php
declare(strict_types=1);

/**
 * SeatingRepository - Verwaltung der Sitzplatz-Wuensche
 * 
 * Speichert pro Hauptgast individuelle Sitzgruppen-Praeferenzen
 * in einer JSON-Datei.
 */

require_once __DIR__ . '/../Bootstrap.php';

final class SeatingRepository
{
    private static function filePath(): string
    {
        return __DIR__ . '/../../storage/data/seating_groups.json';
    }

    /**
     * Laedt alle Sitzgruppen-Daten aus der JSON-Datei.
     * 
     * @return array<string,mixed>
     */
    private static function loadAll(): array
    {
        $path = self::filePath();
        if (!is_file($path)) return [];
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Speichert alle Sitzgruppen-Daten atomisch.
     */
    private static function saveAll(array $all): void
    {
        $path = self::filePath();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, $path);
    }

    /**
     * Laedt die Sitzgruppen eines Hauptgastes.
     * 
     * @return array{groups: array<string,array{name:string,members:array<int,string>}>}
     */
    public static function loadForMainId(string $mainId): array
    {
        $mainId = trim((string)$mainId);
        $all = self::loadAll();

        $entry = $all[$mainId] ?? null;
        if (!is_array($entry)) {
            return ['groups' => []];
        }

        $groups = $entry['groups'] ?? [];
        if (!is_array($groups)) $groups = [];

        // Normalisieren
        $clean = [];
        foreach ($groups as $gid => $g) {
            if (!is_string($gid) || $gid === '') continue;
            if (!is_array($g)) continue;

            $name = (string)($g['name'] ?? $gid);
            $members = $g['members'] ?? [];
            if (!is_array($members)) $members = [];

            $members = array_values(array_filter(array_map('strval', $members), fn($x) => $x !== ''));
            $clean[$gid] = ['name' => $name, 'members' => $members];
        }

        return ['groups' => $clean];
    }

    /**     * Speichert die Sitzgruppen eines Hauptgastes.
     *      * @param array<string,array{name:string,members:array<int,string>>> $groups
     */
    public static function saveForMainId(string $mainId, array $groups): void
    {
        $mainId = trim((string)$mainId);
        $all = self::loadAll();
        $all[$mainId] = ['groups' => $groups];
        self::saveAll($all);
    }
}
