<?php
declare(strict_types=1);

// src/Service/AdminPasswordService.php

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

/**
 * Service für sichere Admin-Passwortoperationen
 */
final class AdminPasswordService
{
    /**
     * Validiert und ändert das Passwort für einen Admin
     * 
     * @param string $adminId Die admin id
     * @param string $currentPassword Das aktuelle Passwort (Klartext)
     * @param string $newPassword Das neue Passwort (Klartext)
     * @param string $newPasswordConfirm Bestätigung des neuen Passworts
     * 
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function changePassword(
        string $adminId,
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirm
    ): array {
        $adminId = trim($adminId);
        
        // Validierung: Alle Felder gefüllt
        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
            return ['success' => false, 'error' => 'empty'];
        }

        // Validierung: Neue Passwörter stimmen überein
        if ($newPassword !== $newPasswordConfirm) {
            return ['success' => false, 'error' => 'match'];
        }

        // Validierung: Länge des neuen Passworts (6-64 Zeichen)
        $newPasswordLen = strlen($newPassword);
        if ($newPasswordLen < 6 || $newPasswordLen > 64) {
            return ['success' => false, 'error' => 'len'];
        }

        // Abrufen des Admins
        $admin = ParticipantsRepository::findById($adminId);
        if (!$admin || ($admin['role'] ?? 'USER') !== 'ADMIN') {
            return ['success' => false, 'error' => 'admin'];
        }

        // Aktuelles Passwort verifizieren
        $stored = (string)($admin['login_code'] ?? '');
        if ($stored === '' || $stored === '0') {
            return ['success' => false, 'error' => 'old'];
        }

        // Prüfen: Ist das Passwort gehashed oder Klartext?
        $isHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
        
        if ($isHashed) {
            $ok = password_verify($currentPassword, $stored);
        } else {
            $ok = hash_equals($stored, $currentPassword);
        }

        if (!$ok) {
            return ['success' => false, 'error' => 'old'];
        }

        // Neues Passwort hashen und speichern
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            return ['success' => false, 'error' => 'save'];
        }

        try {
            ParticipantsRepository::updateLoginCodeForId($adminId, $newHash);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('AdminPasswordService::changePassword error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'save'];
        }
    }
}
