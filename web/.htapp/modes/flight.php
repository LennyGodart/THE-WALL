<?php
/*
 * Modus Flugradar. Raster aus CLAUDE.md, Zeichenvorschub 6, Zeilenabstand 12:
 *   Logo 32x34 bei (2,2), Zeilen 1 bis 3 bei x 38 mit 15 Zeichen,
 *   Zeilen 4 und 5 bei x 2 mit 21 Zeichen, Fortschritt 128x2 bei y 61.
 *
 * Vier Ansichten je Flug, angelehnt an die Bilder von theflightwall, die der
 * Projektinhaber am 18. September 2026 geschickt hat:
 *   route     Airline, Strecke, Typ, Abflugort, Zielort
 *   progress  Rufzeichen, Strecke, Typ, "DEPARTED ~2H AGO", "ARRIVING IN ~58 MIN", Balken
 *   position  Rufzeichen, Strecke, Typ, "FLYING OVER" und der Ort darunter, Balken
 *   metrics   Rufzeichen, Entfernung, Typ, Hoehe und Tempo, Kurs und Steigrate
 * Der Typ steht ausgeschrieben (737 MAX 8 statt B38M, services/aircraft.php).
 * Abflug und Ankunft sind Schaetzungen aus Strecke und Tempo, deshalb die Tilde.
 * Eine Strecke gilt nur, wenn sie zu Position und Bewegung passt (flight_route).
 *
 * Gezeigt wird das naechstgelegene Flugzeug. Nach jeder Standzeit (dwell) schaut
 * der Server neu: ist ein anderes naeher, wechselt die Anzeige, sonst bleibt der
 * Flug stehen. Die Ansichten wechseln unabhaengig davon alle `view` Sekunden,
 * ein neuer Flug beginnt mit der Route. Ohne bekannte Route gibt es nur position
 * und metrics. Ein angehefteter Flug (pin) zeigt dieselben Ansichten, egal wo er
 * gerade fliegt.
 */

declare(strict_types=1);

mode_register('flight', [
    'order' => 10,
    'label' => ['en' => 'Flight radar', 'de' => 'Flugradar'],
    'rotatable' => true,
    'defaults' => ['radius' => 40, 'alt' => false, 'mil' => true, 'dwell' => 8, 'view' => 4, 'pin' => '', 'empty' => 'clock',
        'views' => ['route', 'progress', 'position', 'metrics', 'map'], 'hold' => true,
        'ualt' => 'ft', 'uspd' => 'kmh', 'uvr' => 'ms', 'udist' => 'nm'],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (array_key_exists('radius', $in)) {
            $out['radius'] = (int) (round(clamp_int($in['radius'], 5, 150, (int) $cur['radius']) / 5) * 5);
        }
        if (array_key_exists('dwell', $in)) {
            $out['dwell'] = clamp_int($in['dwell'], FLIGHT_DWELL_MIN, FLIGHT_DWELL_MAX, (int) $cur['dwell']);
        }
        if (array_key_exists('view', $in)) {
            $out['view'] = clamp_int($in['view'], FLIGHT_VIEW_MIN, FLIGHT_VIEW_MAX, (int) ($cur['view'] ?? 4));
        }
        if (array_key_exists('views', $in)) {
            $want = is_array($in['views']) ? $in['views'] : [];
            $keep = array_values(array_filter(FLIGHT_VIEWS, static fn(string $v): bool => in_array($v, $want, true)));
            // Keine Ansicht gewaehlt hiesse ein leeres Panel. Dann gelten wieder alle.
            $out['views'] = $keep ?: FLIGHT_VIEWS;
        }
        foreach (['alt', 'mil', 'hold'] as $b) {
            if (array_key_exists($b, $in)) {
                $out[$b] = (bool) $in[$b];
            }
        }
        if (array_key_exists('pin', $in)) {
            $out['pin'] = strtoupper(substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $in['pin']) ?? '', 0, 12));
        }
        if (isset($in['empty']) && in_array($in['empty'], ['clock', 'wait', 'off'], true)) {
            $out['empty'] = $in['empty'];
        }
        foreach (FLIGHT_UNITS as $k => $allowed) {
            if (isset($in[$k]) && in_array($in[$k], $allowed, true)) {
                $out[$k] = $in[$k];
            }
        }
        return $out;
    },
    'build' => 'flight_build',
]);

const FLIGHT_DWELL_MIN = 3;
const FLIGHT_DWELL_MAX = 60;
const FLIGHT_VIEW_MIN = 2;
const FLIGHT_VIEW_MAX = 30;
const FLIGHT_PLAN_TTL = 120;
/* Die vier Ansichten eines Flugs in ihrer Reihenfolge. Ein neuer Flug beginnt mit der ersten. */
const FLIGHT_VIEWS = ['route', 'progress', 'position', 'metrics', 'map'];
/* Die Karte steht doppelt so lange wie eine Textansicht: sie braucht laenger zum Lesen. */
const FLIGHT_MAP_FACTOR = 2;
/* Die Karte braucht die Zeichenbefehle bmp und dot. Aeltere Firmware kennt sie nicht und
   zeigte eine fast leere Seite, deshalb bekommt sie die Ansicht gar nicht erst. */
const FLIGHT_MAP_FW = '0.1.9';
/* Start oder Landung: unter dieser Hoehe und schneller als diese Fahrt nach oben oder
   unten. Solange das dauert, bleibt das Panel bei diesem Flug (Einstellung hold). */
const FLIGHT_EVENT_FT = 10000;
const FLIGHT_EVENT_VR = 300;
/* Punkte fuer den Rest der Strecke, gedimmte LED wie in CLAUDE.md erlaubt. */
const FLIGHT_DOTS = '4E5A63';
/* Einheiten zur Wahl fuer die Messwerte: Hoehe, Tempo, Steigrate, Entfernung. Die erste
   ist jeweils die Vorgabe: tausend Fuss, km/h, Meter pro Sekunde, Seemeilen. */
const FLIGHT_UNITS = [
    'ualt' => ['ft', 'm'],
    'uspd' => ['kmh', 'kt', 'mph'],
    'uvr' => ['ms', 'fpm'],
    'udist' => ['nm', 'km', 'mi'],
];

const FLIGHT_NAMES = [
    'LGL' => 'LUXAIR', 'CLX' => 'CARGOLUX', 'DLH' => 'LUFTHANSA', 'BAW' => 'BRITISH AIRWAYS', 'EZY' => 'EASYJET',
    'EJU' => 'EASYJET', 'EZS' => 'EASYJET', 'RYR' => 'RYANAIR', 'RUK' => 'RYANAIR', 'AFR' => 'AIR FRANCE', 'KLM' => 'KLM',
    'UAE' => 'EMIRATES', 'THY' => 'TURKISH', 'SWR' => 'SWISS', 'BEL' => 'BRUSSELS', 'TAP' => 'TAP PORTUGAL',
    'IBE' => 'IBERIA', 'AUA' => 'AUSTRIAN', 'SAS' => 'SAS', 'EWG' => 'EUROWINGS', 'TRA' => 'TRANSAVIA',
    'TVF' => 'TRANSAVIA', 'WZZ' => 'WIZZ AIR', 'VLG' => 'VUELING', 'QTR' => 'QATAR', 'ETD' => 'ETIHAD',
    'UPS' => 'UPS', 'FDX' => 'FEDEX', 'BCS' => 'DHL', 'DHK' => 'DHL', 'CFG' => 'CONDOR', 'LOT' => 'LOT',
    'AEE' => 'AEGEAN', 'FIN' => 'FINNAIR', 'ICE' => 'ICELANDAIR', 'AAL' => 'AMERICAN', 'UAL' => 'UNITED', 'DAL' => 'DELTA',
    'LRQ' => 'LUX AIR RESCUE', 'LXA' => 'LUXAVIATION', 'JFA' => 'JETFLY', 'SVW' => 'GLOBAL JET',
    'PL' => 'POLICE',
];

/* Rufzeichen, die nicht mit dem ICAO-Kuerzel ihres Betreibers beginnen: Anfang => [Kuerzel,
   Land der Zulassung, leer fuer jedes]. Die Hubschrauber der Luxembourg Air Rescue fliegen als
   AIRESC1 bis AIRESC3, ihre Jets als LRQ. Nach den ersten drei Buchstaben waere es AIR, und das
   ist Airlift International in den USA. Der Hubschrauber der Polizei (H145, LX-FAA) fliegt als
   POLICE1. Das gilt nur mit Luxemburger Zulassung, sonst bekaeme jede Polizei mit so einem
   Rufzeichen den Loewen. PL ist kein ICAO-Kuerzel, die haben immer drei Buchstaben. */
const FLIGHT_CALLSIGN_OWNERS = ['AIRESC' => ['LRQ', ''], 'POLICE' => ['PL', 'LX']];
/* Bloecke der 24-Bit-Kennung, fuer Flugzeuge ohne bekanntes Kennzeichen. */
const FLIGHT_HEX_BLOCKS = ['LX' => ['4D0000', '4D03FF']];

function flight_build(array $ctx, float $from, float $to): array
{
    $s = $ctx['settings'];
    $f = $s['flight'];
    if (trim((string) $f['pin']) !== '') {
        return flight_pinned_pages($ctx, $from, $to);
    }
    $raw = adsb_nearby((float) $s['location']['lat'], (float) $s['location']['lon'], (int) $f['radius']);
    $list = flight_filter($raw ?? [], $f);
    if (!$list) {
        return flight_empty_pages($ctx, $from, $to);
    }
    $byHex = [];
    foreach ($list as $ac) {
        $byHex[$ac['hex']] = $ac;
    }
    $pages = [];
    foreach (flight_timeline($ctx, $list, $from, $to) as $seg) {
        $a = max($from, (float) $seg['from']);
        $b = min($to, (float) $seg['to']);
        if ($b <= $a) {
            continue;
        }
        $ac = $byHex[$seg['hex']];
        $route = flight_route($ac);
        flight_remember_now($ctx, $ac, $route, $a, $b);
        flight_view_pages($pages, $ctx, $ac, $route, $a, $b, (float) $seg['since'], 'flight:' . $ac['hex'], true);
    }
    return $pages;
}

/**
 * Der Flug, der gerade auf dem Panel steht, fuer Home Assistant (api/ha.php). Nur aus dem
 * Abruf des Geraets, nie aus der Vorschau, und nur der Abschnitt, der jetzt laeuft. Home
 * Assistant liest ihn aus dem Zwischenspeicher und fragt adsb.lol so nie selbst.
 */
function flight_remember_now(array $ctx, array $ac, ?array $route, float $a, float $b): void
{
    $now = (float) ($ctx['now'] ?? microtime(true));
    if (!empty($ctx['preview']) || empty($ctx['device']['id']) || $now < $a || $now >= $b) {
        return;
    }
    $icao = flight_airline_icao($route, $ac['callsign'], $ac);
    $cs = $ac['callsign'] !== '' ? $ac['callsign'] : ($ac['reg'] !== '' ? $ac['reg'] : strtoupper($ac['hex']));
    cache_put('flightnow:' . (int) $ctx['device']['id'], [
        'callsign' => $ac['callsign'] !== '' ? $ac['callsign'] : null,
        'reg' => $ac['reg'] !== '' ? $ac['reg'] : null,
        'hex' => $ac['hex'],
        'airline' => flight_airline($route, $cs, $ac),
        // Nur echte ICAO-Kuerzel, nicht die eigenen aus zwei Buchstaben wie PL.
        'airline_icao' => strlen($icao) === 3 ? $icao : null,
        'type' => $ac['type'] !== '' ? aircraft_type_name($ac['type'], 20) : null,
        'alt_ft' => $ac['alt'],
        'speed_kt' => $ac['gs'] === null ? null : (int) round($ac['gs']),
        'dist_nm' => $ac['dst'],
        'from' => $route ? (($route['origin']['iata'] ?? '') ?: ($route['origin']['icao'] ?? null)) : null,
        'to' => $route ? (($route['destination']['iata'] ?? '') ?: ($route['destination']['icao'] ?? null)) : null,
        'from_city' => $route['origin']['city'] ?? null,
        'to_city' => $route['destination']['city'] ?? null,
        'military' => (bool) $ac['military'],
    ], 30);
}

/**
 * Seiten fuer einen Flug zwischen $a und $b. Die Ansichten zaehlen ab $since, dem
 * Moment, seit dem dieser Flug steht, damit ein neuer Flug mit der Route beginnt.
 * Dieselbe Ansicht ueber eine Abschnittsgrenze hinweg bleibt eine Seite. Mit $swap
 * bekommt die erste Seite eines neuen Flugs fx "swap" (ab Firmware 0.1.2 ein
 * Uebergang in 400 ms, aeltere schneiden hart).
 */
function flight_view_pages(array &$pages, array $ctx, array $ac, ?array $route, float $a, float $b, float $since, string $idPrefix, bool $swap = false): void
{
    $viewLen = max(FLIGHT_VIEW_MIN, (int) ($ctx['settings']['flight']['view'] ?? 4));
    $views = flight_views_now($ctx, $ac, $route);
    /* Eine Runde besteht aus den gewaehlten Ansichten; die Karte zaehlt doppelt. */
    $runde = [];
    $rundeLen = 0.0;
    foreach ($views as $v) {
        $len = (float) $viewLen * ($v === 'map' ? FLIGHT_MAP_FACTOR : 1);
        $runde[] = ['v' => $v, 'off' => $rundeLen, 'len' => $len];
        $rundeLen += $len;
    }
    if ($rundeLen <= 0) {
        return;
    }
    for ($t = $since + floor(($a - $since) / $rundeLen) * $rundeLen; $t < $b; $t += $rundeLen) {
        foreach ($runde as $teil) {
            $vs = max($a, $t + $teil['off']);
            $ve = min($b, $t + $teil['off'] + $teil['len']);
            if ($ve <= $vs) {
                continue;
            }
            $view = $teil['v'];
            $id = $idPrefix . ':' . $view;
            $prev = count($pages) - 1;
            if ($prev >= 0 && $pages[$prev]['id'] === $id && $pages[$prev]['to'] === (int) round($vs * 1000)) {
                $pages[$prev]['to'] = (int) round($ve * 1000);
                continue;
            }
            $extra = ['id' => $id];
            if ($swap && abs($vs - $since) < 0.0005) {
                $extra['fx'] = 'swap';
            }
            $pages[] = frame_page($vs, $ve, flight_ops($ac, $route, $view, $ctx), $extra);
        }
    }
}

/**
 * Welcher Flug wann steht, als Liste von Abschnitten {hex, since, from, to}.
 * Jeder Abschnitt dauert eine Standzeit. Ein Abschnitt, der schon laeuft, bleibt
 * bis zu seinem Ende so (Tabelle cache): sonst koennten zwei Abrufe kurz
 * nacheinander verschiedene Fluege zeigen, wenn zwei Flugzeuge fast gleich weit
 * weg sind. Abschnitte, die noch nicht begonnen haben, entscheidet jeder Abruf mit
 * frischen Daten neu. Verschwindet das Flugzeug aus dem Umkreis, sofort neu.
 */
function flight_timeline(array $ctx, array $list, float $from, float $to): array
{
    $dwell = max(FLIGHT_DWELL_MIN, (int) $ctx['settings']['flight']['dwell']);
    $key = flight_plan_key($ctx);
    $old = cache_get($key);
    $present = array_column($list, 'hex');
    $segs = [];
    $last = null;
    foreach (is_array($old) ? $old : [] as $seg) {
        if (!is_array($seg) || !isset($seg['hex'], $seg['since'], $seg['from'], $seg['to'])) {
            continue;
        }
        if ((float) $seg['to'] <= $from) {
            $last = $seg;
            continue;
        }
        if ((float) $seg['from'] > $from + 1.0 || !in_array($seg['hex'], $present, true)) {
            break;
        }
        $segs[] = $seg;
        $last = $seg;
    }
    $cursor = $segs ? (float) $segs[count($segs) - 1]['to'] : $from;
    $nearest = flight_pick($list, (bool) ($ctx['settings']['flight']['hold'] ?? true));
    while ($cursor < $to) {
        $same = $last !== null && $last['hex'] === $nearest && (float) $last['to'] >= $cursor - FRAME_TTL;
        $seg = ['hex' => $nearest, 'since' => $same ? (float) $last['since'] : $cursor, 'from' => $cursor, 'to' => $cursor + $dwell];
        $segs[] = $seg;
        $last = $seg;
        $cursor += $dwell;
    }
    cache_put($key, $segs, FLIGHT_PLAN_TTL);
    return $segs;
}

/** Eigener Plan fuer Geraet und Vorschau, neu, sobald sich Standzeit, Ort oder Filter aendern. */
function flight_plan_key(array $ctx): string
{
    $s = $ctx['settings'];
    $f = $s['flight'];
    $sig = substr(md5((string) json_encode([$f['dwell'], $f['radius'], $f['alt'], $s['location']['lat'], $s['location']['lon']])), 0, 12);
    return 'flightplan:' . ($ctx['preview'] ? 'preview:' : '') . (int) ($ctx['device']['id'] ?? 0) . ':' . $sig;
}

/** In der Luft, und ohne hohe Ueberflieger, wenn der Hoehenfilter an ist. */
function flight_filter(array $list, array $f): array
{
    return array_values(array_filter($list, static function (array $ac) use ($f): bool {
        if ($ac['ground']) {
            return false;
        }
        if (!empty($f['alt']) && $ac['alt'] !== null && $ac['alt'] > 30000) {
            return false;
        }
        return true;
    }));
}

/** Reihenfolge der Ansichten. Ohne Abflug- und Zielort bleiben Position und Messwerte. */
/**
 * Die Ansichten fuer diesen Flug. Startet oder landet er gerade in der Naehe eines
 * Flugplatzes, bleibt nur die Karte stehen: dabei will man zusehen, und die Textansichten
 * wuerden mitten im Anflug dazwischenfunken. Das gilt nur, wenn die Karte ueberhaupt
 * gewaehlt ist und das Geraet sie zeichnen kann.
 */
function flight_views_now(array $ctx, array $ac, ?array $route): array
{
    $f = $ctx['settings']['flight'];
    $karte = flight_map_ready($ctx);
    $views = flight_views($route, (array) ($f['views'] ?? []), $karte);
    /* Nur im Moment selbst, nicht den ganzen Anflug lang: die letzten Sekunden vor dem
       Aufsetzen und die ersten nach dem Abheben. Sonst steht die Karte fuenf Minuten
       still, waehrend das Flugzeug noch ueber dem Norden des Landes sinkt. */
    if (!empty($f['hold']) && $karte && in_array('map', $views, true) && flight_event($ac)
        && flight_at_airport($ac, flight_airport_for($ac, $route))) {
        return ['map'];
    }
    return $views;
}

/** Kann dieses Geraet die Karte zeichnen? Die Vorschau im Browser immer. */
function flight_map_ready(array $ctx): bool
{
    if (!empty($ctx['preview'])) {
        return true;
    }
    $fw = (string) ($ctx['device']['fw'] ?? '');
    return $fw !== '' && version_compare($fw, FLIGHT_MAP_FW, '>=');
}

/**
 * Welche Ansichten dieser Flug zeigt. Ohne bekannte Route fallen Route und Fortschritt
 * weg, ohne passende Firmware die Karte. Aus dem Rest bleibt, was der Besitzer gewaehlt
 * hat; waehlt er nur Ansichten, die dieser Flug nicht hat, zeigt das Panel lieber alles
 * als nichts.
 */
function flight_views(?array $route, array $chosen = [], bool $karte = false): array
{
    $hasRoute = $route && !empty($route['origin']) && !empty($route['destination']);
    $all = $hasRoute ? ['route', 'progress', 'position', 'metrics'] : ['position', 'metrics'];
    if ($karte) {
        $all[] = 'map';
    }
    if (!$chosen) {
        return $all;
    }
    $keep = array_values(array_filter($all, static fn(string $v): bool => in_array($v, $chosen, true)));
    return $keep ?: $all;
}

/**
 * Startet oder landet dieses Flugzeug gerade? Unter 10 000 Fuss und mehr als 300 Fuss
 * je Minute in Fahrt. Am Boden zaehlt es nicht, da ist nichts zu sehen.
 */
function flight_event(array $ac): bool
{
    return empty($ac['ground']) && $ac['alt'] !== null && $ac['alt'] < FLIGHT_EVENT_FT
        && $ac['vrate'] !== null && abs((int) $ac['vrate']) >= FLIGHT_EVENT_VR;
}

/**
 * Welcher Flug als Naechstes drankommt. Normal der naechstgelegene. Mit "hold" geht ein
 * Start oder eine Landung vor, und zwar so lange, wie er dauert: das Panel bleibt dran,
 * bis das Flugzeug ueber 10 000 Fuss ist oder den Umkreis verlaesst.
 */
function flight_pick(array $list, bool $hold): string
{
    if ($hold) {
        foreach ($list as $ac) {          // nach Entfernung sortiert, also der naechste zuerst
            if (flight_event($ac)) {
                return (string) $ac['hex'];
            }
        }
    }
    return (string) $list[0]['hex'];
}

/** Name fuer Zeile 1. $ac (reg, hex) braucht es fuer Rufzeichen, die an einem Land haengen. */
function flight_airline(?array $route, string $callsign, array $ac = []): string
{
    $icao = flight_airline_icao($route, $callsign, $ac);
    if ($icao !== '' && isset(FLIGHT_NAMES[$icao])) {
        return FLIGHT_NAMES[$icao];
    }
    $name = strtoupper(panel_text((string) ($route['airline'] ?? ''), true, 60));
    // Der Zusatz endet vor einem Leerzeichen oder am Ende: hinter "S.A." greift \b nicht, "SWIFTAIR ." bliebe stehen.
    $name = preg_replace('/\b(AIRLINES|AIRLINE|AIRWAYS|AIR LINES|INTERNATIONAL|LIMITED|LTD|GMBH|AG|PLC|INC|S\.?A\.?)(?=[\s,.]|$)/', '', $name) ?? '';
    $name = trim(preg_replace('/\s{2,}/', ' ', $name) ?? '', " .,\t");
    if ($name === '') {
        return panel_text($callsign, true, 15);
    }
    $out = '';
    foreach (preg_split('/\s+/', $name) as $word) {
        $try = $out === '' ? $word : $out . ' ' . $word;
        if (strlen($try) > 15) {
            break;
        }
        $out = $try;
    }
    return $out !== '' ? $out : substr($name, 0, 15);
}

/** Kuerzel fuer Logo und Namen. $ac (reg, hex) wie bei flight_airline(). */
function flight_airline_icao(?array $route, string $callsign, array $ac = []): string
{
    foreach (FLIGHT_CALLSIGN_OWNERS as $prefix => [$code, $country]) {
        if (str_starts_with($callsign, $prefix) && ($country === '' || flight_registered_in($ac, $country))) {
            return $code;
        }
    }
    if (!empty($route['airline_icao'])) {
        return strtoupper((string) $route['airline_icao']);
    }
    return preg_match('/^([A-Z]{3})\d/', $callsign, $m) ? $m[1] : '';
}

/** In diesem Land zugelassen? Nach dem Kennzeichen, ohne eins nach dem Block der Kennung. */
function flight_registered_in(array $ac, string $country): bool
{
    $reg = strtoupper(trim((string) ($ac['reg'] ?? '')));
    if ($reg !== '') {
        return str_starts_with($reg, $country . '-');
    }
    $hex = strtoupper((string) ($ac['hex'] ?? ''));
    [$lo, $hi] = FLIGHT_HEX_BLOCKS[$country] ?? ['', ''];
    return $lo !== '' && preg_match('/^[0-9A-F]{6}$/', $hex) === 1 && strcmp($hex, $lo) >= 0 && strcmp($hex, $hi) <= 0;
}

/* Wann eine Strecke zum Flugzeug passt, in Seemeilen. Geeicht am 24. September 2026 an
   238 Flugzeugen ueber New York, Chicago, Los Angeles, Atlanta, Luxemburg und Frankfurt:
   jede verworfene Strecke war nachweislich eine andere als die geflogene. */
const FLIGHT_LEG_BEHIND = 40;         // hinter dem Abflugort, so weit reicht eine Abflugroute
const FLIGHT_LEG_BEYOND = 60;         // hinter dem Ziel: Gegenanflug, Warteschleife
const FLIGHT_LEG_SIDE = [80, 0.2];    // quer zur Strecke: mindestens, Anteil der Streckenlaenge
const FLIGHT_LEG_DETOUR = [60, 0.25]; // Umweg ueber das Flugzeug: mindestens, Anteil
const FLIGHT_LEG_NEAR = 100;          // unter 10 000 Fuss steigend oder sinkend hoechstens so weit vom Platz
const FLIGHT_LEG_NEAR_HIGH = 250;     // dasselbe bis 25 000 Fuss

/**
 * Die Strecke, die dieses Flugzeug gerade fliegt, oder nur seine Airline. Beide Quellen
 * kennen je Rufzeichen eine Strecke, aber keinen Tag: dasselbe Rufzeichen fliegt am
 * Nachmittag den Rueckflug, in den USA oft eine ganz andere Strecke, und bei einer
 * Zwischenlandung erst die eine, dann die andere Teilstrecke. Bis zum 24. September 2026
 * stand deshalb ueber New York ein Flug, der gerade in JFK aufsetzte, mit "ARRIVING IN
 * ~1H" auf dem Panel: adsbdb kannte JBU168 noch als Charleston nach Boston. Jetzt gilt
 * eine Strecke nur, wenn sie zu Position und Bewegung passt (flight_route_leg), zuerst
 * aus den VRS-Standdaten, weil sie aktueller sind, dann aus adsbdb. Passt keine, bleibt
 * die Airline ohne Abflug und Ziel, und das Panel zeigt Position und Messwerte statt
 * einer falschen Ankunftszeit.
 */
function flight_route(array $ac): ?array
{
    if ((string) $ac['callsign'] === '') {
        return null;
    }
    return flight_route_pick($ac, adsbdb_route((string) $ac['callsign']), vrs_route((string) $ac['callsign']));
}

/**
 * Waehlt aus den Antworten von adsbdb und VRS, ohne Netz. Dieselbe Form wie
 * adsbdb_route(), dazu 'source' (vrs, adsbdb oder leer). origin und destination sind
 * null, wenn keine Strecke passt; die Airline bleibt dann fuer Name und Logo.
 */
function flight_route_pick(array $ac, ?array $db, ?array $vrs): ?array
{
    if ($db === null && $vrs === null) {
        return null;
    }
    $out = [
        'callsign_icao' => (string) ($db['callsign_icao'] ?? $ac['callsign']),
        'callsign_iata' => (string) ($db['callsign_iata'] ?? ''),
        'airline' => (string) ($db['airline'] ?? ''),
        'airline_icao' => (string) (($db['airline_icao'] ?? '') !== '' ? $db['airline_icao'] : ($vrs['airline_icao'] ?? '')),
        'origin' => null,
        'destination' => null,
        'source' => '',
    ];
    $quellen = [];
    if ($vrs !== null) {
        $quellen['vrs'] = (array) ($vrs['airports'] ?? []);
    }
    if ($db !== null) {
        $quellen['adsbdb'] = array_values(array_filter([$db['origin'] ?? null, $db['midpoint'] ?? null, $db['destination'] ?? null], 'is_array'));
    }
    foreach ($quellen as $quelle => $airports) {
        $leg = flight_route_leg($ac, $airports);
        if ($leg !== null) {
            return array_merge($out, ['origin' => $leg[0], 'destination' => $leg[1], 'source' => $quelle]);
        }
    }
    return $out;
}

/**
 * Welche Teilstrecke zum Flugzeug passt: [Abflugort, Ziel] oder null. $airports sind
 * die Flughaefen in Flugreihenfolge, jeder mit lat und lon. Von mehreren passenden
 * gewinnt die mit dem kleinsten Umweg und dem geradesten Kurs aufs Ziel.
 */
function flight_route_leg(array $ac, array $airports): ?array
{
    $best = null;
    $bestScore = INF;
    for ($i = 0; $i + 1 < count($airports); $i++) {
        $a = $airports[$i];
        $b = $airports[$i + 1];
        if (!is_array($a) || !is_array($b) || !isset($a['lat'], $a['lon'], $b['lat'], $b['lon'])) {
            continue;
        }
        $score = flight_leg_score($ac, (float) $a['lat'], (float) $a['lon'], (float) $b['lat'], (float) $b['lon']);
        if ($score !== null && $score < $bestScore) {
            $best = [$a, $b];
            $bestScore = $score;
        }
    }
    return $best;
}

/**
 * Wie gut eine Teilstrecke zum Flugzeug passt, kleiner ist besser, null heisst gar nicht.
 * Sie passt nicht, wenn das Flugzeug
 *   - weit hinter dem Abflugort, hinter dem Ziel oder neben der Strecke ist,
 *   - einen grossen Umweg bedeutete,
 *   - tief steigt und weit vom Abflugort ist, oder tief sinkt und weit vom Ziel,
 *   - nicht grob aufs Ziel zu fliegt: unterwegs hoechstens 90 Grad daneben, beim Steigen
 *     und Sinken 110. Nah an Start und Ziel zaehlt der Kurs nicht, dort kurven alle.
 */
function flight_leg_score(array $ac, float $aLat, float $aLon, float $bLat, float $bLon): ?float
{
    $lat = (float) $ac['lat'];
    $lon = (float) $ac['lon'];
    $d = geo_nm($aLat, $aLon, $bLat, $bLon);
    if ($d < 5) {
        return null; // Rundflug oder kaputter Eintrag
    }
    $done = geo_nm($aLat, $aLon, $lat, $lon);
    $left = geo_nm($lat, $lon, $bLat, $bLon);
    // Lage zur Strecke: Abstand quer zur Grosskreislinie und Weg entlang der Linie.
    $r = 3440.065;
    $w = deg2rad(geo_bearing($aLat, $aLon, $lat, $lon) - geo_bearing($aLat, $aLon, $bLat, $bLon));
    $quer = asin(max(-1.0, min(1.0, sin($done / $r) * sin($w)))) * $r;
    $laengs = acos(max(-1.0, min(1.0, cos($done / $r) / max(1e-9, cos($quer / $r))))) * $r;
    if (cos($w) < 0) {
        $laengs = -$laengs;
    }
    if (($laengs < 0 && $done > FLIGHT_LEG_BEHIND) || ($laengs > $d && $left > FLIGHT_LEG_BEYOND)) {
        return null;
    }
    if ($laengs >= 0 && $laengs <= $d && abs($quer) > max(FLIGHT_LEG_SIDE[0], FLIGHT_LEG_SIDE[1] * $d)) {
        return null;
    }
    $umweg = $done + $left - $d;
    $erlaubt = max(FLIGHT_LEG_DETOUR[0], FLIGHT_LEG_DETOUR[1] * $d);
    if ($umweg > $erlaubt) {
        return null;
    }
    $alt = $ac['alt'] ?? null;
    $vr = (int) ($ac['vrate'] ?? 0);
    $tief = $alt !== null && $alt < FLIGHT_EVENT_FT;
    $mittel = $alt !== null && $alt < 25000;
    $steigt = $vr >= FLIGHT_EVENT_VR;
    $sinkt = $vr <= -FLIGHT_EVENT_VR;
    if ($tief && (($steigt && $done > FLIGHT_LEG_NEAR) || ($sinkt && $left > FLIGHT_LEG_NEAR))) {
        return null;
    }
    if ($mittel && !$tief && (($vr >= 500 && $done > FLIGHT_LEG_NEAR_HIGH) || ($vr <= -500 && $left > FLIGHT_LEG_NEAR_HIGH))) {
        return null;
    }
    $kursab = 0.0;
    $richtung = 0.0;
    if (($ac['track'] ?? null) !== null && (float) ($ac['gs'] ?? 0) > 100) {
        $trk = (float) $ac['track'];
        [$pruefen, $grenze] = match (true) {
            $steigt && $mittel => [$left > 25 && $done > 20, 110],
            $sinkt && $mittel => [$left > 40 && $done > 20, 110],
            default => [$left > 60 && $done > 40, 90],
        };
        if ($pruefen) {
            $kursab = flight_angle(geo_bearing($lat, $lon, $bLat, $bLon), $trk);
            if ($kursab > $grenze) {
                return null;
            }
        }
        // Nah an einem Platz zaehlt, ob das Flugzeug auf ihn zu oder von ihm weg fliegt. Das
        // entscheidet bei einer Zwischenlandung, solange es waagerecht fliegt: auf das Ziel
        // zu ist die ankommende Teilstrecke, vom Abflugort weg die abgehende. Es verwirft
        // nichts, Gegenanflug und Abflugrouten fuehren auch mal in die andere Richtung.
        if ($left <= 40 && flight_angle(geo_bearing($lat, $lon, $bLat, $bLon), $trk) > 90) {
            $richtung += 0.5;
        }
        if ($done <= 40 && $done > 1 && flight_angle(geo_bearing($aLat, $aLon, $lat, $lon), $trk) > 90) {
            $richtung += 0.5;
        }
    }
    return $umweg / $erlaubt + $kursab / 180 + $richtung;
}

/** Winkel zwischen zwei Kursen, 0 bis 180 Grad. */
function flight_angle(float $a, float $b): float
{
    return abs(fmod($a - $b + 540.0, 360.0) - 180.0);
}

function flight_route_text(?array $route): string
{
    if (!$route || empty($route['origin']) || empty($route['destination'])) {
        return '';
    }
    $o = $route['origin']['iata'] ?: $route['origin']['icao'];
    $d = $route['destination']['iata'] ?: $route['destination']['icao'];
    return panel_text($o . '→' . $d, true, 15);
}

/* Annahmen fuer die geschaetzten Zeiten: das Reisetempo eines grossen Flugzeugs, das im
   Steig- oder Sinkflug noch langsam ist, das Mittel auf den letzten 100 Seemeilen
   (Sinkflug und Anflug), und wie schnell ein Flugzeug im Mittel Hoehe abbaut. */
const FLIGHT_CRUISE_KT = 420.0;
const FLIGHT_APPROACH_KT = 250.0;
const FLIGHT_DESCENT_FPM = 1500;

/**
 * Anteil der Strecke, Restzeit und Zeit seit dem Abflug, alles nur mit bekannter
 * Route und alles geschaetzt. Restzeit: verbleibende Strecke durch das Tempo ueber
 * Grund, die letzten 100 Seemeilen mit hoechstens 250 Knoten, und nie kuerzer, als der
 * Abstieg aus der jetzigen Hoehe dauert. Ist ein grosses Flugzeug unter 20 000 Fuss und
 * noch mehr als 100 Seemeilen vom Ziel, steigt es noch oder fliegt nach dem Start kurz
 * waagerecht: fuer den Rest gilt das Reisetempo von 420 Knoten, sonst stand beim Start in
 * LaGuardia nach Kansas City "ARRIVING IN ~6H 50M". Seit dem Abflug: geflogene Strecke durch das
 * Tempo, fuer ein grosses Flugzeug unter 20 000 Fuss ebenfalls 420 Knoten. Unter 3
 * Seemeilen seit dem Start heisst es "gerade".
 */
function flight_progress(array $ac, ?array $route): array
{
    $out = ['pct' => null, 'left' => null, 'ago' => null];
    $o = $route['origin'] ?? null;
    $d = $route['destination'] ?? null;
    if (!$o || !$d || $o['lat'] === null || $d['lat'] === null) {
        return $out;
    }
    $done = geo_nm((float) $o['lat'], (float) $o['lon'], $ac['lat'], $ac['lon']);
    $left = geo_nm($ac['lat'], $ac['lon'], (float) $d['lat'], (float) $d['lon']);
    if ($done + $left > 0) {
        $out['pct'] = max(0, min(100, (int) round(100 * $done / ($done + $left))));
    }
    $big = in_array($ac['category'] ?? '', ['A3', 'A4', 'A5', 'A6'], true);
    $tief = $ac['alt'] === null || $ac['alt'] < 20000;
    $gs = $ac['gs'];
    if ($gs !== null && $gs > 50) {
        $v = (float) $gs;
        if ($big && $tief && $left > 100) {
            $v = max($v, FLIGHT_CRUISE_KT);
        }
        $langsam = min($left, 100.0);
        $secs = (($left - $langsam) / $v + $langsam / min($v, FLIGHT_APPROACH_KT)) * 3600;
        if ($ac['alt'] !== null && $ac['alt'] > 0) {
            $secs = max($secs, $ac['alt'] / FLIGHT_DESCENT_FPM * 60);
        }
        if ($secs < 20 * 3600) {
            $out['left'] = (int) $secs;
        }
    }
    if ($done < 3) {
        $out['ago'] = 0;
    } else {
        $v = ($big && $tief) ? FLIGHT_CRUISE_KT : $gs;
        if ($v !== null && $v > 50 && $done / $v < 20) {
            $out['ago'] = (int) ($done / $v * 3600);
        }
    }
    return $out;
}

/** Dauer als "~25 MIN" oder "~1H 20M": unter 20 Minuten genau, darunter in Fuenfern, ab einer Stunde in Zehnern. */
function flight_span(int $secs): string
{
    $min = (int) round($secs / 60);
    if ($min < 20) {
        return '~' . max(1, $min) . ' MIN';
    }
    $min = $min < 60 ? (int) (round($min / 5) * 5) : (int) (round($min / 10) * 10);
    if ($min < 60) {
        return '~' . $min . ' MIN';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return '~' . $h . 'H' . ($m > 0 ? ' ' . $m . 'M' : '');
}

function flight_departed_text(?int $ago, bool $de): string
{
    if ($ago === null) {
        return '';
    }
    if ($ago === 0) {
        return $de ? 'GERADE GESTARTET' : 'JUST DEPARTED';
    }
    return $de ? 'ABFLUG VOR ' . flight_span($ago) : 'DEPARTED ' . flight_span($ago) . ' AGO';
}

function flight_arriving_text(?int $left, bool $de): string
{
    if ($left === null) {
        return '';
    }
    if ($left < 120) {
        return $de ? 'LANDET JETZT' : 'ARRIVING NOW';
    }
    return ($de ? 'ANKUNFT IN ' : 'ARRIVING IN ') . flight_span($left);
}

/** "ZANESVILLE, OHIO" oder "ARLON, BELGIUM", ohne Region, wenn es sonst nicht in 21 Zeichen passt. */
function flight_place_text(array $geo): string
{
    $place = panel_text((string) ($geo['place'] ?? ''), true, PANEL_LINE_MAX);
    $region = panel_text((string) ($geo['region'] ?? ''), true, PANEL_LINE_MAX);
    $both = $region !== '' ? $place . ', ' . $region : $place;
    return strlen($both) <= PANEL_LINE_MAX ? $both : $place;
}

/**
 * Hoehe: in tausend Fuss wie bei theflightwall (7.0KFT, ab 10 000 Fuss ganze Zahlen,
 * damit die Zeile passt) oder in Metern, auf 10 gerundet (2130M).
 */
function flight_alt_text(array $ac, string $unit = 'ft'): string
{
    if ($ac['ground']) {
        return 'GND';
    }
    if ($ac['alt'] === null) {
        return '?';
    }
    if ($unit === 'm') {
        return (int) (round(max(0, $ac['alt']) * 0.3048 / 10) * 10) . 'M';
    }
    $k = $ac['alt'] / 1000;
    return ($k < 10 ? sprintf('%.1f', max(0, $k)) : (string) (int) round($k)) . 'KFT';
}

/** Tempo ueber Grund: 485KMH, 262KT oder 301MPH. */
function flight_speed_text(?float $kt, string $unit = 'kmh'): string
{
    if ($kt === null) {
        return '?';
    }
    return match ($unit) {
        'kt' => (int) round($kt) . 'KT',
        'mph' => (int) round($kt * 1.15078) . 'MPH',
        default => (int) round($kt * 1.852) . 'KMH',
    };
}

/** Steigrate: Meter pro Sekunde ("+3.3M/S", unter 0,05 einfach 0) oder Fuss pro Minute ("+640FPM", auf 10 gerundet). */
function flight_vrate_text(?int $fpm, string $unit = 'ms'): string
{
    if ($fpm === null) {
        return '?';
    }
    if ($unit === 'fpm') {
        $f = (int) (round($fpm / 10) * 10);
        return ($f === 0 ? '0' : sprintf('%+d', $f)) . 'FPM';
    }
    $ms = $fpm * 0.00508;
    if (abs($ms) < 0.05) {
        return '0M/S';
    }
    return (abs($ms) < 10 ? sprintf('%+.1f', $ms) : sprintf('%+d', (int) round($ms))) . 'M/S';
}

/** Entfernung: 12.3NM, 22.8KM oder 14.2MI, ab 100 ohne Nachkommastelle. */
function flight_dist_text(float $nm, string $unit = 'nm'): string
{
    [$v, $u] = match ($unit) {
        'km' => [$nm * 1.852, 'KM'],
        'mi' => [$nm * 1.15078, 'MI'],
        default => [$nm, 'NM'],
    };
    return ($v < 100 ? sprintf('%.1f', $v) : (string) (int) round($v)) . $u;
}

/** Gruener Balken fuer das Geflogene, Punkte fuer den Rest. Firmware 0.1.0 kennt dot nicht und zeichnet den Rest voll. */
function flight_bar_ops(?int $pct): array
{
    if ($pct === null) {
        return [];
    }
    $w = (int) round(128 * $pct / 100);
    $ops = [];
    if ($w < 128) {
        $ops[] = op_rect($w, 61, 128 - $w, 2, FLIGHT_DOTS) + ['dot' => 2];
    }
    if ($w > 0) {
        $ops[] = op_bar(0, 61, $w, C_GREEN);
    }
    return $ops;
}

function flight_ops(array $ac, ?array $route, string $view, array $ctx): array
{
    if ($view === 'map') {
        return flight_map_ops($ctx, $ac, $route);
    }
    $s = $ctx['settings'];
    $f = $s['flight'];
    $de = $ctx['lang'] === 'de';
    $cs = $ac['callsign'] !== '' ? $ac['callsign'] : ($ac['reg'] !== '' ? $ac['reg'] : strtoupper($ac['hex']));
    $l1colour = (!empty($f['mil']) && $ac['military']) ? C_GREEN : C_ACCENT;
    $type = panel_text(aircraft_type_name($ac['type']), false, 15);
    $ops = [op_logo(flight_airline_icao($route, $ac['callsign'], $ac))];
    $routeText = flight_route_text($route);
    // Laeuft ein Timer, steht oben rechts seine Ecke: Zeile 1 endet dann nach neun Zeichen
    // (LUFTHANSA statt LUFTHANSA CITY), sonst wie immer nach fuenfzehn.
    $l1max = empty($ctx['corner']) ? 15 : 9;

    if ($view === 'route') {
        $ops[] = op_text(38, 2, panel_text(flight_airline($route, $cs, $ac), true, $l1max), $l1colour);
        $ops[] = op_text(38, 14, $routeText, C_WHITE);
        $ops[] = op_text(38, 26, $type, C_CYAN);
        $ops[] = op_text(2, 38, panel_text((string) ($route['origin']['city'] ?? ''), true, 21), C_WHITE);
        $ops[] = op_text(2, 50, panel_text((string) ($route['destination']['city'] ?? ''), true, 21), C_DIM);
        return $ops;
    }

    $ops[] = op_text(38, 2, panel_text($cs, true, $l1max), $l1colour);
    $ops[] = op_text(38, 14, $view === 'metrics' || $routeText === '' ? flight_dist_text((float) $ac['dst'], (string) ($f['udist'] ?? 'nm')) : $routeText, C_WHITE);
    $ops[] = op_text(38, 26, $type, C_CYAN);

    if ($view === 'metrics') {
        $alt = flight_alt_text($ac, (string) ($f['ualt'] ?? 'ft'));
        $spd = flight_speed_text($ac['gs'], (string) ($f['uspd'] ?? 'kmh'));
        $trk = $ac['track'] === null ? '?' : (string) $ac['track'];
        $vr = flight_vrate_text($ac['vrate'], (string) ($f['uvr'] ?? 'ms'));
        $l4 = 'ALT:' . $alt . ',SPD:' . $spd;
        if (strlen($l4) > PANEL_LINE_MAX) {
            // 11280M mit 1278KMH im Jetstream: kurze Bezeichner statt einer abgeschnittenen Zahl.
            $l4 = 'A:' . $alt . ',S:' . $spd;
        }
        $l5 = 'TRK:' . $trk . 'DEG,VR:' . $vr;
        if (strlen($l5) > PANEL_LINE_MAX) {
            $l5 = 'TRK:' . $trk . ',VR:' . $vr;
        }
        $ops[] = op_text(2, 38, panel_text($l4), C_WHITE);
        $ops[] = op_text(2, 50, panel_text($l5), C_DIM);
        return $ops;
    }

    $p = flight_progress($ac, $route);
    if ($view === 'progress') {
        $dep = flight_departed_text($p['ago'], $de);
        $arr = flight_arriving_text($p['left'], $de);
        if ($dep !== '') {
            $ops[] = op_text(2, 38, $dep, C_WHITE);
        }
        if ($arr !== '') {
            $ops[] = op_text(2, 50, $arr, C_DIM);
        }
        return array_merge($ops, flight_bar_ops($p['pct']));
    }

    // position: der Ort unter dem Flugzeug. Ohne Antwort von Nominatim die Koordinaten.
    // Die Startseite reicht den Ort fest herein (welcome_demo_pages), ohne Abfrage.
    $geo = $ctx['geo'] ?? geocode_reverse($ac['lat'], $ac['lon'], $ctx['lang']);
    if (($geo['status'] ?? '') === 'ok') {
        $ops[] = op_text(2, 38, $de ? 'FLIEGT UEBER' : 'FLYING OVER', C_DIM);
        $ops[] = op_text(2, 50, flight_place_text($geo), C_WHITE);
    } else {
        $ops[] = op_text(2, 38, 'POSITION', C_DIM);
        $ops[] = op_text(2, 50, sprintf('%.2f%s %.2f%s', abs($ac['lat']), $ac['lat'] >= 0 ? 'N' : 'S', abs($ac['lon']), $ac['lon'] >= 0 ? 'E' : 'W'), C_WHITE);
    }
    return array_merge($ops, flight_bar_ops($p['pct']));
}

/**
 * Ein angehefteter Flug, gesucht ueber die ganze Welt (adsb.lol nach Rufzeichen,
 * Flugnummer oder Kennzeichen). Dieselben Ansichten wie im Umkreis, gezaehlt ab
 * einer vollen Runde der Uhr, damit Geraet und Vorschau dieselbe zeigen.
 */
function flight_pinned_pages(array $ctx, float $from, float $to): array
{
    $s = $ctx['settings'];
    $pin = (string) $s['flight']['pin'];
    $de = $ctx['lang'] === 'de';
    $ac = adsb_find($pin, (float) $s['location']['lat'], (float) $s['location']['lon']);
    if (!$ac || !empty($ac['missing'])) {
        $h24 = (bool) $s['clock']['h24'];
        return [frame_page($from, $to, [
            op_text('c', 14, panel_text($pin, true, 21), C_ACCENT),
            op_text('c', 28, $de ? 'NOCH NICHT GESEHEN' : 'NOT SEEN YET', C_DIM),
            op_rect(0, 44, 128, 1, C_LINE),
            op_clock((int) round((128 - panel_width(str_repeat('0', clock_text_length($h24, false)))) / 2), 50, 1, C_DIM, $h24, false, false),
        ], ['id' => 'pin:missing'])];
    }
    $route = flight_route($ac);
    $views = flight_views_now($ctx, $ac, $route);
    $viewLen = max(FLIGHT_VIEW_MIN, (int) ($s['flight']['view'] ?? 4));
    $round = 0;
    foreach ($views as $v) {
        $round += $viewLen * ($v === 'map' ? FLIGHT_MAP_FACTOR : 1);
    }
    $round = max($viewLen, $round);
    $pages = [];
    flight_remember_now($ctx, $ac, $route, $from, $to);
    flight_view_pages($pages, $ctx, $ac, $route, $from, $to, floor($from / $round) * $round, 'pin:' . $ac['hex']);
    return $pages;
}

/**
 * Nichts in der Luft: Uhr, Warten oder Ruhezustand, wie eingestellt. Antwortet
 * adsb.lol nicht, sieht das genauso aus. Bis zum 19. September 2026 stand dann unten
 * rechts ein roter Punkt; er ist weg, weil ein Ausfall von ein paar Sekunden nichts
 * ist, was man auf einem ruhigen Bildschirm sehen will. Nachzulesen bleibt er im
 * Admin-Bereich unter den Ereignissen (Quelle ADSBLOL).
 */
function flight_empty_pages(array $ctx, float $from, float $to): array
{
    $s = $ctx['settings'];
    $ops = match ($s['flight']['empty']) {
        'wait' => [op_anim('waiting', ['lang' => $ctx['lang'], 'h24' => (bool) $s['clock']['h24']])],
        'off' => [op_anim('resting', ['h24' => (bool) $s['clock']['h24']])],
        default => clock_ops($ctx),
    };
    return [frame_page($from, $to, $ops, ['id' => 'empty:' . $s['flight']['empty']])];
}
