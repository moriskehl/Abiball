<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Controller/AdminController.php';

Bootstrap::init();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

AdminController::deleteOverride();
