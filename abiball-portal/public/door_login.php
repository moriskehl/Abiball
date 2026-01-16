<?php
declare(strict_types=1);

// public/door_login.php

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/Auth/DoorContext.php';
require_once __DIR__ . '/../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../src/Http/Response.php';
require_once __DIR__ . '/../src/View/Layout.php';
require_once __DIR__ . '/../src/View/Helpers.php';

Bootstrap::init();

// Wenn bereits eingeloggt → Dashboard
if (DoorContext::isDoor() && DoorContext::checkTimeout()) {
    Response::redirect('/door_dashboard.php');
}

$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $loginCode = trim((string)($_POST['login_code'] ?? ''));
    
    if ($loginCode === '') {
        $error = 'Login-Code erforderlich';
    } else {
        // Suche Person mit diesem Login-Code und Rolle DOOR
        $person = null;
        foreach (ParticipantsRepository::all() as $p) {
            $code = trim((string)($p['login_code'] ?? ''));
            $role = strtoupper(trim((string)($p['role'] ?? '')));
            
            if ($code === $loginCode && $role === 'DOOR') {
                $person = $p;
                break;
            }
        }
        
        if (!$person) {
            $error = 'Login-Code ungültig oder Zugriff nicht berechtigt';
        } else {
            // Erfolgreich authentifiziert
            $doorId = (string)($person['id'] ?? '');
            $doorName = (string)($person['name'] ?? 'Door');
            DoorContext::loginAsDoor($doorId, $doorName);
            Response::redirect('/door_dashboard.php');
        }
    }
}

Layout::header('Türkontrolle – Anmeldung');
?>
<main class="bg-starfield">
  <div class="container py-5" style="max-width: 560px;">

    <div class="text-center mx-auto" style="padding-top: 10px; padding-bottom: 18px;">
      <h1 class="h-serif mb-2" style="font-size: clamp(28px, 4vw, 44px); font-weight: 300;">
        🚪 Türkontrolle
      </h1>
      <p class="text-muted mb-0" style="line-height: 1.6;">
        Einlass-Login für Türpersonal.
      </p>
    </div>

    <div class="card">
      <div class="card-body p-4 p-md-5">

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger mb-4"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/door_login.php">
          <div class="mb-3">
            <label class="form-label">Login-Code</label>
            <input
              class="form-control"
              type="password"
              name="login_code"
              placeholder="••••••"
              autocomplete="off"
              required
            >
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-save" type="submit">Anmelden</button>
            <a class="btn btn-outline-secondary" href="/" style="border-radius: 12px;">Zur Landing Page</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</main>
<?php
Layout::footer();
?>

