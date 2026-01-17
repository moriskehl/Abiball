<?php
/**
 * Login-Seite - Anmeldung mit persönlichem Code
 */
require_once __DIR__ . '/../src/Controller/AuthController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    AuthController::login();
} else {
    AuthController::showLoginForm();
}
