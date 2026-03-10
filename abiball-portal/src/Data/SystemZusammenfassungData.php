<?php

declare(strict_types=1);

return array (
  0 => 
  array (
    'title' => '1. Was ist das System?',
    'shortTitle' => 'Was ist das System?',
    'html' => '<h2>1. Was ist das System?</h2>
<p>Das Abiball-Portal ist eine <strong>vollständige Webanwendung</strong> zur Organisation eines Abiballs. Es umfasst:</p>
<ul>
<li><strong>Ticketverkauf</strong> mit QR-Code-Generierung und PDF-Tickets</li>
<li><strong>Sitzplatzreservierung</strong> mit interaktiver Saalgruppenplanung</li>
<li><strong>Essensbestellungen</strong> (Online-Bestellung + Bon-Ausgabe beim Event)</li>
<li><strong>Türkontrolle</strong> (QR-Code-Scanner zur Ticketentwertung am Eingang)</li>
<li><strong>Admin-Dashboard</strong> mit Statistiken, Teilnehmerverwaltung und Audit-Log</li>
<li><strong>Lehrer-Voting</strong> (Abstimmung in 11 Kategorien)</li>
<li><strong>FAQ, Location, Datenschutz, Impressum</strong></li>
</ul>
<hr />',
  ),
  1 => 
  array (
    'title' => '2. Technologie-Stack',
    'shortTitle' => 'Technologie-Stack',
    'html' => '<h2>2. Technologie-Stack</h2>
<table>
<thead>
<tr>
<th>Komponente</th>
<th>Technologie</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Backend</strong></td>
<td>PHP 8.1+ (mit <code>strict_types</code>)</td>
</tr>
<tr>
<td><strong>Webserver</strong></td>
<td>Apache mit <code>mod_rewrite</code>, <code>mod_headers</code>, <code>mod_deflate</code></td>
</tr>
<tr>
<td><strong>Frontend</strong></td>
<td>HTML + Bootstrap 5.3.3 + Custom CSS (Dark Mode)</td>
</tr>
<tr>
<td><strong>Datenbank</strong></td>
<td><strong>Keine SQL-Datenbank!</strong> Stattdessen CSV-Dateien und JSON</td>
</tr>
<tr>
<td><strong>Charts</strong></td>
<td>Chart.js 4.4.1 (Admin-Dashboard)</td>
</tr>
<tr>
<td><strong>Karten</strong></td>
<td>Leaflet 1.9.4 (Location-Seite)</td>
</tr>
<tr>
<td><strong>QR-Codes</strong></td>
<td>ZXing 0.21.0 (Scanner) + Endroid QR-Code (Generierung)</td>
</tr>
<tr>
<td><strong>PDF-Erzeugung</strong></td>
<td>Dompdf 2.0</td>
</tr>
<tr>
<td><strong>Dependency-Management</strong></td>
<td>Composer (PHP-Paketmanager)</td>
</tr>
</tbody>
</table>

<hr />',
  ),
  2 => 
  array (
    'title' => '3. Architektur – Wie ist das System aufgebaut?',
    'shortTitle' => 'Architektur – Wie ist das System aufgebaut?',
    'html' => '<h2>3. Architektur – Wie ist das System aufgebaut?</h2>
<p>Das System folgt einem <strong>MVC-ähnlichen Pattern</strong> (Model-View-Controller):</p>
<p><img src="/images/sysinfo/architektur.png" alt="architektur" /></p>
<h3>Die 3 Schichten im Detail:</h3>
<table>
<thead>
<tr>
<th>Schicht</th>
<th>Aufgabe</th>
<th>Beispiel</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Entry Point</strong> (<code>public/*.php</code>)</td>
<td>Nimmt den HTTP-Request entgegen, ruft den Controller auf</td>
<td><code>login.php</code> → <code>AuthController::login()</code></td>
</tr>
<tr>
<td><strong>Controller</strong> (<code>src/Controller/</code>)</td>
<td>Steuert den Ablauf: Authentifizierung prüfen, Daten laden, View rendern</td>
<td><code>DashboardController::show()</code></td>
</tr>
<tr>
<td><strong>Repository</strong> (<code>src/Repository/</code>)</td>
<td>Abstrahiert den Dateizugriff auf CSV/JSON</td>
<td><code>ParticipantsRepository::all()</code></td>
</tr>
<tr>
<td><strong>Service</strong> (<code>src/Service/</code>)</td>
<td>Enthält die Business-Logik (Preisberechnung, Voting etc.)</td>
<td><code>PricingService</code>, <code>VotingService</code></td>
</tr>
<tr>
<td><strong>View</strong> (<code>src/View/</code>)</td>
<td>Rendert das HTML mit Layout, Navbar, Footer</td>
<td><code>Layout::header()</code>, <code>Layout::footer()</code></td>
</tr>
</tbody>
</table>
<hr />',
  ),
  3 => 
  array (
    'title' => '4. Request-Fluss – Was passiert bei einem Seitenaufruf?',
    'shortTitle' => 'Request-Fluss – Was passiert bei einem Seitenaufruf?',
    'html' => '<h2>4. Request-Fluss – Was passiert bei einem Seitenaufruf?</h2>
<h3>Beispiel: Ein Gast ruft <code>/dashboard.php</code> auf</h3>
<p><img src="/images/sysinfo/request_fluss.png" alt="request_fluss" /></p>
<h3>Schritt-für-Schritt:</h3>
<ol>
<li><strong>Browser</strong> sendet HTTP-Request an Apache</li>
<li>Apache leitet an <strong><code>public/dashboard.php</code></strong> weiter (DocumentRoot = <code>/public</code>)</li>
<li><code>dashboard.php</code> ist ein simpler Entry-Point:<pre><code class="language-php">require_once __DIR__ . \'/../src/Controller/DashboardController.php\';
DashboardController::show();</code></pre>
</li>
<li><strong><code>DashboardController::show()</code></strong> wird aufgerufen:<ul>
<li>Ruft <strong><code>Bootstrap::init()</code></strong> auf → Session starten, Error-Handling, Timezone</li>
<li>Ruft <strong><code>AuthContext::requireLogin()</code></strong> → prüft ob Nutzer eingeloggt ist</li>
<li>Lädt Daten über <strong><code>ParticipantsRepository</code></strong> aus der CSV</li>
<li>Rendert HTML mit <strong><code>Layout::header()</code></strong> und <strong><code>Layout::footer()</code></strong></li>
</ul>
</li>
<li>Der <strong>Response</strong> geht als HTML-Seite zurück zum Browser</li>
</ol>
<hr />',
  ),
  4 => 
  array (
    'title' => '5. Ordnerstruktur – Jede Datei erklärt',
    'shortTitle' => 'Ordnerstruktur – Jede Datei erklärt',
    'html' => '<h2>5. Ordnerstruktur – Jede Datei erklärt</h2>
<pre><code>Abiball/                                ← Projekt-Wurzel
├── README.md                           ← Projektdokumentation
├── .gitignore                          ← Dateien die Git ignoriert
├── Abiball.code-workspace              ← VS Code Workspace-Konfiguration
│
└── abiball-portal/                     ← Die eigentliche Anwendung
    ├── composer.json                   ← PHP-Dependencies (dompdf, endroid/qr-code)
    ├── composer.lock                   ← Fixierte Versionen der Dependencies
    ├── .env.example                    ← Beispielkonfiguration für Umgebungsvariablen
    │
    ├── public/                         ← 🌐 ÖFFENTLICHES VERZEICHNIS (DocumentRoot)
    ├── src/                            ← 🧠 PHP-KLASSEN (nicht direkt zugänglich)
    ├── storage/                        ← 📁 DATENSPEICHER (nicht direkt zugänglich)
    ├── vendor/                         ← 📦 Composer-Bibliotheken (automatisch generiert)
    └── deploy/                         ← 🚀 Deployment-Skripte</code></pre>
<hr />
<h3>5.1 <code>public/</code> – Öffentlich zugängliche Dateien</h3>
<blockquote>
<p>Alles in diesem Ordner ist über den Browser erreichbar. Apache zeigt auf diesen Ordner als DocumentRoot.</p>
</blockquote>
<pre><code>public/
├── .htaccess                    ← Apache: Caching-Regeln für Bilder, CSS, JS
├── index.php                    ← 🏠 Startseite → LandingController::show()
├── login.php                    ← 🔑 Gäste-Login → AuthController
├── logout.php                   ← 🚪 Gäste-Logout → AuthController::logout()
├── dashboard.php                ← 📊 Gäste-Dashboard → DashboardController::show()
├── faq.php                      ← ❓ FAQ-Seite → FaqController::show()
├── zahlung.php                  ← 💰 Zahlungsinfo → ZahlungController::show()
├── datenschutz.php              ← 🔒 Datenschutzerklärung (statisch)
├── impressum.php                ← 📄 Impressum → ImpressumController::show()
├── 404.php                      ← ⚠️ Fehlerseite "Nicht gefunden"
├── 500.php                      ← ⚠️ Server-Fehlerseite
├── manifest.webmanifest         ← 📱 PWA-Manifest (App-Icon, Name etc.)
├── sw.js                        ← 📱 Service Worker (Offline-Support)
├── robots.txt                   ← 🤖 SEO: Anweisungen für Suchmaschinen
├── sitemap.xml                  ← 🗺️ SEO: Seitenverzeichnis
│
├── assets/                      ← Statische Ressourcen
│   ├── css/
│   │   └── style.css            ← 🎨 Haupt-Stylesheet (68 KB, Dark Mode etc.)
│   └── js/
│       ├── form-loading.js      ← ⏳ Lade-Animationen für Formulare
│       ├── shooting-stars.js    ← ✨ Sternschnuppen-Animation (Hintergrund)
│       ├── sw-register.js       ← 📱 Service Worker Registration
│       └── ui-enhancements.js   ← 🖱️ UI-Verbesserungen (Animationen etc.)
│
├── images/                      ← Bilder (Logo etc.)
├── components/
│   └── darkmode-toggle.php      ← 🌙 Dark-Mode-Umschalter (inkludiert über PHP)
│
├── admin/                       ← 👨‍💼 Admin-Bereich
│   ├── admin_login.php          ← Admin-Login → AdminController::showLoginForm()
│   ├── admin_logout.php         ← Admin-Logout
│   ├── admin_dashboard.php      ← Admin-Dashboard (Statistiken, Teilnehmerliste)
│   ├── admin_update_paid.php    ← Bezahlten Betrag aktualisieren (POST)
│   ├── admin_override_save.php  ← Preisüberschreibung speichern (POST)
│   ├── admin_override_delete.php ← Preisüberschreibung löschen (POST)
│   ├── admin_bulk_override.php  ← Massenhafte Preisanpassung (POST)
│   ├── admin_create_main_guest.php ← Neuen Hauptgast anlegen (POST)
│   ├── admin_create_companion.php  ← Begleitperson anlegen (POST)
│   ├── admin_create_staff.php      ← Personal-Account anlegen (POST)
│   ├── admin_delete_participant.php ← Teilnehmer löschen (POST)
│   ├── admin_delete_staff.php       ← Personal-Account löschen (POST)
│   ├── admin_edit_participant_name.php ← Namen ändern (POST)
│   ├── admin_change_password.php    ← Admin-Passwort ändern (POST)
│   ├── admin_main_logins_pdf.php    ← Login-PDF exportieren
│   ├── admin_food_orders.php        ← Essensbestellungen verwalten
│   └── admin_food_order_update_paid.php ← Essensbezahlung markieren
│
├── door/                        ← 🚪 Türkontrolle
│   ├── door_login.php           ← Türpersonal-Login → DoorContext
│   ├── door_logout.php          ← Türpersonal-Logout
│   ├── door_dashboard.php       ← QR-Scanner + Gästeliste
│   └── door_validate_ticket.php ← Ticket-Validierung per QR
│
├── food/                        ← 🍽️ Essensbestellungen + Food Helper
│   ├── food_order.php           ← Essensbestellung anzeigen (Gast)
│   ├── food_order_create.php    ← Bestellung aufgeben (POST)
│   ├── food_order_cancel.php    ← Bestellung stornieren (POST)
│   ├── food_helper_login.php    ← Food-Helper-Login → FoodHelperContext
│   ├── food_helper_logout.php   ← Food-Helper-Logout
│   ├── food_helper_dashboard.php ← Übersicht aller Bestellungen
│   └── food_helper_redeem.php   ← Essensbon einlösen per QR
│
├── food_bon/                    ← 🎫 Essensbons
│   ├── pdf.php                  ← PDF mit Essensbon generieren
│   └── verify.php               ← Bon-QR-Code verifizieren
│
├── ticket/                      ← 🎟️ Tickets
│   ├── pdf.php                  ← PDF-Ticket mit QR-Code generieren
│   └── verify.php               ← Ticket-QR-Code verifizieren
│
├── seating/                     ← 🪑 Sitzplatzreservierung
│   ├── seating.php              ← Sitzplatz-Formular → SeatingController
│   └── seating_save.php         ← Sitzplatzgruppe speichern (POST)
│
├── voting/                      ← 🗳️ Lehrer-Voting
│   ├── index.php                ← Voting-Formular → VotingController
│   ├── save.php                 ← Stimme abgeben (POST)
│   ├── result.php               ← Ergebnisse anzeigen
│   └── exclude_save.php         ← Lehrer können sich ausschließen (POST)
│
├── location/                    ← 📍 Veranstaltungsort
│   └── location.php             ← Karte + Anfahrt → LocationController
│
└── dashboard/                   ← Sub-Aktionen des Gäste-Dashboards
    ├── dashboard_password_change.php ← Passwort ändern (POST)
    └── dashboard_notes_save.php      ← Notizen speichern (POST)</code></pre>
<hr />
<h3>5.2 <code>src/</code> – PHP-Klassen (das "Gehirn")</h3>
<blockquote>
<p>Dieser Ordner ist <strong>nicht</strong> über den Browser erreichbar. Die Dateien werden per <code>require_once</code> aus den Entry-Points eingebunden.</p>
</blockquote>
<pre><code>src/
├── Bootstrap.php                ← 🚀 Initialisierung der Anwendung
├── Config.php                   ← ⚙️ Zentrale Konfiguration
│
├── Auth/                        ← 🔐 Authentifizierungs-Kontexte
│   ├── AuthContext.php          ← Gäste-Session (Login, Logout, Timeout)
│   ├── AdminContext.php         ← Admin-Session
│   ├── DoorContext.php          ← Türkontrolle-Session
│   └── FoodHelperContext.php    ← Food-Helper-Session
│
├── Controller/                  ← 🎮 Controller (Steuerungslogik)
│   ├── AuthController.php       ← Gäste-Login/Logout
│   ├── LandingController.php    ← Startseite
│   ├── DashboardController.php  ← Gäste-Dashboard
│   ├── AdminController.php      ← Admin-Dashboard (96 KB – größte Datei!)
│   ├── FaqController.php        ← FAQ-Seite
│   ├── LocationController.php   ← Location-Seite mit Karte
│   ├── ZahlungController.php    ← Zahlungsinformationen
│   ├── ImpressumController.php  ← Impressum
│   ├── SeatingController.php    ← Sitzplatzreservierung
│   ├── FoodOrderController.php  ← Essensbestellungen
│   └── VotingController.php     ← Lehrer-Voting
│
├── Repository/                  ← 📚 Datenzugriff (CSV/JSON lesen/schreiben)
│   ├── CsvRepository.php        ← Basis-Klasse: CSV lesen/schreiben mit Locks
│   ├── ParticipantsRepository.php ← Gäste-Daten (CRUD auf participants.csv)
│   ├── FoodOrderRepository.php  ← Essensbestellungen (food_orders.csv)
│   ├── MenuRepository.php       ← Speisekarte (menu.json)
│   ├── PricingOverridesRepository.php ← Preisüberschreibungen
│   ├── SeatingRepository.php    ← Sitzgruppen (JSON-Dateien)
│   └── AdminAuditLogRepository.php ← Audit-Log (Änderungsprotokoll)
│
├── Security/                    ← 🛡️ Sicherheit
│   ├── Csrf.php                 ← CSRF-Token-Schutz für Formulare
│   ├── RateLimiter.php          ← Begrenzung von Login-Versuchen
│   ├── SessionGuard.php         ← Generischer Session-Schutz
│   ├── AdminGuard.php           ← Admin-spezifischer Guard
│   ├── TicketToken.php          ← QR-Code-Signierung für Tickets
│   └── FoodBonToken.php         ← QR-Code-Signierung für Essensbons
│
├── Service/                     ← ⚙️ Business-Logik
│   ├── PricingService.php       ← Preisberechnung (inkl. Ermäßigungen)
│   ├── PasswordService.php      ← Passwort-Hashing und -Änderung (Gäste)
│   ├── AdminPasswordService.php ← Passwort-Änderung (Admin)
│   ├── ParticipantService.php   ← Teilnehmer-bezogene Logik
│   ├── ParticipantAdminService.php ← Admin-Operationen auf Teilnehmern
│   ├── SeatingService.php       ← Sitzplatz-Gruppenlogik
│   ├── FoodOrderService.php     ← Essensbestellungslogik
│   ├── FoodBonService.php       ← Essensbon-Generierung
│   ├── TicketValidationService.php ← Ticket-Entwertung am Eingang
│   └── VotingService.php        ← Lehrer-Voting-Logik
│
├── Http/                        ← 🌐 HTTP-Utilities
│   ├── Request.php              ← Helper für $_POST, $_GET, IP-Erkennung
│   └── Response.php             ← Redirect-Funktionen
│
└── View/                        ← 🎨 Darstellung
    ├── Layout.php               ← HTML-Grundgerüst (Header, Footer, Meta-Tags)
    ├── Helpers.php              ← Hilfsfunktionen (z.B. HTML-Escaping e())
    ├── Location.php             ← Location-spezifische View-Daten
    └── Partials/
        ├── Navbar.php           ← Navigationsleiste
        └── Footer.php           ← Fußzeile</code></pre>
<hr />
<h3>5.3 <code>storage/</code> – Datenspeicher</h3>
<blockquote>
<p>Dieser Ordner ist per <code>.htaccess</code> vor direktem Webzugriff geschützt.</p>
</blockquote>
<pre><code>storage/
├── .htaccess                    ← Blockiert direkten Zugriff per Browser
├── data/
│   ├── participants.csv         ← 👥 ALLE Nutzer (Gäste, Admins, Tür, Food)
│   ├── participants.csv.lock    ← 🔒 Lock-Datei gegen Race-Conditions
│   ├── food_orders.csv          ← 🍽️ Essensbestellungen
│   ├── food_orders.csv.lock     ← 🔒 Lock-Datei
│   ├── pricing_overrides.csv    ← 💰 Manuelle Preisanpassungen
│   ├── pricing_overrides.csv.lock ← 🔒 Lock-Datei
│   ├── menu.json                ← 🍴 Speisekarte (Name, Preis, Beschreibung)
│   ├── seating_groups.json      ← 🪑 Globale Sitzgruppen
│   ├── voting/                  ← 🗳️ Voting-Daten (pro Kategorie)
│   └── audit/                   ← 📝 Änderungsprotokoll (Admin-Aktionen)
├── seating/                     ← 🪑 Individuelle Sitzplatz-JSONs pro Gast
├── secrets/                     ← 🔑 Passwörter, QR-Secret etc.
├── rate_limits/                 ← ⏱️ Rate-Limit-Daten (temporär)
└── logs/                        ← 📋 Log-Dateien</code></pre>
<hr />',
  ),
  5 => 
  array (
    'title' => '6. Das Authentifizierungssystem – 4 getrennte Kontexte',
    'shortTitle' => 'Das Authentifizierungssystem – 4 getrennte Kontexte',
    'html' => '<h2>6. Das Authentifizierungssystem – 4 getrennte Kontexte</h2>
<p>Das System hat <strong>4 verschiedene Benutzerrollen</strong> mit jeweils separater Anmeldung:</p>
<p><img src="/images/sysinfo/auth_kontexte.png" alt="auth_kontexte" /></p>
<table>
<thead>
<tr>
<th>Rolle</th>
<th>Login-URL</th>
<th>Auth-Context</th>
<th>Zugriff auf</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Gast</strong> (USER)</td>
<td><code>/login.php</code></td>
<td><code>AuthContext</code></td>
<td>Dashboard, Tickets, Sitzplätze, Essen, Voting</td>
</tr>
<tr>
<td><strong>Admin</strong></td>
<td><code>/admin/admin_login.php</code></td>
<td><code>AdminContext</code></td>
<td>Komplette Verwaltung</td>
</tr>
<tr>
<td><strong>Türkontrolle</strong> (DOOR)</td>
<td><code>/door/door_login.php</code></td>
<td><code>DoorContext</code></td>
<td>QR-Scanner + Gästeliste</td>
</tr>
<tr>
<td><strong>Food Helper</strong></td>
<td><code>/food/food_helper_login.php</code></td>
<td><code>FoodHelperContext</code></td>
<td>Essensbon-Einlösung</td>
</tr>
</tbody>
</table>
<blockquote>
<p>Alle Benutzer (inkl. Admin, Tür, Food Helper) werden in <strong>derselben CSV-Datei</strong> (<code>participants.csv</code>) gespeichert und durch die Spalte <code>role</code> unterschieden.</p>
</blockquote>
<hr />',
  ),
  6 => 
  array (
    'title' => '7. Sicherheitsmechanismen',
    'shortTitle' => 'Sicherheitsmechanismen',
    'html' => '<h2>7. Sicherheitsmechanismen</h2>
<table>
<thead>
<tr>
<th>Mechanismus</th>
<th>Datei</th>
<th>Funktion</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>CSRF-Schutz</strong></td>
<td><code>Security/Csrf.php</code></td>
<td>Jedes Formular enthält ein verstecktes Token, das bei POST-Requests validiert wird</td>
</tr>
<tr>
<td><strong>Rate-Limiting</strong></td>
<td><code>Security/RateLimiter.php</code></td>
<td>Max. 10 Login-Versuche pro Minute pro IP</td>
</tr>
<tr>
<td><strong>Session-Timeout</strong></td>
<td><code>Auth/*Context.php</code></td>
<td>Automatischer Logout nach 1 Stunde Inaktivität</td>
</tr>
<tr>
<td><strong>Session-Fixation-Schutz</strong></td>
<td><code>Bootstrap.php</code></td>
<td>Session-ID wird bei erstem Aufruf regeneriert</td>
</tr>
<tr>
<td><strong>Passwort-Hashing</strong></td>
<td>bcrypt (<code>password_hash</code>)</td>
<td>Passwörter werden niemals im Klartext gespeichert</td>
</tr>
<tr>
<td><strong>QR-Code-Signierung</strong></td>
<td><code>Security/TicketToken.php</code></td>
<td>QR-Codes werden mit HMAC-SHA256 signiert</td>
</tr>
<tr>
<td><strong>CSV-Injection-Schutz</strong></td>
<td><code>Repository/CsvRepository.php</code></td>
<td>Verhindert Formel-Injection in CSV-Dateien</td>
</tr>
<tr>
<td><strong>Atomares Schreiben</strong></td>
<td><code>Repository/CsvRepository.php</code></td>
<td>Lock-Dateien verhindern Race-Conditions</td>
</tr>
<tr>
<td><strong>HTTPS-Erkennung</strong></td>
<td><code>Config.php</code></td>
<td>Unterstützt Proxies und Cloudflare</td>
</tr>
<tr>
<td><strong>Sichere Cookies</strong></td>
<td><code>Bootstrap.php</code></td>
<td>HttpOnly, Secure, SameSite=Lax</td>
</tr>
</tbody>
</table>
<hr />',
  ),
  7 => 
  array (
    'title' => '8. Datenspeicherung im Detail',
    'shortTitle' => 'Datenspeicherung im Detail',
    'html' => '<h2>8. Datenspeicherung im Detail</h2>
<h3><code>participants.csv</code> – Die zentrale Datei</h3>
<pre><code class="language-csv">id;name;is_main;main_id;login_code;role;amount_paid;password_changed;ticket_validated;validation_time;validation_person
ADMIN00;Admin;1;ADMIN00;&lt;hash&gt;;ADMIN;;;;;
DOOR01;"Eingang Team 1";1;DOOR01;&lt;hash&gt;;DOOR;0;;;;
FOOD01;"Essensausgabe Team 1";1;FOOD01;&lt;hash&gt;;FOOD_HELPER;0;;;;
WGW00S;"Max Mustermann";1;WGW00S;&lt;hash&gt;;USER;0;;;;
WGW00B1;"Lisa Mustermann";0;WGW00S;&lt;code&gt;;USER;;;;;</code></pre>
<table>
<thead>
<tr>
<th>Spalte</th>
<th>Bedeutung</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>id</code></td>
<td>Eindeutige ID (z.B. <code>WGW00S</code> = Hauptgast, <code>WGW00B1</code> = 1. Begleiter)</td>
</tr>
<tr>
<td><code>name</code></td>
<td>Name der Person</td>
</tr>
<tr>
<td><code>is_main</code></td>
<td><code>1</code> = Hauptgast, <code>0</code> = Begleitperson</td>
</tr>
<tr>
<td><code>main_id</code></td>
<td>ID des zugehörigen Hauptgasts (Gruppierung)</td>
</tr>
<tr>
<td><code>login_code</code></td>
<td>Passwort-Hash (bcrypt)</td>
</tr>
<tr>
<td><code>role</code></td>
<td><code>USER</code>, <code>ADMIN</code>, <code>DOOR</code>, <code>FOOD_HELPER</code></td>
</tr>
<tr>
<td><code>amount_paid</code></td>
<td>Bereits bezahlter Betrag</td>
</tr>
<tr>
<td><code>ticket_validated</code></td>
<td>Wurde das Ticket am Eingang entwertet?</td>
</tr>
</tbody>
</table>
<h3><code>CsvRepository</code> – Wie Daten gelesen/geschrieben werden</h3>
<p><img src="/images/sysinfo/csv_repository.png" alt="csv_repository" /></p>
<blockquote>
<p><strong>Atomares Schreiben:</strong> Es wird erst in eine temporäre Datei geschrieben, dann umbenannt. So ist die Datei nie korrupt, selbst wenn der Server abstürzt.</p>
</blockquote>
<hr />',
  ),
  8 => 
  array (
    'title' => '9. Feature-Module im Überblick',
    'shortTitle' => 'Feature-Module im Überblick',
    'html' => '<h2>9. Feature-Module im Überblick</h2>
<h3>🎟️ Ticket-System</h3>
<ul>
<li><strong>Generierung:</strong> <code>ticket/pdf.php</code> → Dompdf + Endroid QR-Code</li>
<li><strong>QR-Inhalt:</strong> <code>TICKET:&lt;id&gt;:&lt;HMAC-Signatur&gt;</code> (fälschungssicher)</li>
<li><strong>Verifizierung:</strong> <code>ticket/verify.php</code> → Signatur prüfen</li>
<li><strong>Entwertung:</strong> <code>door/door_validate_ticket.php</code> → <code>TicketValidationService</code></li>
</ul>
<h3>🪑 Sitzplatzreservierung</h3>
<ul>
<li><strong>Formular:</strong> <code>seating/seating.php</code> → <code>SeatingController</code></li>
<li><strong>Speicherung:</strong> Pro Hauptgast eine JSON-Datei in <code>storage/seating/&lt;id&gt;.json</code></li>
<li><strong>Service:</strong> <code>SeatingService</code> verwaltet Gruppen-Logik</li>
</ul>
<h3>🍽️ Essensbestellungen</h3>
<ul>
<li><strong>Menü:</strong> <code>storage/data/menu.json</code> (Gerichte, Preise, Beschreibungen)</li>
<li><strong>Bestellen:</strong> <code>food/food_order.php</code> → <code>FoodOrderController</code></li>
<li><strong>Daten:</strong> <code>storage/data/food_orders.csv</code></li>
<li><strong>Bons:</strong> <code>food_bon/pdf.php</code> generiert PDF Essensbons mit QR-Code</li>
<li><strong>Einlösung:</strong> <code>food/food_helper_redeem.php</code> → QR scannen → Bon als eingelöst markieren</li>
</ul>
<h3>🗳️ Lehrer-Voting</h3>
<ul>
<li><strong>11 Kategorien</strong> (z.B. "Beliebtester Lehrer", "Lustigster Lehrer")</li>
<li><strong>Abstimmung:</strong> <code>voting/index.php</code> → <code>VotingController</code> → <code>VotingService</code></li>
<li><strong>Ergebnisse:</strong> Erst sichtbar nach der Deadline (18:00 Uhr am Abiball-Tag)</li>
<li><strong>Lehrer-Ausschluss:</strong> Lehrer können sich aus Kategorien ausschließen</li>
</ul>
<h3>👨‍💼 Admin-Dashboard</h3>
<ul>
<li><strong>Statistiken:</strong> Gesamteinnahmen, Bezahlstatus, Teilnehmeranzahl (Chart.js)</li>
<li><strong>Teilnehmerliste:</strong> Alle Gäste mit Suchfunktion</li>
<li><strong>CRUD-Operationen:</strong> Gäste anlegen, bearbeiten, löschen</li>
<li><strong>Preisüberschreibungen:</strong> Individuelle Preisanpassungen</li>
<li><strong>Audit-Log:</strong> Jede Admin-Aktion wird protokolliert</li>
</ul>
<hr />',
  ),
  9 => 
  array (
    'title' => '10. Wie verschachtelt sich alles? (Dependency-Kette)',
    'shortTitle' => 'Wie verschachtelt sich alles? (Dependency-Kette)',
    'html' => '<h2>10. Wie verschachtelt sich alles? (Dependency-Kette)</h2>
<p><img src="/images/sysinfo/dependency_kette.png" alt="dependency_kette" /></p>
<hr />',
  ),
  10 => 
  array (
    'title' => '11. Projekt-Metriken',
    'shortTitle' => 'Projekt-Metriken',
    'html' => '<h2>11. Projekt-Metriken</h2>
<table>
<thead>
<tr>
<th>Metrik</th>
<th>Wert</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Anzahl Dateien</strong></td>
<td>116</td>
</tr>
<tr>
<td><strong>Lines of Code (LoC)</strong></td>
<td>21.301</td>
</tr>
<tr>
<td><strong>Wörter</strong></td>
<td>62.224</td>
</tr>
<tr>
<td><strong>Zeichen</strong></td>
<td>764.755</td>
</tr>
</tbody>
</table>
<blockquote>
<p>[!NOTE]
Die Metriken beziehen sich auf das gesamte Projekt-Verzeichnis <code>abiball-portal</code> exklusive des <code>vendor</code>-Ordners und temporärer Dateien.</p>
</blockquote>
<hr />',
  ),
  11 => 
  array (
    'title' => '12. Zusammenfassung der Kernkonzepte',
    'shortTitle' => 'Zusammenfassung der Kernkonzepte',
    'html' => '<h2>12. Zusammenfassung der Kernkonzepte</h2>
<table>
<thead>
<tr>
<th>Konzept</th>
<th>Umsetzung</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Architekturmuster</strong></td>
<td>MVC-ähnlich (Controller + Repository + View)</td>
</tr>
<tr>
<td><strong>Datenspeicherung</strong></td>
<td>CSV (Semikolon-getrennt) + JSON, keine SQL-Datenbank</td>
</tr>
<tr>
<td><strong>Authentifizierung</strong></td>
<td>4 separate Session-Kontexte, bcrypt-Passwörter</td>
</tr>
<tr>
<td><strong>Sicherheit</strong></td>
<td>CSRF, Rate-Limiting, Session-Timeout, signierte QR-Codes</td>
</tr>
<tr>
<td><strong>PDF-Generierung</strong></td>
<td>Dompdf für Tickets und Essensbons</td>
</tr>
<tr>
<td><strong>QR-Codes</strong></td>
<td>Endroid für Generierung, ZXing für Browser-Scanner</td>
</tr>
<tr>
<td><strong>Frontend</strong></td>
<td>Bootstrap 5 + Custom CSS mit Dark Mode + Animationen</td>
</tr>
<tr>
<td><strong>Deployment</strong></td>
<td>Nur PHP + Apache nötig, kein DB-Setup erforderlich</td>
</tr>
</tbody>
</table>',
  ),
);
