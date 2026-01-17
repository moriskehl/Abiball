<?php
declare(strict_types=1);

/**
 * PricingService - Preisberechnung für Tickets
 * 
 * Ermittelt individuelle Ticketpreise unter Berücksichtigung von
 * Sonderkonditionen (z.B. Lehrer, Ehemalige mit reduziertem Preis).
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Repository/PricingOverridesRepository.php';

final class PricingService
{
    public const DEFAULT_TICKET_PRICE = 17;

    /**
     * Ermittelt den Ticketpreis für einen einzelnen Teilnehmer.
     * Prüft zunächst auf individuelle Preisüberschreibungen.
     */
    public static function ticketForParticipantId(string $participantId): array
    {
        $participantId = trim($participantId);
        if ($participantId === '') {
            return ['price' => self::DEFAULT_TICKET_PRICE, 'reason' => ''];
        }

        $override = PricingOverridesRepository::getOverrideForId($participantId);
        if ($override !== null) {
            $price = (int)($override['ticket_price'] ?? self::DEFAULT_TICKET_PRICE);
            $reason = trim((string)($override['reason'] ?? ''));
            return ['price' => $price, 'reason' => $reason];
        }

        return ['price' => self::DEFAULT_TICKET_PRICE, 'reason' => ''];
    }

    /**
     * Berechnet den Gesamtbetrag für einen Hauptgast inkl. aller Begleitpersonen.
     */
    public static function amountDueForMainId(string $mainId): array
    {
        $mainId = trim($mainId);

        $group = ParticipantsRepository::getGroupByMainId($mainId);
        $main = $group['main'];
        $companions = $group['companions'];

        $billableIds = [];

        if ($main) {
            $billableIds[] = (string)($main['id'] ?? '');
        }
        foreach ($companions as $c) {
            $billableIds[] = (string)($c['id'] ?? '');
        }

        $billableIds = array_values(array_filter($billableIds, static fn($v) => trim((string)$v) !== ''));

        $sum = 0;
        foreach ($billableIds as $id) {
            $t = self::ticketForParticipantId($id);
            $sum += (int)$t['price'];
        }

        return ['amount_due' => $sum, 'billable_ids' => $billableIds];
    }
}
