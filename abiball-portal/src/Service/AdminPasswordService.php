<?php
declare(strict_types=1);

/**
 * AdminPasswordService - Passwortänderung für Administratoren
 * 
 * Ermöglicht Admins das sichere Ändern ihres eigenen Passworts.
 * Prüft zusätzlich die Admin-Rolle vor der Änderung.
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class AdminPasswordService
{
    /**
     * Ändert das Passwort eines Administrators nach Verifizierung.
     */
    public static function changePassword(
        string $adminId,
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirm
    ): array {
        $adminId = trim($adminId);
        
        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
            return ['success' => false, 'error' => 'empty'];
        }

        if ($newPassword !== $newPasswordConfirm) {
            return ['success' => false, 'error' => 'match'];
        }

        $newPasswordLen = strlen($newPassword);
        if ($newPasswordLen < 6 || $newPasswordLen > 64) {
            return ['success' => false, 'error' => 'len'];
        }

        $admin = ParticipantsRepository::findById($adminId);
        if (!$admin || ($admin['role'] ?? 'USER') !== 'ADMIN') {
            return ['success' => false, 'error' => 'admin'];
        }

        $stored = (string)($admin['login_code'] ?? '');
        if ($stored === '' || $stored === '0') {
            return ['success' => false, 'error' => 'old'];
        }

        // Das gespeicherte Passwort kann gehasht oder noch im Klartext vorliegen
        $isHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
        
        if ($isHashed) {
            $ok = password_verify($currentPassword, $stored);
        } else {
            $ok = hash_equals($stored, $currentPassword);
        }

        if (!$ok) {
            return ['success' => false, 'error' => 'old'];
        }

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
