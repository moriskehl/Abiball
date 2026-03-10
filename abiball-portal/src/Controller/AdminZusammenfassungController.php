<?php

declare(strict_types=1);

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Auth/AdminContext.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

final class AdminZusammenfassungController
{
    public static function show(): void
    {
        Bootstrap::init();
        AdminContext::requireAdmin();

        // Load pre-rendered static data from processed PHP file
        $sections = require_once __DIR__ . '/../Data/SystemZusammenfassungData.php';

        Layout::header('Admin – Systemzusammenfassung');
        self::renderView($sections);
        Layout::footer();
    }

    private static function renderView(array $sections): void
    {
        ?>
    <main class="bg-starfield admin-dashboard">
      <!-- Star layers -->
      <div class="stars-layer-1"></div>
      <div class="stars-layer-2"></div>
      <div class="stars-layer-3"></div>

      <div class="container py-4">

        <div class="text-center mx-auto" style="max-width: 820px; padding-top: 18px; padding-bottom: 24px;">

          <div class="glass-hero-header sm mb-4 animate-fade-up">
            <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
              Abiball Projekt
            </h1>
            <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.7; letter-spacing: 0.5px;">
              Wirtschaftsinformatik Präsentation  -  Moris Kehl
            </p>
          </div>
        </div>

        <?php if (!empty($sections)): ?>
        <!-- Tab navigation -->
        <div class="card admin-card">
          <div class="card-body p-4">
            <div class="text-muted small admin-kicker mb-3">Kapitel</div>

            <!-- Pill tabs -->
            <ul class="nav nav-pills sysinfo-tabs flex-wrap mb-4" id="systemTabs" role="tablist">
              <?php foreach ($sections as $i => $s): ?>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link<?= $i === 0 ? ' active' : '' ?>"
                  id="tab-<?= $i ?>"
                  data-bs-toggle="pill"
                  data-bs-target="#content-<?= $i ?>"
                  type="button"
                  role="tab"
                  aria-controls="content-<?= $i ?>"
                  aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                ><?= $s['shortTitle'] ?></button>
              </li>
              <?php endforeach; ?>
            </ul>

            <!-- Tab content -->
            <div class="tab-content" id="systemTabsContent">
              <?php foreach ($sections as $i => $s): ?>
              <div
                class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?> sysinfo-md"
                id="content-<?= $i ?>"
                role="tabpanel"
                aria-labelledby="tab-<?= $i ?>"
              >
                <?= $s['html'] ?>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Prev / Next Navigation -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top: 1px solid var(--border);">
              <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" id="sysinfoNavPrev" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                </svg>
                <span>Zurück</span>
              </button>

              <span class="text-muted small" id="sysinfoNavLabel">1 / <?= count($sections) ?></span>

              <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" id="sysinfoNavNext">
                <span>Weiter</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                </svg>
              </button>
            </div>

          </div>
        </div>
        <?php endif; ?>

      </div>
    </main>

    <!-- ======= Scoped Styles for System Info ======= -->
    <style>
    /* ── Tab pills ─────────────────────────────────────── */
    .sysinfo-tabs .nav-link {
      border-radius: 20px;
      padding: .38rem 1rem;
      margin: 0 4px 6px 0;
      font-size: .85rem;
      font-weight: 500;
      color: var(--text);
      background: rgba(11,11,15,.04);
      border: 1px solid var(--border);
      transition: all .2s ease;
    }
    html.dark .sysinfo-tabs .nav-link {
      background: rgba(255,255,255,.05);
      border-color: rgba(255,255,255,.1);
      color: rgba(243,243,246,.78);
    }
    .sysinfo-tabs .nav-link:hover {
      background: rgba(201,162,39,.08);
      border-color: rgba(201,162,39,.35);
      color: var(--text);
    }
    .sysinfo-tabs .nav-link.active {
      background: linear-gradient(180deg, var(--gold-2, #e8c84a), var(--gold, #c9a227));
      border-color: rgba(0,0,0,.12);
      color: #0b0b0f;
      font-weight: 700;
      box-shadow: 0 6px 18px var(--gold-glow, rgba(201,162,39,.25));
    }

    /* ── Markdown body ─────────────────────────────────── */
    .sysinfo-md { color: var(--text); line-height: 1.7; }

    .sysinfo-md h1 { font-size: 1.6rem; font-weight: 300; margin: 0 0 .8rem; border: none; }
    .sysinfo-md h2 { font-size: 1.35rem; font-weight: 600; margin: 1.8rem 0 .8rem; padding-bottom: .5rem; border-bottom: 1px solid var(--border); }
    .sysinfo-md h3 { font-size: 1.1rem; font-weight: 600; margin: 1.4rem 0 .6rem; }

    .sysinfo-md p  { margin-bottom: .8rem; }
    .sysinfo-md ul, .sysinfo-md ol { margin-bottom: 1rem; padding-left: 1.5rem; }
    .sysinfo-md li { margin-bottom: .3rem; }
    .sysinfo-md strong { font-weight: 700; }

    /* Tables */
    .sysinfo-md table {
      width: 100%;
      margin-bottom: 1.5rem;
      border-collapse: collapse;
      color: var(--text);
    }
    .sysinfo-md table th {
      background: var(--surface-2, rgba(0,0,0,.06));
      text-align: left;
      font-weight: 600;
    }
    .sysinfo-md table th,
    .sysinfo-md table td {
      padding: .65rem .85rem;
      border: 1px solid var(--border);
      font-size: .92rem;
    }
    html.dark .sysinfo-md table th {
      background: rgba(255,255,255,.04);
    }

    /* Code */
    .sysinfo-md code {
      font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
      font-size: .88em;
      background: rgba(201,162,39,.12);
      padding: .15rem .4rem;
      border-radius: 4px;
      color: var(--text);
    }
    html.dark .sysinfo-md code {
      background: rgba(201,162,39,.15);
      color: #f0dfa0;
    }
    .sysinfo-md pre {
      background: rgba(11,11,15,.06);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1rem 1.2rem;
      overflow-x: auto;
      margin-bottom: 1.2rem;
    }
    html.dark .sysinfo-md pre {
      background: rgba(0,0,0,.45);
      border-color: rgba(255,255,255,.08);
    }
    .sysinfo-md pre code {
      background: transparent !important;
      padding: 0;
      color: var(--text);
      font-size: .85rem;
    }
    html.dark .sysinfo-md pre code {
      color: rgba(243,243,246,.9);
    }

    /* Blockquotes */
    .sysinfo-md blockquote {
      border-left: 4px solid var(--gold, #c9a227);
      margin: 1rem 0;
      padding: .6rem 1rem;
      background: rgba(201,162,39,.06);
      border-radius: 0 8px 8px 0;
      color: var(--text);
    }
    html.dark .sysinfo-md blockquote {
      background: rgba(201,162,39,.06);
    }
    .sysinfo-md blockquote p:last-child { margin-bottom: 0; }

    /* Horizontal rules */
    .sysinfo-md hr {
      border: none;
      border-top: 1px solid var(--border);
      margin: 1.5rem 0;
    }

    /* Images → placeholder */
    .sysinfo-md img {
      max-width: 100%;
      border-radius: 12px;
      border: 1px solid var(--border);
      margin: .5rem 0;
    }

    /* Diagram images */
    .sysinfo-md img {
      background: #fff;
      padding: 1.5rem;
      border-radius: 12px;
      border: 1px solid var(--border);
      
      /* Keep native size but prevent overflow and extreme height */
      max-width: 100%;
      height: auto;
      width: auto;
      max-height: 70vh;
      
      /* Centering */
      display: block;
      margin: 2rem auto;
      
      /* Subtle shadow */
      box-shadow: 0 4px 25px rgba(0,0,0,0.05);
    }

    /* Responsive: stack tabs vertically on small screens */
    @media (max-width: 576px) {
      .sysinfo-tabs .nav-link { font-size: .78rem; padding: .3rem .75rem; }
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Replace any images that fail to load with a placeholder
      document.querySelectorAll('.sysinfo-md img').forEach(function(img) {
        img.onerror = function() {
          this.onerror = null;
          this.src = '/images/placeholder.png';
          this.alt = 'Platzhalter – Bild folgt';
        };
      });

      // ── Prev / Next navigation ──────────────────────────
      var tabs = document.querySelectorAll('#systemTabs button.nav-link');
      var prevBtn = document.getElementById('sysinfoNavPrev');
      var nextBtn = document.getElementById('sysinfoNavNext');
      var label   = document.getElementById('sysinfoNavLabel');
      var total   = tabs.length;

      function currentIndex() {
        for (var i = 0; i < tabs.length; i++) {
          if (tabs[i].classList.contains('active')) return i;
        }
        return 0;
      }

      function goTo(idx) {
        if (idx < 0 || idx >= total) return;
        bootstrap.Tab.getOrCreateInstance(tabs[idx]).show();
        updateNav(idx);
        // scroll to top of card
        document.getElementById('systemTabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      function updateNav(idx) {
        if (!prevBtn || !nextBtn || !label) return;
        prevBtn.disabled = (idx <= 0);
        nextBtn.disabled = (idx >= total - 1);
        label.textContent = (idx + 1) + ' / ' + total;
      }

      if (prevBtn) prevBtn.addEventListener('click', function() { goTo(currentIndex() - 1); });
      if (nextBtn) nextBtn.addEventListener('click', function() { goTo(currentIndex() + 1); });

      // Also update nav when user clicks a tab pill directly
      tabs.forEach(function(tab) {
        tab.addEventListener('shown.bs.tab', function() { updateNav(currentIndex()); });
      });

      updateNav(0);
    });
    </script>

        <?php
    }
}
