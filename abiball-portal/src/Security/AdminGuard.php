<?php
declare(strict_types=1);

// src/Security/AdminGuard.php

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Auth/AdminContext.php';

/**
 * Legacy-Guard-Funktion für Admin-Seiten.
 * Leitet intern auf AdminContext um (keine direkten $_SESSION-Zugriffe).
 */
function requireAdmin(): void
{
    Bootstrap::init();
    AdminContext::requireAdmin('/admin_login.php');
}
