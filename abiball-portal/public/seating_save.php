<?php
declare(strict_types=1);

// public/seating_save.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/SeatingController.php';

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

SeatingController::save(); // intern: requireLogin + CSRF + Ownership + DB-Transaction
exit;
