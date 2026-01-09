<?php
// src/View/Partials/Navbar.php (oder wo deine Navbar liegt)
$togglePath = __DIR__ . '/../../../public/components/darkmode-toggle.php';
?>

<style>
  /* ---------- Brand / Glow (deins unverändert, nur minimal geordnet) ---------- */
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

  /* ---------- Mobile toggle placement between logo and hamburger ---------- */
  .navbar-toggle-slot{
    display:none;
    margin-left:auto;
    margin-right:auto;
    align-items:center;
  }

  @media (max-width: 991.98px){
    .navbar-toggle-slot{ display:flex; }
    .navbar-toggle-inmenu{ display:none !important; }

    .navbar .container{ justify-content: space-between; }
    .navbar-left{
      display:flex;
      align-items:center;
      gap:.5rem;
    }

    /* =========================================================
       Hamburger menu spacing + separation (requested)
       ========================================================= */
    .navbar-collapse{
      margin-top: .75rem;
    }

    /* make menu look like a separated list */
    #mainNavbar .navbar-nav{
      width: 100%;
      gap: 0 !important;                /* use our margins instead */
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
      padding: .95rem 1.0rem !important; /* bigger touch target */
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

    /* clear separators between items */
    #mainNavbar .nav-item + .nav-item{
      border-top: 1px solid var(--border);
      padding-top: .20rem;
      margin-top: .20rem;
    }

    /* CTA in menu full width */
    #mainNavbar .btn-cta{
      display: inline-flex;
      justify-content: center;
      width: 100%;
      margin-top: .65rem;
    }
  }

  @media (min-width: 992px){
    .navbar-toggle-slot{ display:none; }
  }
</style>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container py-2">

    <!-- Left cluster: Logo + (mobile) toggle -->
    <div class="navbar-left">
      <a class="navbar-brand d-flex align-items-center" href="/">
        <img class="navbar-logo"
             src="/favicon.png"
             data-logo-light="/favicon.png"
             data-logo-dark="/favicon-dark.png"
             width="60" height="60" alt="Logo">
      </a>

      <!-- Toggle shown ONLY on mobile (between logo and hamburger) -->
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

        <!-- NEW: Zahlung -->
        <li class="nav-item">
          <a class="nav-link" href="/zahlung.php">Zahlung</a>
        </li>

        <!-- Toggle in collapsed/desktop menu (hidden on mobile) -->
        <li class="nav-item ms-lg-2 navbar-toggle-inmenu">
          <?php if (is_file($togglePath)) { require $togglePath; } ?>
        </li>

        <li class="nav-item ms-lg-2">
          <a class="btn btn-cta btn-cta-sm" href="/dashboard.php">Dashboard</a>
        </li>

      </ul>
    </div>
  </div>
</nav>
