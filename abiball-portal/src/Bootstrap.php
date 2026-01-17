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
            // Show all errors except deprecations from vendor packages
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            // Production: Log errors but don't display, exclude deprecations
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        }

        date_default_timezone_set('Europe/Berlin');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
    }

    private static function startSecureSession(): void
    {
        $secure = Config::isHttps();

        // Session-Härtungen
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('abiball_session');

        $params = session_get_cookie_params();
        $path   = ($params['path'] ?? '') !== '' ? (string)$params['path'] : '/';
        $domain = ($params['domain'] ?? '') !== '' ? (string)$params['domain'] : null;

        // Lax ist in der Praxis sinnvoll (z.B. Links aus Mails/QR)
        $sameSite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            $cookie = [
                'lifetime' => 0,
                'path'     => $path,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ];
            if ($domain !== null) {
                $cookie['domain'] = $domain;
            }

            session_set_cookie_params($cookie);
        } else {
            // PHP <7.3: SameSite über path-String
            $pathWithSameSite = $path . '; samesite=' . $sameSite;
            session_set_cookie_params(
                0,
                $pathWithSameSite,
                $domain ?? '',
                $secure,
                true
            );
        }

        session_start();

        // Erstinitialisierung (reduziert Fixation-Risiko)
        if (empty($_SESSION['_inited'])) {
            session_regenerate_id(true);
            $_SESSION['_inited'] = 1;
        }
    }
}
