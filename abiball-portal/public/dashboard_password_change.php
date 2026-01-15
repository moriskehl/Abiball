<?php
/**
 * Thin public routing layer for password change requests
 * 
 * This file acts as a simple entry point that delegates all business logic
 * to the DashboardController in the src folder. No real code logic exists here.
 */
require_once __DIR__ . '/../src/Controller/DashboardController.php';

DashboardController::changePassword();
