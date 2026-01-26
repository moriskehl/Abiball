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
        'creative' => 'Kreativster Unterricht',
        'attractive' => 'Am attraktivsten',
        'strict' => 'Am strengsten',
        'funny' => 'Am lustigsten',
    ];

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
            
            if ($teacherId !== '' && isset($teachers[$teacherId])) {
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
