# services/

Einzige Stelle, die nach außen spricht. Pfade relativ zu `web/.htapp/`. Das Gerät fragt nie selbst bei einem Dienst, sonst lägen Zugangsdaten in der Firmware.

| Dienst | Zweck | Zwischenspeicher | Datei |
| --- | --- | --- | --- |
| `api.adsb.lol/v2/point/LAT/LON/NM` | Flugzeuge im Umkreis | 10 Sekunden je Standort und Radius, die letzte Liste gilt bei einem Ausfall noch 60 Sekunden | `services/adsb.php` |
| `opendata.adsb.fi/api/v2/lat/LAT/lon/LON/dist/NM` | dieselben Flugzeuge, zweite Quelle. Wird immer mitgefragt und über die Hex-Kennung zusammengelegt, bei Dopplern gilt der erste Treffer; antwortet nur eine Quelle, reicht das. Die Liste heißt dort `aircraft` statt `ac` (beim Rufzeichen `ac`), die Felder sind gleich | wie oben | `services/adsb.php` |
| `vrs-standing-data.adsb.lol/routes/<AB>/<cs>.json` | Strecke zu einem Airline-Rufzeichen aus den Standdaten von Virtual Radar Server (gemeinfrei, CC0), stündlich von adsb.lol auf GitHub Pages gespiegelt, eine Datei je Rufzeichen, auch mit Zwischenlandung | Tabelle `cache` (`vrs:<cs>`): 6 Stunden, gefunden oder nicht; nach einem Fehler eine Minute Ruhe | `services/adsb.php` |
| `api.adsbdb.com/v0/callsign/<cs>` | Airline, und die Strecke, wenn VRS keine passende kennt | Tabelle `callsigns`: 30 Tage, unbekannt 1 Tag | `services/adsb.php` |
| `api.open-meteo.com/v1/forecast` | Wetter jetzt und drei Tage | 15 Minuten je Ort | `services/weather.php` |
| `nominatim.openstreetmap.org/search` | Ortssuche auf der Karte | 1 Tag je Suchbegriff, höchstens 1 Anfrage pro Sekunde für den ganzen Server | `services/geocode.php` |
| `nominatim.openstreetmap.org/reverse` | Ort unter dem Flugzeug auf dem Panel | 30 Tage je Kachel von 0,05 Grad, dieselbe Sekunde wie die Suche, höchstens 1 500 am Tag | `services/geocode.php` |
| SMTP (Brevo) | Mails | | `services/smtp.php`, `services/mailer.php` |
| `cdt.hafas.de/opendata/apiserver/` (mobiliteit.lu) | Haltestellen und Abfahrten im Luxemburger Nahverkehr, persönlicher Schlüssel | Haltestellen 1 Tag, Abfahrten 60 Sekunden je Haltestelle, alle Haltestellen des Landes 30 Tage (`.htdata/transit/`) | `services/transit.php`, `services/stops.php` |

Kartenkacheln lädt der Browser direkt von `tile.openstreetmap.org`, erst nach Klick auf "Karte laden".

Die Geodaten der Panel-Karte holt der Server nie zur Laufzeit. Sie liegen fertig gerechnet in `web/.htapp/data/` und kommen aus Natural Earth 1:10 Mio (gemeinfrei) und OurAirports (gemeinfrei), gebaut von `tools/geo-welt.mjs`. Siehe `services/geomap.php` weiter unten.

## services/upstream.php (8)

HTTP-Client, Zwischenspeicher und Abfragebudget. adsb.lol, adsb.fi und adsbdb verlangen einen User-Agent mit Kontakt, sonst kommt statt JSON eine Textzeile. Die Kontaktadresse kommt aus dem Impressum im Admin-Bereich: `TheWall/0.1.0 (+https://thewall.godart.lu; <adresse>)`.

- `user_agent(bool $withContact = true): string` `TheWall/<version> (+<config url>; <Kontakt-E-Mail>)`, ohne Kontakt nur die Adresse der Seite
- `http_get(string $url, int $timeout = 6, array $headers = [], bool $withContact = true): array` nur HTTPS, keine Weiterleitungen, gibt `status`, `body`, `error`
- `http_get_json(string $service, string $url, int $timeout = 6, bool $withContact = true): array` zählt eine echte Abfrage im Budget
- Budget: `budget_add(string $service, bool $cached): void`, `budget_stats(string $service): array` (letzte Stunde, letzter Tag, Anteil aus dem Zwischenspeicher)
- Zwischenspeicher: `cache_get(string $key): mixed`, `cache_put(string $key, mixed $value, int $ttl): void`, `cache_remember(string $key, string $service, callable $fn, int $failTtl = 30): mixed` mit Dateisperre in `.htdata/locks`, damit zwei Geräte im selben Umkreis eine Abfrage teilen; `$fn` liefert `[Wert, Sekunden]` oder `null`. Ein `null` bleibt `$failTtl` Sekunden als `<schlüssel>:fail` gemerkt, noch unter der Sperre gesetzt und vor wie nach ihr geprüft: wer hinter der Sperre wartet, startet dieselbe langsame Abfrage nicht noch einmal

Extern genutzt von: `api/admin.php`, `pages/admin.php`, `pages/welcome.php`, `services/adsb.php`, `services/geocode.php`, `services/transit.php`, `services/weather.php`

## services/aircraft.php (1)

Lesbare Flugzeugtypen. adsb.lol liefert nur das ICAO-Kürzel aus Doc 8643 (`B38M`, `A20N`, `E75L`), das Panel zeigt, was man kennt: `737 MAX 8`, `A320neo`, `E175`. Die Tabelle `AIRCRAFT_TYPES` hat gut 300 Einträge für Linienverkehr, Fracht, Geschäftsreise, Allgemeine Luftfahrt, Hubschrauber und das Militär über Luxemburg, jeder Name höchstens 15 Zeichen (die Zeilen neben dem Logo), Schreibweise wie beim Hersteller.

- `aircraft_type_name(string $icao, int $max = 15): string` Name, ohne Eintrag das Kürzel, ohne Kürzel `?`

Extern genutzt von: `modes/flight.php`, `api/web.php` (Tooltip der Karte)

## services/adsb.php (12)

Konstanten: `ADSB_MAP_MAX` 120, so viele Flugzeuge gehen höchstens an die Karte. `VRS_TTL` 6 Stunden für Strecken aus den VRS-Standdaten.

Welche Strecke tatsächlich gilt, entscheidet `flight_route()` in `modes/flight.php`: beide Quellen kennen je Rufzeichen eine Strecke, aber keinen Tag, deshalb zählt nur eine, die zu Position und Bewegung des Flugzeugs passt. Gemessen am 24. September 2026 an 333 Flugzeugen in den USA und Europa: aus den VRS-Standdaten passten 268 von 286 Strecken, aus adsbdb 162 von 294.

- `adsb_nearby(float $lat, float $lon, int $nm): ?array` Flugzeuge im Umkreis aus adsb.lol und adsb.fi zusammen, je Quelle 4 Sekunden Zeit, normalisiert, nach Entfernung sortiert; `null`, wenn keine Quelle antwortet. Ein Ausfall bleibt 30 Sekunden je Umkreis gemerkt (`adsbfail:*`), eine Minute, wenn eine der Quellen mit 429 gebremst hat, sonst fragte jeder Abruf erneut
- `adsb_stale(string $key): ?array` die letzte brauchbare Liste (`adsblast:*`), solange sie nicht älter als `ADSB_STALE_S` (60 Sekunden) ist
- `adsb_find(string $query, float $lat, float $lon): ?array` ein bestimmter Flug (Einzelflug) nach Rufzeichen, Flugnummer (über adsbdb) oder Kennzeichen; "nicht gefunden" bleibt 20 Sekunden gespeichert
- `adsb_normalise(array $a, float $lat, float $lon): array` Rufzeichen, Kennzeichen, Typ, am Boden, Höhe, Tempo, Kurs, Steigrate, Position, Entfernung, Militär (dbFlags), Kategorie (A1 leicht bis A5 schwer, A7 Hubschrauber)
- `adsbdb_route(string $callsign): ?array` Rufzeichen ICAO und IATA, Airline mit ICAO-Kürzel, Abflug-, Zwischen- (`midpoint`, seit 24. September 2026) und Zielflughafen. Antwortet adsbdb mit einem Fehler (nicht 404), ruht dieses Rufzeichen eine Minute (`adsbdbfail:*`), ein veralteter Eintrag gilt so lange weiter
- `adsbdb_cached_routes(array $callsigns): array` Strecken zu mehreren Rufzeichen in einer Abfrage, nur aus der Tabelle `callsigns` (gefunden, jünger als 30 Tage), nie bei adsbdb
- `adsbdb_airport(?array $a): ?array`
- `vrs_route(string $callsign): ?array` `{found, airline_icao, airports}` aus den VRS-Standdaten, die Flughäfen in Flugreihenfolge (`iata`, `icao`, `city`, `lat`, `lon`); nur für Airline-Rufzeichen (drei Buchstaben, dann eine Ziffer), ein Kennzeichen hat keine Strecke. Budget `vrs`
- `vrs_parse(array $data): array` eine Datei der Standdaten in diese Form; ein Flughafen ohne Koordinaten macht die ganze Strecke ungültig
- `vrs_cached_routes(array $callsigns): array` bekannte VRS-Strecken zu mehreren Rufzeichen in einer Abfrage, nur aus dem Zwischenspeicher
- `geo_bearing(float $lat1, float $lon1, float $lat2, float $lon2): float` Anfangskurs in Grad
- `geo_nm(float $lat1, float $lon1, float $lat2, float $lon2): float` Großkreisentfernung in Seemeilen

Extern genutzt von: `modes/flight.php`, `pages/welcome.php` (Zahl der Flugzeuge im Kopf der Startseite), `api/web.php` (`api_map_aircraft`, Flugzeuge auf der Karte)

## services/weather.php (4)

- `weather_get(float $lat, float $lon, string $tz): ?array` jetzt (Temperatur, Code, Wind) und drei Tage. Nach einem Fehler fragt der Server eine Minute nicht nach, und die letzte gute Antwort (`meteolast:*`) gilt drei Stunden weiter
- `weather_word(int $code, string $lang): string` WMO-Code als kurzes Wort fürs Panel
- `wind_compass(int $deg, string $lang): string` N, NO, O ... (deutsch) oder N, NE, E ...
- `weather_convert(float $celsius, string $unit): int`

Extern genutzt von: `modes/clock.php` (Wetterleiste unter der Uhr), `modes/weather.php`

## services/geocode.php (2)

- `geocode_search(string $query, string $lang): array` `status` `ok` mit `lat`, `lon`, `name`, sonst `none`, `busy`, `error` oder `empty`
- `geocode_reverse(float $lat, float $lon, string $lang): array` der Ort unter einem Flugzeug für `FLYING OVER`: `status` `ok` mit `place` (Stadt, Ort oder Gemeinde) und `region` (Bundesstaat in den USA, Kanada, Australien, Brasilien, Mexiko und Indien, sonst das Land), `none` über Wasser oder ohne Ort, `busy` ohne Antwort. Nominatim reverse mit `zoom=10`, je Kachel von 0,05 Grad (gut 5 km) und Sprache 30 Tage gespeichert (`rgeo2:*`), auch "nichts". Gefragt wird nur für das Flugzeug auf dem Panel, höchstens einmal pro Sekunde für den ganzen Server (dieselbe Sekunde wie die Suche) und höchstens 1 500 Mal am Tag. Ist die Sekunde vergeben, wartet der Abruf nicht, das Panel zeigt dann die Koordinaten. Nach einem Fehler ruht die Ortsbestimmung eine Minute

Konstanten: `GEO_REVERSE_TILE` 0.05, `GEO_REVERSE_TTL` 30 Tage, `GEO_REVERSE_DAY_MAX` 1500, `GEO_REVERSE_STATES`.

Die Kontaktadresse bleibt hier aus dem User-Agent, mit ihr hat Nominatim beim Test mit 403 geantwortet.

Extern genutzt von: `api/web.php` (`POST /api/geo`), `modes/flight.php` (`geocode_reverse()`)

## services/geomap.php (16)

Die Karte des Flugmodus. Rechnet aus den Geodaten ein Bitmap von 128 × 51 Punkten, das die Firmware nur noch hinlegen muss; Text und das Flugzeug kommen als gewöhnliche Zeichenbefehle dazu. Die Ansicht selbst baut `modes/flight_map.php`.

Die Daten liegen in `web/.htapp/data/` und werden nie zur Laufzeit geholt. Sie sind gemeinfrei und stehen im Repo, gebaut aus den Rohdateien von `tools/geo-welt.mjs` und `tools/geo-build.mjs`:

| Datei | Inhalt | Größe |
| --- | --- | --- |
| `geo-world.json` | Küste und Grenzen der ganzen Welt, grob (Natural Earth 1:110 Mio), für die Streckenansicht | 114 KB |
| `geo-detail.bin` | dieselbe Welt fein (1:10 Mio): Landflächen, Seen, Stadtgebiete, Grenzen, Flüsse, dazu 6 615 Start- und Landebahnen aus OurAirports. 2 592 Kacheln zu fünf Grad, jede für sich mit gzip gepackt | 5,2 MB |
| `geo-detail.idx` | Kachel → Versatz und Länge in der `.bin`. Jede Kachel der Welt steht drin, auch die leere mitten im Pazifik: ohne diesen Unterschied wüsste der Server nicht, ob dort Wasser ist oder ob ihm die Daten fehlen | 58 KB |
| `geo-airports.json` | 6 145 Flugplätze: alles mit Linienverkehr plus jeder mittlere und große Platz, mit IATA- und ICAO-Kürzel und Ort | 272 KB |
| `geo-places.json` | 7 342 Ortsnamen der Welt, nach Einwohnern sortiert | 248 KB |
| `geo-near.json` | Luxemburg und Umgebung aus OpenStreetMap, noch feiner, als Einziges mit Autobahnen | 49 KB |

Gelesen wird immer nur das Stück, das der Ausschnitt berührt: zwei bis vier Kacheln, ausgepackt höchstens 35 KB. Die ganze Datei auszupacken kostete bei jedem Abruf eine halbe Sekunde.

Welche Ebenen erscheinen, entscheidet `geo_band()` nach Kilometern je LED, wie im Entwurf `design/karte/ANTWORT.md`:

| Band | km je LED | Ausschnitt | Ebenen |
| --- | --- | --- | --- |
| `nah` | unter 0,25 | bis 32 km | alles, dazu Autobahnen in Luxemburg |
| `umfeld` | unter 1 | bis 128 km | ohne Autobahnen |
| `land` | unter 5 | bis 640 km | Land, Wasser, Grenzen, zwei Ortsnamen |
| `welt` | ab 5 | Streckenansicht | grobe Küste und Grenzen, keine Namen |

Wasser und Stadtgebiet sind Flächen, keine Umrisse: gezeichnet werden sie als Punktraster auf jedem zweiten Punkt, Wasser auf den geraden, Stadt auf den ungeraden. Ein Umriss allein sieht auf 128 × 51 Punkten aus wie hingekritzelt, die Fläche zeigt auf einen Blick, wo die Bucht ist und wo die Stadt. Das Meer wird nicht gespeichert, sondern aus den Landflächen gefüllt: alles, was nicht Land ist. Deshalb muss jede Kachel im Verzeichnis stehen.

Gezeichnet wird erst alles Linienhafte, dann das Raster in die Lücken: gesetzt wird nur, was noch frei ist, also gewinnt jede Linie gegen die Füllung. Umgekehrt löschte das Meer die Küste wieder weg.

Die Flächen sind auf die Kachel zugeschnitten und bleiben geschlossene Ringe. Die Kanten, die dabei auf der Kachelgrenze entstehen, erkennt `geo_on_edge()` an ihren Koordinaten und zeichnet sie nicht: als Linie quer durchs Bild wären sie das Auffälligste daran.

Konstanten: `GEO_W` 128, `GEO_H` 51, `GEO_MIN_LED` 16, `GEO_MIN_GRENZE` 5, `GEO_MIN_KUESTE` 6, `GEO_MIN_FLUSS` 12, `GEO_MIN_STRASSE` 10, `GEO_TILES_MAX` 12, `GEO_COLOURS` (acht Farben, Index 0 ist durchsichtig).

- `geo_band(float $kmProLed): array` welche Ebenen dieses Band zeigt, und ob die feinen Kacheln gelesen werden
- `geo_data(string $which): array` eine der JSON-Dateien, einmal je Abruf gelesen
- `geo_detail_idx(): array`, `geo_tile(string $key): array` eine ausgepackte Kachel, höchstens zwölf im Speicher
- `geo_view_bbox(array $view): ?array` Fenster des Ausschnitts in Grad, `null` bei der Streckenansicht
- `geo_detail(array $view): array` die Kacheln unter dem Ausschnitt, Ebene für Ebene zusammengelegt; Flächen bekommen ihr Kachelfenster als `b` mit
- `geomap_airport_near(float $lat, float $lon, float $maxKm = 60.0): ?array` der nächste Flugplatz aus der Weltliste: `lat`, `lon`, `km`, `ref` (IATA, sonst ICAO), `icao`, `ort`
- `geo_canvas(array $view): array`, `geo_project(array $view, float $lat, float $lon): array`, `geo_project_f(...)` dasselbe ohne Runden, `geo_km_per_led(array $view): float`
- `geo_set(array &$img, int $x, int $y, int $c, bool $stark = false): void`, `geo_line(...)`, `geo_polyline(array &$img, array $view, array $pts, int $c, int $minLed = GEO_MIN_LED, bool $stark = false): void`
- `geo_spans(array $view, array $ringe): array` für jede Bildzeile die Abschnitte innerhalb der Ringe (gerade-ungerade). Die Kanten werden einmal projiziert und nach Zeilen einsortiert, sonst liefe jede der 51 Zeilen über alle paar tausend Kanten
- `geo_fill(array &$img, array $spans, int $c, int $phase, bool $aussen = false): void` Punktraster, mit `$aussen` außerhalb der Ringe
- `geo_on_edge(array $a, array $b, array $box): bool`, `geo_rings(array &$img, array $view, array $ringe, int $c): void`
- `geomap_geography(array $view): array` das fertige Bitmap ohne Spur und Flugzeug, dazu das Band
- `geomap_places(array $view, int $max): array` Ortsnamen im Bild: erst Luxemburg aus OpenStreetMap, dann die Welt nach Einwohnern, jeder Name einmal
- `geomap_short(string $name): string` Kurzform, neun Zeichen
- `geomap_has_near(float $lat, float $lon): bool` liegt der Punkt im Fenster der OpenStreetMap-Daten
- `geomap_op(array $px): array` das Bitmap als `bmp`-Befehl, vier Bit je Punkt
- `geomap_colour(string $name): int`

Gerechnet ist die Geografie in 2 bis 11 ms, das ganze Bild mit Beschriftung in gut 25 ms.

Der Ausschnitt der nahen Ansicht steht in `modes/flight_map.php`: feste Stufen 16, 32, 64, 128 und 256 km, die kleinste, in die alles passt. Hinein müssen das Flugzeug und, wenn es tiefer als 10 000 Fuß in Reichweite eines Flugplatzes ist, dieser Platz. Die Spur zählt nur mit, soweit sie 90 Sekunden zurückreicht (`FLIGHT_FIT_S`), und der Ausschnitt ist mindestens so breit, dass das Flugzeug diese 90 Sekunden darin bleibt. Sonst zoomte die Karte bei einem eben erst gesehenen Reiseflugzeug auf 16 km herunter und riss mit nachwachsendem Schweif auf 256 km auf.

Extern genutzt von: `modes/flight_map.php`, `modes/flight.php`

## services/logos.php (5)

Airline-Logos, 32×34 LED-Pixel in Vollfarbe. Quelle ist `.htdata/logos/<ICAO>.rgb` (3 264 Byte RGB888, Schwarz ist aus), gerechnet von `tools/logos-build.mjs`, siehe [betrieb.md](betrieb.md). Fehlt eine Datei, gilt das Logo aus dem Entwurf (`.htapp/logos/logos.json`, Luxair und Cargolux in Hausfarbe), sonst gibt es keins und Gerät wie Vorschau zeigen den Farbblock mit Kürzel. Eingetragene Marken: nur für angemeldete Nutzer und Geräte mit gültigem Schlüssel.

- `logo_rgb(string $icao): ?string` die 3 264 Byte oder `null`, für Kürzel aus zwei bis vier Zeichen. Zwei Buchstaben stehen für Betreiber ohne ICAO-Kürzel (`PL`, die Polizei in Luxemburg), die Firmware nimmt zwei oder drei
- `logo_rgb_from_rows(array $logo): string` Entwurfsformat in RGB888
- `logos_all(): array` Entwurfs-Logos
- `logo_send(string $rgb): never` Binärantwort mit `X-Logo-Size: 32x34`, eine Woche cachebar
- `logos_status(): array` Anzahl, Stand der Sammlung, Zeitpunkt, für den Admin-Bereich

Extern genutzt von: `api/device.php` (`GET /api/v1/logo/{code}`), `api/web.php` (`GET /api/logo/{code}`), `pages/admin.php` (`logos_status()`)

## services/transit.php (28)

Luxemburger Nahverkehr über die OpenAPI der Administration des transports publics (mobiliteit.lu, HAFAS): AVL, CFL, Luxtram, RGTR, TICE und die Citybusse. Daten unter CC BY 4.0. Der persönliche Schlüssel liegt verschlüsselt in `settings` unter `transit` (wie der SMTP-Schlüssel) und geht nur vom Server an `cdt.hafas.de`; nie in eine Antwort, ein Protokoll oder eine Fehlermeldung, auch nicht, wenn HAFAS die Anfrage zurückschickt. Der Anzeigemodus steht in `modes/transit.php`, dazu kommen Test, Datenprobe und Rohabfrage im Admin-Bereich.

Kontingent des Schlüssels: 500 Abfragen pro Stunde und 5 000 pro Tag. `transit_call()` fragt ab 450 in der Stunde oder 4 500 am Tag nicht mehr (Budget `transit`). Fehlercodes von HAFAS: `API_AUTH` wird 403, `API_QUOTA` 429, `SVC_NO_RESULT` ein leeres Ergebnis, alles andere 424. Nie 502 oder 503: hinter CloudPanel und Cloudflare kam eine 502 der App im Browser nur als abgebrochene Verbindung an.

Haltestellen: Große Knoten sind bei der ATP Gruppen aus Haltestelle (Hauptmast), Steigen und Eingängen. `location.nearbystops` liefert sie nur mit `type=SE`, dann als Eingänge und Steige mit `mainMastExtId`. Abfahrten gibt es für diese ID, deshalb fasst `transit_group_stops()` nach Hauptmast zusammen. Tram-Steige haben ein kleineres Gewicht als Bushalte (1068 gegen 2732, der Hauptbahnhof 32767).

Konstanten: `TRANSIT_BASE`, `TRANSIT_HOUR_CAP` 450, `TRANSIT_DAY_CAP` 4500, `TRANSIT_DEPARTURES_TTL` 60, `TRANSIT_STOPS_TTL` 86400, `TRANSIT_TZ` `Europe/Luxembourg`, `TRANSIT_MODES` (Zug, Tram, Bus), `TRANSIT_BOARD_MAX` 20, `TRANSIT_BOARD_DURATION` 120, `TRANSIT_STALE_TTL` 7200, `TRANSIT_NEXT_TTL` 900, `TRANSIT_SOURCE`, `TRANSIT_SAMPLE_PLACES` (sechs öffentliche Orte für die Datenprobe).

- Schlüssel: `transit_settings(): array` (`has_key`, `tested_at`, `test_ok`, nie der Schlüssel), `transit_key(): string`, `transit_configured(): bool`, `transit_save_key(string $key): void` (leert den Zwischenspeicher `transit:*`), `transit_mark_tested(bool $ok): void`, `transit_quota(): array`
- Abfrage: `transit_call(string $endpoint, array $params, int $timeout = 8): array` gibt `ok`, `status`, `data`, `code`, bei Fehlern `error_en` und `error_de`; `transit_fail(int $status, string $code, string $en, string $de, bool $event = false): array`; `transit_raw(string $endpoint, array $params): array` nur `location.nearbystops` und `departureBoard` (mehr Dienste bietet der Server der ATP nicht, Liste unter `apiserver/?_wadl`) und eine feste Liste einfacher Parameter, darunter `passlist`
- Daten: `transit_nearby(float $lat, float $lon, int $radius = 400, int $max = 5): array` Haltestellen nach Entfernung (Zwischenspeicher `transit:stops3:*`, ein Tag, leer zehn Minuten, dann mit `raw_keys` und `raw_entries` für die Fehlersuche), `transit_group_stops(array $entries): array`, `transit_main_stops(array $stops): array` (größtes Gewicht zuerst, bei Gleichstand die nächste), `transit_departures(string $stopId, int $max = 12, int $duration = 90, bool $raw = false): array` Abfahrten einer Haltestelle, mit `$raw` die erste Abfahrt so, wie HAFAS sie liefert; der letzte gute Stand bleibt zwei Stunden unter `transit:last:*`
- Aufbereitung: `transit_normalize_stop(array $s): array` (`id`, `name`, `place`, `stop`, `lat`, `lon`, `dist_m`, `weight`, `modes`, `lines` mit Farben), `transit_normalize_departure(array $d): array` (`mode`, `label`, `line`, `category`, `number`, `operator`, `direction`, `planned`, `realtime`, `delay_min`, `platform`, `platform_realtime`, `platform_hidden` aus `trackHidden`, `attributes` wie `accessible`, `cancelled`, `part_cancelled`, `status` aus `JourneyStatus`, `additional` (Zusatzfahrt, `A`), `replacement` (Ersatzverkehr, `R`), `redirected` (Halt dazu oder weg, Planzeiten geändert), `time_changed` (aus `scheduledTimeChanged`), `colour`, `notes`; Bedeutungen laut `rest-2.52.xsd`), `transit_list(mixed $v): array` (HAFAS schickt einzelne Einträge als Objekt), `transit_mode(int $cls, string $catOut = ''): string` (Klassen 1, 2, 4 Zug, 32 Bus, 256 Tram), `transit_modes(int $products): array`, `transit_split_name(string $name): array`, `transit_time(string $date, string $time): ?int` (Ortszeit), `transit_iso(?int $ts): ?string`, `transit_colour(mixed $c): ?string`
- Datenprobe: `transit_sample(): array` je Ort bis zu drei Haltestellen, je Verkehrsmittel die wichtigste, jede mit bis zu 15 Abfahrten in zwei Stunden, der ersten Rohabfahrt und den Kandidaten im Umkreis; `transit_stats(array $stops): array` Anzahl je Verkehrsmittel und Betreiber, Echtzeit, Verspätungen, Ausfälle, Teilausfälle, Zusatzfahrten, Ersatzverkehr, geänderte Fahrten und Planzeiten, sichtbare Gleise, längste Texte

Farben von mobiliteit.lu, Stand 14. September 2026: jeder Bus `#752864`, CFL `#D10074`, Luxtram `#C55302`, Schrift jeweils `#FFFFFF`. Betreibercodes: `AVL`, `RGT`, `TIC`, `CFL`, `Lux`, `CEA` (Citybus Esch), `CET` (Citybus Ettelbruck).

- Panel: `transit_board(string $stopId): array` Abfahrten für den Modus, 20 in zwei Stunden; ein Fehler bleibt eine Minute unter `transit:fail:*`, damit der Abruf alle zehn Sekunden das Kontingent nicht aufbraucht, und kommt mit dem letzten guten Stand als `stale`; eine leere Tafel bringt `next` mit. `transit_next_departure(string $stopId): ?array` erste Abfahrt der nächsten zwölf Stunden, 15 Minuten gespeichert. `transit_short_name(array $stop): string` Vorschlag für den Namen auf dem Panel, höchstens 18 Zeichen: Haltestelle ohne Ort, "Gare routière" wird BUS, bleibt nur BUS oder GARE, kommt der Ort davor; ist es zu lang, wird "-sur-" zum Schrägstrich (ESCH/ALZETTE BUS). `transit_stop_codes(array $stop): array` Linien als Kürzel `{code, mode}`, Züge mit Gattung

Extern genutzt von: `api/admin.php` (`api_transit`, `api_transit_test`, `api_transit_sample`, `api_transit_raw`), `api/web.php` (`api_transit_stops`), `modes/transit.php`, `services/stops.php`, `pages/admin.php`, `views/device.php`

## services/stops.php (10)

Alle Haltestellen des Landes für die Haltestellenkarte der Geräteseite. Die ATP bietet keine Suche nach Namen, nur `location.nearbystops`. Deshalb fragt der Server das Land einmal im Monat in einem Raster ab: 35 Kreise mit 10 km Radius im Abstand von 14 km über Luxemburg samt 5 km Rand (`STOPS_BOX` 49,40 bis 50,22 Nord, 5,70 bis 6,56 Ost), je bis zu 5 000 Treffer (`maxNo`, das Maximum laut WADL). Liefert ein Kreis 5 000 Treffer, oder liegt bei mindestens 50 Treffern der weiteste näher als 60 Prozent des Radius an der Mitte (der Dienst könnte den Radius still begrenzen), fragt der Server stattdessen vier Kreise mit halbem Radius, bis hinunter zu 1 250 m. Einträge werden wie in `transit_group_stops()` nach `mainMastExtId` zusammengefasst, Name und Lage vom Hauptmast.

Gearbeitet wird in Stücken von höchstens drei Abfragen und 20 Sekunden je Aufruf von `POST /api/transit/all`, und nur, solange in der laufenden Stunde weniger als 300 und am Tag weniger als 4 000 Abfragen verbraucht sind: die Tafeln der Geräte gehen vor. Anstoßen darf nur, wer ein Gerät bedienen darf (Besitzer oder Bearbeiter). Eine Dateisperre lässt nur einen Aufruf gleichzeitig arbeiten. Ergebnis in `.htdata/transit/`:

| Datei | Inhalt |
| --- | --- |
| `stops-index.json` | jede Haltestelle mit Name `n`, Lage `a`/`o`, Verkehrsmitteln als Bits `m` (1 Zug, 2 Tram, 4 Bus) und Linien `l` als `bus:16` |
| `stops-map.json` | nur `[id, name, lat, lon, bits]` für die Karte |
| `stops-build.json` | Zwischenstand mit Warteschlange, solange das Raster läuft; der alte Stand bleibt bis dahin gültig |

Konstanten: `STOPS_BOX`, `STOPS_RADIUS` 10000, `STOPS_MIN_RADIUS` 1250, `STOPS_MAXNO` 5000, `STOPS_TTL` 30 Tage, `STOPS_CHUNK` 3, `STOPS_HOUR_ROOM` 300, `STOPS_DAY_ROOM` 4000, `STOPS_MODE_BITS`.

- `stops_dir(): string`, `stops_read(string $name): ?array`, `stops_write(string $name, array $data): void`
- `stops_grid(): array` Kreise `[lat, lon, Radius]`
- `stops_split(array $cell): array` vier Kreise mit halbem Radius über das Quadrat des alten
- `stops_merge(array &$stops, array $entries): void`
- `stops_work(): void` ein Stück Arbeit, wenn der Stand fehlt oder älter als 30 Tage ist
- `stops_for_map(bool $work): array` `configured`, `ready`, `building`, `progress` in Prozent, `paused` mit `reason` (`quota` oder ein Code von HAFAS), `built_at`, `stops`; ohne fertigen Stand die bisher gefundenen
- `stops_get(string $id): ?array` eine Haltestelle für die Auswahl wie `api_transit_stops()`: `id`, `name`, `short`, `modes`, `lines`, `lat`, `lon`
- `stops_status(): array` für den Admin-Bereich: `count`, `built_at`, `queries`, `next_at` (nach 30 Tagen), `building`, `progress`, `reason`

Extern genutzt von: `api/web.php` (`api_transit_all`, `api_transit_stop`)

## services/spotify.php (36)

Spotify. Der App-Zugang (Client-ID und Secret) gehört dem Projekt und steht in der Einstellung `spotify`, das Secret mit `seal()` verschlüsselt. Jedes Konto verbindet sein eigenes Spotify-Konto (OAuth mit Autorisierungscode, Umfang `user-read-currently-playing user-read-playback-state`), die Token liegen verschlüsselt in `spotify_links`. Gelesen wird nur, gesteuert nichts. Entwicklungsmodus seit Februar 2026: der Besitzer der App braucht Premium, höchstens fünf Nutzer (`SPOTIFY_USERS_MAX`), jeder unter User Management im Dashboard, sonst antwortet die Web API mit 403.

Spotify erlaubt nach seinen Entwicklerbedingungen nur vorübergehendes Zwischenspeichern, deshalb so kurz wie möglich: "Läuft gerade" 5 Sekunden je Konto, nie über das Ende des Titels hinaus (`spot:<konto>:now`, `spotify_now_ttl()`), Warteschlange 30 Sekunden je Konto und Titel, Album und fertig gerechnete Cover einen Tag (`spotalb:*`, `spotcov:*`). Das Originalbild wird nie gespeichert. Ein 429 bremst alle Abfragen für die Dauer aus `Retry-After` (`spotpause`). Lokal ersetzt `TW_SPOTIFY_MOCK` beide Adressen von Spotify, nur mit `TW_ENV=dev` (siehe `tools/spotify-mock.php` und `betrieb.md`).

- App-Zugang: `spotify_settings(): array` für den Admin-Bereich ohne Secret, mit Redirect-Adresse und Zahl der verbundenen Konten; `spotify_app(): ?array`, `spotify_configured(): bool`, `spotify_save_app(string $clientId, ?string $secret): void` (null behält das Secret), `spotify_mark_tested(bool $ok): void`, `spotify_redirect_uri(): string` (`<url>/spotify/callback`), `spotify_users_count(): int`, `spotify_test_app(): array` (ein Token nur für die App, Client Credentials)
- HTTP: `spotify_mock(): string`, `spotify_base(string $kind): string`, `spotify_http(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 6): array` (liest `Retry-After`), `spotify_token_request(array $fields): array`, `spotify_locked(string $name, callable $fn): mixed` (Dateisperre, damit zwei Geräte ein Token nicht gleichzeitig erneuern)
- Verbindung: `spotify_authorize_url(string $state): string`, `spotify_connect_finish(int $userId, string $code): array` (Code gegen Token, Profil für den Namen; `full`, `not_allowed`, `exchange`, `network`), `spotify_link(int $userId): ?array`, `spotify_unlink(int $userId): void`, `spotify_forget_cache(int $userId): void`, `spotify_link_error(int $userId, ?string $code): void`, `spotify_access(int $userId, bool $force = false): ?string` (erneuert bei Bedarf; nur `invalid_grant` gilt als widerrufen), `spotify_api(int $userId, string $path, int $timeout = 5): array` (ein 401 erneuert einmal und fragt noch einmal; code `ok`, `none`, `not_allowed`, `revoked`, `limited`, `network`, `error`)
- Daten: `spotify_image(array $images): string` (das kleinste Bild ab 250 Pixeln), `spotify_item(mixed $it): ?array` (Titel oder Folge: id, kind, title, artists, album mit name, year, total, type; no, disc, dur, image, url), `spotify_now(int $userId): ?array` (state `play`, `pause`, `none`, `ad`, `not_allowed`, `revoked`, `unlinked`; bei Musik item, pos, at, start, since; ohne Antwort gilt der letzte Stand eine Minute), `spotify_stable(int $userId, array $v): array` (der Beginn des Titels bleibt über Abfragen gleich, erst ab 2,5 Sekunden Abweichung gilt ein Sprung im Titel; since ist der letzte Wechsel von Pause und Weiter), `spotify_queue(int $userId, string $currentId): ?array` (höchstens sechs; `null`, wenn Spotify schon einen anderen Titel spielt, die Liste liegt dann unter diesem), `spotify_forget_now(int $userId): void`, `spotify_now_ttl(array $state): int`, `spotify_album(int $userId, string $albumId): ?array` (Name, Künstler, Jahr, Länge jedes Titels)
- Cover: `spotify_image_url_ok(string $url): bool` (nur `*.scdn.co` und `*.spotifycdn.com`), `spotify_cover(string $url): ?array`, `cover_bitmaps(string $bytes, array $sizes = SPOTIFY_COVER_SIZES): ?array` (64, 48, 44, 16 und 10 Punkte, je bis 15 Farben für den Befehl `bmp`, dazu `lead`), `cover_shrink(GdImage $src, int $size): GdImage` (erst auf das Vierfache, dann gemittelt), `cover_mean_luma(GdImage $img): float`, `cover_bitmap(GdImage $src, int $size, float $gain): array` (helle Cover gedämpft auf mittlere Helligkeit 0,42, Faktor 0,58 bis 0,86; bis zum hellsten Kanal 18 aus, dunkle Farben bis 52 angehoben; die äußerste LED jeder Ecke aus, Spotify verlangt runde Ecken), `cover_lead(array $bmp): ?string` (kräftigste Farbe ab 2 Prozent Fläche, auf volle Leuchtkraft)
- Gerät: `spotify_involved(array $settings): bool` (Modus, Rotation oder Vorrang), `spotify_rev(int $userId): string` (Teil der Revision: anderer Titel, Pause, Weiter, Sprung im Titel)

Extern genutzt von: `modes/spotify.php`, `pages/spotify.php`, `api/web.php`, `api/admin.php`, `api/device.php` (Revision), `auth/users.php` (Löschen), `device/devices.php` (Export), `core/http.php` (`form-action`), `views/admin.php`, `views/device.php`. Geprüft von `tools/spotify-fixtures.php`.

## services/smtp.php (4)

Schlanker SMTP-Client ohne Paket: STARTTLS auf 587 oder SSL auf 465, Anmeldung mit PLAIN oder LOGIN, Zertifikat wird geprüft. Das Passwort erscheint in keinem Fehlertext.

- `smtp_send(array $cfg, string $from, array $to, string $message): void` wirft bei Fehlern mit der Antwort des Servers
- intern: `smtp_addr(string $email): string`, `smtp_cmd($fp, string $line, array $codes, bool $secret = false): string`, `smtp_expect($fp, array $codes): string`

Extern genutzt von: `services/mailer.php`

## services/mailer.php (10)

Die Vorlagen in `mail/` sind die sendefertigen Mails aus den Entwürfen, unverändert. Ersetzt werden nur `REPLACE_WITH_TOKEN`, Benutzername, Name des Einladenden, Gerätename und die Platzhalter im Fußtext. Jede Mail geht als `multipart/alternative` mit Textfassung aus der `.txt` daneben.

- `mail_send(string $to, string $subject, string $html, string $text, array $attachments = []): array` `ok` und `error`; ohne SMTP-Zugang `not-configured`; Fehler landen in Log und "Zuletzt schiefgegangen"
- `mail_build(string $fromEmail, string $fromName, string $to, string $subject, string $html, string $text, array $attachments): string`
- `mail_encode_header(string $s): string`
- `mail_template(string $name): array` HTML und Text aus `mail/<name>.html` und `.txt`, Fußtext aus dem Impressum (`[NAME], [ADRESSE]`, leere Felder fallen weg), Adresse und Host der Seite aus `app_url()` in Links und Fließtext
- `mail_replace_word(string $html, string $word, string $value): string` ersetzt ein Wort nur als ganzes Wort, die Aufrufer übergeben den Wert schon escaped
- Mails: `mail_confirm(array $user, string $token): array`, `mail_reset(array $user, string $token): array`, `mail_invite(string $email, array $inviter, array $device, string $token): array`, `mail_test(string $to): array`, `mail_export(array $user, string $fileName, string $json): array` (JSON als Anhang)

Extern genutzt von: `api/admin.php`, `api/web.php`, `pages/account.php`
