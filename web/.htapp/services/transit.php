<?php
/*
 * Luxemburger Nahverkehr ueber die OpenAPI der Administration des transports
 * publics (mobiliteit.lu, HAFAS): Linien von AVL, CFL, Luxtram, RGTR und TICE.
 * Daten unter CC BY 4.0, die Quelle wird genannt, sobald Nutzer sie sehen.
 *
 * Der persoenliche Schluessel liegt verschluesselt in settings (wie der
 * SMTP-Schluessel) und geht nur vom Server an cdt.hafas.de. Er steht nie in einer
 * Antwort an den Browser, nie in einem Protokoll und nie in einer Fehlermeldung.
 *
 * Fehler eines fremden Dienstes gehen mit 424 an den Browser, nie mit 502 oder 503:
 * hinter CloudPanel und Cloudflare kam eine 502 der App am 14. September 2026 im
 * Browser nur als abgebrochene Verbindung an ("Failed to fetch").
 *
 * Kontingent des Schluessels: 500 Abfragen pro Stunde und 5 000 pro Tag. Der
 * Server hoert bei 450 und 4 500 selbst auf, damit der Schluessel nie gesperrt
 * wird. Abfahrten bleiben 60 Sekunden je Haltestelle im Zwischenspeicher,
 * Haltestellen einen Tag.
 */

declare(strict_types=1);

const TRANSIT_BASE = 'https://cdt.hafas.de/opendata/apiserver/';
const TRANSIT_HOUR_CAP = 450;
const TRANSIT_DAY_CAP = 4500;
const TRANSIT_DEPARTURES_TTL = 60;
const TRANSIT_STOPS_TTL = 86400;
const TRANSIT_TZ = 'Europe/Luxembourg';
const TRANSIT_MODES = ['train', 'tram', 'bus'];
const TRANSIT_BOARD_MAX = 20;
const TRANSIT_BOARD_DURATION = 120;
const TRANSIT_STALE_TTL = 7200;
const TRANSIT_NEXT_TTL = 900;
const TRANSIT_SOURCE = 'Administration des transports publics, mobiliteit.lu, CC BY 4.0';

/** Haltestellen fuer die Datenprobe: oeffentliche Orte mit Bus, Zug und Tram. */
const TRANSIT_SAMPLE_PLACES = [
    ['Luxembourg, Gare Centrale', 49.59997, 6.13424],
    ['Luxembourg, Hamilius', 49.61170, 6.12960],
    ['Kirchberg, Luxexpo', 49.63460, 6.17080],
    ['Esch-sur-Alzette, Gare', 49.49560, 5.98480],
    ['Ettelbruck, Gare', 49.84730, 6.10440],
    ['Steinfort, Gemeng', 49.65942, 5.91341],
];

/** Stand ohne den Schluessel selbst. */
function transit_settings(): array
{
    $s = setting('transit', []);
    $s = is_array($s) ? $s : [];
    return [
        'has_key' => !empty($s['key']),
        'tested_at' => isset($s['tested_at']) ? (int) $s['tested_at'] : null,
        'test_ok' => (bool) ($s['test_ok'] ?? false),
    ];
}

function transit_key(): string
{
    $s = setting('transit', []);
    return is_array($s) && !empty($s['key']) ? (unseal((string) $s['key']) ?? '') : '';
}

function transit_configured(): bool
{
    return transit_key() !== '';
}

/**
 * Schluessel speichern. Ein neuer Schluessel gilt als ungetestet, und der
 * Zwischenspeicher wird geleert, damit der naechste Test ihn wirklich benutzt.
 */
function transit_save_key(string $key): void
{
    setting_set('transit', ['key' => seal($key), 'tested_at' => null, 'test_ok' => false]);
    q("DELETE FROM cache WHERE k LIKE 'transit:%'");
}

function transit_mark_tested(bool $ok): void
{
    $s = setting('transit', []);
    $s = is_array($s) ? $s : [];
    $s['tested_at'] = time();
    $s['test_ok'] = $ok;
    setting_set('transit', $s);
}

/** Wie viel vom Kontingent verbraucht ist, fuer den Admin-Bereich. */
function transit_quota(): array
{
    $b = budget_stats('transit');
    return ['hour' => $b['hour'], 'day' => $b['day'], 'hour_cap' => TRANSIT_HOUR_CAP, 'day_cap' => TRANSIT_DAY_CAP];
}

/**
 * Eine Abfrage an die OpenAPI. Liefert ok, status, data, code und bei Fehlern
 * error_en und error_de. Bricht vor der Abfrage ab, wenn das Kontingent fast
 * aufgebraucht ist oder kein Schluessel da ist.
 */
function transit_call(string $endpoint, array $params, int $timeout = 8): array
{
    $key = transit_key();
    if ($key === '') {
        return transit_fail(409, 'NO_KEY', 'No key for mobiliteit.lu saved yet.', 'Noch kein Schlüssel für mobiliteit.lu gespeichert.');
    }
    $q = transit_quota();
    if ($q['hour'] >= TRANSIT_HOUR_CAP || $q['day'] >= TRANSIT_DAY_CAP) {
        return transit_fail(429, 'QUOTA_LOCAL', 'The server paused mobiliteit.lu requests to stay below the quota of the key.', 'Der Server pausiert die Abfragen an mobiliteit.lu, damit das Kontingent des Schlüssels reicht.');
    }
    budget_add('transit', false);
    $url = TRANSIT_BASE . $endpoint . '?' . http_build_query($params + ['format' => 'json', 'accessId' => $key]);
    $r = http_get($url, $timeout, [], false);
    // Falls HAFAS die Anfrage in einer Meldung wiederholt: der Schluessel bleibt draussen.
    $data = json_decode(str_replace($key, '***', $r['body']), true);
    $data = is_array($data) ? $data : null;
    if ($r['status'] === 0) {
        $err = str_replace($key, '***', $r['error']);
        event_add('TRANSIT', 'mobiliteit.lu not reachable: ' . $err, 'mobiliteit.lu nicht erreichbar: ' . $err);
        return transit_fail(424, 'NETWORK', 'mobiliteit.lu is not reachable right now.', 'mobiliteit.lu ist gerade nicht erreichbar.');
    }
    if ($data === null) {
        return transit_fail(424, 'BAD_ANSWER', 'mobiliteit.lu sent no readable answer (HTTP ' . $r['status'] . ').', 'mobiliteit.lu hat keine lesbare Antwort geschickt (HTTP ' . $r['status'] . ').');
    }
    if (isset($data['errorCode'])) {
        $code = preg_replace('/[^A-Z_]/', '', (string) $data['errorCode']) ?? '';
        return match ($code) {
            'API_AUTH' => transit_fail(403, $code, 'mobiliteit.lu does not accept the key.', 'mobiliteit.lu nimmt den Schlüssel nicht an.', true),
            'API_QUOTA' => transit_fail(429, $code, 'The quota of the key is used up, mobiliteit.lu refuses for now.', 'Das Kontingent des Schlüssels ist aufgebraucht, mobiliteit.lu lehnt vorerst ab.', true),
            'SVC_NO_RESULT' => ['ok' => true, 'status' => 200, 'data' => [], 'code' => $code],
            default => transit_fail(424, $code, 'mobiliteit.lu reports ' . $code . '.', 'mobiliteit.lu meldet ' . $code . '.'),
        };
    }
    if ($r['status'] !== 200) {
        return transit_fail(424, 'HTTP_' . $r['status'], 'mobiliteit.lu answered with HTTP ' . $r['status'] . '.', 'mobiliteit.lu hat mit HTTP ' . $r['status'] . ' geantwortet.');
    }
    return ['ok' => true, 'status' => 200, 'data' => $data, 'code' => ''];
}

function transit_fail(int $status, string $code, string $en, string $de, bool $event = false): array
{
    if ($event) {
        event_add('TRANSIT', $en, $de);
    }
    return ['ok' => false, 'status' => $status, 'data' => null, 'code' => $code, 'error_en' => $en, 'error_de' => $de];
}

/**
 * Rohabfrage fuer die Fehlersuche im Admin-Bereich: nur freigegebene Endpunkte und
 * Parameter, nur einfache Werte. Die Antwort kommt unveraendert, ohne Schluessel.
 */
function transit_raw(string $endpoint, array $params): array
{
    // Mehr Dienste bietet der Server der ATP nicht an (Liste unter apiserver/?_wadl, Stand 14. September 2026).
    $endpoints = ['location.nearbystops', 'departureBoard'];
    $allowed = ['originCoordLat', 'originCoordLong', 'r', 'maxNo', 'type', 'products', 'locationSelectionMode', 'filterMode', 'meta',
        'id', 'extId', 'maxJourneys', 'duration', 'date', 'time', 'lang', 'operators', 'lines', 'rtMode', 'passlist', 'passlistMaxStops'];
    if (!in_array($endpoint, $endpoints, true)) {
        return transit_fail(422, 'BAD_ENDPOINT', 'Allowed: ' . implode(', ', $endpoints) . '.', 'Erlaubt: ' . implode(', ', $endpoints) . '.');
    }
    $clean = [];
    foreach ($params as $k => $v) {
        if (!in_array($k, $allowed, true) || !(is_string($v) || is_int($v) || is_float($v)) || mb_strlen((string) $v) > 120) {
            return transit_fail(422, 'BAD_PARAM', 'Parameter not allowed: ' . str_cut((string) $k, 40), 'Parameter nicht erlaubt: ' . str_cut((string) $k, 40));
        }
        $clean[$k] = (string) $v;
    }
    return transit_call($endpoint, $clean, 10);
}

/** HAFAS liefert einzelne Eintraege als Objekt, mehrere als Liste. */
function transit_list(mixed $v): array
{
    if (!is_array($v) || $v === []) {
        return [];
    }
    return array_is_list($v) ? $v : [$v];
}

/** Verkehrsmittel aus der Produktklasse: 1, 2, 4 Zug, 32 Bus, 256 Tram. */
function transit_mode(int $cls, string $catOut = ''): string
{
    if ($cls & 7) {
        return 'train';
    }
    if ($cls & 32) {
        return 'bus';
    }
    if ($cls & 256) {
        return 'tram';
    }
    $c = strtolower($catOut);
    return $c === 'bus' ? 'bus' : ($c === 'tram' ? 'tram' : 'other');
}

/** Alle Verkehrsmittel einer Haltestelle aus dem Bitfeld products. */
function transit_modes(int $products): array
{
    $out = [];
    if ($products & 7) {
        $out[] = 'train';
    }
    if ($products & 32) {
        $out[] = 'bus';
    }
    if ($products & 256) {
        $out[] = 'tram';
    }
    return $out;
}

/** "Luxembourg, Gare Centrale" in Ort und Haltestelle. */
function transit_split_name(string $name): array
{
    $parts = array_map('trim', explode(',', $name, 2));
    return count($parts) === 2 ? ['place' => $parts[0], 'stop' => $parts[1]] : ['place' => '', 'stop' => $parts[0]];
}

function transit_time(string $date, string $time): ?int
{
    if ($date === '' || $time === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($date . ' ' . $time, new DateTimeZone(TRANSIT_TZ)))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

/** Zeitpunkt als ISO 8601 in Luxemburger Ortszeit, unabhaengig von der Zeitzone des Servers. */
function transit_iso(?int $ts): ?string
{
    return $ts === null ? null : (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(TRANSIT_TZ))->format(DATE_ATOM);
}

function transit_colour(mixed $c): ?string
{
    $hex = is_array($c) ? (string) ($c['hex'] ?? '') : '';
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? strtoupper($hex) : null;
}

/** Eine Haltestelle aus location.nearbystops in unser Format. */
function transit_normalize_stop(array $s): array
{
    $lines = [];
    foreach (transit_list($s['productAtStop'] ?? []) as $p) {
        // Zuege haben oft keine Linie, dann steht die Gattung im Namen ("RE").
        $line = trim((string) ($p['line'] ?? ''));
        $line = $line !== '' ? $line : trim((string) ($p['name'] ?? ''));
        $mode = transit_mode((int) ($p['cls'] ?? 0), (string) ($p['catOut'] ?? ''));
        $k = $mode . ':' . $line;
        if ($line === '' || isset($lines[$k])) {
            continue;
        }
        $lines[$k] = [
            'line' => $line,
            'name' => trim((string) ($p['name'] ?? '')),
            'mode' => $mode,
            'category' => trim((string) ($p['catOutL'] ?? ($p['catOut'] ?? ''))),
            'colour' => transit_colour($p['icon']['backgroundColor'] ?? null),
            'text_colour' => transit_colour($p['icon']['foregroundColor'] ?? null),
        ];
    }
    $name = trim((string) ($s['name'] ?? ''));
    return [
        'id' => (string) ($s['extId'] ?? ''),
        'name' => $name,
    ] + transit_split_name($name) + [
        'lat' => isset($s['lat']) ? (float) $s['lat'] : null,
        'lon' => isset($s['lon']) ? (float) $s['lon'] : null,
        'dist_m' => isset($s['dist']) ? (int) $s['dist'] : null,
        'weight' => (int) ($s['weight'] ?? 0),
        'modes' => transit_modes((int) ($s['products'] ?? 0)),
        'lines' => array_values($lines),
    ];
}

/** Eine Abfahrt aus departureBoard in unser Format. */
function transit_normalize_departure(array $d): array
{
    $p = $d['Product'] ?? [];
    $p = is_array($p) && array_is_list($p) ? ($p[0] ?? []) : (is_array($p) ? $p : []);
    $mode = transit_mode((int) ($p['cls'] ?? 0), (string) ($p['catOut'] ?? ''));
    $planned = transit_time((string) ($d['date'] ?? ''), (string) ($d['time'] ?? ''));
    $real = transit_time((string) ($d['rtDate'] ?? ''), (string) ($d['rtTime'] ?? ''));
    $notes = [];
    $attributes = [];
    foreach (transit_list($d['Notes']['Note'] ?? []) as $n) {
        $type = (string) ($n['type'] ?? '');
        $txt = trim((string) ($n['txtN'] ?? ($n['value'] ?? '')));
        if ($txt !== '' && in_array($type, ['R', 'I'], true) && count($notes) < 3) {
            $notes[] = ['type' => $type, 'text' => $txt];
        }
        // Merkmale wie "accessible"; der Betreiber steht schon in operator.
        if ($txt !== '' && $type === 'A' && ($n['key'] ?? '') !== 'OPERATOR' && count($attributes) < 5) {
            $attributes[] = $txt;
        }
    }
    // Bussteige liefert HAFAS mit, blendet sie aber aus (trackHidden). Nur Gleise zeigen.
    $platformHidden = !empty($d['trackHidden']) || (is_array($d['platform'] ?? null) && !empty($d['platform']['hidden']));
    $line = trim((string) ($p['line'] ?? ''));
    $line = $line !== '' ? $line : trim((string) ($d['trainNumber'] ?? ''));
    $category = trim((string) ($p['catOut'] ?? ''));
    $direction = trim((string) ($d['direction'] ?? ''));
    return [
        'mode' => $mode,
        'label' => trim((string) ($p['name'] ?? ($d['name'] ?? ''))),
        'line' => $line,
        'category' => $category,
        'category_long' => trim((string) ($p['catOutL'] ?? '')),
        'number' => trim((string) ($p['num'] ?? '')),
        'operator' => trim((string) ($p['operatorCode'] ?? '')),
        'operator_name' => trim((string) ($p['operator'] ?? '')),
        'direction' => $direction,
        'direction_split' => transit_split_name($direction),
        'planned' => transit_iso($planned),
        'realtime' => transit_iso($real),
        'delay_min' => ($planned !== null && $real !== null) ? (int) round(($real - $planned) / 60) : null,
        'platform' => isset($d['track']) ? trim((string) $d['track']) : null,
        'platform_realtime' => isset($d['rtTrack']) ? trim((string) $d['rtTrack']) : null,
        'platform_hidden' => $platformHidden,
        'attributes' => $attributes,
        'cancelled' => !empty($d['cancelled']),
        'part_cancelled' => !empty($d['partCancelled']),
        // Bedeutungen laut XSD rest-2.52 der ATP: JourneyStatus P geplant, R Ersatz fuer eine
        // geplante Fahrt, A zusaetzliche Fahrt, S Sonderfahrt. redirected: Fahrt baulich
        // geaendert (Halt dazu oder weg, Planzeiten geaendert). scheduledTimeChanged: Planzeit geaendert.
        'status' => (string) ($d['JourneyStatus'] ?? ''),
        'additional' => ($d['JourneyStatus'] ?? '') === 'A',
        'replacement' => ($d['JourneyStatus'] ?? '') === 'R',
        'redirected' => !empty($d['redirected']),
        'time_changed' => !empty($d['scheduledTimeChanged']),
        'colour' => transit_colour($p['icon']['backgroundColor'] ?? null),
        'text_colour' => transit_colour($p['icon']['foregroundColor'] ?? null),
        'notes' => $notes,
    ];
}

/**
 * Haltestellen im Umkreis, naechste zuerst, hoechstens $max. Einen Tag im
 * Zwischenspeicher, ein leeres Ergebnis nur zehn Minuten.
 *
 * Grosse Knoten sind bei der ATP Gruppen: Haltestelle (Hauptmast) mit Steigen und
 * Eingaengen. Ohne type=SE liefert location.nearbystops dort gar nichts (am
 * 14. September 2026 an Gare Centrale, Hamilius, Luxexpo und Ettelbruck). Mit SE
 * kommen Eingaenge und Steige; jeder nennt seine Haltestelle in mainMastExtId, und
 * fuer diese ID gibt es die Abfahrten. Deshalb wird hier nach Hauptmast
 * zusammengefasst: naechster Eintrag, groesstes Gewicht, alle Verkehrsmittel und Linien.
 */
function transit_nearby(float $lat, float $lon, int $radius = 400, int $max = 5): array
{
    $radius = max(50, min(5000, $radius));
    $max = max(1, min(50, $max));
    $key = sprintf('transit:stops3:%.4f:%.4f:%d', $lat, $lon, $radius);
    $hit = cache_get($key);
    if (is_array($hit)) {
        budget_add('transit', true);
        return ['ok' => true, 'stops' => array_slice($hit, 0, $max)];
    }
    $r = transit_call('location.nearbystops', [
        'originCoordLat' => sprintf('%.5f', $lat),
        'originCoordLong' => sprintf('%.5f', $lon),
        'r' => $radius,
        'maxNo' => 50,
        'type' => 'SE',
    ]);
    if (!$r['ok']) {
        return $r;
    }
    $entries = transit_list($r['data']['stopLocationOrCoordLocation'] ?? ($r['data']['StopLocation'] ?? []));
    $stops = transit_group_stops($entries);
    cache_put($key, $stops, $stops ? TRANSIT_STOPS_TTL : 600);
    $out = ['ok' => true, 'stops' => array_slice($stops, 0, $max)];
    if (!$stops) {
        // Fuer die Fehlersuche im Admin-Bereich: was HAFAS stattdessen geschickt hat.
        $out['raw_keys'] = array_keys($r['data']);
        $out['raw_entries'] = array_slice($entries, 0, 3);
    }
    return $out;
}

/** Eintraege aus location.nearbystops nach Haltestelle (mainMastExtId) zusammenfassen, naechste zuerst. */
function transit_group_stops(array $entries): array
{
    $byId = [];
    foreach ($entries as $entry) {
        $s = is_array($entry) ? ($entry['StopLocation'] ?? $entry) : null;
        if (!is_array($s) || !isset($s['extId'])) {
            continue;
        }
        $stop = transit_normalize_stop($s);
        $id = (string) ($s['mainMastExtId'] ?? $s['extId']);
        $stop['id'] = $id;
        if (!isset($byId[$id])) {
            $byId[$id] = $stop;
            continue;
        }
        $have = &$byId[$id];
        if (($stop['dist_m'] ?? PHP_INT_MAX) < ($have['dist_m'] ?? PHP_INT_MAX)) {
            foreach (['name', 'place', 'stop', 'lat', 'lon', 'dist_m'] as $k) {
                $have[$k] = $stop[$k];
            }
        }
        $have['weight'] = max($have['weight'], $stop['weight']);
        $have['modes'] = array_values(array_intersect(['train', 'bus', 'tram'], array_merge($have['modes'], $stop['modes'])));
        $lines = [];
        foreach (array_merge($have['lines'], $stop['lines']) as $l) {
            $lines[$l['mode'] . ':' . $l['line']] = $l;
        }
        $have['lines'] = array_values($lines);
        unset($have);
    }
    $stops = array_values($byId);
    usort($stops, static fn(array $a, array $b): int => ($a['dist_m'] ?? 0) <=> ($b['dist_m'] ?? 0));
    return $stops;
}

/** Die wichtigste Haltestelle einer Liste: hoechstes Gewicht von HAFAS, bei Gleichstand die naechste. */
function transit_main_stops(array $stops): array
{
    usort($stops, static fn(array $a, array $b): int => [$b['weight'], $a['dist_m'] ?? 0] <=> [$a['weight'], $b['dist_m'] ?? 0]);
    return $stops;
}

/**
 * Naechste Abfahrten einer Haltestelle (extId). 60 Sekunden im Zwischenspeicher.
 * Mit $raw kommt die erste Abfahrt zusaetzlich so, wie HAFAS sie liefert.
 */
function transit_departures(string $stopId, int $max = 12, int $duration = 90, bool $raw = false): array
{
    if (!preg_match('/^\d{3,12}$/', $stopId)) {
        return transit_fail(422, 'BAD_STOP', 'That stop ID is not valid.', 'Diese Haltestellen-ID ist ungültig.');
    }
    $max = max(1, min(40, $max));
    $duration = max(10, min(240, $duration));
    $key = 'transit:dep:' . $stopId . ':' . $max . ':' . $duration;
    $out = cache_get($key);
    if (is_array($out) && isset($out['departures'])) {
        budget_add('transit', true);
        if (!$raw) {
            unset($out['raw']);
        }
        return ['ok' => true] + $out;
    }
    $r = transit_call('departureBoard', ['id' => $stopId, 'maxJourneys' => $max, 'duration' => $duration, 'lang' => 'fr']);
    if (!$r['ok']) {
        return $r;
    }
    $list = transit_list($r['data']['Departure'] ?? []);
    $out = [
        'departures' => array_map('transit_normalize_departure', array_values(array_filter($list, 'is_array'))),
        'fetched_at' => transit_iso(time()),
        'raw' => $list[0] ?? null,
    ];
    cache_put($key, $out, TRANSIT_DEPARTURES_TTL);
    if (!$raw) {
        unset($out['raw']);
    }
    // Der letzte gute Stand bleibt laenger, fuer "Keine Daten, Stand 13:46" auf dem Panel.
    cache_put('transit:last:' . $stopId . ':' . $max . ':' . $duration, ['departures' => $out['departures'], 'fetched_at' => $out['fetched_at']], TRANSIT_STALE_TTL);
    return ['ok' => true] + $out;
}

/**
 * Abfahrten einer Haltestelle fuer das Panel. Scheitert die Abfrage, kommt der
 * letzte gute Stand als `stale` mit (bis zwei Stunden alt). Ist die Tafel fuer
 * zwei Stunden leer, etwa nachts, steht die naechste Abfahrt in `next`.
 */
function transit_board(string $stopId): array
{
    $valid = preg_match('/^\d{3,12}$/', $stopId) === 1;
    // Ein Fehler bleibt eine Minute gespeichert. Sonst fragte jeder Abruf des Geraets
    // alle zehn Sekunden neu und verbrauchte das Kontingent, waehrend der Dienst ausfaellt.
    $failed = $valid ? cache_get('transit:fail:' . $stopId) : null;
    $r = is_array($failed) ? $failed : transit_departures($stopId, TRANSIT_BOARD_MAX, TRANSIT_BOARD_DURATION);
    if (!$r['ok']) {
        if ($valid && !is_array($failed) && !in_array($r['code'], ['NO_KEY', 'QUOTA_LOCAL', 'BAD_STOP'], true)) {
            cache_put('transit:fail:' . $stopId, $r, 60);
        }
        $last = $valid ? cache_get('transit:last:' . $stopId . ':' . TRANSIT_BOARD_MAX . ':' . TRANSIT_BOARD_DURATION) : null;
        return $r + ['stale' => is_array($last) && isset($last['departures']) ? $last : null];
    }
    if ($r['departures'] === []) {
        $r['next'] = transit_next_departure($stopId);
    }
    return $r;
}

/** Erste Abfahrt der naechsten zwoelf Stunden, fuer eine leere Tafel. 15 Minuten gespeichert, auch "keine". */
function transit_next_departure(string $stopId): ?array
{
    $key = 'transit:next:' . $stopId;
    $hit = cache_get($key);
    if (is_array($hit)) {
        budget_add('transit', true);
        return is_array($hit['dep'] ?? null) ? $hit['dep'] : null;
    }
    $r = transit_call('departureBoard', ['id' => $stopId, 'maxJourneys' => 1, 'duration' => 720, 'lang' => 'fr']);
    if (!$r['ok']) {
        return null;
    }
    $list = array_values(array_filter(transit_list($r['data']['Departure'] ?? []), 'is_array'));
    $dep = $list ? transit_normalize_departure($list[0]) : null;
    cache_put($key, ['dep' => $dep], TRANSIT_NEXT_TTL);
    return $dep;
}

/**
 * Vorschlag fuer den Namen auf dem Panel, wie im Entwurf: die Haltestelle ohne Ort,
 * "Gare routiere" wird BUS. Bleibt davon nur BUS oder GARE, kommt der Ort davor,
 * sonst hiesse der Busbahnhof in Esch nur BUS. Hoechstens 18 Zeichen, so viele
 * passen in den Kopf neben die Quadrate.
 */
function transit_short_name(array $stop): string
{
    $stopPart = trim((string) ($stop['stop'] ?? ''));
    $s = panel_text($stopPart !== '' ? $stopPart : (string) ($stop['name'] ?? ''), true, 80);
    $s = trim(preg_replace('/\s*\((BUS|TRAM|TRAIN)\)\s*$/', '', $s) ?? $s);
    $s = preg_replace('/\bGARE ROUTIERE\b/', 'BUS', $s) ?? $s;
    $s = trim(preg_replace('/\bROUTIERE\b/', 'BUS', $s) ?? $s);
    $place = panel_text((string) ($stop['place'] ?? ''), true, 40);
    if ($place !== '' && in_array($s, ['', 'BUS', 'GARE'], true)) {
        $s = trim($place . ' ' . $s);
    }
    // Zu lang fuer den Kopf: "Esch-sur-Alzette" wie ueblich als ESCH/ALZETTE.
    if (mb_strlen($s) > TRANSIT_SHORT_MAX) {
        $s = str_replace(['-SUR-', '-SOUS-'], '/', $s);
    }
    return mb_substr($s, 0, TRANSIT_SHORT_MAX);
}

/** Linien einer Haltestelle als Kuerzel, wie sie auf dem Panel stehen: Zuege mit Gattung, sonst die Linie. */
function transit_stop_codes(array $stop): array
{
    $out = [];
    foreach ($stop['lines'] ?? [] as $l) {
        $mode = (string) ($l['mode'] ?? '');
        if (!in_array($mode, TRANSIT_MODES, true)) {
            continue;
        }
        $raw = $mode === 'train' ? ((string) ($l['category'] ?? '') !== '' ? (string) $l['category'] : (string) ($l['line'] ?? '')) : (string) ($l['line'] ?? '');
        $code = panel_text($raw, true, 8);
        if ($code !== '' && !isset($out[$mode . ':' . $code])) {
            $out[$mode . ':' . $code] = ['code' => $code, 'mode' => $mode];
        }
    }
    $list = array_values($out);
    usort($list, static fn(array $a, array $b): int => [array_search($a['mode'], TRANSIT_MODES, true), $a['code']] <=> [array_search($b['mode'], TRANSIT_MODES, true), $b['code']]);
    return array_slice($list, 0, 60);
}

/**
 * Datenprobe fuer Entwuerfe: die Orte aus TRANSIT_SAMPLE_PLACES mit ihren
 * Haltestellen und Abfahrten, dazu Kennzahlen wie die laengsten Namen.
 */
function transit_sample(): array
{
    $stops = [];
    $errors = [];
    foreach (TRANSIT_SAMPLE_PLACES as [$label, $lat, $lon]) {
        $near = transit_nearby($lat, $lon, 400, 20);
        if (!$near['ok']) {
            $errors[] = $label . ': ' . $near['error_en'];
            if (in_array($near['code'], ['NO_KEY', 'API_AUTH', 'API_QUOTA', 'QUOTA_LOCAL'], true)) {
                break;
            }
            continue;
        }
        if (!$near['stops']) {
            $errors[] = $label . ': no stop within 400 m, HAFAS sent ' . json_encode(['keys' => $near['raw_keys'] ?? [], 'entries' => $near['raw_entries'] ?? []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            continue;
        }
        // Bis zu drei Haltestellen je Ort: je Verkehrsmittel die wichtigste, am Bahnhof also Zuege,
        // Tram und Busbahnhof. Tram-Steige haben bei der ATP ein kleineres Gewicht als Bushalte.
        $candidates = array_map(static fn(array $s): array => [
            'id' => $s['id'], 'name' => $s['name'], 'dist_m' => $s['dist_m'], 'weight' => $s['weight'], 'modes' => $s['modes'],
        ], array_slice($near['stops'], 0, 8));
        $ranked = transit_main_stops($near['stops']);
        $picked = [];
        foreach (['train', 'tram', 'bus'] as $mode) {
            foreach ($ranked as $s) {
                if (in_array($mode, $s['modes'], true) && !isset($picked[$s['id']])) {
                    $picked[$s['id']] = $s;
                    break;
                }
            }
        }
        foreach ($ranked as $s) {
            if (count($picked) >= 3) {
                break;
            }
            $picked[$s['id']] ??= $s;
        }
        foreach (array_slice(array_values($picked), 0, 3) as $stop) {
            $dep = transit_departures($stop['id'], 15, 120, true);
            if (!$dep['ok']) {
                $errors[] = $label . ' (' . $stop['name'] . '): ' . $dep['error_en'];
                break;
            }
            if (!$dep['departures']) {
                $errors[] = $label . ' (' . $stop['name'] . '): no departures in the next 120 minutes';
                continue;
            }
            $stops[] = $stop + [
                'looked_up_as' => $label,
                'candidates' => $candidates,
                'departures' => $dep['departures'],
                'raw_first_departure' => $dep['raw'] ?? null,
            ];
        }
    }
    return [
        'source' => TRANSIT_SOURCE,
        'fetched_at' => transit_iso(time()),
        'timezone' => TRANSIT_TZ,
        'about' => 'Data sample for designing the transit mode. Times are local ISO 8601, delay_min is realtime minus planned, colours are the line colours of the operators.',
        'stats' => transit_stats($stops),
        'stops' => $stops,
        'errors' => $errors,
    ];
}

/** Kennzahlen fuer Entwuerfe: Laengen, Anteile, Beispiele. */
function transit_stats(array $stops): array
{
    $byMode = [];
    $delayed = 0;
    $cancelled = 0;
    $flags = ['part_cancelled' => 0, 'additional' => 0, 'replacement' => 0, 'redirected' => 0, 'time_changed' => 0];
    $realtime = 0;
    $platform = 0;
    $total = 0;
    $longest = ['direction' => '', 'stop' => '', 'label' => ''];
    $operators = [];
    foreach ($stops as $s) {
        if (mb_strlen($s['name']) > mb_strlen($longest['stop'])) {
            $longest['stop'] = $s['name'];
        }
        foreach ($s['departures'] as $d) {
            $total++;
            $byMode[$d['mode']] = ($byMode[$d['mode']] ?? 0) + 1;
            $operators[$d['operator']] = ($operators[$d['operator']] ?? 0) + 1;
            $delayed += ($d['delay_min'] ?? 0) >= 1 ? 1 : 0;
            $cancelled += $d['cancelled'] ? 1 : 0;
            foreach ($flags as $flag => $count) {
                $flags[$flag] = $count + (!empty($d[$flag]) ? 1 : 0);
            }
            $realtime += $d['realtime'] !== null ? 1 : 0;
            $platform += (($d['platform'] ?? '') !== '' && !$d['platform_hidden']) ? 1 : 0;
            foreach (['direction', 'label'] as $k) {
                if (mb_strlen($d[$k]) > mb_strlen($longest[$k])) {
                    $longest[$k] = $d[$k];
                }
            }
        }
    }
    ksort($byMode);
    ksort($operators);
    return [
        'stops' => count($stops),
        'departures' => $total,
        'by_mode' => $byMode,
        'by_operator' => $operators,
        'with_realtime' => $realtime,
        'delayed_1min_or_more' => $delayed,
        'cancelled' => $cancelled,
    ] + $flags + [
        'with_visible_platform' => $platform,
        'longest' => array_map(static fn(string $v): array => ['text' => $v, 'chars' => mb_strlen($v)], $longest),
        'panel_line_chars' => PANEL_LINE_MAX,
    ];
}
