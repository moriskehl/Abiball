<?php
declare(strict_types=1);

// src/Security/SessionGuardguard.php
require_once __DIR__ . '/../Bootstrap.php';

function requireLogin(): void
{
    Bootstrap::init();

    if (empty($_SESSION['main_id'])) {
        header('Location: /login.php');
        exit;
    }
}
