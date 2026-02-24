# 🎓 Abiball-Portal 2026 (BSZ Leonberg)

Ein vollständiges Verwaltungssystem für die Organisation des Abiballs mit Ticketverkauf, Sitzplatzreservierung, Türkontrolle und Admin-Dashboard.

## 📋 Features

### 🎫 Teilnehmerverwaltung
- Hauptgäste (Schüler) und Begleitpersonen
- CSV-basierte Datenspeicherung
- Passwort-geschützte Anmeldung
- Ticket-Generierung mit QR-Codes


### 🍽️ Essensbestellungen
- Online-Bestellung von Essen für Teilnehmer und Begleitpersonen
- Verwaltung und Ausgabe von Essensbons (Food Helper Dashboard)
- QR-Code-Validierung bei Essensausgabe

### 💰 Preisgestaltung
- Admin-Überschreibungen für individuelle Preise
- Automatische Befreiungen (Kinder <4 Jahre, behinderte Personen)


### 🪑 Sitzplatzreservierung
- Interaktive Saalplanung
- Gruppenverwaltung
- Echtzeit-Verfügbarkeit

### 📍 Location & Anfahrt
- Interaktive Karte mit Leaflet zur Anzeige des Veranstaltungsorts
- Standortinformationen und Anfahrtsbeschreibung

### ❓ FAQ
- Häufig gestellte Fragen direkt im Portal abrufbar

### 🚪 Türkontrolle
- QR-Code Scanner mit ZXing
- Ticket-Validierung
- Separate Zugangssteuerung für Türpersonal

### 👨‍💼 Admin-Bereich
- Vollständige Teilnehmerverwaltung
- Dashboard mit Statistiken (Chart.js)
- Audit-Log für alle Änderungen
- PDF-Export von Zugangsdaten

## 🛠️ Technologie-Stack

- **Backend**: PHP 7.4+ (strict_types)
- **Frontend**: Bootstrap 5.3.3, Custom CSS mit Dark Mode
- **Libraries**:
  - Chart.js 4.4.1 (Statistiken)
  - Leaflet 1.9.4 (Karten, Location-Seite)
  - ZXing 0.21.0 (QR-Scanner für Tickets und Essensbons)
  - Dompdf (Ticket- und Bon-Generierung)
- **Server**: Apache mit mod_rewrite, mod_headers, mod_deflate
- **Datenbank**: CSV-Dateien (keine SQL-Datenbank erforderlich)

## 📁 Projektstruktur

```
abiball-portal/
├── public/              # Öffentlich zugängliche Dateien
│   ├── assets/          # CSS, JavaScript, Bilder
│   ├── *.php            # Endpunkte (login, dashboard, admin, food, faq, location, etc.)
│   ├── .htaccess        # Apache-Konfiguration
│   ├── robots.txt       # SEO
│   └── sitemap.xml      # SEO
├── src/                 # PHP-Klassen (PSR-4)
│   ├── Auth/            # Authentifizierung (Admin, User, Door, Food Helper)
│   ├── Controller/      # MVC-Controller (inkl. FAQ, Location, FoodOrder)
│   ├── Repository/      # Datenzugriff
│   ├── Security/        # CSRF, RateLimiter, Guards
│   ├── Service/         # Business-Logik
│   └── View/            # View-Helper und Layouts
├── storage/
│   ├── data/            # CSV-Dateien (participants, pricing_overrides, food_orders)
│   └── seating/         # JSON-Sitzplatzdaten
└── vendor/              # Composer-Dependencies
```

## 🚀 Installation & Setup

### Voraussetzungen
- PHP 7.4 oder höher
- Apache mit mod_rewrite aktiviert
- Composer

### Schritt 1: Repository klonen
```bash
git clone <repository-url>
cd abiball-portal
```

### Schritt 2: Dependencies installieren
```bash
composer install
```


### Schritt 3: CSRF-Secret konfigurieren
Erstelle die folgende Datei in `storage/secrets/`:

**csrf_secret.txt**
```
zufälliger-64-zeichen-string
```

### Schritt 4: Datenverzeichnisse vorbereiten
```bash
mkdir -p storage/data/audit
mkdir -p storage/seating
chmod 755 storage/data storage/seating
```

### Schritt 5: Webserver konfigurieren
Die `.htaccess` ist bereits konfiguriert. DocumentRoot auf `/public` setzen.

**Apache VirtualHost Beispiel:**
```apache
<VirtualHost *:80>
    ServerName abiball.local
    DocumentRoot /pfad/zu/abiball-portal/public
    
    <Directory /pfad/zu/abiball-portal/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Schritt 4: Teilnehmer und Passwörter anlegen
Alle Passwörter (Admin, Tür, Food Helper, Teilnehmer) werden in der Datei `storage/data/participants.csv` gespeichert.
Diese Datei hat das folgende Format (Beispiel):
```csv
id;name;is_main;main_id;login_code;role;amount_paid;password_changed;ticket_validated;validation_time;validation_person;amount_subsided
ADMIN00;Admin;1;ADMIN00;<hash>;ADMIN;;;;;;
DOOR01;"Eingang Team 1";1;DOOR01;<hash>;DOOR;0;;;;;
FOOD01;"Essensausgabe Team 1";1;FOOD01;<hash>;FOOD_HELPER;0;;;;;
WGW00S;"Thilo Breuer";1;WGW00S;<hash>;USER;0;;;;;
WGW00B1;"Isi Breuer";0;WGW00S;<code>;USER;;;;;;
```
Spaltenbeschreibung:
- `id`: Eindeutige ID
- `name`: Name der Person
- `is_main`: 1 = Hauptgast, 0 = Begleitperson
- `main_id`: ID des Hauptgasts (bei Begleitpersonen)
- `login_code`: Passwort-Hash oder Login-Code
- `role`: ADMIN, DOOR, FOOD_HELPER, USER
- `amount_paid`: Bezahlter Betrag
- `password_changed`: (optional) Zeitstempel, wenn Passwort geändert
- `ticket_validated`: (optional) Ticket validiert (1/0)
- `validation_time`: (optional) Zeitstempel der Validierung
- `validation_person`: (optional) Wer validiert hat
- `amount_subsided`: (optional) Zuschussbetrag
Weitere Benutzer (z.B. Türpersonal, Food Helper) werden ebenfalls als Einträge mit entsprechendem `role` und Passwort in dieser Datei gepflegt.

## 🔐 Sicherheit

- **CSRF-Schutz**: Alle Formulare verwenden CSRF-Tokens
- **Rate-Limiting**: Login-Versuche sind begrenzt
- **Session-Guards**: Separate Kontexte für Admin, User, Door
- **Security-Headers**: X-Frame-Options, CSP, X-Content-Type-Options
- **Passwort-Hashing**: bcrypt für alle Passwörter

## 📊 Admin- und Spezial-Zugänge

URL Admin-Login: `/admin_login.php`
URL Türkontrolle: `/door_login.php`
URL Food Helper: `/food_helper_login.php`

Benutzernamen und Passwörter werden ausschließlich in `storage/data/participants.csv` gepflegt. Siehe dort die jeweiligen Einträge für Admin, Türpersonal und Food Helper.

## 🎨 Design-System

Das Projekt nutzt CSS-Variablen für konsistentes Design:

```css
--gold: #c9a227;
--bg: #fbfbfd;
--text: #0b0b0f;
--border: #e8e8ea;
```

**Dark Mode**: Automatisch oder manuell umschaltbar (Toggle in der Navigation)

## 🧪 Entwicklung

### Code-Style
- PHP: `declare(strict_types=1)` in allen Dateien
- Indentation: 4 Spaces
- PSR-4 Autoloading für `src/`

### Neue Controller hinzufügen
1. Erstelle Controller in `src/Controller/`
2. Erweitere von `BaseController` (falls vorhanden) oder erstelle eigenständig
3. Erstelle zugehörige Views in `src/View/`
4. Verlinke im Layout (`src/View/Layout.php`)

### CSV-Repositories erweitern
Siehe `src/Repository/ParticipantsRepository.php` für Beispiele zur CSV-Verarbeitung.

## 📱 PWA-Support

Das Portal ist als Progressive Web App konfiguriert:
- `manifest.webmanifest` für Installation
- Offline-Unterstützung kann mit Service Worker erweitert werden

## 🐛 Debugging

### Fehlerseiten
- 404: Benutzerdefinierte Seite mit Navigation
- 500: Server-Fehlerseite mit Troubleshooting-Tipps

### Logs
Audit-Logs werden in `storage/data/audit/` gespeichert.

## 📈 Performance

- **Gzip-Kompression**: Aktiviert für CSS, JS, HTML
- **Browser-Caching**: 1 Jahr für Bilder, 1 Monat für CSS/JS
- **Asset-Optimierung**: Minimierte und kombinierte Dateien

## 🌐 SEO

- Meta-Tags für alle Seiten (Description, OG, Twitter)
- `robots.txt` und `sitemap.xml` konfiguriert
- Strukturierte Daten können hinzugefügt werden

## 📄 Lizenz

Internes Schulprojekt - Alle Rechte vorbehalten

## 📞 Kontakt

Bei Fragen oder Problemen: moris.kehl@gmail.com

## Quellen

- Readme - Claude Opus 4.6
- Coding - Claude Opus 4.6 / Gemini 3.1 Pro / Moris Kehl

---

**Letzte Aktualisierung**: 2026  
**Version**: 1.0.0
