/**
 * Automatische Loading States für alle Formulare
 * Fügt Spinner zu Submit-Buttons hinzu und deaktiviert sie während der Übertragung
 */
(function() {
  'use strict';

  // Wartezeit in ms bevor Loading State aktiviert wird (verhindert Flackern bei schnellen Requests)
  const DELAY = 150;

  /**
   * Aktiviert Loading State auf einem Button
   */
  function enableLoadingState(button) {
    if (button.classList.contains('btn-loading')) return;
    
    button.classList.add('btn-loading');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
  }

  /**
   * Deaktiviert Loading State (falls Page Reload fehlschlägt)
   */
  function disableLoadingState(button) {
    button.classList.remove('btn-loading');
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }

  /**
   * Initialisiert Form-Loading für alle Formulare auf der Seite
   */
  function initFormLoading() {
    document.querySelectorAll('form').forEach(form => {
      // Skip forms mit data-no-loading Attribut
      if (form.dataset.noLoading) return;

      form.addEventListener('submit', function(e) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) return;

        // Delayed Loading State (verhindert Flackern)
        const timeoutId = setTimeout(() => {
          enableLoadingState(submitButton);
        }, DELAY);

        // Cleanup falls Submit fehlschlägt oder Page nicht reloaded
        setTimeout(() => {
          clearTimeout(timeoutId);
          disableLoadingState(submitButton);
        }, 5000);
      });
    });
  }

  // Auto-Init wenn DOM geladen ist
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormLoading);
  } else {
    initFormLoading();
  }

  // Für dynamisch hinzugefügte Formulare
  window.initFormLoading = initFormLoading;
})();
