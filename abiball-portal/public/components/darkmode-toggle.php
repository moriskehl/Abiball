<?php
declare(strict_types=1);
?>
<button
  type="button"
  class="btn btn-ghost d-inline-flex align-items-center justify-content-center"
  data-theme-toggle
  aria-label="Theme umschalten"
  title="Theme umschalten"
  style="width:44px;height:44px;padding:0;"
>
  <!-- Sun (dark mode) -->
  <svg data-icon-sun xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"
       style="display:none;width:18px;height:18px;">
    <circle cx="12" cy="12" r="4" fill="currentColor"/>
    <g stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="12" y1="2"  x2="12" y2="5"/>
      <line x1="12" y1="19" x2="12" y2="22"/>
      <line x1="2"  y1="12" x2="5"  y2="12"/>
      <line x1="19" y1="12" x2="22" y2="12"/>
      <line x1="4.2" y1="4.2" x2="6.4" y2="6.4"/>
      <line x1="17.6" y1="17.6" x2="19.8" y2="19.8"/>
      <line x1="17.6" y1="6.4" x2="19.8" y2="4.2"/>
      <line x1="4.2" y1="19.8" x2="6.4" y2="17.6"/>
    </g>
  </svg>

  <!-- Moon (light mode) -->
  <svg data-icon-moon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"
       style="display:none;width:18px;height:18px;">
    <circle cx="12" cy="12" r="9" fill="currentColor"/>
    <circle cx="16.3" cy="10.6" r="9" fill="var(--bg)"/>
  </svg>
</button>

<script>
(function () {
  const html = document.documentElement;

  const FAV_LIGHT = "/favicon.png";
  const FAV_DARK  = "/favicon-dark.png";

  function setStoredTheme(v){ try{ localStorage.setItem('theme', v); }catch(e){} }

  function setFavicon(isDark){
    const href = isDark ? FAV_DARK : FAV_LIGHT;

    // 1) Update <link rel="icon"> (preferred)
    const link = document.getElementById("site-favicon");
    if (link) link.setAttribute("href", href + "?v=" + (isDark ? "dark" : "light"));

    // Optional: if you keep an .ico link, leave it or also switch it if you have dark ico.
    // const ico = document.getElementById("site-favicon-ico");
    // if (ico) ico.setAttribute("href", "/favicon.ico");

    // 2) Update navbar logos (can be multiple on page)
    document.querySelectorAll(".navbar-logo").forEach(img => {
      const light = img.getAttribute("data-logo-light") || FAV_LIGHT;
      const dark  = img.getAttribute("data-logo-dark")  || FAV_DARK;
      img.src = (isDark ? dark : light) + "?v=" + (isDark ? "dark" : "light");
    });
  }

  function applyTheme(nextDark){
    html.classList.add('no-theme-transition');
    html.classList.toggle('dark', nextDark);
    setStoredTheme(nextDark ? 'dark' : 'light');
    setFavicon(nextDark);
    requestAnimationFrame(() => html.classList.remove('no-theme-transition'));
  }

  function updateOne(btn){
    const sun  = btn.querySelector('[data-icon-sun]');
    const moon = btn.querySelector('[data-icon-moon]');
    const isDark = html.classList.contains('dark');

    btn.style.color = isDark ? '#f3f3f6' : '#0b0b0f';

    if (sun)  sun.style.display  = isDark ? 'block' : 'none';
    if (moon) moon.style.display = isDark ? 'none' : 'block';

    btn.setAttribute('aria-pressed', String(isDark));
  }

  function updateAll(){
    document.querySelectorAll('[data-theme-toggle]').forEach(updateOne);
  }

  // Init (sync favicon + logos on load)
  setFavicon(html.classList.contains('dark'));
  updateAll();

  // Bind each instance safely
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';

    btn.addEventListener('click', function(){
      const nextDark = !html.classList.contains('dark');
      applyTheme(nextDark);
      updateAll();
    });
  });

  // If theme is changed elsewhere (optional)
  window.addEventListener('storage', (e) => {
    if (e.key === 'theme') {
      const isDark = html.classList.contains('dark');
      setFavicon(isDark);
      updateAll();
    }
  });
})();
</script>

