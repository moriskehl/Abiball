<?php
declare(strict_types=1);

// public/login.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/AuthController.php';

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    AuthController::login();        // intern: CSRF + RateLimit + DB + password_verify
    exit;
}

if ($method === 'GET') {
    AuthController::showLoginForm();
    exit;
}

// Alles andere verbieten
http_response_code(405);
exit('Method Not Allowed');
