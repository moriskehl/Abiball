<?php
// src/View/Partials/Navbar.php

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
  .navbar-brand::after{
    content:"";
    position:absolute;
    left: 50%;
    top: 70%;
    width: 120%;
    height: 60%;
    transform: translateX(-50%);
    border-radius: 999px;
    z-index: 1;
    pointer-events: none;
    background: transparent;
    opacity: .9;
    box-shadow:
      0 12px 26px rgba(201,162,39,.16),
      0 22px 58px rgba(201,162,39,.10);
    filter: blur(10px);
    mix-blend-mode: normal;
  }

  /* NEW: right cluster on mobile (initials + toggle) */
  .navbar-right-mobile{
    display:flex;
    align-items:center;
    gap:.35rem;
    margin-left:auto;
    margin-right:.35rem; /* small gap before hamburger */
  }

  /* Keep toggle in-between logo and hamburger as before (centered slot removed) */
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

  @media (max-width: 991.98px){
    /* On mobile: show toggle outside menu and show initials outside menu */
    .navbar-toggle-slot{ display:flex; }

    /* Hide the in-menu toggle on mobile */
    .navbar-toggle-inmenu{ display:none !important; }

    .navbar .container{
      justify-content: space-between;
    }

    .navbar-left{
      display:flex;
      align-items:center;
      gap:.5rem;
    }

    .navbar-collapse{
      margin-top: .75rem;
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
    /* Desktop: hide the outside-mobile cluster, show everything in-menu */
    .navbar-right-mobile{ display:none; }
    .navbar-toggle-slot{ display:none; }
  }
</style>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container py-2">

    <!-- Left cluster -->
    <div class="navbar-left">
      <a class="navbar-brand d-flex align-items-center" href="/">
        <img class="navbar-logo"
             src="/favicon.png"
             data-logo-light="/favicon.png"
             data-logo-dark="/favicon-dark.png"
             width="60" height="60" alt="Logo">
      </a>
    </div>

    <!-- Mobile right cluster (outside hamburger menu): initials + toggle -->
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

    <!-- Hamburger -->
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
          <a class="nav-link" href="/Location.php">Location</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="/zahlung.php">Zahlung</a>
        </li>

        <!-- Desktop: show initials + toggle inside menu -->
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
