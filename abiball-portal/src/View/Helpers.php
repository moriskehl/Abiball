<?php
declare(strict_types=1);

/**
 * Helpers - Globale View-Hilfsfunktionen
 * 
 * Enthält kleine Hilfsfunktionen, die in Views häufig benötigt werden.
 */

/**
 * Escaped einen String für sichere HTML-Ausgabe.
 */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
