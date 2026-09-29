<?php
/* Startseite. Oeffentlich, ohne echte Airline-Logos (FEHLERLISTE 3.6). */

declare(strict_types=1);

function page_welcome(array $params = []): void
{
    $online = 0;
    $count = null;
    try {
        $online = (int) qval('SELECT COUNT(*) FROM devices WHERE last_seen_at >= ?', [time() - DEVICE_ONLINE_SECONDS]);
        $count = welcome_aircraft_count();
    } catch (SetupMissing $e) {
        throw $e;
    } catch (Throwable $e) {
        log_error('Startseite: ' . $e->getMessage());
    }
    $demo = [];
    try {
        $demo = welcome_demo();
    } catch (Throwable $e) {
        log_error('Startseite, Beispielseiten: ' . $e->getMessage());
    }
    page_open(['title' => 'THE WALL', 'page' => 'welcome']);
    view('welcome', ['online' => $online, 'count' => $count, 'repo' => imprint()['repo']]);
    page_close(['js/lib/ops.js', 'js/pages/welcome.js'], ['demo' => $demo]);
}

/** Flugzeuge in der Luft um Luxemburg-Stadt, fuer die Zeile oben. Eine Minute gespeichert. */
function welcome_aircraft_count(): ?int
{
    $cached = cache_get('welcome:count');
    if ($cached !== null) {
        return (int) $cached;
    }
    $list = adsb_nearby(49.6116, 6.1319, 40);
    if ($list === null) {
        return null;
    }
    $n = count(array_filter($list, static fn(array $a): bool => !$a['ground']));
    cache_put('welcome:count', $n, 60);
    return $n;
}

/**
 * Beispielseiten fuer das Panel der Startseite, je Sprache, damit der Umschalter ohne
 * Neuladen auskommt. Gerechnet mit denselben Funktionen wie fuer ein Geraet, so bleibt die
 * Startseite beim naechsten Umbau des Panels von selbst aktuell.
 */
function welcome_demo(): array
{
    return ['en' => welcome_demo_pages('en'), 'de' => welcome_demo_pages('de')];
}

/**
 * Vier Ansichten eines Flugs, eine Abfahrtstafel, Uhr, Wetter und eine Notiz. Fest sind der
 * Flug (Luxair von Luxemburg nach Lissabon ueber Metz, wie in tools/flight-fixtures.php), der
 * Ort unter ihm und die Abfahrten (echte Fahrten der Datenprobe vom 14. September 2026 am
 * Hauptbahnhof, die Minuten ab jetzt). Das Wetter ist das von Luxemburg-Stadt, aus demselben
 * Zwischenspeicher wie fuer die Geraete, ohne Antwort ein fester Tag. Statt eines Logos ein
 * Farbblock, echte Logos gibt es nur auf dem eigenen Geraet.
 */
function welcome_demo_pages(string $lang): array
{
    $de = $lang === 'de';
    $s = device_defaults();
    // Ohne Nachtabsenkung, sonst hingen die Farben der Tafel von der Uhrzeit ab.
    $s['clock']['night'] = false;
    $ctx = ['settings' => $s, 'device' => ['id' => 0], 'owner' => [], 'lang' => $lang, 'preview' => true];

    $wx = null;
    try {
        $wx = weather_get((float) $s['location']['lat'], (float) $s['location']['lon'], (string) $s['tz']);
    } catch (Throwable $e) {
        $wx = null;
    }
    if (!$wx) {
        $day = static fn(int $n): string => (new DateTimeImmutable('now', new DateTimeZone('Europe/Luxembourg')))->modify('+' . $n . ' day')->format('Y-m-d');
        $wx = ['temp' => 17.0, 'code' => 2, 'wind' => 14.0, 'wind_dir' => 240, 'days' => [
            ['date' => $day(0), 'max' => 19.0, 'min' => 9.0, 'code' => 2],
            ['date' => $day(1), 'max' => 21.0, 'min' => 10.0, 'code' => 1],
            ['date' => $day(2), 'max' => 18.0, 'min' => 11.0, 'code' => 61],
            ['date' => $day(3), 'max' => 16.0, 'min' => 8.0, 'code' => 3],
        ]];
    }
    $ctx['wx'] = $wx;

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
    $fctx = $ctx + ['geo' => ['status' => 'ok', 'place' => 'Metz', 'region' => $de ? 'Frankreich' : 'France']];

    $pages = [];
    foreach (['route', 'progress', 'position', 'metrics'] as $view) {
        $pages[] = ['key' => $view, 'ops' => welcome_logo_block(flight_ops($ac, $route, $view, $fctx))];
    }

    $now = time();
    $minute = intdiv($now, 60) * 60;
    $at = static fn(int $min): string => (string) transit_iso($minute + $min * 60);
    $dep = static fn(array $o): array => array_replace([
        'mode' => 'bus', 'label' => '', 'line' => '', 'category' => 'Bus',
        'direction' => '', 'direction_split' => ['place' => '', 'stop' => ''],
        'planned' => null, 'realtime' => null, 'delay_min' => 0,
        'platform' => null, 'platform_realtime' => null, 'platform_hidden' => true,
        'cancelled' => false, 'part_cancelled' => false, 'additional' => false, 'replacement' => false, 'notes' => [],
    ], $o);
    $tctx = $ctx;
    $tctx['settings']['transit']['stops'] = [
        ['id' => '200405059', 'name' => 'Luxembourg, Gare Centrale (Tram)', 'short' => '', 'modes' => ['tram'], 'lines' => []],
        ['id' => '200405060', 'name' => 'Luxembourg, Gare Centrale', 'short' => '', 'modes' => ['train'], 'lines' => []],
        ['id' => '200405036', 'name' => 'Luxembourg, Gare Centrale routière', 'short' => '', 'modes' => ['bus'], 'lines' => []],
    ];
    $boards = ['configured' => true, 'boards' => [
        ['ok' => true, 'departures' => [
            $dep(['mode' => 'tram', 'label' => 'T1', 'line' => 'T1', 'category' => 'Tram', 'direction' => 'Kirchberg, Luxexpo', 'direction_split' => ['place' => 'Kirchberg', 'stop' => 'Luxexpo'], 'planned' => $at(3), 'realtime' => $at(3)]),
            $dep(['mode' => 'tram', 'label' => 'T1', 'line' => 'T1', 'category' => 'Tram', 'direction' => 'Gasperich, Stadion', 'direction_split' => ['place' => 'Gasperich', 'stop' => 'Stadion'], 'planned' => $at(11), 'realtime' => $at(11)]),
        ]],
        ['ok' => true, 'departures' => [
            $dep(['mode' => 'train', 'label' => 'RE', 'line' => 'RE', 'category' => 'RE', 'direction' => 'Rodange, Gare', 'direction_split' => ['place' => 'Rodange', 'stop' => 'Gare'], 'planned' => $at(4), 'realtime' => $at(4), 'platform' => '5 A-D', 'platform_hidden' => false]),
            $dep(['mode' => 'train', 'label' => 'TER', 'line' => 'TER', 'category' => 'TER', 'direction' => 'Metz-Ville, Gare', 'direction_split' => ['place' => 'Metz-Ville', 'stop' => 'Gare'], 'planned' => $at(11), 'delay_min' => null, 'platform' => '8 A-D', 'platform_hidden' => false]),
        ]],
        ['ok' => true, 'departures' => [
            $dep(['label' => 'Bus 27', 'line' => '27', 'direction' => 'Kockelscheuer, Patinoire', 'direction_split' => ['place' => 'Kockelscheuer', 'stop' => 'Patinoire'], 'planned' => $at(1), 'realtime' => $at(1)]),
            $dep(['label' => 'Bus 23', 'line' => '23', 'direction' => 'Bonnevoie, Demy Schlechter', 'direction_split' => ['place' => 'Bonnevoie', 'stop' => 'Demy Schlechter'], 'planned' => $at(1), 'realtime' => $at(4), 'delay_min' => 3]),
        ]],
    ]];
    $pages[] = ['key' => 'transit', 'ops' => transit_ops($tctx, $boards, $now)];

    $pages[] = ['key' => 'clock', 'ops' => clock_ops($ctx)];
    $pages[] = ['key' => 'weather', 'ops' => weather_ops($ctx)];

    $nctx = $ctx;
    $nctx['settings']['notes'] = ['line1' => $de ? 'Essen um 7' : 'Dinner at 7', 'line2' => $de ? 'Kuchen mitbringen' : 'Bring the cake', 'flash' => false];
    $notes = modes()['notes']['build']($nctx, (float) $now, (float) $now + 10);
    $pages[] = ['key' => 'notes', 'ops' => $notes[0]['ops'] ?? []];

    return $pages;
}

/** Logo als Farbblock mit Kuerzel wie frueher auf der Startseite, die oeffentliche Seite laedt keine Logos. */
function welcome_logo_block(array $ops): array
{
    $out = [];
    foreach ($ops as $op) {
        if (($op['t'] ?? '') !== 'logo') {
            $out[] = $op;
            continue;
        }
        $out[] = op_rect((int) $op['x'], (int) $op['y'], 32, 34, '0B4DA2');
        $out[] = op_text((int) $op['x'] + 6, (int) $op['y'] + 8, (string) $op['code'], 'DCE8F7');
        $out[] = op_rect((int) $op['x'] + 6, (int) $op['y'] + 20, 18, 1, 'DCE8F7');
    }
    return $out;
}
