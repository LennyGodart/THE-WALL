<?php
// Prueft den Flugmodus (modes/flight.php) ohne Netz: Typnamen, Ansichten, Zeilenlaengen,
// Abflug und Ankunft, Ort unter dem Flugzeug, Balken mit Punkten, Uhr mit AM und PM.
// Der Ort kommt aus dem Zwischenspeicher, der hier vorab gefuellt wird.
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/flight-fixtures.php
// Exit-Code 1 bei einem Fehler.
declare(strict_types=1);
require __DIR__ . '/../web/.htapp/bootstrap.php';

$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok     ' : 'FEHLER ') . $what . "\n";
    if (!$ok) {
        $fail++;
    }
}

function texts(array $ops): array
{
    return array_values(array_map(static fn($o) => (string) $o['s'], array_filter($ops, static fn($o) => $o['t'] === 'text')));
}

function fits(array $ops): bool
{
    foreach ($ops as $o) {
        if ($o['t'] !== 'text' || !is_int($o['x'])) {
            continue;
        }
        if ($o['x'] + panel_width((string) $o['s'], (int) ($o['z'] ?? 1)) > 127) {
            return false;
        }
    }
    return true;
}

// Typnamen.
check('Typ: B38M ist 737 MAX 8', aircraft_type_name('B38M') === '737 MAX 8');
check('Typ: A21N ist A321neo, klein wie beim Hersteller', aircraft_type_name('a21n') === 'A321neo');
check('Typ: B77W ist 777-300ER', aircraft_type_name('B77W') === '777-300ER');
check('Typ: unbekannt bleibt das Kuerzel', aircraft_type_name('ZZZ9') === 'ZZZ9');
check('Typ: leer ist ein Fragezeichen', aircraft_type_name('') === '?');
$long = array_filter(AIRCRAFT_TYPES, static fn(string $n): bool => strlen($n) > 15);
check('Typ: kein Name laenger als 15 Zeichen', $long === []);

// Dauer und Texte.
check('Dauer: 8 Minuten genau', flight_span(8 * 60 + 10) === '~8 MIN');
check('Dauer: 58 Minuten auf Fuenfer, also eine Stunde', flight_span(58 * 60) === '~1H');
check('Dauer: 43 Minuten auf 45', flight_span(43 * 60) === '~45 MIN');
check('Dauer: 1 h 17 auf 1H 20M', flight_span(77 * 60) === '~1H 20M');
check('Abflug: gerade gestartet', flight_departed_text(0, false) === 'JUST DEPARTED' && flight_departed_text(0, true) === 'GERADE GESTARTET');
check('Abflug: vor 2 Stunden', flight_departed_text(7200, false) === 'DEPARTED ~2H AGO' && flight_departed_text(7200, true) === 'ABFLUG VOR ~2H');
check('Ankunft: jetzt unter zwei Minuten', flight_arriving_text(90, false) === 'ARRIVING NOW' && flight_arriving_text(90, true) === 'LANDET JETZT');
check('Ankunft: laengster Text passt in 21', strlen(flight_arriving_text(19 * 3600 + 50 * 60, false)) <= 21 && strlen(flight_departed_text(19 * 3600 + 50 * 60, false)) <= 21);
check('Hoehe: 7.0KFT und 37KFT', flight_alt_text(['ground' => false, 'alt' => 7000]) === '7.0KFT' && flight_alt_text(['ground' => false, 'alt' => 36800]) === '37KFT');
check('Steigrate: m/s mit Vorzeichen, 0 im Reiseflug', flight_vrate_text(640) === '+3.3M/S' && flight_vrate_text(0) === '0M/S' && flight_vrate_text(-2500) === '-13M/S');
check('Ort: mit Bundesstaat, wenn es passt', flight_place_text(['place' => 'Zanesville', 'region' => 'Ohio']) === 'ZANESVILLE, OHIO');
check('Ort: ohne Region, wenn es nicht passt', flight_place_text(['place' => 'Esch-sur-Alzette', 'region' => 'Luxembourg']) === 'ESCH-SUR-ALZETTE');

// Balken: Geflogenes gruen, Rest als Punkte.
$bar = flight_bar_ops(25);
check('Balken: 32 Spalten gruen, 96 als Punkte', count($bar) === 2 && $bar[0]['x'] === 32 && $bar[0]['w'] === 96 && $bar[0]['dot'] === 2 && $bar[1]['w'] === 32 && $bar[1]['c'] === C_GREEN);
check('Balken: ohne Anteil kein Balken', flight_bar_ops(null) === []);

// Ansichten mit einem Flug von Luxemburg nach Lissabon, gerade ueber Metz.
$route = [
    'callsign_icao' => 'LGL4AB', 'airline' => 'Luxair', 'airline_icao' => 'LGL',
    'origin' => ['iata' => 'LUX', 'icao' => 'ELLX', 'city' => 'Luxembourg', 'lat' => 49.6233, 'lon' => 6.2044],
    'destination' => ['iata' => 'LIS', 'icao' => 'LPPT', 'city' => 'Lisbon', 'lat' => 38.7813, 'lon' => -9.1359],
];
$ac = [
    'hex' => '4d0107', 'callsign' => 'LGL4AB', 'reg' => 'LX-LGU', 'type' => 'B38M', 'ground' => false,
    'alt' => 24000, 'gs' => 430.0, 'track' => 225, 'vrate' => 1500, 'lat' => 49.12, 'lon' => 6.18,
    'dst' => 28.4, 'military' => false, 'category' => 'A3',
];
$s = device_defaults();
$ctx = ['settings' => $s, 'device' => ['id' => 0], 'owner' => [], 'lang' => 'en', 'preview' => true];
cache_put('rgeo2:en:' . (int) round(49.12 / GEO_REVERSE_TILE) . ':' . (int) round(6.18 / GEO_REVERSE_TILE), ['status' => 'ok', 'place' => 'Metz', 'region' => 'France'], 60);

check('Ansichten mit Route: vier', flight_views($route) === ['route', 'progress', 'position', 'metrics']);
check('Ansichten ohne Route: Position und Messwerte', flight_views(null) === ['position', 'metrics']);

$ops = flight_ops($ac, $route, 'route', $ctx);
$t = texts($ops);
check('Route: LUXAIR, LUX→LIS, 737 MAX 8, Orte', $t === ['LUXAIR', 'LUX→LIS', '737 MAX 8', 'LUXEMBOURG', 'LISBON'] && fits($ops));

$ops = flight_ops($ac, $route, 'progress', $ctx);
$t = texts($ops);
check('Abflug und Ankunft: Rufzeichen, Strecke, Typ, zwei Zeilen', $t[0] === 'LGL4AB' && $t[1] === 'LUX→LIS' && str_starts_with($t[3], 'DEPARTED ~') && str_starts_with($t[4], 'ARRIVING IN ~') && fits($ops));
check('Abflug und Ankunft: Balken mit Punkten', count(array_filter($ops, static fn($o) => ($o['dot'] ?? 0) === 2)) === 1);

$ops = flight_ops($ac, $route, 'position', $ctx);
$t = texts($ops);
check('Position: FLYING OVER, METZ, FRANCE', $t[3] === 'FLYING OVER' && $t[4] === 'METZ, FRANCE' && fits($ops));
$de = ['lang' => 'de'] + $ctx;
cache_put('rgeo2:de:' . (int) round(49.12 / GEO_REVERSE_TILE) . ':' . (int) round(6.18 / GEO_REVERSE_TILE), ['status' => 'ok', 'place' => 'Metz', 'region' => 'Frankreich'], 60);
$t = texts(flight_ops($ac, $route, 'position', $de));
check('Position deutsch: FLIEGT UEBER, METZ, FRANKREICH', $t[3] === 'FLIEGT UEBER' && $t[4] === 'METZ, FRANKREICH');
cache_put('rgeo2:en:' . (int) round(49.12 / GEO_REVERSE_TILE) . ':' . (int) round(6.18 / GEO_REVERSE_TILE), ['status' => 'none'], 60);
$t = texts(flight_ops($ac, $route, 'position', $ctx));
check('Position ohne Ort: Koordinaten', $t[3] === 'POSITION' && $t[4] === '49.12N 6.18E');

$ops = flight_ops($ac, $route, 'metrics', $ctx);
$t = texts($ops);
check('Messwerte: ALT:24KFT,SPD:796KMH', $t[3] === 'ALT:24KFT,SPD:796KMH' && fits($ops));
check('Messwerte: TRK:225DEG,VR:+7.6M/S', $t[4] === 'TRK:225DEG,VR:+7.6M/S');
check('Messwerte: Entfernung statt Strecke', $t[1] === '28.4NM');

$slow = ['alt' => 3200, 'gs' => 160.0, 'vrate' => -900, 'category' => 'A1', 'type' => 'C172'] + $ac;
$t = texts(flight_ops($slow, null, 'metrics', $ctx));
check('Messwerte klein: ALT:3.2KFT, Cessna 172', $t[3] === 'ALT:3.2KFT,SPD:296KMH' && $t[2] === 'Cessna 172');

// Abflug geschaetzt: grosses Flugzeug im Sinkflug rechnet mit 420 Knoten, nicht mit seinem Tempo.
$p = flight_progress(['alt' => 9000, 'gs' => 250.0, 'category' => 'A3', 'lat' => 38.9, 'lon' => -9.0] + $ac, $route);
check('Abflug: im Sinkflug mit 420 Knoten gerechnet (etwa 2 h 20)', $p['ago'] !== null && abs($p['ago'] - 8430) < 600);
$p = flight_progress(['lat' => 49.63, 'lon' => 6.21] + $ac, $route);
check('Abflug: unter 3 Seemeilen heisst gerade gestartet', $p['ago'] === 0);

// Einheiten zur Wahl.
check('Einheiten: Meter auf 10 gerundet', flight_alt_text(['ground' => false, 'alt' => 37000], 'm') === '11280M');
check('Einheiten: Knoten und Meilen pro Stunde', flight_speed_text(262.0, 'kt') === '262KT' && flight_speed_text(262.0, 'mph') === '302MPH' && flight_speed_text(262.0) === '485KMH');
check('Einheiten: Fuss pro Minute', flight_vrate_text(1234, 'fpm') === '+1230FPM' && flight_vrate_text(0, 'fpm') === '0FPM');
check('Einheiten: Kilometer und Meilen', flight_dist_text(12.3, 'km') === '22.8KM' && flight_dist_text(12.3, 'mi') === '14.2MI' && flight_dist_text(250.0) === '250NM');
$mctx = $ctx;
$mctx['settings']['flight'] = ['ualt' => 'm', 'uspd' => 'kmh', 'uvr' => 'fpm', 'udist' => 'km'] + $ctx['settings']['flight'];
$jet = ['alt' => 37000, 'gs' => 690.0, 'vrate' => -1500, 'track' => 88] + $ac;
$t = texts(flight_ops($jet, $route, 'metrics', $mctx));
check('Einheiten: Jetstream in Metern passt mit kurzen Bezeichnern', $t[3] === 'A:11280M,S:1278KMH' && $t[4] === 'TRK:88DEG,VR:-1500FPM' && $t[1] === '52.6KM');

// Flugwechsel: die erste Seite eines neuen Flugs bekommt fx swap, spaetere Abrufe nicht.
$pages = [];
flight_view_pages($pages, $ctx, $ac, $route, 1000.0, 1010.0, 1000.0, 'flight:4d0107', true);
check('Wechsel: erste Seite eines neuen Flugs mit swap', ($pages[0]['fx'] ?? '') === 'swap' && !isset($pages[1]['fx']));
$pages = [];
flight_view_pages($pages, $ctx, $ac, $route, 1003.0, 1010.0, 1000.0, 'flight:4d0107', true);
check('Wechsel: laeuft der Flug schon, kein swap', !isset($pages[0]['fx']));

// Seiten: ein neuer Flug beginnt mit der Route, die Ansichten wechseln im eingestellten Takt.
$pages = [];
flight_view_pages($pages, $ctx, $ac, $route, 1000.0, 1016.0, 1000.0, 'flight:4d0107');
check('Seiten: Route, Abflug, Position, Messwerte je 4 s', array_column($pages, 'id') === ['flight:4d0107:route', 'flight:4d0107:progress', 'flight:4d0107:position', 'flight:4d0107:metrics'] && $pages[1]['from'] === 1004000);

// Ansicht wechseln alle: seit dem 4. Oktober 2026 bis zu zwei Minuten, vorher hoechstens 30 Sekunden.
$fsan = mode_get('flight')['sanitize'];
check('Ansicht: 120 Sekunden erlaubt, mehr wird 120, unter 2 wird 2', $fsan(['view' => 120], $s['flight'])['view'] === 120 && $fsan(['view' => 600], $s['flight'])['view'] === 120 && $fsan(['view' => 1], $s['flight'])['view'] === 2);
$stufen = FLIGHT_VIEW_STOPS;
sort($stufen);
check('Ansicht: die Stufen des Reglers reichen genau von 2 bis 120, aufsteigend und ohne doppelte', $stufen === FLIGHT_VIEW_STOPS && count(array_unique($stufen)) === count($stufen) && $stufen[0] === FLIGHT_VIEW_MIN && $stufen[count($stufen) - 1] === FLIGHT_VIEW_MAX);
$langsam = $ctx;
$langsam['settings']['flight']['view'] = 60;
$pages = [];
flight_view_pages($pages, $langsam, $ac, $route, 1000.0, 1025.0, 1000.0, 'flight:4d0107');
check('Seiten: bei 60 Sekunden steht die Route das ganze Fenster', array_column($pages, 'id') === ['flight:4d0107:route'] && $pages[0]['to'] === 1025000);
$pages = [];
flight_view_pages($pages, $langsam, $ac, $route, 1050.0, 1075.0, 1000.0, 'flight:4d0107');
check('Seiten: nach 60 Sekunden kommen Abflug und Ankunft', array_column($pages, 'id') === ['flight:4d0107:route', 'flight:4d0107:progress'] && $pages[1]['from'] === 1060000);

/* Leerer Himmel: die eingestellte Seite, ohne Hinweis auf den Grund. Der rote Punkt
   unten rechts fiel am 19. September 2026 weg (CHANGELOG 44), und seit CHANGELOG 50
   raeumt ein Ausfall den Himmel gar nicht mehr: die letzte Liste gilt noch 60 Sekunden. */
$empty = flight_empty_pages($ctx, 1000.0, 1010.0);
check('Leerer Himmel: Uhr ohne roten Punkt', !in_array(op_rect(125, 61, 2, 2, C_RED), $empty[0]['ops'], true) && $empty[0]['id'] === 'empty:clock');

// Airline-Namen: S.A. ohne Punkt am Ende (Befund B6).
check('Airline: SWIFTAIR S.A. wird SWIFTAIR', flight_airline(['airline' => 'Swiftair S.A.', 'airline_icao' => 'SWT'], 'SWT123') === 'SWIFTAIR');

// Uhr: 12 Stunden mit Sekunden bleibt bei Faktor 2 (Befund B15).
check('Uhr: 09:14:33 PM bei Faktor 2 ist 109 breit', clock_width(false, true, 2) === 109);
check('Uhr: 09:14 PM bei Faktor 3 ist 102 breit', clock_width(false, false, 3) === 102);
check('Uhr: 24 Stunden unveraendert', clock_width(true, false, 3) === 87 && clock_width(true, true, 1) === 47);
$c = $s;
$c['clock'] = ['face' => 'big', 'color' => '#FFAA00', 'h24' => false, 'sec' => true, 'wx' => false, 'night' => true] + $s['clock'];
$ops = clock_ops(['settings' => $c, 'lang' => 'en'] + $ctx);
check('Uhr: Fett, 12 h, Sekunden: Faktor 2 mit ap', $ops[0]['z'] === 2 && ($ops[0]['ap'] ?? false) === true);

// Nachtabsenkung: Tag ist, wenn einer der drei Sonnenaufgaenge vor und sein Untergang nach jetzt liegt (Befund B1).
$noonNy = (new DateTimeImmutable('2026-09-18 13:00', new DateTimeZone('America/New_York')))->getTimestamp();
$nightNy = (new DateTimeImmutable('2026-09-18 23:30', new DateTimeZone('America/New_York')))->getTimestamp();
$evenNy = (new DateTimeImmutable('2026-09-18 18:30', new DateTimeZone('America/New_York')))->getTimestamp();
check('Nacht: New York mittags ist Tag', !frame_is_night(40.71, -74.01, $noonNy));
check('Nacht: New York 18:30 ist noch Tag (Untergang etwa 19:05)', !frame_is_night(40.71, -74.01, $evenNy));
check('Nacht: New York 23:30 ist Nacht', frame_is_night(40.71, -74.01, $nightNy));
$noonSyd = (new DateTimeImmutable('2026-09-18 12:00', new DateTimeZone('Australia/Sydney')))->getTimestamp();
check('Nacht: Sydney mittags ist Tag', !frame_is_night(-33.87, 151.21, $noonSyd));

/* Ausschnitt der Karte: das Flugzeug muss immer im Bild liegen. Die Stufenwahl hatte
   einen Fehler, der ab 16 km Bedarf immer 32 km lieferte; dann lag das Flugzeug bei
   groesserem Abstand zum Flugplatz ausserhalb (CHANGELOG 53). */
$flieger = static fn(float $la, float $lo, int $hoehe, float $gs): array
    => ['lat' => $la, 'lon' => $lo, 'alt' => $hoehe, 'gs' => $gs, 'track' => 0.0, 'vrate' => 0];
$lux = ['lat' => 49.6266, 'lon' => 6.2114, 'ref' => 'LUX'];
$weg = static fn(float $km): array => $lux + ['km' => $km];
$drin = static function (array $view, float $la, float $lo): bool {
    [$x, $y] = geo_project($view, $la, $lo);
    return $x >= 0 && $x < GEO_W && $y >= 0 && $y < GEO_H;
};

// 15 km noerdlich des Platzes, tief: beide muessen hinein
$ac1 = $flieger(49.6266 + 15 / 111.32, 6.2114, 2500, 160.0);
$v1 = flight_map_near_view($ac1, $weg(15.0), []);
check('Karte: Anflug aus 15 km Norden, Flugzeug im Bild', $drin($v1, $ac1['lat'], $ac1['lon']));
check('Karte: dabei ist auch der Platz im Bild', $drin($v1, $lux['lat'], $lux['lon']));

// 30 km oestlich, tief: 32 km Breite reichen nicht mehr
$ac2 = $flieger(49.6266, 6.2114 + 30 / 72.2, 3000, 200.0);
$v2 = flight_map_near_view($ac2, $weg(30.0), []);
check('Karte: 30 km oestlich, Stufe waechst ueber 32 km', $v2['km'] > 32.0);
check('Karte: 30 km oestlich, Flugzeug im Bild', $drin($v2, $ac2['lat'], $ac2['lon']));

// Reiseflughoehe: ein Platz in der Naehe zerrt den Ausschnitt nicht auseinander
$ac3 = $flieger(49.9, 6.2114, 36000, 450.0);
$v3 = flight_map_near_view($ac3, $weg(30.0), []);
check('Karte: in Reiseflughoehe ist das Flugzeug die Mitte', abs($v3['lat'] - 49.9) < 1e-9);
check('Karte: Mindestbreite passt zur Geschwindigkeit', $v3['km'] >= 64.0);

// Eine lange Spur schiebt das Flugzeug nicht aus dem Bild
$spur = [];
for ($i = 15; $i >= 0; $i--) {
    $spur[] = [1000 - $i * 60, 49.9 - $i * 0.2, 6.2114 - $i * 0.3];
}
$v4 = flight_map_near_view($ac3, $weg(30.0), $spur);
check('Karte: lange Spur laesst das Flugzeug im Bild', $drin($v4, $ac3['lat'], $ac3['lon']));

/* Festhalten nur im Moment selbst: 30 Sekunden, nicht der ganze Anflug. */
check('Halten: 2 km vor der Schwelle, tief und langsam', flight_at_airport($flieger(49.6, 6.2, 900, 140.0), $weg(2.0)));
check('Halten: 20 km davor noch nicht', !flight_at_airport($flieger(49.6, 6.2, 3000, 160.0), $weg(20.0)));
check('Halten: 2 km, aber 8 000 Fuss darueber, nicht', !flight_at_airport($flieger(49.6, 6.2, 8000, 300.0), $weg(2.0)));
check('Halten: ohne Flugplatz nicht', !flight_at_airport($flieger(49.6, 6.2, 900, 140.0), null));

// Fusszeile der Karte: das Rufzeichen bleibt ganz, rechts wird gekuerzt.
$passt = static fn(array $z): bool => panel_width($z[0]) + 5 + panel_width($z[1]) <= GEO_W - 4;
$z = flight_map_footer('LGL4521', ['3.5KFT +5.1M/S', '3.5KFT+5.1M/S', '3.5KFT']);
check('Karte: LGL4521 bleibt ganz, Steigrate ohne Luecke', $z === ['LGL4521', '3.5KFT+5.1M/S'] && $passt($z));
$z = flight_map_footer('SWR123AB', ['3.5KFT +1500FPM', '3.5KFT+1500FPM', '3.5KFT']);
check('Karte: SWR123AB bleibt ganz, rechts nur die Hoehe', $z === ['SWR123AB', '3.5KFT'] && $passt($z));
$z = flight_map_footer('KLM12', ['12KFT -5.1M/S', '12KFT-5.1M/S', '12KFT']);
check('Karte: kurzes Rufzeichen mit voller Zeile', $z === ['KLM12', '12KFT -5.1M/S']);

// Strecke nur, wenn sie zum Flugzeug passt. Positionen und Strecken sind echte Faelle vom
// 24. September 2026 ueber New York, Luxemburg und Frankfurt.
$ap = static fn(string $icao, string $iata, float $lat, float $lon): array => ['iata' => $iata, 'icao' => $icao, 'city' => $iata, 'lat' => $lat, 'lon' => $lon];
$jfk = $ap('KJFK', 'JFK', 40.639801, -73.7789);
$ewr = $ap('KEWR', 'EWR', 40.692501, -74.168701);
$ord = $ap('KORD', 'ORD', 41.9786, -87.9048);
$sna = $ap('KSNA', 'SNA', 33.675701, -117.867996);
$db = static fn(array $o, array $d, string $name, string $icao): array => ['callsign_icao' => '', 'callsign_iata' => '', 'airline' => $name, 'airline_icao' => $icao, 'origin' => $o, 'midpoint' => null, 'destination' => $d];
$flug = static fn(string $cs, float $lat, float $lon, ?int $alt, float $gs, int $trk, int $vr): array => [
    'hex' => 'abc123', 'callsign' => $cs, 'reg' => '', 'type' => 'A321', 'ground' => false, 'alt' => $alt, 'gs' => $gs,
    'track' => $trk, 'vrate' => $vr, 'lat' => $lat, 'lon' => $lon, 'dst' => 5.0, 'military' => false, 'category' => 'A3',
];

// JBU168 setzt in JFK auf. adsbdb kennt das Rufzeichen noch als Charleston nach Boston: bis
// heute stand dann "ARRIVING IN ~1H" auf dem Panel. VRS kennt Punta Cana nach New York.
$jbu = $flug('JBU168', 40.610504, -73.794495, -25, 133.6, 31, -832);
$jbuDb = $db($ap('KCHS', 'CHS', 32.898602, -80.040497), $ap('KBOS', 'BOS', 42.3643, -71.005203), 'JetBlue Airways', 'JBU');
$jbuVrs = ['found' => true, 'airline_icao' => 'JBU', 'airports' => [$ap('MDPC', 'PUJ', 18.5674, -68.363403), $jfk]];
check('Strecke: CHS nach BOS passt nicht zur Landung in JFK', flight_route_leg($jbu, [$jbuDb['origin'], $jbuDb['destination']]) === null);
$r = flight_route_pick($jbu, $jbuDb, $jbuVrs);
check('Strecke: VRS gewinnt, PUJ nach JFK', $r['source'] === 'vrs' && flight_route_text($r) === 'PUJ→JFK' && $r['airline'] === 'JetBlue Airways');
check('Strecke: Ankunft jetzt statt in einer Stunde', flight_arriving_text(flight_progress($jbu, $r)['left'], false) === 'ARRIVING NOW');
$r = flight_route_pick($jbu, $jbuDb, null);
check('Strecke: passt keine, bleibt die Airline ohne Abflug und Ziel', $r !== null && $r['origin'] === null && $r['airline_icao'] === 'JBU' && $r['source'] === '');
check('Strecke: ohne Strecke nur Position und Messwerte', flight_views($r) === ['position', 'metrics'] && flight_progress($jbu, $r)['left'] === null);
check('Strecke: keine Quelle, keine Route', flight_route_pick($jbu, null, null) === null);

// UAL661 fliegt Santa Ana, Newark, Chicago: bei der Landung in Newark die erste Teilstrecke,
// beim Start dort die zweite.
$ual = ['found' => true, 'airline_icao' => 'UAL', 'airports' => [$sna, $ewr, $ord]];
$r = flight_route_pick($flug('UAL661', 40.669199, -74.179563, -100, 130.1, 26, -704), null, $ual);
check('Strecke: Zwischenlandung, bei der Landung SNA nach EWR', flight_route_text($r) === 'SNA→EWR');
$r = flight_route_pick($flug('UAL661', 40.75, -74.35, 4000, 220.0, 290, 2200), null, $ual);
check('Strecke: Zwischenlandung, beim Start EWR nach ORD', flight_route_text($r) === 'EWR→ORD');

// Hin und zurueck mit Zwischenlandung in JFK, waagerecht nah am Platz: der Kurs entscheidet.
$cmh = $ap('KCMH', 'CMH', 39.998, -82.8919);
$rpa = ['found' => true, 'airline_icao' => 'RPA', 'airports' => [$cmh, $jfk, $cmh]];
$r = flight_route_pick($flug('RPA4399', 40.55, -73.60, 7625, 250.0, 305, 0), null, $rpa);
check('Strecke: waagerecht auf JFK zu ist die Ankunft', flight_route_text($r) === 'CMH→JFK');
$r = flight_route_pick($flug('RPA4399', 40.70, -74.00, 7625, 280.0, 280, 0), null, $rpa);
check('Strecke: waagerecht von JFK weg nach Westen ist der Abflug', flight_route_text($r) === 'JFK→CMH');

// Weitere Faelle: falsche Strecke weit weg, Rueckflug, Umweg wegen gesperrten Luftraums.
check('Strecke: LAX nach SEA ueber New Jersey passt nicht', flight_route_leg($flug('UAL464', 40.403915, -74.20636, 15600, 384.3, 159, 1920), [$ap('KLAX', 'LAX', 33.942501, -118.407997), $ap('KSEA', 'SEA', 47.449001, -122.308998)]) === null);
$clx = [$ap('VHHH', 'HKG', 22.308901, 113.915001), $ap('UBBB', 'GYD', 40.467499, 50.0467), $ap('LOWW', 'VIE', 48.110298, 16.5697), $ap('ELLX', 'LUX', 49.626598, 6.21152)];
check('Strecke: Cargolux steigt ueber Frankfurt nach Osten, der Hinweg passt nicht', flight_route_leg($flug('CLX7334', 50.045285, 8.561096, 27075, 480.4, 86, 1728), $clx) === null);
$kal = [$ap('EGLL', 'LHR', 51.4706, -0.461941), $ap('RKSI', 'ICN', 37.469101, 126.450996)];
check('Strecke: London nach Seoul ueber Koblenz, 60 Grad neben dem Grosskreis, passt', flight_route_leg($flug('KAL908', 50.408905, 7.183797, 33000, 499.9, 104, 0), $kal) !== null);
$afr = [$ap('LFPG', 'CDG', 49.012798, 2.55), $ap('EDDL', 'DUS', 51.289501, 6.76678)];
check('Strecke: Paris nach Duesseldorf ueber Luxemburg passt', flight_route_leg($flug('AFR42YN', 49.756165, 5.887877, 23000, 389.0, 16, 0), $afr) !== null);

// Ankunftszeit: beim Start in LaGuardia nach Kansas City zaehlt das Reisetempo, nicht das
// Tempo beim Abheben (vorher "ARRIVING IN ~6H 50M"). Und wer noch hoch ist, landet nicht jetzt.
$lga = $ap('KLGA', 'LGA', 40.777199, -73.872597);
$mci = $ap('KMCI', 'MCI', 39.2976, -94.713898);
$start = $flug('DAL2068', 40.79, -73.90, 275, 150.0, 310, 3264);
$left = flight_progress($start, ['origin' => $lga, 'destination' => $mci])['left'];
check('Ankunft: im Steigflug mit Reisetempo, etwa 2 h 25', $left !== null && abs($left - 8700) < 600 && flight_arriving_text($left, false) === 'ARRIVING IN ~2H 30M');
$hoch = $flug('RPA4399', 40.60, -73.72, 9625, 250.0, 220, -3264);
$left = flight_progress($hoch, ['origin' => $ap('KCMH', 'CMH', 39.998, -82.8919), 'destination' => $jfk])['left'];
check('Ankunft: 9 600 Fuss ueber dem Platz ist nicht "jetzt"', $left !== null && $left >= 380 && flight_arriving_text($left, false) === 'ARRIVING IN ~6 MIN');

// Die Hubschrauber der Luxembourg Air Rescue fliegen als AIRESC1 bis 3: Logo LRQ, nicht AIR.
check('Logo: AIRESC2 bekommt LRQ', flight_airline_icao(null, 'AIRESC2') === 'LRQ' && flight_airline(null, 'AIRESC2') === 'LUX AIR RESCUE');
check('Logo: AIR123 bleibt AIR, LGL4AB LGL', flight_airline_icao(null, 'AIR123') === 'AIR' && flight_airline_icao(null, 'LGL4AB') === 'LGL');

// Der Hubschrauber der Polizei fliegt als POLICE1 (LX-FAA, 4D03F0): Logo PL, aber nur in Luxemburg.
$pol = ['hex' => '4d03f0', 'reg' => 'LX-FAA'];
check('Logo: POLICE1 aus Luxemburg bekommt PL und POLICE', flight_airline_icao(null, 'POLICE1', $pol) === 'PL' && flight_airline(null, 'POLICE1', $pol) === 'POLICE');
check('Logo: ohne Kennzeichen zaehlt der Block 4D0000 bis 4D03FF', flight_airline_icao(null, 'POLICE1', ['hex' => '4d03f0', 'reg' => '']) === 'PL');
check('Logo: POLICE1 aus einem anderen Land bleibt ohne', flight_airline_icao(null, 'POLICE1', ['hex' => '3c4b21', 'reg' => 'D-HBPA']) === '' && flight_airline(null, 'POLICE1', ['hex' => '3c4b21', 'reg' => 'D-HBPA']) === 'POLICE1');
check('Logo: fremde Kennung ohne Kennzeichen bleibt ohne', flight_airline_icao(null, 'POLICE2', ['hex' => '4d0400', 'reg' => '']) === '');
$ops = flight_ops(['hex' => '4d03f0', 'callsign' => 'POLICE1', 'reg' => 'LX-FAA', 'type' => 'EC45', 'category' => ''] + $ac, null, 'metrics', $ctx);
check('Logo: der Befehl traegt PL, oben steht POLICE1', ($ops[0]['t'] ?? '') === 'logo' && ($ops[0]['code'] ?? '') === 'PL' && in_array('POLICE1', texts($ops), true));

// Die Datei der VRS-Standdaten, wie sie adsb.lol ausliefert.
$v = vrs_parse(['callsign' => 'LGL4592', 'airline_code' => 'LGL', 'airport_codes' => 'EGLC-ELLX', '_airports' => [
    ['name' => 'London City Airport', 'icao' => 'EGLC', 'iata' => 'LCY', 'location' => 'London', 'lat' => 51.505299, 'lon' => 0.055278],
    ['name' => 'Luxembourg-Findel International Airport', 'icao' => 'ELLX', 'iata' => 'LUX', 'location' => 'Luxembourg', 'lat' => 49.626598, 'lon' => 6.21152],
]]);
check('VRS: Datei gelesen, zwei Flughaefen mit Ort', $v['found'] && $v['airline_icao'] === 'LGL' && $v['airports'][1]['iata'] === 'LUX' && $v['airports'][0]['city'] === 'London');
check('VRS: Flughafen ohne Koordinaten macht die Strecke ungueltig', !vrs_parse(['_airports' => [['icao' => 'EGLC'], ['icao' => 'ELLX', 'lat' => 49.6, 'lon' => 6.2]]])['found']);
check('VRS: Kennzeichen haben keine Strecke', vrs_route('DEABC') === null && vrs_route('N123AB') === null);

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
