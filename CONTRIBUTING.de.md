# Bei THE WALL mitmachen

[English](CONTRIBUTING.md)

Beiträge sind willkommen: neue Modi fürs Panel, Fehlerbehebungen, bessere Doku, Arbeit an der Firmware. Diese Seite erklärt, welchen Weg ein Beitrag nimmt, wie alles lokal läuft, ohne Hardware und ohne Schlüssel, und welche Prüfungen bestehen müssen.

Die technische Doku in [`docs/server/`](docs/server) und die Projektregeln in [`CLAUDE.md`](CLAUDE.md) sind auf Deutsch. Die Rezepte für neue Funktionen, Schritt für Schritt, stehen in [`docs/server/erweitern.md`](docs/server/erweitern.md). Wer mit einem KI-Assistenten arbeitet, gibt ihm zuerst diese beiden Dateien. Claude Code liest `CLAUDE.md` von selbst.

## Der Weg eines Beitrags

1. Das Repo forken, einen Branch anlegen, ändern. Code, Doku und Prüfungen ändern sich zusammen.
2. Einen Pull Request gegen `main` öffnen. Darin steht, was sich ändert und warum. Bei allem, was aufs Panel geht, ein Bild dazu, gemacht mit `node tools/panel-png.mjs frame.json out.png`.
3. Die Prüfungen aus `.github/workflows/checks.yml` laufen bei jedem Pull Request. Beim ersten Pull Request eines neuen Mitwirkenden wartet der Lauf, bis der Projektinhaber ihn freigibt.
4. Der Projektinhaber sieht ihn durch, merged ihn und bringt ihn auf thewall.godart.lu. Entwickelt wird diese Instanz in einem privaten Werkstatt-Repo: gemergte Pull Requests kommen dorthin, spätere Änderungen kommen hierher zurück als Commits mit dem Namen "Update from the workshop".
5. Größere Ideen zuerst als Issue, damit niemand dasselbe zweimal baut.

Mit einem Beitrag erklärst du dich einverstanden, dass er unter den Lizenzen dieses Repos erscheint: MIT mit Commons Clause für Code, CC BY-NC-SA 4.0 für Doku und Hardware, reine MIT für `homeassistant/`. Siehe [`LICENSE`](LICENSE).

## Lokal starten

Alles läuft auf einem Rechner mit SQLite. Keine Hardware, keine Schlüssel, keine Konfigurationsdatei.

Nötig sind:

- PHP 8.5 als Kommandozeilenprogramm, mit pdo_sqlite, mbstring, curl, sodium, openssl, intl, gd und zlib
- Node 18 oder neuer für die Werkzeuge
- PlatformIO nur für die Firmware, Python 3.14 nur für die Integration in Home Assistant

**Unter Windows** das Zip "VS17 x64 Non Thread Safe" von [windows.php.net](https://windows.php.net/download/) nehmen, entpacken, `php.ini-development` nach `php.ini` kopieren und das Semikolon vor `extension_dir = "ext"` und vor den Zeilen `extension=curl`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_sqlite` und `sodium` entfernen. PHP unter Windows kennt keine Zertifizierungsstellen, Abrufe nach außen scheitern deshalb, bis `curl.cainfo` und `openssl.cafile` auf eine Liste zeigen, etwa `cacert.pem` von [curl.se](https://curl.se/docs/caextract.html). Die Befehle unten laufen in Git Bash, das mit Git für Windows kommt. Ist `php` nicht die richtige Version, sagt man dem Startskript, welche: `PHP_BIN=/c/pfad/zu/php.exe bash tools/dev-server.sh`.

### Server und Webseite

```
bash tools/dev-server.sh
```

Die Seite läuft unter http://127.0.0.1:8765, die Datenbank ist `web/.htdata/dev.sqlite` und entsteht beim ersten Aufruf. Im Browser 127.0.0.1 oder localhost nehmen, keine Netzwerkadresse: das Sitzungscookie ist ein sicheres Cookie, und über einfaches HTTP nehmen Browser es nur für diese beiden an.

Das erste Konto:

1. http://127.0.0.1:8765/account einmal öffnen. Solange es kein Konto gibt, legt das `web/.htdata/secret.php` an.
2. `setup_token` aus dieser Datei nehmen und `http://127.0.0.1:8765/account?mode=register&setup=<token>` öffnen. Das erste Konto wird Admin und ist sofort bestätigt.

Ohne SMTP ist jedes neue Konto sofort bestätigt, Mails gehen keine raus.

### Ein Gerät ohne Hardware

- **Testgerät:** im Admin-Bereich unter "Konten", Spalte "Testgerät". Es fragt nie, aber Geräteseite, alle Einstellungen und die Vorschau funktionieren.
- **Nachgestelltes Gerät:** fragen wie die Firmware, mit dem API-Schlüssel aus `/settings`:

  ```
  curl -s http://127.0.0.1:8765/api/v1/frame -H "Authorization: Bearer <API-Schlüssel>" -H "X-Wall-Id: wall-dev01" -H "X-Wall-Fw: 0.2.1" > frame.json
  node tools/panel-png.mjs frame.json out.png
  ```

  Der erste Abruf legt das Gerät an. Es zeigt eine Begrüßung, bis auf seiner Geräteseite einmal "Übernehmen" gedrückt wurde. Höchstens ein Frame pro Sekunde und Gerät. Beim ersten Öffnen der Geräteseite eines Geräts, das sich gemeldet hat, erscheint eine kurze Einführung, "Später" schließt sie. Ein neues Gerät wechselt alle 30 Sekunden zwischen Flugradar und Uhr (Rotation). Wer nur den gewählten Modus sehen will, nimmt die anderen auf der Geräteseite aus der Rotation.

### Dienste von außen

| Dienst | Lokal |
| --- | --- |
| adsb.lol, adsbdb, Open-Meteo, Nominatim | brauchen Internet, keinen Schlüssel. Der lokale Server gibt sich als Entwicklungsstand mit der Adresse dieses Repos aus |
| Nahverkehr (mobiliteit.lu) | braucht einen kostenlosen persönlichen Schlüssel, siehe [`docs/server/betrieb.md`](docs/server/betrieb.md). Ohne ihn zeigt das Panel KEIN SCHLUESSEL |
| Spotify | eine Nachbildung: `php -S 127.0.0.1:8766 tools/spotify-mock.php` (braucht gd), dann den Server mit `TW_SPOTIFY_MOCK=http://127.0.0.1:8766` starten, im Admin-Bereich beliebige 32 Hex-Zeichen als Client-ID und Secret eintragen und auf der Geräteseite verbinden. `/control?mode=pause`, `skip=1`, `delay=1200`, `early=1500` steuern sie |
| Mail | braucht einen eigenen SMTP-Zugang, sonst geht nichts raus |
| Airline-Logos | nicht in diesem Repo, sie sind Marken. Ohne sie zeigt das Panel einen grauen Block mit dem Kürzel. `node tools/logos-build.mjs` baut sie für den eigenen Server |

### Firmware

```
pio run -d firmware -e wall
```

Das Ergebnis ist `firmware/.pio/build/wall/firmware.bin`. Zum Bauen braucht es keine Schlüssel, das Gerät bekommt seinen im eigenen Einrichtungsnetz. Für einen eigenen Server mit `-DWALL_SERVER=\"https://dein.server\"` bauen: die Firmware spricht nur HTTPS mit einem Zertifikat einer gängigen Stelle wie Let's Encrypt, der lokale `http://`-Server taugt deshalb nicht für ein echtes Gerät. Einzelheiten in [`firmware/README.md`](firmware/README.md).

## Prüfungen

Aus dem Hauptverzeichnis starten. Dieselben Prüfungen laufen bei jedem Pull Request.

| Befehl | Was geprüft wird |
| --- | --- |
| `TW_ENV=dev php tools/auth-fixtures.php` | Regeln für die Herkunft von Anfragen |
| `TW_ENV=dev php tools/device-fixtures.php` | Notizen, Übernehmen, Zähler, Uhrzeit im Frame |
| `TW_ENV=dev php tools/flight-fixtures.php` | Flugtexte, Einheiten, Karte, Strecken, Logos |
| `TW_ENV=dev php tools/timer-fixtures.php` | Timer, Wecker, Klingeln, Timer-Ecke in jedem Modus |
| `TW_ENV=dev php tools/transit-fixtures.php` | die Schnittstelle des Nahverkehrs |
| `TW_ENV=dev php tools/transit-mode-fixtures.php` | die Abfahrtstafel |
| `TW_ENV=dev php tools/spotify-fixtures.php` | Cover, Texte, Übergänge |
| `TW_ENV=dev php tools/ha-fixtures.php` | die Schnittstelle für Home Assistant über HTTP, bei laufendem lokalem Server |
| `node --check <datei>` | jede Datei in `web/assets/js/` und jedes `tools/*.mjs` |
| `pio run -d firmware -e wall` | die Firmware baut |
| in `homeassistant/`: `ruff check .`, `ruff format --check .`, `python -m pytest tests -q` | die Integration, nach `pip install -r requirements_test.txt ruff` |

Eine neue Funktion bringt eigene `check()`-Zeilen mit, siehe die Rezepte in [`docs/server/erweitern.md`](docs/server/erweitern.md).

## Regeln kurz

Die ganze Liste steht in [`CLAUDE.md`](CLAUDE.md).

- Jeder Text für Menschen auf Englisch und Deutsch: Englisch im Markup, Deutsch in `data-de`.
- Keine Gedankenstriche, keine Emoji, keine Werbefloskeln.
- Barrierefreiheit ist Pflicht: Kontrast 4,5:1, sichtbarer Fokus, Rollen für Schalter, Ziele von 44 Pixeln, reduzierte Bewegung beachten.
- Nie `innerHTML` mit Werten von außen.
- Schlüssel und Passwörter nie in Code oder Firmware. Der Server holt alles von außen, nie das Gerät.
- Zeitzonen als IANA-Namen, nie ein fester Versatz.
- Nie mehr als drei Blitze pro Sekunde, auch nicht auf dem Panel.

## Sicherheit

Sicherheitslücken bitte nicht in öffentlichen Issues melden. Siehe [`SECURITY.md`](SECURITY.md).
