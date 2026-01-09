<?php
declare(strict_types=1);

// public/zahlung.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Controller/ZahlungController.php';

Bootstrap::init();

ZahlungController::show();
