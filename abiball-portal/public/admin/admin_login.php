<?php
declare(strict_types=1);

// public/admin_login.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Controller/AdminController.php';

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    AdminController::login();
    exit;
}

if ($method === 'GET') {
    AdminController::showLoginForm();
    exit;
}

http_response_code(405);
exit('Method Not Allowed');
