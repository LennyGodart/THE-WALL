# device/, modes/ und die Geräteschnittstelle

Pfade relativ zu `web/.htapp/`.

## Geräteschnittstelle

Das Gerät fragt alle zehn Sekunden, der Server schiebt nie. Das kommt durch jeden Router ohne Port-Forwarding. Damit eine Änderung auf der Webseite nicht bis zum nächsten Abruf wartet, fragt das Gerät zusätzlich alle zwei Sekunden nach der Revision (unten). Wie die Firmware damit umgeht, steht in [`firmware/README.md`](../../firmware/README.md).

### Abruf

```
GET /api/v1/frame
Authorization: Bearer tw_live_0123456789abcdef
X-Wall-Id: wall-7f3a91        Pflicht, 4 bis 32 Zeichen a-z 0-9 -, aus der MAC
X-Wall-Fw: 0.4.2
X-Wall-Ssid: Heimnetz-2G
X-Wall-Rssi: -54              dBm
X-Wall-Uptime: 570000         Sekunden
X-Wall-Temp: 41               Grad Celsius
X-Wall-Flash: 71              Prozent belegt
X-Wall-Restarts: 2
X-Wall-Fw-Bad: 0.1.11         nur nach einem Zurückrollen, ab Firmware 0.1.10
```

Ein unbekanntes Gerät mit gültigem Schlüssel wird beim ersten Abruf angelegt ("Wall", "Wall 2", ...), höchstens 20 je Konto. Bis auf der Webseite einmal "Übernehmen" gedrückt wurde, zeigt es "HALLO", den Benutzernamen und "MODUS WAEHLEN".

| Status | Bedeutung |
| --- | --- |
| 200 | Antwort unten |
| 400 | `X-Wall-Id` fehlt oder ist ungültig |
| 401 | Schlüssel fehlt oder ist ungültig (nach Rotation sofort) |
| 403 | 20 Geräte auf dem Konto |
| 410 | das Gerät wurde auf der Webseite entfernt (einmalig, danach legt der nächste Abruf es neu an), oder das Konto zum Schlüssel wurde gelöscht (30 Tage lang). Firmware ab 0.1.1 vergisst dann den Schlüssel, das WLAN bleibt |
| 429 | mehr als ein Abruf pro Sekunde je Gerät (`Retry-After: 2`), oder mehr als 120 Abrufe pro Minute von einer Adresse (`Retry-After` bis zum nächsten Fenster) |

Nach 50 Versuchen mit demselben falschen Schlüssel in einer Stunde antwortet der Server von dieser Adresse mit diesem Schlüssel sofort mit 401, ohne in der Datenbank zu suchen. Andere Geräte hinter derselben Adresse sind davon nicht betroffen.

### Antwort

```json
{
  "v": 1,
  "ttl": 10,
  "now": 1789290000000,
  "bright": 168,
  "tz": "CET-1CEST,M3.5.0,M10.5.0/3",
  "iana": "Europe/Luxembourg",
  "lang": "de",
  "pages": [
    {"from": 1789289999000, "to": 1789290008000, "id": "flight", "fx": "push", "ops": [
      {"t": "logo", "code": "LGL", "x": 2, "y": 2},
      {"t": "text", "x": 38, "y": 2, "s": "LUXAIR", "c": "FFAA00"},
      {"t": "bar", "x": 0, "y": 61, "w": 79, "c": "3DE07C"}
    ]}
  ],
  "user": "LENNY",
  "device": {"id": 1, "name": "Wohnzimmer"},
  "rev": "12.3.0.3f9a1c",
  "fw": {"version": "0.5.0", "url": "https://thewall.godart.lu/api/v1/firmware/0.5.0", "sha256": "...", "size": 1300000},
  "timers": [1789290300000],
  "alarm": {"h": 6, "m": 30, "d": 31, "ack": 0, "set": 1789200000000},
  "ring": {"since": 1789289990000}
}
```

- `pages` deckt die nächsten 25 Sekunden ab, jede Seite mit festem Zeitfenster in Millisekunden. Das Gerät zeigt die Seite, deren Fenster gerade läuft. Die Fenster hängen an der Uhr, deshalb schließen zwei Antworten ohne Sprung aneinander an.
- `fx` markiert den Beginn eines neuen Modus in der Rotation: `push` schiebt den alten Inhalt nach links hinaus (1,6 s), `drop` ist der Einstieg in die Abfahrtstafel (1,1 s): Kopf und Streifen sofort, die Zeilen fallen nacheinander ein, deren obere Kanten in `bands` stehen (etwa `[12, 24, 36, 48]`). `drop` kennt die Firmware ab 0.1.1, ältere schneiden hart. `swap` steht auf der ersten Seite eines neuen Flugs (0,4 s): im rechten Block rollt jede Zeile in ihrem Streifen nach oben, die neue kommt von unten, Logo und untere Zeilen blenden über; ab Firmware 0.1.2. Ohne `fx` und mit derselben `id` wie die Seite davor (die Abfahrtstafel jede Minute) rücken ab 0.1.2 die Zeilen nach, wenn eine Abfahrt weggefallen ist, sonst rollen die Spalten, deren Ziffern sich ändern, in den Bändern aus `bands` (0,24 s). Jede Seite trägt ihren Modus (`mode`, aus `frame_mode_pages()`), der Nahverkehr dazu `enter: drop`. Wechselt der Modus ohne `fx`, etwa nach "Übernehmen" auf der Webseite, steigt der neue ab Firmware 0.1.4 ein wie in der Rotation, `push` oder `drop`. Die Vorschau der Geräteseite zeigt dieselben Übergänge (`TWAnim.push`, `drop`, `swap`, `roll`), beim Klick auf einen anderen Modus sofort, sonst nur, wenn im Lauf der Zeit die nächste Seite drankommt.
- `motion: "reduce"` steht nur drin, wenn auf der Geräteseite "Bewegung reduzieren" an ist: kein Schub, kein Einfallen, kein Blinken (auch nicht der Doppelpunkt), die Laufschrift steht, die Helligkeit springt statt zu gleiten, Animationen im Endzustand. Das Gerät merkt es sich im NVS für die Anzeigen ohne Server (ab 0.1.1).
- `now` ist die Uhrzeit des Servers in Millisekunden, seit dem 26. September 2026 die Mitte zwischen Eingang der Anfrage und Antwort (`frame_clock_ms()`). Das Gerät stellt seine Uhr auf `now` plus die halbe Laufzeit, wenn sie mehr als 250 ms abweicht. In der Laufzeit steckt auch, wie lange der Server gerechnet hat, die Mitte gleicht das aus. Vorher stand hier der Beginn des Rechnens: nach einem langsamen Frame (Spotify mit Warteschlange und Cover, bis 1,3 s) ging die Uhr um eine halbe Sekunde zurück und beim nächsten wieder vor, Laufschrift, Countdown und Balken sprangen. Die Kopfzeile `Server-Timing: app;dur=` nennt die Rechenzeit.
- `tz` ist die POSIX-Zeichenkette für `setenv("TZ")`, der ESP32 kennt keine IANA-Namen.
- `bright` 0 bis 255, nachts auf 35 Prozent gedimmt, wenn "Nachts dimmen" an ist (Sonnenauf- und -untergang am Standort). Ab Firmware 0.1.2 gleitet das Panel zum neuen Wert, 2 Stufen je Bild. Unter 96 nehmen Ruhezustand und Nahverkehr hellere Töne, sonst verschwinden gedimmte LEDs hinter der CIE-Kurve der Panel-Bibliothek.
- `fw` steht nur drin, wenn das Gerät jetzt aktualisieren soll.
- `rev` ist die Revision des Geräts: `settings_rev`, `note_rev`, `update_requested_at` und `ring_rev()` mit Punkten verbunden. Sie ändert sich bei "Übernehmen", einer neuen Notiz, "Jetzt aktualisieren" und wenn ein Timer oder der Wecker zu klingeln beginnt oder aufhört.
- `timers`, `alarm` und `ring` stehen seit dem 26. September 2026 in der Antwort, für die Firmware ab 0.2.1 (`ring_frame_fields()`): die Enden der laufenden Timer in Millisekunden, der Wecker mit Stunde, Minute und Tagen als Bits (Bit 0 Montag bis Bit 6 Sonntag, 31 heißt Montag bis Freitag), gestoppt (`ack`) und gestellt (`set`), und `ring`, solange gerade etwas klingelt. Die Firmware klingelt damit auf die Sekunde und den Wecker auch ohne Server. Fehlt `alarm`, ist der Wecker aus. Ältere Firmware übergeht die Felder.
- Läuft ein Timer, hat jede Seite eines Modus eine Ecke mit der Restzeit: ein schwarzes Rechteck und ein `count` in Grün, rechtsbündig. Beginnt im Fenster der Antwort ein Klingeln, endet die Seite davor genau dann, und eine Seite mit `mode: "ring"` schließt an. Beim Koppeln mit Home Assistant steht eine Seite mit `mode: "pairing"` da, bei ausgeschaltetem Panel eine leere mit `mode: "off"` und `bright` 0 (siehe `device/timers.php` und `device/ha.php`).
- Alle Texte sind schon umgeschrieben (ö wird o) und auf 21 Zeichen gekürzt.

### Zeichenbefehle

| `t` | Felder | Bedeutung |
| --- | --- | --- |
| `text` | `x, y, s, c, z` | Text, `x: "c"` heißt mittig, `z` Vergrößerung (1 wenn fehlt), 6 px Vorschub |
| `bmp` | `x, y, w, h, p, d` | Bild mit vier Bit je Punkt, Base64 in `d`. 0 ist durchsichtig, sonst zeigt die Zahl in die Palette `p` (bis 15 Farben als Hex ohne Raute). Die Karte des Flugmodus ist 128 × 51 und braucht so 3 264 Bytes, mit Base64 4 352 Zeichen. Ab Firmware 0.1.9 |
| `dot` | `x, y, vx, vy, c, t0` | Ein Punkt von 3 × 3 mit weißem Kern, um ein Pixel freigestellt. `vx` und `vy` sind LEDs je Sekunde, `t0` der Zeitpunkt der Position in Millisekunden: das Gerät rechnet ihn zwischen zwei Abrufen weiter, damit das Flugzeug auf der Karte nicht ruckt. Mit reduzierter Bewegung steht er. Ab Firmware 0.1.9 |
| `ticker` | `x, y, s, c, v` | Laufschrift ab `x` nach links, `v` Pixel pro Sekunde, nach sechs Leerzeichen wieder von vorn. Den Versatz rechnet das Gerät aus seiner Uhr (Millisekunden seit 1970 mal `v` durch 1000, modulo Breite von Text und sechs Leerzeichen plus 1), deshalb läuft sie über Seitenwechsel ohne Sprung weiter. Mit reduzierter Bewegung steht sie am Anfang |
| `rect` | `x, y, w, h, c, dot` | gefülltes Rechteck; mit `dot` (2 bis 16) nur jede `dot`-te Spalte, so zeichnet der Flugmodus den Rest der Strecke als Punkte. Firmware 0.1.0 kennt `dot` nicht und füllt das Rechteck |
| `bar` | `x, y, w, h, c` | Balken, `h` 2 wenn fehlt |
| `frame` | `x, y, w, h, c` | Rahmen |
| `logo` | `code, x, y` | Airline-Logo 32×34 in Vollfarbe, holt das Gerät über `/api/v1/logo/{code}` und legt es in seinen Dateispeicher; bei 404 Farbblock mit Kürzel |
| `clock` | `x, y, z, c, h24, sec, seg, g, ap` | Uhrzeit, rechnet das Gerät aus seiner RTC. Ohne `h24` gelten 24 Stunden. Mit `ap` (nur bei 12 Stunden und `z` über 1) stehen AM und PM klein neben den Ziffern, unten bündig, 4 Spalten Abstand; `seg` rahmt nur Ziffern. `g` ist die Farbe dieser Rahmen, ab Firmware 0.2.2: die Uhrfarbe mit einem Sechstel der Helligkeit (`clock_ghost()`), ältere zeichnen sie fest in `2A2000`. Ohne `sec` und ohne `seg` blinkt der Doppelpunkt ab Firmware 0.1.2 im Sekundentakt, außer mit `motion: reduce` |
| `date` | `y, c, lang` | Datum mittig, ebenfalls vom Gerät |
| `anim` | `id` | eingebaute Animation: `boot`, `wifi`, `connecting`, `address`, `paired`, `modeswap`, `waiting`, `note`, `resting`, `noserver`, `nowifi`, `updating`, `poweroff`, `pixeldemo`. Ein unbekannter Name zeichnet nichts |
| `prog` | `x, x1, y, b, hy, t0, d, c, s, soon, p, pl, nl, nh` | Zeitleiste, die das Gerät selbst weiterzählt (Spotify). Balken von `x` bis `x1` in Zeile `b` (2 hoch), gespielt in `c`, der Rest als Punkte in jeder zweiten Spalte, Kopf weiß ab `hy`. Darüber in Zeile `y` die laufende Zeit über dem Kopf, das Ende rechts, mit `s` links 0:00, jeweils nur, wenn Platz ist. `t0` ist der Beginn des Titels in Millisekunden seit 1970, `d` die Länge. Mit `soon` werden die Punkte in den letzten zehn Sekunden bernsteinfarben. `p` ist die Position in der Pause: dann grau, statt der Zeit `pl` (PAUSE). `nl` ohne Zeiten, `nh` ohne Kopf. Ab Firmware 0.2.0 |
| `count` | `x, y, c, a, v, to, t0, m, pre` | Minuten und Sekunden, die das Gerät selbst zählt: `v` steht fest, `to` zählt bis dahin herunter (aufgerundet), `t0` seit dahin hoch, höchstens `m`. `a`: `l` beginnt bei `x`, `r` endet vor `x`, `c` mittig um `x`. `pre` steht davor, etwa ein Minus. Ab Firmware 0.2.0 |
| `disc` | `cx, cy, r, x, sp, t0` | Die Platte aus dem Layout Platte: Kreis um `cx, cy` mit Radius `r`, nur ab Spalte `x` (rechts der Hülle). Rillen in jedem dritten Radius, Etikett in Bernstein, ein heller Streifen dreht mit 33 1/3 Umdrehungen je Minute. `sp` spielt; `t0` ist der letzte Wechsel von Pause und Weiter, dann gleitet sie in 600 ms heraus oder in die Hülle. Mit reduzierter Bewegung steht sie. Ab Firmware 0.2.0 |

Eine Seite mit `flash` (Revision der Notiz) lässt bei neuer Notiz dreimal in 1,5 Sekunden die ersten beiden Textzeilen weiß blinken und legt dabei einen Rahmen in Bernstein ums Panel (ab 0.1.1, vorher nur die erste Zeile).

Spotify bringt ab Firmware 0.2.0 drei Übergänge mit, jeweils mit den Fenstern in `fxw`: `c` Cover, `t` Text, `b` Zeitleiste als `[x0, y0, x1, y1]`, `l` die Oberkanten der Textzeilen, `p` ihr Abstand, `s` 1, wenn der Textblock als Ganzes eine Zeile hoch schiebt.

| `fx` | Dauer | Bedeutung |
| --- | --- | --- |
| `reel` | 560 ms | Walze, ein Titel läuft aus: das Cover-Fenster rollt nach oben hinaus, das neue kommt von unten (cubic-bezier 0.77, 0, 0.175, 1). Der Text folgt 120 ms später: nach der Ansage (`s: 1`) schiebt sich der Block eine Zeile hoch, sonst rollt jede Zeile in ihrem Streifen, 40 ms nach der vorigen, je 280 ms. In der ersten Hälfte läuft der alte Balken leer, dann steht die neue Zeitleiste |
| `carousel` | 560 ms | Karussell, am Handy weitergesprungen: wie die Walze, das Cover waagerecht |
| `lines` | 400 ms | Die Ansage rollt herein: alles steht sofort, nur die Textzeilen rollen einzeln |

Während dieser Übergänge zeichnet das Gerät die alte Seite weiter, aus einer Kopie: ein Standbild ließe zwei Laufschriften versetzt übereinander stehen. Mit reduzierter Bewegung und in älterer Firmware ein harter Schnitt.

Farben als sechs Hex-Zeichen ohne `#`.

### Revision

```
GET /api/v1/rev                 dieselben Köpfe Authorization und X-Wall-Id
-> {"rev": "12.3.0"}
```

Das Gerät fragt das alle zwei Sekunden über die offene TLS-Verbindung und holt sofort einen neuen Frame, wenn die Revision nicht mehr zur letzten Antwort passt. Die Antwort ist eine Datenbankabfrage ohne Telemetrie und ohne Anlegen des Geräts. Der vierte Teil, `ring_rev()`, ist ein kurzer Hash über die Timer und den Wecker und darüber, ob sie gerade klingeln: so steht das Klingeln in gut zwei Sekunden auf dem Panel, auch wenn sich an den Einstellungen nichts geändert hat. Zeigt das Gerät Spotify (Modus, Rotation oder Vorrang), hängt ein fünfter Teil daran, `spotify_rev()`: er ändert sich bei einem anderen Titel, bei Pause und Weiter und bei einem Sprung im Titel. Dafür fragt der Server Spotify höchstens alle fünf Sekunden je Konto, so kommt ein Wechsel am Handy in zwei bis sieben Sekunden aufs Panel.

| Status | Bedeutung |
| --- | --- |
| 200 | `{"rev": ...}` |
| 400 | `X-Wall-Id` fehlt oder ist ungültig |
| 401 | Schlüssel fehlt oder ist ungültig |
| 404 | Gerät unbekannt, es muss zuerst `/api/v1/frame` fragen |
| 429 | mehr als 600 pro Minute von einer Adresse oder mehr als zwei pro Sekunde je Gerät, mit `Retry-After` |

### Logo und Firmware

```
GET /api/v1/logo/LGL            -> 3 264 Byte application/octet-stream, X-Logo-Size: 32x34, 7 Tage cachebar
                                   RGB888 zeilenweise von oben links, 0,0,0 heisst LED aus; 404 als JSON, wenn es keins gibt
GET /api/v1/firmware/0.5.0      -> Binärdatei, X-Checksum-Sha256, Content-Length
```

Beide mit demselben `Authorization`-Kopf, die Firmware zusätzlich mit `X-Wall-Id`.

### Klingeln stoppen

```
POST /api/v1/ring/stop          dieselben Köpfe Authorization und X-Wall-Id
-> {"ok": true}
```

Ab Firmware 0.2.1: ein kurzer Druck aufs Rad (oder auf BOOT), während etwas klingelt. Das Gerät ist dann schon still, der Server erfährt es hier, damit auch die Seite fürs Klingeln verschwindet und Home Assistant es weiß. Klingelnde Timer fallen weg, der Wecker merkt sich den Zeitpunkt (`ring_stop()`). Höchstens zehn pro Minute je Gerät.

## device/modes.php (4)

Verzeichnis der Anzeigemodi.

- `mode_register(string $id, array $def): void` Felder: `order`, `label` (en, de), `available` (Vorgabe `true`), `rotatable`, `defaults`, `sanitize` fn(Eingabe, bisher): array, `build` fn(ctx, von, bis): array Seiten, `enter` Übergang in der Rotation, `push` (Vorgabe) oder `drop`, `active` fn(ctx): bool, optional: ohne etwas zu zeigen fällt der Modus aus der Rotation, `corner` fn(ctx): array, optional: wo die Ecke eines laufenden Timers sitzt (`box`, `right`, `y` wie `CORNER_SPOT`), ohne Angabe oben rechts. Läuft ein Timer, steht er in `$ctx['corner']`, und ein Modus, der oben rechts selbst etwas zeigt, macht dort Platz
- `modes(): array` nach `order` sortiert (sortiert wird beim Registrieren)
- `mode_get(string $id): ?array`, `mode_is_active(string $id, array $ctx): bool`

Extern genutzt von: `device/devices.php`, `device/frame.php`, allen `modes/*.php`, `pages/device.php`, `views/admin.php`

## device/devices.php (21)

Geräte, Einstellungen und Rechte. Rollen `owner`, `edit`, `view`. Konstanten: `DEVICE_ONLINE_SECONDS` 60, `DEVICE_MAX_PER_ACCOUNT` 20, `DEVICE_REMOVED_DAYS` 30.

Testgeräte (Spalte `test`) legt der Admin für ein Konto an, damit es die Geräteseite ohne Hardware sieht. Sie haben die Geräte-ID `test-` und acht Hex-Zeichen, fragen den Server nie, zählen im Admin-Bereich nicht bei Online, Firmware und Budget und zeigen in Statusleiste und Geräteumschalter "Testgerät". Vorschau, Übernehmen, Freigaben und Einstellungen funktionieren wie bei echten Geräten.

- `device_defaults(): array` Modus flight, Rotation flight und clock, Helligkeit 168, `reduce` (Bewegung reduzieren) aus, `off` (Panel aus) aus, keine `timers`, der Wecker aus (`alarm_defaults()`), `Europe/Luxembourg`, Standort Luxemburg-Stadt, dazu die `defaults` jedes Modus
- `device_settings(array $device): array` gespeicherte Einstellungen über die Vorgaben gelegt
- `device_sanitize(array $in, array $current, string $role): array` gibt `[Einstellungen, Fehler]`; Notizen darf jede Rolle, bei `view` endet es dort. `off` nur mit Bedienrecht. Timer und Wecker fasst es nie an, die haben eigene Schnittstellen. Ein Standort ohne Namen heißt je nach Sprache des Geräts "Custom point" oder "Eigener Punkt"
- `devices_for_user(int $userId): array` eigene und freigegebene, mit `role`
- `device_for_user(int $deviceId, int $userId): ?array`
- `device_online(array $d): bool` Abruf in den letzten 60 Sekunden
- `device_telemetry(array $d): array` Einträge der Statusleiste je `[en, de]`: online seit oder zuletzt gesehen, WLAN, Firmware, Temperatur, Speicher; beim Testgerät nur der Hinweis darauf und während einer Kopplung mit Home Assistant deren Code, weil es kein Panel hat; dieselbe Liste für die Seite und `GET /api/device/{id}/status`
- `device_add_test(array $user): ?int` Testgerät anlegen, höchstens eins je Konto (gibt sonst die vorhandene ID), `null` bei 20 Geräten
- `device_remove_test(int $userId): int` Testgeräte eines Kontos samt Freigaben entfernen
- `device_is_test(array $d): bool`
- `device_settings_update(int $deviceId, callable $fn): ?array` liest, ändert und schreibt nur, wenn niemand dazwischen geschrieben hat: `settings_rev` dient als Vergleich, sonst von vorn, höchstens fünfmal, ohne Transaktion (CHANGELOG 62). `$fn` bekommt die aktuellen Einstellungen und gibt `[neu, Notiz geändert]` oder `null`. Zählt `settings_rev` und bei neuer Notiz `note_rev` hoch. Seit Home Assistant und Timer schreiben mehrere Stellen dieselbe Zeile; vorher hätte "Übernehmen" einen eben gestellten Timer überschrieben
- `device_rev_value(array $device, ?array $settings = null, int $ownerId = 0): string` die Revision für `/api/v1/rev` und Home Assistant, mit `ring_rev()` und `spotify_rev()`
- `device_note_after_apply(array $clean, array $current, ?int $now = null): array` gibt `[Einstellungen, Notiz geändert]`: eine neue Notiz setzt `note_at` auf jetzt; ein anderer Modus oder eine andere Rotation ohne neue Notiz setzt es auf 0, die Notiz verliert ihren Vorrang sofort. Bis zum 26. September 2026 stand sie nach einem Wechsel noch bis zu zehn Minuten vorn, die Vorschau zeigte schon den neuen Modus, und das Panel wirkte festgeklemmt
- `device_note_hide(int $deviceId): void` `note_at` auf 0 und `settings_rev` hoch, `applied` bleibt, wie es ist; schreibt nichts, wenn keine Notiz vorn steht
- `device_uid_valid(string $uid): bool`
- `device_register_poll(array $owner, string $uid, array $t): array` Gerät anlegen oder finden, Telemetrie speichern; legen zwei erste Abrufe es gleichzeitig an, liest der zweite das Gerät des ersten statt mit 500 zu enden
- `device_mark_removed(int $ownerId, string $uid): void` merkt sich ein entferntes Gerät 30 Tage in der Einstellung `removed_devices`
- `device_take_removed(int $ownerId, string $uid): bool` einmal `true` für ein entferntes Gerät, dann ist der Eintrag weg (`api_frame()` antwortet 410)
- `device_delete(int $id): void` samt Freigaben, offenen Einladungen und allem zu Home Assistant
- `device_shares(int $deviceId): array` Freigaben und offene Einladungen
- `account_export(array $user): array` alles zum Konto für den Datenexport, ohne Passwort-Hash

Extern genutzt von: `api/device.php`, `api/web.php`, `pages/admin.php`, `pages/device.php`, `pages/settings.php`, `views/admin.php`, `views/device.php`, `views/settings.php`

## device/frame.php (25)

Baut die Antwort oben. Dieselbe Funktion liefert die Vorschau im Browser (`POST /api/preview/{id}` mit dem Entwurf), die Vorschau zeigt also genau, was das Panel bekommt.

- `frame_build(array $device, array $settings, array $owner, bool $preview = false): array` vom Wichtigsten an: Kopplungscode für Home Assistant, Klingeln, Panel aus, Begrüßung vor dem ersten "Übernehmen", dann die Modi (`frame_normal_pages()`). Beginnt im Fenster ein Klingeln, endet die normale Seite dort und die Seite fürs Klingeln schließt an. Beim Kopplungscode und beim Klingeln mindestens Helligkeit 96 (`RING_BRIGHT_MIN`). Die Vorschau zeigt immer den gewählten Modus, mit der Ecke eines laufenden Timers
- `frame_mode_list(array $settings, array $ctx, bool $preview): array` welche Modi gerade dran sind: einer oder die Rotation ohne die, die nichts zu zeigen haben, Spotify zuerst, wenn so eingestellt, eine neue Notiz zehn Minuten vorn
- `frame_normal_pages(array $ctx, float $from, float $to): array` die Seiten der Modi in 30-Sekunden-Schritten, bei einem laufenden Timer mit `$ctx['corner']` und der Ecke auf jeder Seite, die vor seinem Ende beginnt
- `note_front_left(array $settings, ?int $now = null): int` Sekunden, die eine neue Notiz noch vor dem gewählten Modus steht, 0 ohne Text oder nach dem Ausblenden (`note_at` 0)
- `frame_mode_pages(string $modeId, array $ctx, float $from, float $to): array` fängt Fehler eines Modus ab und zeigt "FEHLER"
- `frame_page(float $from, float $to, array $ops, array $extra = []): array`
- `clock_ghost(string $c): string` Farbe der Geisterrahmen im Segment-Gesicht, jeder Kanal der Uhrfarbe durch sechs, wie `ghostOf()` in `device.js`
- `frame_clock_ms(float $received, float $sent): int` die Uhrzeit für das Gerät, die Mitte zwischen Eingang der Anfrage und Antwort, siehe `now` oben
- `frame_slots(float $from, float $to, float $len): array`
- `frame_hello(array $owner, string $lang): array`
- `frame_brightness(array $s): int` nachts 35 Prozent, mindestens 8
- `frame_is_night(float $lat, float $lon, int $now): bool` Sonnenzeiten von gestern, heute und morgen: Tag ist, wenn einer der drei Aufgänge vor und sein Untergang nach jetzt liegt. `date_sun_info()` rechnet mit dem Kalendertag der Serverzone, für ein Gerät in New York oder Sydney war die Nacht sonst stundenweise verschoben
- Befehle: `op_text(int|string $x, int $y, string $s, string $c, int $z = 1): array`, `op_ticker(int $x, int $y, string $s, string $c, int $v = 14): array`, `op_rect(int $x, int $y, int $w, int $h, string $c): array`, `op_bar(int $x, int $y, int $w, string $c, int $h = 2): array`, `op_frame(int $x, int $y, int $w, int $h, string $c): array`, `op_logo(string $code, int $x = 2, int $y = 2): array`, `op_clock(int $x, int $y, int $z, string $c, bool $h24, bool $sec, bool $seg, bool $ap = false): array`, `op_date(int $y, string $c, string $lang): array`, `op_anim(string $id, array $params = []): array`. Die Karte baut ihre Befehle in `modes/flight_map.php` und `services/geomap.php`
- Helfer: `hex6(string $colour): string`, `clock_text_length(bool $h24, bool $sec): int`, `clock_width(bool $h24, bool $sec, int $z): int` (Breite mit kleinem AM oder PM), `local_time(string $tz, ?int $ts = null, bool $h24 = true): string`

Konstanten: `FRAME_TTL` 10, `FRAME_WINDOW` 25, `ROTATION_SLOT` 30, `NOTE_FRONT_SECONDS` 600, Farben `C_ACCENT`, `C_WHITE`, `C_DIM`, `C_CYAN`, `C_GREEN`, `C_RED`, `C_LINE`, `C_TRACK`.

Extern genutzt von: `api/device.php`, `api/web.php`, allen `modes/*.php`, `views/admin.php`

## device/timers.php (25)

Timer, Wecker, Klingeln und die Ecke eines laufenden Timers (CHANGELOG 63). Timer stehen in `settings.timers` (`id`, `end`, `total`, `label`, `by`), höchstens fünf gleichzeitig, 1 Sekunde bis 24 Stunden, Namen bis 12 Zeichen. Nach dem Ende klingelt ein Timer, bis ihn jemand stoppt, höchstens 15 Minuten, dann fällt er weg. Der Wecker ist einer je Gerät (`settings.alarm`: `on`, `time`, `days` nach ISO, `ack`, `set`), gerechnet in der Zone des Geräts. Konstanten: `TIMER_MAX` 5, `TIMER_MAX_SECONDS` 86400, `TIMER_LABEL_MAX` 12, `RING_SECONDS` 900, `RING_BRIGHT_MIN` 96, `CORNER_SPOT` (Feld x 96 bis 127, y 0 bis 8, Ziffern rechtsbündig in Zeile 2), `RING_WHEEL_FW` 0.2.1.

- `alarm_defaults(): array`, `alarm_time_valid(string $t): bool`
- `alarm_get(array $s): array` der Wecker, bereinigt
- `alarm_apply(array $cur, array $in, int $now): array` gibt `[Wecker, Fehler oder null]`; 6:30 wird 06:30, ohne Tag abgelehnt. Jede Änderung setzt `set` auf jetzt: wer um 7:05 auf 7:00 stellt, wird heute nicht mehr geweckt
- `alarm_times(array $a, string $tz, int $now): array` `[letzter <= jetzt, nächster > jetzt]`, eine Woche zurück und voraus, beide `null`, wenn er aus ist. Fällt die Zeit in die Lücke der Sommerzeit, schiebt PHP sie eine Stunde weiter
- `alarm_ringing(array $s, int $now): ?int` Beginn, wenn er gerade klingelt: nach `ack` und nicht vor `set`, höchstens 15 Minuten
- `timer_label(string $l): string`, `timers_list(array $s, ?int $now = null): array` bereinigt, nach Ende sortiert, ausgeklingelte fallen weg
- `timer_add(array $s, int $seconds, string $label, string $by, int $now): array` gibt `[Einstellungen, Timer, null]` oder `[null, null, [code, en, de]]` mit `invalid_value` oder `too_many_timers`
- `timer_cancel(array $s, ?string $id, int $now): array` einen oder alle
- `ring_now(array $s, int $now): array` was klingelt: `timers`, `alarm`, `since`
- `ring_next(array $s, int $now): ?int` wann das nächste Klingeln beginnt
- `ring_stop(array $s, int $now): array` klingelnde Timer fallen weg, der Wecker merkt sich `ack`
- `ring_rev(array $s, int $now): string` Teil der Revision, ändert sich genau bei Beginn und Ende eines Klingelns
- `corner_timer(array $s, float $from): ?array` der nächste Timer, der im Fenster noch läuft
- `corner_ops(array $t, array $spot, bool $count, int $now): array` schwarzes Feld und Restzeit in Grün; ab Firmware 0.2.0 zählt das Gerät selbst (`count`), ältere bekommen den Stand beim Abruf; ab 100 Minuten steht zum Beispiel 1H45
- `corner_spot(string $modeId, array $ctx): array` die Vorgabe oder was der Modus sagt (Spotify unten rechts)
- `frame_fw_at_least(array $ctx, string $version): bool` die Vorschau immer
- `ring_ops(array $ring, array $ctx): array` das ganze Panel: der Wecker mit der Uhrzeit groß, ein Timer mit 0:00 und seinem Namen, darunter wie man stoppt (Rad erst ab 0.2.1)
- `ring_pages(array $ctx, float $from, float $to): array` Seite mit `mode: "ring"`, ohne `flash`: die Firmware merkt sich dafür die Nummer der Notiz, eine alte Notiz blinkte sonst später noch einmal
- `pairing_ops(string $code, int $expires, array $ctx): array` HOME ASSISTANT, der Code groß in zwei Dreiergruppen, die Restzeit
- `ring_frame_fields(array $s, int $now): array` `timers`, `alarm`, `ring` für die Firmware ab 0.2.1
- `timers_public(array $s, int $now): array`, `alarm_public(array $s, int $now): array`, `ring_status_public(array $s): array` für Webseite und Home Assistant

Extern genutzt von: `api/device.php`, `api/ha.php`, `api/web.php`, `device/devices.php`, `device/frame.php`, `modes/spotify.php`, `modes/transit.php`, `pages/device.php`, `views/device.php`

## device/ha.php (8)

Was von Home Assistant auch Rahmen, Seiten und Löschen brauchen, die Schnittstelle steht in `api/ha.php`. Konstanten: `HA_PAIR_SECONDS` 300, `HA_PAIR_ATTEMPTS` 5, `HA_CODE_ALPHABET` (ohne 0, O, 1, I, 2, Z, 5, S, 8, B), `HA_CODE_LENGTH` 6, `HA_LINKS_MAX` 5 je Gerät, `HA_TOKEN_PREFIX` `twha_`.

- `ha_pairing_pending(int $deviceId, int $now): ?array` laufende Kopplung mit `code` und `expires_at`
- `ha_code_new(): string`, `ha_code_normalize(string $in): string` (klein, Leerzeichen und Striche gehen)
- `ha_token_hash(string $token): string` SHA-256, `ha_token_new(): string` `twha_` und 43 Zeichen Base64url
- `ha_links_public(int $deviceId): array` gekoppelte Instanzen für die Einstellungen
- `ha_forget_device(int $deviceId): void` beim Löschen von Gerät oder Konto
- `device_bump_rev(int $deviceId): void` `settings_rev` hoch, damit das Panel in gut zwei Sekunden neu fragt

Extern genutzt von: `api/ha.php`, `api/web.php`, `auth/users.php`, `device/devices.php`, `device/frame.php`, `pages/settings.php`

## device/firmware.php (8)

Firmware über die Luft. Eine Datei `.htdata/firmware/thewall-<x.y.z>.bin` ablegen oder im Admin-Bereich hochladen, der Server trägt sie mit SHA-256 selbst ein. Konstante `FIRMWARE_MAX_BYTES` 4 MB, so groß ist eine App-Partition.

- `firmware_store(string $bin): array` hochgeladene Datei prüfen (App-Image, 64 KB bis 4 MB, Marke `THEWALL-FW x.y.z`, Version noch nicht da) und ablegen; gibt `[Version, Fehler en, Fehler de]`

- `firmware_scan(): void`
- `firmware_latest(): ?array` höchste Version
- `firmware_rollout(): int` Anteil in Prozent, Schritte von 5
- `firmware_auto(): bool` ohne Nachfrage aktualisieren, Vorgabe aus
- `firmware_bucket(string $uid): int` fester Platz 0 bis 99 aus der Geräte-ID
- `firmware_available(array $device): ?array` neuere Fassung als `X-Wall-Fw`, und der Platz des Geräts liegt unter dem Rollout-Anteil
- `firmware_offer(array $device, string $bad = ''): ?array` nur mit "Automatisch" oder wenn der Besitzer in den letzten 24 Stunden "Jetzt aktualisieren" gedrückt hat; nie die Version aus `X-Wall-Fw-Bad`, die auf diesem Gerät schon einmal zurückgerollt wurde

Extern genutzt von: `api/admin.php`, `api/device.php`, `api/web.php`, `pages/admin.php`, `pages/settings.php`

## modes/

Jede Datei ruft `mode_register()` auf. Die `build`-Funktionen ruft `frame_mode_pages()`, deshalb gibt es keine direkten Aufrufer.

| Datei | id | Reihenfolge | Rotation | Einstellungen (Vorgabe) |
| --- | --- | --- | --- | --- |
| `modes/flight.php` | `flight` | 10 | ja | `radius` 40 NM (5 bis 150, Schritt 5), `alt` Hohe Überflieger ausblenden (aus), `mil` Militär farbig (an), `dwell` Standzeit je Flug 8 s (3 bis 60), `view` Ansicht wechseln alle 4 s (2 bis 30), `views` welche Ansichten laufen (alle fünf, die Karte nur ab Firmware 0.1.9, weltweit), `hold` bei Start und Landung dranbleiben (an), `pin` Einzelflug, `empty` wenn nichts fliegt: `clock`, `wait` oder `off` (clock) |
| `modes/clock.php` | `clock` | 20 | ja | `face` small, big, seg (big), `color` #FFAA00, `h24` an, `sec` aus, `wx` Wetterleiste an, `night` nachts dimmen an |
| `modes/weather.php` | `weather` | 30 | ja | `unit` C oder F, `wind` aus, `view` now oder forecast (forecast) |
| `modes/transit.php` | `transit` | 35 | ja | `stops` bis zu drei Haltestellen `{id, name, short, modes, lines}`, Kurzname höchstens 18 Zeichen, `modes` Zug, Tram, Bus (alle), `hide` ausgeblendete Linien, `rows` 3 bis 5 (4), `walk` Fußweg 0 bis 15 Minuten (0), `fmt` `min` oder `clock` (min), `notes` Meldungen als Laufschrift (an), `school` Schulbusse (aus) |
| `modes/spotify.php` | `spotify` | 38 | ja, nur solange Musik läuft | `layout` A1 Klassisch, A2 Groß, A3 Farbe aus dem Cover, A4 Platte (A1), `views` zusätzlich zu "Läuft": `album`, `queue`, `cover` (keine), `view` Sekunden je Ansicht 5 bis 30 (10), `first` Musik geht vor (aus) |
| `modes/notes.php` | `notes` | 40 | nein | `line1`, `line2` je 21 Zeichen, `flash` bei neuer Notiz dreimal blinken (an, zwei Wechsel pro Sekunde) |
| `modes/pixel.php` | `pixel` | 50 | nein | kommt nach dem 25., bis dahin nur Vorschau, `available` false |

### modes/flight.php (32)

Konstanten: `FLIGHT_DWELL_MIN` 3, `FLIGHT_DWELL_MAX` 60, `FLIGHT_VIEW_MIN` 2, `FLIGHT_VIEW_MAX` 30, `FLIGHT_PLAN_TTL` 120, `FLIGHT_DOTS` `4E5A63`, `FLIGHT_UNITS` (erlaubte Einheiten je Einstellung, die erste ist die Vorgabe).

Raster aus `CLAUDE.md`: Logo bei (2,2), Zeilen 1 bis 3 bei x 38 mit 15 Zeichen, Zeilen 4 und 5 bei x 2 mit 21 Zeichen, Fortschritt 128×2 bei y 61. Läuft ein Timer, endet Zeile 1 nach neun Zeichen (LUFTHANSA statt LUFTHANSA CITY), rechts daneben steht seine Ecke; ohne Timer wieder fünfzehn.

Vier Ansichten, angelehnt an die Bilder von theflightwall, die der Projektinhaber am 18. September 2026 geschickt hat. Zeile 3 ist immer der Typ, ausgeschrieben wie beim Hersteller (`737 MAX 8`, `A321neo`, aus `services/aircraft.php`), sonst das ICAO-Kürzel.

| Ansicht | Zeile 1 | Zeile 2 | Zeile 4 | Zeile 5 | Balken |
| --- | --- | --- | --- | --- | --- |
| `route` | Airline | `LUX→LIS` | Abflugort | Zielort | nein |
| `progress` | Rufzeichen | Strecke | `DEPARTED ~1H 20M AGO` oder `JUST DEPARTED` | `ARRIVING IN ~58 MIN` oder `ARRIVING NOW` | ja |
| `position` | Rufzeichen | Strecke, ohne Route die Entfernung | `FLYING OVER` | Ort, etwa `METZ, FRANCE`; ohne Ort die Koordinaten unter `POSITION` | ja |
| `metrics` | Rufzeichen | Entfernung `12.3NM` | `ALT:7.0KFT,SPD:485KMH` | `TRK:263DEG,VR:+3.3M/S` | nein |

Die Einheiten der Messwerte stellt die Geräteseite ein (Karte "Einheiten"): `ualt` `ft` (tausend Fuß) oder `m`, `uspd` `kmh`, `kt` oder `mph`, `uvr` `ms` oder `fpm`, `udist` `nm`, `km` oder `mi`. Passt Zeile 4 nicht in 21 Zeichen, etwa 11 280 m mit 1 278 km/h, steht `A:11280M,S:1278KMH` da. Zeile 5 lässt `DEG` weg, wenn sie sonst nicht passt (`TRK:263,VR:+3200FPM`). Den Umkreis misst adsb.lol in Seemeilen, er bleibt so.

Deutsch: `ABFLUG VOR ~2H`, `GERADE GESTARTET`, `ANKUNFT IN ~58 MIN`, `LANDET JETZT`, `FLIEGT UEBER`. Der Balken ist grün für das Geflogene, der Rest Punkte in jeder zweiten Spalte (`rect` mit `dot`). Abflug und Ankunft sind Schätzungen, deshalb die Tilde: die Ankunft ist die Reststrecke durch das Tempo über Grund, die letzten 100 Seemeilen mit höchstens 250 Knoten (`FLIGHT_APPROACH_KT`, Sinkflug und Anflug), und nie kürzer als der Abstieg aus der jetzigen Höhe mit 1 500 Fuß pro Minute (`FLIGHT_DESCENT_FPM`). Ist ein großes Flugzeug (Kategorie A3 bis A6) unter 20 000 Fuß und noch mehr als 100 Seemeilen vom Ziel, steigt es noch oder fliegt nach dem Start kurz waagerecht: für den Rest gilt das Reisetempo von 420 Knoten (`FLIGHT_CRUISE_KT`): sonst stand beim Start in LaGuardia nach Kansas City "ARRIVING IN ~6H 50M". Der Abflug ist die geflogene Strecke durch das Tempo, für ein großes Flugzeug unter 20 000 Fuß ebenfalls 420 Knoten. Dauern unter 20 Minuten genau, darunter in Fünfern, ab einer Stunde in Zehnern. Der Ort kommt von `geocode_reverse()`.

Die Strecke kommt aus zwei Quellen, und beide kennen je Rufzeichen eine Strecke, aber keinen Tag. Dasselbe Rufzeichen fliegt nachmittags den Rückflug, in den USA oft an einem anderen Tag eine andere Strecke, und bei einer Zwischenlandung erst die eine, dann die andere Teilstrecke. `flight_route()` nimmt deshalb nur eine Strecke, die zu Position und Bewegung passt: zuerst die VRS-Standdaten (`vrs_route()`), weil sie stündlich gepflegt werden, dann adsbdb. Passt keine, bleibt die Airline für Name und Logo, Abflug und Ziel fallen weg, und das Panel zeigt Position und Messwerte statt einer falschen Ankunftszeit. Eine Teilstrecke passt nicht, wenn das Flugzeug

| Regel | Grenze |
| --- | --- |
| hinter dem Abflugort ist | mehr als 40 Seemeilen (`FLIGHT_LEG_BEHIND`) |
| hinter dem Ziel ist | mehr als 60 Seemeilen (`FLIGHT_LEG_BEYOND`), Gegenanflug und Warteschleife liegen darunter |
| neben der Strecke ist | mehr als 80 Seemeilen oder ein Fünftel der Streckenlänge (`FLIGHT_LEG_SIDE`) |
| einen Umweg bedeutete | Abflugort–Flugzeug–Ziel mehr als 60 Seemeilen oder ein Viertel länger (`FLIGHT_LEG_DETOUR`) |
| unter 10 000 Fuß steigt oder sinkt | weiter als 100 Seemeilen vom Abflugort beziehungsweise Ziel (`FLIGHT_LEG_NEAR`) |
| bis 25 000 Fuß mit mindestens 500 Fuß pro Minute steigt oder sinkt | weiter als 250 Seemeilen (`FLIGHT_LEG_NEAR_HIGH`) |
| nicht aufs Ziel zu fliegt | unterwegs mehr als 90 Grad daneben, beim Steigen und Sinken mehr als 110; nah an Start und Ziel zählt der Kurs nicht |

Von mehreren passenden Teilstrecken gewinnt die mit dem kleinsten Umweg und dem geradesten Kurs. Nah an einem Platz (40 Seemeilen) zählt dazu, ob das Flugzeug auf ihn zu oder von ihm weg fliegt: bei einer Zwischenlandung entscheidet das, solange es waagerecht fliegt. Geeicht am 24. September 2026 an 333 Flugzeugen über New York, Chicago, Los Angeles, Atlanta, Luxemburg, Frankfurt, London, Amsterdam und Madrid: aus den VRS-Standdaten passten 268 von 286 Strecken, aus adsbdb 162 von 294, und jede verworfene war nachweislich eine andere als die geflogene (etwa UAL464 Los Angeles nach Seattle über New Jersey). Der Anlass: über New York setzte JBU168 in JFK auf, und das Panel zeigte "ARRIVING IN ~1H", weil adsbdb das Rufzeichen noch als Charleston nach Boston führte.

Gezeigt wird das nächstgelegene Flugzeug. Nach jeder Standzeit (`dwell`) schaut der Server neu: ist ein anderes näher, wechselt die Anzeige, sonst bleibt der Flug stehen. Mit `hold` (Vorgabe an) geht ein Start oder eine Landung vor: steigt oder sinkt ein Flugzeug unter 10 000 Fuß mit mindestens 300 Fuß pro Minute, bleibt das Panel bei ihm, bis es darüber ist oder den Umkreis verlässt. In den letzten 30 Sekunden vor dem Aufsetzen und den ersten 30 nach dem Abheben zeigt es nur noch die Karte, ohne Wechsel: beim Zusehen sollen keine Textansichten dazwischenfunken. Gemessen wird nicht in Kilometern, sondern in Zeit bis zum Flugplatz (Entfernung durch Geschwindigkeit über Grund, `FLIGHT_HOLD_S` 30 s), dazu höchstens 3 500 Fuß (`FLIGHT_HOLD_FT`): 30 Sekunden sind bei einer Cessna gut zwei und bei einer 747 gut vier Kilometer. Der Flugplatz kommt aus der Strecke (beim Steigen der Start, beim Sinken das Ziel) und sonst aus der Weltliste in `geo-airports.json`, also überall, nicht nur in Luxemburg. Das gilt nur, wenn die Karte gewählt ist und das Gerät sie zeichnen kann. Unabhängig davon wechselt die Ansicht alle `view` Sekunden reihum durch die gewählten Ansichten aus `views` (route, progress, position, metrics), ein neuer Flug beginnt mit der ersten; ohne bekannte Strecke gibt es position und metrics, und wer nur Ansichten wählt, die dieser Flug nicht hat, bekommt lieber alle als keine. Ein angehefteter Flug (`pin`, Flugnummer, Rufzeichen oder Kennzeichen, weltweit) zeigt dieselben Ansichten, gezählt ab einer vollen Runde der Uhr. Damit zwei Abrufe kurz nacheinander nicht verschiedene Flüge zeigen, merkt sich der Server den Plan in der Tabelle `cache` (`flightplan:<gerät>:<einstellungen>`, zwei Minuten, für die Vorschau getrennt): ein laufender Abschnitt bleibt bis zu seinem Ende, spätere entscheidet jeder Abruf mit frischen Daten neu, ein verschwundenes Flugzeug sofort.

- `flight_build(array $ctx, float $from, float $to): array` Seiten aus dem Plan, gleiche Ansicht über eine Abschnittsgrenze bleibt eine Seite
- `flight_remember_now(array $ctx, array $ac, ?array $route, float $a, float $b): void` der Flug, der gerade auf dem Panel steht, 30 Sekunden im Zwischenspeicher (`flightnow:<Gerät>`), nur aus dem Abruf des Geräts. Home Assistant liest ihn dort und fragt adsb.lol nie selbst
- `flight_timeline(array $ctx, array $list, float $from, float $to): array` Abschnitte `{hex, since, from, to}`, je eine Standzeit lang
- `flight_plan_key(array $ctx): string`
- `flight_filter(array $list, array $f): array` am Boden raus, mit Höhenfilter alles über 30 000 Fuß raus
- `flight_view_pages(array &$pages, array $ctx, array $ac, ?array $route, float $a, float $b, float $since, string $idPrefix, bool $swap = false): void` Seiten eines Flugs, Ansichten ab `$since` gezählt; mit `$swap` bekommt die erste Seite eines neuen Flugs `fx` `swap`
- `flight_views(?array $route, array $chosen = [], bool $karte = false): array` `route, progress, position, metrics`, ohne Strecke `position, metrics`, mit `$karte` dazu `map`, gefiltert auf die gewählten
- `flight_views_now(array $ctx, array $ac, ?array $route): array` die Ansichten in diesem Moment: in den letzten 30 Sekunden vor dem Aufsetzen und den ersten 30 nach dem Abheben nur die Karte
- `flight_map_ready(array $ctx): bool` kann das Gerät die Karte zeichnen (ab `FLIGHT_MAP_FW` 0.1.9), die Vorschau immer
- `flight_event(array $ac): bool` startet oder landet gerade: unter 10 000 Fuß und mindestens 300 Fuß pro Minute, nicht am Boden
- `flight_pick(array $list, bool $hold): string` welcher Flug drankommt: mit `hold` zuerst ein Start oder eine Landung, sonst der nächstgelegene
- `flight_airline(?array $route, string $callsign, array $ac = []): string` Zusätze wie `S.A.` fallen samt Punkt weg, `flight_airline_icao(?array $route, string $callsign, array $ac = []): string` (zuerst `FLIGHT_CALLSIGN_OWNERS`, Anfang des Rufzeichens mit Kürzel und Land der Zulassung: die Hubschrauber der Luxembourg Air Rescue fliegen als `AIRESC1` bis `AIRESC3` und bekommen das Logo `LRQ`, nicht `AIR` von Airlift International; der Hubschrauber der Polizei, LX-FAA, fliegt als `POLICE1` und bekommt `PL` mit dem Namen POLICE, aber nur mit Luxemburger Zulassung), `flight_registered_in(array $ac, string $country): bool` (nach dem Kennzeichen, ohne eins nach dem Block der Kennung aus `FLIGHT_HEX_BLOCKS`, Luxemburg `4D0000` bis `4D03FF`), `flight_route_text(?array $route): string`
- `flight_route(array $ac): ?array` die Strecke, die dieses Flugzeug gerade fliegt, aus `vrs_route()` und `adsbdb_route()`; `null` ohne Rufzeichen oder ohne Eintrag in beiden Quellen
- `flight_route_pick(array $ac, ?array $db, ?array $vrs): ?array` wählt ohne Netz, dieselbe Form wie `adsbdb_route()` und dazu `source` (`vrs`, `adsbdb` oder leer); passt keine Strecke, sind `origin` und `destination` `null`, Airline und ICAO-Kürzel bleiben
- `flight_route_leg(array $ac, array $airports): ?array` die passende Teilstrecke `[Abflugort, Ziel]` aus einer Liste von Flughäfen in Flugreihenfolge, auch mit Zwischenlandung
- `flight_leg_score(array $ac, float $aLat, float $aLon, float $bLat, float $bLon): ?float` die Regeln aus der Tabelle oben, kleiner passt besser, `null` gar nicht
- `flight_angle(float $a, float $b): float` Winkel zwischen zwei Kursen, 0 bis 180 Grad
- `flight_progress(array $ac, ?array $route): array` `pct` Anteil der Strecke, `left` Sekunden bis zur Ankunft, `ago` Sekunden seit dem Abflug (0 unter 3 Seemeilen)
- `flight_span(int $secs): string` `~8 MIN`, `~45 MIN`, `~1H 20M`
- `flight_departed_text(?int $ago, bool $de): string`, `flight_arriving_text(?int $left, bool $de): string`
- `flight_place_text(array $geo): string` Ort und Bundesstaat oder Land, ohne Region, wenn es nicht in 21 Zeichen passt
- `flight_alt_text(array $ac, string $unit = 'ft'): string` `7.0KFT`, ab 10 000 Fuß ganze Zahlen, `GND` am Boden; in Metern auf 10 gerundet (`2130M`)
- `flight_speed_text(?float $kt, string $unit = 'kmh'): string` `485KMH`, `262KT`, `301MPH`
- `flight_vrate_text(?int $fpm, string $unit = 'ms'): string` Meter pro Sekunde mit Vorzeichen (`+3.3M/S`) oder Fuß pro Minute auf 10 gerundet (`+640FPM`)
- `flight_dist_text(float $nm, string $unit = 'nm'): string` `12.3NM`, `22.8KM`, `14.2MI`, ab 100 ohne Nachkommastelle
- `flight_bar_ops(?int $pct): array` grüner Balken und Punkte
- `flight_ops(array $ac, ?array $route, string $view, array $ctx): array` der Ort für `position` kommt aus `geocode_reverse()`, oder aus `$ctx['geo']`, das nur die Startseite setzt
- `flight_pinned_pages(array $ctx, float $from, float $to): array` Einzelflug, ohne Treffer `NOT SEEN YET` mit Uhr
- `flight_empty_pages(array $ctx, float $from, float $to): array` nichts in der Luft: `clock` Uhr, `wait` Warte-Animation, `off` Ruhezustand. Ein Ausfall von adsb.lol sieht genauso aus; er steht nur in den Ereignissen, seit dem 19. September 2026 nicht mehr als roter Punkt auf dem Panel (CHANGELOG 44)

Nutzt: `services/adsb.php`, `services/aircraft.php`, `services/geocode.php`, `modes/clock.php` (`clock_ops()` für leere Minuten). Geprüft von `tools/flight-fixtures.php`.

### modes/flight_map.php (11)

Die Kartenansicht, fünfte Ansicht des Flugmodus: oben 51 Zeilen Karte als `bmp`, darunter eine Trennlinie und eine Textzeile, das Flugzeug als `dot`. Nah (`FLIGHT_MAP_NEAR_KM` 40 km, Stufen `FLIGHT_MAP_STEPS` 16 bis 256 km) bei Start und Landung, sonst die ganze Strecke auf dem Großkreis.

- `flight_track(string $hex, ?array $ac = null, ?int $now = null): array` Spur der letzten 15 Minuten im Cache, höchstens 60 Punkte
- `flight_airport_for(array $ac, ?array $route): ?array` Start oder Ziel aus der Strecke, sonst der nächste Platz; `flight_airport(float $lat, float $lon): ?array` der nächste aus `geo-airports.json`
- `flight_airport_seconds(array $ac, array $airport): float`, `flight_at_airport(array $ac, ?array $airport): bool` die letzten 30 Sekunden vor dem Aufsetzen und die ersten 30 nach dem Abheben
- `flight_map_kind(array $ac, ?array $route, ?array $airport): string` `nah` oder `strecke`
- `flight_map_near_view(array $ac, ?array $airport, array $track): array`, `flight_map_route_view(array $ac, array $route): array` Ausschnitte, `flight_gc_point(array $a, array $b, float $f): array`
- `flight_map_ops(array $ctx, array $ac, ?array $route): array` die Zeichenbefehle
- `flight_map_footer(string $links, array $wahl): array` die Textzeile: links das Rufzeichen, rechts Höhe und Steigrate (nah) oder Restzeit (Strecke), mindestens 5 LEDs dazwischen. Das Rufzeichen wird nie gekürzt, aus KLM1234 würde sonst KLM123, eine andere Flugnummer; passt es nicht, fällt rechts erst die Lücke weg (`3.5KFT+5.1M/S`, nur mit Vorzeichen), dann die Steigrate

Nutzt: `services/geomap.php`, `services/adsb.php`. Geprüft von `tools/flight-fixtures.php` (Ausschnitt, Halten, Fußzeile).

### modes/spotify.php (28)

Entwurf und Entscheidungen vom 24. September 2026 in `design/spotify/ENTWURF.md`. Gezeigt wird immer das Spotify-Konto des Besitzers eines Geräts.

Raster je Layout in `SPOTIFY_LAYOUTS`: Klassisch und Farbe aus dem Cover mit Cover 48 × 48 bei 2, 2, Text ab x 54 mit 12 Zeichen in den Zeilen 2, 14, 26, 38; Groß mit Cover 64 × 64 bei 0, 0, Text ab x 68 mit 10 Zeichen; Platte mit Cover 44 × 44 bei 2, 3, der Platte dahinter (Mitte 40, 25, Radius 21) und Text ab x 66. Die Ecke eines laufenden Timers sitzt hier unten rechts in der Zeitzeile (`corner` in `mode_register()`), weil oben rechts Titel und Ansage stehen und aufs Cover nichts kommt. Titel weiß, als Laufschrift, wenn er nicht passt (links davon löscht ein Rechteck in `000000` die Zeile, das Cover kommt zuletzt darüber); Künstler in Bernstein, bei "Farbe aus dem Cover" in der Leitfarbe; Album grau auf zwei Zeilen. Eine Single zeigt SINGLE und das Jahr, ein Titelsong (Album heißt wie der Titel) TITELSONG und das Jahr. Zeitleiste über die volle Breite (Groß: ab x 68) mit `prog`.

Ablauf eines Titels: die Ansichten reihum ab Beginn des Titels, "Läuft" im Wechsel mit Album, Als Nächstes und Nur Cover, je `view` Sekunden; die letzten zehn Sekunden die Ansage (Kopf DANACH mit Countdown, darunter der nächste Titel genau so, wie er gleich dasteht, eine Zeile tiefer, `fx: lines`), am Ende der nächste Titel mit `fx: reel`. Die Ansage gibt es nur, wenn die Warteschlange einen nächsten Titel kennt und der laufende länger als 25 Sekunden ist. Pause: eine Seite, Balken und Kopf grau, PAUSE. Nach zehn Minuten Pause (`SPOTIFY_PAUSE_IDLE_MS`) und ohne Musik zeigt der Modus die Uhr und fällt aus der Rotation.

Titelwechsel am Handy: der Server merkt sich je Gerät, welche Seiten er geplant hat (`spot:<konto>:plan:<gerät>`, zwei Minuten). Steht laut Plan gerade ein anderer Titel auf dem Panel, bekommt die erste Seite einen Übergang: die Walze, wenn der neue Titel ohnehin als Nächster kam und höchstens drei Sekunden vor dem geplanten Ende beginnt (Überblenden, Lücken), sonst das Karussell, auch beim Sprung auf den nächsten Titel der Warteschlange. Entschieden wird nach dem Stand, den das Panel hat, wenn die Antwort ankommt (Ende des Rechnens plus `SPOTIFY_ARRIVE_MS`, 300 ms), nicht nach dem vom Beginn: kurz nach einem Titelwechsel rechnet ein Frame bis zu 1,3 s, und das Gerät hatte den Wechsel nach dem alten Plan oft schon gezeigt und bekam die Walze ein zweites Mal. Gehört die Warteschlange schon zu einem anderen Titel als "Läuft", ist "Läuft" veraltet und wird einmal neu geholt (`spotify_queue()` gibt dann `null`).

Firmware vor 0.2.0 bekommt keine `prog`, `count` und `disc`: der Balken steht als Rechtecke, rechts das Ende, neu gezeichnet mit jedem Abruf.

- `spotify_fw_ok(array $ctx): bool`, `spotify_active(array $ctx): bool`, `spotify_build(array $ctx, float $from, float $to): array` (Hinweise ohne Einrichtung, Verbindung oder Freischaltung, die Uhr ohne Musik, sonst der Plan)
- Plan: `spotify_pages(array $ctx, array $v, array $queue, ?array $album, callable $cover, float $from, float $to, ?array $shown = null): array` ohne Netz, `spotify_change_fx(array $pages, ?array $shown, array $v, array $L): array` (Walze oder Karussell nach einem Titelwechsel), `spotify_plan_shown(mixed $plan, int $nowMs): ?array`, `spotify_plan_of(array $pages, array $ends = []): array`, `spotify_cycle(array $extras, array $item, ?array $album, array $queue): array`, `spotify_song_segs(array &$segs, array $item, int $start, int $end, ?int $teaser, array $cycle, int $viewMs, int $fromMs, int $toMs): void`, `spotify_fx(string $fx, array $L, bool $shift): array`
- Text: `spotify_fold(string $s): string`, `spotify_clean_title(string $s): string` (ohne "- 2011 Remaster" und "(Radio Edit)"), `spotify_cut(string $s, int $n): string`, `spotify_artists(array $list, int $n): string` ("DAFT PUNK +2"), `spotify_wrap(string $s, int $n, int $max): array`, `spotify_mmss(int $sec): string`, `spotify_tr(string $lang, string $en, string $de): string`
- Ansichten: `spotify_view_ops(string $view, array $L, array $st, array $opt, int $atMs): array`, `spotify_cover_op(callable $cover, array $item, int $x, int $y, int $size): array` (ohne Bild ein Rahmen), `spotify_ticker(int $x, int $y, string $s, string $c): array`, `spotify_song_text(array $L, array $item, array $ys, string $lead, string $lang, bool $three = false): array`, `spotify_now_ops(array $L, array $st, array $opt, int $atMs, ?array $next): array`, `spotify_time_ops(array $T, array $st, array $opt, int $atMs, string $bar, bool $soon): array`, `spotify_album_ops(array $st, array $opt, int $atMs): array` (Cover 64, Name bis drei Zeilen, Titelnummer, Jahr, je Titel ein Stück Balken so breit, wie er lang ist), `spotify_queue_ops(array $st, array $opt, int $atMs): array` (JETZT und drei folgende mit Minuten bis dahin, Cover 10 × 10), `spotify_cover_ops(array $st, array $opt, int $atMs): array` (Cover 64 bei x 32, links Zeit und kurzer Balken, rechts der Rest), `spotify_note_ops(string $lang, string $kind): array`
- Beispiel für die Layout-Kacheln der Geräteseite: `spotify_demo(): array`, `spotify_demo_image(): string` (erfundener Titel, gerechnetes Motiv, kein echtes Cover)

Nutzt: `services/spotify.php`, `modes/clock.php` (`clock_ops()` ohne Musik). Geprüft von `tools/spotify-fixtures.php`.

### modes/clock.php (1)

- `clock_ops(array $ctx): array` Größe sinkt, bis die Zeit in 126 Spalten passt (`clock_width()`); bei 12 Stunden stehen AM und PM klein daneben, so bleibt "09:14:33 PM" in der fetten Schrift bei Faktor 2 statt auf Faktor 1 zu fallen; Wetterleiste aus `weather_get()`, oder aus `$ctx['wx']`, das nur die Startseite setzt

Extern genutzt von: `modes/flight.php`

### modes/weather.php (3)

Einheit hinter der Zahl statt an fester Stelle, dreistellige Werte schrumpfen, Wind passt in die 58 Spalten ab x 70 (FEHLERLISTE 4.1 und 4.2). Der Ortsname oben hat 20 Zeichen ab x 4 (mit 21 fehlte die letzte Spalte), mit laufendem Timer 15, damit er vor dessen Ecke endet, die Windzeile der Ansicht "jetzt" steht bei y 56, weil die Lippe des Standfußes die unterste LED-Zeile halb verdeckt.

- `weather_ops(array $ctx): array` Daten aus `weather_get()`, oder aus `$ctx['wx']`, das nur die Startseite setzt (`welcome_demo_pages()`)
- `weather_wind_text(array $wx, string $unit, string $lang, bool $wide): string`
- `weather_day_name(string $date, string $lang): string`

### modes/transit.php (18)

Nachbau des Entwurfs `Transit.dc.html`. Höchstens drei Haltestellen je Gerät, und über alle Geräte zusammen nicht mehr als drei verschiedene: jede kostet eine Abfrage pro Minute, 1 440 am Tag, bei mehr greift die Bremse bei 4 500 am Tag, und die Tafeln zeigen für den Rest des Tages den alten Stand. Der Admin-Bereich zählt die verschiedenen Haltestellen aller Geräte. Konstanten: `TRANSIT_STOPS_MAX` 3, `TRANSIT_SHORT_MAX` 18, `TRANSIT_CHAR` 6, `TRANSIT_RIGHT` 126, `TRANSIT_DIM_CODE` `7A5200` und `TRANSIT_DIM_TEXT` `4E5A63` (gedimmte LED, Ausnahme Panel-Simulation in `CLAUDE.md`), unter Helligkeit 96 `TRANSIT_DIM_CODE_NIGHT` `B37800` und `TRANSIT_DIM_TEXT_NIGHT` `8E9AA3`, `TRANSIT_TROUBLE_BAR` `3A1008`.

Kopf: Kurzname der ersten Haltestelle bei (2,1) in Grau, höchstens 18 Zeichen und immer vor dem ersten Quadrat; rechts je gezeigtem Verkehrsmittel ein Quadrat 5×5 bei y 2, von rechts ab x 122 im Abstand 7 (Zug Cyan, Tram Grün, Bus Bernstein); Linie bei y 9. Läuft ein Timer, steht rechts seine Ecke statt der Quadrate, und der Name endet vor x 96. Zeilen bei y 12, 24, 36, 48 (vier), 13, 27, 41 (drei) oder 12 bis 52 im Abstand 10 (fünf).

Eine Zeile wird von rechts gesetzt: die Zeit rechtsbündig an Spalte 126 (Minuten bis 99, `NOW` oder `JETZT`, oder die Uhrzeit HH:MM; weiß, bernstein bei Verspätung, grau über 60 Minuten; bei einem Ausfall keine), 3 Pixel Luft; dann ein Zeichen (`X` rot Ausfall, `!` bernstein Teilausfall, `+` grün Zusatzfahrt, `E` cyan Ersatzverkehr), 7 Pixel; dann bei Zügen das Gleis, nur ohne Zeichen und nur, wenn mobiliteit.lu es nicht versteckt (drei Zeichen, gemischt zwei; grau, bernstein bei Gleiswechsel), 3 Pixel. Links bei x 2 die Linie (vier Zeichen) oder bei Zügen die Gattung (drei). Das Ziel beginnt bei x 28 oder 22 und bekommt so viele Zeichen, wie bis zur Kante frei sind: bei Bus und Tram die Haltestelle ohne Ort und ohne "(Bus)", bei Zügen der Ort. Rot bei einem Ausfall.

Mehrere Haltestellen laufen reihum, je Runde die früheste zuerst: nur nach Zeit gäbe es am Bahnhof vier Busse und nie den Zug. Bei drei und vier Zeilen unten ein Streifen: Linie bei y 55, Text bei y 56 (die Lippe des Standfußes verdeckt die unterste LED-Zeile halb). Uhr (Befehl `clock`, Zeitformat der Uhr) und `MOBILITEIT` rechtsbündig bis 125 in `4E5A63`, oder eine Meldung als `ticker` (14 Pixel pro Sekunde). Die Meldung gehört zu einer Zeile mit Zeichen, zuerst Ausfall, dann Teilausfall, Zusatzfahrt, Ersatzverkehr, je Zeile die Echtzeit-Meldung vor dem Hinweis; eine Zeile ohne Zeichen bekommt keine Laufschrift.

In der Rotation fällt die Tafel ein (`enter` `drop`): jede Seite trägt `bands` mit den oberen Kanten der Zeilen.

Die Seiten wechseln an jeder vollen Minute. Leere Tafel: `NO DEPARTURES` bei y 24, darunter `NEXT 05:12`, wenn mobiliteit.lu für die nächsten zwölf Stunden eine Abfahrt kennt. Keine frischen Daten: die ersten zwei Zeilen des letzten guten Stands gedimmt bei y 14 und 26, `NO DATA` rot bei y 42, `AS OF 13:46` bei y 52, ein dunkelroter Balken bei y 61. Ohne Schlüssel `NO KEY`, ohne Haltestelle `PICK A STOP`.

- `transit_sanitize(array $in, array $cur): array` bis zu drei Haltestellen mit gültiger ID, Name höchstens 80 Zeichen, Kurzname 18 (so viel zeigt der Kopf, mit drei Quadraten 17), Linien als Kürzel; Verkehrsmittel in fester Reihenfolge; `rows` 3 bis 5, `walk` 0 bis 15, `fmt` `min` oder `clock`
- `transit_build(array $ctx, float $from, float $to): array` Tafeln einmal holen, Seiten an jeder Minute teilen
- `transit_collect(array $ctx): array` `transit_board()` je Haltestelle
- `transit_ops(array $ctx, array $boards, int $now): array` alle Zustände
- `transit_set(int $stopCount, array $ticks): string` `train`, `mixed`, `bus` oder `tram`
- `transit_mode_colour(string $mode): string`
- `transit_header(array &$ops, string $name, array $ticks): void`
- `transit_row_ys(int $rows): array`
- `transit_row(array &$ops, int $y, array $dep, string $set, array $ctx, bool $dim): void`
- `transit_dim(array $ctx, string $what): string` gedimmter Ton für `code` oder `text`, nachts heller (gewählt in `transit_ops()`)
- `transit_marker(array $dep): ?array`
- `transit_row_note(array $rows): ?string` Meldung der wichtigsten Zeile mit Zeichen
- `transit_strip(array &$ops, array $ctx, ?string $note): void`
- `transit_trouble(array &$ops, array $ctx, array $rows, string $set, string $message, ?int $asOf): void`
- `transit_visible(array $departures, array $ctx, int $now): array` Verkehrsmittel, ausgeblendete Linien, Schulbusse (Buchstabe und zwei Ziffern), Fußweg; Minuten ab der laufenden Minute, nach Echtzeit sortiert
- `transit_pick_rows(array $queues, int $limit): array`
- `transit_panel_dep(array $d, string $tz): ?array` Kürzel, Ziel, Zeit, Verspätung, Gleis und Gleiswechsel, Zeichen, Meldung
- `transit_ts(string $iso): ?int`

Nutzt: `services/transit.php`, `device/frame.php`, `core/text.php`. Geprüft von `tools/transit-mode-fixtures.php`.

### modes/notes.php, modes/pixel.php

Keine eigenen Funktionen, alles in der Registrierung.
