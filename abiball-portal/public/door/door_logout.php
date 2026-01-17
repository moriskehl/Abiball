<?php
declare(strict_types=1);

// public/door_logout.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Auth/DoorContext.php';
require_once __DIR__ . '/../../src/Http/Response.php';

Bootstrap::init();

DoorContext::logout();
Response::redirect('/door/door_login.php');
