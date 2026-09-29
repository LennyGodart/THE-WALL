# THE WALL, Projektregeln

Diese Datei gilt für jede Arbeit an diesem Projekt, hier und in Claude Code.

## Beitragen

Beiträge kommen als Pull Request ins öffentliche Repo. Wie das läuft und wie man alles lokal
startet, steht in `CONTRIBUTING.md`, die Rezepte für neue Funktionen stehen in
`docs/server/erweitern.md`. Beide vor der ersten Änderung lesen. Code, Doku und Prüfungen
ändern sich im selben Pull Request, und alle Prüfskripte müssen durchlaufen.

## Produkt

THE WALL ist ein 128×64 LED-Matrix-Display an einem ESP32-S3, das Flugzeuge über
Luxemburg anzeigt, dazu Uhr, Wetter, Notizen und mehr. Ein Server übernimmt die Datenbeschaffung, eine Webseite die Verwaltung.

Der Grund ist **nicht** Flash-Mangel. Das Board hat 16 MB und 8 MB PSRAM, eine
Flug-Firmware liegt bei etwa 1,3 MB. Die echten Gründe: neue Modi ohne
Neuflashen, ein Zwischenspeicher pro Standort statt einer Abfrage je Gerät, und
keine Zugangsdaten für spätere Dienste wie Spotify in einer Firmware, die jeder
auslesen kann.

- Name: **THE WALL**, durchgehend Großbuchstaben
- Domain: thewall.godart.lu
- Repo: github.com/LennyGodart/THE-WALL
- Standort: Luxemburg. Zeitzone `Europe/Luxembourg`, Aufsichtsbehörde CNPD
- Umfang: privat für zwei Geräte, dazu offen für Bastler: der Quelltext von Firmware und
  Server ist öffentlich, verkaufen darf ihn niemand (Lizenz unten)
- Kein Verkauf, kein Abo, kein Shop

## Absolute Verbote

- **Keine KI-Ästhetik.** Keine aggressiven Farbverläufe, keine violetten Neon-Schatten,
  keine abgerundeten Kästen mit farbigem Rand links als Akzent, keine Drei-Karten-Reihen
  als Standardlayout
- **Keine KI-Schriften.** Inter, Roboto, Arial und Fraunces sind gesperrt
- **Keine Emoji** als Symbole, Aufzählungszeichen oder Statusanzeigen
- **Keine Em-Dashes** (—) im Text. Stattdessen Komma, Punkt oder Klammer.
  Das ist das deutlichste Zeichen für maschinell geschriebenen Text
- **Keine KI-Floskeln**: "es geht nicht nur um X, sondern um Y", "elevate",
  "seamless", "unleash", "next-gen", "nahtlos", "revolutionär". Keine Dreiergruppen
  als Rhythmustrick. Keine Metadiskussion ("hier ist, warum das wichtig ist")
- **Keine Google Fonts.** Nur Bunny Fonts (EU, kein Tracking) oder lokal gehostet
- **Keine Tracker, keine Analytics.** Deshalb braucht die Seite kein Cookie-Banner,
  und das muss so bleiben
- **Keine handgezeichneten SVG-Karten oder -Illustrationen.** Geografie kommt aus
  echten Daten (Natural Earth, OpenStreetMap)
- **Keine erfundenen Markenlogos.** Airline-Logos nur aus echten Dateien, die der
  Projektinhaber liefert

## Sprache und Texte

- Ausgeliefert wird Englisch, Deutsch über den Schalter. Englisch steht im Markup,
  Deutsch in `data-de`, der Umschalter tauscht `textContent` (in `web/` so gebaut,
  damit auch dort nie HTML aus einem Attribut entsteht)
- Sachlich schreiben, konkrete Zahlen statt Behauptungen. "Fünf Zeilen, die unteren
  zwei sind 21 Zeichen breit" statt "maximale Informationsdichte"
- Satzlängen variieren. Nicht jeder Absatz gleich lang
- Wenn der Projektinhaber Text liefert, wird er wörtlich übernommen

## Gestaltung

- Grundfarbe `#08090A` mit Punktraster `#181C20` im 6px-Abstand, das Raster gehört
  zur Marke
- Leitfarbe Bernstein `#FFAA00`. Zweitfarben Cyan `#35D6FF` für Technisches,
  Grün `#3DE07C` für Status, Rot `#FF4A1C` für Fehler
- Schrift: IBM Plex Mono für Technisches und Überschriften, IBM Plex Sans für
  Fließtext, beide über Bunny Fonts.
  **Eine Ausnahme:** In den drei E-Mails steht Arial im Schriftstapel. Mail-Clients
  laden keine Webfonts, und Helvetica und Arial sind die einzigen Stapel, die
  Outlook und Gmail zuverlässig gleich rendern
- Markentypografie kommt aus `pixelfont.js` auf Canvas, nicht aus einem Webfont
- Flächen werden durch 1px Rasterlücken getrennt, nicht durch Schatten
- Trennlinien `#1B2126`, Ränder von Bedienelementen `#2C353C`

## Barrierefreiheit ist Pflicht, nicht Zusatz

- Textkontrast mindestens 4,5:1. Die Graustufen `#5A636B` und `#4A535B` sind
  gesperrt, sie fallen durch. Leiseste erlaubte Textfarbe: `#8B949C`
- **Ausnahme Panel-Simulation:** Innerhalb eines Canvas, das LEDs nachbildet,
  dürfen `#7A5200`, `#4E5A63` und `#3B444B` als Leuchtfarbe stehen. Sie zeigen eine
  gedimmte LED, nicht Text auf einer Seite, und die Aussage steht immer zusätzlich
  im `aria-label` des Canvas. `#3B444B` ist das Punktraster der Stadtgebiete auf der
  Karte, es soll dunkler sein als jede Linie daneben. Für echten Seitentext bleiben
  alle drei gesperrt
- Sprungmarke zum Inhalt auf jeder Seite
- Sichtbarer Fokusring über `:focus-visible`, nie entfernen
- Jedes Canvas braucht `role="img"` und eine Beschreibung, oder `aria-hidden`
- Schalter: `role="switch"` mit `aria-checked`. Auswahl: `role="radio"`
  mit `aria-checked`. Umschalter: `aria-pressed`
- Fehler am Feld mit `role="alert"`, beim Absenden Fokus auf das erste fehlerhafte Feld
- Touch-Ziele mindestens 44px hoch
- `prefers-reduced-motion` respektieren: Bewegung raus, Inhalt bleibt
- Bewegter Inhalt über fünf Sekunden braucht einen Haltknopf
- **Nie mehr als drei Blitze pro Sekunde**, auch nicht auf dem Panel. 32 mal 16
  Zentimeter LED sind eine Großfläche, kein Bildschirmdetail
- Live-Bereiche für Fehler müssen von Anfang an im Dokument stehen. Eine Rolle,
  die erst im Fehlerfall auf `alert` wechselt, wird oft nicht vorgelesen
- Kein `innerHTML` für Werte, die von außen kommen. Ortsnamen aus OpenStreetMap
  kann jeder bearbeiten, Suchbegriffe sowieso
- `postMessage` immer mit Herkunftsprüfung und Ziel-Origin, nie mit `'*'`
- `html[lang]` beim Laden setzen, nicht erst beim Umschalten

## Technische Architektur

- Das Gerät fragt den Server alle 10 Sekunden, der Server schiebt nichts. Kommt durch
  jeden Router, kein Port-Forwarding. Dazu fragt es alle 2 Sekunden kurz nach der
  Revision (`/api/v1/rev`), damit eine Änderung auf der Webseite in gut zwei Sekunden
  auf dem Panel ist
- Der Server holt Flugdaten, Wetter und alles Externe, nie das Gerät. Sonst lägen
  Zugangsdaten in der Firmware
- Der Server schreibt Umlaute um, bevor er sendet: ö wird o, é wird e.
  `pixelfont.js` hat inzwischen Ä, Ö, Ü und ß, falls das später anders gewollt ist
- Zeitzonen sind IANA-Namen wie `Europe/Luxembourg`, nie ein fester Versatz.
  Ein Versatz ist die halbe Jahreshälfte falsch
- OTA von Anfang an. Einmal flashen, danach alles über die Webseite
- Ein API-Schlüssel pro Konto, rotierbar, mit Warnung vor dem Rotieren
- Anmeldung: E-Mail, Benutzername, Passwort. Mindestens 6 Zeichen mit mindestens
  einem Buchstaben. Weitere Verfahren später nachrüstbar
- Home Assistant spricht nur mit dem Server (`api/ha.php`), nie mit dem Gerät. Gekoppelt
  wird je Gerät mit einem Code aus sechs Zeichen, der fünf Minuten auf dem Panel steht,
  danach gilt ein eigener Schlüssel `twha_` je Home Assistant, gespeichert nur als Hash.
  Nie der API-Schlüssel des Kontos, der liegt im Flash des Geräts. Die Integration liegt
  in `homeassistant/` und seit dem 26. September 2026 zusätzlich öffentlich unter
  github.com/LennyGodart/THE-WALL-HA (HACS will ein eigenes Repo). Quelle bleibt
  `homeassistant/`, das öffentliche Repo bekommt Kopien davon

### Datenquellen

| Zweck | Dienst | Schlüssel |
| --- | --- | --- |
| Flugzeuge im Umkreis | `api.adsb.lol/v2/point/LAT/LON/RADIUS` | keiner, User-Agent mit Kontakt nötig |
| Route | VRS-Standdaten, `vrs-standing-data.adsb.lol/routes/<AB>/<callsign>.json`, gemeinfrei (CC0), stündlich gespiegelt | keiner, 6 Stunden gespeichert. Gilt nur, wenn sie zu Position und Kurs passt (`flight_route()`), sonst keine Ankunftszeit |
| Airline, Ersatz-Route | `api.adsbdb.com/v0/callsign/<callsign>` | keiner, lange cachen. Die Strecke dort ist oft veraltet |
| Wetter | Open-Meteo | keiner |
| Karte | OpenStreetMap, Nominatim | keiner, Namensnennung Pflicht, Suche nur auf Absenden |
| Ort unter dem Flugzeug | Nominatim reverse | keiner, nur für das Flugzeug auf dem Panel, je Kachel von 0,05 Grad 30 Tage gespeichert, höchstens 1 pro Sekunde |
| Karte auf dem Panel | Natural Earth 1:10 Mio, OurAirports | keiner, beide gemeinfrei. Liegt fertig gerechnet in `web/.htapp/data/`, wird nie zur Laufzeit geholt, gebaut von `tools/geo-welt.mjs` |
| Nahverkehr | OpenAPI der ATP, `cdt.hafas.de/opendata/apiserver/`, mobiliteit.lu | persönlicher Schlüssel, im Admin-Bereich verschlüsselt, 500 pro Stunde, 5 000 pro Tag |
| Spotify | OAuth, App-Zugang gehört dem Projekt, Token pro Nutzer. Gebaut in `services/spotify.php` und `modes/spotify.php`, zwischengespeichert höchstens einen Tag (Entwicklerbedingungen) | Entwicklungsmodus seit Februar 2026: Besitzer der App braucht Premium, höchstens 5 Nutzer, jeder unter User Management eingetragen. Client-ID und Secret im Admin-Bereich, das Secret verschlüsselt |

### Panel-Raster (Flugmodus)

Zeichenbreite 6px, Zeilenabstand 12px.

| Element | X | Y | Zeichen |
| --- | --- | --- | --- |
| Airline-Logo | 2 | 2 | 32×34 px Bitmap |
| Zeile 1 | 38 | 2 | 15 |
| Zeile 2 | 38 | 14 | 15 |
| Zeile 3 | 38 | 26 | 15 |
| Zeile 4 | 2 | 38 | 21 |
| Zeile 5 | 2 | 50 | 21 |
| Fortschritt | 0 | 61 | 128×2 px |

21 Zeichen sind die harte Grenze für eine Zeile über die volle Breite.
Längere Strings werden stillschweigend abgeschnitten.

Läuft ein Timer, steht oben rechts seine Ecke (x 96 bis 127, y 0 bis 8, Restzeit in
Grün), und Zeile 1 endet nach neun Zeichen. Ohne Timer wieder fünfzehn, also
LUFTHANSA CITY in voller Länge. So will es der Projektinhaber (26. September 2026):
Wichtiges wie ein Timer soll in jedem Modus irgendwo sichtbar sein, Platz aber nur
kosten, solange es läuft.

Vier Ansichten wechseln sich ab: Route, Abflug und Ankunft, Position ("FLYING
OVER" und der Ort), Messwerte. Zeile 3 ist der Flugzeugtyp ausgeschrieben wie
beim Hersteller (737 MAX 8, A321neo), nicht das ICAO-Kürzel. Der Balken ist grün
für das Geflogene, der Rest steht als Punkte da. Angelehnt an die Bilder von
theflightwall, die der Projektinhaber am 18. September 2026 geschickt hat.

### Panel-Raster (Nahverkehr)

Keine festen Spalten. Eine Zeile wird von rechts nach links gesetzt: Zeit,
dann Zeichen, dann Gleis, und das Ziel bekommt den Rest. Feste Spalten brechen,
sobald man von Minuten auf Uhrzeit umschaltet, aus einem Zeichen werden fünf.

| Element | X | Zeichen |
| --- | --- | --- |
| Linie, Bus und Tram | 2 | 4 |
| Gattung, Zug | 2 | 3 |
| Ziel beginnt | 28 bzw. 22 | Rest |
| Rechte Kante | 126 | |

Ziel in Zeichen: Bus mit Minuten 15, mit Uhrzeit 11. Zug mit Gleis 13 bzw. 9.
Deshalb sind Minuten der Standard.

Bei Bus und Tram zeigt das Ziel nur die Haltestelle ohne Ort, das passt in
179 von 186 Fällen statt in 76. Bei Zügen umgekehrt der Ort, weil die
Haltestelle 36 von 45 Mal einfach GARE heißt.

Farben: Bus Bernstein, Zug Cyan, Tram Grün. **Nicht** die Farben von
mobiliteit.lu: Bus `#752864` und Zug `#D10074` sind beide dunkles Magenta und
auf unbeleuchteten LEDs nicht zu unterscheiden.

Die Zahl ist immer die Echtzeit, die Verspätung steckt in der Farbe.
Zeichen: X Ausfall rot, ! Teilausfall bernstein, + Zusatzfahrt grün,
E Ersatzverkehr cyan. Geänderte Fahrt bekommt nichts, 46 von 276 sind so.

### Hardware

- Panel: Ocnvlia P2.5, 128×64, 320×160 mm, ASIN B0H1MPC9YZ
- Board: HUB75 Controller mit microSD, Audio und RTC, ASIN B0H69TFHJ7
- Treiber FM6126A, braucht `mxconfig.driver = HUB75_I2S_CFG::FM6126A;`
  sonst bleibt das Panel schwarz. Die gelieferten Panels tragen laut Aufkleber
  DP32019A und DP5125D und laufen trotzdem genau so, mit `line_decoder` TYPE138
- Die Panel-Bibliothek will vierzehn Pins in der Reihenfolge r1, g1, b1, r2, g2,
  b2, a, b, c, d, e, lat, oe, clk. Mit elf Pins wie in alten Beispielen leuchten
  nur einzelne Zeilen
- 1/32 Scan, E-Pin muss gesetzt sein
- Pins: R1 5, G1 4, B1 6, R2 15, G2 7, B2 17, A 8, B 18, C 10, D 9, E 16,
  LAT 11, OE 13, CLK 12
- Strom nur über die Buchse USB-C am Board, Panel hängt am Board. Die Buchse POWER bleibt frei
- Kein Acrylglas davor

## Offene Wünsche

### Entschieden am 13. September

| Frage | Entscheidung |
| --- | --- |
| Architektur | Server bleibt, das Gerät fragt alle 10 Sekunden |
| Umfang am 25. | Flugradar, Uhr, Wetter, Helligkeit, Webseite, Boot-Animation |
| Notizen, Pixel-Editor | nach dem 25. |
| Schrift | `pixelfont.js` gilt, Kleinbuchstaben ergänzt, 89 Glyphen |
| API-Schlüssel | bleibt einer pro Konto, dauerhaft sichtbar. Bewusst, weil privat |
| Schlüssel auf der Geräteseite | bleibt im Klartext, Heimnetz |
| Passwort | 6 Zeichen bleiben |
| Einrichtung | Netzname und Passwort im Klartext auf dem Panel. Der QR-Code fiel am 19. September weg, das Handy des Projektinhabers las ihn vom Panel nicht |
| Einzelflug, ETA | ehrlich umschrieben, ETA mit Tilde als Schätzung |
| Gäste mit Leserecht | dürfen Notizen senden, sonst nichts |
| Höhenfilter | Standard aus |
| Datenexport | Download und Mail |
| Registrierung | Standard nur mit Einladung, im Admin-Bereich umschaltbar |
| Einrichtungsnetz | fest "THE WALL SETUP" mit festem Passwort, wie im Entwurf. Das Passwort 12345678 darf im Code stehen, es soll nur Vorbeikommende draußen halten (18. September) |
| Server-Stack | PHP und MariaDB direkt im Webroot von thewall.godart.lu, Mails über Brevo |
| Flugwahl | nach jeder Standzeit der nächstgelegene Flug; die drei Ansichten wechseln in eigenem Takt |
| Airline-Logos | Sammlung github.com/Jxck-S/airline-logos (FlightAware, Lücken aus RadarBox) in Vollfarbe, Schrift neben dem Symbol abgeschnitten, nur auf dem Server, nie im Repo. Seit 24. September dazu neuere FlightAware-Logos und von Hand freigestellte echte Logos von den Seiten der Betreiber; der Projektinhaber hat das Laden am 24. September freigegeben. Herkunft je Datei in `tools/.logo-cache/eigen/quellen.json` |
| Impressum | Name und Kontakt-E-Mail, die Adresse ist freiwillig und fehlt, wenn sie leer ist |
| Lizenz | Seit 29. September 2026 MIT mit Commons Clause für Code (Server, Webseite, Firmware, Werkzeuge): nutzen, ändern, teilen ja, verkaufen nein. CC BY-NC-SA 4.0 für Doku und Hardware. Die Home-Assistant-Integration bleibt reine MIT. Vorher MIT und CC BY-SA 4.0, veröffentlicht war davon nur die Integration. Auf der Webseite heißt es deshalb "Quelltext offen", nicht "Open Source" |
| Veröffentlichung | eigenes öffentliches Repo ohne Verlauf, befüllt mit `tools/public-export.mjs`; dieses Repo bleibt privat |
| README | Englisch in `README.md`, Deutsch vollständig in `README.de.md`, gegenseitig verlinkt |

### Entschieden am 26. September

| Frage | Entscheidung |
| --- | --- |
| Timer | in jedem Modus sichtbar, Ecke oben rechts (Spotify unten rechts), Platz nur solange einer läuft; abgelaufen gehört dem Klingeln das ganze Panel, bis jemand stoppt, höchstens 15 Minuten |
| Wecker | einer je Gerät mit Wochentagen; Uhrzeit aus Internet oder Pufferbatterie an J1 (normale Knopfzelle, das Board lädt nicht) |
| Home Assistant | eigene Integration mit Einrichtung, Entitäten legen sich selbst an; Kopplung je Gerät mit Code auf dem Panel |
| Sprachassistent | nur optional und über Home Assistant, kommt nach Timer, Wecker und Integration |

### Animationen auf dem Gerät

Der Bildschirm soll nie wirklich aus sein, außer man will es. Geplant:

1. Boot-Animation beim Einschalten
2. Beim ersten Start: WLAN-Zeichen mit Handy daneben, animiert, als Aufforderung
   sich zu verbinden
3. Nach erfolgreicher Verbindung: die Radar-Animation als Übergang in den Betrieb
4. Übergänge zwischen den Modi, damit ein Wechsel nicht springt
5. Ruhezustand mit leiser Bewegung statt schwarzem Panel

### E-Mails

Drei sind gebaut: Adressbestätigung, Passwort-Link, Einladung. Alle hell statt
dunkel, weil Clients im Dunkelmodus selbst invertieren. Zweisprachig in einer
Mail, Englisch oben, Deutsch darunter. Aus Tabellenzellen gebaut, kein Bild,
kein JavaScript, unter 10 KB. Token-Platzhalter: `REPLACE_WITH_TOKEN`.

Noch offen: Hinweis wenn ein Gerät lange offline ist, Datenexport fertig,
Passwort wurde geändert, Konto gelöscht. Versand über eigenen SMTP,
SPF, DKIM und DMARC sind Pflicht bevor die erste Mail rausgeht.

### Hardware-Nachtrag

Das Board ist ein **RGB Matrix HUB75 S3 V1.0**, also ESP32-S3: mehr Flash als
ein klassischer ESP32. Der DMA-Puffer für 128 × 64 braucht rund 64 KB von 512 KB
internem SRAM, dazu kommen 8 MB PSRAM.
Flash mit `esptool.py flash_id` auslesen. OTA halbiert den nutzbaren Flash,
weil zwei App-Partitionen nötig sind. Zusätzlich an Bord: zwei Mikrofone,
Audio-Codec, Lautsprecheranschluss, Drei-Wege-Rad, microSD, RTC. I2C an IO1 und
IO2 mit Uhrchip PCF85063A (0x51) und Port-Baustein PCA9557 (0x19), an dem das
Rad hängt. Den Port-Baustein nur lesen, sein IO5 versorgt die Audio-Chips.
Belegung in `hardware/README.md`.

### Zeichenbefehle statt Rohdaten

Der Server schickt Zeichenbefehle, nicht Rohdaten. Die Firmware kennt vierzehn
Befehlsarten und sonst nichts, damit jeder neue Modus null Flash kostet. Seit
Firmware 0.1.9 gehören `bmp` (ein Bild mit vier Bit je Punkt, für die Karte) und
`dot` (ein Punkt mit Geschwindigkeit, der zwischen zwei Abrufen weiterläuft) dazu,
seit 0.2.0 für Spotify `prog` (Zeitleiste, die das Gerät selbst zählt), `count`
(Minuten und Sekunden) und `disc` (die Platte):

```json
{"ttl":10,"ops":[
  {"t":"logo","code":"LGL","x":2,"y":2},
  {"t":"text","x":38,"y":2,"s":"LUXAIR","c":"FFAA00"},
  {"t":"bar","x":0,"y":61,"w":79,"c":"3DE07C"}
]}
```

Seit dem 26. September 2026 stehen im Frame außerdem `timers`, `alarm` und `ring`: ab
Firmware 0.2.1 klingelt das Gerät damit selbst, auf die Sekunde und den Wecker auch ohne
Server, mit Ton über den Codec ES8311 und den Verstärker an IO3. Ein kurzer Druck aufs Rad
stoppt das Klingeln (`POST /api/v1/ring/stop`).

Lokal bleibt nur, was ohne Server laufen muss: Animationen, Uhr aus der RTC,
letzter empfangener Bildschirm, der Wecker. Airline-Logos liegen im Dateispeicher des
Flash, die microSD bleibt frei.
Animationen werden gerechnet, nie gespeichert: ein Vollbild sind 24 KB.

### Weiteres

- Karte im Flugmodus: nah bei Start und Landung, die ganze Strecke beim Verfolgen eines
  Flugs. Am 19. September 2026 mit echten Daten geprüft, Zahlen und Auftrag in
  `design/karte/`. Nah bewegt sich das Flugzeug sichtbar (bei 20 km Bildbreite sieben LEDs
  in vier Sekunden), auf der Strecke eine LED je Minute. Flüssig wird es durch
  Koppelnavigation in der Firmware, nicht durch schnellere Abfragen
- Pixel-Editor mit Frames
- Wettermodus ausbauen
- Zeitplan pro Modus
- Szenen speichern und benennen
- Bauanleitung als Webseite aus der Machbarkeitsstudie
- Airline-Logos zur Laufzeit nach ICAO-Kürzel laden, mit Downsampler im Browser
- Luxemburger Nahverkehr, Zugang geprüft am 13. September 2026: die OpenAPI der
  Administration des transports publics (mobiliteit.lu, HAFAS) auf
  data.public.lu. `location.nearbystops` für die Haltestellen-ID, `departureBoard`
  für Abfahrten in Echtzeit, Linien von AVL, CFL, Luxtram, RGTR und TICE. Lizenz
  CC BY 4.0, Quelle nennen. Persönlicher Schlüssel per Mail an
  opendata-api@atp.etat.lu (früher opendata-api@verkeiersverbond.lu). Der
  Projektinhaber hat seinen Schlüssel seit 14. September 2026, Kontingent
  500 Abfragen pro Stunde und 5 000 pro Tag. Schlüssel nur auf dem Server,
  verschlüsselt wie der SMTP-Schlüssel, nie im Repo und nie im Chat. Abfahrten je
  Haltestelle zwischenspeichern und nur abfragen, solange ein Gerät den Modus
  zeigt: eine Abfrage pro Minute sind 1 440 am Tag, also höchstens drei
  Haltestellen je Gerät, und über alle Geräte zusammen nicht mehr als drei
  verschiedene, sonst greift die Bremse bei 4 500 am Tag. Gewählt wird auf einer
  Karte aller Haltestellen des Landes; die Liste fragt der Server einmal im Monat
  im Raster ab (rund 35 Abfragen). Der Server bietet nur diese zwei Dienste (Stand 14. September
  2026), keine Fahrzeugpositionen. `departureBoard` mit `passlist=1` liefert die
  folgenden Halte jeder Fahrt mit Koordinaten und Prognose, daraus ließen sich
  Positionen schätzen
