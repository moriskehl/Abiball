<?php
declare(strict_types=1);

/**
 * PasswordService - Passwortänderung für Hauptgäste
 * 
 * Ermöglicht eingeloggten Gästen das sichere Ändern ihres Passworts.
 * Unterstützt sowohl gehashte als auch Klartext-Passwörter für die Verifizierung.
 */

require_once __DIR__ . '/../Repository/ParticipantsRepository.php';

final class PasswordService
{
    /**
     * Ändert das Passwort eines Hauptgastes nach Verifizierung des aktuellen Passworts.
     */
    public static function changePassword(
        string $mainId,
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirm
    ): array {
        $mainId = trim($mainId);
        
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

        $main = ParticipantsRepository::mainRowForMainId($mainId);
        if (!$main) {
            return ['success' => false, 'error' => 'main'];
        }

        $stored = (string)($main['login_code'] ?? '');
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
            ParticipantsRepository::updateLoginCodeForMainId($mainId, $newHash);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            error_log('PasswordService::changePassword error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'save'];
        }
    }
}
