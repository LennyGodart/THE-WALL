<?php
/*
 * Modus Nahverkehr, nachgebaut nach dem Entwurf Transit.dc.html. Abfahrten von bis zu
 * drei Haltestellen aus der OpenAPI der ATP (services/transit.php).
 *
 * Kopf: Name der Haltestelle links in Grau, rechts je Verkehrsmittel ein Quadrat
 * 5x5, eine Linie bei y 9. Darunter 3, 4 oder 5 Zeilen. Eine Zeile wird von
 * rechts nach links gesetzt: Zeit rechtsbuendig an Spalte 126, davor ein Zeichen
 * (X faellt aus, ! teilweise, + Zusatzfahrt, E Ersatzverkehr), davor bei Zuegen
 * das Gleis. Links steht die Linie, bei Zuegen die Gattung, und das Ziel bekommt,
 * was dazwischen frei bleibt. Feste Spalten brechen, sobald man von Minuten auf
 * Uhrzeit umschaltet: aus einem Zeichen werden fuenf.
 *
 * Bei drei und vier Zeilen unten ein Streifen: Uhr und MOBILITEIT, oder eine
 * Meldung als Laufschrift. Farben aus der Marke, Bus Bernstein, Zug Cyan, Tram
 * Gruen. Die Zahl ist immer die Echtzeit, eine Verspaetung faerbt sie bernstein.
 */

declare(strict_types=1);

const TRANSIT_STOPS_MAX = 3;
/* So viele Zeichen hat der Name im Kopf, mit drei Quadraten fuer Bus, Zug und Tram einer weniger. */
const TRANSIT_SHORT_MAX = 18;
const TRANSIT_CHAR = 6;
const TRANSIT_RIGHT = 126;
/* Gedimmte LED fuer den alten Stand, Ausnahme Panel-Simulation in CLAUDE.md. Unter
   Helligkeit 96 (nachts) verschwaenden sie auf dem Panel, dann gelten die helleren. */
const TRANSIT_DIM_CODE = '7A5200';
const TRANSIT_DIM_TEXT = '4E5A63';
const TRANSIT_DIM_CODE_NIGHT = 'B37800';
const TRANSIT_DIM_TEXT_NIGHT = '8E9AA3';
const TRANSIT_TROUBLE_BAR = '3A1008';

mode_register('transit', [
    'order' => 35,
    'label' => ['en' => 'Departures', 'de' => 'Nahverkehr'],
    'rotatable' => true,
    'defaults' => ['stops' => [], 'modes' => TRANSIT_MODES, 'hide' => [], 'rows' => 4, 'walk' => 0, 'fmt' => 'min', 'notes' => true, 'school' => false],
    'sanitize' => 'transit_sanitize',
    'build' => 'transit_build',
    'enter' => 'drop',
]);

/** Eingaben der Geraeteseite. Namen und Linien kommen vom Browser und werden nur beschnitten, angezeigt wird alles als Text. */
function transit_sanitize(array $in, array $cur): array
{
    $out = $cur;
    $clean = static fn(mixed $v, int $max): string => is_scalar($v) ? str_cut(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $v) ?? ''), $max) : '';
    if (isset($in['stops']) && is_array($in['stops'])) {
        $stops = [];
        foreach ($in['stops'] as $s) {
            $id = is_array($s) ? $clean($s['id'] ?? '', 12) : '';
            if (!preg_match('/^\d{3,12}$/', $id) || in_array($id, array_column($stops, 'id'), true)) {
                continue;
            }
            $modes = array_values(array_filter(TRANSIT_MODES, static fn(string $m): bool => in_array($m, is_array($s['modes'] ?? null) ? $s['modes'] : [], true)));
            $lines = [];
            foreach (is_array($s['lines'] ?? null) ? $s['lines'] : [] as $l) {
                if (!is_array($l) || !in_array($l['mode'] ?? null, TRANSIT_MODES, true)) {
                    continue;
                }
                $code = panel_text($clean($l['code'] ?? '', 12), true, 8);
                if ($code !== '' && !isset($lines[$l['mode'] . ':' . $code])) {
                    $lines[$l['mode'] . ':' . $code] = ['code' => $code, 'mode' => $l['mode']];
                }
                if (count($lines) >= 60) {
                    break;
                }
            }
            $name = $clean($s['name'] ?? '', 80);
            $stops[] = ['id' => $id, 'name' => $name !== '' ? $name : $id, 'short' => $clean($s['short'] ?? '', TRANSIT_SHORT_MAX), 'modes' => $modes, 'lines' => array_values($lines)];
            if (count($stops) >= TRANSIT_STOPS_MAX) {
                break;
            }
        }
        $out['stops'] = $stops;
    }
    if (isset($in['modes']) && is_array($in['modes'])) {
        $out['modes'] = array_values(array_filter(TRANSIT_MODES, static fn(string $m): bool => in_array($m, $in['modes'], true)));
    }
    if (isset($in['hide']) && is_array($in['hide'])) {
        $hide = [];
        foreach ($in['hide'] as $h) {
            $code = panel_text($clean($h, 12), true, 8);
            if ($code !== '' && !in_array($code, $hide, true)) {
                $hide[] = $code;
            }
            if (count($hide) >= 60) {
                break;
            }
        }
        $out['hide'] = $hide;
    }
    if (array_key_exists('rows', $in)) {
        $out['rows'] = clamp_int($in['rows'], 3, 5, (int) $cur['rows']);
    }
    if (array_key_exists('walk', $in)) {
        $out['walk'] = clamp_int($in['walk'], 0, 15, (int) $cur['walk']);
    }
    if (isset($in['fmt']) && in_array($in['fmt'], ['min', 'clock'], true)) {
        $out['fmt'] = $in['fmt'];
    }
    foreach (['notes', 'school'] as $b) {
        if (array_key_exists($b, $in)) {
            $out[$b] = (bool) $in[$b];
        }
    }
    return $out;
}

/**
 * Seiten fuer [von, bis]. Die Tafeln werden einmal geholt, die Seiten wechseln an
 * jeder vollen Minute, damit die Minutenzahl zur richtigen Zeit springt.
 */
function transit_build(array $ctx, float $from, float $to): array
{
    $boards = transit_collect($ctx);
    $cuts = [$from];
    for ($m = (floor($from / 60) + 1) * 60; $m < $to; $m += 60) {
        $cuts[] = (float) $m;
    }
    $cuts[] = $to;
    $pages = [];
    for ($i = 0; $i < count($cuts) - 1; $i++) {
        if ($cuts[$i + 1] > $cuts[$i]) {
            // bands: obere Kante jeder Zeile, damit die Firmware beim Einstieg (drop) weiss, welche Zeile wann einfaellt.
            $pages[] = frame_page($cuts[$i], $cuts[$i + 1], transit_ops($ctx, $boards, (int) floor($cuts[$i])), ['id' => 'transit', 'bands' => transit_row_ys((int) $ctx['settings']['transit']['rows'])]);
        }
    }
    return $pages;
}

/** Die Tafeln aller gewaehlten Haltestellen, einmal je Aufbau. */
function transit_collect(array $ctx): array
{
    $out = ['configured' => transit_configured(), 'boards' => []];
    if (!$out['configured']) {
        return $out;
    }
    foreach (array_slice($ctx['settings']['transit']['stops'] ?? [], 0, TRANSIT_STOPS_MAX) as $stop) {
        $out['boards'][] = transit_board((string) $stop['id']);
    }
    return $out;
}

/** Zeichenbefehle fuer einen Zeitpunkt. */
function transit_ops(array $ctx, array $boards, int $now): array
{
    $low = frame_brightness($ctx['settings']) < 96;
    $ctx['dim_code'] = $low ? TRANSIT_DIM_CODE_NIGHT : TRANSIT_DIM_CODE;
    $ctx['dim_text'] = $low ? TRANSIT_DIM_TEXT_NIGHT : TRANSIT_DIM_TEXT;
    $s = $ctx['settings'];
    $t = $s['transit'];
    $de = $ctx['lang'] === 'de';
    $stops = array_slice($t['stops'] ?? [], 0, TRANSIT_STOPS_MAX);
    $ops = [];

    if (!$stops) {
        $ops[] = op_text('c', 22, $de ? 'NAHVERKEHR' : 'DEPARTURES', C_DIM);
        $ops[] = op_text('c', 36, $de ? 'HALTESTELLE WAEHLEN' : 'PICK A STOP', C_ACCENT);
        return $ops;
    }

    $enabled = array_values(array_intersect(TRANSIT_MODES, $t['modes'] ?? TRANSIT_MODES));
    $present = [];
    foreach ($stops as $st) {
        foreach ($st['modes'] ?? [] as $m) {
            $present[$m] = true;
        }
    }
    $ticks = array_values(array_filter(TRANSIT_MODES, static fn(string $m): bool => isset($present[$m]) && in_array($m, $enabled, true)));
    $set = transit_set(count($stops), $ticks);
    $first = $stops[0];
    transit_header($ops, (string) ($first['short'] ?? '') !== '' ? (string) $first['short'] : transit_short_name(transit_split_name((string) $first['name']) + ['name' => (string) $first['name']]), $ticks, !empty($ctx['corner']));

    if (!$boards['configured']) {
        transit_trouble($ops, $ctx, [], $set, $de ? 'KEIN SCHLUESSEL' : 'NO KEY', null);
        return $ops;
    }

    $queues = [];
    $stale = [];
    $staleAt = null;
    $next = null;
    foreach ($boards['boards'] as $b) {
        if ($b['ok']) {
            $queues[] = transit_visible($b['departures'], $ctx, $now);
            if (!empty($b['next'])) {
                $cand = transit_panel_dep($b['next'], (string) $s['tz']);
                if ($cand && $cand['ts'] > $now && ($next === null || $cand['ts'] < $next['ts'])) {
                    $next = $cand;
                }
            }
        } elseif (!empty($b['stale'])) {
            $stale[] = transit_visible($b['stale']['departures'], $ctx, $now);
            $at = transit_ts((string) ($b['stale']['fetched_at'] ?? ''));
            $staleAt = $at !== null ? min($staleAt ?? $at, $at) : $staleAt;
        }
    }

    if (!$queues) {
        transit_trouble($ops, $ctx, transit_pick_rows($stale, 2), $set, $de ? 'KEINE DATEN' : 'NO DATA', $staleAt);
        return $ops;
    }

    $rows = transit_pick_rows($queues, (int) $t['rows']);
    if (!$rows) {
        $ops[] = op_text('c', 24, $de ? 'KEINE ABFAHRTEN' : 'NO DEPARTURES', C_WHITE);
        if ($next !== null) {
            $ops[] = op_text('c', 38, ($de ? 'NAECHSTE ' : 'NEXT ') . $next['time'], C_DIM);
        }
        transit_strip($ops, $ctx, null);
        return $ops;
    }

    $ys = transit_row_ys((int) $t['rows']);
    foreach ($rows as $i => $dep) {
        transit_row($ops, $ys[$i], $dep, $set, $ctx, false);
    }
    transit_strip($ops, $ctx, transit_row_note($rows));
    return $ops;
}

/**
 * Die Meldung fuer die Laufschrift gehoert zu einer Zeile mit Zeichen, wie im Entwurf:
 * zuerst ein Ausfall, dann Teilausfall, Zusatzfahrt, Ersatzverkehr. Eine Zeile ohne
 * Zeichen bekommt keine Laufschrift, auch wenn HAFAS einen Hinweis mitschickt
 * ("Changement d'horaire possible" an einer geaenderten Fahrt). Vorher lief die
 * Meldung der ersten Zeile mit Hinweis, und die eines Teilausfalls weiter unten nie
 * (Befund B3).
 */
function transit_row_note(array $rows): ?string
{
    $best = null;
    $rank = PHP_INT_MAX;
    foreach ($rows as $dep) {
        $r = match (true) {
            $dep['cancelled'] => 0,
            $dep['part'] => 1,
            $dep['additional'] => 2,
            $dep['replacement'] => 3,
            default => null,
        };
        if ($r !== null && $r < $rank && ($dep['note'] ?? null) !== null) {
            $best = $dep['note'];
            $rank = $r;
        }
    }
    return $best;
}

/** train: nur Zuege, Gattung mit drei Zeichen und Gleis. mixed: mehrere Haltestellen oder Verkehrsmittel. Sonst bus oder tram. */
function transit_set(int $stopCount, array $ticks): string
{
    if ($ticks === ['train']) {
        return 'train';
    }
    if ($stopCount > 1 || count($ticks) > 1) {
        return 'mixed';
    }
    return $ticks[0] ?? 'bus';
}

function transit_mode_colour(string $mode): string
{
    return match ($mode) {
        'train' => C_CYAN,
        'tram' => C_GREEN,
        default => C_ACCENT,
    };
}

/**
 * Kopf: Name links, rechts ein Quadrat je Verkehrsmittel, Linie bei y 9. Der Name endet vor
 * dem ersten Quadrat. Laeuft ein Timer, steht rechts seine Ecke statt der Quadrate.
 */
function transit_header(array &$ops, string $name, array $ticks, bool $corner = false): void
{
    $x = TRANSIT_RIGHT;
    $firstTick = $corner ? CORNER_SPOT['box'][0] : 128;
    foreach ($corner ? [] : array_reverse($ticks) as $m) {
        $ops[] = op_rect($x - 4, 2, 5, 5, transit_mode_colour($m));
        $firstTick = $x - 4;
        $x -= 7;
    }
    $chars = min(TRANSIT_SHORT_MAX, intdiv($firstTick - 2 - 2 + 1, TRANSIT_CHAR));
    $ops[] = op_text(2, 1, panel_text($name, true, $chars), C_DIM);
    $ops[] = op_rect(0, 9, 128, 1, C_LINE);
}

/** Zeilenhoehen wie im Entwurf: vier mit Streifen, drei mit mehr Luft, fuenf fuellen das Panel. */
function transit_row_ys(int $rows): array
{
    return match (true) {
        $rows >= 5 => [12, 22, 32, 42, 52],
        $rows === 3 => [13, 27, 41],
        default => [12, 24, 36, 48],
    };
}

/**
 * Eine Zeile, von rechts nach links. Jede Spalte ist gerechnet, keine geschaetzt.
 * $dim ist der alte Stand: dieselben Spalten, nur leiser.
 */
function transit_row(array &$ops, int $y, array $dep, string $set, array $ctx, bool $dim): void
{
    $t = $ctx['settings']['transit'];
    $codeChars = $set === 'train' ? 3 : 4;
    $destX = 2 + $codeChars * TRANSIT_CHAR + 2;
    $edge = TRANSIT_RIGHT;

    if (!$dep['cancelled']) {
        if (($t['fmt'] ?? 'min') === 'clock') {
            $txt = $dep['time'];
            $col = $dep['delay'] > 0 ? C_ACCENT : C_WHITE;
        } else {
            $m = $dep['wait'];
            $txt = $m <= 0 ? ($ctx['lang'] === 'de' ? 'JETZT' : 'NOW') : (string) min(99, $m);
            $col = $dep['delay'] > 0 ? C_ACCENT : ($m > 60 ? C_DIM : C_WHITE);
        }
        $w = panel_width($txt);
        $ops[] = op_text($edge - $w, $y, $txt, $dim ? transit_dim($ctx, 'text') : $col);
        $edge -= $w + 3;
    }

    $mark = transit_marker($dep);
    if ($mark !== null) {
        // Im alten Stand ist auch das Zeichen leise: ein leuchtendes X auf einer alten Tafel wuerde mehr behaupten, als sie weiss.
        $ops[] = op_text($edge - 5, $y, $mark[0], $dim ? transit_dim($ctx, 'text') : $mark[1]);
        $edge -= TRANSIT_CHAR + 1;
    }

    // Ein Ausfall oder eine Zusatzfahrt geht vor: das Gleis eines ausgefallenen Zuges liest niemand.
    if ($dep['plat'] !== null && $mark === null && ($set === 'train' || ($set === 'mixed' && $dep['mode'] === 'train'))) {
        $plat = substr($dep['plat'], 0, $set === 'train' ? 3 : 2);
        $w = panel_width($plat);
        $ops[] = op_text($edge - $w, $y, $plat, $dim ? transit_dim($ctx, 'text') : ($dep['plat_changed'] ? C_ACCENT : C_DIM));
        $edge -= $w + 3;
    }

    $ops[] = op_text(2, $y, substr($dep['code'], 0, $codeChars), $dim ? transit_dim($ctx, 'code') : transit_mode_colour($dep['mode']));
    $room = max(0, intdiv($edge - $destX + 1, TRANSIT_CHAR));
    if ($room > 0 && $dep['dest'] !== '') {
        $ops[] = op_text($destX, $y, mb_substr($dep['dest'], 0, $room), $dim ? transit_dim($ctx, 'text') : ($dep['cancelled'] ? C_RED : C_WHITE));
    }
}

/** Gedimmter Ton fuer den alten Stand und die Quelle, nachts heller (in transit_ops() gewaehlt). */
function transit_dim(array $ctx, string $what): string
{
    return (string) ($ctx['dim_' . $what] ?? ($what === 'code' ? TRANSIT_DIM_CODE : TRANSIT_DIM_TEXT));
}

/** Zeichen und Farbe, das wichtigere zuerst. Eine geaenderte Fahrt bekommt keins: 46 von 276 sind so. */
function transit_marker(array $dep): ?array
{
    return match (true) {
        $dep['cancelled'] => ['X', C_RED],
        $dep['part'] => ['!', C_ACCENT],
        $dep['additional'] => ['+', C_GREEN],
        $dep['replacement'] => ['E', C_CYAN],
        default => null,
    };
}

/**
 * Streifen unten: Uhr und Quelle, oder die Meldung als Laufschrift. Bei fuenf Zeilen
 * ist kein Platz. Text bei y 56 bis 62 statt 57 bis 63: die Lippe des Standfusses
 * (1,5 mm) verdeckt die unterste LED-Zeile zur Haelfte (Befund A4).
 */
function transit_strip(array &$ops, array $ctx, ?string $note): void
{
    $t = $ctx['settings']['transit'];
    if ((int) $t['rows'] >= 5) {
        return;
    }
    $ops[] = op_rect(0, 55, 128, 1, C_LINE);
    $text = $note !== null ? panel_text($note, true, 160) : '';
    if ($text !== '' && !empty($t['notes'])) {
        $ops[] = op_ticker(2, 56, $text, C_ACCENT, 14);
        return;
    }
    $ops[] = op_clock(2, 56, 1, C_DIM, (bool) ($ctx['settings']['clock']['h24'] ?? true), false, false);
    $ops[] = op_text(125 - panel_width('MOBILITEIT'), 56, 'MOBILITEIT', transit_dim($ctx, 'text'));
}

/**
 * Keine frischen Daten: der alte Stand bleibt gedimmt stehen, mit seinem Alter.
 * Leer saehe kaputt aus, und eine alte Tafel, die sagt, dass sie alt ist, hilft noch.
 */
function transit_trouble(array &$ops, array $ctx, array $rows, string $set, string $message, ?int $asOf): void
{
    foreach (array_slice($rows, 0, 2) as $i => $dep) {
        transit_row($ops, 14 + $i * 12, $dep, $set, $ctx, true);
    }
    $ops[] = op_text(2, 42, $message, C_RED);
    if ($asOf !== null) {
        $ops[] = op_text(2, 52, ($ctx['lang'] === 'de' ? 'STAND ' : 'AS OF ') . local_time((string) $ctx['settings']['tz'], $asOf), C_DIM);
    }
    $ops[] = op_rect(0, 61, 128, 2, TRANSIT_TROUBLE_BAR);
}

/** Abfahrten einer Haltestelle, wie das Panel sie braucht: gefiltert, mit Minuten ab $now, nach Zeit. */
function transit_visible(array $departures, array $ctx, int $now): array
{
    $t = $ctx['settings']['transit'];
    $enabled = $t['modes'] ?? TRANSIT_MODES;
    $hide = $t['hide'] ?? [];
    $walk = (int) ($t['walk'] ?? 0);
    $minute = intdiv($now, 60) * 60;
    $out = [];
    foreach ($departures as $i => $d) {
        $dep = is_array($d) ? transit_panel_dep($d, (string) $ctx['settings']['tz']) : null;
        if ($dep === null || !in_array($dep['mode'], $enabled, true) || in_array($dep['code'], $hide, true)) {
            continue;
        }
        // Schulbusse: Linien aus einem Buchstaben und zwei Ziffern, K01 oder L34.
        if (empty($t['school']) && preg_match('/^[A-Z]\d{2}$/', $dep['code'])) {
            continue;
        }
        $dep['wait'] = intdiv($dep['ts'] - $minute, 60) - $walk;
        if ($dep['ts'] < $minute || $dep['wait'] < 0) {
            continue;
        }
        $dep['order'] = $i;
        $out[] = $dep;
    }
    usort($out, static fn(array $a, array $b): int => [$a['ts'], $a['order']] <=> [$b['ts'], $b['order']]);
    return $out;
}

/**
 * Zeilen aus einer oder mehreren Haltestellen. Mehrere laufen reihum, je Runde die
 * frueheste zuerst: nur nach Zeit gaebe es am Bahnhof vier Busse und nie den Zug.
 */
function transit_pick_rows(array $queues, int $limit): array
{
    $queues = array_values(array_filter($queues));
    if (count($queues) <= 1) {
        return array_slice($queues[0] ?? [], 0, $limit);
    }
    $out = [];
    for ($guard = 0; count($out) < $limit && $guard < 64; $guard++) {
        $heads = [];
        foreach ($queues as $i => $q) {
            if ($q) {
                $heads[] = [$q[0]['ts'], $i];
            }
        }
        if (!$heads) {
            break;
        }
        sort($heads);
        foreach ($heads as [, $i]) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = array_shift($queues[$i]);
        }
    }
    return $out;
}

/** Eine Abfahrt aus services/transit.php fuer das Panel, abgeleitet wie transit-sample.js im Entwurf. */
function transit_panel_dep(array $d, string $tz): ?array
{
    $ts = transit_ts((string) ($d['realtime'] ?? '')) ?? transit_ts((string) ($d['planned'] ?? ''));
    if ($ts === null) {
        return null;
    }
    $mode = in_array($d['mode'] ?? '', TRANSIT_MODES, true) ? $d['mode'] : 'bus';
    $line = (string) ($d['line'] ?? '');
    $category = (string) ($d['category'] ?? '');
    $code = $mode === 'train' ? ($category !== '' ? $category : $line) : ($line !== '' ? $line : $category);
    $split = is_array($d['direction_split'] ?? null) ? $d['direction_split'] : transit_split_name((string) ($d['direction'] ?? ''));
    // Zuege: der Ort, weil die Haltestelle meist GARE heisst. Bus und Tram: die Haltestelle ohne Ort.
    $dest = $mode === 'train' && (string) ($split['place'] ?? '') !== '' ? (string) $split['place'] : (string) ($split['stop'] ?? '');
    $dest = trim(preg_replace('/\s*\((Bus|Tram)\)\s*$/i', '', $dest) ?? $dest);
    $plat = null;
    $platChanged = false;
    if (empty($d['platform_hidden'])) {
        // Erst fuers Panel umschreiben (ASCII), dann Leerzeichen und Striche weg: ein geschuetztes
        // Leerzeichen oder ein Halbgeviertstrich ergab sonst nach substr ungueltiges UTF-8 und 500.
        $norm = static fn(mixed $p): string => substr(preg_replace('/[\s\-]/', '', panel_text((string) $p, true, 16)) ?? '', 0, 8);
        $planned = $norm($d['platform'] ?? '');
        $real = $norm($d['platform_realtime'] ?? '');
        $plat = $real !== '' ? $real : ($planned !== '' ? $planned : null);
        $platChanged = $real !== '' && $planned !== '' && $real !== $planned;
    }
    $note = null;
    foreach (['R', 'I'] as $type) {
        foreach ($d['notes'] ?? [] as $n) {
            if (($n['type'] ?? '') === $type && trim((string) ($n['text'] ?? '')) !== '') {
                $note = trim((string) $n['text']);
                break 2;
            }
        }
    }
    return [
        'mode' => $mode,
        'code' => panel_text($code, true, 8),
        'dest' => panel_text($dest, true, 40),
        'ts' => $ts,
        'time' => local_time($tz, $ts),
        'delay' => (int) ($d['delay_min'] ?? 0),
        'plat' => $plat,
        'plat_changed' => $platChanged,
        'cancelled' => !empty($d['cancelled']),
        'part' => !empty($d['part_cancelled']),
        'additional' => !empty($d['additional']),
        'replacement' => !empty($d['replacement']),
        'note' => $note,
    ];
}

function transit_ts(string $iso): ?int
{
    if ($iso === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($iso))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}
