<?php
declare(strict_types=1);

// public/seating.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/SeatingController.php';

Bootstrap::init();

SeatingController::show();
