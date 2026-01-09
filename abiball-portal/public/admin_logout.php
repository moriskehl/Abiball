<?php
declare(strict_types=1);

// public/admin_logout.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/AdminController.php';

Bootstrap::init();

AdminController::logout();
exit;
