<?php
declare(strict_types=1);

/**
 * ParticipantService - Geschäftslogik für Teilnehmerdaten
 * 
 * Stellt Methoden zum Abrufen von Hauptgästen und deren Begleitpersonen bereit.
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class ParticipantService
{
    /**
     * Lädt den Hauptgast und alle zugehörigen Begleitpersonen.
     */
    public static function getMainAndCompanions(string $mainId): array
    {
        return ParticipantsRepository::getGroupByMainId($mainId);
    }
}
