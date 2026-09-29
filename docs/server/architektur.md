# Aufbau

## Warum PHP und MariaDB

Die Übergabe aus der Gestaltung empfahl Node oder Python mit SQLite. Gebaut ist PHP mit MariaDB, weil der Server ein CloudPanel-Host ist, auf dem PHP-FPM und MariaDB schon laufen: kein eigener Prozess, der abstürzen kann, kein Port, kein Build. Die Abfragen laufen auch auf SQLite, lokal wird damit entwickelt.

## Weg einer Anfrage

```
Browser oder Gerät
  -> Cloudflare (TLS, speichert auch HTML, deshalb überall Cache-Control: private, no-store)
  -> nginx :443 auf dem Host (CloudPanel-Vhost)
  -> Varnish -> nginx :8080
  -> gibt es die Datei? dann direkt (assets/, robots.txt), sonst try_files -> /index.php
  -> PHP-FPM 8.5: index.php -> .htapp/bootstrap.php -> app_run()
```

`app_run()` in `core/router.php`:

1. wandelt PHP-Warnungen in Ausnahmen
2. startet einen Ausgabepuffer, damit ein Abbruch mitten in einer Seite die Fehlerseite liefert statt einer halben Seite
3. sucht in `routes.php` die passende Route, lädt die Datei des Handlers und ruft die Funktion
4. `housekeeping()` räumt höchstens einmal pro Stunde alte Zeilen und Dateien weg, als Shutdown-Funktion am Ende jedes Aufrufs, auch nach einer JSON-Antwort
5. Fehler: `HttpError` wird zur Fehlerseite oder zu JSON, jede andere Ausnahme zu 500 mit Eintrag in `.htdata/logs`

JSON oder HTML entscheidet `wants_json()`: Pfade unter `/api/` und Anfragen mit `application/json` in `Accept` oder `Content-Type` bekommen JSON.

## Ordner und Zuständigkeiten

| Ordner | Regel |
| --- | --- |
| `core/` | kennt weder Konten noch Geräte, nur Werkzeuge |
| `auth/` | Konten, Sitzungen, CSRF, Einmal-Links. Darf `core/` benutzen |
| `services/` | einzige Stelle, die nach außen spricht. Jede Abfrage geht durch `services/upstream.php` (User-Agent, Zwischenspeicher, Budget) |
| `device/` | Geräte, Rechte, Zeichenbefehle, Firmware |
| `modes/` | ein Modus pro Datei, meldet sich mit `mode_register()` an |
| `pages/` | Controller für HTML-Seiten und Formular-POSTs |
| `api/` | JSON: `web.php` und `admin.php` für den Browser, `device.php` für das Gerät |
| `views/` | nur HTML, bekommt fertige Variablen |

`bootstrap.php` lädt `core`, `auth`, `services` und `device` in fester Reihenfolge und danach jede Datei in `modes/`. `pages/` und `api/` lädt erst der Router.

## Einen Modus ergänzen

Eine Datei `modes/<id>.php` mit `mode_register('<id>', [...])`, Felder siehe [geraet.md](geraet.md). Die Geräteseite zeigt ihn dann in der Liste, die Vorschau und das Gerät bekommen seine Seiten. Die Firmware ändert sich nicht, solange der Modus mit den vierzehn Zeichenbefehlen auskommt. Schritt für Schritt, mit Einstellungen, Prüfungen und Doku: [erweitern.md](erweitern.md). Die Einstellungen des Modus auf der Geräteseite brauchen Markup in `views/device.php` und Verhalten in `assets/js/pages/device.js`.

## Eine Seite ergänzen

1. Route in `routes.php`, feste Pfade vor Mustern mit `{id}`
2. Controller in `pages/<name>.php`: Rechte prüfen (`require_user()`, `require_admin()`), Daten laden, `page_open()`, `view()`, `page_close()`
3. View in `views/<name>.php`, deutsche Texte mit `de('...')` als `data-de`
4. Skript in `assets/js/pages/<name>.js`, nutzt `window.TW`
5. Jede verändernde Anfrage: `csrf_check()` und Rollenprüfung im Handler

## Datenbank

Schema in `migrations/001_init.mysql.sql` und `001_init.sqlite.sql`, beide gleich aufgebaut. `db_migrate()` spielt beim Verbindungsaufbau fehlende Dateien ein und merkt sich den Stand in der Tabelle `schema_migrations`, auf MariaDB unter `GET_LOCK`, damit zwei gleichzeitige Aufrufe nicht doppelt migrieren. Neue Änderungen kommen als `NNN_<name>.mysql.sql` und `NNN_<name>.sqlite.sql`, die Nummer vorn zählt; bisher `002_test_devices`, `003_tour`, `004_spotify` und `005_homeassistant`.

| Tabelle | Inhalt |
| --- | --- |
| `users` | Benutzername, E-Mail (klein), Passwort-Hash, Rolle `user` oder `admin`, API-Schlüssel, Sprache, bestätigt, `tour_seen_at`: wann die Einführung ins Gerät lief oder übersprungen wurde, `NULL` heißt noch nie (`003_tour`) |
| `sessions` | SHA-256 der Sitzungskennung, CSRF-Token, Ablauf |
| `tokens` | Einmal-Links als Hash: confirm, reset, share, signup |
| `devices` | Gerät je Besitzer und Geräte-ID, Einstellungen als JSON, Telemetrie aus den Kopfzeilen des Geräts, `test` für Testgeräte aus dem Admin-Bereich (`002_test_devices`) |
| `shares` | Freigaben: Gerät, Konto, Recht `view` oder `edit` |
| `settings` | Einstellungen des Projekts als JSON: registration, smtp, transit (Schlüssel für mobiliteit.lu, verschlüsselt), imprint, rollout, auto_update |
| `cache` | Zwischenspeicher für Abfragen nach außen, auch die Strecken aus den VRS-Standdaten (`vrs:<rufzeichen>`, 6 Stunden) |
| `callsigns` | Airline und Strecke aus adsbdb, 30 Tage, unbekannte einen Tag |
| `budget` | Abfragen je Dienst und Stunde, mit und ohne Zwischenspeicher: adsblol, adsbfi, vrs, adsbdb, meteo, nominatim, transit, spotify, spotifyimg |
| `events` | Meldungen für "Zuletzt schiefgegangen" im Admin-Bereich, 14 Tage |
| `rate_limits` | Zähler je Schlüssel und Zeitfenster |
| `firmware` | eingetragene Firmware-Dateien mit SHA-256 |

Zeiten sind Unix-Sekunden, Texte UTF-8 (`utf8mb4`), keine datenbankspezifischen Datumsfunktionen.

## Lokal und Server

| | lokal | Server |
| --- | --- | --- |
| Start | `tools/dev-server.sh`, setzt `TW_ENV=dev` | PHP-FPM über nginx |
| Datenbank | SQLite `.htdata/dev.sqlite` | MariaDB `thewall` |
| Adresse in Mails | Host der Anfrage | `url` aus der Konfiguration, Vorgabe `https://thewall.godart.lu` |
| Cookie | `__Host-tw_session`, Chrome erlaubt Secure auf 127.0.0.1 | dasselbe |
