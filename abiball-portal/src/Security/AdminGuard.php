<?php
declare(strict_types=1);

/**
 * AdminGuard - Legacy-Hilfsfunktion für Admin-Check
 * 
 * Wrapper um AdminContext::requireAdmin() für Abwärtskompatibilität.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Auth/AdminContext.php';

function requireAdmin(): void
{
    Bootstrap::init();
    AdminContext::requireAdmin('/admin/admin_login.php');
}
