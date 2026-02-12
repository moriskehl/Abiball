<?php

declare(strict_types=1);

/**
 * AuthController - Authentifizierung für Gäste
 * 
 * Verarbeitet Login, Logout und zeigt das Login-Formular an.
 * Unterstützt gehashte und Klartext-Passwörter für die Migration.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';
require_once __DIR__ . '/../Auth/AuthContext.php';

final class AuthController
{
  /**
   * Zeigt das Login-Formular an.
   */
  public static function showLoginForm(string $error = '', string $identifier = ''): void
  {
    Bootstrap::init();

    // Bereits eingeloggte Nutzer direkt ins Dashboard weiterleiten
    if (AuthContext::mainId() !== '') {
      Response::redirect('/dashboard.php');
    }

    Layout::header('Abiball – Login');
?>
    <main class="bg-starfield" id="main-content">
      <!-- Star layers -->
      <div class="stars-layer-1" aria-hidden="true"></div>
      <div class="stars-layer-2" aria-hidden="true"></div>
      <div class="stars-layer-3" aria-hidden="true"></div>

      <div class="container py-5" style="max-width: 1100px;">

        <div class="glass-hero-header sm mb-5 animate-fade-up text-center mx-auto" style="max-width: 560px;">
          <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
            Gästelogin
          </h1>

          <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.7;">
            Melde dich mit Hauptgast-ID oder Name und deinem Login-Code an, um Tickets, Sitzplätze und Status im Dashboard einzusehen.
          </p>
        </div>

        <div class="card mx-auto" style="max-width: 560px;">
          <div class="card-body p-4 p-md-5">

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger mb-4"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php" autocomplete="on">
              <?= Csrf::inputField() ?>

              <div class="mb-3">
                <label class="form-label">Name oder Hauptgast-ID</label>
                <input
                  class="form-control"
                  name="identifier"
                  value="<?= e($identifier) ?>"
                  required
                  autocomplete="username"
                  inputmode="text">
              </div>

              <div class="mb-4">
                <label class="form-label">Passwort (Login-Code)</label>
                <input
                  class="form-control"
                  type="password"
                  name="password"
                  required
                  autocomplete="current-password">
              </div>

              <div class="d-grid gap-3">
                <button class="btn btn-cta btn-cta-lg" type="submit">Einloggen</button>
                <a class="btn btn-ghost text-muted" href="/">Zurück zur Startseite</a>
              </div>

              <div class="text-muted small" style="line-height: 1.6;">
                Hinweis: Der Login-Code wird vom Organisationsteam vergeben. Bitte prüfe Groß-/Kleinschreibung und Sonderzeichen.
              </div>
            </form>

          </div>
        </div>

      </div>
    </main>
<?php
    Layout::footer();
  }

  /**
   * Verarbeitet den Login-Vorgang mit Rate-Limiting und Passwort-Validierung.
   */
  public static function login(): void
  {
    Bootstrap::init();

    // Bereits eingeloggte Nutzer direkt ins Dashboard weiterleiten
    if (AuthContext::mainId() !== '') {
      Response::redirect('/dashboard.php');
    }

    $ipKey = 'login_' . Request::ip();
    if (!RateLimiter::allow($ipKey, 10, 60)) {
      self::showLoginForm('Zu viele Versuche. Bitte 1 Minute warten.');
      return;
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      self::showLoginForm('Ungültige Anfrage (CSRF). Seite neu laden und erneut versuchen.');
      return;
    }

    $identifier = Request::postString('identifier');
    $password   = Request::postString('password');

    if ($identifier === '' || $password === '') {
      self::showLoginForm('Bitte Name/ID und Passwort eingeben.', $identifier);
      return;
    }

    $user = ParticipantsRepository::findMainByIdOrName($identifier);
    if (!$user) {
      self::showLoginForm('Kein Hauptgast gefunden.', $identifier);
      return;
    }

    $stored = (string)($user['login_code'] ?? '');
    if ($stored === '' || $stored === '0') {
      self::showLoginForm('Login-Code fehlt in CSV.', $identifier);
      return;
    }

    $input = $password;

    // Sowohl gehashte als auch Klartext-Passwörter akzeptieren (für Migration)
    $isHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
    $ok = $isHashed ? password_verify($input, $stored) : hash_equals($stored, $input);

    if (!$ok) {
      self::showLoginForm('Login-Code ist falsch.', $identifier);
      return;
    }

    if (!$isHashed) {
      $newHash = password_hash($input, PASSWORD_DEFAULT);
      if ($newHash !== false) {
        try {
          ParticipantsRepository::updateLoginCodeForMainId((string)($user['main_id'] ?? ''), $newHash);
        } catch (Throwable $e) {
        }
      }
    }

    // Session starten und Benutzer anmelden
    AuthContext::loginAsMain($user);

    // Bei erstem Login Hinweis zur Passwortänderung anzeigen
    $mainId = ParticipantsRepository::resolveMainIdFromRow($user);
    $changed = ParticipantsRepository::isPasswordChangedForMainId($mainId);
    if (!$changed) {
      $_SESSION['show_pw_prompt'] = 1;
    }

    Response::redirect('/dashboard.php');
  }

  /**
   * Meldet den Benutzer ab und leitet zur Login-Seite weiter.
   */
  public static function logout(): void
  {
    AuthContext::logout('/login.php');
  }
}
