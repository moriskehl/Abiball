<?php
declare(strict_types=1);

// public/dashboard_notes_save.php
//
// Ziel (DB-kompatibel):
// - Ownership strikt serverseitig prüfen (nur Notizen innerhalb der eigenen main_id)
// - CSRF Pflicht
// - Method enforcement (POST only)
// - Optional: RateLimit gegen Spam
// - Keine Daten aus Session "main_id" als Quelle der Wahrheit; stattdessen canonical user_id
//
// Voraussetzungen:
// - SessionGuard setzt/erwartet $_SESSION['user_id'] (canonical) und optional role
// - ParticipantService/SeatingService sind DB-kompatibel ODER werden ersetzt durch Repos
//
// Wenn du Seat/Notes in DB speichern willst:
// -> SeatingService::updatePersonNote($mainId, $pid, $note) muss DB schreiben (notes Tabelle/Spalte)

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Security/SessionGuard.php';
require_once __DIR__ . '/../src/Security/Csrf.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';

require_once __DIR__ . '/../src/Http/Request.php';
require_once __DIR__ . '/../src/Http/Response.php';

require_once __DIR__ . '/../src/Service/ParticipantService.php';
require_once __DIR__ . '/../src/Service/SeatingService.php';

Bootstrap::init();
requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::redirect('/dashboard.php');
}

// CSRF
if (!Csrf::validate($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

// Canonical session key (Fallback auf altes main_id während Migration)
$sessionUserId = (string)($_SESSION['user_id'] ?? $_SESSION['main_id'] ?? '');
if ($sessionUserId === '') {
    http_response_code(403);
    exit('Nicht erlaubt.');
}

// Optionales RateLimit gegen massives Spammen von Notes
$ip = Request::ip();
$rlKey = 'notes_' . $ip . '_' . $sessionUserId;
if (!RateLimiter::allow($rlKey, 30, 60)) {
    http_response_code(429);
    exit('Zu viele Anfragen.');
}

// Input normalisieren
$pid  = trim((string)($_POST['pid'] ?? ''));
$note = (string)($_POST['note'] ?? '');

// Note-Constraints (DB/UX/Sicherheit)
$note = trim($note);
if (mb_strlen($note, 'UTF-8') > 500) {
    $note = mb_substr($note, 0, 500, 'UTF-8');
}

if ($pid === '') {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

// Ownership prüfen: pid muss zu dieser main_id gehören
// DB-kompatibel: ParticipantService muss das über DB lösen (nicht CSV)
$grp = ParticipantService::getMainAndCompanions($sessionUserId);

$allowed = [];
if (!empty($grp['main'])) {
    $allowed[(string)($grp['main']['id'] ?? '')] = true;
}
foreach (($grp['companions'] ?? []) as $c) {
    $allowed[(string)($c['id'] ?? '')] = true;
}

if (!isset($allowed[$pid])) {
    http_response_code(403);
    exit('Nicht erlaubt.');
}

// DB-Write (SeatingService muss DB schreiben, z.B. Tabelle person_notes oder seating_members.note)
SeatingService::updatePersonNote($sessionUserId, $pid, $note);

// Redirect back
Response::redirect('/dashboard.php');
