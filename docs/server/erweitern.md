# Erweitern: neue Funktionen bauen

Diese Anleitung ist für alle, die THE WALL erweitern wollen, und für ihre KI-Assistenten. Sie beschreibt Schritt für Schritt, wie eine neue Funktion entsteht, welche Dateien sie berührt und woran man merkt, dass sie fertig ist. Die Regeln für Gestaltung, Texte und Barrierefreiheit stehen in [`CLAUDE.md`](../../CLAUDE.md), der Weg eines Beitrags in [`CONTRIBUTING.md`](../../CONTRIBUTING.md). Beides zuerst lesen.

Grundsatz: Code, Doku und Prüfungen ändern sich im selben Pull Request. Wer eine Funktion umbenennt, sucht den alten Namen in allen `README.md` und `docs/server/*.md` und korrigiert ihn dort.

## Wie ein Bild aufs Panel kommt

1. Das Gerät fragt alle 10 Sekunden `/api/v1/frame` und alle 2 Sekunden `/api/v1/rev`. Der Server schiebt nie etwas.
2. `frame_build()` in `web/.htapp/device/frame.php` wählt die Modi (einer, die Rotation, eine neue Notiz vorn, Spotify zuerst) und lässt jeden Modus Seiten für die nächsten 25 Sekunden bauen.
3. Eine Seite ist ein Zeitfenster (`from`, `to` in Millisekunden seit 1970) mit Zeichenbefehlen (`ops`), etwa `{"t":"text","x":38,"y":2,"s":"LUXAIR","c":"FFAA00"}`.
4. Die Firmware kennt 14 Zeichenbefehle und sonst nichts: `text`, `ticker`, `rect`, `bar`, `frame`, `logo`, `clock`, `date`, `anim`, `bmp`, `dot`, `prog`, `count`, `disc`. Die Vorschau im Browser zeichnet dieselben mit `web/assets/js/lib/ops.js`. Felder und Versionen stehen in [geraet.md](geraet.md).

Daraus folgt die wichtigste Unterscheidung: **Ein neuer Modus ist reiner Servercode** und kommt ohne neue Firmware aufs Panel. **Ein neuer Zeichenbefehl** braucht Firmware, `ops.js`, Server und eine neue Firmware-Version. Die meisten Ideen lassen sich mit den 14 vorhandenen bauen.

## Rezept 1: ein neuer Modus

### Datei und Anmeldung

Ein Modus ist eine Datei `web/.htapp/modes/<id>.php`. `bootstrap.php` lädt jede Datei in diesem Ordner von selbst, alphabetisch. Als Vorlage dient `modes/weather.php` (knapp 100 Zeilen): Anmeldung, eine Funktion für die Zeichenbefehle, Einstellungen mit Prüfung, eine Seite über das ganze Fenster, Platz für die Timer-Ecke, ein Zustand ohne Daten.

```php
<?php
declare(strict_types=1);

mode_register('example', [
    'order' => 45,
    'label' => ['en' => 'Example', 'de' => 'Beispiel'],
    'rotatable' => true,
    'defaults' => ['view' => 'now', 'big' => false, 'speed' => 5],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (isset($in['view']) && in_array($in['view'], ['now', 'list'], true)) {
            $out['view'] = $in['view'];
        }
        if (array_key_exists('big', $in)) {
            $out['big'] = (bool) $in['big'];
        }
        if (array_key_exists('speed', $in)) {
            $out['speed'] = clamp_int($in['speed'], 1, 10, (int) $cur['speed']);
        }
        return $out;
    },
    'build' => static fn(array $ctx, float $from, float $to): array
        => [frame_page($from, $to, example_ops($ctx), ['id' => 'example'])],
]);

function example_ops(array $ctx): array
{
    $s = $ctx['settings'];
    $de = $ctx['lang'] === 'de';
    $ops = [op_text(4, 3, panel_text((string) $s['location']['place'], true, empty($ctx['corner']) ? 20 : 15), C_DIM)];
    $data = $ctx['example_data'] ?? example_fetch((float) $s['location']['lat'], (float) $s['location']['lon']);
    if (!$data) {
        $ops[] = op_text('c', 30, $de ? 'KEINE DATEN' : 'NO DATA', C_DIM);
        return $ops;
    }
    $ops[] = op_text(4, 18, panel_text((string) $data['title']), C_ACCENT, $s['example']['big'] ? 2 : 1);
    return $ops;
}
```

Die Schlüssel von `mode_register()` (`device/modes.php`):

| Schlüssel | Bedeutung |
| --- | --- |
| `order` | Reihenfolge in Listen und in der Rotation. Vergeben: Flug 10, Uhr 20, Wetter 30, Nahverkehr 35, Spotify 38, Notizen 40, Pixel 50 |
| `label` | Name auf Englisch und Deutsch, in Webseite, Geräteumschalter und Home Assistant |
| `defaults` | Einstellungen des Modus, landen als `$settings['<id>']` bei jedem Gerät. Neue Schlüssel erreichen bestehende Geräte ohne Migration |
| `sanitize` | `fn(array $in, array $cur): array`. Bekommt beim Übernehmen nur die geänderten Felder, deshalb mit `$out = $cur` beginnen, jeden Schlüssel einzeln prüfen und schlechte Werte still verwerfen. Fehler melden kann sie nicht |
| `build` | `fn(array $ctx, float $from, float $to): array`, Sekunden hinein, Seiten heraus. Ohne `build` bleibt das Panel leer |
| `rotatable` | darf in die Rotation |
| `available` | `false` sperrt den Modus überall, für Entwürfe |
| `active` | `fn(array $ctx): bool`, optional: ohne Inhalt fällt der Modus aus der Rotation (Spotify ohne Musik). Läuft bei jedem Abruf, muss billig sein |
| `enter` | Einstieg in der Rotation, `push` (Standard) oder `drop` (braucht `bands`) |
| `corner` | `fn(array $ctx): array`, optional: legt die Timer-Ecke woanders hin (Spotify: unten rechts) |

Die ID des Modus: Kleinbuchstaben ohne Punkt, nicht `ring`, `pairing`, `off`, `hello` und kein Name aus den Grundeinstellungen (`mode`, `rotation`, `bright`, `location` und so weiter, siehe `device_defaults()`). Alle Funktionen und Konstanten tragen die ID als Vorsilbe, PHP hat hier einen gemeinsamen Namensraum.

### Was `build` bekommt und liefert

`$ctx` enthält:

| Schlüssel | Inhalt |
| --- | --- |
| `settings` | alle Einstellungen des Geräts, in der Vorschau der Entwurf |
| `device` | die Zeile aus `devices` (`id`, `uid`, `name`, `fw`, ...), `fw` kann leer sein |
| `owner` | das Konto des Besitzers |
| `lang` | `en` oder `de`, für Text auf dem Panel |
| `preview` | `true` in der Vorschau der Webseite |
| `now` | Zeitpunkt des Abrufs in Sekunden |
| `corner` | leer, oder der laufende Timer. Dann Platz lassen, siehe unten |

Seiten entstehen mit `frame_page(float $from, float $to, array $ops, array $extra = [])`. In `$extra` gehört mindestens eine `id`. `mode` und `enter` setzt der Server selbst.

Regeln für die Zeitfenster:

- Die Seiten decken `[$from, $to]` lückenlos ab, die erste beginnt genau bei `$from`. `build` bekommt oft nur einen Teil der 25 Sekunden: in der Rotation Plätze von 10 Sekunden bis 10 Minuten (`cycle`, Vorgabe 30), vor einem Klingeln ein kürzeres Stück.
- Grenzen innerhalb des Fensters hängen an der Uhr, nicht am Abruf: volle Minuten, `floor($from / $n) * $n`. Sonst springt die Anzeige, wenn zwei Antworten aneinanderstoßen.
- Was sich jede Sekunde ändert, zählt das Gerät selbst (`clock`, `date`, `count`, `prog`, `ticker`). Niemals eine Seite je Sekunde schicken.
- Eine Ausnahme in `build` wird protokolliert, und das Panel zeigt eine rote Fehlerseite. Warnungen gelten als Fehler.

Hilfsfunktionen für Zeichenbefehle in `device/frame.php`: `op_text()` (x `'c'` zentriert), `op_ticker()`, `op_rect()`, `op_bar()`, `op_frame()`, `op_logo()`, `op_clock()`, `op_date()`, `op_anim()`. Für `bmp`, `dot`, `prog`, `count` und `disc` gibt es keine, sie entstehen als Array wie in `modes/spotify.php`. Ihr Einsatz braucht eine Firmware-Prüfung, siehe Rezept 3.

Text auf dem Panel:

- Immer durch `panel_text(string $s, bool $upper = true, int $max = 21)`. Sie schreibt Umlaute um (ö wird o, ß wird ss), lässt nur druckbares ASCII durch und kürzt. Feste deutsche Wörter im Code stehen gleich umgeschrieben da: `'FLIEGT UEBER'`.
- Schrift 5 × 7, jedes Zeichen 6 Pixel breit, Zeilenabstand 12. Eine volle Zeile ab x 2 fasst 21 Zeichen. Die unterste Textzeile beginnt bei y 56, darunter verdeckt der Standfuß eine halbe Zeile.
- `panel_width()` und `panel_center_x()` rechnen Breiten.

Farben als Konstanten aus `device/frame.php`: `C_ACCENT` (Bernstein), `C_WHITE`, `C_DIM`, `C_CYAN` (Technik), `C_GREEN` (Status), `C_RED` (Fehler). `000000` schaltet LEDs aus und dient zum Löschen. Nie mehr als drei Blitze pro Sekunde.

### Daten von außen

Das Gerät holt nie selbst Daten, der Server tut es. Eine neue Quelle bekommt eine Datei `services/<name>.php` und geht immer durch den Zwischenspeicher: `cache_remember(string $key, string $service, callable $fn, int $failTtl = 30)` mit `http_get_json()` (Muster: `services/weather.php`). `build` läuft bei jedem Abruf jedes Geräts, alle 10 Sekunden. Braucht ein Dienst einen Schlüssel, liegt er im Admin-Bereich verschlüsselt in den Einstellungen (Muster: Nahverkehr, Spotify), nie im Code und nie in der Firmware.

Für Prüfungen ohne Netz: `build` nimmt die Daten aus `$ctx`, wenn sie dort stehen (`$ctx['example_data'] ?? example_fetch(...)`), so wie das Wetter `$ctx['wx']`.

### Timer-Ecke

Läuft ein Timer, steht `$ctx['corner']`, und oben rechts liegt ein Feld von 32 × 9 LEDs mit der Restzeit (x 96 bis 127, y 0 bis 8). Der Modus hat zwei Wege:

- **Platz machen:** Text, der dort hineinreicht, kürzer setzen, aber nur solange `!empty($ctx['corner'])`. Der Flugmodus kürzt Zeile 1 von 15 auf 9 Zeichen, das Wetter den Ort von 20 auf 15.
- **Die Ecke verlegen:** `'corner' => fn(array $ctx): array` gibt `box`, `right` und `y` zurück.

Ohne Timer sieht alles aus wie vorher. Geprüft wird das in `tools/timer-fixtures.php`.

### Einstellungen auf der Webseite

1. In `web/.htapp/views/device.php` einen Block `<div data-panel-for="<id>" ...>` im Abschnitt `settings` anlegen, am besten den Block des Wetters kopieren. Jedes Bedienelement trägt `data-set="<id>.<schlüssel>"` und bei Gästen ohne Schreibrecht `<?= $dis ?>`.
2. Werte: Schalter (`button.sw`, `role="switch"`) liefern `true` oder `false`, Knöpfe mit `data-value` einen Text, Regler eine Zahl, Auswahllisten und Textfelder Text. `sanitize` muss genau diese Typen annehmen. Checkboxen und `type=number` gibt es nicht.
3. Nur diese Bedienelemente aus der Bibliothek (`/controls`, `views/controls.php`): Textfeld A1, mit Zeichenzähler A5 für alles, was aufs Panel geht; Regler B2, schlank B1; Schalter C1, mit Erklärung C5; Auswahl als Kacheln mit Vorschau D4, Mehrfachauswahl D5; Farbe E5.
4. Englisch steht im Markup, Deutsch in `data-de` über die PHP-Hilfe `de()`. `de()` gehört an ein Element, das nur Text enthält.
5. In `web/assets/js/pages/device.js` den Modus in `TITLES` und `PREVIEW` eintragen, bei `rotatable` auch in `ROT`, und in `views/device.php` den Chip für die Rotation ergänzen.
6. Die Vorschau holt sich die Seiten über `/api/preview` und zeichnet sie mit `ops.js`, dafür ist nichts weiter nötig.

### Prüfen

Eine Datei `tools/<id>-fixtures.php` nach dem Muster von `tools/transit-mode-fixtures.php`: feste Uhrzeit, Daten über `$ctx`, eine `check()`-Zeile je Behauptung. Sie sollte prüfen:

- die Angaben aus `mode_get('<id>')`
- `sanitize` mit Unsinn: falsche Typen, unbekannte Werte, zu große Zahlen
- dass die Seiten `[$from, $to]` genau abdecken
- dass jeder Text passt (`fits()` aus `tools/flight-fixtures.php`)
- dass mit `$ctx['corner']` Platz frei bleibt
- dass `frame_build()` mit `'mode' => '<id>'` Seiten dieses Modus liefert

Starten mit `TW_ENV=dev php tools/<id>-fixtures.php` aus dem Hauptverzeichnis. Die Tabelle aller Prüfskripte steht in [betrieb.md](betrieb.md), dort die neue Datei eintragen.

### Doku

- [geraet.md](geraet.md): die Tabelle `modes/` und ein eigener Abschnitt `### modes/<id>.php` wie beim Wetter
- `README.md` und `README.de.md`: je ein Absatz unter "What the panel shows" bzw. "Was das Panel zeigt"
- [services.md](services.md), wenn eine neue Datenquelle dazukommt, und die Quelle unter "Credits"

Freiwillig, aber schön: der Name in `KNOWN_MODES` und in den Übersetzungen der Home-Assistant-Integration (`homeassistant/custom_components/thewall/const.py`, `translations/*.json`) und die Wörter für die Sprachsteuerung in `homeassistant/blueprints/thewall_voice_modes.yaml`.

## Rezept 2: eine neue Einstellung für einen Modus

1. Standardwert in `defaults` des Modus.
2. Prüfung in `sanitize`, mit `array_key_exists` und Rückfall auf den alten Wert.
3. Das Bedienelement in `views/device.php` wie oben, `data-set="<id>.<schlüssel>"`. Für Sekunden mit großer Spanne einen Regler mit Stufen über `$secRange(...)` mit einer Liste der Stufen als Konstante: er schickt Sekunden, `device.js` rechnet um und setzt `aria-valuetext`. Vorbilder sind der Takt der Rotation und "Ansicht wechseln alle".
4. Wirkung in der Funktion, die die Zeichenbefehle baut.
5. Eine `check()`-Zeile für den neuen Wert und für Unsinn.
6. Doku in [geraet.md](geraet.md) beim Modus.

Die Webseite schickt beim Übernehmen nur geänderte Felder, und bestehende Geräte bekommen den Standardwert von selbst.

## Rezept 3: ein neuer Zeichenbefehl, mit Firmware

Nur nötig, wenn die 14 vorhandenen nicht reichen. Ältere Firmware übergeht einen unbekannten Befehl still und lässt eine Lücke, deshalb bekommt sie etwas anderes.

1. **Server:** eine Hilfsfunktion `op_<name>()` in `device/frame.php` oder ein Array im Modus. Eine Konstante für die Mindestversion anlegen, wie `SPOTIFY_FW` oder `FLIGHT_MAP_FW`, und mit `frame_fw_at_least(array $ctx, string $version)` prüfen. Ältere Geräte bekommen einen Ersatz aus vorhandenen Befehlen. Die Vorschau bekommt immer den neuen.
2. **Firmware:** in `firmware/src/live.cpp` in `live::render()` einen Zweig `else if (!strcmp(t, "<name>"))` und eine Zeichenfunktion im anonymen Namensraum, wie `drawCount()`. Felder lesen wie der Rest: ganze Zahlen mit `op["x"] | 0`, Zeitstempel mit `num64()`, Kommazahlen mit `numD()`, Farben mit `parseColour(op["c"] | "", C_ACCENT)`. Zeit kommt aus `ctx.nowMs`, der mit dem Server abgeglichenen Uhr. Mit `ctx.reduce` steht alles im Endzustand. Die Zeichenfläche beschreibt `firmware/src/grid.h`.
3. **Vorschau:** in `web/assets/js/lib/ops.js` einen `case`, der genau dasselbe zeichnet, mit derselben Rundung. Nur Sprachmittel von JavaScript, kein DOM und kein `atob`: `tools/panel-png.mjs` führt die Datei in Node aus.
4. **Version:** `FW_VERSION` in `firmware/src/config.h` erhöhen. Jede Version lässt sich nur einmal auf einen Server laden.
5. **Doku:** die Tabelle der Zeichenbefehle in [geraet.md](geraet.md) mit "ab Firmware x.y.z", die Tabelle "Stand" in `firmware/README.md`, der Abschnitt `ops.js` in [frontend.md](frontend.md) und die Zahl der Befehle in `CLAUDE.md`.
6. **Prüfen:** eine Prüfung mit neuer und eine mit alter Firmware (Muster: `tools/timer-fixtures.php`). Bauen mit `pio run -e wall` im Ordner `firmware/`, das Ergebnis ist `.pio/build/wall/firmware.bin`. Wie es aussieht, zeigt `node tools/panel-png.mjs frame.json out.png`.

Animationen rechnet das Gerät aus der Zeit, gespeichert wird keine. Bewegung hält bei `motion: reduce` an, und nie mehr als drei Blitze pro Sekunde, auch nicht auf dem Panel.

Auf die Geräte kommt eine neue Firmware nur über den Server, den sie fragen: hochladen im Admin-Bereich, dann bietet der Server sie an ("Jetzt aktualisieren", Home Assistant oder automatisch). Für die Instanz thewall.godart.lu entscheidet das der Projektinhaber, nachdem die Version auf seinem Gerät gelaufen ist.

## Rezept 4: Übergänge

Übergänge stehen als `fx` auf der ersten Seite nach dem Wechsel. Der Server entscheidet, die Firmware (`drawLive()` in `firmware/src/main.cpp`) und die Vorschau (`web/assets/js/lib/anim.js`) zeichnen.

| fx | Dauer | Wofür |
| --- | --- | --- |
| `push` | 1,6 s | neuer Modus in der Rotation |
| `drop` | 1,1 s | Einstieg in die Abfahrtstafel, braucht `bands` |
| `swap` | 0,4 s | neuer Flug |
| (keins, gleiche `id`, mit `bands`) | 0,24 s | geänderte Spalten rollen, Abfahrtstafel jede Minute |
| `reel` | 0,56 s | Spotify, Titel läuft aus |
| `carousel` | 0,56 s | Spotify, am Handy weitergesprungen |
| `lines` | 0,4 s | Spotify, die Ansage rollt herein |

Ein neuer Übergang ist Firmware und `anim.js`, also Rezept 3.

## Rezept 5: Home Assistant

Die Integration liegt in `homeassistant/`, steht unter MIT und wird vom Projektinhaber zusätzlich nach [THE-WALL-HA](https://github.com/LennyGodart/THE-WALL-HA) gespiegelt. Home Assistant spricht nur mit dem Server (`web/.htapp/api/ha.php`), nie mit dem Gerät.

**Eine neue Entität**

1. Plattform-Datei in `custom_components/thewall/`, bei einer neuen Plattform auch in `PLATFORMS` in `__init__.py` und in `tests/test_translations.py`.
2. Beschreibung mit `value_fn` wie in `sensor.py`, Basisklasse aus `entity.py`.
3. Der Stand kommt von `GET /api/ha/v1/state`, gebaut von `ha_state()` in `api/ha.php`. Ein neues Feld dort ergänzen und in `tests/fixtures/state.json` eintragen.
4. Namen in `translations/en.json` und `de.json` unter `entity.<plattform>.<schlüssel>`, Symbol in `icons.json`. Die Prüfungen verlangen dieselben Schlüssel in beiden Sprachen, keine leeren Texte und keine Gedankenstriche.
5. Tests nach dem Muster von `tests/test_sensor.py`.

**Ein neuer Befehl**

1. Route in `web/.htapp/routes.php`: `['POST', '/api/ha/v1/<x>', 'api/ha:ha_api_<x>']`.
2. Handler: `[$link, $device] = ha_auth();`, Eingabe mit `req_json()`, schlechte Werte mit `ha_fail(422, 'invalid_value', $en, $de)`, Einstellungen mit `ha_update()`, Antwort mit `ha_send_state()`.
3. Prüfungen über HTTP in `tools/ha-fixtures.php`.
4. In der Integration eine Methode in `api.py`, die Entität ruft sie über `coordinator.async_command()`. Neue Fehlercodes in `coordinator.py` und in `exceptions` der Übersetzungen.
5. Im Testserver `tests/fake_server.py` einen Handler ergänzen.

Danach die Version in `manifest.json` erhöhen (und in `test_manifest`), die Tabelle der Entitäten in beiden READMEs der Integration nachziehen. Prüfen mit `python -m pytest tests -q` und `ruff check . && ruff format --check .` im Ordner `homeassistant/`.

## Rezept 6: die Webseite

- Seiten sind PHP-Ansichten in `web/.htapp/views/`, Handler in `pages/` und `api/`, Adressen in `routes.php`.
- Gestaltung wie in `CLAUDE.md`: Grundfarbe `#08090A` mit Punktraster, Bernstein `#FFAA00` als Leitfarbe, IBM Plex über Bunny Fonts, Flächen durch 1 Pixel Lücke getrennt statt durch Schatten.
- Werte aus dem Entwurf stehen inline, was inline nicht geht (Fokusring, `@keyframes`, Regler), in `web/assets/css/base.css`.
- Kein Skript im HTML: die Content-Security-Policy erlaubt nur Dateien von der eigenen Adresse.
- Nie `innerHTML` mit Werten von außen. Ortsnamen aus OpenStreetMap kann jeder bearbeiten. In PHP schützt `h()`.
- Barrierefreiheit ist Pflicht: Kontrast mindestens 4,5:1, Fokusring über `:focus-visible`, Schalter mit `role="switch"` und `aria-checked`, Fehler in Live-Bereichen, die von Anfang an im Dokument stehen, Ziele mindestens 44 Pixel hoch, `prefers-reduced-motion`.
- `node tools/audit.mjs <ordner>` prüft gespeicherte Seiten gegen diese Regeln.

## Gewohnheiten im Code

- PHP: `declare(strict_types=1)` in jeder Datei außer den Ansichten, Funktionen in `snake_case` mit der Vorsilbe ihres Bereichs (`frame_`, `ha_`, `spotify_`), Konstanten in Großbuchstaben mit derselben Vorsilbe.
- JavaScript: eine Funktion, die sich selbst aufruft, mit `'use strict'` und `var`, ohne Build-Schritt. Globale Namen nur `TW`, `TWOps`, `TWAnim`, `PX`.
- Firmware: Namensräume (`live::`, `anim::`), Funktionen in camelCase, Konstanten als `constexpr`.
- Kommentare im Code auf Deutsch, in PHP, JavaScript und C++ mit ae, oe, ue und ss statt Umlauten. Doku in Markdown mit Umlauten.
- Texte für Menschen auf Englisch und Deutsch, sachlich, mit Zahlen statt Behauptungen, ohne Gedankenstriche und ohne Emoji.

## Checkliste vor dem Pull Request

- [ ] Code, Doku und Prüfungen im selben Pull Request
- [ ] Alle Prüfskripte laufen durch, die neuen eingeschlossen
- [ ] Jeder neue Text auf Englisch und Deutsch
- [ ] Keine Schlüssel, Passwörter, Adressen oder Namen aus der eigenen Instanz
- [ ] Bei Änderungen am Panel ein Bild aus `tools/panel-png.mjs` im Pull Request
- [ ] Bei Firmware: die neue Version und was ältere Geräte stattdessen zeigen
- [ ] Im Pull Request steht, was sich ändert und warum. Den CHANGELOG führt der Projektinhaber
