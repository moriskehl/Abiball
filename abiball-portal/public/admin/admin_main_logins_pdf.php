<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Controller/AdminController.php';

Bootstrap::init();

// GET ist ok, weil Admin-Session geschützt ist.
// Optional: zusätzlich CSRF über POST möglich, aber für "PDF öffnen" ist GET üblich.
AdminController::printMainLoginsPdf();
