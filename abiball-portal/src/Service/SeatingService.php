<?php
declare(strict_types=1);

/**
 * SeatingService - Verwaltung der Sitzplatzplanung
 * 
 * Speichert und lädt Sitzgruppen sowie Notizen für Personen und Gruppen.
 * Jeder Hauptgast hat eine eigene JSON-Datei mit seiner Sitzplatzplanung.
 */

require_once __DIR__ . '/../Config.php';

final class SeatingService
{
    /**
     * Lädt die Sitzplatzplanung eines Hauptgastes.
     * Gibt immer ein normalisiertes Array mit mindestens einer Sitzgruppe zurück.
     */
    public static function load(string $mainId): array
    {
        $mainId = trim($mainId);
        if ($mainId === '') return self::empty();

        $path = Config::seatingJsonPath($mainId);
        if (!is_file($path)) return self::empty();

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return self::empty();

        $data = json_decode($raw, true);
        if (!is_array($data)) return self::empty();

        return self::normalize($data);
    }

    /**
     * Speichert die komplette Sitzplatzplanung eines Hauptgastes.
     * Verwendet atomares Schreiben um Datenverlust bei Abstürzen zu vermeiden.
     */
    public static function saveAll(string $mainId, array $groups, array $groupNotes, array $personNotes): void
    {
        $mainId = trim($mainId);
        if ($mainId === '') throw new InvalidArgumentException('mainId missing');

        $data = [
            'groups' => $groups,
            'group_notes' => $groupNotes,
            'person_notes' => $personNotes,
        ];

        $data = self::normalize($data);

        $path = Config::seatingJsonPath($mainId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmp = $path . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode seating json');
        }

        $fh = @fopen($tmp, 'wb');
        if ($fh === false) throw new RuntimeException('Cannot write seating tmp file');

        try {
            if (@fwrite($fh, $json) === false) throw new RuntimeException('Write failed');
            @fflush($fh);
        } finally {
            @fclose($fh);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Atomic rename failed');
        }
    }

    /**
     * Aktualisiert die Notiz einer einzelnen Person.
     * Bei leerem Notiztext wird die Notiz gelöscht.
     */
    public static function updatePersonNote(string $mainId, string $personId, string $note): void
    {
        $mainId = trim($mainId);
        $personId = trim($personId);
        $note = trim($note);

        if ($mainId === '') throw new InvalidArgumentException('mainId missing');
        if ($personId === '') throw new InvalidArgumentException('personId missing');

        $existing = self::load($mainId);

        $groups = $existing['groups'] ?? [];
        if (!is_array($groups)) $groups = [];

        $groupNotes = $existing['group_notes'] ?? [];
        if (!is_array($groupNotes)) $groupNotes = [];

        $personNotes = $existing['person_notes'] ?? [];
        if (!is_array($personNotes)) $personNotes = [];

        if ($note === '') {
            unset($personNotes[$personId]);
        } else {
            $personNotes[$personId] = $note;
        }

        self::saveAll($mainId, $groups, $groupNotes, $personNotes);
    }

    /**
     * Aktualisiert die Notiz einer Sitzgruppe.
     * Bei leerem Notiztext wird die Notiz gelöscht.
     */
    public static function updateGroupNote(string $mainId, string $groupId, string $note): void
    {
        $mainId = trim($mainId);
        $groupId = trim($groupId);
        $note = trim($note);

        if ($mainId === '') throw new InvalidArgumentException('mainId missing');
        if ($groupId === '') throw new InvalidArgumentException('groupId missing');

        $existing = self::load($mainId);

        $groups = $existing['groups'] ?? [];
        if (!is_array($groups)) $groups = [];

        $groupNotes = $existing['group_notes'] ?? [];
        if (!is_array($groupNotes)) $groupNotes = [];

        $personNotes = $existing['person_notes'] ?? [];
        if (!is_array($personNotes)) $personNotes = [];

        if ($note === '') {
            unset($groupNotes[$groupId]);
        } else {
            $groupNotes[$groupId] = $note;
        }

        self::saveAll($mainId, $groups, $groupNotes, $personNotes);
    }

    /**
     * Liefert eine leere Standardstruktur mit einer Sitzgruppe.
     */
    private static function empty(): array
    {
        return [
            'groups' => [
                'SG1' => ['name' => 'Sitzgruppe 1', 'members' => []],
            ],
            'group_notes' => [],
            'person_notes' => [],
        ];
    }

    /**
     * Normalisiert die Datenstruktur und migriert alte Formate.
     * Stellt sicher, dass immer mindestens Sitzgruppe 1 existiert.
     */
    private static function normalize(array $data): array
    {
        $SG1 = 'SG1'; $SG2 = 'SG2'; $SG3 = 'SG3';

        $groups = $data['groups'] ?? [];
        if (!is_array($groups)) $groups = [];

        // Migration: Alte numerische Arrays auf benannte Gruppen umstellen
        $isList = self::isListArray($groups);
        if ($isList) {
            $mapped = [];
            if (isset($groups[0]) && is_array($groups[0])) $mapped[$SG1] = $groups[0];
            if (isset($groups[1]) && is_array($groups[1])) $mapped[$SG2] = $groups[1];
            if (isset($groups[2]) && is_array($groups[2])) $mapped[$SG3] = $groups[2];
            $groups = $mapped;
        }

        if (!isset($groups[$SG1]) || !is_array($groups[$SG1])) {
            $groups[$SG1] = ['name' => 'Sitzgruppe 1', 'members' => []];
        }

        $normGroups = [];
        foreach ([$SG1,$SG2,$SG3] as $gid) {
            if (!isset($groups[$gid]) || !is_array($groups[$gid])) continue;

            $name = trim((string)($groups[$gid]['name'] ?? ''));
            if ($name === '') {
                $name = match ($gid) {
                    $SG1 => 'Sitzgruppe 1',
                    $SG2 => 'Sitzgruppe 2',
                    $SG3 => 'Sitzgruppe 3',
                    default => $gid
                };
            }

            $members = $groups[$gid]['members'] ?? [];
            if (!is_array($members)) $members = [];

            // Duplikate entfernen und leere Einträge filtern
            $seen = [];
            $cleanMembers = [];
            foreach ($members as $m) {
                $m = trim((string)$m);
                if ($m === '' || isset($seen[$m])) continue;
                $seen[$m] = true;
                $cleanMembers[] = $m;
            }

            $normGroups[$gid] = ['name' => $name, 'members' => $cleanMembers];
        }

        if (!isset($normGroups[$SG1])) {
            $normGroups[$SG1] = ['name' => 'Sitzgruppe 1', 'members' => []];
        }

        $groupNotes = $data['group_notes'] ?? [];
        if (!is_array($groupNotes)) $groupNotes = [];

        $personNotes = $data['person_notes'] ?? [];
        if (!is_array($personNotes)) $personNotes = [];

        return [
            'groups' => $normGroups,
            'group_notes' => $groupNotes,
            'person_notes' => $personNotes,
        ];
    }

    /**
     * Prüft, ob ein Array ein numerisch indiziertes Listen-Array ist.
     */
    private static function isListArray(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) return false;
            $i++;
        }
        return true;
    }
}
