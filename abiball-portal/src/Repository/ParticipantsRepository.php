<?php
declare(strict_types=1);

// src/Repository/ParticipantsRepository.php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/CsvRepository.php';

final class ParticipantsRepository
{
    /**
     * Lädt alle Teilnehmer aus der CSV und normalisiert Felder.
     *
     * Erwartete CSV-Header (mindestens):
     * id;name;is_main;main_id;login_code
     * Optional:
     * role (z.B. ADMIN)
     * amount_paid (Zahlungseingang, sinnvoll beim Hauptgast)
     *
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        $rows = CsvRepository::readAssoc(Config::participantsCsvPath(), ';');

        foreach ($rows as &$r) {
            $r['id']         = trim((string)($r['id'] ?? ''));
            $r['name']       = trim((string)($r['name'] ?? ''));
            $r['main_id']    = trim((string)($r['main_id'] ?? ''));
            $r['login_code'] = trim((string)($r['login_code'] ?? ''));

            // bool normalisieren
            $r['is_main_bool'] = CsvRepository::parseBool((string)($r['is_main'] ?? ''));

            // role optional (Default USER)
            $r['role'] = strtoupper(trim((string)($r['role'] ?? 'USER')));
            if ($r['role'] === '') {
                $r['role'] = 'USER';
            }

            // amount_paid optional (Default 0)
            $rawPaid = trim((string)($r['amount_paid'] ?? ''));
            $r['amount_paid_int'] = is_numeric($rawPaid) ? (int)round((float)$rawPaid) : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * Findet einen Teilnehmer (Hauptgast oder Begleitung) anhand der exakten ID.
     */
    public static function findById(string $id): ?array
    {
        $id = trim((string)$id);
        if ($id === '') return null;

        foreach (self::all() as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Findet einen Hauptgast (is_main=true) über ID oder exakten Namen (case-insensitive).
     */
    public static function findMainByIdOrName(string $identifier): ?array
    {
        $identifier = trim((string)$identifier);
        if ($identifier === '') return null;

        $all = self::all();
        $identifierLower = mb_strtolower($identifier, 'UTF-8');

        // 1) ID bevorzugen
        foreach ($all as $p) {
            if (!($p['is_main_bool'] ?? false)) continue;
            if (($p['id'] ?? '') === $identifier) return $p;
        }

        // 2) exakter Name
        foreach ($all as $p) {
            if (!($p['is_main_bool'] ?? false)) continue;
            if (mb_strtolower((string)($p['name'] ?? ''), 'UTF-8') === $identifierLower) return $p;
        }

        return null;
    }

    /**
     * Findet einen Admin (role=ADMIN) über ID oder exakten Namen (case-insensitive).
     */
    public static function findAdminByIdOrName(string $identifier): ?array
    {
        $identifier = trim((string)$identifier);
        if ($identifier === '') return null;

        $all = self::all();
        $identifierLower = mb_strtolower($identifier, 'UTF-8');

        // 1) ID bevorzugen
        foreach ($all as $p) {
            if (($p['role'] ?? 'USER') !== 'ADMIN') continue;
            if (($p['id'] ?? '') === $identifier) return $p;
        }

        // 2) exakter Name
        foreach ($all as $p) {
            if (($p['role'] ?? 'USER') !== 'ADMIN') continue;
            if (mb_strtolower((string)($p['name'] ?? ''), 'UTF-8') === $identifierLower) return $p;
        }

        return null;
    }

    /**
     * Gibt Hauptgast + Begleiter anhand main_id zurück.
     *
     * @return array{main: ?array, companions: array<int,array>}
     */
    public static function getGroupByMainId(string $mainId): array
    {
        $mainId = trim((string)$mainId);
        $all = self::all();

        $main = null;
        $companions = [];

        foreach ($all as $p) {
            if (($p['main_id'] ?? '') !== $mainId) continue;

            if (($p['is_main_bool'] ?? false)) $main = $p;
            else $companions[] = $p;
        }

        return ['main' => $main, 'companions' => $companions];
    }

    /**
     * Betrag, der beim Hauptgast als bezahlt hinterlegt ist (amount_paid).
     * Empfehlung: amount_paid nur beim Hauptgast pflegen.
     */
    public static function amountPaidForMainId(string $mainId): int
    {
        $g = self::getGroupByMainId($mainId);
        $main = $g['main'];
        if (!$main) return 0;

        return (int)($main['amount_paid_int'] ?? 0);
    }

    /**
     * Ticketanzahl als Default = Gruppengröße (Hauptgast + Begleiter).
     * Das ist die einzig belastbare Zahl aus der CSV, wenn es kein eigenes Feld gibt.
     */
    public static function ticketCountForMainId(string $mainId): int
    {
        $g = self::getGroupByMainId($mainId);
        $count = 0;

        if (!empty($g['main'])) $count++;
        $count += count($g['companions'] ?? []);

        // Minimum 1, falls CSV inkonsistent ist
        return max(1, $count);
    }

    /**
     * main_id robust bestimmen:
     * - Wenn main_id gesetzt ist -> nutzen
     * - sonst id nutzen (typisch beim Hauptgast)
     */
    public static function resolveMainIdFromRow(array $row): string
    {
        $mid = trim((string)($row['main_id'] ?? ''));
        if ($mid !== '') return $mid;

        return trim((string)($row['id'] ?? ''));
    }

    /**
     * Gruppiert alle Datensätze nach main_id.
     * Nützlich fürs Admin-Dashboard.
     *
     * @return array<string, array<int,array>>
     */
    public static function groupAllByMainId(): array
    {
        $all = self::all();
        $groups = [];

        foreach ($all as $p) {
            $mid = (string)($p['main_id'] ?? '');
            if ($mid === '') continue;
            $groups[$mid][] = $p;
        }

        ksort($groups);
        return $groups;
    }
}
