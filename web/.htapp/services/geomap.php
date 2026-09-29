<?php
/*
 * Karte fuer das Panel. Rechnet aus den Geodaten in data/geo-* ein Bitmap von
 * 128 x 51 Punkten, das die Firmware nur noch hinlegen muss. Text und das Flugzeug
 * kommen als gewoehnliche Zeichenbefehle dazu, damit die Schrift aus der Firmware
 * kommt und der Punkt zwischen zwei Abrufen weiterlaufen kann.
 *
 * Entwurf: design/Karte.dc.html, Zahlen und Regeln in design/karte/ANTWORT.md.
 * Vier Baender nach Kilometern je LED entscheiden, welche Ebene ueberhaupt erscheint,
 * dazu die Regel: eine Linie muss im Bild mindestens 16 LEDs weit reichen.
 *
 * Die teure Arbeit (Datei lesen, projizieren, zeichnen) liegt im Zwischenspeicher,
 * Schluessel ist der Ausschnitt. Die Spur des Flugzeugs kommt danach obendrauf,
 * sie aendert sich mit jedem Abruf.
 *
 * Die Daten kommen aus vier Quellen, je nach Zoomstufe:
 *
 *   geo-world.json    Kueste und Grenzen der Welt, grob (1:110 Mio), fuer die Uebersicht
 *   geo-detail.bin    fein (1:10 Mio): Landflaechen, Seen, Stadtgebiete, Grenzen,
 *                     Fluesse, Start- und Landebahnen, in Kacheln zu fuenf Grad
 *   geo-near.json     Luxemburg aus OpenStreetMap, noch feiner, mit Autobahnen
 *   geo-places.json   Ortsnamen der Welt
 *
 * Im Nahbereich zeichnen die Kacheln, in der Uebersicht die grobe Datei. Beides
 * zusammen gaebe doppelte Kuestenlinien, ein paar Punkte gegeneinander versetzt.
 *
 * Wasser und Stadtgebiet sind Flaechen, keine Umrisse. Gezeichnet werden sie als
 * Punktraster auf jedem zweiten Punkt, Wasser auf den geraden, Stadt auf den ungeraden.
 * Ein Umriss allein sieht auf 128 mal 51 Punkten aus wie hingekritzelt; die Flaeche
 * zeigt auf einen Blick, wo die Bucht ist und wo die Stadt.
 */

declare(strict_types=1);

const GEO_W = 128;
const GEO_H = 51;                 // darunter Trennlinie und eine Textzeile
/* Mindestausdehnung im Bild, je Ebene. Der Entwurf nennt 16 LEDs fuer alles; fuer
   Grenzen ist das zu streng, dann fehlen ganze Laender. Grenzen und Kuesten tragen die
   Orientierung, Fluesse und Strassen sind Beiwerk und duerfen strenger gefiltert sein. */
const GEO_MIN_LED = 16;
const GEO_MIN_GRENZE = 5;
const GEO_MIN_KUESTE = 6;
const GEO_MIN_FLUSS = 12;
const GEO_MIN_STRASSE = 10;
/* Mehr Kacheln beruehrt ein Ausschnitt nie, den die Baender 'nah' und 'umfeld' zeigen.
   Die Schranke ist trotzdem da: ohne sie koennte ein kaputter Ausschnitt die ganze Welt
   auspacken wollen. */
const GEO_TILES_MAX = 12;
/* Farben des Bitmaps. Index 0 ist durchsichtig, der Rest steht in der Palette. */
const GEO_COLOURS = [
    'wasser' => '17606F',
    'chrom' => '4E5A63',
    'weiss' => 'F2F4F5',
    'spur' => '7A5200',
    'spurhell' => 'FFAA00',
    'geflogen' => '3DE07C',
    'rest' => '7A5200',
    'stadt' => '3B444B',
];

/** Baender nach Kilometern je LED, wie im Entwurf. */
function geo_band(float $kmProLed): array
{
    if ($kmProLed < 0.25) {
        return ['id' => 'nah', 'detail' => true, 'kueste' => true, 'grenze' => true, 'fluss' => true, 'see' => true, 'stadt' => true, 'autobahn' => true, 'bahn' => true, 'orte' => 2];
    }
    if ($kmProLed < 1.0) {
        return ['id' => 'umfeld', 'detail' => true, 'kueste' => true, 'grenze' => true, 'fluss' => true, 'see' => true, 'stadt' => true, 'autobahn' => false, 'bahn' => true, 'orte' => 2];
    }
    if ($kmProLed < 5.0) {
        return ['id' => 'land', 'detail' => true, 'kueste' => true, 'grenze' => true, 'fluss' => false, 'see' => false, 'stadt' => false, 'autobahn' => false, 'bahn' => false, 'orte' => 2];
    }
    return ['id' => 'welt', 'detail' => false, 'kueste' => true, 'grenze' => true, 'fluss' => false, 'see' => false, 'stadt' => false, 'autobahn' => false, 'bahn' => false, 'orte' => 0];
}

function geo_data(string $which): array
{
    static $cache = [];
    if (isset($cache[$which])) {
        return $cache[$which];
    }
    $file = __DIR__ . '/../data/geo-' . $which . '.json';
    $raw = is_readable($file) ? file_get_contents($file) : false;
    $data = $raw === false ? [] : (json_decode($raw, true) ?: []);
    $cache[$which] = $data;
    return $data;
}

/** Inhaltsverzeichnis der Weltkacheln: Kachel -> [Versatz, Laenge] in geo-detail.bin. */
function geo_detail_idx(): array
{
    static $idx = null;
    if ($idx === null) {
        $file = __DIR__ . '/../data/geo-detail.idx';
        $raw = is_readable($file) ? file_get_contents($file) : false;
        $idx = ($raw === false ? null : json_decode($raw, true)) ?: ['grad' => 5, 'k' => []];
    }
    return $idx;
}

/**
 * Eine Kachel, ausgepackt. Gelesen wird nur ihr Stueck aus der Datei: alles zusammen
 * sind 5 MB, und die will kein Abruf auspacken. Leeres Feld, wenn es sie nicht gibt,
 * dann zeichnet das Band eben die grobe Weltdatei.
 */
function geo_tile(string $key): array
{
    static $cache = [];
    static $fh = null;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $pos = geo_detail_idx()['k'][$key] ?? null;
    $out = [];
    // Ohne zlib gibt es keine Kacheln, dafuer die grobe Weltdatei. Lieber eine magere
    // Karte als ein Fehler mitten im Abruf.
    if (is_array($pos) && count($pos) === 2 && function_exists('gzdecode')) {
        if ($fh === null) {
            $file = __DIR__ . '/../data/geo-detail.bin';
            $fh = is_readable($file) ? fopen($file, 'rb') : false;
        }
        if ($fh !== false && fseek($fh, (int) $pos[0]) === 0) {
            $blob = fread($fh, (int) $pos[1]);
            $json = is_string($blob) ? @gzdecode($blob) : false;
            if (is_string($json)) {
                $out = json_decode($json, true) ?: [];
            }
        }
    }
    if (count($cache) > GEO_TILES_MAX) {
        $cache = [];
    }
    $cache[$key] = $out;
    return $out;
}

/** Fenster des Ausschnitts in Grad: [sued, west, nord, ost]. null bei der Streckenansicht. */
function geo_view_bbox(array $view): ?array
{
    if (($view['art'] ?? 'nah') !== 'nah') {
        return null;
    }
    $halbX = (float) $view['km'] / 2;
    $halbY = $halbX * (GEO_H / GEO_W);
    $dLat = $halbY / 111.32;
    $dLon = $halbX / max(1e-6, 111.32 * cos(deg2rad((float) $view['lat'])));
    return [
        (float) $view['lat'] - $dLat, (float) $view['lon'] - $dLon,
        (float) $view['lat'] + $dLat, (float) $view['lon'] + $dLon,
    ];
}

/** Die Weltkacheln unter diesem Ausschnitt, Schicht fuer Schicht zusammengelegt. */
function geo_detail(array $view): array
{
    $bb = geo_view_bbox($view);
    if ($bb === null) {
        return [];
    }
    $g = max(1, (int) (geo_detail_idx()['grad'] ?? 5));
    $out = [];
    $n = 0;
    for ($la = (int) floor($bb[0] / $g) * $g; $la <= (int) floor($bb[2] / $g) * $g; $la += $g) {
        for ($lo = (int) floor($bb[1] / $g) * $g; $lo <= (int) floor($bb[3] / $g) * $g; $lo += $g) {
            if (++$n > GEO_TILES_MAX) {
                break 2;
            }
            // Um 180 Grad herum weiterzaehlen, sonst faellt die Kachel hinter der Datumsgrenze weg.
            $lw = (int) (fmod(fmod($lo + 180, 360) + 360, 360) - 180);
            $box = [$la, $lw, $la + $g, $lw + $g];
            foreach (geo_tile($la . ',' . $lw) as $schicht => $liste) {
                if ($schicht === 'land' || $schicht === 'see' || $schicht === 'stadt') {
                    /* Flaechen merken sich, aus welcher Kachel sie stammen: der Renderer
                       erkennt daran die Kanten, die nur vom Zuschneiden kommen. */
                    $out[$schicht] ??= [];
                    foreach ($liste as $ring) {
                        $out[$schicht][] = ['p' => $ring, 'b' => $box];
                    }
                } else {
                    $out[$schicht] = isset($out[$schicht]) ? array_merge($out[$schicht], $liste) : $liste;
                }
            }
        }
    }
    return $out;
}

/**
 * Der naechste Flugplatz zu einem Punkt, aus der Weltliste (OurAirports). Alles mit
 * Linienverkehr ist dabei, also auch JFK, Haneda und der Platz auf Spitzbergen.
 */
function geomap_airport_near(float $lat, float $lon, float $maxKm = 60.0): ?array
{
    $dLat = $maxKm / 111.32 + 0.01;
    $dLon = $maxKm / max(1e-6, 111.32 * cos(deg2rad($lat))) + 0.01;
    $best = null;
    foreach (geo_data('airports')['p'] ?? [] as $p) {
        // Erst grob nach Grad aussieben, das spart 6 000 Wurzeln je Bild.
        if (abs($p[0] - $lat) > $dLat || abs($p[1] - $lon) > $dLon) {
            continue;
        }
        $km = geo_nm($lat, $lon, (float) $p[0], (float) $p[1]) * 1.852;
        if ($km <= $maxKm && ($best === null || $km < $best['km'])) {
            $best = [
                'lat' => (float) $p[0], 'lon' => (float) $p[1], 'km' => $km,
                'ref' => (string) $p[2], 'icao' => (string) $p[3], 'ort' => (string) $p[4],
            ];
        }
    }
    return $best;
}

/**
 * Ein Bild im Aufbau: Punkte als Farbnummern, dazu die Projektion.
 * Nah: gleichmaessig in Kilometern um die Mitte. Strecke: Mercator, der Ausschnitt
 * kommt von aussen.
 */
function geo_canvas(array $view): array
{
    $px = array_fill(0, GEO_W * GEO_H, 0);
    return ['px' => $px, 'view' => $view];
}

function geo_project(array $view, float $lat, float $lon): array
{
    if (($view['art'] ?? 'nah') === 'strecke') {
        $y = log(tan(M_PI / 4 + deg2rad($lat) / 2));
        return [
            (int) round(GEO_W / 2 + (($lon - $view['lon']) * M_PI / 180 - 0) * $view['s']),
            (int) round(GEO_H / 2 - ($y - $view['ymid']) * $view['s']),
        ];
    }
    $kmLat = 111.32;
    $kmLon = 111.32 * cos(deg2rad($view['lat']));
    $s = GEO_W / $view['km'];
    return [
        (int) round(GEO_W / 2 + ($lon - $view['lon']) * $kmLon * $s),
        (int) round(GEO_H / 2 - ($lat - $view['lat']) * $kmLat * $s),
    ];
}

/** Kilometer je LED im aktuellen Ausschnitt. */
/** Dieselbe Projektion ohne Runden. Die Flaechenfuellung braucht Zwischenwerte. */
function geo_project_f(array $view, float $lat, float $lon): array
{
    if (($view['art'] ?? 'nah') === 'strecke') {
        $y = log(tan(M_PI / 4 + deg2rad($lat) / 2));
        return [
            GEO_W / 2 + ($lon - $view['lon']) * M_PI / 180 * $view['s'],
            GEO_H / 2 - ($y - $view['ymid']) * $view['s'],
        ];
    }
    $s = GEO_W / $view['km'];
    return [
        GEO_W / 2 + ($lon - $view['lon']) * 111.32 * cos(deg2rad($view['lat'])) * $s,
        GEO_H / 2 - ($lat - $view['lat']) * 111.32 * $s,
    ];
}

/** Kilometer je LED im aktuellen Ausschnitt. */
function geo_km_per_led(array $view): float
{
    if (($view['art'] ?? 'nah') === 'strecke') {
        /* Mercator: eine Einheit in x ist ein Bogenmass Laenge, also 6371 km mal dem
           Kosinus der Breite. Gerechnet wird mit der mittleren Breite des Ausschnitts. */
        return 6371.0 * cos(deg2rad((float) $view['lat'])) / max(1e-9, (float) $view['s']);
    }
    return $view['km'] / GEO_W;
}

function geo_set(array &$img, int $x, int $y, int $c, bool $stark = false): void
{
    if ($x < 0 || $y < 0 || $x >= GEO_W || $y >= GEO_H) {
        return;
    }
    $i = $y * GEO_W + $x;
    if (!$stark && $img['px'][$i] !== 0) {
        return;
    }
    $img['px'][$i] = $c;
}

function geo_line(array &$img, int $x0, int $y0, int $x1, int $y1, int $c, bool $stark = false): void
{
    $dx = abs($x1 - $x0);
    $dy = abs($y1 - $y0);
    $sx = $x0 < $x1 ? 1 : -1;
    $sy = $y0 < $y1 ? 1 : -1;
    $err = $dx - $dy;
    $schritte = 0;
    for (;;) {
        geo_set($img, $x0, $y0, $c, $stark);
        if (($x0 === $x1 && $y0 === $y1) || ++$schritte > 4096) {
            return;
        }
        $e2 = 2 * $err;
        if ($e2 > -$dy) {
            $err -= $dy;
            $x0 += $sx;
        }
        if ($e2 < $dx) {
            $err += $dx;
            $y0 += $sy;
        }
    }
}

/**
 * Einen Linienzug zeichnen. Zu kurze Zuege fallen weg: was im Bild weniger als
 * GEO_MIN_LED misst, ist Rauschen. Punkte weit ausserhalb werden uebersprungen,
 * damit Bresenham nicht quer durch das Bild laeuft.
 */
function geo_polyline(array &$img, array $view, array $pts, int $c, int $minLed = GEO_MIN_LED, bool $stark = false): void
{
    $n = count($pts);
    if ($n < 2) {
        return;
    }
    $proj = [];
    $x0 = PHP_INT_MAX;
    $y0 = PHP_INT_MAX;
    $x1 = -PHP_INT_MAX;
    $y1 = -PHP_INT_MAX;
    foreach ($pts as $p) {
        $q = geo_project($view, (float) $p[0], (float) $p[1]);
        $proj[] = $q;
        $x0 = min($x0, $q[0]);
        $y0 = min($y0, $q[1]);
        $x1 = max($x1, $q[0]);
        $y1 = max($y1, $q[1]);
    }
    if ($x1 < -GEO_W || $x0 > 2 * GEO_W || $y1 < -GEO_H || $y0 > 2 * GEO_H) {
        return;
    }
    if ($minLed > 0 && max($x1 - $x0, $y1 - $y0) < $minLed) {
        return;
    }
    $prev = null;
    foreach ($proj as $q) {
        if ($prev !== null) {
            $weit = max(abs($q[0] - $prev[0]), abs($q[1] - $prev[1]));
            $drin = ($q[0] > -GEO_W && $q[0] < 2 * GEO_W && $q[1] > -GEO_H && $q[1] < 2 * GEO_H)
                || ($prev[0] > -GEO_W && $prev[0] < 2 * GEO_W && $prev[1] > -GEO_H && $prev[1] < 2 * GEO_H);
            if ($drin && $weit < 4 * GEO_W) {
                geo_line($img, $prev[0], $prev[1], $q[0], $q[1], $c, $stark);
            }
        }
        $prev = $q;
    }
}

/**
 * Fuer jede Bildzeile die Abschnitte, die innerhalb der Ringe liegen, nach der
 * Gerade-ungerade-Regel. Die Kanten werden einmal projiziert und nach Zeilen
 * einsortiert: sonst liefe jede der 51 Zeilen ueber alle Kanten, und eine Kueste
 * bringt davon ein paar tausend mit.
 */
function geo_spans(array $view, array $ringe): array
{
    $eimer = array_fill(0, GEO_H, []);
    foreach ($ringe as $r) {
        $pts = $r['p'] ?? $r;
        $n = is_array($pts) ? count($pts) : 0;
        if ($n < 3) {
            continue;
        }
        $proj = [];
        foreach ($pts as $p) {
            $proj[] = geo_project_f($view, (float) $p[0], (float) $p[1]);
        }
        for ($i = 0; $i < $n; $i++) {
            $a = $proj[$i];
            $b = $proj[($i + 1) % $n];
            $y0 = max(0, (int) ceil(min($a[1], $b[1])));
            $y1 = min(GEO_H - 1, (int) ceil(max($a[1], $b[1])) - 1);
            if ($y0 > $y1) {
                continue;
            }
            $m = ($b[0] - $a[0]) / ($b[1] - $a[1]);
            for ($y = $y0; $y <= $y1; $y++) {
                $eimer[$y][] = $a[0] + ($y - $a[1]) * $m;
            }
        }
    }
    foreach ($eimer as $y => $xs) {
        if (count($xs) < 2) {
            $eimer[$y] = [];
            continue;
        }
        sort($xs);
        $zeile = [];
        for ($i = 0; $i + 1 < count($xs); $i += 2) {
            $zeile[] = [$xs[$i], $xs[$i + 1]];
        }
        $eimer[$y] = $zeile;
    }
    return $eimer;
}

/**
 * Eine Flaeche als Punktraster fuellen, jeder zweite Punkt in beide Richtungen.
 * $phase 0 oder 1 waehlt das Gitter, damit Wasser und Stadt nie auf denselben Punkt
 * fallen. Mit $aussen wird gefuellt, was ausserhalb liegt: so entsteht das Meer aus
 * den Landflaechen, ohne dass der Ozean als eigene Flaeche gespeichert werden muss.
 */
function geo_fill(array &$img, array $spans, int $c, int $phase, bool $aussen = false): void
{
    for ($y = $phase; $y < GEO_H; $y += 2) {
        $zeile = $spans[$y] ?? [];
        if ($zeile === [] && !$aussen) {
            continue;
        }
        for ($x = $phase; $x < GEO_W; $x += 2) {
            $drin = false;
            foreach ($zeile as $s) {
                if ($x >= $s[0] && $x <= $s[1]) {
                    $drin = true;
                    break;
                }
            }
            if ($drin !== $aussen) {
                geo_set($img, $x, $y, $c);
            }
        }
    }
}

/** Liegen beide Punkte auf derselben Kante der Kachel? Dann ist die Kante kein Ufer. */
function geo_on_edge(array $a, array $b, array $box): bool
{
    $e = 1e-6;
    return (abs($a[0] - $box[0]) < $e && abs($b[0] - $box[0]) < $e)
        || (abs($a[0] - $box[2]) < $e && abs($b[0] - $box[2]) < $e)
        || (abs($a[1] - $box[1]) < $e && abs($b[1] - $box[1]) < $e)
        || (abs($a[1] - $box[3]) < $e && abs($b[1] - $box[3]) < $e);
}

/**
 * Die Umrisse geschlossener Ringe zeichnen, ohne die Kanten, die beim Zuschneiden auf
 * der Kachelgrenze entstanden sind. Die sind nicht Ufer, sondern nur der Rand der
 * Kachel, und als Linie quer durchs Bild waeren sie das Auffaelligste daran.
 */
function geo_rings(array &$img, array $view, array $ringe, int $c): void
{
    foreach ($ringe as $r) {
        $pts = $r['p'] ?? $r;
        $box = $r['b'] ?? null;
        $n = is_array($pts) ? count($pts) : 0;
        if ($n < 3) {
            continue;
        }
        $proj = [];
        foreach ($pts as $p) {
            $proj[] = geo_project($view, (float) $p[0], (float) $p[1]);
        }
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            if ($box !== null && geo_on_edge($pts[$i], $pts[$j], $box)) {
                continue;
            }
            [$ax, $ay] = $proj[$i];
            [$bx, $by] = $proj[$j];
            if ((max($ax, $bx) < 0 || min($ax, $bx) > GEO_W) && (max($ay, $by) < 0 || min($ay, $by) > GEO_H)) {
                continue;
            }
            if (max($ax, $bx) < -GEO_W || min($ax, $bx) > 2 * GEO_W || max($ay, $by) < -GEO_H || min($ay, $by) > 2 * GEO_H) {
                continue;
            }
            geo_line($img, $ax, $ay, $bx, $by, $c);
        }
    }
}

/** Die Geografie eines Ausschnitts, ohne Spur und ohne Flugzeug. */
function geomap_geography(array $view): array
{
    $band = geo_band(geo_km_per_led($view));
    $img = geo_canvas($view);
    $welt = geo_data('world');
    $lux = geo_data('near');
    $fein = !empty($band['detail']) ? geo_detail($view) : [];
    $farben = array_keys(GEO_COLOURS);
    $idx = static fn(string $name): int => (int) array_search($name, $farben, true) + 1;

    /* Woher die Linien kommen. Im Nahbereich die Kacheln, sonst die grobe Weltdatei.
       Gibt es fuer diesen Fleck gar keine Kachel, bleibt die grobe Datei stehen.
       Fluesse, Seen und Bahnen liefert fuer Luxemburg OpenStreetMap, das ist feiner
       als Natural Earth; ueberall sonst die Kacheln. */
    $osm = geomap_has_near((float) ($view['lat'] ?? 0), (float) ($view['lon'] ?? 0));
    $quelle = static fn(string $schicht): array => $fein === [] ? ($welt[$schicht] ?? []) : ($fein[$schicht] ?? []);
    $nahQuelle = static fn(string $schicht): array => $osm && !empty($lux[$schicht])
        ? $lux[$schicht] : ($fein[$schicht] ?? []);

    /* Reihenfolge: erst alle Linien, danach das Punktraster. Gesetzt wird nur, was noch
       frei ist, also gewinnt jede Linie gegen die Fuellung und das Raster schliesst nur
       die Luecken. Umgekehrt loeschte das Meer die Kueste wieder weg. */
    if ($band['grenze']) {
        foreach ($quelle('grenze') as $l) {
            geo_polyline($img, $view, $l, $idx('chrom'), GEO_MIN_GRENZE);
        }
    }
    if ($band['kueste']) {
        if (isset($fein['land'])) {
            geo_rings($img, $view, $fein['land'], $idx('wasser'));
        } else {
            foreach ($welt['kueste'] ?? [] as $l) {
                geo_polyline($img, $view, $l, $idx('wasser'), GEO_MIN_KUESTE);
            }
        }
    }
    if ($band['autobahn']) {
        foreach ($lux['autobahn'] ?? [] as $l) {
            geo_polyline($img, $view, $l, $idx('chrom'), GEO_MIN_STRASSE);
        }
    }
    if ($band['see']) {
        if ($osm && !empty($lux['see'])) {
            foreach ($lux['see'] as $l) {
                geo_polyline($img, $view, $l, $idx('wasser'), GEO_MIN_KUESTE);
            }
        } else {
            geo_rings($img, $view, $fein['see'] ?? [], $idx('wasser'));
        }
    }
    if ($band['fluss']) {
        foreach ($nahQuelle('fluss') as $l) {
            geo_polyline($img, $view, $l, $idx('wasser'), GEO_MIN_FLUSS);
        }
    }
    if ($band['bahn']) {
        // Bahnen sind im Nahfeld der Anker, sie gelten ohne Laengenregel und zuoberst.
        foreach ($nahQuelle('bahn') as $b) {
            geo_polyline($img, $view, $b['p'] ?? [], $idx('weiss'), 0, true);
        }
    }
    if (isset($fein['land'])) {
        /* Meer ist alles, was nicht Land ist. Deshalb muss die Kachel vorliegen, auch
           wenn sie leer ist: ohne diesen Unterschied tupfte eine fehlende Datei die
           halbe Karte blau. */
        geo_fill($img, geo_spans($view, $fein['land']), $idx('wasser'), 0, true);
        if ($band['see'] && !empty($fein['see'])) {
            geo_fill($img, geo_spans($view, $fein['see']), $idx('wasser'), 0);
        }
        if (!empty($band['stadt']) && !empty($fein['stadt'])) {
            geo_fill($img, geo_spans($view, $fein['stadt']), $idx('stadt'), 1);
        }
    }
    return ['px' => $img['px'], 'band' => $band];
}

/** Ortsnamen im Bild, die groessten zuerst, mit Platz fuer den Text daneben. */
function geomap_places(array $view, int $max): array
{
    if ($max <= 0) {
        return [];
    }
    /* Erst Luxemburg aus OpenStreetMap, das ist die feinere Liste, dann die Welt nach
       Einwohnern sortiert. Wer zweimal vorkommt, kommt einmal aufs Panel. */
    $liste = [];
    foreach (geo_data('near')['ort'] ?? [] as $o) {
        $liste[] = [(float) $o['la'], (float) $o['lo'], (string) $o['n']];
    }
    foreach (geo_data('places')['o'] ?? [] as $o) {
        $liste[] = [(float) $o[0], (float) $o[1], (string) $o[2]];
    }
    $bb = geo_view_bbox($view);
    $out = [];
    $gesehen = [];
    foreach ($liste as [$la, $lo, $name]) {
        if (count($out) >= $max) {
            break;
        }
        if ($bb !== null && ($la < $bb[0] || $la > $bb[2] || $lo < $bb[1] || $lo > $bb[3])) {
            continue;
        }
        $kurz = geomap_short($name);
        if (isset($gesehen[$kurz])) {
            continue;
        }
        [$x, $y] = geo_project($view, $la, $lo);
        if ($x < 2 || $x > GEO_W - 3 || $y < 2 || $y > GEO_H - 3) {
            continue;
        }
        $gesehen[$kurz] = true;
        $out[] = ['x' => $x, 'y' => $y, 'name' => $kurz];
    }
    return $out;
}

/** Kurzform eines Ortsnamens: neun Zeichen, ein Schnitt bekommt einen Punkt. */
function geomap_short(string $name): string
{
    static $kurz = [
        'LUXEMBOURG' => 'LUXEMBURG', 'ESCH-SUR-ALZETTE' => 'ESCH', 'GREVENMACHER' => 'GREVENM.',
        'SAARBRUCKEN' => 'SAARBR.', 'SAINT-AVOLD' => 'ST-AVOLD', 'THIONVILLE' => 'THIONV.',
        'DIFFERDANGE' => 'DIFFERD.', 'SARREGUEMINES' => 'SARREGU.', 'LUXEMBURG FINDEL' => 'LUX',
    ];
    $n = $kurz[$name] ?? $name;
    if (mb_strlen($n) > 9) {
        $teil = preg_split('/[ \-]/', $n)[0] ?? $n;
        $n = mb_substr($teil, 0, 8) . '.';
    }
    return $n;
}

/** Liegt der Punkt im Fenster, fuer das es Nahdaten gibt? */
function geomap_has_near(float $lat, float $lon): bool
{
    $b = geo_data('near')['bbox'] ?? null;
    return is_array($b) && count($b) === 4
        && $lat >= $b[0] - 0.2 && $lat <= $b[2] + 0.2 && $lon >= $b[1] - 0.3 && $lon <= $b[3] + 0.3;
}

/** Das fertige Bitmap als Zeichenbefehl: vier Bit je Punkt, Palette daneben. */
function geomap_op(array $px): array
{
    $bytes = '';
    $n = count($px);
    for ($i = 0; $i < $n; $i += 2) {
        $hi = $px[$i] & 0x0F;
        $lo = ($i + 1 < $n ? $px[$i + 1] : 0) & 0x0F;
        $bytes .= chr(($hi << 4) | $lo);
    }
    return [
        't' => 'bmp',
        'x' => 0,
        'y' => 0,
        'w' => GEO_W,
        'h' => GEO_H,
        'p' => array_values(GEO_COLOURS),
        'd' => base64_encode($bytes),
    ];
}

/** Farbnummer eines Namens aus GEO_COLOURS. */
function geomap_colour(string $name): int
{
    $i = array_search($name, array_keys(GEO_COLOURS), true);
    return $i === false ? 0 : (int) $i + 1;
}
