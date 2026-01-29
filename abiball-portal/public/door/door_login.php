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
  <!-- Star layers -->
  <div class="stars-layer-1"></div>
  <div class="stars-layer-2"></div>
  <div class="stars-layer-3"></div>

  <div class="container py-5" style="max-width: 1100px;">

    <div class="glass-hero-header sm mb-5 animate-fade-up text-center mx-auto" style="max-width: 560px;">
      <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
        Türkontrolle
      </h1>

      <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.7;">
        Einlass-Login für Türpersonal. Bitte melde dich mit deinem persönlichen Login-Code an.
      </p>
    </div>

    <div class="card mx-auto" style="max-width: 560px;">
      <div class="card-body p-4 p-md-5">

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger mb-4"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/door/door_login.php">
          <?= Csrf::inputField() ?>
          
          <div class="mb-4">
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

          <div class="d-grid gap-3">
            <button class="btn btn-cta btn-cta-lg" type="submit">Anmelden</button>
            <a class="btn btn-ghost text-muted" href="/">Zurück zur Startseite</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</main>
<?php
Layout::footer();
?>

