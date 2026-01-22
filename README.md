# 🎓 Abiball-Portal 2026 (BSZ Leonberg)

Ein vollständiges Verwaltungssystem für die Organisation des Abiballs mit Ticketverkauf, Sitzplatzreservierung, Türkontrolle und Admin-Dashboard.

## 📋 Features

### 🎫 Teilnehmerverwaltung
- Hauptgäste (Schüler) und Begleitpersonen
- CSV-basierte Datenspeicherung
- Passwort-geschützte Anmeldung
- Ticket-Generierung mit QR-Codes

### 💰 Preisgestaltung
- Flexible Preisstaffelung nach Teilnehmertyp
- Admin-Überschreibungen für individuelle Preise
- Automatische Befreiungen (Kinder <4 Jahre, behinderte Personen)

### 🪑 Sitzplatzreservierung
- Interaktive Saalplanung
- Gruppenverwaltung
- Echtzeit-Verfügbarkeit

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
  - Leaflet 1.9.4 (Karten)
  - ZXing 0.21.0 (QR-Scanner)
  - Dompdf (Ticket-Generierung)
- **Server**: Apache mit mod_rewrite, mod_headers, mod_deflate
- **Datenbank**: CSV-Dateien (keine SQL-Datenbank erforderlich)

## 📁 Projektstruktur

```
abiball-portal/
├── public/              # Öffentlich zugängliche Dateien
│   ├── assets/          # CSS, JavaScript, Bilder
│   ├── *.php            # Endpunkte (login, dashboard, admin, etc.)
│   ├── .htaccess        # Apache-Konfiguration
│   ├── robots.txt       # SEO
│   └── sitemap.xml      # SEO
├── src/                 # PHP-Klassen (PSR-4)
│   ├── Auth/            # Authentifizierung (Admin, User, Door)
│   ├── Controller/      # MVC-Controller
│   ├── Repository/      # Datenzugriff
│   ├── Security/        # CSRF, RateLimiter, Guards
│   ├── Service/         # Business-Logik
│   └── View/            # View-Helper und Layouts
├── storage/
│   ├── data/            # CSV-Dateien (participants, pricing_overrides)
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

### Schritt 3: Secrets konfigurieren
Erstelle die folgenden Dateien in `storage/secrets/`:

**admin_password.txt**
```
dein-sicheres-admin-passwort
```

**door_password.txt**
```
dein-türkontrolle-passwort
```

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

### Schritt 6: Erste Teilnehmer importieren
Die Datei `storage/data/participants.csv` sollte folgendes Format haben:
```csv
ID,Vorname,Nachname,Klasse,Passwort,PaidAmount
ABI001,Max,Mustermann,12A,passwort123,0
```

## 🔐 Sicherheit

- **CSRF-Schutz**: Alle Formulare verwenden CSRF-Tokens
- **Rate-Limiting**: Login-Versuche sind begrenzt
- **Session-Guards**: Separate Kontexte für Admin, User, Door
- **Security-Headers**: X-Frame-Options, CSP, X-Content-Type-Options
- **Passwort-Hashing**: bcrypt für alle Passwörter

## 📊 Admin-Zugang

URL: `/admin_login.php`

Standard-Benutzername: `admin`  
Passwort: Siehe `storage/secrets/admin_password.txt`

## 🚪 Türkontrolle-Zugang

URL: `/door_login.php`

Benutzername: `door`  
Passwort: Siehe `storage/secrets/door_password.txt`

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

## 🤝 Contributing

1. Feature-Branch erstellen
2. Änderungen committen
3. Pull Request öffnen

## 📄 Lizenz

Internes Schulprojekt - Alle Rechte vorbehalten

## 📞 Kontakt

Bei Fragen oder Problemen: moris.kehl@gmail.com

---

**Letzte Aktualisierung**: 2024  
**Version**: 1.0.0
