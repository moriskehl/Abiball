/**
 * Service Worker Registration & PDF Pre-Caching
 */
(function() {
  'use strict';

  // Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/sw.js')
        .then(function(registration) {
          console.log('✓ Service Worker registered:', registration.scope);
          
          // Update-Check
          registration.addEventListener('updatefound', function() {
            const newWorker = registration.installing;
            newWorker.addEventListener('statechange', function() {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // Neue Version verfügbar
                if (confirm('Eine neue Version ist verfügbar. Seite neu laden?')) {
                  newWorker.postMessage({ type: 'SKIP_WAITING' });
                  window.location.reload();
                }
              }
            });
          });
        })
        .catch(function(err) {
          console.log('Service Worker registration failed:', err);
        });
    });
  }

  // PDF Pre-Caching (nur im Dashboard und nur bei idle time)
  function initPdfPreCaching() {
    // Nur auf Dashboard-Seite
    if (!window.location.pathname.includes('dashboard')) {
      return;
    }

    // Service Worker muss aktiv sein
    if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
      return;
    }

    // Warte bis Seite vollständig geladen ist
    if (document.readyState !== 'complete') {
      window.addEventListener('load', initPdfPreCaching);
      return;
    }

    // RequestIdleCallback für niedrige Priorität
    const idleCallback = window.requestIdleCallback || function(cb) { 
      setTimeout(cb, 1000); 
    };

    idleCallback(function() {
      // Finde alle Ticket-Links
      const ticketLinks = document.querySelectorAll('a[href*="/ticket/pdf.php"]');
      
      ticketLinks.forEach(function(link, index) {
        const pdfUrl = link.href;
        
        // Staggered caching (nicht alle auf einmal)
        setTimeout(function() {
          // Prüfe Netzwerkverbindung (nur bei guter Verbindung cachen)
          const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
          
          // Skip wenn langsame Verbindung oder Save-Data aktiv
          if (connection) {
            if (connection.saveData || connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') {
              return;
            }
          }
          
          // Message an Service Worker schicken
          navigator.serviceWorker.controller.postMessage({
            type: 'CACHE_PDF',
            url: pdfUrl
          });
          
        }, index * 3000); // 3 Sekunden zwischen jedem PDF
      });
    });
  }

  // Auto-Init
  initPdfPreCaching();
})();
