<?php
declare(strict_types=1);

// public/logout.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/AuthController.php';

Bootstrap::init();

AuthController::logout();
exit;
