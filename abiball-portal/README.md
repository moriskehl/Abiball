# Abiball 2026 Portal - BSZ Leonberg

Das offizielle Web-Portal für den Abiball 2026 des Beruflichen Schulzentrums Leonberg. Diese Anwendung verwaltet Ticketverkäufe, Sitzplatzreservierungen, Essensbestellungen und bietet ein umfangreiches Admin-Dashboard.

## Features

### Für Gäste
- **Ticket-System:** Persönlicher Login mit Code, PDF-Ticket-Generierung (QR-Code).
- **Sitzplatzreservierung:** Interaktive Auswahl von Sitzgruppen und Plätzen.
- **Essensbestellung:** Auswahl von Menüs, Überweisungsinformationen und Bon-Download.
- **Dashboard:** Zentrale Übersicht über Status, Zahlungen und Termine.
- **Begleitpersonen:** Anmeldung und Verwaltung von Begleitpersonen.

### Für Administratoren
- **Teilnehmerverwaltung:** Liste aller Gäste, Suchfunktion, Bearbeitung von Daten.
- **Finanzen:** Übersicht über offene und bezahlte Beträge.
- **Staff-Accounts:** Erstellung von Accounts für Helfer (Einlass, Essensausgabe).
- **Preisanpassung (Bulk Override):** Massenänderung von Ticketpreisen für unbezahlte Tickets (z.B. Erhöhung ab Stichtag).
- **Audit-Log:** Nachvollziehbare Protokollierung aller Admin-Aktionen.

## Technologie-Stack

- **Backend:** PHP 8.2+ (Kein Framework, native Implementierung)
- **Frontend:** HTML5, CSS3 (Custom Design System), JavaScript (Vanilla)
- **Datenhaltung:** CSV-Dateien (in `storage/data/`) für einfache Handhabung ohne komplexe Datenbank.
- **Sicherheit:** CSRF-Schutz, Passwort-Hashing (Argon2/Bcrypt), Rate-Limiting.

## Installation & Setup

1. **Voraussetzungen:** PHP 8.2 oder höher installiert.
2. **Projekt starten:**
   ```bash
   php -S localhost:8000 -t public
   ```
3. **Im Browser öffnen:** [http://localhost:8000](http://localhost:8000)

## Projektstruktur

- `public/`: Öffentlich zugängliche Dateien (Einstiegspunkte, Assets).
- `src/`: Quellcode (Controller, Services, Repositories, View-Logik).
- `storage/`: Datenverzeichnis (CSV-Dateien, Logs).
- `deploy/`: Skripte für das Deployment.

## Wichtige Hinweise

- **Datenbank:** Die Daten liegen in CSV-Dateien. Backups dieser Dateien sind essentiell.
- **Preiserhöhung:** Die Preiserhöhung auf 20€ ist ab dem 15.02.2026 im Admin-Bereich aktivierbar.
