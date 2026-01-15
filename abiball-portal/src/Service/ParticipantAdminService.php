<?php
declare(strict_types=1);

// src/Service/ParticipantAdminService.php

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

/**
 * Service für Admin-Operationen auf Teilnehmern
 * Dient der Verwaltung von Gästen und Begleitern durch Administratoren
 */
final class ParticipantAdminService
{
    /**
     * Löscht einen Teilnehmer oder Begleiter
     * Wenn ein Hauptgast gelöscht wird, werden auch alle seine Begleiter gelöscht
     * 
     * @param string $participantId Die ID des zu löschenden Teilnehmers
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function deleteParticipant(string $participantId): array
    {
        $participantId = trim($participantId);
        
        if ($participantId === '') {
            return ['success' => false, 'error' => 'empty_id'];
        }

        // Prüfen ob Participant existiert
        $participant = ParticipantsRepository::findById($participantId);
        if (!$participant) {
            return ['success' => false, 'error' => 'not_found'];
        }

        try {
            ParticipantsRepository::deleteParticipantById($participantId);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('ParticipantAdminService::deleteParticipant error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'delete_failed'];
        }
    }

    /**
     * Ändert den Namen eines Teilnehmers
     * 
     * @param string $participantId Die ID des Teilnehmers
     * @param string $newName Der neue Name
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function editParticipantName(string $participantId, string $newName): array
    {
        $participantId = trim($participantId);
        $newName = trim($newName);
        
        if ($participantId === '') {
            return ['success' => false, 'error' => 'empty_id'];
        }

        if ($newName === '') {
            return ['success' => false, 'error' => 'empty_name'];
        }

        // Validierung: Länge des Namens
        if (strlen($newName) > 255) {
            return ['success' => false, 'error' => 'name_too_long'];
        }

        // Prüfen ob Participant existiert
        $participant = ParticipantsRepository::findById($participantId);
        if (!$participant) {
            return ['success' => false, 'error' => 'not_found'];
        }

        try {
            ParticipantsRepository::updateParticipantName($participantId, $newName);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('ParticipantAdminService::editParticipantName error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'update_failed'];
        }
    }
}
