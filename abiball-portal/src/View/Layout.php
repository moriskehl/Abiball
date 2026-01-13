<?php
declare(strict_types=1);

// src/View/Layout.php
require_once __DIR__ . '/Helpers.php';

final class Layout
{
    public static function header(string $title): void
    {
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="icon" href="/favicon.ico" type="image/x-icon">
            <title><?= e($title) ?></title>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>

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

        <!-- Bootstrap JS -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"
        ></script>
        </body>
        </html>
        <?php
    }
}
