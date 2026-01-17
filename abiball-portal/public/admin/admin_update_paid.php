<?php
declare(strict_types=1);

// public/admin_update_paid.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Controller/AdminController.php';

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

AdminController::updatePaid();
