<?php
/**
 * Navbar Partial - Hauptnavigation für alle Seiten
 * 
 * Responsive Navigation mit Logo, Links, Darkmode-Toggle und
 * Benutzer-Avatar wenn eingeloggt. Passt sich für Mobile an.
 */

require_once __DIR__ . '/../../Auth/AuthContext.php';

$togglePath  = __DIR__ . '/../../../public/components/darkmode-toggle.php';

$mainId     = trim(AuthContext::mainId());
$isLoggedIn = ($mainId !== '');
$initials   = $isLoggedIn ? AuthContext::userInitials() : '';
?>

<style>
  .navbar-brand{
    position: relative;
    display: inline-flex;
    align-items: center;
  }
  .navbar-brand .navbar-logo{
    position: relative;
    z-index: 2;
    display: block;
    border-radius: 999px;
  }
  /* Navbar brand glow removed as per request */

  /* Rechte Elemente auf Mobile (Initialen + Toggle) */
  .navbar-right-mobile{
    display:flex;
    align-items:center;
    gap:.35rem;
    margin-left:auto;
    margin-right:.35rem;
  }

  /* Toggle-Container zwischen Logo und Hamburger */
  .navbar-toggle-slot{
    display:none;
    align-items:center;
  }

  .navbar-user{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:44px;
    height:44px;
    border-radius:999px;
    font-weight:700;
    font-size:.95rem;
    letter-spacing:.02em;
    background: var(--surface-2);
    border:1px solid var(--border);
    color: var(--text, inherit);
    user-select:none;
  }

  /* Smooth fade-out for focus/active states on touch devices */
  button,
  a,
  .btn,
  .navbar-toggler {
    transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }

  @media (max-width: 991.98px){
    /* Mobile: Toggle und Initialen außerhalb des Menüs anzeigen */
    .navbar-toggle-slot{ display:flex; }

    /* In-Menu Toggle auf Mobile ausblenden */
    .navbar-toggle-inmenu{ display:none !important; }

    .navbar .container{
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .navbar-left{
      display:flex;
      align-items:center;
      gap:.5rem;
    }

    .navbar-collapse{
      margin-top: .75rem;
      width: 100%;
      order: 4; /* Ensure it stays below brand/toggler */
    }

    #mainNavbar .navbar-nav{
      width: 100%;
      gap: 0 !important;
      padding: .25rem 0;
      border-top: 1px solid var(--border);
      margin-top: .5rem;
    }

    #mainNavbar .nav-item{
      width: 100%;
      padding: 0;
      margin: 0;
    }

    #mainNavbar .nav-link{
      display: block;
      width: 100%;
      padding: .95rem 1.0rem !important;
      margin: .15rem 0 !important;
      border-radius: 12px;
      border: 1px solid transparent;
      background: rgba(255,255,255,.40);
    }

    html.dark #mainNavbar .nav-link{
      background: rgba(255,255,255,.04);
    }

    #mainNavbar .nav-link:hover{
      background: var(--surface-2);
      border-color: rgba(201,162,39,.22);
    }

    #mainNavbar .nav-item + .nav-item{
      border-top: 1px solid var(--border);
      padding-top: .20rem;
      margin-top: .20rem;
    }

    #mainNavbar .btn-cta{
      display: inline-flex;
      justify-content: center;
      width: 100%;
      margin-top: .65rem;
    }
  }

  @media (min-width: 992px){
    /* Desktop: Mobile-Elemente ausblenden, alles im Menü anzeigen */
    .navbar-right-mobile{ display:none; }
    .navbar-toggle-slot{ display:none; }
  }
</style>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container py-2">

    <!-- Logo links -->
    <div class="navbar-left">
      <a class="navbar-brand d-flex align-items-center" href="/">
        <img class="navbar-logo"
             src="/images/!!favicon.png"
             data-logo-light="/images/!!favicon.png"
             data-logo-dark="/images/!!favicon-dark.png"
             width="60" height="60" alt="Logo">
      </a>
    </div>

    <!-- Mobile: Initialen und Darkmode-Toggle rechts vom Hamburger -->
    <div class="navbar-right-mobile">
      <?php if ($isLoggedIn): ?>
        <a href="/dashboard.php"
          class="navbar-user"
          title="Zum Dashboard"
          aria-label="Zum Dashboard">
          <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
        </a>

      <?php endif; ?>

      <div class="navbar-toggle-slot">
        <?php if (is_file($togglePath)) { require $togglePath; } ?>
      </div>
    </div>

    <!-- Hamburger-Menü für Mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

        <li class="nav-item">
          <a class="nav-link" href="/index.php">Home</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="/location/location.php">Location</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="/zahlung.php">Zahlung</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="/faq.php">FAQ</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="/voting/result.php">Voting</a>
        </li>

        <!-- Desktop: Initialen und Toggle im Menü -->
        <?php if ($isLoggedIn): ?>
          <li class="nav-item ms-lg-2 d-none d-lg-flex">
            <a href="/dashboard.php"
              class="navbar-user"
              title="Zum Dashboard"
              aria-label="Zum Dashboard">
              <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>

        <?php endif; ?>

        <li class="nav-item ms-lg-2 navbar-toggle-inmenu">
          <?php if (is_file($togglePath)) { require $togglePath; } ?>
        </li>

        <li class="nav-item ms-lg-2">
          <?php if ($isLoggedIn): ?>
            <a class="btn btn-cta btn-cta-sm" href="/dashboard.php">Dashboard</a>
          <?php else: ?>
            <a class="btn btn-cta btn-cta-sm" href="/login.php">Login</a>
          <?php endif; ?>
        </li>

      </ul>
    </div>
  </div>
</nav>
