<?php
declare(strict_types=1);

/**
 * SessionGuard - Legacy-Hilfsfunktion für Login-Check
 * 
 * Wrapper um AuthContext::requireLogin() für Abwärtskompatibilität.
 */

require_once __DIR__ . '/../Auth/AuthContext.php';

function requireLogin(): void
{
    AuthContext::requireLogin('/login.php');
}
