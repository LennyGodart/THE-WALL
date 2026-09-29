# assets/

Öffentlich ausgeliefert, von nginx mit langem Cache. Jede Einbindung trägt `?v=` aus Änderungszeit und Größe (`asset()` in `core/view.php`), eine geänderte Datei bekommt so eine neue Adresse. Kein Build, kein Paketmanager, alles klassische Skripte mit `defer`.

## Ladereihenfolge

Jede Seite: `lang-early.js` im Kopf, am Ende `pixelfont.js`, `ui.js`, dann die Seitenskripte aus `page_close()`.

| Seite | zusätzliche Skripte |
| --- | --- |
| `/` | `lib/ops.js`, `pages/welcome.js` |
| `/account` | `pages/account.js` |
| `/device` ohne Gerät | `pages/nodevice.js` |
| `/device/{id}` | `lib/ops.js`, `lib/anim.js`, `lib/tour.js`, `pages/device.js` |
| `/settings/{id}` | Besitzer: `lib/ops.js`, `lib/anim.js`, `lib/tour.js`, `pages/settings.js`; sonst nur `pages/settings.js` |
| `/admin` | `pages/admin.js` |
| `/animations` | `lib/ops.js`, `lib/anim.js`, `pages/animations.js` |
| `/controls` | `pages/controls.js` |
| Fehlerseiten | `pages/notfound.js` |
| `/map` (iframe) | nur `lang-early.js`, `vendor/leaflet/leaflet.js`, `pages/map.js` |

## Globale Objekte

| Objekt | Datei | genutzt von |
| --- | --- | --- |
| `window.PX` | `js/lib/pixelfont.js` | `ui.js`, `ops.js`, `anim.js`, allen Seiten mit Panel |
| `window.TW` | `js/core/ui.js` | allen Seitenskripten außer `map.js` |
| `window.TWOps` | `js/lib/ops.js` | `device.js`, `anim.js` |
| `window.TWAnim` | `js/lib/anim.js` | `animations.js`, `ops.js` (Befehl `anim`), `tour.js` |
| `window.TWTour` | `js/lib/tour.js` | niemand, `open()` und `close()` zum Ausprobieren in der Konsole |

## js/core/lang-early.js

Läuft im Kopf. Steht in `localStorage` `tw-lang=de`, setzt es `html[lang]` sofort und verbirgt die Seite (`tw-de-pending`), bis `ui.js` die Texte getauscht hat, höchstens 1,5 Sekunden. Kein englischer Zwischenstand, kein Cookie.

## js/core/ui.js (19)

Gemeinsamer Code, stellt `window.TW` bereit.

- Sprache: `lang()`, `t(en, de)`, `applyLang(l)` (tauscht `textContent` aus `data-de`, dazu über `swapAttr(el, attr, key, l)` die Attribute aus `data-de-label`, `data-de-placeholder`, `data-de-title`), `setLang(l)`, `text(el, en, de)` (Text aus JavaScript in beiden Sprachen), `onLang(fn)`
- Zeichnen: `drawMarks()` Wortmarke, `drawHeadings()` Pixel-Überschriften aus `[data-pxhead]` in fünf Größen, bis sie in drei Zeilen passen, `ledBar(holder, share, colour, glow, px, rows)`, `brightBar(holder, value)`, `fit(container, cols, maxPx, minPx)`
- Server: `csrf()`, `api(url, body, method)` (fetch mit CSRF-Kopf, gibt `{ok, status, data}`), `errorText(res, fallbackEn, fallbackDe)`
- Sonstiges: `flash(el, en, de, ms, colour)` (Meldung, verschwindet nach `ms`; Fehler mit `ms` 0 bleiben stehen), `pageData()` (JSON aus `#tw-data`), `onResize(fn)`, `fireResize()`

## js/lib/pixelfont.js (11)

5×7-Bitmapfont mit 6 px Vorschub und LED-Renderer, Quelle der Wahrheit für das Firmware-Raster. Gegenüber der Fassung in den Entwürfen ergänzt: Tilde, Dollar, Und-Zeichen, Semikolon, eckige und geschweifte Klammern, Backslash, Zirkumflex, Gravis und senkrechter Strich (FEHLERLISTE 1.2).

- `grid(w, h)`, `set(g, x, y, color)`, `get(g, x, y)`
- `text(g, x, y, str, color, scale, mixed)` groß, außer `mixed`; `width(str, scale)`, `wrap(str, cols, scale)`, `block(g, x, y, lines, color, scale, lead)`
- `rect(g, x, y, w, h, color)`, `frame(g, x, y, w, h, color)`, `rnd(seed)`
- `paint(canvas, g, opts)` LEDs mit Abstand und Glühen auf ein Canvas

## js/lib/ops.js (19)

Zeichnet die Antwort des Servers auf ein 128×64-Raster, so wie die Firmware es soll. Fehlende Felder haben dieselben Vorgaben wie in `live.cpp`: `clock` ohne `h24` zeigt 24 Stunden, `z` liegt zwischen 1 und 8.

- öffentlich: `pageAt(frame, nowMs)` laufende Seite, `render(g, page, ctx)` (auch `ticker`: Versatz aus `ctx.now`, mit `ctx.reduce` am Anfang; `rect` mit `dot` zeichnet nur jede n-te Spalte; `clock` mit `ap` setzt AM und PM klein daneben; neue Notiz: die ersten zwei Textzeilen weiß und ein Rahmen in Bernstein, nicht mit `ctx.reduce`; `clock` ohne `sec` und `seg` blinkt den Doppelpunkt im Sekundentakt, nicht mit `ctx.reduce`; `ctx.bright` geht an die Animationen), `clockText(op, nowMs, iana)`, `dateText(lang, nowMs, iana)`
- intern: `colour(c)`, `pad(n)`, `zoom(op)`, `parts(nowMs, iana)` (Uhrzeit über `Intl.DateTimeFormat` mit IANA-Zone), `loadLogo(code, url)` (3 264 Byte RGB888 von `/api/logo/{code}`), `drawLogo(g, op, url)` (jede nicht schwarze LED in ihrer Farbe, sonst Farbblock mit Kürzel)
- Spotify, wie `live.cpp` ab 0.2.0: `drawProg(g, op, nowMs, over)` (öffentlich; Zeitleiste, `ctx.progOver` erzwingt die Breite), `drawCount(g, op, nowMs)`, `drawDisc(g, op, nowMs, reduce)`, dazu `mmss(s)`, `clampTo(v, a, b)`, `bez(x1, y1, x2, y2)` (cubic-bezier wie `anim.cpp`). `rect` mit der Farbe `000000` löscht, wie Schwarz in der Firmware

## js/lib/anim.js (21)

Die dreizehn Geräte-Animationen als Funktion der Zeit. Vorlage für die Firmware, dazu die Befehle `anim` in der Vorschau. Optionen in `o`: `lang`, `reduce`, `now`, `iana`, `h24`, `bright` (unter 96 hellere Töne im Ruhezustand), `phone` (wifi: ein Handy ist im Einrichtungsnetz), `user` (paired: der Name des Kontos wie in `anim.cpp`, ohne ihn der Beispielname LENNY; über 10 Zeichen einfach statt doppelt groß). `wifi` zeichnet wie `anim.cpp` ohne Handy CONNECT A PHONE, WLAN-Zeichen, gepunktete Linie mit laufendem Strich und ein wartendes Handy, darunter Netzname und Passwort im Klartext; mit `phone` dieselbe Szene verbunden. Den QR-Code und `qr.js` gibt es seit dem 19. September 2026 nicht mehr (CHANGELOG 42).

- öffentlich: `list` (`[{key, dur, loop}]`), `draw(key, g, t, o)`; wie in `anim.cpp` rechnet `resting` mit der ganzen Zeit und jede Schleife läuft über ihre Dauer
- Übergänge wie in `anim.cpp`, für die Vorschau der Geräteseite: `push(g, from, to, k)` Moduswechsel, `drop(g, to, bands, k)` Einstieg in die Abfahrtstafel, `swap(g, from, to, k)` Flugwechsel (jede Zeile rollt in ihrem Streifen), `roll(g, from, to, bands, k)` neue Minute, Zeilen rücken nach, wenn eine Abfahrt wegfällt
- Spotify, wie `anim.cpp` ab 0.2.0: `song(g, from, to, w, k, kind, oldProg, freezeMs)` Walze (`reel`) oder Karussell (`carousel`) in 560 ms mit den Fenstern aus `fxw`, der alte Balken läuft über `TWOps.drawProg` leer; `lines(g, from, to, w, k)` die Ansage rollt zeilenweise herein. Intern `bez()`, `reelWin()`, `slideWin()`, `rollLines()`. Mit reduzierter Bewegung stehen einmalige Animationen gleich im Endbild, `paired` ohne drehenden Strahl und ohne blinkenden Fund (wie 0.1.10)
- intern: `de(o)`, `centre(g, y, s, col, z)`, `plane(g, x, y, col)`, `nowText(o, fallback)`, `night(o, day, dark)`, `flightFrame(g)`, `px(g, x, y)`, `clamp(k)`, `chan(c)`, `sameRow(a, ya, b, yb)`

## js/lib/tour.js (34)

Einführung ins Gerät, nachgebaut aus `design/Intro.dc.html` (Maße, Kamera, Zeiten in `design/tour/TOUR.md`). Ein Dialog über der Seite (`role="dialog"`, `aria-modal`, Überschrift der Station als Name): links das Steuerboard als CSS-3D-Modell, 21 Bauteile als Quader aus Deckfläche und drei Wänden, 5 px je Millimeter, darunter das Panel mit `TWAnim` und den drei Firmware-Bildschirmen (gehalten, EN-Fenster, zurückgesetzt), rechts der Text; sechs Stationen und "Fertig". Öffnet von selbst mit `tour.auto` aus den Seitendaten, sonst über jeden Knopf mit `data-tour-open` (Geräteseite unten, Einstellungsseite im Abschnitt Gerät); beim Öffnen einmal `POST /api/account/tour`. Mit `tour.test` (Testgerät ohne Hardware) heißt die erste Station "ist eingerichtet" statt "ist verbunden" und sagt, dass das Panel auf der Seite bleibt. Die Seite dahinter bekommt `inert`, Tab bleibt im Dialog, Escape schließt, Pfeile blättern, der Fokus geht beim Wechsel auf die Überschrift und am Ende zurück. Station 3 und 4 laufen wie Firmware 0.1.5, mit Tastendruck am Modell (1 mm, 200 ms). Reduzierte Bewegung (System oder Schalter des Geräts): feste Ansicht, Taste gedrückt, Endbild, Schein ruhig. Der Haltknopf hält Panel und Schein an. Geräte- und Benutzername nur als `textContent`

- Modell: `pan(cam)`, `el(tag, css, attrs)`, `face(css, dims)` (abgedunkelt wird jede Fläche, nie ein ganzes Bauteil: `opacity` auf einem Element mit `preserve-3d` macht es flach), `buildBoard(world, parts)`
- Panel: `planFor(step, sub)`, `itemDur(it)`, `animMeta(key)`, `centre(g, y, s, col, z)`, `screenHeld(g, held)`, `screenEnWindow(g, t)`, `screenReset(g)`, `paintItem(g, it, t)`, `drawPanel(local)`, `updatePress(local)`, `tick(now)`
- Texte: `stationText(step, sub)`, `boardLabel(step)`, `panelLabel(step, sub)`, `label(e, pair)`
- Dialog: `build()`, `wide()`, `camera(mode)`, `placeHalo(cam)`, `renderPlay()`, `renderText()`, `render()`, `focusTitle()`, `goTo(n)`, `setSub(i)`, `lockPage(on)`, `open()`, `close()`, `finish()`, `trapTab(ev)`

## js/pages/

| Datei | Aufgabe | Funktionen |
| --- | --- | --- |
| `welcome.js` | Wortmarke mit Einschalt-Scan, Flugzeug im Kopf, Demo-Panel mit acht Bildschirmen vom Server (`demo` aus `#tw-data`, gezeichnet mit `TWOps.render` wie die Vorschau der Geräteseite; Uhr und Streifen einmal pro Sekunde neu, nur im sichtbaren Tab), Wechsel alle 4,6 Sekunden mit Haltknopf, Beschreibung für Vorleser aus den Texten der Seite, Modus-Kacheln. Auf dem Handy steht das Panel zwischen Einleitung und Bildschirmen (`.demo-grid` in `base.css`) | `drawHeroFrame(t, planeX)`, `startHero()`, `startPlane()`, `pages()`, `describe(page, now)`, `drawPanel()`, `renderTabs()`, `startCycle()`, `stopCycle()`, `drawModes()` |
| `account.js` | Reiter ohne Neuladen, Prüfung vorab, Stärkeanzeige (dazu die Stärke als Satz im Hinweis unter dem Feld, der `aria-live` hat, nur wenn sich das Wort ändert), Panel reagiert auf das Formular (Radar: ein Blip leuchtet, wenn der Strahl ihn überstrichen hat, und klingt in zwei Stufen ab; das Häkchen zeichnet sich einmal und bleibt), Token aus der Adresszeile entfernen | `setHint(name, pair, isError)`, `resetHints()`, `strength()`, `speakStrength(score)`, `renderMeter()`, `setMode(mode)`, `validate()`, `showErrors(errors)`, `planeSprite(g, x, y, col)`, `sceneRadar(g, t)`, `sceneName(g, t)`, `sceneMail(g)`, `scenePass(g)`, `sceneDone(g, t)`, `drawPanel(t)`, `loop(now)`, `renderMotion()` |
| `device.js` | Entwurf im Browser bis "Übernehmen", Vorschau vom Server, Karte über `postMessage`, Geräteumschalter, Statusleiste alle 30 Sekunden neu, solange die Seite sichtbar ist, Hinweis unter "Ansicht wechseln alle" mit der Länge einer Runde. Nahverkehr: Haltestellenkarte auf Klick (iframe `/map/stops`, Auswahl per Nachricht `stop-toggle`, Name und Linien von `/api/transit/stop`), dazu Haltestellen im Umkreis des Standorts (`/api/transit/stops`), bis zu drei; Name fürs Panel mit Zähler bis 18, mit drei Verkehrsmitteln 17 (so viel zeigt der Kopf), Verkehrsmittel, Liste der Linien zum Aus- und Einblenden; die Liste der gewählten Haltestellen wird nur neu gebaut, wenn sich die Auswahl ändert, damit das Namensfeld beim Tippen den Fokus behält. Schalter "Bewegung reduzieren". Zwei Fenster mit großen Vorschauen, beide aus `makePicker()`: ein Dialog über der Seite (`role="dialog"`, `aria-modal`, die Seite dahinter `inert`, Escape am Dokument, Tab bleibt drin, der Fokus geht zurück auf den Knopf), jede Kachel holt sich `/api/preview` mit ihrem eigenen Entwurf und zeichnet die Antwort mit `ops.js`, nicht solange der Tab unsichtbar ist. Die Anfragen laufen nacheinander, nicht parallel: der Server beantwortet sie ohnehin der Reihe nach, parallel dauerte die erste Kachel 534 ms statt 37 ms (gemessen am 20. September 2026). Bis ein Bild da ist, steht LAEDT auf der Kachel, und die Kopfzeile zählt mit. "Ansichten wählen" (zwei Knöpfe: einer in der Modi-Spalte, einer in der Karte "Ansichten" im Flugmodus) zeigt die fünf Ansichten des Flugmodus, alle zehn Sekunden neu; die Schalter ändern `flight.views`, die letzte Ansicht lässt sich nicht abschalten, und die Karte trägt einen Hinweis, wenn die Firmware älter als 0.1.9 ist. "Alle Modi ansehen" (Knopf in der Modi-Spalte, seit dem 20. September 2026 `hidden`, weil noch nicht gebraucht) zeigt alle sechs Modi, alle fünfzehn Sekunden neu; die Knöpfe darin sind dieselben wie in der Spalte (`data-set="mode"`, `data-rotation`), ein nicht eingerichteter Modus trägt einen Hinweis. Karte "Einheiten" beim Flugmodus (Radiogruppen `flight.ualt`, `uspd`, `uvr`, `udist`), die Einheiten gehen mit `filter` auch an die Karte. Nach "Übernehmen" zwei Sekunden ein grüner Balken unten in der Vorschau, so lange braucht das Panel höchstens (nicht mit reduzierter Bewegung). Radiogruppen mit Pfeiltasten, nur das gewählte Feld im Tab-Weg. Die Vorschau fragt nicht, solange der Tab unsichtbar ist. Übergänge wie auf dem Gerät (`TWAnim.swap` beim Flugwechsel, `roll` bei neuer Minute, `push` oder `drop` bei einem anderen Modus, auch gleich nach dem Klick darauf), sonst nach einer Änderung auf der Seite ein harter Schnitt, damit sie sofort steht; nicht mit reduzierter Bewegung oder angehaltener Vorschau. Die Zeichenzähler sagen "3 frei" statt nur der Zahl. "Übernehmen" schickt nur, was sich seit dem Laden geändert hat, in den Modi je Feld (`changes()`), damit ein alter Tab keine Gastnotiz und keine Zeitzone zurücksetzt. Steht eine neue Notiz vor dem gewählten Modus, sagt ein Satz unter der Vorschau, wie lange noch (Minuten, alle 15 Sekunden neu gerechnet aus `noteLeft` der Seitendaten, von "Übernehmen" und der Statusabfrage), mit dem Knopf "Notiz jetzt ausblenden" (`POST /api/device/{id}/note/hide`); der Satz ist ein Live-Bereich, der von Anfang an im Dokument steht. Spotify: Stand der Verbindung von `/api/device/{id}/spotify` alle 15 Sekunden, solange der Modus gewählt und die Seite sichtbar ist, "Mit Spotify verbinden" als Formular (gesperrt mit Hinweis, solange ein Entwurf nicht übernommen ist), "Trennen", Layout-Kacheln aus `spotifyDemo` der Seitendaten, Ansichten als Knöpfe mit `aria-pressed`, Rückmeldung aus `?spotify=` einmal und dann aus der Adresse. Die Vorschau zeigt Walze, Karussell und Ansage wie das Gerät und zeichnet dabei die alte Seite live weiter. Timer und Wecker (seit dem 26. September 2026) gelten sofort, ohne "Übernehmen", und stehen deshalb nicht im Entwurf (`comparable()` und `changes()` lassen `timers` und `alarm` aus): Knöpfe für 1 bis 30 Minuten, Minuten und Name frei, Liste der laufenden Timer mit Restzeit jede Sekunde (gerechnet mit der Uhr des Servers, `skew`) und Abbrechen; der Wecker mit Schalter, Uhrzeit und Tagen als Knöpfe mit `aria-pressed`, dazu wann er als Nächstes klingelt, in der Zone des Geräts. Klingelt etwas, sagt ein Satz unter der Vorschau es, mit "Stoppen"; kurz nach jedem Timerende und jeder Weckzeit fragt die Seite den Stand neu (`scheduleRingCheck()`). Läuft ein Timer oder hört er auf, holt die Vorschau sich neu, damit die Ecke kommt und geht. Fehler an Timer und Wecker stehen in Live-Bereichen (`role="alert"`), die von Anfang an im Dokument sind. Schalter "Panel ausschalten" (`off`) wie die anderen Einstellungen mit "Übernehmen" | `get(path)`, `set(path, value)`, `comparable(s)`, `isDirty()`, `notesDirty()`, `canApply()`, `render()`, `drawBar()`, `drawFaces()`, `changed(path)`, `schedulePreview(ms)`, `fetchPreview()`, `drawPreview()`, `loop(now)`, `renderMotion()`, `pushMap()`, `pushFilter()`, `el(tag, style, text)`, `isSchool(code)`, `shortMax()`, `modeWords(modes)`, `square(modes)`, `byCode(a, b)`, `stopRow(stop, i)`, `nearRow(stop)`, `renderTransit()`, `pushStopsMap()`, `toggleStop(id)`, `rovingTabs()`, `setMenu(open)`, `refreshStatus()`, `views()`, `viewsSummary()`, `makePicker(root, openBtn, o)` mit `note`, `fetchAll`, `lock`, `show`, `hide`, `kartenAnsichtMoeglich()`, `changes()`, `renderSpotify()`, `drawSpotifyTiles()`, `spotifyStatus()`, `setNoteLeft(s)`, `renderNoteFront()`, `nowS()`, `pad2(n)`, `leftText(s)`, `ariaPair(el, en, de)`, `timersRunning()`, `ringingTimers()`, `setRing(r)`, `renderTimers()`, `renderRing()`, `renderAlarm()`, `scheduleRingCheck()`, `timerStart(seconds, name)`, `timerCancel(id)`, `alarmSave(change)` |
| `settings.js` | Name und Zeitzone, Schlüssel kopieren und erneuern mit Sicherheitsabfrage, Freigaben, Home Assistant (Geräte-ID kopieren, gekoppelte Instanzen trennen), Passwort, Export, Entfernen, Löschen | `fail(el, res)`, `openDialog(dialog, opener)`, `closeDialog(dialog)`, `hint(id, en, de, isError)`, `resetPw()`, `resetPwErrors()` |
| `admin.js` | Budget-Balken (adsb.lol gegen die Abfragen ohne Zwischenspeicher, adsbdb der Teil ohne Treffer im Speicher), Hinweis am Rollout-Regler mit Zahl und Namen genau der Geräte, deren fester Platz unter dem Anteil liegt (Namen nur als `textContent`), Testgerät je Konto an und aus, Rollout, Automatik, Registrierung, Registrierungslinks, SMTP mit Test-Mail, Schlüssel für mobiliteit.lu mit Test, Abfahrtsliste und Datenprobe als JSON zum Herunterladen oder Kopieren (Werte von außen nur als `textContent`), Impressum | `fail(el, res)`, `drawBudgets()`, `renderRollout()`, `renderReg()`, `markInvalid(ids)`, `paintTag(tag, kind, en, de)`, `setTag(kind, en, de)`, `modeCounts(byMode)`, `busyTransit(btn, on)` |
| `animations.js` | Liste als Radiogruppe mit Pfeiltasten, Tempo, Abspielen, reduzierte Bewegung; der Ruhezustand läuft über drei Runden (42 s), sonst käme nur der erste der drei Gäste | `current()`, `span(a)`, `renderText()`, `pick(i, focus)`, `loop(now)` |
| `controls.js` | jede Karte der Bibliothek bedienbar; E5 als Radiogruppe mit Pfeiltasten, "Mehr" außerhalb | `drawB2()`, `setBright(v)`, `select(r, focus)`, `drawTiles()`, `hsl(h)`, `drawHue()`, `setE5(hex)` |
| `notfound.js` | Flugzeug verlässt das Bild, Haltknopf | `draw(t)`, `loop(now)`, `renderBtn()` |
| `nodevice.js` | Seite "Noch kein Gerät": fragt alle 4 Sekunden `/api/devices`, solange der Tab sichtbar ist, und wechselt beim ersten Gerät auf `/device`, wo sich die Einführung öffnet; hört nach einer Stunde oder ohne Anmeldung auf | `stop()`, `check()` |
| `map.js` | Leaflet-Karte, Umkreis, Nadel, Suche über `/api/geo`, Sprache beim Laden und per Nachricht. Flugzeuge über `/api/map/aircraft` alle 10 Sekunden, solange die Seite sichtbar und Live an ist; nach 30 Minuten ohne Bedienung hält sie selbst an. Marker bleiben je `hex` stehen und springen, Klick wählt aus, Höhenfilter, Militärfarbe und Einheiten (Höhe, Tempo, Abstand wie auf dem Panel) kommen per Nachricht `filter` von der Geräteseite. Rufzeichen und Strecken nur als `textContent` | `T(en, dd)`, `write(el, parts)`, `say(parts)`, `render(fit)`, `tell(msg)`, `moveTo(lat, lon, name, fit)`, `csrf()`, `acKey()`, `ignored(ac)`, `acName(ac)`, `pad(n)`, `group(n)`, `altText(ac)`, `speedText(kt)`, `distText(nm)`, `details(ac)`, `brief(ac)`, `acSchedule(ms)`, `pause(state)`, `acFetch()`, `acMoved()`, `acDraw()`, `acText()`, `applyLang()` |
| `stops.js` | Haltestellenkarte im iframe: alle Haltestellen als Punkte auf einer Canvas-Ebene (Bus Bernstein, Zug Cyan, Tram Grün, gewählte größer mit weißem Rand), Suche nach Ort oder Haltestelle in der Liste selbst, ohne Abfrage nach außen (ein Ort fasst seine Haltestellen zusammen und zoomt auf alle), Fenster mit Hinzufügen und Entfernen. Lädt über `/api/transit/all` und fragt weiter, solange der Server das Raster sammelt. Nachrichten: `lang`, `center`, `chosen` herein, `stops-ready` und `stop-toggle` hinaus. Namen nur als `textContent` | `T(en, dd)`, `tell(msg)`, `csrf()`, `group(n)`, `tint(m)`, `size()`, `isChosen(id)`, `style(s)`, `modeWords(m)`, `drawAll()`, `popupContent(s)`, `openStop(id, fly)`, `refreshPopup()`, `norm(t)`, `placeOf(name)`, `search(term)`, `renderHits()`, `pickHit(i)`, `write(el, parts)`, `say(parts)`, `status()`, `drawLegend()`, `schedule(ms)`, `load()`, `applyLang()` |

Alle Funktionen in `js/pages/` sind lokal in einer IIFE, es gibt keine Aufrufer von außen.

## css/

- `base.css` Grundzustand (Grund `#08090A` mit Punktraster), Knöpfe `.btn-ghost`, `.btn-solid`, `.btn-danger`, Felder `.field`, Schalter `.sw` (Knopf im Aus-Zustand `#8B949C`, 3:1 gegen den Grund), Chips `.chip`, `.chip.tint` (Farbe über `--tint`, Bus, Zug, Tram) und `.chip.line` (Linien im Nahverkehr: gezeigt mit Bernsteinrand, ausgeblendet grau und durchgestrichen), Reiter, Sprachschalter, Zustände über `aria-pressed`, `aria-checked`, `aria-disabled`, `aria-expanded`, Fokusring über `:focus-visible`, Range-Regler (44 px hohe Trefferfläche, 4 px Spur), Platzhalter `#8B949C`, Keyframe `px-blink` mit den Klassen `.blink-18` und `.blink-24` (zwei Durchläufe, danach steht der Punkt), `prefers-reduced-motion` mit `animation-iteration-count: 1`
- `base.css`, Einführung ins Gerät: `.tour` (Fläche über der Seite), `.tour-body`, `.tour-visual`, `.tour-stage`, `.tour-foot` (ab 860 px nebeneinander, darunter untereinander), `.tour-hit` (44 px Trefferfläche), `.tour-rail` (Zustände in Station 6), `.tour-face` mit dem Abdunkeln über `.tour-board[data-focus]`, Keyframe `tw-glow` für `.tour-halo` (angehalten mit `data-paused`, ruhig mit `data-reduce`)
- `map.css` Karte aus `radius-map.html`, Filter für die Kacheln, Leiste unten mit Live-Schalter, Flugzeuge als Pfeil in Flugrichtung (Bernstein, Militär grün, vom Höhenfilter ausgenommene grau); für die Haltestellenkarte die Trefferliste `.hits` und das Fenster `.stop-popup`

## vendor/leaflet/

Leaflet 1.9.4 lokal statt von unpkg.com (FEHLERLISTE 5.1), mit `LICENSE` und `VERSION.txt` (Quelle und Prüfsummen).

## favicon.svg

Das W aus `pixelfont.js` als SVG.
