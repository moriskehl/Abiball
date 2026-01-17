<?php
declare(strict_types=1);

// src/View/Layout.php
require_once __DIR__ . '/Helpers.php';

final class Layout
{
    public static function header(string $title, string $description = '', string $ogImage = '', bool $loadChartJs = false): void
    {
        // Default description
        if ($description === '') {
            $description = 'Abiball 2026 BSZ Leonberg - Alle wichtigen Informationen rund um Tickets, Sitzplätze und organisatorische Hinweise für den Abiball.';
        }
        
        // Default OG Image
        if ($ogImage === '') {
            $ogImage = '/images/saal.jpeg'; // Fallback auf Saal-Bild
        }
        
        // Aktuelle URL ermitteln
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $currentUrl = $protocol . '://' . $host . $uri;
        
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
            <meta name="keywords" content="Abiball, BSZ Leonberg, 2026, Tickets, Abitur, Berufliches Gymnasium">
            <meta name="author" content="Moris Kehl">
            <meta name="theme-color" content="#c9a227">
            
            <!-- Open Graph / Facebook -->
            <meta property="og:type" content="website">
            <meta property="og:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
            <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
            <meta property="og:image" content="<?= htmlspecialchars($protocol . '://' . $host . $ogImage, ENT_QUOTES, 'UTF-8') ?>">
            <meta property="og:locale" content="de_DE">
            
            <!-- Twitter -->
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <meta name="twitter:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
            <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
            <meta name="twitter:image" content="<?= htmlspecialchars($protocol . '://' . $host . $ogImage, ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- iOS / PWA -->
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
            <meta name="apple-mobile-web-app-title" content="Abiball 2026">
            <link rel="apple-touch-icon" href="/images/favicon.png">
            
            <!-- Favicon -->
            <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
            <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon.png">
            
            <!-- PWA Manifest -->
            <link rel="manifest" href="/manifest.webmanifest">
            
            <title><?= e($title) ?></title>

            <?php if ($loadChartJs): ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
            <?php endif; ?>

            <!-- Theme init before CSS to avoid flicker -->
            <script>
                (function () {
                    try {
                        const stored = localStorage.getItem('theme'); // "dark" | "light" | null
                        const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                        const theme = (stored === 'dark' || stored === 'light') ? stored : (systemDark ? 'dark' : 'light');
                        document.documentElement.classList.toggle('dark', theme === 'dark');
                    } catch (e) {}
                })();
            </script>

            <!-- Bootstrap CSS -->
            <link
                href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
                rel="stylesheet"
                crossorigin="anonymous"
            >

            <!-- Custom CSS -->
            <link rel="stylesheet" href="/assets/css/style.css">
        </head>

        <body class="layout-root">
            <!-- Skip to main content (Accessibility) -->
            <a href="#main-content" class="skip-to-content">Zum Hauptinhalt springen</a>
            
            <!-- Global premium background (layout-level, on ALL pages) -->
            <div class="global-bg" aria-hidden="true">
                <div class="global-glow-layer"></div>
            </div>

        <?php
        // Navbar partial
        $navbar = __DIR__ . '/Partials/Navbar.php';
        if (is_file($navbar)) {
            require $navbar;
        }
        ?>

        <!-- Sticky-footer wrapper: grows on short pages (e.g., login) -->
        <div class="page-content">
        <?php
    }

    public static function footer(): void
    {
        ?>
        </div><!-- /.page-content -->
        <?php
        // Footer partial
        $footer = __DIR__ . '/Partials/Footer.php';
        if (is_file($footer)) {
            require $footer;
        }
        ?>

        <!-- Cookie Banner -->
        <div id="cookie-banner" class="cookie-banner" style="display: none;">
            <div class="cookie-banner-content">
                                <p class="mb-3">
                    <strong>Cookies & Datenschutz</strong><br>
                    Diese Website verwendet technisch notwendige Cookies für die Funktionalität. 
                    Durch die weitere Nutzung stimmst du der Verwendung zu.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <button id="cookie-accept" class="btn btn-save btn-sm">Akzeptieren</button>
                    <a href="/datenschutz.php" class="btn btn-outline-secondary btn-sm">Mehr erfahren</a>
                </div>
            </div>
        </div>

        <!-- Zurück nach oben Button -->
        <button id="back-to-top" class="back-to-top" aria-label="Zurück nach oben" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 4l-8 8h5v8h6v-8h5z"/>
            </svg>
        </button>

        <!-- Bootstrap JS -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"
        ></script>
        
        <!-- Form Loading States -->
        <script src="/assets/js/form-loading.js"></script>
        
        <!-- Cookie Banner & Back to Top -->
        <script src="/assets/js/ui-enhancements.js"></script>
        
        <!-- Service Worker Registration -->
        <script src="/assets/js/sw-register.js"></script>
        </body>
        </html>
        <?php
    }
}
