<?php
declare(strict_types=1);

// public/datenschutz.php
require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/View/Layout.php';

Bootstrap::init();

Layout::header('Datenschutzerklärung', 'Datenschutzbestimmungen für das Abiball Portal 2026 BSZ Leonberg');
?>

<main class="bg-starfield">
  <div class="container py-5" style="max-width: 900px;">
    
    <div class="text-center mx-auto mb-5" style="max-width: 760px; padding-top: 18px;">
      <h1 class="h-serif mb-3" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
        Datenschutzerklärung
      </h1>
      <p class="text-muted" style="font-size: 1.05rem;">
        Informationen zur Verarbeitung deiner personenbezogenen Daten
      </p>
    </div>

    <div class="card">
      <div class="card-body p-4 p-md-5" style="line-height: 1.8;">

        <h2 class="h4 mb-3">1. Verantwortliche Stelle</h2>
        <p>
          Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
          <strong>Moris Kehl</strong><br>
          E-Mail: <a href="mailto:moris.kehl@gmail.com">moris.kehl@gmail.com</a>
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">2. Erhebung und Speicherung personenbezogener Daten</h2>
        
        <h3 class="h6 mb-2">2.1 Welche Daten werden erhoben?</h3>
        <ul>
          <li><strong>Teilnehmerdaten:</strong> Vorname, Nachname, Klasse, Hauptgast-ID</li>
          <li><strong>Authentifizierung:</strong> Login-Code (verschlüsselt gespeichert)</li>
          <li><strong>Zahlungsinformationen:</strong> Bezahlstatus (keine Kreditkartendaten)</li>
          <li><strong>Sitzplatzpräferenzen:</strong> Ausgewählte Sitzplätze und Gruppenzuordnungen</li>
          <li><strong>Notizen:</strong> Selbst eingegebene Notizen im Dashboard</li>
          <li><strong>Server-Logs:</strong> IP-Adresse, Browser-Typ, Zugriffszeitpunkt</li>
        </ul>

        <h3 class="h6 mb-2 mt-3">2.2 Rechtsgrundlage</h3>
        <p>
          Die Verarbeitung erfolgt auf Grundlage von:
        </p>
        <ul>
          <li>Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung - Organisation des Abiballs)</li>
          <li>Art. 6 Abs. 1 lit. f DSGVO (Berechtigtes Interesse - Sicherheit und Betrieb)</li>
          <li>Art. 6 Abs. 1 lit. a DSGVO (Einwilligung - bei freiwilligen Angaben)</li>
        </ul>

        <hr class="my-4">

        <h2 class="h4 mb-3">3. Cookies und lokale Speicherung</h2>
        
        <h3 class="h6 mb-2">3.1 Technisch notwendige Cookies</h3>
        <p>Diese Website verwendet folgende Cookies:</p>
        <ul>
          <li><strong>Session-Cookie:</strong> Ermöglicht die Anmeldung und Session-Verwaltung (PHPSESSID)</li>
          <li><strong>Theme-Einstellung:</strong> Speichert Dark/Light Mode Präferenz (localStorage)</li>
          <li><strong>Cookie-Consent:</strong> Speichert deine Zustimmung zur Cookie-Nutzung (localStorage)</li>
        </ul>
        <p>
          Diese Cookies sind für die Funktionsfähigkeit der Website zwingend erforderlich und 
          können nicht deaktiviert werden.
        </p>

        <h3 class="h6 mb-2 mt-3">3.2 Keine Tracking-Cookies</h3>
        <p>
          Wir verwenden <strong>keine</strong> Tracking-Cookies von Drittanbietern (Google Analytics, 
          Facebook Pixel, etc.). Dein Nutzungsverhalten wird nicht nachverfolgt oder ausgewertet.
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">4. Datenweitergabe und Empfänger</h2>
        <p>
          Deine Daten werden <strong>nicht</strong> an Dritte weitergegeben, außer:
        </p>
        <ul>
          <li><strong>Hosting-Provider:</strong> Server-Betreiber zur technischen Bereitstellung</li>
          <li><strong>Türpersonal:</strong> Zugriff auf Teilnehmerliste zur Einlasskontrolle</li>
          <li><strong>Organisationsteam:</strong> Admin-Zugriff zur Verwaltung der Veranstaltung</li>
        </ul>
        <p>
          Eine kommerzielle Weitergabe oder Nutzung zu Werbezwecken erfolgt nicht.
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">5. Speicherdauer</h2>
        <p>
          Deine Daten werden gespeichert bis:
        </p>
        <ul>
          <li>Der Abiball stattgefunden hat (10.07.2026)</li>
          <li>Alle organisatorischen Nacharbeiten abgeschlossen sind (ca. 3 Monate nach Event)</li>
          <li>Du die Löschung deiner Daten verlangst (siehe Rechte)</li>
        </ul>
        <p>
          Danach werden alle personenbezogenen Daten gelöscht, sofern keine gesetzlichen 
          Aufbewahrungspflichten entgegenstehen.
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">6. Deine Rechte</h2>
        <p>Du hast folgende Rechte bezüglich deiner Daten:</p>
        <ul>
          <li><strong>Auskunftsrecht (Art. 15 DSGVO):</strong> Abfrage welche Daten gespeichert sind</li>
          <li><strong>Berichtigungsrecht (Art. 16 DSGVO):</strong> Korrektur falscher Daten</li>
          <li><strong>Löschungsrecht (Art. 17 DSGVO):</strong> Löschung deiner Daten ("Recht auf Vergessenwerden")</li>
          <li><strong>Einschränkung (Art. 18 DSGVO):</strong> Sperrung der Datenverarbeitung</li>
          <li><strong>Datenportabilität (Art. 20 DSGVO):</strong> Export deiner Daten in maschinenlesbarem Format</li>
          <li><strong>Widerspruchsrecht (Art. 21 DSGVO):</strong> Widerspruch gegen Datenverarbeitung</li>
        </ul>
        <p>
          Zur Ausübung deiner Rechte kontaktiere uns unter: 
          <a href="mailto:moris.kehl@gmail.com">moris.kehl@gmail.com</a>
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">7. Beschwerderecht</h2>
        <p>
          Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren, 
          wenn du der Ansicht bist, dass die Verarbeitung deiner Daten gegen die DSGVO verstößt.
        </p>
        <p>
          Zuständige Aufsichtsbehörde für Baden-Württemberg:<br>
          <strong>Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit</strong><br>
          Lautenschlagerstraße 20, 70173 Stuttgart<br>
          <a href="https://www.baden-wuerttemberg.datenschutz.de" target="_blank" rel="noopener">
            www.baden-wuerttemberg.datenschutz.de
          </a>
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">8. Datensicherheit</h2>
        <p>
          Wir setzen technische und organisatorische Sicherheitsmaßnahmen ein, um deine Daten zu schützen:
        </p>
        <ul>
          <li><strong>HTTPS-Verschlüsselung:</strong> Alle Datenübertragungen sind SSL/TLS-verschlüsselt</li>
          <li><strong>Passwort-Hashing:</strong> Login-Codes werden mit bcrypt verschlüsselt</li>
          <li><strong>CSRF-Protection:</strong> Schutz vor Cross-Site-Request-Forgery Angriffen</li>
          <li><strong>Rate-Limiting:</strong> Schutz vor Brute-Force Attacken</li>
          <li><strong>Zugriffsbeschränkung:</strong> Admin-Bereich durch separates Login geschützt</li>
        </ul>

        <hr class="my-4">

        <h2 class="h4 mb-3">9. Externe Dienste</h2>
        
        <h3 class="h6 mb-2">9.1 Bootstrap & Chart.js (CDN)</h3>
        <p>
          Wir verwenden Bootstrap CSS/JS und Chart.js von Content Delivery Networks (CDN). 
          Beim Laden dieser Ressourcen kann deine IP-Adresse an die CDN-Betreiber übermittelt werden.
        </p>

        <h3 class="h6 mb-2 mt-3">9.2 Keine sozialen Netzwerke</h3>
        <p>
          Es werden keine Social-Media-Plugins (Facebook Like, Twitter Share, etc.) eingebunden. 
          Deine Daten werden nicht mit sozialen Netzwerken geteilt.
        </p>

        <hr class="my-4">

        <h2 class="h4 mb-3">10. Änderungen dieser Datenschutzerklärung</h2>
        <p>
          Wir behalten uns vor, diese Datenschutzerklärung anzupassen, um sie an geänderte 
          Rechtslagen oder Funktionen der Website anzupassen. Die aktuelle Version findest du 
          jederzeit auf dieser Seite.
        </p>
        <p class="text-muted small mb-0">
          <strong>Stand:</strong> <?= date('d.m.Y') ?>
        </p>

      </div>
    </div>

    <div class="text-center mt-4">
      <a href="/" class="btn btn-outline-secondary">Zurück zur Startseite</a>
    </div>

  </div>
</main>

<?php
Layout::footer();
