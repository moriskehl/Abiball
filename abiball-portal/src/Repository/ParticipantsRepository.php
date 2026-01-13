<?php
declare(strict_types=1);

// src/Repository/ParticipantsRepository.php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/CsvRepository.php';

final class ParticipantsRepository
{
    /**
     * Erwartete CSV-Header:
     * id;name;is_main;main_id;login_code;role;amount_paid;amount_subsided
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

            $r['is_main_bool'] = CsvRepository::parseBool((string)($r['is_main'] ?? ''));

            $r['role'] = strtoupper(trim((string)($r['role'] ?? 'USER')));
            if ($r['role'] === '') $r['role'] = 'USER';

            $rawPaid = trim((string)($r['amount_paid'] ?? ''));
            $r['amount_paid_int'] = is_numeric($rawPaid) ? (int)round((float)$rawPaid) : 0;

            $rawSub = trim((string)($r['amount_subsided'] ?? ''));
            $r['amount_subsided_int'] = is_numeric($rawSub) ? (int)round((float)$rawSub) : 0;
        }
        unset($r);

        return $rows;
    }

    public static function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') return null;

        foreach (self::all() as $p) {
            if (($p['id'] ?? '') === $id) return $p;
        }
        return null;
    }

    public static function findMainByIdOrName(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') return null;

        $all = self::all();
        $identifierLower = mb_strtolower($identifier, 'UTF-8');

        foreach ($all as $p) {
            if (empty($p['is_main_bool'])) continue;
            if (($p['id'] ?? '') === $identifier) return $p;
        }

        foreach ($all as $p) {
            if (empty($p['is_main_bool'])) continue;
            if (mb_strtolower((string)($p['name'] ?? ''), 'UTF-8') === $identifierLower) return $p;
        }

        return null;
    }

    public static function findAdminByIdOrName(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') return null;

        $all = self::all();
        $identifierLower = mb_strtolower($identifier, 'UTF-8');

        foreach ($all as $p) {
            if (($p['role'] ?? 'USER') !== 'ADMIN') continue;
            if (($p['id'] ?? '') === $identifier) return $p;
        }

        foreach ($all as $p) {
            if (($p['role'] ?? 'USER') !== 'ADMIN') continue;
            if (mb_strtolower((string)($p['name'] ?? ''), 'UTF-8') === $identifierLower) return $p;
        }

        return null;
    }

    /**
     * @return array{main: ?array, companions: array<int,array>}
     */
    public static function getGroupByMainId(string $mainId): array
    {
        $mainId = trim($mainId);
        $all = self::all();

        $main = null;
        $companions = [];

        foreach ($all as $p) {
            if (($p['main_id'] ?? '') !== $mainId) continue;

            if (!empty($p['is_main_bool'])) $main = $p;
            else $companions[] = $p;
        }

        return ['main' => $main, 'companions' => $companions];
    }

    public static function amountPaidForMainId(string $mainId): int
    {
        $g = self::getGroupByMainId($mainId);
        $main = $g['main'];
        if (!$main) return 0;
        return (int)($main['amount_paid_int'] ?? 0);
    }

    public static function ticketCountForMainId(string $mainId): int
    {
        $g = self::getGroupByMainId($mainId);
        $count = 0;

        if (!empty($g['main'])) $count++;
        $count += count($g['companions'] ?? []);

        return max(1, $count);
    }

    public static function resolveMainIdFromRow(array $row): string
    {
        $mid = trim((string)($row['main_id'] ?? ''));
        if ($mid !== '') return $mid;

        return trim((string)($row['id'] ?? ''));
    }

    /**
     * Für Dashboard/Auth: liefert die Hauptgast-Zeile einer main_id (is_main=true).
     */
    public static function mainRowForMainId(string $mainId): ?array
    {
        $mainId = trim($mainId);
        if ($mainId === '') return null;

        foreach (self::all() as $p) {
            if (($p['main_id'] ?? '') !== $mainId) continue;
            if (!empty($p['is_main_bool'])) return $p;
        }
        return null;
    }

    /**
     * Update: amount_paid beim Hauptgast (pro main_id).
     */
    public static function updateAmountPaidForMainId(string $mainId, int $amountPaid): void
    {
        $mainId = trim($mainId);
        if ($mainId === '') throw new InvalidArgumentException('mainId missing');
        if ($amountPaid < 0) $amountPaid = 0;

        $path = Config::participantsCsvPath();

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($mainId, $amountPaid): array {
            $header = self::ensureParticipantsHeader($header);

            $updated = false;
            foreach ($rows as &$r) {
                $rMid = trim((string)($r['main_id'] ?? ''));
                if ($rMid !== $mainId) continue;

                $isMain = CsvRepository::parseBool((string)($r['is_main'] ?? ''));
                if (!$isMain) continue;

                $r['amount_paid'] = (string)$amountPaid;
                $updated = true;
                break;
            }
            unset($r);

            if (!$updated) {
                throw new RuntimeException('Main guest not found for main_id=' . $mainId);
            }

            return [$header, $rows];
        }, ';');
    }

    /**
     * Update: login_code beim Hauptgast (pro main_id).
     * WICHTIG: Begleiter behalten ihren eigenen login_code (falls vorhanden),
     * damit nichts „kaputt“ geht. Wenn du willst, kannst du optional alle der Gruppe
     * mitziehen – hier bewusst NICHT.
     */
    public static function updateLoginCodeForMainId(string $mainId, string $newLoginCode): void
    {
        $mainId = trim($mainId);
        $newLoginCode = trim($newLoginCode);

        if ($mainId === '') throw new InvalidArgumentException('mainId missing');
        if ($newLoginCode === '') throw new InvalidArgumentException('newLoginCode missing');

        $path = Config::participantsCsvPath();

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($mainId, $newLoginCode): array {
            $header = self::ensureParticipantsHeader($header);

            $updated = false;
            foreach ($rows as &$r) {
                $rMid = trim((string)($r['main_id'] ?? ''));
                if ($rMid !== $mainId) continue;

                $isMain = CsvRepository::parseBool((string)($r['is_main'] ?? ''));
                if (!$isMain) continue;

                $r['login_code'] = $newLoginCode;
                $updated = true;
                break;
            }
            unset($r);

            if (!$updated) {
                throw new RuntimeException('Main guest not found for main_id=' . $mainId);
            }

            return [$header, $rows];
        }, ';');
    }

    /**
     * True, wenn der Hauptgast bereits ein „gehashtes“ Passwort hat.
     * (Heuristik: bcrypt startet üblicherweise mit $2y$ / $2a$ / $2b$)
     */
    public static function isPasswordChangedForMainId(string $mainId): bool
    {
        $row = self::mainRowForMainId($mainId);
        if (!$row) return false;

        $stored = (string)($row['login_code'] ?? '');
        $stored = trim($stored);

        if ($stored === '') return false;

        return (
            str_starts_with($stored, '$2y$') ||
            str_starts_with($stored, '$2a$') ||
            str_starts_with($stored, '$2b$')
        );
    }

    /**
     * @return array<string, array<int,array>>
     */
    public static function groupAllByMainIdRobust(): array
    {
        $all = self::all();
        $groups = [];

        foreach ($all as $p) {
            $mid = self::resolveMainIdFromRow($p);
            if ($mid === '') continue;
            $groups[$mid][] = $p;
        }

        ksort($groups);
        return $groups;
    }

    /* =========================
     * CREATE (Hauptgast/Begleiter)
     * ========================= */

    private static function ensureParticipantsHeader(array $header): array
    {
        $need = ['id','name','is_main','main_id','login_code','role','amount_paid','amount_subsided'];
        if (count($header) === 0) return $need;

        foreach ($need as $col) {
            if (!in_array($col, $header, true)) $header[] = $col;
        }
        return $header;
    }

    private static function assertIdFormat(string $id): void
    {
        if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new InvalidArgumentException('Invalid id');
        }
    }

    private static function normalizeRole(string $role): string
    {
        $r = strtoupper(trim($role));
        return $r !== '' ? $r : 'USER';
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = trim($name, " \t\n\r\0\x0B\"");
        return $name;
    }

    private static function nextCompanionId(string $mainId, array $rows): string
    {
        $prefix = (string)preg_replace('/S$/i', '', $mainId);
        $prefix = trim($prefix) !== '' ? trim($prefix) : $mainId;

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . 'B(\d+)$/i';

        foreach ($rows as $r) {
            $id = trim((string)($r['id'] ?? ''));
            if ($id === '') continue;
            if (preg_match($pattern, $id, $m)) {
                $n = (int)$m[1];
                if ($n > $max) $max = $n;
            }
        }

        return $prefix . 'B' . (string)($max + 1);
    }

    public static function createMainGuest(string $id, string $name, string $loginCode, string $role = 'USER'): void
    {
        $id = trim($id);
        $name = self::normalizeName($name);
        $loginCode = trim($loginCode);
        $role = self::normalizeRole($role);

        self::assertIdFormat($id);
        if ($name === '') throw new InvalidArgumentException('Name missing');
        if ($loginCode === '') throw new InvalidArgumentException('Login code missing');

        if (!preg_match('/S$/i', $id)) {
            throw new InvalidArgumentException('Main guest id must end with "S"');
        }

        $path = Config::participantsCsvPath();

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($id, $name, $loginCode, $role): array {
            $header = self::ensureParticipantsHeader($header);

            foreach ($rows as $r) {
                if (trim((string)($r['id'] ?? '')) === $id) {
                    throw new RuntimeException('ID exists');
                }
            }

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'is_main' => '1',
                'main_id' => $id,
                'login_code' => $loginCode,
                'role' => $role,
                'amount_paid' => '0',
                'amount_subsided' => '',
            ];

            return [$header, $rows];
        }, ';');
    }

    /**
     * @return string neue Begleiter-ID
     */
    public static function createCompanion(string $mainId, string $name, string $loginCode = ''): string
    {
        $mainId = trim($mainId);
        $name = self::normalizeName($name);
        $loginCode = trim($loginCode);

        if ($mainId === '') throw new InvalidArgumentException('mainId missing');
        if ($name === '') throw new InvalidArgumentException('Name missing');

        $path = Config::participantsCsvPath();
        $newId = '';

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($mainId, $name, $loginCode, &$newId): array {
            $header = self::ensureParticipantsHeader($header);

            $mainFound = false;
            $mainLogin = '';

            foreach ($rows as $r) {
                $rMid = trim((string)($r['main_id'] ?? ''));
                if ($rMid !== $mainId) continue;

                $isMain = CsvRepository::parseBool((string)($r['is_main'] ?? ''));
                if ($isMain) {
                    $mainFound = true;
                    $mainLogin = trim((string)($r['login_code'] ?? ''));
                    break;
                }
            }

            if (!$mainFound) {
                throw new RuntimeException('Main guest not found for main_id=' . $mainId);
            }

            $newId = self::nextCompanionId($mainId, $rows);
            self::assertIdFormat($newId);

            // login_code fallback = Hauptgast login_code
            $finalLogin = ($loginCode !== '') ? $loginCode : $mainLogin;

            $rows[] = [
                'id' => $newId,
                'name' => $name,
                'is_main' => '0',
                'main_id' => $mainId,
                'login_code' => $finalLogin,
                'role' => 'USER',
                'amount_paid' => '',
                'amount_subsided' => '',
            ];

            return [$header, $rows];
        }, ';');

        return $newId;
    }
}
