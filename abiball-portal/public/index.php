<?php
declare(strict_types=1);

// public/index.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/LandingController.php';

Bootstrap::init();

LandingController::show();
