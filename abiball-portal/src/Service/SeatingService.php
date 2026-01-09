<?php
declare(strict_types=1);

// src/Service/SeatingService.php
require_once __DIR__ . '/../Repository/SeatingRepository.php';

final class SeatingService
{
    /**
     * Lädt Seating-Daten und normalisiert das Format so,
     * dass der Controller immer mit:
     *   $data['groups']['SGx']['members']
     * arbeiten kann (assoc keys: SG1/SG2/SG3).
     */
    public static function load(string $mainId): array
    {
        $data = SeatingRepository::loadForMainId($mainId);
        if (!is_array($data)) $data = [];

        $data['groups']       = self::normalizeGroups($data['groups'] ?? []);
        $data['group_notes']  = is_array($data['group_notes'] ?? null) ? $data['group_notes'] : [];
        $data['person_notes'] = is_array($data['person_notes'] ?? null) ? $data['person_notes'] : [];

        // Optional: nur erlaubte Group-Keys behalten
        $data['group_notes'] = self::filterGroupNotes($data['group_notes']);

        return $data;
    }

    /**
     * Speichert Gruppen + Notes in konsistentem Format.
     * Erwartet $groups als assoc array SG1/SG2/SG3.
     */
    public static function saveAll(string $mainId, array $groups, array $groupNotes, array $personNotes): void
    {
        $groups = self::normalizeGroups($groups);

        $cleanGroupNotes = self::filterGroupNotes($groupNotes);

        $cleanPersonNotes = [];
        foreach ($personNotes as $pid => $txt) {
            $pid = (string)$pid;
            if ($pid === '') continue;
            $cleanPersonNotes[$pid] = self::normalizeText($txt, 500);
        }

        SeatingRepository::saveForMainId($mainId, [
            'groups'       => $groups,
            'group_notes'  => $cleanGroupNotes,
            'person_notes' => $cleanPersonNotes,
        ]);
    }

    public static function updatePersonNote(string $mainId, string $personId, string $note): void
    {
        $data = self::load($mainId);

        $personId = trim($personId);
        if ($personId === '') return;

        $note = self::normalizeText($note, 500);

        if ($note === '') {
            unset($data['person_notes'][$personId]);
        } else {
            $data['person_notes'][$personId] = $note;
        }

        SeatingRepository::saveForMainId($mainId, $data);
    }

    /**
     * Normalisiert groups auf:
     * [
     *   'SG1' => ['name' => 'Sitzgruppe 1', 'members' => ['ID1','ID2',...]],
     *   'SG2' => ...,
     *   'SG3' => ...
     * ]
     *
     * Unterstützt auch Alt-/Fehlformate:
     * - LISTE von Gruppenobjekten: [ ['id'=>'SG2','members'=>[...] ], ... ]
     * - Assoc, aber members kein Array
     * - members enthält Duplikate / leere Werte
     */
    private static function normalizeGroups(mixed $groupsRaw): array
    {
        $allowed = ['SG1', 'SG2', 'SG3'];

        if (!is_array($groupsRaw)) {
            return ['SG1' => ['name' => 'Sitzgruppe 1', 'members' => []]];
        }

        $groups = $groupsRaw;

        // PHP < 8.1 compatibility: array_is_list shim
        $isList = self::arrayIsList($groups);

        // 1) Wenn LISTE -> in ASSOC umwandeln
        if ($isList) {
            $assoc = [];
            foreach ($groups as $g) {
                if (!is_array($g)) continue;

                $gid = $g['id'] ?? $g['group_id'] ?? $g['key'] ?? null;
                if (!is_string($gid) || $gid === '') continue;
                if (!in_array($gid, $allowed, true)) continue;

                $members = $g['members'] ?? [];
                $assoc[$gid] = [
                    'name'    => (string)($g['name'] ?? $thisName = ($gid === 'SG1' ? 'Sitzgruppe 1' : ($gid === 'SG2' ? 'Sitzgruppe 2' : 'Sitzgruppe 3'))),
                    'members' => self::normalizeMembers($members),
                ];
            }
            $groups = $assoc;
        }

        // 2) Assoc: nur SG1/2/3 behalten + Struktur absichern
        $out = [];

        foreach ($allowed as $gid) {
            $g = $groups[$gid] ?? null;

            if (!is_array($g)) {
                $out[$gid] = [
                    'name'    => self::defaultGroupName($gid),
                    'members' => [],
                ];
                continue;
            }

            $out[$gid] = [
                'name'    => (string)($g['name'] ?? self::defaultGroupName($gid)),
                'members' => self::normalizeMembers($g['members'] ?? []),
            ];
        }

        // 3) Wenn SG2/SG3 komplett leer sind, ist das okay – Controller blendet sie je nach saved presence ein/aus.
        // Du willst aber "presence" erhalten: Wenn ursprünglich SG2/SG3 gar nicht existierten, sollen sie auch nicht erscheinen.
        // Daher entfernen wir sie wieder, falls sie im Original nicht da waren.
        // (Wichtig, damit dein UI-Logic $hasSG2/$hasSG3 korrekt bleibt.)
        foreach (['SG2', 'SG3'] as $gid) {
            if (!isset($groupsRaw[$gid]) && !(is_array($groupsRaw) && self::arrayIsList($groupsRaw))) {
                // original war assoc und hatte gid nicht -> weg lassen
                unset($out[$gid]);
            }
        }

        // SG1 muss immer existieren
        if (!isset($out['SG1'])) {
            $out['SG1'] = ['name' => 'Sitzgruppe 1', 'members' => []];
        }

        return $out;
    }

    private static function normalizeMembers(mixed $membersRaw): array
    {
        if (!is_array($membersRaw)) return [];

        $seen = [];
        $out = [];

        foreach ($membersRaw as $m) {
            $id = trim((string)$m);
            if ($id === '') continue;
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $out[] = $id;
        }

        return $out;
    }

    private static function filterGroupNotes(mixed $groupNotes): array
    {
        $allowed = ['SG1','SG2','SG3'];
        $out = [];

        if (!is_array($groupNotes)) return $out;

        foreach ($groupNotes as $gid => $txt) {
            $gid = (string)$gid;
            if (!in_array($gid, $allowed, true)) continue;
            $out[$gid] = self::normalizeText($txt, 500);
        }

        return $out;
    }

    private static function defaultGroupName(string $gid): string
    {
        return match ($gid) {
            'SG1' => 'Sitzgruppe 1',
            'SG2' => 'Sitzgruppe 2',
            'SG3' => 'Sitzgruppe 3',
            default => $gid,
        };
    }

    private static function normalizeText(mixed $v, int $maxLen): string
    {
        $s = trim((string)$v);
        if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
            $s = mb_substr($s, 0, $maxLen);
        }
        return $s;
    }

    /**
     * PHP 8.1 hat array_is_list(). Für ältere Versionen:
     */
    private static function arrayIsList(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }

        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) return false;
            $i++;
        }
        return true;
    }
}
