# Door Personnel - Ticket-Entwertungssystem

## Übersicht

Das Door Personnel System ermöglicht es Mitarbeitern an der Tür, gültige Tickets von Gästen beim Einlass zu entwerten. Dies verhindert:
- Doppeltverwertung von Tickets
- Versehentliche Entwertung durch Gäste selbst
- Unbezahlte oder ungültige Tickets werden nicht entwertet

## Features

✓ **Kamera-basierter QR-Code Scanner** - Schnelle und kontaktlose Erfassung
✓ **Echtzeit-Validierung** - Prüfung von Bezahlung und Gültigkeit
✓ **Audio-Rückmeldung** - Bestätigungstöne für Erfolg/Fehler
✓ **Audit-Log** - Alle Entwertungen werden protokolliert
✓ **Session-Timeout** - Automatisches Abmelden nach 30 Minuten Inaktivität
✓ **Responsive Design** - Funktioniert auf Tablets und Mobilgeräten

## Rollen & Berechtigungen

### DOOR - Door Personnel
- Kann nur QR-Codes scannen und Tickets entwerten
- Kein Zugriff auf Admin-Panel
- Kein Zugriff auf persönliche Teilnehmerdaten (außer für Scan-Feedback)
- Automatisches Abmelden nach 30 Minuten

### USER - Normale Teilnehmer
- Können ihre Tickets prüfen
- Können Tickets nicht selbst entwerten

### ADMIN - Administratoren
- Vollständiger Zugriff
- Können Entwertungen rückgängig machen (zukünftige Funktion)

## Verwendung

### 1. Anmeldung
- Besuchen Sie: `https://abiball.local/door_login.php`
- Geben Sie den Login-Code ein: `DOOR1234` (Standard-Beispiel)
- Login-Code wird dem Door-Personal vom Administrator mitgeteilt

### 2. QR-Code scannen
1. Klicken Sie auf "📹 Kamera starten"
2. Geben Sie der Website Zugriff auf Ihre Kamera
3. Halten Sie das Ticket mit QR-Code in die Kamera
4. Der Code wird automatisch erkannt und validiert
5. Ergebnis wird angezeigt (grün = erfolgreich, rot = Fehler)

### 3. Abmeldung
- Klicken Sie auf "Abmelden" um die Session zu beenden
- Session läuft auch automatisch nach 30 Minuten ab

## CSV-Struktur

Die Datei `storage/data/participants.csv` wurde um diese Felder erweitert:

```
id;name;is_main;main_id;login_code;role;amount_paid;password_changed;ticket_validated;validation_time;validation_person;;amount_subsided
```

### Neue Felder:
- `ticket_validated` - Status (0 = nicht entwertet, 1 = entwertet)
- `validation_time` - Zeitstempel der Entwertung (z.B. "2025-01-16 20:34:15")
- `validation_person` - ID der Door-Person, die das Ticket entwertet hat

## API-Endpoints

### POST /door_validate_ticket.php

Entwertet ein Ticket durch einen QR-Code-Scan.

**Request Body:**
```json
{
  "pid": "ABI00S",
  "sig": "abc123def456..."
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Ticket erfolgreich entwertet",
  "person_name": "Max Mustermann",
  "person_id": "ABI00S",
  "main_name": "Max Mustermann",
  "valid_until": "2025-01-16 20:34:15"
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Zahlung unvollständig – Ticket ist ungültig"
}
```

## Validierungsregeln

Ein Ticket wird nur entwertet wenn:
1. ✓ QR-Code-Signatur gültig ist (HMAC-SHA256)
2. ✓ Ticket existiert in der Datenbank
3. ✓ Ticket noch nicht entwertet (ticket_validated = 0)
4. ✓ Zahlung vollständig (open amount = 0)
5. ✓ Gast existiert in der Gruppe
6. ✓ Alle Überprüfungen erfolgreich

## Fehlerbehandlung

| Fehler | Ursache | Lösung |
|--------|---------|--------|
| "Ticket nicht gefunden" | QR-Code ungültig/beschädigt | Neuer QR-Code erforderlich |
| "Ticket bereits entwertet" | Ticket wurde bereits gescannt | Doppelter Eintritt verhindern |
| "Zahlung unvollständig" | Gast hat nicht bezahlt | Zahlung abrechnen bevor Einlass |
| "Kamerazugriff verweigert" | Browser-Berechtigung fehlt | In Browser-Einstellungen aktivieren |

## Audit-Log

Alle Entwertungen werden in `storage/data/audit/validation_log.csv` protokolliert:

```
timestamp;ticket_id;main_id;door_person_id;action;ip_address
2025-01-16 20:34:15;ABI00S;ABI00S;DOOR001;VALIDATED;192.168.1.100
```

## Neue Door-Person hinzufügen

Um eine neue Door-Person hinzuzufügen:

1. Öffnen Sie `storage/data/participants.csv`
2. Fügen Sie eine neue Zeile hinzu:
```
DOOR002;Name;1;DOOR002;GEHEIMER_CODE;DOOR;;;;;
```

3. Geben Sie der Person den Login-Code (z.B. GEHEIMER_CODE)
4. Fertig!

## QR-Code Erstellung

Der QR-Code muss folgendes Format haben:
```
TICKET_ID|SIGNATUR
```

Beispiel:
```
ABI00S|e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

Die Signatur wird mit HMAC-SHA256 erstellt:
```php
TicketToken::sign('ABI00S');
```

## Datenschutz & Sicherheit

✓ Entwertungs-Anfragen erfordern gültigen QR-Code-Signature
✓ DOOR-Accounts können nur Tickets entwerten, nicht sehen
✓ Alle Entwertungen werden mit Timestamp & IP-Adresse geloggt
✓ Session-Timeout verhindert unbeaufsichtigte Accounts
✓ Atomare CSV-Updates verhindern Datenkorruption

## Troubleshooting

### Kamera funktioniert nicht
- Browser Berechtigung prüfen
- HTTPS erforderlich (bei localhost HTTP ok)
- Anderes Gerät versuchen

### QR-Code wird nicht erkannt
- QR-Code nicht zu klein halten
- Helles Licht für bessere Erkennung
- QR-Code-Mitte in Kameramitte

### Login schlägt fehl
- Login-Code korrekt prüfen
- CAPS LOCK deaktivieren
- Admin um neuen Code bitten

## Support

Kontakt: Abiball Administrator
