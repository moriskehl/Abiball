<?php

declare(strict_types=1);

/**
 * VotingService - Logik für das Lehrer-Voting
 * 
 * Verwaltet Kategorien, validiert Stimmen und speichert sie in CSV-Dateien.
 * Erlaubt Änderungen bis zur Deadline (siehe Config::isVotingChangeAllowed).
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Config.php';

final class VotingService
{
    private const VOTES_FILE = __DIR__ . '/../../data/votes.csv';
    private const VOTERS_FILE = __DIR__ . '/../../data/voters.csv';

    // Kategorien definieren
    public const CATEGORIES = [
        'schnellstes_korrigieren' => 'Am Schnellsten im Korrigieren',
        'breitester_lehrer'       => 'Breitester Lehrer',
        'wer_wird_millionaer'     => 'Würde bei "Wer wird Millionär" mindestens 500.000€ holen',
        'kaffeekoenig'            => 'König / Königin des Kaffekonsums',
        'gute_laune'              => 'Hat immer gute Laune',
        'stand_up_comedy'         => 'Könnte eigentlich Stand-Up-Comedy machen',
        'bester_style'            => 'Hat den Besten Style',
        'kreativster_unterricht'  => 'Kreativster Unterricht',
        'huebscheste_lehrkraft'   => 'Hübscheste Lehrerin / Hübschester Lehrer',
        'tiktok_viral'            => 'Würde heimlich auf TikTok viral gehen',
        'meiste_geduld'           => 'Am meisten Geduld',
    ];

    // Spezialwert für "keine Antwort"
    public const NO_ANSWER = '__none__';

    // Lehrer die von der Abstimmung ausgeschlossen sind (Nachname)
    private const EXCLUDED_TEACHERS = ['Koch', 'Diebold'];

    /**
     * Prüft ob ein User ein Lehrer ist (ID endet auf 'L').
     */
    public static function isTeacher(string $userId): bool
    {
        return str_ends_with(strtoupper(trim($userId)), 'L');
    }

    /**
     * Gibt alle Lehrer zurück (IDs enden auf 'L'), außer ausgeschlossene.
     * @return array<string, string> Assoc Array [ID => Name]
     */
    public static function getTeachers(): array
    {
        $all = ParticipantsRepository::all();
        $teachers = [];

        foreach ($all as $p) {
            $id = trim((string)($p['id'] ?? ''));
            $name = trim((string)($p['name'] ?? $id));

            if (!str_ends_with(strtoupper($id), 'L')) {
                continue;
            }

            // Ausgeschlossene Lehrer überspringen
            $excluded = false;
            foreach (self::EXCLUDED_TEACHERS as $excludedName) {
                if (stripos($name, $excludedName) !== false) {
                    $excluded = true;
                    break;
                }
            }

            if (!$excluded) {
                $teachers[$id] = $name;
            }
        }

        asort($teachers);
        return $teachers;
    }

    /**
     * Prüft, ob ein User bereits abgestimmt hat.
     */
    public static function hasVoted(string $userId): bool
    {
        $voters = self::loadVoters();
        return isset($voters[$userId]);
    }

    /**
     * Prüft, ob Änderungen noch erlaubt sind.
     */
    public static function canChangeVote(): bool
    {
        return Config::isVotingChangeAllowed();
    }

    /**
     * Holt die bisherigen Stimmen eines Users.
     * @return array<string, string> [category => teacherId]
     */
    public static function getVotesForUser(string $userId): array
    {
        $file = self::VOTES_FILE;
        if (!file_exists($file)) {
            return [];
        }

        $votes = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        // Header überspringen
        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 3) {
                $voterId = trim((string)$row[0]);
                $category = trim((string)$row[1]);
                $teacherId = trim((string)$row[2]);

                if ($voterId === $userId) {
                    $votes[$category] = $teacherId;
                }
            }
        }

        fclose($handle);
        return $votes;
    }

    /**
     * Speichert oder aktualisiert die Stimmen eines Users.
     * @param string $userId
     * @param array $votes Assoziatives Array [category => teacherId]
     * @return bool Erfolg
     */
    public static function submitVote(string $userId, array $votes): bool
    {
        $hasVotedBefore = self::hasVoted($userId);

        // Wenn bereits abgestimmt und Änderungen nicht mehr erlaubt
        if ($hasVotedBefore && !self::canChangeVote()) {
            return false;
        }

        $teachers = self::getTeachers();
        $validVotes = [];

        foreach (self::CATEGORIES as $catKey => $catLabel) {
            $teacherId = trim((string)($votes[$catKey] ?? ''));

            // "keine Antwort" ist eine gültige Auswahl
            if ($teacherId === self::NO_ANSWER) {
                $validVotes[$catKey] = self::NO_ANSWER;
            } elseif ($teacherId !== '' && isset($teachers[$teacherId])) {
                $validVotes[$catKey] = $teacherId;
            }
        }

        if (empty($validVotes)) {
            return false;
        }

        // Bei Update: Alte Stimmen entfernen
        if ($hasVotedBefore) {
            self::removeVotesForUser($userId);
        } else {
            // Voter in CSV speichern
            self::saveVoter($userId);
        }

        // Neue Stimmen speichern
        foreach ($validVotes as $cat => $tid) {
            self::appendVote($userId, $cat, $tid);
        }

        return true;
    }

    /**
     * Entfernt alle Stimmen eines Users (für Updates).
     */
    private static function removeVotesForUser(string $userId): void
    {
        $file = self::VOTES_FILE;
        if (!file_exists($file)) {
            return;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return;
        }

        $header = fgetcsv($handle, 0, ';');
        $rows = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 3) {
                $voterId = trim((string)$row[0]);
                if ($voterId !== $userId) {
                    $rows[] = $row;
                }
            }
        }

        fclose($handle);

        // Datei neu schreiben ohne die Votes des Users
        $handle = fopen($file, 'w');
        if ($handle === false) {
            return;
        }

        fputcsv($handle, $header ?: ['user_id', 'category', 'teacher_id'], ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);
    }

    /**
     * Gibt die Ergebnisse zurück (Top 5 pro Kategorie).
     */
    public static function getResults(): array
    {
        $votes = self::loadVotes();
        $teachers = self::getTeachers();
        $output = [];

        foreach (self::CATEGORIES as $catKey => $catLabel) {
            $counts = $votes[$catKey] ?? [];

            // "keine Antwort" nicht in Ergebnisse einbeziehen
            unset($counts[self::NO_ANSWER]);

            arsort($counts);
            $top5 = array_slice($counts, 0, 5, true);

            $list = [];
            foreach ($top5 as $tid => $count) {
                $list[] = [
                    'id' => $tid,
                    'name' => $teachers[$tid] ?? $tid,
                    'votes' => $count
                ];
            }

            $output[$catKey] = [
                'label' => $catLabel,
                'rankings' => $list
            ];
        }

        return $output;
    }

    /**
     * Gibt die kompletten Ergebnisse zurück (alle Lehrer pro Kategorie, für Admin).
     */
    public static function getFullResults(): array
    {
        $votes = self::loadVotes();
        $teachers = self::getTeachers();
        $output = [];

        foreach (self::CATEGORIES as $catKey => $catLabel) {
            $counts = $votes[$catKey] ?? [];

            // "keine Antwort" nicht in Ergebnisse einbeziehen
            unset($counts[self::NO_ANSWER]);

            arsort($counts);

            $list = [];
            foreach ($counts as $tid => $count) {
                $list[] = [
                    'id' => $tid,
                    'name' => $teachers[$tid] ?? $tid,
                    'votes' => $count
                ];
            }

            $output[$catKey] = [
                'label' => $catLabel,
                'rankings' => $list
            ];
        }

        return $output;
    }

    /**
     * Gibt die Gesamtzahl der Wähler zurück.
     */
    public static function getTotalVoterCount(): int
    {
        return count(self::loadVoters());
    }

    private static function loadVoters(): array
    {
        $file = self::VOTERS_FILE;
        if (!file_exists($file)) {
            return [];
        }

        $voters = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 2) {
                $voters[trim((string)$row[0])] = (int)$row[1];
            }
        }

        fclose($handle);
        return $voters;
    }

    private static function saveVoter(string $userId): void
    {
        $file = self::VOTERS_FILE;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $writeHeader = !file_exists($file);

        $handle = fopen($file, 'a');
        if ($handle === false) {
            return;
        }

        if ($writeHeader) {
            fputcsv($handle, ['user_id', 'timestamp'], ';');
        }

        fputcsv($handle, [$userId, time()], ';');
        fclose($handle);
    }

    private static function loadVotes(): array
    {
        $file = self::VOTES_FILE;
        if (!file_exists($file)) {
            return [];
        }

        $votes = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 3) {
                $category = trim((string)$row[1]);
                $teacherId = trim((string)$row[2]);

                if (!isset($votes[$category][$teacherId])) {
                    $votes[$category][$teacherId] = 0;
                }
                $votes[$category][$teacherId]++;
            }
        }

        fclose($handle);
        return $votes;
    }

    private static function appendVote(string $userId, string $category, string $teacherId): void
    {
        $file = self::VOTES_FILE;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $writeHeader = !file_exists($file);

        $handle = fopen($file, 'a');
        if ($handle === false) {
            return;
        }

        if ($writeHeader) {
            fputcsv($handle, ['user_id', 'category', 'teacher_id'], ';');
        }

        fputcsv($handle, [$userId, $category, $teacherId], ';');
        fclose($handle);
    }
}
