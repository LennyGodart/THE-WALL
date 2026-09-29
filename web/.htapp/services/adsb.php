<?php
/*
 * Flugdaten.
 *   adsb.lol   Flugzeuge im Umkreis, ohne Schluessel.
 *   adsb.fi    dasselbe aus einem zweiten Netz, immer mitgefragt und zusammengelegt.
 *              Zwischenspeicher 10 Sekunden je Standort, damit zwei Geraete im selben
 *              Umkreis eine Abfrage teilen.
 *   VRS        Strecke zu einem Rufzeichen aus den Standdaten von Virtual Radar Server
 *              (gemeinfrei, CC0), stuendlich gespiegelt von adsb.lol als eine Datei je
 *              Rufzeichen. Auch Strecken mit Zwischenlandung. Sechs Stunden gespeichert.
 *   adsbdb     Airline und Route zu einem Rufzeichen. 30 Tage gespeichert, unbekannte
 *              Rufzeichen einen Tag. Die Strecke dort ist oft veraltet, sie gilt nur,
 *              wenn VRS keine passende kennt (modes/flight.php, flight_route()).
 *
 * Keiner der Dienste liefert Flugplaene. Ein Flugzeug erscheint erst, wenn ein
 * Empfaenger es sieht, eine Strecke haengt am Rufzeichen und nicht am Tag, und eine
 * Ankunftszeit ist nur eine Schaetzung.
 *
 * Fehler bleiben kurz gemerkt (adsb.lol 30 Sekunden je Umkreis, adsbdb und VRS eine
 * Minute je Rufzeichen), damit ein Ausfall nicht jeden Abruf zu einer neuen Abfrage macht.
 */

declare(strict_types=1);

/** Hoechstens so viele Flugzeuge gehen an die Karte der Geraeteseite, die naechsten zuerst. */
const ADSB_MAP_MAX = 120;

/* Strecken aus den VRS-Standdaten: sechs Stunden, gefunden oder nicht. Die Quelle wird
   stuendlich neu gebaut, eine Strecke aendert sich aber selten. */
const VRS_TTL = 21600;

/* Antwortet adsb.lol nicht, gilt die letzte brauchbare Liste noch so lange. Eine Minute
   alte Positionen sind besser als ein leerer Himmel: der Dienst bremst mit HTTP 429, wenn
   mehrere Abfragen dicht aufeinander kommen, und das traf sonst jedes Mal den Bildschirm. */
const ADSB_STALE_S = 60;

/** Flugzeuge um einen Punkt, nach Entfernung sortiert. null, wenn gar nichts vorliegt. */
function adsb_nearby(float $lat, float $lon, int $nm): ?array
{
    $nm = max(5, min(150, $nm));
    $key = sprintf('adsb:%.2f:%.2f:%d', $lat, $lon, $nm);
    $letzte = 'adsblast:' . $key;
    // Ein Ausfall bleibt 30 Sekunden gemerkt, sonst fragte jeder Abruf jedes Geraets erneut.
    if (cache_get('adsbfail:' . $key) !== null) {
        return adsb_stale($letzte);
    }
    $bremse = false;
    $list = cache_remember($key, 'adsblol', static function () use ($lat, $lon, $nm, $key, &$bremse): ?array {
        /* Zwei Netze, dieselben Felder, beide ohne Schluessel. Sie werden zusammengelegt,
           nicht abgeloest: jedes hat eigene Empfaenger, und was dem einen fehlt, hat oft
           das andere. Bei Dopplern gilt der erste Treffer, die Liste ist nach Entfernung
           sortiert. Faellt eine Quelle aus, reicht die andere. */
        $quellen = [
            ['adsblol', 'adsb.lol', sprintf('https://api.adsb.lol/v2/point/%.4f/%.4f/%d', $lat, $lon, $nm)],
            ['adsbfi', 'adsb.fi', sprintf('https://opendata.adsb.fi/api/v2/lat/%.4f/lon/%.4f/dist/%d', $lat, $lon, $nm)],
        ];
        $nachHex = [];
        $antworten = 0;
        foreach ($quellen as [$dienst, $name, $url]) {
            // Je 4 Sekunden: beide zusammen bleiben unter den 10 Sekunden, nach denen das Geraet aufgibt.
            $r = http_get_json($dienst, $url, 4);
            if ((int) $r['status'] === 429) {
                $bremse = true;
            }
            // adsb.lol nennt die Liste "ac", adsb.fi beim Umkreis "aircraft". Sonst gleich.
            $roh = $r['data']['ac'] ?? $r['data']['aircraft'] ?? null;
            if (!is_array($roh)) {
                event_add(strtoupper($dienst), 'No usable answer from ' . $name . ' (HTTP ' . $r['status'] . ')', 'Keine brauchbare Antwort von ' . $name . ' (HTTP ' . $r['status'] . ')');
                continue;
            }
            $antworten++;
            foreach ($roh as $x) {
                if (!is_array($x) || !isset($x['lat'], $x['lon'])) {
                    continue;
                }
                $hex = strtolower(trim((string) ($x['hex'] ?? '')));
                if ($hex === '' || isset($nachHex[$hex])) {
                    continue;
                }
                $nachHex[$hex] = adsb_normalise($x, $lat, $lon);
            }
        }
        if ($antworten === 0) {
            return null;
        }
        $list = array_values($nachHex);
        usort($list, static fn(array $x, array $y): int => $x['dst'] <=> $y['dst']);
        // Zehn Sekunden: genau der Takt, in dem das Geraet fragt. Kuerzer hiesse nur, dass
        // jeder Abruf erneut bei den Diensten landet, und die bremsen dann mit 429.
        return [$list, 10];
    });
    if ($list === null) {
        // Keine Quelle hat geantwortet. Hat eine mit 429 gebremst, eine Minute Ruhe, sonst 30 Sekunden.
        cache_put('adsbfail:' . $key, 1, $bremse ? 60 : 30);
        return adsb_stale($letzte);
    }
    cache_put($letzte, ['at' => time(), 'list' => $list], ADSB_STALE_S + 30);
    return $list;
}

/** Die letzte brauchbare Liste, solange sie nicht aelter als ADSB_STALE_S ist. */
function adsb_stale(string $key): ?array
{
    $alt = cache_get($key);
    if (!is_array($alt) || !isset($alt['at'], $alt['list']) || !is_array($alt['list'])) {
        return null;
    }
    return time() - (int) $alt['at'] <= ADSB_STALE_S ? $alt['list'] : null;
}

/** Ein angeheftetes Flugzeug nach Rufzeichen, Flugnummer oder Kennzeichen suchen. */
function adsb_find(string $query, float $lat, float $lon): ?array
{
    $q = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $query) ?? '');
    if ($q === '' || strlen($q) > 12) {
        return null;
    }
    return cache_remember('adsbpin:' . $q, 'adsblol', static function () use ($q, $lat, $lon): ?array {
        $candidates = [$q];
        // Flugnummer wie LG9561: adsbdb kennt das ICAO-Rufzeichen dazu.
        if (preg_match('/^[A-Z0-9]{2}\d{1,4}[A-Z]?$/', $q)) {
            $route = adsbdb_route($q);
            if ($route && !empty($route['callsign_icao'])) {
                array_unshift($candidates, strtoupper($route['callsign_icao']));
            }
        }
        /* Auch hier beide Netze: ein Flug ueber dem Atlantik haengt an wenigen Empfaengern,
           und die speisen nicht beide Netze. Gesucht wird Rufzeichen vor Kennzeichen. */
        $hosts = [['adsblol', 'https://api.adsb.lol/v2'], ['adsbfi', 'https://opendata.adsb.fi/api/v2']];
        $treffer = static function (array $r): ?array {
            $roh = $r['data']['ac'] ?? $r['data']['aircraft'] ?? null;
            return is_array($roh) && !empty($roh[0]) && is_array($roh[0]) ? $roh[0] : null;
        };
        foreach (array_unique($candidates) as $cs) {
            foreach ($hosts as [$dienst, $basis]) {
                $a = $treffer(http_get_json($dienst, $basis . '/callsign/' . rawurlencode($cs), 6));
                if ($a !== null && isset($a['lat'], $a['lon'])) {
                    return [adsb_normalise($a, $lat, $lon), 8];
                }
            }
        }
        foreach ($hosts as [$dienst, $basis]) {
            $a = $treffer(http_get_json($dienst, $basis . '/reg/' . rawurlencode($q), 6));
            if ($a !== null && isset($a['lat'], $a['lon'])) {
                return [adsb_normalise($a, $lat, $lon), 8];
            }
        }
        // Nicht gefunden ist auch ein Ergebnis, sonst fragt jedes Geraet alle 10 Sekunden erneut.
        return [['missing' => true], 20];
    });
}

function adsb_normalise(array $a, float $lat, float $lon): array
{
    $alt = $a['alt_baro'] ?? null;
    $dst = isset($a['dst']) ? (float) $a['dst'] : geo_nm($lat, $lon, (float) $a['lat'], (float) $a['lon']);
    return [
        'hex' => (string) ($a['hex'] ?? ''),
        'callsign' => strtoupper(trim((string) ($a['flight'] ?? ''))),
        'reg' => strtoupper(trim((string) ($a['r'] ?? ''))),
        'type' => strtoupper(trim((string) ($a['t'] ?? ''))),
        'ground' => $alt === 'ground',
        'alt' => is_numeric($alt) ? (int) round((float) $alt) : null,
        'gs' => isset($a['gs']) ? (float) $a['gs'] : null,
        'track' => isset($a['track']) ? (int) round((float) $a['track']) : null,
        'vrate' => isset($a['baro_rate']) ? (int) $a['baro_rate'] : (isset($a['geom_rate']) ? (int) $a['geom_rate'] : null),
        'lat' => (float) $a['lat'],
        'lon' => (float) $a['lon'],
        'dst' => round($dst, 1),
        'military' => ((int) ($a['dbFlags'] ?? 0) & 1) === 1,
        // Wirbelschleppen-Kategorie nach DO-260: A1 leicht bis A5 schwer, A7 Hubschrauber.
        'category' => preg_match('/^[A-D][0-7]$/', (string) ($a['category'] ?? '')) ? (string) $a['category'] : '',
    ];
}

/** Route und Airline zu einem Rufzeichen, aus dem Speicher oder von adsbdb. */
function adsbdb_route(string $callsign): ?array
{
    $cs = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $callsign) ?? '');
    if ($cs === '' || strlen($cs) > 12) {
        return null;
    }
    $row = q1('SELECT data, found, fetched_at FROM callsigns WHERE cs = ?', [$cs]);
    if ($row) {
        $age = time() - (int) $row['fetched_at'];
        $fresh = (int) $row['found'] === 1 ? $age < 30 * 86400 : $age < 86400;
        if ($fresh) {
            budget_add('adsbdb', true);
            return (int) $row['found'] === 1 ? json_decode((string) $row['data'], true) : null;
        }
    }
    // Nach einem Fehler von adsbdb (nicht 404) eine Minute Ruhe fuer dieses Rufzeichen.
    if (cache_get('adsbdbfail:' . $cs) !== null) {
        return $row && (int) $row['found'] === 1 ? json_decode((string) $row['data'], true) : null;
    }
    $r = http_get_json('adsbdb', 'https://api.adsbdb.com/v0/callsign/' . rawurlencode($cs), 6);
    $route = $r['data']['response']['flightroute'] ?? null;
    if ($r['status'] === 200 && is_array($route)) {
        $data = [
            'callsign_icao' => (string) ($route['callsign_icao'] ?? $cs),
            'callsign_iata' => (string) ($route['callsign_iata'] ?? ''),
            'airline' => (string) ($route['airline']['name'] ?? ''),
            'airline_icao' => (string) ($route['airline']['icao'] ?? ''),
            'origin' => adsbdb_airport($route['origin'] ?? null),
            'midpoint' => adsbdb_airport($route['midpoint'] ?? null),
            'destination' => adsbdb_airport($route['destination'] ?? null),
        ];
        db_upsert('callsigns', ['cs' => $cs, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'found' => 1, 'fetched_at' => time()], ['cs'], ['data' => null, 'found' => null, 'fetched_at' => null]);
        return $data;
    }
    if ($r['status'] === 404 || ($r['data']['response'] ?? null) === 'unknown callsign') {
        db_upsert('callsigns', ['cs' => $cs, 'data' => null, 'found' => 0, 'fetched_at' => time()], ['cs'], ['data' => null, 'found' => null, 'fetched_at' => null]);
        event_add('ADSBDB', 'No route entry for ' . $cs . ', fell back to the callsign', 'Kein Routeneintrag fuer ' . $cs . ', Rueckfall auf Rufzeichen');
        return null;
    }
    event_add('ADSBDB', 'adsbdb did not answer (HTTP ' . $r['status'] . ')', 'adsbdb hat nicht geantwortet (HTTP ' . $r['status'] . ')');
    cache_put('adsbdbfail:' . $cs, 1, 60);
    // Ein veralteter Eintrag ist besser als keiner, solange adsbdb stoert.
    if ($row && (int) $row['found'] === 1) {
        return json_decode((string) $row['data'], true);
    }
    return null;
}

/** Bekannte Strecken zu mehreren Rufzeichen, nur aus der Tabelle callsigns, ohne Abfrage bei adsbdb. */
function adsbdb_cached_routes(array $callsigns): array
{
    $list = [];
    foreach ($callsigns as $c) {
        $cs = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c) ?? '');
        if ($cs !== '' && strlen($cs) <= 12) {
            $list[$cs] = true;
        }
    }
    if (!$list) {
        return [];
    }
    $keys = array_keys($list);
    $rows = qall(
        'SELECT cs, data FROM callsigns WHERE found = 1 AND fetched_at > ? AND cs IN (' . implode(',', array_fill(0, count($keys), '?')) . ')',
        array_merge([time() - 30 * 86400], $keys)
    );
    $out = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data'], true);
        if (is_array($data)) {
            $out[(string) $row['cs']] = $data;
        }
    }
    return $out;
}

/**
 * Strecke zu einem Airline-Rufzeichen aus den VRS-Standdaten: {airline_icao, airports},
 * die Flughaefen in Flugreihenfolge, zwei oder mehr (SNA, EWR, ORD). null, wenn das
 * Rufzeichen dort fehlt oder keine Airline-Form hat (ein Kennzeichen hat keine Strecke).
 * Gemessen am 24. September 2026 an sechs Flughaefen in den USA und Europa: 181 von 199
 * Strecken passten zu Position und Bewegung des Flugzeugs, bei adsbdb 95 von 207.
 */
function vrs_route(string $callsign): ?array
{
    $cs = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $callsign) ?? '');
    if (!preg_match('/^[A-Z]{3}\d[A-Z0-9]{0,4}$/', $cs)) {
        return null;
    }
    $hit = cache_remember('vrs:' . $cs, 'vrs', static function () use ($cs): ?array {
        $url = 'https://vrs-standing-data.adsb.lol/routes/' . substr($cs, 0, 2) . '/' . $cs . '.json';
        // Drei Sekunden: GitHub Pages antwortet sonst in einer Zehntelsekunde, und der Abruf des
        // Geraets wartet schon auf adsb.lol und adsbdb.
        $r = http_get_json('vrs', $url, 3);
        if ($r['status'] === 404) {
            return [['found' => false], VRS_TTL];
        }
        if ($r['status'] !== 200 || !is_array($r['data'])) {
            event_add('VRS', 'Route file did not answer (HTTP ' . $r['status'] . ')', 'Streckendatei hat nicht geantwortet (HTTP ' . $r['status'] . ')');
            return null;
        }
        return [vrs_parse($r['data']), VRS_TTL];
    }, 60);
    return is_array($hit) && !empty($hit['found']) ? $hit : null;
}

/** Eine Datei der VRS-Standdaten in dieselbe Form wie adsbdb_airport(). */
function vrs_parse(array $data): array
{
    $airports = [];
    foreach ((array) ($data['_airports'] ?? []) as $a) {
        if (!is_array($a) || !is_numeric($a['lat'] ?? null) || !is_numeric($a['lon'] ?? null)) {
            return ['found' => false];
        }
        $airports[] = [
            'iata' => (string) ($a['iata'] ?? ''),
            'icao' => (string) ($a['icao'] ?? ''),
            'city' => (string) ($a['location'] ?? ($a['name'] ?? '')),
            'lat' => (float) $a['lat'],
            'lon' => (float) $a['lon'],
        ];
    }
    return [
        'found' => count($airports) >= 2,
        'airline_icao' => preg_match('/^[A-Z]{3}$/', (string) ($data['airline_code'] ?? '')) ? (string) $data['airline_code'] : '',
        'airports' => $airports,
    ];
}

/** Bekannte VRS-Strecken zu mehreren Rufzeichen, nur aus dem Zwischenspeicher, ohne Abfrage. */
function vrs_cached_routes(array $callsigns): array
{
    $keys = [];
    foreach ($callsigns as $c) {
        $cs = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c) ?? '');
        if ($cs !== '' && strlen($cs) <= 12) {
            $keys['vrs:' . $cs] = $cs;
        }
    }
    if (!$keys) {
        return [];
    }
    $rows = qall(
        'SELECT k, v FROM cache WHERE expires_at > ? AND k IN (' . implode(',', array_fill(0, count($keys), '?')) . ')',
        array_merge([time()], array_keys($keys))
    );
    $out = [];
    foreach ($rows as $row) {
        $v = json_decode((string) $row['v'], true);
        if (is_array($v) && !empty($v['found'])) {
            $out[$keys[(string) $row['k']]] = $v;
        }
    }
    return $out;
}

function adsbdb_airport(?array $a): ?array
{
    if (!$a) {
        return null;
    }
    return [
        'iata' => (string) ($a['iata_code'] ?? ''),
        'icao' => (string) ($a['icao_code'] ?? ''),
        'city' => (string) ($a['municipality'] ?? ($a['name'] ?? '')),
        'lat' => isset($a['latitude']) ? (float) $a['latitude'] : null,
        'lon' => isset($a['longitude']) ? (float) $a['longitude'] : null,
    ];
}

/** Anfangskurs von Punkt 1 nach Punkt 2 in Grad, 0 bis unter 360. */
function geo_bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dl = deg2rad($lon2 - $lon1);
    $y = sin($dl) * cos($p2);
    $x = cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dl);
    return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
}

/** Grosskreisentfernung in Seemeilen. */
function geo_nm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 3440.065;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $h = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($h)));
}
