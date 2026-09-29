<?php
/*
 * Timer, Wecker und die Statusecke.
 *
 * Timer stehen in den Einstellungen des Geraets (settings.timers), hoechstens fuenf
 * gleichzeitig. Ein Timer laeuft bis end und klingelt danach, bis ihn jemand stoppt,
 * hoechstens RING_SECONDS lang. Der Wecker (settings.alarm) ist einer je Geraet: Uhrzeit in
 * der Zone des Geraets, Wochentage nach ISO (1 ist Montag). Er klingelt zur Uhrzeit, bis ihn
 * jemand stoppt, ebenfalls hoechstens RING_SECONDS. Gestoppt merkt er sich den Zeitpunkt
 * (ack), damit er in derselben Viertelstunde nicht wieder angeht.
 *
 * Laeuft ein Timer, zeigt jeder Modus die Restzeit in einer Ecke (corner_ops()), in den
 * meisten oben rechts, bei Spotify unten rechts. Die Modi machen dort Platz, aber nur,
 * solange ein Timer laeuft: ohne Timer steht zum Beispiel wieder LUFTHANSA CITY in voller
 * Laenge da (Wunsch des Projektinhabers am 26. September 2026). Klingelt etwas, gehoert das
 * ganze Panel dem Klingeln (ring_ops()), in jedem Modus, auch wenn es ausgeschaltet ist.
 */

declare(strict_types=1);

const TIMER_MAX = 5;
const TIMER_MAX_SECONDS = 86400;
const TIMER_LABEL_MAX = 12;
const RING_SECONDS = 900;
/* So hell wird das Panel mindestens, solange etwas klingelt oder ein Kopplungscode dasteht. */
const RING_BRIGHT_MIN = 96;
/* Die Ecke: schwarzes Feld von x 96 bis 127, y 0 bis 8, die Ziffern rechtsbuendig an der
   Kante in Zeile 2. 59:59 sind fuenf Zeichen, 29 Spalten; Zeile 1 des Flugmodus endet mit
   neun Zeichen bei x 91. Die Linie des Nahverkehrs bei y 9 bleibt frei. */
const CORNER_SPOT = ['box' => [96, 0, 32, 9], 'right' => 128, 'y' => 2];
/* Ab Firmware 0.2.1 stoppt ein Druck aufs Rad das Klingeln (POST /api/v1/ring/stop). */
const RING_WHEEL_FW = '0.2.1';

function alarm_defaults(): array
{
    return ['on' => false, 'time' => '07:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'ack' => 0, 'set' => 0];
}

function alarm_time_valid(string $t): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

/** Der Wecker aus den Einstellungen, mit Vorgaben aufgefuellt und bereinigt. */
function alarm_get(array $s): array
{
    $a = (is_array($s['alarm'] ?? null) ? $s['alarm'] : []) + alarm_defaults();
    $days = [];
    foreach (is_array($a['days']) ? $a['days'] : [] as $d) {
        if (is_numeric($d) && (int) $d >= 1 && (int) $d <= 7 && !in_array((int) $d, $days, true)) {
            $days[] = (int) $d;
        }
    }
    sort($days);
    return [
        'on' => (bool) $a['on'],
        'time' => alarm_time_valid((string) $a['time']) ? (string) $a['time'] : '07:00',
        'days' => $days ?: [1, 2, 3, 4, 5, 6, 7],
        'ack' => (int) $a['ack'],
        'set' => (int) $a['set'],
    ];
}

/**
 * Eingabe fuer den Wecker pruefen: on, time "HH:MM", days (ISO-Wochentage). Liefert
 * [Wecker, Fehler als [en, de] oder null]. Jede Aenderung setzt set auf jetzt: ein Wecker,
 * den man um 7:05 auf 7:00 stellt, klingelt erst am naechsten passenden Tag.
 */
function alarm_apply(array $cur, array $in, int $now): array
{
    $a = $cur;
    if (array_key_exists('on', $in)) {
        $a['on'] = (bool) $in['on'];
    }
    if (array_key_exists('time', $in)) {
        $t = trim((string) $in['time']);
        if (preg_match('/^(\d):([0-5]\d)$/', $t, $m)) {
            $t = '0' . $m[1] . ':' . $m[2];
        }
        if (!alarm_time_valid($t)) {
            return [$cur, ['The alarm time must look like 06:30.', 'Die Weckzeit muss wie 06:30 aussehen.']];
        }
        $a['time'] = $t;
    }
    if (array_key_exists('days', $in)) {
        $days = [];
        foreach (is_array($in['days']) ? $in['days'] : [] as $d) {
            if (!is_numeric($d) || (int) $d < 1 || (int) $d > 7) {
                return [$cur, ['Weekdays are numbers from 1 (Monday) to 7 (Sunday).', 'Wochentage sind Zahlen von 1 (Montag) bis 7 (Sonntag).']];
            }
            if (!in_array((int) $d, $days, true)) {
                $days[] = (int) $d;
            }
        }
        if (!$days) {
            return [$cur, ['Pick at least one day.', 'Mindestens einen Tag wählen.']];
        }
        sort($days);
        $a['days'] = $days;
    }
    if ($a['on'] !== $cur['on'] || $a['time'] !== $cur['time'] || $a['days'] !== $cur['days']) {
        $a['set'] = $now;
    }
    return [$a, null];
}

/**
 * Zeitpunkte des Weckers rund um jetzt: [letzter <= jetzt, naechster > jetzt], beide null,
 * wenn er aus ist. Gerechnet in der Zone des Geraets, eine Woche zurueck und voraus. Faellt
 * die Weckzeit in die Luecke der Sommerzeit, schiebt PHP sie eine Stunde weiter.
 */
function alarm_times(array $a, string $tz, int $now): array
{
    if (!$a['on']) {
        return [null, null];
    }
    $zone = new DateTimeZone(tz_valid($tz) ? $tz : 'UTC');
    [$h, $m] = array_map('intval', explode(':', $a['time']));
    $today = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
    $last = null;
    $next = null;
    for ($d = -7; $d <= 7; $d++) {
        $day = $d === 0 ? $today : $today->modify(sprintf('%+d day', $d));
        if (!in_array((int) $day->format('N'), $a['days'], true)) {
            continue;
        }
        $t = $day->setTime($h, $m)->getTimestamp();
        if ($t <= $now) {
            $last = $t;
        } elseif ($next === null) {
            $next = $t;
        }
    }
    return [$last, $next];
}

/** Beginn des Klingelns, wenn der Wecker gerade klingelt, sonst null. */
function alarm_ringing(array $s, int $now): ?int
{
    $a = alarm_get($s);
    [$last] = alarm_times($a, (string) ($s['tz'] ?? 'UTC'), $now);
    if ($last === null || $last <= $a['ack'] || $last < $a['set'] || $now >= $last + RING_SECONDS) {
        return null;
    }
    return $last;
}

function timer_label(string $l): string
{
    return str_cut(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $l) ?? ''), TIMER_LABEL_MAX);
}

/** Timer aus den Einstellungen, bereinigt und nach Ende sortiert. Ausgeklingelte fallen weg. */
function timers_list(array $s, ?int $now = null): array
{
    $now ??= time();
    $out = [];
    foreach (is_array($s['timers'] ?? null) ? $s['timers'] : [] as $t) {
        if (!is_array($t) || !is_string($t['id'] ?? null) || !is_numeric($t['end'] ?? null)) {
            continue;
        }
        $end = (int) $t['end'];
        if ($end + RING_SECONDS <= $now) {
            continue;
        }
        $out[] = [
            'id' => (string) $t['id'],
            'end' => $end,
            'total' => max(1, (int) ($t['total'] ?? 0)),
            'label' => timer_label((string) ($t['label'] ?? '')),
            'by' => (string) ($t['by'] ?? 'web'),
        ];
    }
    usort($out, static fn(array $a, array $b): int => $a['end'] <=> $b['end']);
    return $out;
}

/**
 * Neuer Timer ueber $seconds. Liefert [Einstellungen, Timer, null] oder bei einem Fehler
 * [null, null, [code, en, de]].
 */
function timer_add(array $s, int $seconds, string $label, string $by, int $now): array
{
    if ($seconds < 1 || $seconds > TIMER_MAX_SECONDS) {
        return [null, null, ['invalid_value', 'A timer runs from 1 second to 24 hours.', 'Ein Timer läuft von 1 Sekunde bis 24 Stunden.']];
    }
    $list = timers_list($s, $now);
    $running = array_filter($list, static fn(array $t): bool => $t['end'] > $now);
    if (count($running) >= TIMER_MAX) {
        return [null, null, ['too_many_timers', 'At most ' . TIMER_MAX . ' timers at once.', 'Höchstens ' . TIMER_MAX . ' Timer gleichzeitig.']];
    }
    $t = ['id' => random_hex(4), 'end' => $now + $seconds, 'total' => $seconds, 'label' => timer_label($label), 'by' => $by];
    $list[] = $t;
    usort($list, static fn(array $a, array $b): int => $a['end'] <=> $b['end']);
    $s['timers'] = $list;
    return [$s, $t, null];
}

/** Einen Timer abbrechen, oder alle, wenn $id null ist. Klingelnde gehoeren dazu. */
function timer_cancel(array $s, ?string $id, int $now): array
{
    $s['timers'] = $id === null ? [] : array_values(array_filter(timers_list($s, $now), static fn(array $t): bool => $t['id'] !== $id));
    return $s;
}

/** Was gerade klingelt: timers (klingelnde Timer), alarm (Beginn oder null), since (frueheste). */
function ring_now(array $s, int $now): array
{
    $timers = array_values(array_filter(timers_list($s, $now), static fn(array $t): bool => $t['end'] <= $now));
    $alarm = alarm_ringing($s, $now);
    $starts = array_column($timers, 'end');
    if ($alarm !== null) {
        $starts[] = $alarm;
    }
    return ['timers' => $timers, 'alarm' => $alarm, 'since' => $starts ? min($starts) : null];
}

/** Naechster Zeitpunkt nach $now, an dem etwas zu klingeln beginnt, oder null. */
function ring_next(array $s, int $now): ?int
{
    $c = [];
    foreach (timers_list($s, $now) as $t) {
        if ($t['end'] > $now) {
            $c[] = $t['end'];
        }
    }
    [, $next] = alarm_times(alarm_get($s), (string) ($s['tz'] ?? 'UTC'), $now);
    if ($next !== null) {
        $c[] = $next;
    }
    return $c ? min($c) : null;
}

/** Klingeln stoppen: klingelnde Timer fallen weg, der Wecker merkt sich den Zeitpunkt. */
function ring_stop(array $s, int $now): array
{
    $s['timers'] = array_values(array_filter(timers_list($s, $now), static fn(array $t): bool => $t['end'] > $now));
    $r = alarm_ringing($s, $now);
    if ($r !== null) {
        $a = alarm_get($s);
        $a['ack'] = $r;
        $s['alarm'] = $a;
    }
    return $s;
}

/**
 * Teil der Revision fuer /api/v1/rev: aendert sich genau dann, wenn ein Timer zu klingeln
 * beginnt oder ausgeklingelt ist und wenn der Wecker angeht oder aufhoert. So steht das
 * Klingeln in gut zwei Sekunden auf dem Panel, auch ohne neue Einstellungen.
 */
function ring_rev(array $s, int $now): string
{
    $parts = [];
    foreach (timers_list($s, $now) as $t) {
        $parts[] = $t['id'] . ($t['end'] <= $now ? '1' : '0');
    }
    $a = alarm_get($s);
    if ($a['on']) {
        [$last] = alarm_times($a, (string) ($s['tz'] ?? 'UTC'), $now);
        $parts[] = ($last ?? 0) . (alarm_ringing($s, $now) !== null ? '1' : '0');
    }
    return $parts ? substr(md5(implode(',', $parts)), 0, 6) : '0';
}

/** Der Timer fuer die Ecke: der naechste, der waehrend [von, bis] noch laeuft, sonst null. */
function corner_timer(array $s, float $from): ?array
{
    foreach (timers_list($s, (int) floor($from)) as $t) {
        if ($t['end'] > $from) {
            return $t;
        }
    }
    return null;
}

/**
 * Zeichenbefehle fuer die Ecke: ein schwarzes Feld und die Restzeit in Gruen, rechtsbuendig.
 * Ab Firmware 0.2.0 zaehlt das Geraet selbst herunter (count). Aeltere bekommen den Stand
 * beim Abruf, der alle zehn Sekunden neu kommt. Ab 100 Minuten steht 1H45: 100:00 waere
 * sechs Zeichen breit und passte nicht ins Feld.
 */
function corner_ops(array $t, array $spot, bool $count, int $now): array
{
    [$bx, $by, $bw, $bh] = $spot['box'];
    $ops = [op_rect($bx, $by, $bw, $bh, '000000')];
    $left = max(0, $t['end'] - $now);
    if ($left >= 6000) {
        $txt = intdiv($left, 3600) . 'H' . str_pad((string) intdiv($left % 3600, 60), 2, '0', STR_PAD_LEFT);
        $ops[] = op_text($spot['right'] - panel_width($txt), $spot['y'], $txt, C_GREEN);
    } elseif ($count) {
        $ops[] = ['t' => 'count', 'x' => $spot['right'], 'y' => $spot['y'], 'c' => C_GREEN, 'to' => $t['end'] * 1000, 'a' => 'r'];
    } else {
        $txt = intdiv($left, 60) . ':' . str_pad((string) ($left % 60), 2, '0', STR_PAD_LEFT);
        $ops[] = op_text($spot['right'] - panel_width($txt), $spot['y'], $txt, C_GREEN);
    }
    return $ops;
}

/** Wo die Ecke in einem Modus sitzt: die Vorgabe oben rechts, sonst was der Modus sagt. */
function corner_spot(string $modeId, array $ctx): array
{
    $m = mode_get($modeId);
    $fn = $m['corner'] ?? null;
    $spot = is_callable($fn) ? $fn($ctx) : null;
    return is_array($spot) ? $spot + CORNER_SPOT : CORNER_SPOT;
}

/** Kann das Geraet selbst zaehlen (count, ab 0.2.0)? Die Vorschau im Browser immer. */
function frame_fw_at_least(array $ctx, string $version): bool
{
    if (!empty($ctx['preview'])) {
        return true;
    }
    $fw = (string) ($ctx['device']['fw'] ?? '');
    return $fw !== '' && version_compare($fw, $version, '>=');
}

/**
 * Das ganze Panel fuer das Klingeln. Klingeln Wecker und Timer zugleich, zeigt es, was
 * zuerst anfing. Der Wecker zeigt die Uhrzeit gross, der Timer 0:00 und seinen Namen.
 */
function ring_ops(array $ring, array $ctx): array
{
    $s = $ctx['settings'];
    $de = $ctx['lang'] === 'de';
    $wheel = frame_fw_at_least($ctx, RING_WHEEL_FW) && empty($ctx['preview']);
    $hint = $wheel ? ($de ? 'STOPP: RAD DRUECKEN' : 'STOP: PRESS THE WHEEL') : ($de ? 'STOPP UEBER WEBSEITE' : 'STOP ON THE WEBSITE');
    $firstTimer = $ring['timers'][0] ?? null;
    if ($ring['alarm'] !== null && ($firstTimer === null || $ring['alarm'] <= $firstTimer['end'])) {
        $h24 = (bool) ($s['clock']['h24'] ?? true);
        $w = clock_width($h24, false, 3);
        return [
            op_text('c', 3, $de ? 'WECKER' : 'ALARM', C_DIM),
            op_clock(max(1, (int) round((PANEL_COLS - $w) / 2)), 17, 3, C_ACCENT, $h24, false, false, true),
            op_text('c', 52, $hint, C_DIM),
        ];
    }
    $title = $firstTimer !== null && $firstTimer['label'] !== '' ? panel_text($firstTimer['label'], true, 21) : 'TIMER';
    $more = count($ring['timers']) - 1;
    $ops = [
        op_text('c', 3, $title, C_DIM),
        op_text('c', 17, '0:00', C_ACCENT, 3),
        op_text('c', 52, $hint, C_DIM),
    ];
    if ($more > 0) {
        $ops[] = op_text(126 - panel_width('+' . $more), 3, '+' . $more, C_ACCENT);
    }
    return $ops;
}

/**
 * Seiten fuer das Klingeln ab $from. Kein flash wie bei der Notiz: die Firmware merkt sich
 * dafuer die Nummer der Notiz, und eine alte Notiz blinkte nach dem Klingeln noch einmal.
 * Aufmerksamkeit bringt ab Firmware 0.2.1 der Ton.
 */
function ring_pages(array $ctx, float $from, float $to): array
{
    $ring = ring_now($ctx['settings'], (int) ceil($from));
    if ($ring['since'] === null) {
        return [];
    }
    return [frame_page($from, $to, ring_ops($ring, $ctx), ['id' => 'ring:' . $ring['since'], 'mode' => 'ring'])];
}

/** Der Kopplungscode fuer Home Assistant, gross und in zwei Dreiergruppen. */
function pairing_ops(string $code, int $expires, array $ctx): array
{
    $de = $ctx['lang'] === 'de';
    $ops = [
        op_text('c', 4, 'HOME ASSISTANT', C_CYAN),
        op_text('c', 19, substr($code, 0, 3) . ' ' . substr($code, 3), C_ACCENT, 2),
        op_text('c', 40, $de ? 'CODE IN HA EINGEBEN' : 'ENTER THIS CODE IN HA', C_WHITE),
    ];
    if (frame_fw_at_least($ctx, '0.2.0')) {
        $ops[] = ['t' => 'count', 'x' => 64, 'y' => 53, 'c' => C_DIM, 'to' => $expires * 1000, 'a' => 'c'];
    }
    return $ops;
}

/**
 * Timer und Wecker fuer die Firmware (ab 0.2.1): Enden der laufenden Timer in ms, der Wecker
 * als Stunde, Minute und Tage (Bit 0 Montag bis Bit 6 Sonntag), dazu ring, wenn gerade etwas
 * klingelt. Die Firmware klingelt damit auf die Sekunde und auch ohne Server. Aeltere
 * Firmware uebergeht die Felder.
 */
function ring_frame_fields(array $s, int $now): array
{
    $out = [];
    $ends = [];
    foreach (timers_list($s, $now) as $t) {
        if ($t['end'] > $now) {
            $ends[] = $t['end'] * 1000;
        }
    }
    if ($ends) {
        $out['timers'] = $ends;
    }
    $a = alarm_get($s);
    if ($a['on']) {
        [$h, $m] = array_map('intval', explode(':', $a['time']));
        $bits = 0;
        foreach ($a['days'] as $d) {
            $bits |= 1 << ($d - 1);
        }
        $out['alarm'] = ['h' => $h, 'm' => $m, 'd' => $bits, 'ack' => $a['ack'] * 1000, 'set' => $a['set'] * 1000];
    }
    $ring = ring_now($s, $now);
    if ($ring['since'] !== null) {
        $out['ring'] = ['since' => $ring['since'] * 1000];
    }
    return $out;
}

/** Timer fuer Webseite und Home Assistant, ohne interne Felder. */
function timers_public(array $s, int $now): array
{
    return array_map(static fn(array $t): array => [
        'id' => $t['id'],
        'label' => $t['label'],
        'total' => $t['total'],
        'end' => $t['end'],
        'ringing' => $t['end'] <= $now,
    ], timers_list($s, $now));
}

/** Wecker fuer Webseite und Home Assistant. */
function alarm_public(array $s, int $now): array
{
    $a = alarm_get($s);
    [, $next] = alarm_times($a, (string) ($s['tz'] ?? 'UTC'), $now);
    return ['on' => $a['on'], 'time' => $a['time'], 'days' => $a['days'], 'next' => $next, 'ringing' => alarm_ringing($s, $now) !== null];
}

/**
 * Timer, Wecker und Panel aus fuer die Geraeteseite: was laeuft, fuer die Anzeige unter der
 * Vorschau und in der Karte Timer und Wecker. Dieselben Zahlen bekommt Home Assistant.
 */
function ring_status_public(array $s): array
{
    $now = time();
    return [
        'timers' => timers_public($s, $now),
        'alarm' => alarm_public($s, $now),
        'off' => !empty($s['off']),
        'now' => $now,
    ];
}
