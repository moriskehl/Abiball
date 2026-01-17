<?php
declare(strict_types=1);

/**
 * ParticipantAdminService - Admin-Operationen für Teilnehmerverwaltung
 * 
 * Ermöglicht Administratoren das Löschen, Umbenennen und
 * Passwort-Zurücksetzen von Gästen und Begleitpersonen.
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class ParticipantAdminService
{
    /**
     * Löscht einen Teilnehmer oder Begleiter.
     * Bei Hauptgästen werden auch alle Begleitpersonen entfernt.
     */
    public static function deleteParticipant(string $participantId): array
    {
        $participantId = trim($participantId);
        
        if ($participantId === '') {
            return ['success' => false, 'error' => 'empty_id'];
        }

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
     * Ändert den Namen eines Teilnehmers.
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

        if (strlen($newName) > 255) {
            return ['success' => false, 'error' => 'name_too_long'];
        }

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

    /**
     * Setzt das Login-Passwort eines Teilnehmers zurück.
     * Das Passwort wird als Klartext gespeichert und beim nächsten Login automatisch gehasht.
     */
    public static function changeParticipantPassword(string $participantId, string $newPassword): array
    {
        $participantId = trim($participantId);
        $newPassword = trim($newPassword);
        
        if ($participantId === '') {
            return ['success' => false, 'error' => 'empty_id'];
        }

        if ($newPassword === '') {
            return ['success' => false, 'error' => 'empty_password'];
        }

        if (strlen($newPassword) < 4) {
            return ['success' => false, 'error' => 'password_too_short'];
        }

        if (strlen($newPassword) > 255) {
            return ['success' => false, 'error' => 'password_too_long'];
        }

        $participant = ParticipantsRepository::findById($participantId);
        if (!$participant) {
            return ['success' => false, 'error' => 'not_found'];
        }

        try {
            ParticipantsRepository::updateLoginCodeForId($participantId, $newPassword);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('ParticipantAdminService::changeParticipantPassword error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'update_failed'];
        }
    }
}
