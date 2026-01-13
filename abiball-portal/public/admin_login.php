<?php
require_once __DIR__ . '/../src/Controller/AdminController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    AdminController::login();
} else {
    AdminController::showLoginForm();
}<?php
declare(strict_types=1);

// public/admin_login.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/AdminController.php';

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    AdminController::login();       // intern: CSRF + RateLimit + DB + password_verify
    exit;
}

if ($method === 'GET') {
    AdminController::showLoginForm();
    exit;
}

// Alles andere verbieten
http_response_code(405);
exit('Method Not Allowed');

