/**
 * UI Enhancements: Cookie Banner & Back-to-Top Button
 */
(function() {
  'use strict';

  // =========================================================
  // Cookie Banner
  // =========================================================
  function initCookieBanner() {
    const banner = document.getElementById('cookie-banner');
    if (!banner) return;

    const acceptBtn = document.getElementById('cookie-accept');
    const cookieConsent = localStorage.getItem('cookieConsent');

    // Banner anzeigen falls noch nicht akzeptiert
    if (!cookieConsent) {
      setTimeout(() => {
        banner.style.display = 'block';
        banner.style.animation = 'slideUp 0.4s ease-out';
      }, 1000);
    }

    // Accept Handler
    if (acceptBtn) {
      acceptBtn.addEventListener('click', function() {
        localStorage.setItem('cookieConsent', 'accepted');
        banner.style.animation = 'slideDown 0.3s ease-in';
        setTimeout(() => {
          banner.style.display = 'none';
        }, 300);
      });
    }
  }

  // =========================================================
  // Back to Top Button
  // =========================================================
  function initBackToTop() {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    let isVisible = false;

    // Show/Hide basierend auf Scroll-Position
    function checkScroll() {
      const shouldShow = window.pageYOffset > 300;
      
      if (shouldShow && !isVisible) {
        btn.style.display = 'flex';
        setTimeout(() => btn.classList.add('visible'), 10);
        isVisible = true;
      } else if (!shouldShow && isVisible) {
        btn.classList.remove('visible');
        setTimeout(() => {
          if (!btn.classList.contains('visible')) {
            btn.style.display = 'none';
          }
        }, 300);
        isVisible = false;
      }
    }

    // Throttled Scroll Handler
    let scrollTimeout;
    window.addEventListener('scroll', function() {
      if (scrollTimeout) return;
      scrollTimeout = setTimeout(() => {
        checkScroll();
        scrollTimeout = null;
      }, 100);
    }, { passive: true });

    // Click Handler - Smooth Scroll
    btn.addEventListener('click', function() {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });

    // Initial Check
    checkScroll();
  }

  // Auto-Init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initCookieBanner();
      initBackToTop();
    });
  } else {
    initCookieBanner();
    initBackToTop();
  }
})();
