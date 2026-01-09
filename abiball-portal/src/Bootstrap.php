<?php
declare(strict_types=1);

// src/Bootstrap.php
require_once __DIR__ . '/Config.php';

final class Bootstrap
{
    public static function init(): void
    {
        if (Config::isDev()) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL);
        }

        date_default_timezone_set('Europe/Berlin');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
    }

    private static function startSecureSession(): void
    {
        $secure = Config::isHttps();

        // Empfohlene Session-Härtungen
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('abiball_session');

        $params = session_get_cookie_params();

        // In der Praxis besser als Strict (sonst kommen "Cookie fehlt" bei externen Links)
        $sameSite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);
        } else {
            // PHP <7.3: SameSite über path-String
            session_set_cookie_params(
                0,
                ($params['path'] ?: '/') . '; samesite=' . $sameSite,
                $params['domain'] ?: '',
                $secure,
                true
            );
        }

        session_start();

        if (empty($_SESSION['_inited'])) {
            session_regenerate_id(true);
            $_SESSION['_inited'] = 1;
        }
    }
}
