<?php
declare(strict_types=1);

// src/Security/SessionGuard.php (DB-ready, vereinheitlicht)
//
// Ziele:
// - canonical Session-Key: user_id (und optional group_id)
// - Backwards-Compatibility: akzeptiert noch main_id/participant_id während Migration
// - zentrale Redirects + sichere Statuscodes
// - optionale Guards: requireGuest(), requireRole(), requireAdmin()

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Http/Response.php';

function requireLogin(): void
{
    Bootstrap::init();

    // Canonical (neu)
    $userId = (string)($_SESSION['user_id'] ?? '');

    // Migration-Fallback (alt)
    if ($userId === '') {
        $userId = (string)($_SESSION['main_id'] ?? $_SESSION['participant_id'] ?? '');
        if ($userId !== '') {
            // Session beim ersten Treffer migrieren
            $_SESSION['user_id'] = $userId;
        }
    }

    if ($userId === '') {
        Response::redirect('/login.php');
    }
}

function currentUserId(): string
{
    Bootstrap::init();
    return (string)($_SESSION['user_id'] ?? $_SESSION['main_id'] ?? $_SESSION['participant_id'] ?? '');
}

function currentRole(): string
{
    Bootstrap::init();
    $r = strtoupper((string)($_SESSION['role'] ?? 'USER'));
    return $r !== '' ? $r : 'USER';
}

function requireRole(string $role): void
{
    requireLogin();
    $need = strtoupper(trim($role));
    $have = currentRole();

    if ($need !== $have) {
        http_response_code(403);
        exit('Forbidden');
    }
}
