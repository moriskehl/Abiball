<?php
/**
 * Thin public routing layer for changing participant passwords
 * 
 * This file acts as a simple entry point that delegates all business logic
 * to the AdminController in the src folder. No real code logic exists here.
 */
require_once __DIR__ . '/../src/Controller/AdminController.php';

AdminController::changeParticipantPassword();
