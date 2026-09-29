# Firmware

Für das Board SEENGREAT RGB Matrix HUB75 S3 V1.0 (ESP32-S3-WROOM-1-N16R8) mit einem Panel 128 × 64, Treiber FM6126A. Pins, Uhrchip und Rad stehen in [`../hardware/README.md`](../hardware/README.md), die Schnittstelle zum Server in [`../docs/server/geraet.md`](../docs/server/geraet.md).

## Stand

| Version | Inhalt | Stand |
| --- | --- | --- |
| Stufe 1 | Testbild: Rot, Grün, Blau, Rahmen mit Raster, Ecken, Lauflicht | am 14. September 2026 auf Board und Panel geprüft, jetzt der Befehl `test` |
| 0.1.0 | Schrift aus `pixelfont.js`, die dreizehn Animationen, Einrichtungsnetz mit Einrichtungsseite, Abruf alle 10 Sekunden mit allen neun Zeichenbefehlen, Airline-Logos, Uhr aus dem Uhrchip, Rad, Updates über das Netz | lief ab 14. September 2026 auf dem Board, QR-Code Modul für Modul gleich wie auf der Webseite. Am selben Tag mit dem Handy eingerichtet, der Abruf mit dem echten Schlüssel läuft |
| 0.1.1 | Bewegung reduzieren (`motion`), Einstieg in die Abfahrtstafel (`fx: drop`), Punkte für den Rest der Flugstrecke (`rect` mit `dot`), AM und PM klein neben der Uhr (`ap`), neue Notiz mit beiden Zeilen und Rahmen, Radarblip mit zwei statt drei Blitzen pro Sekunde, 410 vergisst den Schlüssel, Einrichtungsseite ohne fremde Schrift und mit übersetzten Beschriftungen, Servername aus `WALL_SERVER` (CHANGELOG 30) | gebaut am 18. September 2026. Noch nicht auf dem Gerät ausprobiert: Update über das Netz, Halten des Rads |
| 0.1.2 | Flugwechsel in 400 ms (`fx: swap`), rollende Minuten und nachrückende Zeilen auf der Abfahrtstafel, blinkender Doppelpunkt, Helligkeit gleitet statt zu springen, hellere Töne unter Helligkeit 96, QR-Code mit 2 × 2 LEDs je Modul, Einrichtungsnetz mit geprüftem WPA2, Handy im Einrichtungsnetz, EN setzt nur nach einem langen Lauf zurück (CHANGELOG 31, 33) | am 18. September 2026 im Admin-Bereich hochgeladen und über das Netz aufgespielt, der erste echte Update-Test. Doppel-EN traf man nicht mehr, siehe 0.1.3 |
| 0.1.3 | Das Fenster für den zweiten Druck auf EN steht auf dem Panel: nach der Startanimation fünf Sekunden EN NOCHMAL = RESET mit rotem Balken; ein langer Lauf heißt jetzt 10 statt 30 Sekunden (CHANGELOG 34) | am 18. September 2026 über das Netz aufgespielt, Zurücksetzen ging wieder, startete aber zweimal neu |
| 0.1.4 | Zurücksetzen mit der Taste BOOT (fünf Sekunden halten, wie das Rad), EN startet nur neu; Moduswechsel nach "Übernehmen" schiebt wie die Rotation; QR-Code voll weiß und mindestens Helligkeit 160; nach dem Verbinden HANDY VERBUNDEN, IM BROWSER OEFFNEN und HTTP://4.3.2.1, WLAN-Zeichen tiefer (CHANGELOG 35) | am 18. September 2026 hochgeladen und über das Netz aufgespielt. BOOT reagierte am Gerät nicht, damit ging kein Zurücksetzen mehr, siehe 0.1.5 |
| 0.1.5 | EN zweimal setzt wieder zurück: das Fenster EN NOCHMAL = RESET steht ab dem ersten Bild nach dem Neustart, über der Startanimation, und der zweite Druck setzt ohne weiteren Neustart zurück; das Panel leuchtet nach jedem Start 1,2 Sekunden früher; `status` zeigt die Taste BOOT (CHANGELOG 36) | gebaut am 19. September 2026 |
| 0.1.6 | QR-Code des Einrichtungsnetzes mit Fehlerkorrektur M statt L, gleich groß (Version 3, 29 Module): 13 statt 7 kaputte Codewörter reparierbar. Nach einem Foto des Panels war er mit L in keinem Abstand lesbar (CHANGELOG 39) | am 19. September 2026 über das Netz aufgespielt; der Code blieb unlesbar, siehe 0.1.7 |
| 0.1.7 | QR-Code 3 LEDs breit und 2 hoch je Modul statt 2 × 2: die Mitte eines Moduls, wo ein Leser misst, liegt auf einer LED statt in der Lücke; nach der Rechnung lesbar aus etwa halb so viel Abstand. Rechts Netzname und Passwort in Zeilen zu fünf Zeichen, SCAN fällt weg (CHANGELOG 40) | am 19. September 2026 über das Netz aufgespielt; auch so las das Handy des Projektinhabers den Code nicht, siehe 0.1.8 |
| 0.1.8 | Kein QR-Code mehr. Der Einrichtungsbildschirm zeigt oben CONNECT A PHONE, in der Mitte WLAN-Zeichen, Linie und Handy wie nach dem Verbinden, nur wartend, unten THE WALL SETUP und PASSWORD 12345678. Helligkeit dort mindestens 96 statt 160, der Befehl `qr` entfällt, `qr.h` und `qr.cpp` gelöscht (CHANGELOG 42) | gebaut am 19. September 2026, etwa 1,13 MB |
| 0.1.9 | Zwei neue Zeichenbefehle für die Karte: `bmp` (Bild mit vier Bit je Punkt, Base64, Palette bis 15 Farben) und `dot` (Punkt mit Geschwindigkeit, läuft zwischen zwei Abrufen selbst weiter). Damit zeigt der Flugmodus eine Karte, nah bei Start und Landung, sonst die ganze Strecke (CHANGELOG 47) | gebaut am 20. September 2026, etwa 1,13 MB. Läuft seit dem 20. September auf dem ersten Gerät, am 24. September per USB auf das zweite |
| 0.1.10 | Fehler aus der Durchsicht vom 24. September (CHANGELOG 57): Das Einrichtungsnetz öffnete sich nach 24,86 Tagen Laufzeit von selbst und stand rund 25 Tage offen, jetzt sind alle Fristen Flags, die nach Ablauf fallen (siehe Fristen). Ein einzelnes 401 kostet nicht mehr Inhalt und Einrichtungsnetz, erst das dritte in Folge. Logos warten nach einem Fehler eine Minute, dann doppelt so lange, bis eine Stunde, und nur mit angenommenem Schlüssel. Eine zurückgerollte Version wird nie wieder installiert (`ota_bad`). 410 öffnet das Einrichtungsnetz für 15 Minuten. Eine Antwort zum alten Schlüssel wird nach einem neuen verworfen. Bewegung reduzieren wirkt auch in `paired` und in den einmaligen Animationen. Die Einrichtungsseite nimmt Helligkeit erst ab 8, Boot, EN-Fenster und Zurücksetzen leuchten mit mindestens 60. `dot` liest Kommazahlen, `bmp` lässt Nummern hinter der Palette durchsichtig. `X-Wall-Uptime` springt nicht mehr nach 49,7 Tagen auf 0 | gebaut am 24. September 2026, etwa 1,13 MB |
| 0.2.0 | Spotify (CHANGELOG 58): drei neue Zeichenbefehle, `prog` (Zeitleiste, die das Gerät selbst weiterzählt: Balken, Kopf, laufende Zeit, Ende, Pause), `count` (Minuten und Sekunden hoch oder herunter) und `disc` (die Platte, dreht mit 33 1/3, gleitet bei Pause in 600 ms in die Hülle), dazu die Übergänge `reel` (Walze, 560 ms), `carousel` (Karussell, 560 ms) und `lines` (die Ansage rollt zeilenweise herein, 400 ms). Im Übergang wird die alte Seite aus einer Kopie weiter gezeichnet, der alte Balken läuft leer. Enthält alles aus 0.1.10 | gebaut am 24. September 2026, etwa 1,14 MB |
| 0.2.1 | Timer und Wecker (CHANGELOG 65): Ton über den Codec ES8311 und den Verstärker NS4150B (Timer dreimal kurz, Wecker drei steigende Töne, leise beginnend, 15 Minuten lang), das Gerät klingelt auf die Sekunde aus `timers` und `alarm` im Frame und den Wecker auch ohne Server (im NVS, Uhrzeit aus dem Uhrchip), ein kurzer Druck aufs Rad oder auf BOOT stoppt das Klingeln und sagt es dem Server (`POST /api/v1/ring/stop`), ohne Seiten vom Server zeichnet es das Klingeln selbst. Dazu die Meldung im Heimnetz als `_thewall._tcp` für Home Assistant und die Befehle `audio`, `beep`, `ring` | am 26. September 2026 gebaut (1,19 MB), noch auf keinem Gerät gelaufen. Zuerst auf dem eigenen Gerät per USB oder "Jetzt aktualisieren" prüfen: `audio` muss Chip-ID 8311 melden, `beep` zwei Töne spielen, `ring` klingeln, bis das Rad gedrückt wird |

## Ablauf

1. Boot-Animation, 2,6 Sekunden. Nach dem Einstecken oder einem Druck auf EN steht darüber fünf Sekunden EN NOCHMAL = RESET, siehe Taste EN.
2. Ohne WLAN: das Einrichtungsnetz "THE WALL SETUP" (Passwort 12345678), auf dem Panel im Klartext: oben CONNECT A PHONE, in der Mitte das WLAN-Zeichen, dessen Bögen nacheinander aufleuchten, eine gepunktete Linie mit einem laufenden Strich und ein Handy mit drei wartenden Punkten, unten Netzname und Passwort. Bis 0.1.7 stand dort ein QR-Code; die Kamera eines Handys fand ihn auf den LED-Punkten nicht zuverlässig, auch nicht mit Fehlerkorrektur M und 3 × 2 LEDs je Modul (CHANGELOG 39, 40, 42). Auf diesem Bildschirm steht die Helligkeit auf mindestens 96, auch wenn zuletzt nachts gedimmt war; ab 96 bleiben auch die dunklen Töne der Szene sichtbar. Ist ein Handy im Netz, zeigt das Panel oben PHONE CONNECTED, das Handy leuchtet auf, unten stehen OPEN IN A BROWSER und HTTP://4.3.2.1. Das Netz legt die Firmware selbst mit WPA2 an und liest danach zurück, ob es wirklich verschlüsselt ist (bis zu drei Versuche, seriell "Einrichtungsnetz THE WALL SETUP, WPA2 mit Passwort", `status` zeigt "an, WPA2"). Jede Adresse im Einrichtungsnetz führt zur Einrichtungsseite unter `http://4.3.2.1`, deshalb öffnet das Handy sie meist von selbst. Die Adresse ist absichtlich keine private: manche Android-Handys melden bei einer privaten Antwort auf ihre Internetprüfung "kein Internet" und öffnen nichts (CHANGELOG 26).
3. WLAN gewählt: "VERBINDE" mit Balken bis zum Aufgeben nach 30 Sekunden. Danach zeigt das Panel seine Adresse im Heimnetz im Wechsel mit dem Einrichtungsnetz. Dieselbe Seite ist unter der Adresse erreichbar und weiter im Einrichtungsnetz, das bis zum angenommenen Schlüssel offen bleibt.
4. Schlüssel eingesetzt: der erste Abruf prüft ihn. Angenommen, laufen Radar und Anmeldung (11 Sekunden), dann schiebt sich der Inhalt vom Server herein.
5. Betrieb: Jede Seite trägt ihren Modus (`mode`); wechselt er ohne `fx`, etwa nach "Übernehmen", steigt der neue ein wie in der Rotation, `push` oder `enter` der Seite (ab 0.1.4). Abruf alle `ttl` Sekunden, dazwischen alle 2 Sekunden die Revision (`/api/v1/rev`). Passt sie nicht mehr zur letzten Antwort, kommt der nächste Abruf sofort, eine Änderung auf der Webseite ist so in gut zwei Sekunden da. Die Logos aller Seiten einer Antwort werden gleich vorgemerkt. Gezeigt wird die Seite, deren Zeitfenster gerade läuft. `fx: push` schiebt den alten Inhalt nach links (1,6 s), `fx: drop` lässt die Zeilen der Abfahrtstafel nacheinander einfallen (1,1 s), `fx: swap` wechselt den Flug (0,4 s): im rechten Block rollt jede Zeile in ihrem Streifen nach oben, die neue kommt von unten, Logo und untere Zeilen blenden über. Kommt dieselbe Tafel mit neuen Minuten (gleiche `id`, kein `fx`, mit `bands`), rücken die Zeilen nach, wenn eine Abfahrt weggefallen ist, sonst rollen in jeder Zeile nur die Spalten, die sich ändern (0,24 s). Die Uhr ohne Sekunden lässt den Doppelpunkt im Sekundentakt blinken. Eine neue Helligkeit kommt mit 2 Stufen je Bild, von 168 auf 59 in knapp zwei Sekunden. Eine neue Notiz blinkt dreimal in 1,5 Sekunden: beide Zeilen weiß und ein Rahmen in Bernstein. Steht in der Antwort `motion: reduce`, gibt es keinen Übergang, kein Blinken und keine laufende Schrift, die Helligkeit springt, die Animationen stehen im letzten Bild; das Gerät merkt es sich im NVS (`reduce`) auch für die Anzeigen ohne Server.

| Lage | Panel |
| --- | --- |
| Seiten reichen nicht mehr bis jetzt, WLAN da | `noserver`: Uhr, Datum, KEIN SERVER |
| WLAN weg | `nowifi` mit Netzname. Nach 2 Minuten öffnet zusätzlich das Einrichtungsnetz, das Panel wechselt alle 8 Sekunden zum Einrichtungsnetz |
| Schlüssel abgelehnt (401) oder 20 Geräte (403) | Adresse mit SCHLUESSEL ABGELEHNT oder ZU VIELE GERAETE im Wechsel mit dem Einrichtungsnetz, neuer Versuch jede Minute. Lief der Schlüssel bis eben, gilt er ab 0.1.10 erst nach dem dritten 401 in Folge als abgelehnt, 20 Sekunden auseinander; bis dahin bleibt der Inhalt stehen |
| Auf der Webseite entfernt (410) | das Gerät vergisst den Schlüssel, das WLAN bleibt; Adresse und Einrichtungsnetz im Wechsel, bis ein neuer Schlüssel eingesetzt ist. Das Einrichtungsnetz öffnet ab 0.1.10 für 15 Minuten, bis 0.1.9 blieb es nach langer Laufzeit zu |
| Kein Schlüssel | Adresse und Einrichtungsnetz im Wechsel, solange das Einrichtungsnetz offen ist, danach die Uhr mit Datum und Adresse, wenn "Uhr aus der Echtzeituhr" an ist |
| Update | `updating` mit Version und echtem Fortschritt, danach Neustart |

Das Einrichtungsnetz ist nur offen, solange es gebraucht wird: ohne WLAN, ohne angenommenen Schlüssel, nach einer Ablehnung des Schlüssels, 10 Minuten nach einer Einrichtung (eine Minute nach angenommenem Schlüssel), 15 Minuten nach einem 410 und nach 2 Minuten ohne Verbindung. Ohne Konto schließt es 15 Minuten nach dem Verbinden, außer ein Handy ist eingebucht. Gastnetze trennen Geräte voneinander, dann erreicht ein Handy die Seite nur über das Einrichtungsnetz. Name und Passwort sind fest, so entschieden am 13. September.

### Rad und Taste BOOT

- Rad kurz drücken: Panel aus mit `poweroff`, noch einmal drücken startet mit `boot`. Klingelt gerade ein Timer oder der Wecker, stoppt der Druck stattdessen das Klingeln (ab 0.2.1), ebenso ein kurzer Druck auf BOOT.
- Rad oder BOOT 5 Sekunden halten: WLAN und Schlüssel vergessen. Ab der ersten Sekunde zählt das Panel rückwärts (RELEASE TO KEEP, WI-FI AND KEY, 4 bis 1, roter Balken), Loslassen bricht ab. Dann steht 2,5 Sekunden ZURUECKGESETZT, das Gerät startet neu und öffnet sein Einrichtungsnetz.
- BOOT kurz drücken tut nichts. BOOT ist IO0: im Betrieb ein gewöhnlicher Eingang. Wer BOOT beim Einstecken oder beim Druck auf EN hält, startet den Chip in den Download-Modus zum Flashen, dann bleibt das Panel dunkel bis zum nächsten Start.
- Am ersten Gerät reagierte BOOT mit 0.1.4 nicht, die Ursache ist offen. Laut Schaltplan zieht die Taste (SW4) IO0 direkt auf Masse, mit 10 kΩ nach 3,3 V, der Panel-Treiber belegt IO0 nicht, und im gebauten Code liest `loop()` den Pin alle 40 ms. Seit 0.1.5 zeigt der serielle Befehl `status` "Taste BOOT gedrueckt" oder "offen". Die Webseite nennt BOOT deshalb nicht, sicher zurück setzt EN zweimal.

Welche Richtung des Rads K1, K2 oder K3 ist, steht in keiner Quelle. Die Firmware behandelt alle drei gleich.

### Taste EN

EN hängt an ESP32_EN (SW5, 10 kΩ nach 3,3 V, 1 µF nach Masse): solange die Taste unten ist, ist der Chip aus. Keine Firmware kann den ersten Druck abfangen oder warten, ob ein zweiter kommt, sie sieht nur den Start danach, genau wie nach dem Einstecken. Deshalb setzt EN mit zwei Drücken zurück:

1. EN drücken. Das Gerät startet neu, und mit dem ersten Bild steht über der Startanimation fünf Sekunden EN NOCHMAL = RESET mit einem roten Balken, der schrumpft (`Screen::EnWindow`, mit reduzierter Bewegung steht er). `setup()` zeichnet dieses Bild gleich nach `panel::begin()`, vor WLAN und Portal. Solange der Balken läuft, steht im NVS die Marke `en_armed`.
2. Noch einmal EN drücken, solange der Balken läuft. Der nächste Start findet die Marke, vergisst WLAN und Schlüssel, zeigt 2,5 Sekunden ZURUECKGESETZT und geht ohne weiteren Neustart und ohne Startanimation ins Einrichtungsnetz.

Das Fenster öffnet nach jedem Start durch EN oder Strom (`ESP_RST_POWERON`, `ESP_RST_EXT`), nie nach Update, Absturz oder Unterspannung, und nur, wenn der vorige Lauf länger als 10 Sekunden ging (`long_run`). Ein Wackelkontakt, der das Gerät immer wieder kurz neu startet, öffnet es so nicht jedes Mal. Zwei Stromausfälle kurz hintereinander nach einem langen Lauf setzen aber zurück, wenn der zweite in die fünf Sekunden fällt.

Bis 0.1.3 stand das Fenster erst nach der Startanimation, gut vier Sekunden nach dem Einschalten; es wirkte wie zweimal ganz neu starten. 0.1.4 ersetzte EN durch BOOT, das am Gerät nicht reagierte. Seit 0.1.5 wieder EN, mit dem Fenster ab dem ersten Bild. Bis dahin wartete jeder Start außerdem 1,2 Sekunden auf einen seriellen Monitor, jetzt nur mit einem Rechner an der Buchse (`HWCDC::isPlugged()`).

### Uhrzeit

- Beim Start aus dem Uhrchip PCF85063A, wenn er eine gültige Zeit hat. Ohne Pufferbatterie an J1 vergisst er sie beim Ausstecken.
- Mit Schlüssel stellt jede Antwort des Servers die Uhr (`now` plus halbe Laufzeit der Anfrage, nur bei mehr als 250 ms Abweichung). `now` ist seit dem 26. September 2026 die Mitte zwischen Anfrage und Antwort beim Server, so zählt dessen Rechenzeit nicht mit. Ohne Schlüssel fragt das Gerät `pool.ntp.org`. Mit Schlüssel spricht es nur mit thewall.godart.lu.
- Die Zeitzone kommt als POSIX-Zeichenkette vom Server (`tz`) und bleibt im NVS, Vorgabe Luxemburg.
- In den Uhrchip schreibt die Firmware UTC, beim ersten Stellen und danach höchstens stündlich.

### Updates

`fw` in der Antwort startet den Download von `WALL_SERVER/api/v1/firmware/<version>`. Andere Adressen lehnt die Firmware ab. Der SHA-256 wird beim Schreiben gerechnet und mit `fw.sha256` und `X-Checksum-Sha256` verglichen, nur dann wird die neue Partition aktiv. Schlägt es fehl, bleibt die alte Version, und dieselbe Version wird 30 Minuten nicht erneut versucht.

Eine frisch installierte Version muss 60 Sekunden laufen. Startet sie dreimal hintereinander nicht so lange, schaltet die Firmware auf die vorige Partition zurück. Ab 0.1.10 schreibt sie vorher ihre Version als `ota_bad` ins NVS: die vorige installiert sie dann nie wieder und meldet sie bei jedem Abruf im Kopf `X-Wall-Fw-Bad`, der Server bietet sie diesem Gerät nicht mehr an. Die Sperre fällt, sobald eine andere neu installierte Version 60 Sekunden lief. Eine vorige Version ohne diese Sperre (0.1.9 und älter) holt eine kaputte neue nach dem Zurückrollen wieder, dann hilft nur eine höhere Nummer auf dem Server.

### Fristen

`millis()` läuft nach 49,7 Tagen über, und `(int32_t)(a - b)` kippt schon nach 24,86 Tagen. Bis 0.1.9 stand deshalb nach 24,86 Tagen Laufzeit das Einrichtungsnetz offen, ein neuer Schlüssel wartete 24 Tage auf seine Prüfung, und nach 49,7 Tagen liefen Boot-Animation, Begrüßung und ZURUECKGESETZT noch einmal ab. Seit 0.1.10 gilt:

- Eine Frist ist ein Flag mit Zeitpunkt (`bootAnim`, `offAnim`, `wiped`, `pairedAt`, `apHoldUntil`, `upAddr`, `upLong`, `offLong`). `closeWindows()` und `manageWifi()` löschen oder setzen sie jede Runde, so steht keine länger als eine Runde über ihrem Ende.
- `0` heißt nie "sofort". Wer jetzt fragen will, setzt `nextPollAt = millis()`. Gesetzte Zeitpunkte, die 0 als "keiner" nutzen, bekommen Bit 0 (`holdAp()`).
- Laufzeiten, die länger als 49 Tage stimmen müssen, kommen aus `esp_timer_get_time()` (64 Bit), etwa `X-Wall-Uptime`.
- Abstände, die sich selbst erledigen (Blinken, Übergänge, Schub), dürfen bei `now - start` bleiben, wenn ein Flag sie beendet.

### Sicherheit

- Der Schlüssel liegt im NVS und geht nur als `Authorization`-Kopf an `WALL_SERVER`, geprüft gegen die Stammzertifikate in `src/certs.h`. Die Einrichtungsseite zeigt ihn nie an, `/api/state` meldet nur, ob einer gesetzt ist.
- Schreibende Anfragen an die Einrichtungsseite brauchen `Content-Type: application/json`, eine IP-Adresse als Host und, wenn der Browser einen schickt, einen passenden `Origin`. Eine fremde Webseite im selben Netz kann so nichts umstellen, auch nicht über DNS-Rebinding.
- Netznamen aus der Umgebung setzt die Seite nur als `textContent`.

## Bauen und flashen

PlatformIO Core, aus diesem Ordner:

```
pio run -t upload
pio device monitor
```

Beim ersten Bauen lädt PlatformIO die Plattform `espressif32` 7.1.3 mit Arduino-Kern 2.0.17, dazu die Bibliotheken ESP32-HUB75-MatrixPanel-DMA 3.0.15 und ArduinoJson 7.4.3.

- `platformio.ini`: 16 MB Flash, Octal-PSRAM (`qio_opi`, ohne startet das Modul nicht), serielle Ausgabe über die USB-Buchse des Boards, `NO_GFX` statt Adafruit GFX. Ein eigener Server geht mit `-DWALL_SERVER=\"https://...\"`
- `partitions.csv`: zwei App-Partitionen mit je 4 MB für Updates über das Netz, knapp 8 MB Dateispeicher (Logos), dazu Speicher für Absturzberichte

Die Firmware ist 1,1 MB groß, gut ein Viertel einer App-Partition.

### Werkzeuge

| Befehl | Zweck |
| --- | --- |
| `node tools/font.mjs` | `src/glyphs.h` aus `web/assets/js/lib/pixelfont.js`, nach jeder Änderung an der Schrift |
| `node tools/certs.mjs <cacert.pem>` | `src/certs.h` aus einer Stammliste im Format von certifi. PlatformIO bringt eine mit: `%USERPROFILE%\.platformio\penv\Lib\site-packages\certifi\cacert.pem` |

### Serielle Befehle

115200 Baud. Öffnen der Schnittstelle startet das Board neu.

| Befehl | Wirkung |
| --- | --- |
| `status` | Firmware, Gerät, WLAN, Schlüssel gesetzt, Zustand, Uhrzeit, Uhrchip, Rad, Handys im Einrichtungsnetz, Gründe der letzten zehn Starts |
| `anim`, `anim <name>`, `auto` | Liste, eine Animation in Schleife, zurück zum Ablauf |
| `lang de`, `lang en` | Sprache des Panels, bis der Server eine schickt |
| `bright <0-255>` | Helligkeit, bis der Server eine schickt |
| `test` | Testbild, 10 Sekunden |
| `rtc`, `i2c` | Uhrchip lesen, Adressen am I2C-Bus |
| `forget` | WLAN und Schlüssel vergessen, Neustart |
| `mem` | freier Speicher |
| `audio` | Codec gefunden, Chip-ID (8311, wenn er antwortet), was gerade klingt (ab 0.2.1) |
| `beep` | Testton, zwei Töne einmal |
| `ring` | Klingeln wie bei einem abgelaufenen Timer, mit Ton und Anzeige, bis zum Druck aufs Rad |
| `shot` | gezeigtes Bild, eine Zeile je Pixelzeile als Hex, dazu Breite, Höhe, Helligkeit |
| `log 1`, `log 0` | Protokoll von Abrufen (mit oder ohne Handshake), Revisionen, Logos, Seitenwechseln |
| `restart` | Neustart |

## Strom

Das Board hat zwei USB-C-Buchsen, beschriftet USB-C und POWER. Der Strom kommt über USB-C, dort wird auch geflasht; das Board gibt die 5 V über die Klemme VH-4P und das beiliegende Kabel ans Panel weiter. POWER bleibt frei (laut Projektinhaber, 18. September 2026). Ein Netzteil mit 5 V und 3 A reicht. Vorgabe der Helligkeit bis zur ersten Antwort des Servers: 140 von 255.

## Dateien

`src/glyphs.h` und `src/certs.h` sind erzeugt, siehe Werkzeuge.

### src/main.cpp (37)

Zustände, Anzeige, serielle Befehle. Welche Anzeige gerade dran ist, entscheidet `choose()`, gezeichnet wird mit 30 Bildern pro Sekunde.

- Zeit: `epochMs()`, `setEpochMs(int64_t ms)`, `applyTz()`, `localNow(struct tm &out)`, `writeRtcSoon(uint32_t now, bool force)`, `onNtp(struct timeval *)`, `manageNtp()`
- Fristen: `holdAp(uint32_t ms, bool extend)` hält das Einrichtungsnetz offen, `closeWindows(uint32_t now)` löscht abgelaufene Fristen, `uptimeS()` Laufzeit aus dem 64-Bit-Zeitgeber
- Server: `num64(JsonVariantConst v)`, `requestFrame(uint32_t now)`, `offerFirmware(JsonObjectConst fw, uint32_t now)`, `takeFrame(uint32_t now)` (verwirft Antworten zum alten Schlüssel über `keyGen`, zählt 401 in `authFails`), `pollRev(uint32_t now)`, `handleLogos()`, `handleOta(uint32_t now)`
- WLAN und Einrichtungsseite: `manageWifi(uint32_t now)`, `wifiStateName()`, `keyStateName()`, `stateJson()`, `startPortal()`
- Zeichnen: `drawTest(Grid &g, uint32_t ms)`, `drawClock(Grid &g, const struct tm &t, bool de)`, `drawHold(Grid &g, uint32_t held, bool de)`, `drawWiped(Grid &g, bool de)` (ZURUECKGESETZT, nach EN zweimal beim Start, nach Rad oder BOOT vor dem Neustart), `drawLive(Grid &g, uint32_t now, const struct tm *local, bool de)`, `choose(uint32_t now)`, `draw(Screen sc, uint32_t now)`
- Bedienung: `handleWheel(uint32_t now)` (Rad und BOOT; EN zweimal steht in `setup()`), `handleCommand(String cmd)`, `handleSerial()`, `guardUpdate()`
- Klingeln ab 0.2.1: `ringFromFrame(JsonDocument &d)` (Timer, Wecker in den NVS, `ring` des Servers), `alarmToday(const struct tm &local)`, `updateRing()` (fünfmal pro Sekunde: klingelt etwas, das nach dem letzten Druck aufs Rad anfing? Timer ab ihrem Ende, der Wecker ab seiner Uhrzeit, jeweils 15 Minuten, der Server, solange seine Seiten gelten; schaltet ein am Rad ausgeschaltetes Panel an), `stopRing()`, `drawRing(Grid &g, const struct tm *local, bool de)` (ohne Seiten vom Server), `startMdns()` (`_thewall._tcp` mit TXT `id`, `fw`, `srv`, sobald das WLAN steht). `choose()` zeigt das Klingeln vor dem ausgeschalteten Panel, die Helligkeit ist dabei mindestens 96
- Spotify ab 0.2.0: `parseSongWin(JsonObjectConst fxw, anim::SongWin &w)` liest die Fenster eines Übergangs; `drawLive()` startet bei `fx` `reel`, `carousel` oder `lines` den Übergang, zeichnet die alte Seite aus `live::oldPage()` weiter und lässt in der ersten Hälfte den alten Balken leer laufen
- `setup()`, `loop()`

### src/config.h

Version `FW_VERSION` und die Marke `FW_MARKER` ("THEWALL-FW " und die Version, beim Start seriell ausgegeben, damit sie in der Datei bleibt; der Server liest daran die Version einer hochgeladenen Firmware), `WALL_SERVER` mit `serverHost()` (nur der Name, für Einrichtungsseite und `paired`), die vierzehn Panel-Pins, I2C-Pins und -Adressen, Name und Passwort des Einrichtungsnetzes, die Farben aus `anim.js`.

Extern genutzt von: allen Dateien in `src/`

### src/grid.h, src/grid.cpp (13)

Raster 128 × 64 im PSRAM, zeichnet wie `pixelfont.js`: Text in UTF-8, ohne `mixed` in Großbuchstaben, 6 Pixel Vorschub.

- `Grid::begin()`, `clear()`, `set(int x, int y, uint32_t c)`, `get(int x, int y)`, `rect(int x, int y, int w, int h, uint32_t c)`, `frame(...)`, `text(int x, int y, const char *s, uint32_t c, int scale, bool mixed)`, `copyFrom(const Grid &o)`
- statisch: `Grid::width(const char *s, int scale)`, `Grid::centreX(const char *s, int scale)`
- `jround(double v)` rundet wie `Math.round`, `parseColour(const char *hex, uint32_t fallback)`
- intern: `nextCp()`, `upper()`, `findGlyph()`

Extern genutzt von: `anim.cpp`, `live.cpp`, `panel.cpp`, `main.cpp`

### src/clocktext.h, src/clocktext.cpp (2)

- `clockText(char *out, size_t n, const struct tm &t, bool h24, bool sec, bool suffixed = true)` 20:14, 20:14:33, 08:14 PM; ohne `suffixed` fehlt AM und PM, dann zeichnet `live.cpp` es klein daneben
- `dateText(char *out, size_t n, const struct tm &t, bool de)` FRI 25 SEP

Extern genutzt von: `anim.cpp`, `live.cpp`, `main.cpp`

### src/anim.h, src/anim.cpp (9)

Die Animationen aus `anim.js` mit denselben Dauern: boot, wifi, connecting, address, paired, modeswap, waiting, note, resting, noserver, nowifi, updating, poweroff, dazu pixeldemo. `PX.rnd` wird in `double` nachgerechnet, damit Staub und Pixel an denselben Stellen stehen wie in der Vorschau.

Abweichungen von der Vorlage, weil das Gerät echte Werte hat: Netzname, Adresse, Konto und Version aus `AnimOpts`. In `paired` steht statt eines erfundenen Rufzeichens der Server aus `WALL_SERVER` (`THEWALL.GODART.LU`), der Blip um das gefundene Flugzeug blinkt zweimal pro Sekunde, die Anmeldezeilen heißen WLAN, SCHLUESSEL, KONTO, GERAET, BEREIT. `connecting` und `updating` zeigen echten Fortschritt, `noserver` ohne gültige Uhrzeit NOCH KEINE UHRZEIT. `wifi` zeigt WLAN-Bögen (Mitte bei y 33), Linie und Handy. Ohne Handy im Netz oben CONNECT A PHONE in Bernstein, die Linie gepunktet mit einem laufenden Strich, das Handy grau mit drei Punkten, die nacheinander aufleuchten, unten Netzname und PASSWORD mit dem Passwort in Bernstein; mit `AnimOpts.phone` (ein Handy hängt im Einrichtungsnetz) oben PHONE CONNECTED in Grün, die Linie durchgezogen, das Handy mit hellem Bildschirm, unten OPEN IN A BROWSER und HTTP://4.3.2.1. Bis 0.1.7 stand ohne Handy ein QR-Code da (CHANGELOG 42).

Unter Helligkeit 96 (`AnimOpts.bright`, die Helligkeit, die das Panel gerade zeigt) nehmen `resting` und die Nachtflüge hellere Töne, sonst verschwinden sie hinter der CIE-Kurve der Panel-Bibliothek: Uhr `B37800` statt `6B4A00`, Staub und Mond `4E5A63`, Sterne `8E9AA3`.

- `anim::begin()`, `anim::find(const char *key)`, `anim::count()`, `anim::at(int i)`
- `anim::draw(const char *key, Grid &g, double t, const AnimOpts &o)` Schleifen laufen über die Dauer, resting rechnet mit der ganzen Zeit
- `anim::push(Grid &g, const Grid &from, const Grid &to, double k)` Übergang wie modeswap
- `anim::swap(Grid &g, const Grid &from, const Grid &to, double k)` Flugwechsel: im rechten Block bis y 37 rollt jede Zeile in ihrem eigenen Streifen von 12 Pixeln nach oben, die alte hinaus, die neue von unten nach; Logo und untere Zeilen je Kanal gemischt, ab y 61 sofort neu
- `anim::roll(Grid &g, const Grid &from, const Grid &to, const int *bands, int n, double k)` neue Minute auf der Tafel: fällt eine Abfahrt weg, rücken die anderen Zeilen nach (gleiche Abfahrt erkannt an Linie und Ziel links von x 70, `sameRow()`), neue rollen im eigenen Band von unten herein; wo eine Zeile bleibt, rollen nur die geänderten Spalten um 8 Pixel nach oben
- `anim::drop(Grid &g, const Grid &to, const int *bands, int n, double k)` Einstieg in die Abfahrtstafel wie `sceneEnter` im Entwurf: alles außerhalb der Zeilen sofort, Zeile i ab k = 0,12 + i × 0,14, rutscht in 0,16 von rechts herein, solange nur ihre Linie in gedimmtem Bernstein

Extern genutzt von: `live.cpp` (Zeichenbefehl `anim`), `main.cpp`

### src/panel.h, src/panel.cpp (6)

- `panel::begin(uint8_t brightness)` FM6126A, TYPE138, vierzehn Pins
- `panel::show(const Grid &g)` schreibt nur geänderte Pixel
- `panel::setBrightness(uint8_t b)`, `panel::brightness()`, `panel::frame()` das zuletzt gezeigte Bild, `panel::invalidate()`

Extern genutzt von: `main.cpp`

### src/settings.h, src/settings.cpp (22)

NVS unter `wall`: `ssid`, `pass`, `key`, `tz`, `lang`, `bright`, `clock`, `reduce`, `note_rev`, `restarts`, `reset_log`, `ota_fresh`, `boot_tries`, `ota_bad` (ab 0.1.10, zurückgerollte Version), `al_on`, `al_h`, `al_m`, `al_days`, `al_ack`, `al_set` (ab 0.2.1, der Wecker vom Server), dazu für EN zweimal `en_armed` und `long_run` (bis 0.1.3 und wieder ab 0.1.5; 0.1.4 löschte sie). Die Schlüssel des Testbilds (`variant`, `cycling`, `cycle`) löscht `begin()`.

- `settings::begin()`, `get()`, `setWifi(ssid, pass)`, `setKey(key)`, `setTz(tz)`, `setLang(lang)`, `setBright(b)`, `setClock(on)`, `setReduce(on)`, `setNoteRev(rev)`, `setAlarm(on, h, m, days, ack, set)` (schreibt nur bei einer Änderung), `forget()`
- `resetLog()`, `noteReset(uint32_t number, const char *reason)` die letzten zehn Starts als "Nummer:Grund/ROM-Grund", etwa "17:Neustart/12". ROM-Grund 15 ist Unterspannung, 21 ein Neustart über USB
- `otaFresh()`, `setOtaFresh(bool)`, `bootTries()`, `setBootTries(uint8_t)`, `setOtaBad(version)` (gelesen über `get().otaBad`)
- `resetArmed()`, `setResetArmed(bool)` die Marke für den zweiten Druck auf EN, `lastRunLong()`, `setLastRunLong(bool)` ob der vorige Lauf länger als 10 Sekunden ging

Extern genutzt von: `main.cpp`

### src/wlan.h, src/wlan.cpp (16)

Heimnetz als Station mit eigener Wiederholung (zwei Handshake-Fehler in Folge gelten als falsches Passwort, Netz dreimal nicht gefunden, `JOIN_TIMEOUT_MS` 30 Sekunden ohne Verbindung), Einrichtungsnetz mit DNS auf das Gerät, Netzsuche im Hintergrund. Das Einrichtungsnetz baut `startSetupAp()` selbst: `wifi_config_t` genullt, WPA2 mit CCMP, danach zurückgelesen; `WiFi.softAP()` füllt die Struktur ungenullt und meldet einen Fehler nur im Log, dann liefe das Netz mit der alten oder der offenen Vorgabe weiter.

- `wlan::begin(const String &hostname)`, `loop()`, `join(ssid, pass)`, `state()`, `stateAge()`, `everUp()`, `ssid()`, `rssi()`, `ip()`
- `setAp(bool on)`, `apOn()`, `apSecured()` (laut Treiber WPA2 mit Passwort), `apIp()`, `startScan()`, `scanning()`, `scanJson()`
- intern: `startSetupAp()`, `applyMode()`, `setupIp()` und die Auswertung der Trenngründe

Extern genutzt von: `main.cpp`, `portal.cpp`

### src/portal.h, src/portal.cpp, src/portal_page.h (2)

Webserver auf Port 80. Die Seite ist ein Nachbau der Einrichtungsseite aus den Entwürfen, zweisprachig (auch die Beschriftungen für Vorleser), liest den Zustand alle 2 Sekunden und schreibt Statusfelder nur, wenn sich der Text ändert. Sie lädt keine fremde Schrift: im Einrichtungsnetz gibt es kein Internet, und im Heimnetz ginge die Adresse des Handys an den Schriftdienst. Ist `WALL_SERVER` ein anderer Server, steht dessen Name statt thewall.godart.lu in der Seite.

| Weg | Inhalt |
| --- | --- |
| `GET /` | Einrichtungsseite |
| `GET /api/state` | WLAN, Schlüssel (nur ob gesetzt und der Zustand), Gerät, Helligkeit, Uhr |
| `GET /api/scan` | Netze in Reichweite, startet eine Suche |
| `POST /api/wifi` | `{ssid, pass}`, Passwort leer oder 8 bis 63 Zeichen |
| `POST /api/key` | `{key}`, `tw_live_` und 16 Hex-Zeichen |
| `POST /api/bright`, `/api/clock`, `/api/test`, `/api/lang` | `{v}` (8 bis 255, darunter wirkt das Panel tot), `{on}`, `{}`, `{lang}` |

Alles andere leitet im Einrichtungsnetz auf `http://4.3.2.1/` um, damit Handys die Seite von selbst öffnen, auch die Prüfadressen von Android (`/generate_204`), Apple (`/hotspot-detect.html`) und Windows (`/connecttest.txt`). `/favicon.ico` und `/wpad.dat` bekommen 404.

- `portal::begin(const Hooks &hooks)`, `portal::loop()`

Extern genutzt von: `main.cpp`

### src/fetch.h, src/fetch.cpp (17)

Eigene Aufgabe auf Kern 0 für alle Anfragen an den Server, TLS gegen `certs.h`. Ein einziger `HTTPClient` lebt so lange wie die Aufgabe, denn sein Destruktor schließt die Verbindung; so bleibt sie zwischen den Abrufen offen. Antworten liegen im PSRAM. Der Abruf schickt die Kopfzeilen aus `docs/server/geraet.md`, ab 0.1.10 nach einem Zurückrollen dazu `X-Wall-Fw-Bad`.

- `fetch::begin()`, `requestFrame(key, id, telemetry)`, `requestRev(key, id)`, `requestLogo(key, code)`, `requestFirmware(key, id, url, sha256, size)`, `requestRingStop(key, id)` (ab 0.2.1, POST ohne Antwort an `loop()`; kommt es nicht an, klingelt es auf dem Server höchstens bis zum Ende seiner Viertelstunde, das Gerät bleibt still)
- `frameBusy()`, `revBusy()`, `logoBusy()`, `takeFrame(FrameResult &)`, `takeRev(RevResult &)`, `takeLogo(LogoResult &)`
- `otaState()`, `otaPermille()`, `otaError()`, `lastError()`

Extern genutzt von: `main.cpp`, `live.cpp` (`LOGO_BYTES`)

### src/audio.h, src/audio.cpp (4)

Ton ab 0.2.1. I2S0 als Master mit 16 kHz, 16 Bit, MCLK 4,096 MHz an IO38, BCLK IO48, WS IO21, Daten IO14; der Codec ES8311 an I2C 0x18 als Slave, eingerichtet mit den Registerwerten aus Espressifs Treiber `es8311` (esp-bsp, Apache 2.0, nachgeschrieben); der Verstärker NS4150B an IO3, nur an, solange etwas klingt. Das Panel belegt auf dem S3 die LCD-Einheit, nicht I2S. Die Töne rechnet eine eigene Aufgabe auf Kern 0 aus einer Sinustabelle, mit 5 ms An- und Abschwellen gegen Knacken: Timer 1 760 Hz dreimal kurz, Wecker 988, 1 319 und 1 568 Hz, beginnt bei einem Viertel und ist nach 30 Sekunden voll da. Die Versorgung von Codec und Mikrofon-Wandler (IO5 des Port-Bausteins) ist ab Start an, die Firmware schaltet daran nichts. Antwortet der Codec nicht, bleibt das Gerät still, alles andere geht.

- `audio::begin()`, `present()`, `chipId()`, `play(Tone)` mit `Off`, `Timer`, `Alarm`, `Test`, `playing()`

Extern genutzt von: `main.cpp`

### src/live.h, src/live.cpp (11)

Die Antwort des Servers und die vierzehn Zeichenbefehle wie `ops.js` (ab 0.2.0 dazu `prog`, `count`, `disc`), dazu `rect` mit `dot` (nur jede n-te Spalte), `bmp` (Bild mit vier Bit je Punkt, Base64 im Durchlauf dekodiert, ohne Zwischenpuffer) und `dot` (Punkt mit Geschwindigkeit, `t0` ist der Zeitpunkt der Position) und `clock` mit `ap` (AM und PM klein, unten bündig; `seg` rahmt nur Ziffern). `Ctx.reduce` hält die Laufschrift an, schaltet das Blinken der Notiz und des Doppelpunkts ab und gibt Animationen ihren Endzustand. `Ctx.bright` reicht die gezeigte Helligkeit an die Animationen weiter (Nachttöne). `clock` ohne Sekunden und ohne `seg` blinkt den Doppelpunkt in ungeraden Sekunden aus. Animationen behalten ihren Takt, solange dieselbe läuft, auch über neue Antworten hinweg. Logos liegen im RAM (40 Plätze) und als `/logo/<code>.rgb` im Dateispeicher. Bei 404 fragt das Gerät eine Stunde nicht erneut, nach jedem anderen Fehler erst nach einer Minute, dann nach 2, 4, 8, 16, 32 Minuten und höchstens einer Stunde (ab 0.1.10; bis 0.1.9 sofort wieder). `dot` liest `x`, `y`, `vx`, `vy` als Kommazahlen und läuft höchstens 60 Sekunden weiter, `bmp` lässt eine Nummer hinter dem Ende der Palette durchsichtig, beides wie `ops.js`.

- `live::begin()`, `setFrame(JsonDocument *doc)`, `frame()`, `pageAt(int64_t nowMs)`, `covers(int64_t nowMs)` mit 5 Sekunden Nachlauf
- `render(Grid &g, JsonObjectConst page, const Ctx &ctx)`
- `prefetchLogos(const JsonDocument &doc)`, `wantedLogo()`, `logoLoading(code)`, `logoArrived(code, status, rgb)`, `storageUsedPercent()`
- Spotify ab 0.2.0: `drawProg(Grid &g, JsonObjectConst op, int64_t nowMs, int over)` (Zeitleiste, `over` erzwingt die Breite), `progWidth(op, nowMs)`, `keepShown(page)`, `keepOld()`, `oldPage()` (Kopien der gezeigten und der vorigen Seite im PSRAM), `findOp(page, t)`; intern `drawCount()`, `drawDisc()`, `mmss()`, `clampTo()`. Alles wie in `ops.js`

Extern genutzt von: `main.cpp`

### src/board.h, src/board.cpp (7)

- `board::begin()` I2C an IO1 und IO2
- `rtcPresent()`, `rtcRead(time_t &utc)`, `rtcWrite(time_t utc)` PCF85063A an 0x51, UTC
- `wheelPresent()`, `wheel()` PCA9557 an 0x19, liest nur, damit IO5 die Audio-Versorgung nicht verliert
- `scan()`

Extern genutzt von: `main.cpp`

### tools/font.mjs, tools/certs.mjs

Siehe Werkzeuge.
