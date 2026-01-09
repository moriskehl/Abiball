<?php
declare(strict_types=1);

// public/dashboard.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/DashboardController.php';

Bootstrap::init();

DashboardController::show();
