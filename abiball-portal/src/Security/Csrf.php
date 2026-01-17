<?php
declare(strict_types=1);

/**
 * Csrf - Cross-Site Request Forgery Schutz
 * 
 * Generiert und validiert CSRF-Tokens für alle Formulare.
 * Verhindert dass böswillige Seiten Anfragen im Namen des Users stellen.
 */

require_once __DIR__ . '/../Bootstrap.php';

final class Csrf
{
    private const KEY = '_csrf_token';

    private static function init(): void
    {
        Bootstrap::init();
    }

    /**
     * Gibt das aktuelle CSRF-Token zurück (generiert eins falls nötig).
     */
    public static function token(): string
    {
        self::init();

        if (
            !isset($_SESSION[self::KEY]) ||
            !is_string($_SESSION[self::KEY]) ||
            strlen($_SESSION[self::KEY]) < 32
        ) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /**
     * Validiert ein Token gegen das Session-Token.
     * Nutzt hash_equals um Timing-Angriffe zu verhindern.
     */
    public static function validate(?string $token): bool
    {
        self::init();

        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION[self::KEY] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Validiert das Token aus verschiedenen Quellen.
     * Prüft: POST-Parameter, X-CSRF-TOKEN Header, JSON-Body.
     */
    public static function validateRequest(): bool
    {
        self::init();

        // Aus Formular
        if (isset($_POST['_csrf'])) {
            return self::validate($_POST['_csrf']);
        }

        // Aus Header (für AJAX)
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken !== null) {
            return self::validate($headerToken);
        }

        // Aus JSON-Body
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['_csrf'])) {
                return self::validate($input['_csrf']);
            }
        }

        return false;
    }

    /**
     * Gibt ein verstecktes Input-Feld für Formulare zurück.
     */
    public static function inputField(): string
    {
        $t = self::token();

        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Gibt ein Meta-Tag zurück für JavaScript-Zugriff.
     */
    public static function metaTag(): string
    {
        $t = self::token();

        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
        );
    }
}
