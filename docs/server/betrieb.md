# Betrieb

## Voraussetzungen

| | |
| --- | --- |
| Webserver | nginx oder Apache |
| PHP | 8.5, damit getestet. Erweiterungen pdo_mysql (lokal pdo_sqlite), curl, sodium, mbstring, openssl |
| Datenbank | MariaDB, getestet mit 12. Lokal SQLite |
| Cron | keiner nötig, `housekeeping()` läuft höchstens stündlich mit einem Aufruf |
| Mail | ein SMTP-Zugang mit STARTTLS oder SSL |

Der Webserver braucht zwei Regeln: alles, was keine Datei ist, geht an `index.php`, und alles unter `/.ht` ist gesperrt. Für nginx:

```nginx
location / { try_files $uri $uri/ /index.php?$args; }
location ~ /\.(ht|svn|git) { deny all; }
```

Apache nimmt die mitgelieferten `.htaccess`-Dateien, dafür muss `AllowOverride All` gelten. Die `.htaccess` im Webroot reicht den Kopf `Authorization` per Rewrite an PHP weiter (`REDIRECT_HTTP_AUTHORIZATION`), sonst sähe jedes Gerät hinter Apache mit PHP-FPM dauerhaft SCHLUESSEL ABGELEHNT. Statische Dateien dürfen lange im Cache liegen, jede Asset-Adresse trägt `?v=`.

**Basic Auth muss aus bleiben**, oder `/api/v1/` muss davon ausgenommen sein. Das Gerät schickt nur seinen Bearer-Schlüssel.

Hinter einem CDN: Jede dynamische Antwort trägt `Cache-Control: private, no-store, max-age=0, no-transform`, damit kein HTML im Cache landet und das CDN keine Skripte einfügt. Scheitert ein fremder Dienst, antwortet die App mit 424, nie mit 502 oder 503: hinter CloudPanel und Cloudflare kam eine 502 der App im Browser nur als abgebrochene Verbindung an. Für die Rate-Limits braucht PHP die Adresse des Besuchers, `core/ip.php` vertraut dazu `X-Forwarded-For` vom eigenen nginx und `CF-Connecting-IP` nur aus Cloudflare-Netzen.

## Deploy

`web/` wird 1:1 ins Webroot kopiert, `.htdata` bleibt dabei unberührt, zum Beispiel:

```
rsync -a --delete --exclude .htdata web/ server:/pfad/zum/webroot/
```

Danach prüfen: die Startseite antwortet mit 200 und `cache-control: private, no-store`, `/.htapp/routes.php` und `/.htdata/config.php` antworten mit 403.

Neue Migrationen laufen beim ersten Aufruf nach dem Deploy von selbst.

## Erste Einrichtung

1. `.htdata/config.php` nach `.htapp/config.example.php` anlegen: Adresse der Seite und Datenbankzugang.
2. Deploy, dann `/account` einmal aufrufen: Solange es kein Konto gibt, legt das `.htdata/secret.php` an, und die Datenbank wird migriert.
3. `setup_token` aus `.htdata/secret.php` lesen und `https://<domain>/account?mode=register&setup=<token>` öffnen. Das erste Konto wird Admin und ist sofort bestätigt. Der Link gilt nur, solange es kein Konto gibt.
4. Im Admin-Bereich unter "Dienst-Zugänge" SMTP eintragen und eine Test-Mail schicken (unten).
5. Unter "Impressum und Kontakt" Name und Kontakt-E-Mail eintragen, die Adresse ist freiwillig. Die Angaben erscheinen in Datenschutz und Impressum, im Fußtext der Mails, die E-Mail außerdem im User-Agent für adsb.lol, adsb.fi und adsbdb. Ohne Kontakt antworten die Dienste womöglich nicht.
6. Registrierung bleibt "nur mit Einladung". Weitere Konten über "Einladungslink" im Admin-Bereich oder über eine Freigabe in den Einstellungen eines Geräts.
7. Freiwillig, für den Nahverkehr: unter "Dienst-Zugänge" bei mobiliteit.lu den persönlichen Schlüssel eintragen und testen (unten).

## Mails

Jeder SMTP-Dienst mit STARTTLS (Port 587) oder SSL (Port 465). Die Absender-Domain braucht DKIM und DMARC im DNS, sonst landen die Mails im Spam. Beispiel Brevo:

| Feld | Wert |
| --- | --- |
| Server | `smtp-relay.brevo.com` |
| Port | 587 |
| Sicherheit | STARTTLS |
| Login | die SMTP-Anmeldung aus Brevo, "SMTP & API" |
| Schlüssel | ein SMTP-Schlüssel aus Brevo (nicht der API-Schlüssel) |
| Absender | eine in Brevo bestätigte Adresse |

Der Schlüssel wird verschlüsselt gespeichert und nie wieder angezeigt. Ein leeres Schlüsselfeld beim Speichern behält den alten. Nach jedem Speichern zeigt der Bereich "Ungetestet", bis eine Test-Mail durchging.

Ohne SMTP: neue Konten sind sofort bestätigt (das steht dann in "Zuletzt schiefgegangen"), Passwort-Links und Einladungs-Mails gehen nicht raus (Einladungen zeigen dann einen Link zum Kopieren), der Export per Mail meldet einen Fehler.

## Nahverkehr (mobiliteit.lu)

Haltestellen und Abfahrten kommen aus der OpenAPI der Administration des transports publics, mit Echtzeit für AVL, CFL, Luxtram, RGTR und TICE. Den persönlichen Schlüssel gibt es per Mail an opendata-api@atp.etat.lu, kostenlos. Er gilt für 500 Abfragen pro Stunde und 5 000 pro Tag, die Daten stehen unter CC BY 4.0.

1. Im Admin-Bereich unter "Dienst-Zugänge" bei mobiliteit.lu auf "Bearbeiten", den Schlüssel einfügen, "Speichern". Er wird verschlüsselt gespeichert und nie wieder angezeigt. Ein neuer Schlüssel ersetzt den alten.
2. "Testen" fragt die Haltestellen am Bahnhof Luxemburg und die nächsten Abfahrten des Bahnhofs ab, zwei Abfragen. Die Liste zeigt Zeit, Zug, Ziel, Gleis und Verspätung.
3. "Datenprobe laden" sammelt Abfahrten an sechs Orten (Gare Centrale, Hamilius, Luxexpo, Esch, Ettelbruck, Steinfort), je Ort bis zu drei Haltestellen, je Verkehrsmittel die wichtigste. Beim ersten Mal rund 25 Abfragen, die Haltestellen bleiben danach einen Tag gespeichert. Die Probe kommt als JSON zum Herunterladen oder Kopieren und liegt zusätzlich in `.htdata/transit/sample-latest.json`: aufbereitete Abfahrten, je Haltestelle die erste Abfahrt im Rohformat, Kennzahlen wie die längsten Ziele. Gedacht für Entwürfe des Nahverkehrsmodus.

Für die Fehlersuche gibt es `POST /api/admin/transit-raw` mit `{endpoint, params}`: eine Abfrage an einen der vier freigegebenen Endpunkte, die Antwort unverändert ohne Schlüssel. Ohne Oberfläche, aus der Konsole des angemeldeten Admin-Bereichs mit dem CSRF-Token aus `meta[name="csrf-token"]`.

Was die Schnittstelle wirklich liefert, steht mit Zahlen in `services.md` bei `services/transit.php`: große Knoten nur mit `type=SE`, Abfahrten über `mainMastExtId`, Steige bei Bussen ausgeblendet.

Der Server hört bei 450 Abfragen in der Stunde und 4 500 am Tag von selbst auf, damit der Schlüssel nie gesperrt wird. Die Karte "mobiliteit.lu" unter den Abfragebudgets zeigt den Verbrauch. Abfahrten bleiben 60 Sekunden je Haltestelle im Zwischenspeicher, Haltestellen einen Tag.

Der Anzeigemodus steht in `modes/transit.php` (seit CHANGELOG 21), die Quelle nennen Geräteseite, Haltestellenkarte, Datenschutz und Impressum. Für die Haltestellenkarte fragt der Server einmal im Monat alle Haltestellen des Landes ab, rund 35 Abfragen, angestoßen von der Karte selbst und nur bei weniger als 300 Abfragen in der laufenden Stunde (`services/stops.php`, Ergebnis in `.htdata/transit/stops-*.json`). Höchstens drei Haltestellen je Gerät; zusammen sollten alle Geräte nicht mehr als drei verschiedene zeigen, sonst greift die Bremse bei 4 500 am Tag und die Tafeln stehen für den Rest des Tages auf dem alten Stand.

## Spotify

Der Spotify-Modus braucht eine App im Spotify-Dashboard, die dem Betreiber gehört. Seit Februar 2026 gilt für neue Apps der Entwicklungsmodus: der Besitzer braucht Spotify Premium, höchstens fünf Nutzer, und jeder muss vorher unter User Management eingetragen sein.

1. Auf developer.spotify.com/dashboard eine App anlegen, nur "Web API". Unter Redirect URIs genau die Adresse eintragen, die der Admin-Bereich unter "Dienst-Zugänge" bei Spotify anzeigt, also `<url>/spotify/callback`.
2. Unter User Management jedes Spotify-Konto eintragen, das verbunden werden soll, mit Name und der E-Mail des Spotify-Kontos.
3. Im Admin-Bereich bei Spotify auf "Bearbeiten", Client-ID und Client Secret einfügen, "Speichern", dann "Testen". Der Test holt ein Token nur für die App; das geht ohne ein verbundenes Konto. Das Secret wird verschlüsselt gespeichert und nie wieder angezeigt.
4. Jedes Konto verbindet sein Spotify auf der Geräteseite im Modus Spotify ("Mit Spotify verbinden"). Ein Konto, das nicht unter User Management steht, bekommt eine Absage mit Erklärung.

Der Server fragt Spotify nur, solange ein Gerät oder eine Vorschau Spotify zeigt: "Läuft gerade" höchstens alle fünf Sekunden je Konto, die Warteschlange alle 30 Sekunden, das Album einmal am Tag. Die Budgets heißen `spotify` und `spotifyimg`. Zeitleiste, Platte und Übergänge brauchen Firmware 0.2.0.

Nach den Entwicklerbedingungen von Spotify läuft eine App nur auf "Approved Devices" (Computer, Tablets, Handys und was Spotify schriftlich freigibt). Ein LED-Panel ist dort nicht genannt. Für den privaten Entwicklungsmodus mit fünf Nutzern ist das vertretbar; wer die App für mehr Nutzer freischalten lassen will, muss das bei Spotify ansprechen.

Lokal ohne echte Zugangsdaten: `php -d extension=gd -S 127.0.0.1:8766 tools/spotify-mock.php` bildet Anmeldung, Token, "Läuft gerade", Warteschlange, Album und Bilder nach, mit drei erfundenen Titeln von 35 bis 50 Sekunden. Der Testserver braucht dazu `TW_SPOTIFY_MOCK=http://127.0.0.1:8766` (nur mit `TW_ENV=dev`). Im Admin-Bereich gehen dann beliebige 32 Hex-Zeichen als Client-ID und Secret. `/control?mode=pause`, `none`, `forbidden`, `limited`, `expire` und `/control?skip=1` steuern die Nachbildung. `/control?delay=1200` lässt die Warteschlange so viele Millisekunden warten wie die echte oft, `/control?early=1500` meldet jeden Titel so viel länger, als er läuft: dann kommt der nächste vor dem geplanten Ende. Mit beiden ließ sich am 26. September 2026 das Ruckeln vor dem Titelwechsel nachstellen (CHANGELOG 67).

## Airline-Logos

Die Logos kommen aus der Sammlung [Jxck-S/airline-logos](https://github.com/Jxck-S/airline-logos), nach ICAO-Kürzel: zuerst die FlightAware-Symbole (1 425), für die Lücken die RadarBox-Kacheln (815). Eingetragene Marken, deshalb nur auf dem Server in `.htdata/logos`, nie im Repo.

```
node tools/logos-build.mjs --preview
```

Danach `web/.htdata/logos/` nach `.htdata/logos/` auf den Server kopieren.

Das Skript lädt die PNGs in `tools/.logo-cache/` (einmalig, rund 7 MB), rechnet jedes auf 32×34 LED-Pixel in Vollfarbe und schreibt `web/.htdata/logos/<ICAO>.rgb` samt `index.json`. Dabei:

- Weißer Hintergrund wird durchsichtig, farbige Kacheln bleiben.
- Dunkle Farben werden aufgehellt, Schwarz wird hellgrau, sonst leuchtet auf dem Panel nichts.
- Eine Schriftzeile unter oder rechts neben dem Symbol wird abgeschnitten (mindestens vier dicht stehende Buchstaben, flacher als das Symbol). Emirates behält die Schrift (`KEEP_TEXT` im Skript).
- Besteht ein Logo nur aus einem Schriftzug, nimmt das Skript die andere Quelle, wenn sie ein Symbol hat. Ein Schriftzug, der auf dem Panel keine 10 Zeilen hoch wird, bekommt keine Datei: dann zeigt das Panel den Farbblock mit Kürzel.
- `--preview` schreibt `tools/.logo-cache/preview.html`: beschnittene Logos vorher und nachher, lesbare Schriftzüge, die häufigen Airlines über Luxemburg und eine gleichmäßig verteilte Stichprobe.
- Nachgeladen: Logos, die FlightAware erst nach dem Stand der Sammlung bekam, liegen als `ICAO.png` in `tools/.logo-cache/nachgeladen/` (oder `--more ordner/`). Sie laufen durch dieselbe Prüfung wie die Sammlung und gehen ihr vor.
- Eigene Dateien gehen allem vor: von Hand freigestellte echte Logos als `ICAO.png` in `tools/.logo-cache/eigen/` (oder `--extra ordner/`), unverändert übernommen, ohne Schriftprüfung. Betreiber ohne ICAO-Kürzel bekommen zwei Buchstaben, die keine Airline haben kann (`PL.png`), zugeordnet über `FLIGHT_CALLSIGN_OWNERS` in `modes/flight.php`. Woher jede Datei stammt, steht in `eigen/quellen.json`, die Vorlage in `eigen/original/`.
- `ALIASES` im Skript: eine Marke unter einem zweiten Kürzel bekommt dasselbe Logo, solange sie kein eigenes hat (die JUP-Flüge fliegt Zimex, also `IMX`; Fly 7 heißt seit Mai 2026 Jetfly, also `JFA`; Circadian gehört wie Sterling zu Airshare, also `NSH`).
- `NEVER` im Skript: Kürzel, die nie ein Logo bekommen. Das sind Rufzeichen für anonyme Privatflüge aus dem Programm PIA der FAA (`FFL` ForeFlight, `FWR` FlightAware, `XAA` ARINC). Das Logo des Anbieters sähe aus, als fliege der Anbieter selbst.
- Beide Ordner sind wie der Rest von `tools/.logo-cache/` nicht im Repo. Wer die Logos auf einem anderen Rechner baut, bekommt ohne sie nur die Sammlung.

Stand am 24. September 2026: 1 684 Logos. 1 311 FlightAware und 244 RadarBox aus der Sammlung, 48 nachgeladen von FlightAware, 78 von Hand freigestellt, 3 über `ALIASES`; 28 mit abgeschnittener Schrift, 71 lesbare Schriftzüge, 62 unlesbare ohne Datei. Die Anzahl steht im Admin-Bereich unter "Dienst-Zugänge". Am 13. September waren es 1 561. Am 26. September kam das Logo der Polizei in Luxemburg dazu (`PL`, vom Projektinhaber geliefert), seither 1 685.

Die von Hand freigestellten stammen von den Seiten der Betreiber, aus deren Pressebereich oder von Wikimedia Commons, geprüft gegen die Liste der ICAO-Kürzel der FAA (JO 7340.2P mit Änderung 3 vom Juli 2026): Luxaviation, Luxembourg Air Rescue, Bundespolizei, Glock Aviation, NetJets, PlaneSense und 70 weitere. Welche Datei wie beschnitten wurde, steht mit der Quelle in `eigen/quellen.json`. Kürzel, die inzwischen jemand anderem gehören (First Choice Airways, flyglobespan, Japan Air System), bekommen das Logo des heutigen Inhabers, nicht das alte aus einer Sammlung.

Das Gerät legt jedes Logo in seinem Dateispeicher ab und fragt ein Kürzel ohne Logo stündlich erneut. Neue Logos kommen also von selbst aufs Panel; ein geändertes Logo für ein Kürzel, das das Gerät schon hat, bleibt dort alt, bis die Firmware den Stand der Sammlung mitbekommt (noch nicht gebaut).

## Home Assistant

Die Integration liegt in `homeassistant/` und seit dem 26. September 2026 zusätzlich im öffentlichen Repo [LennyGodart/THE-WALL-HA](https://github.com/LennyGodart/THE-WALL-HA), weil HACS nur aus öffentlichen Repos installiert und je Repo nur eine Integration kennt. Dort prüfen zwei Workflows jeden Stand: hassfest und die HACS-Prüfung, dazu Tests und ruff unter Linux. In HACS unter "Benutzerdefinierte Repositories" die Adresse des Repos mit Typ Integration eintragen; ohne HACS `custom_components/thewall` nach `config/custom_components/` von Home Assistant kopieren und neu starten.

Quelle bleibt `homeassistant/` in diesem Repo. Für einen neuen Stand dort ändern und testen, die Version in `custom_components/thewall/manifest.json` erhöhen, dann den Inhalt von `homeassistant/` ins öffentliche Repo kopieren (ohne `__pycache__`, `.pytest_cache`, `.ruff_cache`), committen, einen Tag `vX.Y.Z` setzen und pushen. HACS bietet Releases an, wenn es welche gibt, sonst den Hauptzweig; ein Release legt man auf GitHub unter "Releases" zum Tag an.

Einrichtung: Home Assistant findet ein Gerät ab Firmware 0.2.1 im Heimnetz (`_thewall._tcp`, TXT `id`, `fw`, `srv`), sonst gibt man beim Hinzufügen die Geräte-ID aus den Einstellungen ein. Auf dem Panel steht dann fünf Minuten ein Code, den tippt man in Home Assistant ein. Danach gilt ein eigener Schlüssel je Home Assistant, im Klartext sieht ihn nur Home Assistant; auf der Einstellungsseite des Geräts steht, wer gekoppelt ist, mit "Trennen". Die Schnittstelle steht in [seiten.md](seiten.md) unter `api/ha.php`.

Geprüft wird die Integration mit ihren eigenen Tests gegen einen nachgebauten Server (`homeassistant/tests/`, 296) und mit einem Durchlauf gegen diesen Server im Entwicklungsmodus (`homeassistant/tests_live/`, Aufruf in der README der Integration).

Home Assistant fragt alle 15 Sekunden den Stand, kurz nach jedem Timerende und jeder Weckzeit noch einmal. Der Stand liest nur aus der Datenbank und dem Zwischenspeicher: der Flug auf dem Panel kommt aus dem Abruf des Geräts, adsb.lol fragt Home Assistant nie. Zehn Instanzen sind so knapp 60 000 Anfragen am Tag, jede eine Datenbankabfrage.

## Firmware ausrollen

Nur die erste Fassung kommt per USB aufs Board. Jede weitere holt sich das Gerät vom Server.

Firmware 0.2.1 (26. September 2026) bringt Ton für Timer und Wecker, den Wecker ohne Server, Stoppen per Rad und die Meldung im Heimnetz. Sie ist gebaut, aber noch auf keinem Gerät gelaufen: zuerst auf einem eigenen Gerät testen (seriell `audio`, `beep`, `ring`), dann ausrollen, das Geschenk zuletzt.

### Version

Die Firmware kennt ihre Version als drei Zahlen `x.y.z` und schickt sie bei jedem Abruf im Kopf `X-Wall-Fw`. Ein Gerät ohne diesen Kopf bekommt nie ein Update angeboten. Verglichen wird mit `version_compare`, also `0.10.0` nach `0.9.3`. Angeboten wird nur eine höhere Version. Zurück auf einen alten Stand geht es, indem man ihn mit einer höheren Nummer neu baut.

### Datei ablegen

1. Die App-Datei der Firmware bauen, unter PlatformIO `.pio/build/<umgebung>/firmware.bin`. Nicht das zusammengeführte Abbild mit Bootloader und Partitionstabelle, das ist nur für den ersten Flash per USB.
2. Im Admin-Bereich unter "Firmware ausrollen" hochladen. Der Server liest die Version aus der Datei selbst (die Marke `THEWALL-FW x.y.z`, ab Firmware 0.1.1), prüft, ob es ein App-Image für den ESP32 ist (erstes Byte `0xE9`, 64 KB bis 4 MB), und legt es als `.htdata/firmware/thewall-<x.y.z>.bin` ab. Eine Version, die schon da ist, lehnt er ab.
3. Oder von Hand: in `thewall-<x.y.z>.bin` umbenennen, zum Beispiel `thewall-0.2.0.bin` (andere Namen übersieht der Server), und nach `.htdata/firmware/` legen. Dafür muss der Ordner für den eigenen Zugang beschreibbar sein; PHP legt ihn mit 0750 an.

### Wie der Server sie erkennt

Beim nächsten Abruf eines Geräts oder beim Öffnen des Admin-Bereichs schaut `firmware_scan()` in den Ordner. Eine neue Datei trägt er mit SHA-256 und Größe in die Tabelle `firmware` ein, eine gelöschte trägt er aus, eine Datei mit anderer Größe rechnet er neu. Es gilt immer die höchste Version im Ordner.

Eine ausgelieferte Version nie durch eine gleich große Datei ersetzen: dann bleibt die alte Prüfsumme stehen und jedes Gerät verwirft den Download. Für jeden neuen Stand eine neue Nummer.

### Wer sie bekommt

- **Anteil** im Admin-Bereich unter "Firmware", in Schritten von 5 Prozent. Jedes Gerät hat einen festen Platz von 0 bis 99, gerechnet aus seiner Geräte-ID (`crc32(uid) % 100`). Bei 25 Prozent bekommen die Plätze 0 bis 24 das Update. Beim Hochdrehen kommen neue Plätze dazu, keiner fällt heraus.
- **"Automatisch" aus** (Vorgabe): Der Besitzer sieht in den Einstellungen seines Geräts "Jetzt aktualisieren". Nach dem Drücken holt das Gerät das Update beim nächsten Abruf, der Auftrag gilt 24 Stunden.
- **"Automatisch" an**: Jedes Gerät im Anteil holt es beim nächsten Abruf, ohne Nachfrage.

### Was das Gerät tut

1. Die Antwort auf `GET /api/v1/frame` enthält `"fw": {"version", "url", "sha256", "size"}`. Das Feld fehlt, solange es nichts zu tun gibt.
2. Das Gerät lädt `GET /api/v1/firmware/<version>` mit `Authorization` und `X-Wall-Id`. Antwort: die Datei mit `Content-Length` und `X-Checksum-Sha256`. 404, wenn die Version für dieses Gerät nicht mehr freigegeben ist.
3. Es schreibt in die zweite App-Partition und vergleicht SHA-256 und Größe mit dem `fw`-Feld. Stimmt etwas nicht, verwirft es die Datei und läuft weiter wie bisher.
4. Neustart in die neue Partition. Startet die neue Fassung dreimal hintereinander neu, bevor sie 60 Sekunden lief, schaltet sie selbst auf die alte Partition zurück (`guardUpdate()` in `firmware/src/main.cpp`). Nach 60 Sekunden Lauf gilt sie als gut, ein erfolgreicher Abruf ist dafür nicht nötig. Ab Firmware 0.1.10 merkt sich das Gerät eine zurückgerollte Version im NVS (`ota_bad`), installiert sie nicht noch einmal und schickt sie bei jedem Abruf im Kopf `X-Wall-Fw-Bad`; `firmware_offer()` bietet sie diesem Gerät dann nicht mehr an. Das Board braucht zwei App-Partitionen, bei 16 MB Flash und rund 1,3 MB Firmware reichen je 4 MB.
5. Beim nächsten Abruf meldet es die neue Version in `X-Wall-Fw`, der Admin-Bereich zählt es dann dort mit.

Die Firmware macht das seit 0.1.0 so (SHA-256 beim Schreiben, Größe, Rollback nach drei Fehlstarts, Freigabe nach 60 Sekunden Lauf). Updates über das Netz liefen seit dem 18. September 2026 mehrfach auf dem Gerät, einen echten Rollback hat noch keines gebraucht.

## Daten und Protokolle

| Was | Wo | wie lange |
| --- | --- | --- |
| Konten, Geräte, Einstellungen | Datenbank | bis zur Löschung |
| unbestätigte Konten | Datenbank | 7 Tage |
| Fehlerprotokoll | `.htdata/logs/app-<datum>.log` | 14 Tage |
| "Zuletzt schiefgegangen" | Tabelle `events` | 14 Tage |
| letzte Datenprobe Nahverkehr | `.htdata/transit/sample-latest.json` | bis zur nächsten Probe, keine Personendaten |
| Zugriffsprotokolle des Webservers | Webserver | auf 14 Tage stellen, so steht es in der Datenschutzerklärung |

Sicherung: die Datenbank und der Ordner `.htdata` (ohne `logs/` und `locks/`). Ohne `secret.php` ist der gespeicherte SMTP-Schlüssel nicht mehr lesbar, alle Sitzungen und Links bleiben aber gültig.

## Lokal entwickeln

Voraussetzung: PHP 8.5 mit pdo_sqlite, curl, sodium, mbstring, intl, gd und zlib. Node 18 oder neuer für die Werkzeuge. Ohne eigene `config.php` gibt sich der lokale Server bei adsb.lol, Open-Meteo und Nominatim als Entwicklungsstand mit der Adresse des öffentlichen Repos aus, nicht als thewall.godart.lu. Den Einrichtungs-Token legt der erste Aufruf von `/account` an.

```
bash tools/dev-server.sh
```

Startet `php -S 127.0.0.1:8765` mit `tools/dev-router.php` (verhält sich wie nginx: `.ht*` gesperrt, Rest an `index.php`) und `TW_ENV=dev`. Die Datenbank ist `web/.htdata/dev.sqlite`, der Einrichtungs-Token steht in `web/.htdata/secret.php`. Mit `PHP_BIN=/pfad/php` lässt sich ein anderes PHP wählen, mit `TW_DB_PATH=/pfad/datei.sqlite` eine andere Datenbank. Unter Windows braucht curl in der `php.ini` einen CA-Pfad (`curl.cainfo`), sonst scheitern die Abfragen nach außen.

## Prüfskripte

Im öffentlichen Repo laufen die PHP-Prüfungen, die Syntax aller JavaScript-Dateien, der Firmware-Build und die Tests der Home-Assistant-Integration bei jedem Push und Pull Request (`.github/workflows/checks.yml`, nur in öffentlichen Repos). Am 29. September 2026 mit PHP 8.5.11 unter Linux durchgespielt: 83 Dateien Syntax, 362 Prüfungen ohne Server, 55 über HTTP.

| Aufruf | Zweck |
| --- | --- |
| `node tools/audit.mjs <ordner>` | Regeln aus `CLAUDE.md` auf gespeicherten Seiten |
| `node tools/fontcmp.mjs web/assets/js/lib/pixelfont.js tools/font95.hex` | fehlende Zeichen im Pixelfont |
| `node tools/panel-png.mjs antwort.json bild.png` | eine Antwort von `/api/v1/frame` oder `/api/preview/{id}` als PNG, mit denselben Zeichenfunktionen wie die Vorschau. Referenz für die Firmware, Aufruf im Kopf des Skripts |
| `node tools/public-export.mjs <ordner>` | Stand für das öffentliche Repo: nur freigegebene Dateien, private Abschnitte entfernt, Suche nach Spuren der eigenen Instanz |
| `TW_ENV=dev php tools/auth-fixtures.php` | Herkunftsprüfung: welche Kombination aus `Sec-Fetch-Site` und `Origin` als eigene Seite gilt, mit den Werten, die Browser wirklich schicken |
| `TW_ENV=dev php tools/device-fixtures.php` | Einstellungen und Rahmen eines Geräts (fragt einmal Open-Meteo für die Uhr, das Ergebnis zählt nicht): wie lange eine neue Notiz vorn steht, wann sie den Vorrang verliert (Moduswechsel, Ausblenden, geleert) |
| `TW_ENV=dev php tools/timer-fixtures.php` | Timer, Wecker und Klingeln (fragt Open-Meteo für die Uhr, das Ergebnis zählt nicht): Grenzen, Wochentage, Sommerzeit, Stoppen, die Ecke in jedem Modus nur mit laufendem Timer (LUFTHANSA CITY sonst in voller Länge), Seite fürs Klingeln auf die Sekunde, Panel aus, Kopplungscode, Speichern ohne verlorene Änderung |
| `TW_ENV=dev php tools/ha-fixtures.php [adresse]` | Schnittstelle für Home Assistant über HTTP gegen einen laufenden Testserver mit derselben Datenbank: Koppeln mit dem Code vom Panel, Stand, Panel, Notiz, Timer, Wecker, Klingeln, Stoppen per Rad, Grenzen, Trennen |
| `TW_ENV=dev php tools/flight-fixtures.php` | Flugmodus (fragt einmal Open-Meteo für den leeren Himmel, das Ergebnis zählt nicht): Typnamen, Ansichten, Zeilenlängen, Einheiten, Ausschnitt der Karte, Festhalten bei Start und Landung |
| `TW_ENV=dev php tools/transit-fixtures.php` | Auslesen der Antworten von mobiliteit.lu gegen dokumentierte und echte Formen, ohne Netz und ohne Schlüssel |
| `TW_ENV=dev php tools/transit-mode-fixtures.php` | Nahverkehrsmodus: Spalten von rechts, Farben, Zeichen, Filter, Streifen, Störung, Einstellungen, ohne Netz |
| `TW_ENV=dev php tools/spotify-fixtures.php` | Spotify (fragt einmal Open-Meteo für die Uhr, das Ergebnis zählt nicht): Cover für das Panel, Texte, Seitenplan mit Ansage, Walze und Karussell, Ansichten, Pause, alte Firmware, Rotation ohne Musik |

