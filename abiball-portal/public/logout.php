<?php
/**
 * Logout - Beendet die aktuelle Sitzung
 */
require_once __DIR__ . '/../src/Controller/AuthController.php';

AuthController::logout();
