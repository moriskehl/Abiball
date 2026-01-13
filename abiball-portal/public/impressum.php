<?php
declare(strict_types=1);

// public/impressum.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/ImpressumController.php';

Bootstrap::init();
ImpressumController::show();
