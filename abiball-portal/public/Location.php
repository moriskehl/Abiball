<?php
declare(strict_types=1);

// public/location.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/LocationController.php';

Bootstrap::init();

LocationController::show();
