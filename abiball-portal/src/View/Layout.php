<?php
declare(strict_types=1);

/**
 * Layout - HTML-Grundgerüst für alle Seiten
 * 
 * Stellt header() und footer() Methoden bereit, die das gemeinsame
 * HTML-Gerüst mit Navigation, Meta-Tags, Scripts und strukturierten
 * Daten (JSON-LD) für Suchmaschinen rendern.
 */

require_once __DIR__ . '/Helpers.php';

final class Layout
{
    /**
     * Gibt JSON-LD strukturierte Daten für das Event aus.
     * Verbessert die Darstellung in Google-Suchergebnissen.
     */
    public static function eventStructuredData(): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'bsz.app';
        $baseUrl = $protocol . '://' . $host;
        
        $eventData = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => 'Abiball 2026 - BSZ Leonberg',
            'description' => 'Der offizielle Abiball 2026 des Beruflichen Schulzentrums Leonberg. Ein eleganter Abend zum Feiern des Abiturs mit festlichem Dinner, Musik und unvergesslichen Momenten.',
            'startDate' => '2026-07-03T18:00:00+02:00',
            'endDate' => '2026-07-04T02:00:00+02:00',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => [
                '@type' => 'Place',
                'name' => 'Spitalkirche Leonberg',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Pfarrstraße 3',
                    'addressLocality' => 'Leonberg',
                    'postalCode' => '71229',
                    'addressCountry' => 'DE'
                ]
            ],
            'image' => [
                $baseUrl . '/images/saal.jpeg',
                $baseUrl . '/images/favicon.png'
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'BSZ Leonberg - Abitur 2026',
                'url' => $baseUrl
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $baseUrl . '/zahlung.php',
                'price' => '70.00',
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'validFrom' => '2025-09-01'
            ],
            'performer' => [
                '@type' => 'Organization',
                'name' => 'Abiturjahrgang 2026 BSZ Leonberg'
            ]
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }
    
    /**
     * Gibt JSON-LD für die Website aus.
     * Hilft Google dabei, Sitelinks in den Suchergebnissen anzuzeigen.
     */
    public static function websiteStructuredData(): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'bsz.app';
        $baseUrl = $protocol . '://' . $host;
        
        $websiteData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Abiball 2026 BSZ Leonberg',
            'alternateName' => 'Abiball Portal',
            'url' => $baseUrl,
            'description' => 'Offizielles Portal für den Abiball 2026 des Beruflichen Schulzentrums Leonberg.',
            'inLanguage' => 'de-DE',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Abiball 2026 BSZ Leonberg',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $baseUrl . '/images/favicon.png'
                ]
            ]
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($websiteData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }
    
    /**
     * Gibt JSON-LD für die Seitennavigation aus.
     * Unterstützt Google beim Anzeigen von Navigations-Sitelinks.
     */
    public static function siteNavigationStructuredData(): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'bsz.app';
        $baseUrl = $protocol . '://' . $host;
        
        $navItems = [
            ['name' => 'Login', 'url' => $baseUrl . '/login.php', 'description' => 'Anmelden zum Abiball Portal'],
            ['name' => 'Location', 'url' => $baseUrl . '/location/location.php', 'description' => 'Veranstaltungsort und Anfahrt'],
            ['name' => 'Zahlung', 'url' => $baseUrl . '/zahlung.php', 'description' => 'Zahlungsinformationen und Bankverbindung'],
            ['name' => 'FAQ', 'url' => $baseUrl . '/faq.php', 'description' => 'Häufig gestellte Fragen'],
            ['name' => 'Impressum', 'url' => $baseUrl . '/impressum.php', 'description' => 'Rechtliche Angaben'],
            ['name' => 'Datenschutz', 'url' => $baseUrl . '/datenschutz.php', 'description' => 'Datenschutzerklärung']
        ];
        
        $itemListElements = [];
        $position = 1;
        foreach ($navItems as $item) {
            $itemListElements[] = [
                '@type' => 'SiteNavigationElement',
                'position' => $position++,
                'name' => $item['name'],
                'description' => $item['description'],
                'url' => $item['url']
            ];
        }
        
        $navData = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $itemListElements
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($navData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }
    
    /**
     * Gibt JSON-LD für die Organisation aus.
     * Stärkt das Branding in Suchergebnissen.
     */
    public static function organizationStructuredData(): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'bsz.app';
        $baseUrl = $protocol . '://' . $host;
        
        $orgData = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Abiball 2026 BSZ Leonberg',
            'url' => $baseUrl,
            'logo' => $baseUrl . '/images/favicon.png',
            'description' => 'Offizielles Portal für den Abiball 2026 des Beruflichen Schulzentrums Leonberg.',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Leonberg',
                'addressCountry' => 'DE'
            ]
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($orgData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }
    
    /**
     * Gibt JSON-LD für die FAQ-Seite aus.
     * Ermöglicht Rich-Snippets mit Fragen und Antworten bei Google.
     */
    public static function faqStructuredData(array $faqs): void
    {
        $faqItems = [];
        foreach ($faqs as $faq) {
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq['answer'])
                ]
            ];
        }
        
        $faqData = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqItems
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($faqData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }
    
    /**
     * Gibt JSON-LD für Breadcrumb-Navigation aus.
     * Zeigt den Navigationspfad in Suchergebnissen an.
     */
    public static function breadcrumbStructuredData(array $breadcrumbs): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'bsz.app';
        $baseUrl = $protocol . '://' . $host;
        
        $items = [];
        $position = 1;
        foreach ($breadcrumbs as $name => $path) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $baseUrl . $path
            ];
        }
        
        $breadcrumbData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($breadcrumbData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }

    /**
     * Rendert den HTML-Head mit Meta-Tags, CSS und öffnenden Body-Bereich.
     */
    public static function header(string $title, string $description = '', string $ogImage = '', bool $loadChartJs = false): void
    {
        // Standard-Beschreibung falls nicht gesetzt
        if ($description === '') {
            $description = 'Abiball 2026 BSZ Leonberg - Alle wichtigen Informationen rund um Tickets, Sitzplätze und organisatorische Hinweise für den Abiball.';
        }
        
        // Standard Open-Graph-Bild falls nicht gesetzt
        if ($ogImage === '') {
            $ogImage = '/images/saal.jpeg';
        }
        
        // Aktuelle URL für Open-Graph-Tags ermitteln
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
            <link rel="apple-touch-icon" sizes="180x180" href="/images/favicon.png">
            
            <!-- Favicon: Standard .ico für Browser -->
            <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
            <link rel="shortcut icon" type="image/x-icon" href="/images/favicon.ico">
            <!-- PNG-Varianten für Geräte/Apps -->
            <link rel="icon" type="image/png" sizes="192x192" href="/images/favicon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon.png">
            
            <!-- PWA Manifest -->
            <link rel="manifest" href="/manifest.webmanifest">
            
            <title><?= e($title) ?></title>

            <?php if ($loadChartJs): ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
            <?php endif; ?>

            <!-- Theme früh setzen um Flackern zu vermeiden -->
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
            <!-- Barrierefreiheit: Sprung zum Hauptinhalt -->
            <a href="#main-content" class="skip-to-content">Zum Hauptinhalt springen</a>
            
            <!-- Globaler Hintergrund-Effekt für alle Seiten -->
            <div class="global-bg" aria-hidden="true">
                <div class="global-glow-layer"></div>
            </div>

        <?php
        // Navigation einbinden
        $navbar = __DIR__ . '/Partials/Navbar.php';
        if (is_file($navbar)) {
            require $navbar;
        }
        ?>

        <!-- Content-Wrapper: wächst auf kurzen Seiten, damit Footer unten bleibt -->
        <div class="page-content">
        <?php
    }

    /**
     * Rendert den Footer mit Scripts und schließenden HTML-Tags.
     */
    public static function footer(): void
    {
        ?>
        </div>
        <?php
        // Footer einbinden
        $footer = __DIR__ . '/Partials/Footer.php';
        if (is_file($footer)) {
            require $footer;
        }
        ?>

        <!-- Cookie-Hinweis -->
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

        <!-- Nach-oben-Button -->
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
        
        <!-- Formular-Ladezustand -->
        <script src="/assets/js/form-loading.js"></script>
        
        <!-- UI-Verbesserungen (Cookie-Banner, Nach-oben-Button) -->
        <script src="/assets/js/ui-enhancements.js"></script>
        
        <!-- Service Worker für PWA -->
        <script src="/assets/js/sw-register.js"></script>
        </body>
        </html>
        <?php
    }
}
