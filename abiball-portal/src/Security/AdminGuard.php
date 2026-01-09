<?php
declare(strict_types=1);

require_once __DIR__ . '/../Bootstrap.php';

function requireAdmin(): void
{
    Bootstrap::init();

    if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: /admin_login.php');
        exit;
    }
}
