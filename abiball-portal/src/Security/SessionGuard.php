<?php
declare(strict_types=1);

require_once __DIR__ . '/../Auth/AuthContext.php';

function requireLogin(): void
{
    AuthContext::requireLogin('/login.php');
}
