<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
Bootstrap::init();

require_once __DIR__ . '/../src/Controller/SeatingController.php';

SeatingController::save();
