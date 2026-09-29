# Webseite und Server

Die Webseite unter thewall.godart.lu und die Schnittstelle, die das Gerät alle zehn Sekunden abfragt. Ein Programm für beides: PHP 8.5 ohne Framework und ohne Composer, MariaDB auf dem Server, SQLite lokal. Der Code liegt in `web/` und wird 1:1 ins Webroot kopiert.

**Diese Dateien werden bei jeder Änderung am Code im selben Commit mitgezogen.** Wer eine Datei verschiebt, eine Funktion umbenennt oder eine Signatur ändert, sucht den alten Namen in allen `README.md` und `docs/server/*.md` und korrigiert ihn dort.

## Bereiche

| Bereich | Datei |
| --- | --- |
| Aufbau, Weg einer Anfrage, Ordner | [architektur.md](architektur.md) |
| `core/`: Konfiguration, Datenbank, HTTP, Router, Ansicht, Zeitzonen, Panel-Text | [core.md](core.md) |
| `auth/`: Konten, Sitzungen, CSRF, Einmal-Links, dazu das Sicherheitsmodell | [auth.md](auth.md) |
| `services/`: adsb.lol, adsbdb, Flugzeugtypen, Open-Meteo, Nominatim (Suche und Ort unter dem Flugzeug), mobiliteit.lu mit allen Haltestellen des Landes, SMTP, Logos | [services.md](services.md) |
| `device/` und `modes/`: Geräte, Rechte, Zeichenbefehle, Firmware, Geräteschnittstelle | [geraet.md](geraet.md) |
| `routes.php`, `pages/`, `views/`, `api/web.php`, `api/admin.php` | [seiten.md](seiten.md) |
| `assets/`: JavaScript, CSS, Leaflet | [frontend.md](frontend.md) |
| Hosting, Deploy, erste Einrichtung, Mailversand, lokale Entwicklung, Prüfskripte | [betrieb.md](betrieb.md) |
| Neue Funktionen bauen: Modus, Einstellung, Zeichenbefehl mit Firmware, Home Assistant, Webseite | [erweitern.md](erweitern.md) |

## Ordner in `web/`

```
web/
  index.php            lädt .htapp/bootstrap.php und startet app_run()
  .htaccess            nur für Apache: alles auf index.php, .ht* gesperrt
  robots.txt           /device, /settings, /admin, /api/, /map ausgeschlossen
  assets/              öffentlich: css/, js/, favicon.svg, vendor/leaflet/
  .htapp/              Programm, von nginx gesperrt (location ~ /\.(ht|svn|git))
    bootstrap.php      Konstanten, lädt alle Module in fester Reihenfolge
    routes.php         alle Adressen, Handler als "datei:funktion"
    config.example.php Vorlage für .htdata/config.php
    core/              Werkzeuge ohne Fachwissen
    auth/              Konten, Sitzungen, CSRF, Einmal-Links
    services/          alles, was nach außen spricht
    device/            Geräte, Einstellungen, Zeichenbefehle, Firmware
    modes/             ein Modul pro Anzeigemodus
    pages/             Seiten für den Browser (Controller)
    api/               JSON für Browser (web.php, admin.php) und Gerät (device.php)
    views/             HTML der Seiten, Werte aus den Entwürfen als Inline-Styles
    mail/              die drei Mails aus den Entwürfen unverändert, dazu Test und Export, je mit .txt
    migrations/        Schema je Datenbank (mysql, sqlite)
    logos/logos.json   Airline-Bitmaps, eingetragene Marken, nie öffentlich
  .htdata/             nur auf dem Server und lokal, nie im Repo
    config.php         Datenbankzugang (Vorlage .htapp/config.example.php)
    secret.php         vom Server erzeugt: app_key, setup_token
    firmware/          thewall-<version>.bin für OTA
    transit/           letzte Datenprobe von mobiliteit.lu (sample-latest.json)
    logs/, locks/, exports/, dev.sqlite (nur lokal)
```

## Gestaltung

Inline-Styles stehen wörtlich wie im Entwurf im Markup der Views, deutsche Texte in `data-de`. Was inline nicht geht (Hover, Fokus, Zustände über `aria-*`, Keyframes, reduzierte Bewegung) steht in `assets/css/base.css`. Schriften kommen von Bunny Fonts, Überschriften und Wortmarke zeichnet `pixelfont.js` auf Canvas.

## Sicherheit, kurz

- Ein Cookie (`__Host-tw_session`), HttpOnly, Secure, SameSite=Lax, in der Datenbank nur als SHA-256
- CSRF: Herkunft (Origin, Sec-Fetch-Site) und Sitzungstoken auf jeder verändernden Anfrage
- Rollen am Gerät prüft der Server: Gäste mit Leserecht ändern nur Notizen
- Rate-Limits auf Anmeldung, Registrierung, Passwort-Link, Vorschau, Geräteschnittstelle
- Content-Security-Policy mit `script-src 'self'`, keine Inline-Skripte
- SMTP-Schlüssel verschlüsselt, der Schlüssel dafür liegt in `.htdata/secret.php`, nicht in der Datenbank
- `.htapp` und `.htdata` sind von außen gesperrt, geprüft mit HTTP 403

Ausführlich in [auth.md](auth.md).

## Git

- `web/.htdata/` ist ausgeschlossen (`.gitignore`), dort liegen Zugangsdaten, Geheimnisse und die lokale Datenbank
- Airline-Logos sind eingetragene Marken und liegen nur auf dem Server, siehe [betrieb.md](betrieb.md)
- Nach jeder inhaltlichen Änderung committen und pushen, Doku im selben Commit

