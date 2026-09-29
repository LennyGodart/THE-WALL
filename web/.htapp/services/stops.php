<?php
/*
 * Alle Haltestellen Luxemburgs fuer die Haltestellenkarte der Geraeteseite.
 *
 * Die ATP bietet nur location.nearbystops und departureBoard, eine Suche nach Namen
 * gibt es nicht. Deshalb fragt der Server das Land einmal im Monat in einem Raster
 * ab: Kreise mit 10 km Radius im Abstand von 14 km (jeder Punkt liegt in einem
 * Kreis), dazu bis zu 5 000 Treffer je Abfrage, das Maximum laut WADL. Das sind
 * etwa 35 Abfragen. Liefert ein Kreis 5 000 Treffer oder liegt sein weitester
 * Treffer auffaellig nah an der Mitte (der Dienst koennte den Radius begrenzen),
 * wird er in vier Kreise mit halbem Radius geteilt.
 *
 * Die Arbeit laeuft in Stuecken von drei Abfragen je Aufruf, angestossen von der
 * Karte selbst, und nur, solange in der laufenden Stunde weniger als 300 Abfragen
 * verbraucht sind: die Tafeln der Geraete gehen vor. Ergebnis in .htdata/transit:
 *   stops-index.json   alle Haltestellen mit Linien, fuer die Auswahl
 *   stops-map.json     nur ID, Name, Lage und Verkehrsmittel, fuer die Karte
 *   stops-build.json   Zwischenstand, solange das Raster laeuft
 * Der alte Stand bleibt nutzbar, bis ein neuer fertig ist.
 */

declare(strict_types=1);

/* Luxemburg mit gut 5 km Rand fuer grenznahe Haltestellen: Sued, West, Nord, Ost. */
const STOPS_BOX = [49.40, 5.70, 50.22, 6.56];
const STOPS_RADIUS = 10000;
const STOPS_MIN_RADIUS = 1250;
const STOPS_MAXNO = 5000;
const STOPS_TTL = 30 * 86400;
const STOPS_CHUNK = 3;
const STOPS_HOUR_ROOM = 300;
const STOPS_DAY_ROOM = 4000;
const STOPS_MODE_BITS = ['train' => 1, 'tram' => 2, 'bus' => 4];

function stops_dir(): string
{
    return TW_DATA . '/transit';
}

function stops_read(string $name): ?array
{
    $p = stops_dir() . '/' . $name;
    if (!is_file($p)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($p), true);
    return is_array($data) ? $data : null;
}

function stops_write(string $name, array $data): void
{
    atomic_write(stops_dir() . '/' . $name, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** Raster aus Kreisen: [lat, lon, Radius in m]. Abstand Radius mal Wurzel 2, damit keine Luecke bleibt. */
function stops_grid(): array
{
    [$s, $w, $n, $e] = STOPS_BOX;
    $side = STOPS_RADIUS * M_SQRT2;
    $dLat = $side / 111320;
    $dLon = $side / (111320 * cos(deg2rad(($s + $n) / 2)));
    $cells = [];
    for ($lat = $s + $dLat / 2; $lat - $dLat / 2 < $n; $lat += $dLat) {
        for ($lon = $w + $dLon / 2; $lon - $dLon / 2 < $e; $lon += $dLon) {
            $cells[] = [round($lat, 5), round($lon, 5), STOPS_RADIUS];
        }
    }
    return $cells;
}

/** Vier Kreise mit halbem Radius, die zusammen das Quadrat des alten Kreises abdecken. */
function stops_split(array $cell): array
{
    [$lat, $lon, $r] = $cell;
    $q = $r * M_SQRT2 / 4;
    $dLat = $q / 111320;
    $dLon = $q / (111320 * cos(deg2rad($lat)));
    $out = [];
    foreach ([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$a, $b]) {
        $out[] = [round($lat + $a * $dLat, 5), round($lon + $b * $dLon, 5), (int) round($r / 2)];
    }
    return $out;
}

/**
 * Eintraege einer Abfrage in die Sammlung. Grosse Knoten kommen als Steige und
 * Eingaenge, jeder nennt seine Haltestelle in mainMastExtId. Name und Lage kommen
 * vom Hauptmast selbst, sonst vom ersten Eintrag; Linien und Verkehrsmittel aus allen.
 */
function stops_merge(array &$stops, array $entries): void
{
    foreach ($entries as $entry) {
        $s = is_array($entry) ? ($entry['StopLocation'] ?? $entry) : null;
        if (!is_array($s) || !isset($s['extId'], $s['lat'], $s['lon'])) {
            continue;
        }
        $n = transit_normalize_stop($s);
        $id = (string) ($s['mainMastExtId'] ?? $s['extId']);
        if (!preg_match('/^\d{3,12}$/', $id)) {
            continue;
        }
        $main = (string) $s['extId'] === $id;
        $have = $stops[$id] ?? null;
        if ($have === null || ($main && empty($have['main']))) {
            $lines = $have['l'] ?? [];
            $modes = $have['m'] ?? 0;
            $stops[$id] = ['n' => $n['name'], 'a' => round((float) $s['lat'], 5), 'o' => round((float) $s['lon'], 5), 'm' => $modes, 'l' => $lines, 'main' => $main];
        }
        $m = (int) $stops[$id]['m'];
        foreach ($n['modes'] as $mode) {
            $m |= STOPS_MODE_BITS[$mode] ?? 0;
        }
        $stops[$id]['m'] = $m;
        foreach (transit_stop_codes($n) as $c) {
            $key = $c['mode'] . ':' . $c['code'];
            if (!in_array($key, $stops[$id]['l'], true) && count($stops[$id]['l']) < 60) {
                $stops[$id]['l'][] = $key;
            }
        }
    }
}

/**
 * Ein Stueck Arbeit am Raster, wenn der Stand fehlt oder aelter als 30 Tage ist.
 * Nur ein Aufruf gleichzeitig (Dateisperre), die anderen lesen nur.
 */
function stops_work(): void
{
    $index = stops_read('stops-index.json');
    if ($index !== null && (int) ($index['built_at'] ?? 0) > time() - STOPS_TTL) {
        return;
    }
    if (!transit_configured()) {
        return;
    }
    if (!is_dir(stops_dir())) {
        @mkdir(stops_dir(), 0750, true);
    }
    $fh = @fopen(stops_dir() . '/stops-build.lock', 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
        if ($fh) {
            fclose($fh);
        }
        return;
    }
    try {
        // Eine Antwort mit einigen tausend Steigen ist mehrere MB gross. Hoechstens 20 Sekunden
        // Arbeit je Aufruf, damit nginx (60 s) und Cloudflare (100 s) nicht vorher abbrechen.
        @set_time_limit(90);
        @ini_set('memory_limit', '512M');
        $began = microtime(true);
        $build = stops_read('stops-build.json');
        if ($build === null || !is_array($build['queue'] ?? null)) {
            $grid = stops_grid();
            $build = ['started_at' => time(), 'queue' => $grid, 'done' => 0, 'total' => count($grid), 'stops' => [], 'error' => null];
        }
        for ($i = 0; $i < STOPS_CHUNK && $build['queue'] && microtime(true) - $began < 20; $i++) {
            $q = transit_quota();
            if ($q['hour'] >= STOPS_HOUR_ROOM || $q['day'] >= STOPS_DAY_ROOM) {
                $build['error'] = 'quota';
                break;
            }
            $cell = $build['queue'][0];
            $r = transit_call('location.nearbystops', [
                'originCoordLat' => sprintf('%.5f', $cell[0]),
                'originCoordLong' => sprintf('%.5f', $cell[1]),
                'r' => (int) $cell[2],
                'maxNo' => STOPS_MAXNO,
                'type' => 'SE',
            ], 25);
            if (!$r['ok']) {
                $build['error'] = $r['code'];
                break;
            }
            array_shift($build['queue']);
            $build['done']++;
            $build['error'] = null;
            $entries = transit_list($r['data']['stopLocationOrCoordLocation'] ?? ($r['data']['StopLocation'] ?? []));
            $far = 0;
            foreach ($entries as $entry) {
                $far = max($far, (int) (($entry['StopLocation'] ?? $entry)['dist'] ?? 0));
            }
            $full = count($entries) >= STOPS_MAXNO;
            $capped = count($entries) >= 50 && $far < 0.6 * $cell[2];
            if (($full || $capped) && $cell[2] / 2 >= STOPS_MIN_RADIUS) {
                // Kein Ergebnis dieses Kreises uebernehmen, die vier kleineren fragen neu.
                array_push($build['queue'], ...stops_split($cell));
                $build['total'] += 4;
                continue;
            }
            stops_merge($build['stops'], $entries);
        }
        if (!$build['queue']) {
            $stops = $build['stops'];
            foreach ($stops as &$s) {
                unset($s['main']);
            }
            unset($s);
            stops_write('stops-index.json', ['built_at' => time(), 'queries' => $build['done'], 'stops' => $stops]);
            $map = [];
            foreach ($stops as $id => $s) {
                $map[] = [(string) $id, $s['n'], $s['a'], $s['o'], $s['m']];
            }
            stops_write('stops-map.json', ['built_at' => time(), 'stops' => $map]);
            @unlink(stops_dir() . '/stops-build.json');
            event_add('TRANSIT', 'Stop map ready: ' . count($map) . ' stops from ' . $build['done'] . ' requests', 'Haltestellenkarte fertig: ' . count($map) . ' Haltestellen aus ' . $build['done'] . ' Abfragen');
        } else {
            stops_write('stops-build.json', $build);
        }
    } catch (Throwable $e) {
        log_error('Haltestellen-Raster: ' . $e->getMessage());
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Fuer die Karte: fertige Liste oder der Zwischenstand, mit Fortschritt in Prozent.
 * $work stoesst ein Stueck Arbeit an, wenn noetig.
 */
function stops_for_map(bool $work): array
{
    if ($work) {
        stops_work();
    }
    $map = stops_read('stops-map.json');
    $build = stops_read('stops-build.json');
    $out = ['configured' => transit_configured(), 'ready' => $map !== null, 'building' => $build !== null, 'progress' => 100, 'paused' => false];
    if ($build !== null) {
        $total = max(1, (int) $build['total']);
        $out['progress'] = (int) floor(100 * (int) $build['done'] / $total);
        $out['paused'] = ($build['error'] ?? null) !== null;
        $out['reason'] = $build['error'] ?? null;
    }
    if ($map !== null) {
        $out['built_at'] = (int) $map['built_at'];
        $out['stops'] = $map['stops'];
        return $out;
    }
    // Noch kein fertiger Stand: was das Raster bisher gefunden hat, damit die Karte nicht leer bleibt.
    $out['stops'] = [];
    foreach (($build['stops'] ?? []) as $id => $s) {
        $out['stops'][] = [(string) $id, $s['n'], $s['a'], $s['o'], $s['m']];
    }
    return $out;
}

/** Stand fuer den Admin-Bereich: Anzahl, Abfragen, Zeitpunkt, laufendes Raster mit Fortschritt. */
function stops_status(): array
{
    $map = stops_read('stops-map.json');
    $index = stops_read('stops-index.json');
    $build = stops_read('stops-build.json');
    return [
        'count' => $map !== null ? count($map['stops'] ?? []) : 0,
        'built_at' => $map !== null ? (int) $map['built_at'] : null,
        'queries' => $index !== null ? (int) ($index['queries'] ?? 0) : 0,
        'next_at' => $map !== null ? (int) $map['built_at'] + STOPS_TTL : null,
        'building' => $build !== null,
        'progress' => $build !== null ? (int) floor(100 * (int) $build['done'] / max(1, (int) $build['total'])) : null,
        'reason' => $build['error'] ?? null,
    ];
}

/** Eine Haltestelle fuer die Auswahl, im selben Format wie api_transit_stops(). null, wenn unbekannt. */
function stops_get(string $id): ?array
{
    if (!preg_match('/^\d{3,12}$/', $id)) {
        return null;
    }
    $index = stops_read('stops-index.json');
    $s = $index['stops'][$id] ?? (stops_read('stops-build.json')['stops'][$id] ?? null);
    if (!is_array($s)) {
        return null;
    }
    $modes = [];
    foreach (TRANSIT_MODES as $m) {
        if ((int) $s['m'] & STOPS_MODE_BITS[$m]) {
            $modes[] = $m;
        }
    }
    $lines = [];
    foreach ($s['l'] as $key) {
        [$mode, $code] = explode(':', (string) $key, 2) + [1 => ''];
        if ($code !== '' && in_array($mode, TRANSIT_MODES, true)) {
            $lines[] = ['code' => $code, 'mode' => $mode];
        }
    }
    $name = (string) $s['n'];
    return [
        'id' => $id,
        'name' => $name,
        'short' => transit_short_name(transit_split_name($name) + ['name' => $name]),
        'modes' => $modes,
        'lines' => $lines,
        'lat' => (float) $s['a'],
        'lon' => (float) $s['o'],
    ];
}
