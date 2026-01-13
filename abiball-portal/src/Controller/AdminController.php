<?php
declare(strict_types=1);

// src/Controller/AdminController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Security/AdminGuard.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';



final class AdminController
{
    public static function showLoginForm(string $error = '', string $identifier = ''): void
    {
        Bootstrap::init();

        Layout::header('Admin – Login');
        ?>
        <div class="container py-5" style="max-width:520px">
            <h1 class="h4 mb-3">Admin Login</h1>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin_login.php">
                <?= Csrf::inputField() ?>

                <div class="mb-3">
                    <label class="form-label">Admin-Name oder Admin-ID</label>
                    <input class="form-control" name="identifier" value="<?= e($identifier) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin-Code</label>
                    <input class="form-control" type="password" name="password" required>
                </div>

                <button class="btn btn-primary" type="submit">Einloggen</button>
                <a class="btn btn-outline-secondary ms-2" href="/">Zur Landing Page</a>
            </form>
        </div>
        <?php
        Layout::footer();
    }

    public static function login(): void
    {
        Bootstrap::init();

        if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            Response::redirect('/admin_dashboard.php');
        }

        $ipKey = 'admin_login_' . Request::ip();
        if (!RateLimiter::allow($ipKey, 8, 60)) {
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
            self::showLoginForm('Bitte Daten eingeben.', $identifier);
            return;
        }

        $admin = ParticipantsRepository::findAdminByIdOrName($identifier);
        if (!$admin) {
            self::showLoginForm('Admin nicht gefunden.', $identifier);
            return;
        }

        $stored = (string)($admin['login_code'] ?? '');
        if ($stored === '' || $stored === '0') {
            self::showLoginForm('Admin-Code fehlt in CSV.', $identifier);
            return;
        }

        if (!hash_equals($stored, $password)) {
            self::showLoginForm('Admin-Code ist falsch.', $identifier);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_id'] = $admin['id'] ?? '';
        $_SESSION['admin_name'] = $admin['name'] ?? 'Admin';

        Response::redirect('/admin_dashboard.php');
    }

    public static function logout(): void
    {
        Bootstrap::init();
        $_SESSION = [];
        session_destroy();
        Response::redirect('/admin_login.php');
    }

    public static function dashboard(): void
    {
        requireAdmin();

        $q = Request::getString('q');
        $all = ParticipantsRepository::all();

        // Filter
        $filtered = $all;
        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $filtered = array_values(array_filter($all, function ($p) use ($qLower) {
                $id = mb_strtolower((string)($p['id'] ?? ''), 'UTF-8');
                $name = mb_strtolower((string)($p['name'] ?? ''), 'UTF-8');
                $main = mb_strtolower((string)($p['main_id'] ?? ''), 'UTF-8');
                return str_contains($id, $qLower) || str_contains($name, $qLower) || str_contains($main, $qLower);
            }));
        }

        // Gruppieren nach main_id
        $groups = [];
        foreach ($filtered as $p) {
            $mainId = (string)($p['main_id'] ?? '');
            if ($mainId === '') continue;
            $groups[$mainId][] = $p;
        }
        ksort($groups);

        Layout::header('Admin – Dashboard');
        ?>
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Admin Dashboard</h1>
                    <div class="text-muted" style="font-size:.9rem;">
                        Eingeloggt als <?= e((string)($_SESSION['admin_name'] ?? 'Admin')) ?>
                    </div>
                </div>
                <a class="btn btn-outline-danger btn-sm" href="/admin_logout.php">Logout</a>
            </div>

            <form class="card mb-3" method="get" action="/admin_dashboard.php">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <input class="form-control" name="q" placeholder="Suche nach Name, ID, main_id ..." value="<?= e($q) ?>">
                    <button class="btn btn-primary" type="submit">Suchen</button>
                    <a class="btn btn-outline-secondary" href="/admin_dashboard.php">Zurücksetzen</a>
                </div>
            </form>

            <?php if (count($groups) === 0): ?>
                <div class="alert alert-warning">Keine Treffer.</div>
            <?php else: ?>
                <?php foreach ($groups as $mainId => $members): ?>
                    <?php
                    // Hauptgast finden
                    $main = null;
                    $companions = [];
                    foreach ($members as $m) {
                        if (!empty($m['is_main_bool'])) $main = $m;
                        else $companions[] = $m;
                    }
                    ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="text-muted" style="font-size:.9rem;">main_id</div>
                                    <div class="fw-semibold"><?= e($mainId) ?></div>
                                </div>
                                <div>
                                    <div class="text-muted" style="font-size:.9rem;">Hauptgast</div>
                                    <div class="fw-semibold">
                                        <?= e($main['name'] ?? '—') ?> (<?= e($main['id'] ?? '—') ?>)
                                    </div>
                                </div>
                                <div class="text-muted" style="font-size:.9rem;">
                                    Begleiter: <?= count($companions) ?>
                                </div>
                            </div>

                            <?php if (count($companions) > 0): ?>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Begleiter Name</th>
                                                <th class="text-nowrap">Begleiter ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($companions as $c): ?>
                                            <tr>
                                                <td><?= e($c['name'] ?? '') ?></td>
                                                <td class="text-nowrap"><?= e($c['id'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        Layout::footer();
    }
}
