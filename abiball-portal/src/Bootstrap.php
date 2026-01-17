<?php
declare(strict_types=1);

/**
 * Bootstrap - Initialisiert die Anwendung
 * 
 * Kümmert sich um Error-Handling, Timezone und sichere Session-Konfiguration.
 * Muss am Anfang jeder Seite via Bootstrap::init() aufgerufen werden.
 */
require_once __DIR__ . '/Config.php';

final class Bootstrap
{
    /**
     * Initialisiert die Anwendung mit allen nötigen Einstellungen.
     */
    public static function init(): void
    {
        // Fehleranzeige je nach Umgebung konfigurieren
        if (Config::isDev()) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        }

        date_default_timezone_set('Europe/Berlin');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
    }

    /**
     * Startet eine gehärtete Session mit sicheren Cookie-Einstellungen.
     */
    private static function startSecureSession(): void
    {
        $secure = Config::isHttps();

        // Session-Sicherheit: Keine URL-Parameter, nur Cookies
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('abiball_session');

        $params = session_get_cookie_params();
        $path   = ($params['path'] ?? '') !== '' ? (string)$params['path'] : '/';
        $domain = ($params['domain'] ?? '') !== '' ? (string)$params['domain'] : null;

        // Lax erlaubt Zugriff über externe Links (z.B. aus E-Mails)
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
            // Fallback für ältere PHP-Versionen
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

        // Neue Session-ID bei erstem Aufruf generieren (verhindert Session-Fixation)
        if (empty($_SESSION['_inited'])) {
            session_regenerate_id(true);
            $_SESSION['_inited'] = 1;
        }
    }
}
