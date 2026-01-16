<?php
declare(strict_types=1);

// public/door_validate_ticket.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Auth/DoorContext.php';
require_once __DIR__ . '/../src/Security/TicketToken.php';
require_once __DIR__ . '/../src/Service/TicketValidationService.php';

Bootstrap::init();
DoorContext::requireDoor('/door_login.php');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pid = trim((string)($input['pid'] ?? ''));
$sig = (string)($input['sig'] ?? '');

if ($pid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ticket-ID erforderlich']);
    exit;
}

// Signatur prüfen
if (!TicketToken::verify($pid, $sig)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR-Code ungültig oder manipuliert']);
    exit;
}

// Entwertet Ticket
$doorPersonId = DoorContext::doorId();
$result = TicketValidationService::validateAndInvalidateTicket($pid, $doorPersonId);

if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(400);
}

echo json_encode($result);
exit;
