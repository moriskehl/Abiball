<?php
require_once __DIR__ . '/../src/Controller/AdminController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    AdminController::login();
} else {
    AdminController::showLoginForm();
}
