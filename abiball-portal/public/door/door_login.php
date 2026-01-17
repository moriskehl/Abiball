<?php
/**
 * Türkontrolle Login - Anmeldung für das Einlasspersonal
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Auth/DoorContext.php';
require_once __DIR__ . '/../../src/Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../../src/Http/Response.php';
require_once __DIR__ . '/../../src/Http/Request.php';
require_once __DIR__ . '/../../src/Security/Csrf.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/View/Layout.php';
require_once __DIR__ . '/../../src/View/Helpers.php';

Bootstrap::init();

// Wenn bereits eingeloggt → Dashboard
if (DoorContext::isDoor() && DoorContext::checkTimeout()) {
    Response::redirect('/door/door_dashboard.php');
}

$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // SECURITY: CSRF Validation
    if (!Csrf::validate(Request::postString('_csrf'))) {
        $error = 'Ungültige Anfrage (CSRF). Seite neu laden und erneut versuchen.';
    } else {
        // SECURITY: Rate Limiting
        $ipKey = 'door_login_' . Request::ip();
        if (!RateLimiter::allow($ipKey, 8, 60)) {
            $error = 'Zu viele Versuche. Bitte 1 Minute warten.';
        } else {
            $loginCode = trim((string)($_POST['login_code'] ?? ''));
            
            if ($loginCode === '') {
                $error = 'Login-Code erforderlich';
            } else {
                // Suche Person mit Rolle DOOR und prüfe Passwort
                $person = null;
                foreach (ParticipantsRepository::all() as $p) {
                    $storedCode = trim((string)($p['login_code'] ?? ''));
                    $role = strtoupper(trim((string)($p['role'] ?? '')));
                    
                    if ($role !== 'DOOR') {
                        continue;
                    }
                    
                    // Prüfe ob Hash (bcrypt) oder Klartext
                    $isHashed = str_starts_with($storedCode, '$2y$');
                    $passwordOk = $isHashed 
                        ? password_verify($loginCode, $storedCode)
                        : hash_equals($storedCode, $loginCode);
                    
                    if ($passwordOk) {
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
                    Response::redirect('/door/door_dashboard.php');
                }
            }
        }
    }
}

Layout::header('Türkontrolle – Anmeldung');
?>
<main class="bg-starfield">
  <div class="container py-5" style="max-width: 560px;">

    <div class="text-center mx-auto" style="padding-top: 18px; padding-bottom: 24px;">
      <h1 class="h-serif mb-2" style="font-size: clamp(32px, 4.2vw, 48px); font-weight: 300;">
        Türkontrolle
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

        <form method="post" action="/door/door_login.php">
          <?= Csrf::inputField() ?>
          
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
            <a class="btn btn-outline-secondary" href="/">Zur Landing Page</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</main>
<?php
Layout::footer();
?>

