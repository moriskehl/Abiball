<?php
declare(strict_types=1);

// public/dashboard_notes_save.php
require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Security/SessionGuard.php';
require_once __DIR__ . '/../src/Security/Csrf.php';
require_once __DIR__ . '/../src/Service/ParticipantService.php';
require_once __DIR__ . '/../src/Service/SeatingService.php';

Bootstrap::init();
requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

if (!Csrf::validate($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo 'CSRF ungültig.';
    exit;
}

$mainId = (string)($_SESSION['main_id'] ?? '');
$pid    = trim((string)($_POST['pid'] ?? ''));
$note   = (string)($_POST['note'] ?? '');

$grp = ParticipantService::getMainAndCompanions($mainId);
$allowed = [];
if (!empty($grp['main'])) $allowed[(string)$grp['main']['id']] = true;
foreach ($grp['companions'] as $c) $allowed[(string)$c['id']] = true;

if ($pid === '' || !isset($allowed[$pid])) {
    http_response_code(403);
    echo 'Nicht erlaubt.';
    exit;
}

SeatingService::updatePersonNote($mainId, $pid, $note);

header('Location: /dashboard.php');
exit;
