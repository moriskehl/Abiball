<?php
declare(strict_types=1);

// src/Controller/AuthController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class AuthController
{
    public static function showLoginForm(string $error = '', string $identifier = ''): void
    {
        Bootstrap::init();

        Layout::header('Abiball – Login');
        ?>
        <main class="bg-starfield">
          <div class="container py-5" style="max-width: 1100px;">

            <div class="text-center mx-auto" style="max-width: 760px; padding-top: 14px; padding-bottom: 18px;">
              <h1 class="h-serif mb-3" style="font-size: clamp(34px, 4.2vw, 56px); font-weight: 300; line-height: 1.05;">
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
                      inputmode="text"
                    >
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Passwort (Login-Code)</label>
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

    public static function login(): void
    {
        Bootstrap::init();

        // Wenn schon eingeloggt (neuer und alter Key)
        if (!empty($_SESSION['main_id']) || !empty($_SESSION['participant_id'])) {
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
        $password = (string)($_POST['password'] ?? '');

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
            self::showLoginForm('Für diesen Hauptgast ist kein Login-Code gesetzt.', $identifier);
            return;
        }

        if (!hash_equals($stored, $password)) {
            self::showLoginForm('Passwort ist falsch.', $identifier);
            return;
        }

        // ----------------------------
        // Session setzen (WICHTIG)
        // ----------------------------
        session_regenerate_id(true);

        // main_id robust bestimmen (für Hauptgast ist main_id meist = id, aber nicht garantiert)
        $mainId = ParticipantsRepository::resolveMainIdFromRow($user);

        // Ticketanzahl: Gruppengröße (Hauptgast + Begleiter) als sinnvoller Default
        $ticketCount = ParticipantsRepository::ticketCountForMainId($mainId);

        // neue Keys (für ZahlungController)
        $_SESSION['participant_id']   = $mainId;
        $_SESSION['participant_name'] = (string)($user['name'] ?? '');
        $_SESSION['ticket_count']     = $ticketCount;

        // alte Keys (Backwards-Compatibility für vorhandene Seiten)
        $_SESSION['main_id']     = $mainId;
        $_SESSION['guest_id']    = (string)($user['id'] ?? '');
        $_SESSION['guest_name']  = (string)($user['name'] ?? '');

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
                $p['path'],
                $p['domain'],
                (bool)$p['secure'],
                (bool)$p['httponly']
            );
        }
        session_destroy();

        Response::redirect('/login.php');
    }
}
