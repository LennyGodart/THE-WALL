<?php
/*
 * Die Kartenansicht des Flugmodus. Aufgeteilt wie im Entwurf (design/Karte.dc.html):
 * oben 51 Zeilen Karte als Bitmap, darunter eine Trennlinie und eine Textzeile.
 *
 * Nah, wenn das Flugzeug tief und nah am Flugplatz ist: Bahn, Spur der letzten Minuten,
 * Ortsnamen. Sonst die ganze Strecke mit Grosskreis, geflogen gruen, Rest gepunktet.
 *
 * Das Flugzeug ist kein Punkt im Bitmap, sondern ein eigener Befehl mit Geschwindigkeit:
 * die Firmware rechnet ihn zwischen zwei Abrufen weiter, damit die Bewegung nicht ruckt.
 */

declare(strict_types=1);

/* Naeher als das und tiefer als FLIGHT_EVENT_FT heisst: Start oder Landung, nahe Ansicht. */
const FLIGHT_MAP_NEAR_KM = 40.0;
/* So lange bleibt das Panel beim Zuschauen auf der Karte stehen: die letzten Sekunden vor
   dem Aufsetzen und die ersten nach dem Abheben. Gerechnet wird aus Entfernung und
   Geschwindigkeit, nicht in Kilometern: 30 Sekunden sind bei einer Cessna gut zwei und
   bei einer 747 gut vier Kilometer. Wer schon aus dem Norden des Landes zusieht, sieht
   fuenf Minuten lang fast nichts. */
const FLIGHT_HOLD_S = 30.0;
/* Und nur so tief. Dreissig Sekunden vor dem Aufsetzen ist ein Verkehrsflugzeug auf gut
   1 500 Fuss; alles darueber ist noch im Anflug, nicht in der Landung. */
const FLIGHT_HOLD_FT = 3500;
/* Stufen des Ausschnitts. Der Ausschnitt waechst, wenn es eng wird, und schrumpft erst
   beim naechsten Flug: sonst wandert der Rahmen staendig und das Flugzeug scheint zu stehen. */
const FLIGHT_MAP_STEPS = [16, 32, 64, 128, 256];
/* Fuer den Ausschnitt zaehlt nur die juengste Spur, und er ist mindestens so breit, dass
   das Flugzeug anderthalb Minuten darin bleibt. Ohne das zoomt die Karte bei einem eben
   erst gesehenen Reiseflugzeug auf 16 km herunter, und sobald der Schweif nachwaechst
   reisst sie auf 256 km auf. Gezeichnet wird die Spur weiter in voller Laenge. */
const FLIGHT_FIT_S = 90;
/* Spur: so lange zurueck, und hoechstens so viele Punkte. */
const FLIGHT_TRACK_S = 900;
const FLIGHT_TRACK_MAX = 60;

/** Spur eines Flugs fortschreiben und zurueckgeben: [[zeit, lat, lon], ...]. */
function flight_track(string $hex, ?array $ac = null, ?int $now = null): array
{
    $key = 'track:' . strtolower($hex);
    $alt = cache_get($key);
    $list = is_array($alt) ? $alt : [];
    $now ??= time();
    if ($ac !== null) {
        $last = $list ? $list[count($list) - 1] : null;
        $weit = $last === null || geo_nm((float) $last[1], (float) $last[2], (float) $ac['lat'], (float) $ac['lon']) > 0.05;
        if ($last === null || ($now - (int) $last[0] >= 5 && $weit)) {
            $list[] = [$now, round((float) $ac['lat'], 5), round((float) $ac['lon'], 5)];
        }
    }
    $list = array_values(array_filter($list, static fn(array $p): bool => $now - (int) $p[0] <= FLIGHT_TRACK_S));
    if (count($list) > FLIGHT_TRACK_MAX) {
        $list = array_slice($list, -FLIGHT_TRACK_MAX);
    }
    if ($ac !== null) {
        cache_put($key, $list, FLIGHT_TRACK_S);
    }
    return $list;
}

/**
 * Der Flugplatz, um den es gerade geht. Steigt das Flugzeug, ist das sein Startplatz,
 * sinkt es, sein Ziel; die Koordinaten liefert adsbdb mit der Route. Ist der weit weg
 * oder gibt es keine Route, entscheidet der naechste Platz aus der Weltliste. Das
 * greift beim Ausweichflugplatz und bei jedem Flug, dessen Strecke adsbdb nicht kennt.
 */
function flight_airport_for(array $ac, ?array $route): ?array
{
    $steigt = ($ac['vrate'] ?? 0) > 0;
    $a = $route[$steigt ? 'origin' : 'destination'] ?? null;
    $ausRoute = null;
    if (is_array($a) && isset($a['lat'], $a['lon']) && $a['lat'] !== null && $a['lon'] !== null) {
        $ausRoute = [
            'lat' => (float) $a['lat'],
            'lon' => (float) $a['lon'],
            'km' => geo_nm((float) $ac['lat'], (float) $ac['lon'], (float) $a['lat'], (float) $a['lon']) * 1.852,
            'ref' => strtoupper((string) ($a['iata'] ?: $a['icao'])),
        ];
        if ($ausRoute['km'] <= FLIGHT_MAP_NEAR_KM) {
            return $ausRoute;
        }
    }
    $ausListe = flight_airport((float) $ac['lat'], (float) $ac['lon']);
    if ($ausListe === null) {
        return $ausRoute;
    }
    return $ausRoute !== null && $ausRoute['km'] < $ausListe['km'] ? $ausRoute : $ausListe;
}

/** Der naechste Flugplatz aus der Weltliste: alles mit Linienverkehr, 6 145 Plaetze. */
function flight_airport(float $lat, float $lon): ?array
{
    return geomap_airport_near($lat, $lon, FLIGHT_MAP_NEAR_KM + 20.0);
}

/**
 * Wie viele Sekunden das Flugzeug noch bis zum Flugplatz braucht, aus Entfernung und
 * Geschwindigkeit ueber Grund. Beim Start zaehlt dieselbe Rechnung rueckwaerts: 30
 * Sekunden nach dem Abheben ist es gut drei Kilometer weit.
 */
function flight_airport_seconds(array $ac, array $airport): float
{
    $kmProS = max(0.02, (float) ($ac['gs'] ?? 0) * 1.852 / 3600);
    return (float) $airport['km'] / $kmProS;
}

/** Steht das Flugzeug gerade unmittelbar vor der Landung oder direkt nach dem Start? */
function flight_at_airport(array $ac, ?array $airport): bool
{
    return $airport !== null
        && !empty($ac['alt']) && (int) $ac['alt'] < FLIGHT_HOLD_FT
        && flight_airport_seconds($ac, $airport) <= FLIGHT_HOLD_S;
}

/** Welche Karte dieser Flug bekommt: 'nah' bei Start und Landung, sonst 'strecke'. */
function flight_map_kind(array $ac, ?array $route, ?array $airport): string
{
    $tief = !empty($ac['alt']) && (int) $ac['alt'] < FLIGHT_EVENT_FT;
    $nah = $airport !== null && $airport['km'] <= FLIGHT_MAP_NEAR_KM;
    if ($tief && $nah) {
        return 'nah';
    }
    $hasRoute = $route && !empty($route['origin']['lat']) && !empty($route['destination']['lat']);
    return $hasRoute ? 'strecke' : 'nah';
}

/**
 * Ausschnitt der nahen Ansicht. Das Flugzeug muss hinein, der Flugplatz auch, wenn es
 * dorthin startet oder landet, und die Spur, solange sie passt. Gerastert auf feste
 * Stufen, damit der Rahmen nicht bei jedem Abruf wandert.
 */
function flight_map_near_view(array $ac, ?array $airport, array $track): array
{
    $lat = (float) $ac['lat'];
    $lon = (float) $ac['lon'];
    /* Der Flugplatz zieht den Ausschnitt nur, wenn das Flugzeug auch dorthin will, also
       tief ist. Sonst zerrt ein Platz 30 km weiter das Bild auseinander, waehrend das
       Flugzeug in 11 km Hoehe darueber hinwegfliegt. */
    $ankern = $airport !== null && $airport['km'] <= FLIGHT_MAP_NEAR_KM
        && !empty($ac['alt']) && (int) $ac['alt'] < FLIGHT_EVENT_FT;
    $clat = $ankern ? ($lat + $airport['lat']) / 2 : $lat;
    $clon = $ankern ? ($lon + $airport['lon']) / 2 : $lon;
    $kmLon = 111.32 * cos(deg2rad($clat));

    $pflicht = [[$lat, $lon]];
    if ($ankern) {
        $pflicht[] = [$airport['lat'], $airport['lon']];
    }
    $alle = $pflicht;
    $juengste = 0;
    foreach ($track as $p) {
        $juengste = max($juengste, (int) $p[0]);
    }
    foreach ($track as $p) {
        if ($juengste - (int) $p[0] <= FLIGHT_FIT_S) {
            $alle[] = [(float) $p[1], (float) $p[2]];
        }
    }
    // So weit kommt das Flugzeug in der Zeit, aus der auch die Spur genommen wird.
    $mindest = max(8.0, (float) ($ac['gs'] ?? 0) * 1.852 / 3600 * 2 * FLIGHT_FIT_S);
    $braucht = static function (array $punkte) use ($clat, $clon, $kmLon, $mindest): float {
        $dx = 0.0;
        $dy = 0.0;
        foreach ($punkte as [$pa, $po]) {
            $dx = max($dx, abs($po - $clon) * $kmLon);
            $dy = max($dy, abs($pa - $clat) * 111.32);
        }
        // Rand lassen, und das Bild ist nur 51 von 128 Punkten hoch.
        return max($dx * 2 * 1.3, $dy * 2 * 1.3 * (GEO_W / GEO_H), $mindest);
    };

    /* Zwei Rechnungen: was hinein muss, und was schoen waere. Reicht die groesste Stufe
       fuer die Spur nicht, laeuft sie eben aus dem Bild. Lieber die Spur beschnitten als
       das Flugzeug. */
    $groesste = FLIGHT_MAP_STEPS[count(FLIGHT_MAP_STEPS) - 1];
    $km = $braucht($alle);
    if ($km > $groesste) {
        $km = $braucht($pflicht);
    }
    /* Die kleinste Stufe, in die alles passt. Diese Schleife setzte $km frueher bei jedem
       Fehlschlag auf die Stufe und verglich danach die Stufe mit sich selbst: ab 16 km
       Bedarf kamen immer 32 km heraus, und das Flugzeug lag ausserhalb des Bildes. */
    $gewaehlt = $groesste;
    foreach (FLIGHT_MAP_STEPS as $stufe) {
        if ($km <= $stufe) {
            $gewaehlt = $stufe;
            break;
        }
    }
    return ['art' => 'nah', 'lat' => $clat, 'lon' => $clon, 'km' => (float) $gewaehlt];
}

/** Ausschnitt der Streckenansicht: Start, Ziel und Flugzeug in Mercator einpassen. */
function flight_map_route_view(array $ac, array $route): array
{
    $pts = [
        [(float) $route['origin']['lat'], (float) $route['origin']['lon']],
        [(float) $route['destination']['lat'], (float) $route['destination']['lon']],
        [(float) $ac['lat'], (float) $ac['lon']],
    ];
    $merc = static fn(float $la): float => log(tan(M_PI / 4 + deg2rad($la) / 2));
    $xs = array_map(static fn(array $p): float => deg2rad($p[1]), $pts);
    $ys = array_map(static fn(array $p): float => $merc($p[0]), $pts);
    $x0 = min($xs);
    $x1 = max($xs);
    $y0 = min($ys);
    $y1 = max($ys);
    $sx = (GEO_W - 34) / max(1e-6, $x1 - $x0);      // Rand fuer die Kuerzel neben den Plaetzen
    $sy = (GEO_H - 14) / max(1e-6, $y1 - $y0);
    $s = min($sx, $sy);
    $lonMid = rad2deg(($x0 + $x1) / 2);
    $latMid = ($pts[0][0] + $pts[1][0]) / 2;
    return ['art' => 'strecke', 'lat' => $latMid, 'lon' => $lonMid, 'ymid' => ($y0 + $y1) / 2, 's' => $s];
}

/** Punkt auf dem Grosskreis zwischen zwei Orten, Anteil $f. */
function flight_gc_point(array $a, array $b, float $f): array
{
    [$la1, $lo1] = [deg2rad($a[0]), deg2rad($a[1])];
    [$la2, $lo2] = [deg2rad($b[0]), deg2rad($b[1])];
    $d = 2 * asin(sqrt(sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2));
    if ($d < 1e-9) {
        return $a;
    }
    $A = sin((1 - $f) * $d) / sin($d);
    $B = sin($f * $d) / sin($d);
    $x = $A * cos($la1) * cos($lo1) + $B * cos($la2) * cos($lo2);
    $y = $A * cos($la1) * sin($lo1) + $B * cos($la2) * sin($lo2);
    $z = $A * sin($la1) + $B * sin($la2);
    return [rad2deg(atan2($z, hypot($x, $y))), rad2deg(atan2($y, $x))];
}

/**
 * Die Zeichenbefehle der Kartenansicht.
 * Der Punkt des Flugzeugs bekommt Kurs und Geschwindigkeit in LEDs je Sekunde mit,
 * dazu den Zeitpunkt der Position: die Firmware rechnet ihn von da an weiter.
 */
function flight_map_ops(array $ctx, array $ac, ?array $route): array
{
    $de = ($ctx['lang'] ?? 'en') === 'de';
    $now = (int) ($ctx['now'] ?? time());
    $track = flight_track((string) $ac['hex'], $ac, $now);
    $airport = flight_airport_for($ac, $route);
    $kind = flight_map_kind($ac, $route, $airport);
    $view = $kind === 'strecke' && $route ? flight_map_route_view($ac, $route) : flight_map_near_view($ac, $airport, $track);

    $geo = geomap_geography($view);
    $img = ['px' => $geo['px'], 'view' => $view];
    $band = $geo['band'];

    if ($kind === 'strecke' && $route) {
        /* Die Strecke selbst: geflogener Teil durchgezogen, Rest jeder dritte Punkt. */
        $von = [(float) $route['origin']['lat'], (float) $route['origin']['lon']];
        $nach = [(float) $route['destination']['lat'], (float) $route['destination']['lon']];
        $ganz = max(1.0, geo_nm($von[0], $von[1], $nach[0], $nach[1]));
        $anteil = min(1.0, geo_nm($von[0], $von[1], (float) $ac['lat'], (float) $ac['lon']) / $ganz);
        $prev = null;
        for ($i = 0; $i <= 160; $i++) {
            $f = $i / 160;
            $p = flight_gc_point($von, $nach, $f);
            $q = geo_project($view, $p[0], $p[1]);
            if ($prev !== null) {
                if ($f <= $anteil) {
                    geo_line($img, $prev[0], $prev[1], $q[0], $q[1], geomap_colour('geflogen'), true);
                } elseif ($i % 3 === 0) {
                    geo_set($img, $q[0], $q[1], geomap_colour('rest'), true);
                }
            }
            $prev = $q;
        }
        foreach ([$von, $nach] as $ort) {
            $q = geo_project($view, $ort[0], $ort[1]);
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    geo_set($img, $q[0] + $dx, $q[1] + $dy, geomap_colour('weiss'), true);
                }
            }
        }
    } else {
        /* Spur der letzten Minuten: hinten dunkel, vorne hell. */
        $n = count($track);
        $prev = null;
        foreach ($track as $i => $p) {
            $q = geo_project($view, (float) $p[1], (float) $p[2]);
            if ($prev !== null) {
                geo_line($img, $prev[0], $prev[1], $q[0], $q[1], $i > $n * 0.6 ? geomap_colour('spurhell') : geomap_colour('spur'), true);
            }
            $prev = $q;
        }
    }

    /* Erst planen, dann freistellen, dann zeichnen: Text steht sonst mitten in einer
       Linie. Freigestellt wird im Bitmap selbst, ein Pixel Rand ringsum. */
    $texte = [];
    $belegt = [];
    $frei = static function (int $x, int $y, int $w, int $h) use (&$belegt): bool {
        $box = [$x, $y, $x + $w - 1, $y + $h - 1];
        if ($box[0] < 0 || $box[2] > GEO_W - 1 || $box[1] < 0 || $box[3] > GEO_H - 1) {
            return false;
        }
        foreach ($belegt as $b) {
            if (!($box[2] < $b[0] - 2 || $box[0] > $b[2] + 2 || $box[3] < $b[1] - 1 || $box[1] > $b[3] + 1)) {
                return false;
            }
        }
        $belegt[] = $box;
        return true;
    };
    /* Ein Platz fuer die Beschriftung: rechts, links, darunter, darueber. Ein Name wie
       LUXEMBURG ist 53 Punkte breit, ein Drittel des Bildes. Mit nur zwei Versuchen
       faellt in einer vollen Karte fast jeder zweite Name weg. */
    $setzen = static function (int $px, int $py, string $s, string $c) use (&$texte, $frei): bool {
        $w = panel_width($s);
        foreach ([[4, -3], [-3 - $w, -3], [4, 4], [-3 - $w, 4], [4, -10], [-3 - $w, -10]] as [$dx, $dy]) {
            $x = $px + $dx;
            $y = $py + $dy;
            if ($y < 0 || $y > GEO_H - 7 || !$frei($x, $y, $w, 7)) {
                continue;
            }
            $texte[] = ['art' => 'text', 'x' => $x, 'y' => $y, 's' => $s, 'c' => $c];
            return true;
        }
        return false;
    };

    /* Das Flugzeug bekommt seinen Platz zuerst. Es ist das Wichtigste im Bild, und ein
       Name oder der Massstab quer darueber macht es unlesbar. */
    $qAc = geo_project($view, (float) $ac['lat'], (float) $ac['lon']);
    $frei($qAc[0] - 2, $qAc[1] - 2, 5, 5);

    /* Namen: Kuerzel der Plaetze auf der Strecke, Orte im Nahbereich. */
    if ($kind === 'strecke' && $route) {
        foreach ([['origin', -1], ['destination', 1]] as [$feld, $seite]) {
            $code = strtoupper((string) ($route[$feld]['iata'] ?: $route[$feld]['icao']));
            if ($code === '') {
                continue;
            }
            $q = geo_project($view, (float) $route[$feld]['lat'], (float) $route[$feld]['lon']);
            $w = panel_width($code);
            $y = max(0, min(GEO_H - 8, $q[1] - 3));
            $x = $seite < 0 ? $q[0] - 3 - $w : $q[0] + 4;
            if (!$frei($x, $y, $w, 7)) {
                $x = $seite < 0 ? $q[0] + 4 : $q[0] - 3 - $w;
                if (!$frei($x, $y, $w, 7)) {
                    continue;
                }
            }
            $texte[] = ['art' => 'text', 'x' => $x, 'y' => $y, 's' => $code, 'c' => C_WHITE];
        }
    } else {
        /* Der Flugplatz zuerst: sein Kuerzel traegt mehr als jeder Ortsname, und in
           Japan oder New York ist es oft das Einzige, was den Ort verraet. Das Kreuz
           kommt auch dann, wenn fuer den Namen kein Platz mehr ist. */
        if ($airport !== null && ($airport['ref'] ?? '') !== '' && $airport['km'] <= FLIGHT_MAP_NEAR_KM) {
            $q = geo_project($view, (float) $airport['lat'], (float) $airport['lon']);
            if ($q[0] >= 0 && $q[0] < GEO_W && $q[1] >= 0 && $q[1] < GEO_H) {
                $texte[] = ['art' => 'platz', 'x' => $q[0], 'y' => $q[1]];
                $setzen($q[0], $q[1], (string) $airport['ref'], C_ACCENT);
            }
        }
        foreach (geomap_places($view, (int) $band['orte']) as $ort) {
            if ($setzen($ort['x'], $ort['y'], $ort['name'], C_WHITE)) {
                $texte[] = ['art' => 'punkt', 'x' => $ort['x'], 'y' => $ort['y']];
            }
        }
    }

    /* Massstab: Balken auf eine runde Zahl. Er kommt nach den Namen, denn die tragen
       mehr. Passt er unten links nicht, versucht er es unten rechts, sonst faellt er weg. */
    $kmLed = geo_km_per_led($view);
    $stufen = [];
    foreach ([1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2500] as $stufe) {
        if ($stufe / $kmLed <= 46) {
            $stufen[] = $stufe;
        }
    }
    /* Erst alle Groessen in den vier Ecken durchgehen, danach erst mitten in die Zeile.
       Ein kurzer Balken in der Ecke sieht gewollt aus, ein langer in der Mitte nicht.
       Ganz weglassen ist die letzte Wahl: ohne ihn sieht man nicht, ob das Bild drei
       Kilometer zeigt oder dreihundert. */
    $gesetzt = false;
    foreach ([true, false] as $nurEcken) {
        foreach (array_reverse($stufen) as $balken) {
            $bw = max(6, (int) round($balken / $kmLed));
            $skalaText = $balken . 'KM';
            $skalaBreite = $bw + 4 + panel_width($skalaText);
            $plaetze = [[2, GEO_H - 8], [GEO_W - 2 - $skalaBreite, GEO_H - 8], [2, 1], [GEO_W - 2 - $skalaBreite, 1]];
            if (!$nurEcken) {
                $plaetze = [];
                foreach ([GEO_H - 8, 1] as $sy) {
                    for ($sx = 10; $sx + $skalaBreite < GEO_W - 2; $sx += 8) {
                        $plaetze[] = [$sx, $sy];
                    }
                }
            }
            foreach ($plaetze as [$sx, $sy]) {
                if ($sx < 1 || !$frei($sx, $sy, $skalaBreite, 8)) {
                    continue;
                }
                $unten = $sy + 7;
                $texte[] = ['art' => 'rect', 'x' => $sx + 1, 'y' => $unten - 3, 'w' => $bw, 'h' => 1, 'c' => C_DIM];
                $texte[] = ['art' => 'rect', 'x' => $sx + 1, 'y' => $unten - 4, 'w' => 1, 'h' => 3, 'c' => C_DIM];
                $texte[] = ['art' => 'rect', 'x' => $sx + $bw, 'y' => $unten - 4, 'w' => 1, 'h' => 3, 'c' => C_DIM];
                $texte[] = ['art' => 'text', 'x' => $sx + $bw + 4, 'y' => $unten - 6, 's' => $skalaText, 'c' => C_DIM];
                $gesetzt = true;
                break;
            }
            if ($gesetzt) {
                break;
            }
        }
        if ($gesetzt) {
            break;
        }
    }

    /* Freistellen: jede belegte Flaeche im Bitmap loeschen, ein Pixel Rand ringsum. */
    foreach ($belegt as [$bx0, $by0, $bx1, $by1]) {
        for ($y = $by0 - 1; $y <= $by1 + 1; $y++) {
            for ($x = $bx0 - 1; $x <= $bx1 + 1; $x++) {
                if ($x >= 0 && $y >= 0 && $x < GEO_W && $y < GEO_H) {
                    $img['px'][$y * GEO_W + $x] = 0;
                }
            }
        }
    }

    $ops = [geomap_op($img['px'])];
    foreach ($texte as $t) {
        if ($t['art'] === 'text') {
            $ops[] = op_text($t['x'], $t['y'], $t['s'], $t['c']);
        } elseif ($t['art'] === 'rect') {
            $ops[] = op_rect($t['x'], $t['y'], $t['w'], $t['h'], $t['c']);
        } elseif ($t['art'] === 'platz') {
            $ops[] = op_rect($t['x'] - 1, $t['y'], 3, 1, C_ACCENT);
            $ops[] = op_rect($t['x'], $t['y'] - 1, 1, 3, C_ACCENT);
        } else {
            $ops[] = op_rect($t['x'], $t['y'], 1, 1, C_WHITE);
        }
    }

    /* Das Flugzeug: Position jetzt, dazu Kurs und Geschwindigkeit in LEDs je Sekunde. */
    $q = $qAc;
    $vx = 0.0;
    $vy = 0.0;
    if ($ac['gs'] !== null && $ac['track'] !== null) {
        $kmProSekunde = (float) $ac['gs'] * 1.852 / 3600;
        $ledProKm = 1.0 / max(1e-6, geo_km_per_led($view));
        $vx = round(sin(deg2rad((float) $ac['track'])) * $kmProSekunde * $ledProKm, 3);
        $vy = round(-cos(deg2rad((float) $ac['track'])) * $kmProSekunde * $ledProKm, 3);
    }
    $ops[] = ['t' => 'dot', 'x' => $q[0], 'y' => $q[1], 'vx' => $vx, 'vy' => $vy, 'c' => C_ACCENT, 't0' => $now * 1000];

    /* Trennlinie und Textzeile. */
    $ops[] = op_rect(0, GEO_H + 1, GEO_W, 1, C_LINE);
    $f = $ctx['settings']['flight'];
    $links = $ac['callsign'] !== '' ? $ac['callsign'] : ($ac['reg'] !== '' ? $ac['reg'] : strtoupper((string) $ac['hex']));
    if ($kind === 'nah') {
        $hoehe = flight_alt_text($ac, (string) ($f['ualt'] ?? 'ft'));
        $steig = flight_vrate_text($ac['vrate'], (string) ($f['uvr'] ?? 'ms'));
        $wahl = [$hoehe . ' ' . $steig];
        // Ohne Luecke nur mit Vorzeichen: aus 3.5KFT und 0M/S wuerde sonst 3.5KFT0M/S.
        if (str_starts_with($steig, '+') || str_starts_with($steig, '-')) {
            $wahl[] = $hoehe . $steig;
        }
        $wahl[] = $hoehe;
    } else {
        $p = flight_progress($ac, $route);
        $wahl = [$p['left'] !== null ? flight_span($p['left']) : flight_dist_text((float) $ac['dst'], (string) ($f['udist'] ?? 'nm'))];
    }
    [$links, $rechts] = flight_map_footer(panel_text($links), $wahl);
    $ops[] = op_text(GEO_W - 2 - panel_width($rechts), 56, $rechts, $kind === 'nah' ? C_GREEN : C_ACCENT);
    $ops[] = op_text(2, 56, $links, C_WHITE);
    return $ops;
}

/**
 * Fusszeile der Karte: links das Rufzeichen, rechts die Messwerte, dazwischen mindestens
 * 5 LEDs. Das Rufzeichen wird nie gekuerzt, aus KLM1234 wuerde sonst KLM123, eine andere
 * Flugnummer. Passt es nicht, nimmt die Fusszeile die naechste, kuerzere Fassung von rechts
 * (Fehlerliste 24.09.2026, P1.5). Liefert [links, rechts].
 */
function flight_map_footer(string $links, array $wahl): array
{
    $wahl = array_values(array_map(static fn($r): string => trim((string) $r), $wahl));
    foreach ($wahl as $r) {
        if (panel_width($links) + 5 + panel_width($r) <= GEO_W - 4) {
            return [$links, $r];
        }
    }
    // Nur ein ungewoehnlich langes Kennzeichen landet hier, dann wird doch links gekuerzt.
    $r = $wahl ? $wahl[count($wahl) - 1] : '';
    $platz = (int) floor((GEO_W - 4 - 5 - panel_width($r) + 1) / 6);
    return [mb_substr($links, 0, max(1, $platz)), $r];
}
