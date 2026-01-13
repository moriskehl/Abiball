<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/CsvRepository.php';

final class PricingOverridesRepository
{
    /** @return array<string,array{ticket_price:int,reason:string}> */
    public static function mapById(): array
    {
        $path = Config::pricingOverridesCsvPath();
        if (!is_file($path)) return [];

        $rows = CsvRepository::readAssoc($path, ';');
        $map = [];

        foreach ($rows as $r) {
            $id = trim((string)($r['id'] ?? ''));
            if ($id === '') continue;

            $priceRaw = trim((string)($r['ticket_price'] ?? ''));
            $price = is_numeric($priceRaw) ? (int)round((float)$priceRaw) : 0;

            $reason = trim((string)($r['reason'] ?? ''));

            $map[$id] = ['ticket_price' => $price, 'reason' => $reason];
        }

        return $map;
    }

    public static function getTicketPriceForId(string $id): ?int
    {
        $id = trim($id);
        if ($id === '') return null;

        $map = self::mapById();
        return isset($map[$id]) ? (int)$map[$id]['ticket_price'] : null;
    }

    public static function getReasonForId(string $id): ?string
    {
        $id = trim($id);
        if ($id === '') return null;

        $map = self::mapById();
        if (!isset($map[$id])) return null;

        $reason = trim((string)($map[$id]['reason'] ?? ''));
        return $reason !== '' ? $reason : null;
    }

    /** @return array{ticket_price:int,reason:string}|null */
    public static function getOverrideForId(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') return null;

        $map = self::mapById();
        return $map[$id] ?? null;
    }

    public static function upsertOverrideForId(string $id, int $ticketPrice, string $reason): void
    {
        $id = trim($id);
        if ($id === '') throw new InvalidArgumentException('id missing');

        if ($ticketPrice < 0) $ticketPrice = 0;
        $reason = trim($reason);

        $path = Config::pricingOverridesCsvPath();

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($id, $ticketPrice, $reason): array {
            // Standard-Header sicherstellen (auch falls Datei leer/neu)
            if (count($header) === 0) {
                $header = ['id', 'ticket_price', 'reason'];
            } else {
                if (!in_array('id', $header, true)) $header[] = 'id';
                if (!in_array('ticket_price', $header, true)) $header[] = 'ticket_price';
                if (!in_array('reason', $header, true)) $header[] = 'reason';
            }

            $found = false;
            foreach ($rows as &$r) {
                if (trim((string)($r['id'] ?? '')) !== $id) continue;
                $r['ticket_price'] = (string)$ticketPrice;
                $r['reason'] = $reason;
                $found = true;
                break;
            }
            unset($r);

            if (!$found) {
                $rows[] = [
                    'id' => $id,
                    'ticket_price' => (string)$ticketPrice,
                    'reason' => $reason,
                ];
            }

            return [$header, $rows];
        }, ';');
    }

    public static function deleteOverrideForId(string $id): void
    {
        $id = trim($id);
        if ($id === '') return;

        $path = Config::pricingOverridesCsvPath();

        CsvRepository::updateAssocAtomic($path, static function (array $header, array $rows) use ($id): array {
            if (count($header) === 0) {
                $header = ['id', 'ticket_price', 'reason'];
            }
            $rows = array_values(array_filter(
                $rows,
                static fn($r) => trim((string)($r['id'] ?? '')) !== $id
            ));
            return [$header, $rows];
        }, ';');
    }
}
