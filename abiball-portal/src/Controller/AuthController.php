<?php
declare(strict_types=1);

// src/Controller/AuthController.php (DB-ready, gehärtet)
//
// Änderungen ggü. CSV-Version:
// - ParticipantsRepository -> UserRepository (DB)
// - login_code Klartext -> password_hash + password_verify
// - einheitliche Session-Keys: user_id, role, display_name, ticket_count
// - Fehlermeldungen vereinheitlicht (keine Enumeration)
// - optional: password_needs_rehash
//
// Voraussetzungen (neu/DB):
// - src/Repository/UserRepository.php
//   - findMainByIdOrName(string $identifier): ?array {id, name, main_id, role, password_hash}
//   - ticketCountForMainId(string $mainId): int
//   - resolveMainIdFromRow(array $row): string (oder direkt main_id Feld sauber pflegen)
//   - updatePasswordHash(string $userId, string $hash): void
// - Bootstrap, Csrf, RateLimiter, Request, Response, Layout, Helpers wie gehabt

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/UserRepository.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class AuthController
{
    public static function showLoginForm(string $error = '', string $identifier = ''): void
    {
        Bootstrap::init();

        // Wenn bereits eingeloggt: direkt Dashboard
        if (!empty($_SESSION['user_id'])) {
            Response::redirect('/dashboard.php');
        }

        Layout::header('Abiball – Login');
        ?>
        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 1100px;">

            <div class="text-center mx-auto" style="max-width: 760px; padding-top: 14px; padding-bottom: 18px;">
              <h1 class="h-serif mb-3" style="font-size: clamp(34px, 4.2vw, 56px); font-weight: 300; line-height: 1.05;">
                Gästelogin
              </h1>

              <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.7;">
                Melde dich mit Hauptgast-ID oder Name und deinem Passwort an, um Tickets, Sitzplätze und Status im Dashboard einzusehen.
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
                      inputmode="text"
                    >
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Passwort</label>
                    <input
                      class="form-control"
                      type="password"
                      name="password"
                      required
                      autocomplete="current-password"
                    >
                  </div>

                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-save" type="submit">Einloggen</button>
                    <a class="btn btn-outline-secondary" href="/" style="border-radius: 12px;">Zur Landing Page</a>
                  </div>

                  <hr class="my-4">

                  <div class="text-muted small" style="line-height: 1.6;">
                    Hinweis: Das Passwort wird vom Organisationsteam vergeben. Bitte prüfe Groß-/Kleinschreibung und Sonderzeichen.
                  </div>
                </form>

              </div>
            </div>

          </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function login(): void
    {
        Bootstrap::init();

        if (!empty($_SESSION['user_id'])) {
            Response::redirect('/dashboard.php');
        }

        // RateLimit: User-Login moderat
        $ipKey = 'login_' . Request::ip();
        if (!RateLimiter::allow($ipKey, 10, 60)) {
            self::showLoginForm('Zu viele Versuche. Bitte kurz warten.');
            return;
        }

        if (!Csrf::validate(Request::postString('_csrf'))) {
            self::showLoginForm('Ungültige Anfrage. Bitte Seite neu laden.', Request::postString('identifier'));
            return;
        }

        $identifier = Request::postString('identifier');
        $password   = (string)($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            self::showLoginForm('Login fehlgeschlagen.', $identifier);
            return;
        }

        // DB Lookup: Hauptgast (USER) anhand ID oder Name
        $user = UserRepository::findMainByIdOrName($identifier);

        // Keine Enumeration: gleiche Fehlermeldung
        if (!$user) {
            self::showLoginForm('Login fehlgeschlagen.', $identifier);
            return;
        }

        $hash = (string)($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            self::showLoginForm('Login fehlgeschlagen.', $identifier);
            return;
        }

        // Optional: Rehash bei geänderter Policy
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            UserRepository::updatePasswordHash((string)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        // Session setzen
        session_regenerate_id(true);

        // Canonical: mainId = Group Identifier
        $mainId = (string)UserRepository::resolveMainIdFromRow($user);

        // Ticketanzahl: Gruppengröße
        $ticketCount = (int)UserRepository::ticketCountForMainId($mainId);

        $_SESSION['user_id']      = $mainId; // canonical "Hauptgast" / group owner
        $_SESSION['role']         = (string)($user['role'] ?? 'USER');
        $_SESSION['display_name'] = (string)($user['name'] ?? '');
        $_SESSION['ticket_count'] = max(1, $ticketCount);

        // Backwards-Compatibility (nur während Migration)
        $_SESSION['main_id']         = $mainId;
        $_SESSION['participant_id']  = $mainId;
        $_SESSION['participant_name']= (string)($user['name'] ?? '');
        $_SESSION['guest_id']        = (string)($user['id'] ?? '');
        $_SESSION['guest_name']      = (string)($user['name'] ?? '');

        Response::redirect('/dashboard.php');
    }

    public static function logout(): void
    {
        Bootstrap::init();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'] ?? '/',
                $p['domain'] ?? '',
                (bool)($p['secure'] ?? false),
                (bool)($p['httponly'] ?? true)
            );
        }

        session_destroy();

        Response::redirect('/login.php');
    }
}
