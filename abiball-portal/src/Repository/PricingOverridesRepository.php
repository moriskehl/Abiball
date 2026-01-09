<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/CsvRepository.php';

final class PricingOverridesRepository
{
    /** @return array<string,array{ticket_price:int,reason:string}> */
    public static function mapById(): array
    {
        $path = __DIR__ . '/../../storage/data/pricing_overrides.csv';
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
        $id = trim((string)$id);
        if ($id === '') return null;

        $map = self::mapById();
        return isset($map[$id]) ? (int)$map[$id]['ticket_price'] : null;
    }

    public static function getReasonForId(string $id): ?string
    {
        $id = trim((string)$id);
        if ($id === '') return null;

        $map = self::mapById();
        if (!isset($map[$id])) return null;

        $reason = trim((string)($map[$id]['reason'] ?? ''));
        return $reason !== '' ? $reason : null;
    }

    /** @return array{ticket_price:int,reason:string}|null */
    public static function getOverrideForId(string $id): ?array
    {
        $id = trim((string)$id);
        if ($id === '') return null;

        $map = self::mapById();
        return isset($map[$id]) ? $map[$id] : null;
    }
}
