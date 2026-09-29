<?php
/*
 * Zeichenbefehle fuer das Panel. Der Server schickt dem Geraet keine Rohdaten,
 * sondern fertige Befehle. Die Firmware kennt vierzehn: die neun hier, seit 0.1.9 bmp und dot fuer
 * die Karte (modes/flight_map.php), seit 0.2.0 prog, count und disc fuer Spotify
 * (modes/spotify.php). Unbekannte Befehle uebergeht sie:
 *
 *   text   {t,x,y,s,c,z}   Text, x "c" heisst mittig, z ist die Vergroesserung
 *   ticker {t,x,y,s,c,v}   Laufschrift ab x nach links, v Pixel pro Sekunde, Versatz aus der Uhr
 *   rect   {t,x,y,w,h,c,dot}  gefuelltes Rechteck, mit dot nur jede n-te Spalte (Punkte)
 *   bar    {t,x,y,w,h,c}   Fortschrittsbalken, h ist 2, wenn es fehlt
 *   frame  {t,x,y,w,h,c}   Rahmen
 *   logo   {t,code,x,y}    Airline-Logo 32x34, fehlt es: Farbblock mit Kuerzel
 *   clock  {t,x,y,z,c,h24,sec,seg,ap}  Uhrzeit, rechnet das Geraet aus seiner RTC
 *   date   {t,y,c,lang}    Datum mittig, ebenfalls vom Geraet
 *   anim   {t,id}          eine der eingebauten Animationen: boot, wifi, connecting,
 *                          address, paired, modeswap, waiting, note, resting, noserver,
 *                          nowifi, updating, poweroff, pixeldemo
 *
 * Eine Antwort enthaelt Seiten mit festen Zeitfenstern (from, to in ms seit
 * 1970). Das Geraet zeigt die Seite, deren Fenster gerade laeuft. Weil die
 * Fenster an der Uhr ausgerichtet sind, schliessen zwei Antworten ohne Sprung
 * aneinander an, auch wenn eine Flug-Standzeit kuerzer ist als der Abruftakt.
 * Die Vorschau im Browser rendert dieselbe Antwort.
 */

declare(strict_types=1);

const FRAME_TTL = 10;
const FRAME_WINDOW = 25;
const ROTATION_SLOT = 30;
const NOTE_FRONT_SECONDS = 600;

const C_ACCENT = 'FFAA00';
const C_WHITE = 'F2F4F5';
const C_DIM = '8E9AA3';
const C_CYAN = '35D6FF';
const C_GREEN = '3DE07C';
const C_RED = 'FF4A1C';
const C_LINE = '1B2126';
const C_TRACK = '14222B';

/**
 * $opts: preview (bool), user (Konto des Besitzers), lang
 *
 * Reihenfolge, vom Wichtigsten an (die Vorschau zeigt immer den gewaehlten Modus):
 * Kopplungscode fuer Home Assistant, Klingeln von Timer oder Wecker, Panel aus, Begruessung
 * vor dem ersten "Uebernehmen", dann Notiz vorn, Rotation oder der gewaehlte Modus. Beginnt
 * ein Klingeln im Fenster der Antwort, endet die normale Seite genau dann, und die Seite
 * fuers Klingeln schliesst an: so steht es auf die Sekunde da, ohne neuen Abruf.
 */
function frame_build(array $device, array $settings, array $owner, bool $preview = false): array
{
    $now = microtime(true);
    $from = $now - 1;
    $to = $now + FRAME_WINDOW;
    $lang = $settings['lang'] === 'de' ? 'de' : 'en';
    $ctx = ['settings' => $settings, 'device' => $device, 'owner' => $owner, 'lang' => $lang, 'preview' => $preview, 'now' => $now];
    $iNow = (int) floor($now);
    $bright = frame_brightness($settings);

    $pages = [];
    $pairing = $preview ? null : ha_pairing_pending((int) ($device['id'] ?? 0), $iNow);
    $ringing = !$preview && ring_now($settings, $iNow)['since'] !== null;
    if ($pairing !== null) {
        $pages[] = frame_page($from, $to, pairing_ops($pairing['code'], $pairing['expires_at'], $ctx), ['id' => 'pairing', 'mode' => 'pairing']);
        $bright = max($bright, RING_BRIGHT_MIN);
    } elseif ($ringing) {
        $pages = ring_pages($ctx, $from, $to);
        $bright = max($bright, RING_BRIGHT_MIN);
    } else {
        // Beginnt im Fenster ein Klingeln, endet der Rest dort.
        $next = $preview ? null : ring_next($settings, $iNow);
        $until = $next !== null && $next < $to ? max($from, (float) $next) : $to;
        if (!$preview && !empty($settings['off'])) {
            $pages[] = frame_page($from, $until, [], ['id' => 'off', 'mode' => 'off']);
            $bright = 0;
        } elseif (!$preview && empty($settings['applied'])) {
            $pages[] = frame_page($from, $until, frame_hello($owner, $lang));
        } elseif ($until > $from) {
            $pages = frame_normal_pages($ctx, $from, $until);
        }
        if ($until < $to) {
            $pages = array_merge($pages, ring_pages($ctx, $until, $to));
        }
    }

    $frame = [
        'v' => 1,
        'ttl' => FRAME_TTL,
        'now' => (int) round($now * 1000),
        'bright' => $bright,
        'tz' => tz_posix($settings['tz']),
        'iana' => $settings['tz'],
        'lang' => $lang,
        'pages' => $pages,
    ];
    // Ab Firmware 0.1.1: harter Schnitt statt Schub, kein Blinken, Laufschrift steht,
    // Animationen im Endzustand. Das Geraet merkt es sich fuer Anzeigen ohne Server.
    if (!empty($settings['reduce'])) {
        $frame['motion'] = 'reduce';
    }
    return $frame;
}

/**
 * Uhrzeit fuer das Geraet in ms: die Mitte zwischen Eingang der Anfrage und Antwort. Das
 * Geraet stellt seine Uhr auf diese Zeit plus die halbe Laufzeit, und in der Laufzeit steckt
 * auch, wie lange der Server gerechnet hat. Stand hier der Beginn des Rechnens, lag die Uhr
 * nach einem langsamen Frame um die halbe Rechenzeit zurueck, und ab 250 ms stellt das
 * Geraet nach. Bei Spotify kurz vor einem Titelwechsel (Warteschlange, Cover) war das oft
 * eine halbe Sekunde: Laufschrift, Countdown und Balken sprangen zurueck.
 */
function frame_clock_ms(float $received, float $sent): int
{
    return (int) round(($received + $sent) / 2 * 1000);
}

/**
 * Welche Modi das Panel gerade zeigt: einer, oder die Rotation. Wer nichts zu zeigen hat,
 * faellt aus der Rotation, etwa Spotify ohne Musik. Musik geht vor, wenn so eingestellt, und
 * eine neue Notiz steht zehn Minuten vorn. Die Vorschau zeigt immer den gewaehlten Modus.
 */
function frame_mode_list(array $settings, array $ctx, bool $preview): array
{
    $list = [$settings['mode']];
    if (!$preview) {
        $rot = array_values(array_filter($settings['rotation'] ?? [], static fn($m) => ($x = mode_get((string) $m)) && $x['available']));
        if (count($rot) >= 2) {
            $rot = array_values(array_filter($rot, static fn($m) => mode_is_active((string) $m, $ctx)));
            if ($rot) {
                $list = $rot;
            }
        }
        if (!empty($settings['spotify']['first']) && mode_get('spotify') !== null && mode_is_active('spotify', $ctx)) {
            $list = ['spotify'];
        }
    }
    $list = array_values(array_filter($list, static fn($m) => mode_get((string) $m) !== null));
    if (!$list) {
        $list = ['clock'];
    }
    /* Eine neue Notiz steht zehn Minuten lang vorn, auch wenn gerade ein
       anderer Modus laeuft. Sonst saehe niemand, was ein Gast geschrieben hat. */
    if (!$preview && note_front_left($settings) > 0) {
        $list = ['notes'];
    }
    return $list;
}

/**
 * Die Seiten der Modi zwischen $from und $to, bei einem laufenden Timer mit der Ecke. Die
 * Modi erfahren ueber $ctx['corner'], dass ein Timer laeuft, und machen Platz; ohne Timer
 * bleibt alles wie vorher.
 */
function frame_normal_pages(array $ctx, float $from, float $to): array
{
    $timer = corner_timer($ctx['settings'], $from);
    $ctx['corner'] = $timer;
    $list = frame_mode_list($ctx['settings'], $ctx, (bool) $ctx['preview']);
    $pages = [];
    if (count($list) === 1) {
        $pages = frame_mode_pages($list[0], $ctx, $from, $to);
    } else {
        foreach (frame_slots($from, $to, ROTATION_SLOT) as [$index, $start, $end]) {
            $modeId = $list[$index % count($list)];
            $slotPages = frame_mode_pages($modeId, $ctx, max($from, $start), min($to, $end));
            if ($slotPages && abs($slotPages[0]['from'] - (int) round($start * 1000)) < 5) {
                // Nahverkehr faellt zeilenweise ein (drop), alle anderen schieben (push).
                $slotPages[0]['fx'] = mode_get($modeId)['enter'] ?? 'push';
            }
            $pages = array_merge($pages, $slotPages);
        }
    }
    if ($timer !== null) {
        $count = frame_fw_at_least($ctx, '0.2.0');
        foreach ($pages as &$page) {
            if ($timer['end'] * 1000 > $page['from']) {
                array_push($page['ops'], ...corner_ops($timer, corner_spot((string) ($page['mode'] ?? ''), $ctx), $count, (int) floor($ctx['now'])));
            }
        }
        unset($page);
    }
    return $pages;
}

/**
 * Sekunden, die eine neue Notiz noch vor dem gewaehlten Modus steht, 0 wenn keine.
 * Den Vorrang beendet auch ein neuer Modus oder eine neue Rotation (api_apply()) und
 * der Knopf "Notiz jetzt ausblenden" (api_note_hide()): bis zum 26. September 2026
 * stand die Notiz nach einem Wechsel noch bis zu zehn Minuten da, und das Panel
 * wirkte festgeklemmt.
 */
function note_front_left(array $settings, ?int $now = null): int
{
    $text = trim(($settings['notes']['line1'] ?? '') . ($settings['notes']['line2'] ?? ''));
    $at = (int) ($settings['note_at'] ?? 0);
    if ($text === '' || $at <= 0) {
        return 0;
    }
    return max(0, min(NOTE_FRONT_SECONDS, $at + NOTE_FRONT_SECONDS - ($now ?? time())));
}

function frame_mode_pages(string $modeId, array $ctx, float $from, float $to): array
{
    $mode = mode_get($modeId);
    if ($mode === null || ($to - $from) <= 0) {
        return [];
    }
    try {
        $pages = ($mode['build'])($ctx, $from, $to);
    } catch (Throwable $e) {
        log_error('Modus ' . $modeId . ': ' . $e->getMessage(), ['line' => $e->getFile() . ':' . $e->getLine()]);
        $msg = $ctx['lang'] === 'de' ? 'FEHLER' : 'ERROR';
        $pages = [frame_page($from, $to, [op_text('c', 28, $msg, C_RED)])];
    }
    // Jede Seite traegt ihren Modus. Wechselt er ohne fx, etwa nach "Uebernehmen", steigt das
    // Geraet ein wie in der Rotation (ab Firmware 0.1.4); enter steht nur, wo es nicht push ist.
    foreach ($pages as &$page) {
        $page['mode'] = $modeId;
        if (($mode['enter'] ?? 'push') !== 'push') {
            $page['enter'] = $mode['enter'];
        }
    }
    unset($page);
    return $pages;
}

function frame_page(float $from, float $to, array $ops, array $extra = []): array
{
    return ['from' => (int) round($from * 1000), 'to' => (int) round($to * 1000), 'ops' => $ops] + $extra;
}

/** Zeitfenster fester Laenge, die [von, bis] ueberlappen: [Index, Start, Ende]. */
function frame_slots(float $from, float $to, float $len): array
{
    $out = [];
    $i = (int) floor($from / $len);
    while ($i * $len < $to) {
        $out[] = [$i, $i * $len, ($i + 1) * $len];
        $i++;
        if (count($out) > 64) {
            break;
        }
    }
    return $out;
}

/** Nach dem Koppeln, bis auf der Webseite ein Modus gewaehlt ist. */
function frame_hello(array $owner, string $lang): array
{
    $word = $lang === 'de' ? 'HALLO' : 'HELLO';
    $name = panel_text((string) $owner['username'], true, 10);
    $wait = $lang === 'de' ? 'MODUS WAEHLEN' : 'PICK A MODE';
    return [
        op_text('c', 10, $word, C_DIM, 2),
        op_text('c', 28, $name, C_ACCENT, 2),
        op_text('c', 50, $wait, C_DIM),
    ];
}

/** Helligkeit 0 bis 255, nachts gedimmt, wenn die Nachtabsenkung an ist. */
function frame_brightness(array $s): int
{
    $b = (int) $s['bright'];
    if (!empty($s['clock']['night']) && $b > 0 && frame_is_night((float) $s['location']['lat'], (float) $s['location']['lon'], time())) {
        $b = max(8, (int) round($b * 0.35));
    }
    return $b;
}

/**
 * Nacht am Standort des Geraets. date_sun_info() rechnet mit dem Kalendertag in der
 * Zone des Servers; fuer ein Geraet in New York oder Sydney liegt dessen Tag
 * stundenweise daneben. Deshalb gestern, heute und morgen: Tag ist, wenn einer der
 * drei Sonnenaufgaenge vor jetzt und sein Untergang nach jetzt liegt. Polartag und
 * Polarnacht (true oder false statt Zeit) gelten als Tag, dort dimmt nichts.
 */
function frame_is_night(float $lat, float $lon, int $now): bool
{
    foreach ([-86400, 0, 86400] as $shift) {
        $sun = @date_sun_info($now + $shift, $lat, $lon);
        if (!is_array($sun) || !is_int($sun['sunrise'] ?? null) || !is_int($sun['sunset'] ?? null)) {
            return false;
        }
        if ($sun['sunrise'] <= $now && $now <= $sun['sunset']) {
            return false;
        }
    }
    return true;
}

function op_text(int|string $x, int $y, string $s, string $c, int $z = 1): array
{
    $op = ['t' => 'text', 'x' => $x, 'y' => $y, 's' => $s, 'c' => $c];
    if ($z !== 1) {
        $op['z'] = $z;
    }
    return $op;
}

/**
 * Laufschrift fuer Texte, die nie in eine Zeile passen, etwa Meldungen zum Nahverkehr.
 * Der Text laeuft mit $v Pixeln pro Sekunde nach links und kehrt nach sechs
 * Leerzeichen wieder. Den Versatz rechnet das Geraet aus seiner Uhr (Millisekunden
 * seit 1970 mal v durch 1000, modulo Textbreite), deshalb springt sie beim
 * Seitenwechsel nicht zurueck.
 */
function op_ticker(int $x, int $y, string $s, string $c, int $v = 14): array
{
    return ['t' => 'ticker', 'x' => $x, 'y' => $y, 's' => $s, 'c' => $c, 'v' => $v];
}

function op_rect(int $x, int $y, int $w, int $h, string $c): array
{
    return ['t' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'c' => $c];
}

function op_bar(int $x, int $y, int $w, string $c, int $h = 2): array
{
    return ['t' => 'bar', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'c' => $c];
}

function op_frame(int $x, int $y, int $w, int $h, string $c): array
{
    return ['t' => 'frame', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'c' => $c];
}

function op_logo(string $code, int $x = 2, int $y = 2): array
{
    return ['t' => 'logo', 'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $code) ?? '', 0, 3)), 'x' => $x, 'y' => $y];
}

/**
 * $ap: bei 12 Stunden und Faktor ueber 1 steht AM oder PM klein neben den Ziffern,
 * unten buendig, 4 Spalten Abstand (ab Firmware 0.1.1, aeltere zeichnen es gross dahinter).
 */
function op_clock(int $x, int $y, int $z, string $c, bool $h24, bool $sec, bool $seg, bool $ap = false): array
{
    $op = ['t' => 'clock', 'x' => $x, 'y' => $y, 'z' => $z, 'c' => $c, 'h24' => $h24, 'sec' => $sec, 'seg' => $seg];
    if ($ap && !$h24 && $z > 1) {
        $op['ap'] = true;
    }
    return $op;
}

function op_date(int $y, string $c, string $lang): array
{
    return ['t' => 'date', 'y' => $y, 'c' => $c, 'lang' => $lang];
}

function op_anim(string $id, array $params = []): array
{
    return ['t' => 'anim', 'id' => $id] + $params;
}

function hex6(string $colour): string
{
    $c = strtoupper(ltrim($colour, '#'));
    return preg_match('/^[0-9A-F]{6}$/', $c) ? $c : C_ACCENT;
}

/** Laenge der Uhrzeit, wie die Firmware sie zeichnet: 20:14, 20:14:33, 08:14 PM. */
function clock_text_length(bool $h24, bool $sec): int
{
    return 5 + ($sec ? 3 : 0) + ($h24 ? 0 : 3);
}

/**
 * Breite der Uhr in Spalten bei Faktor $z. Bei 12 Stunden und Faktor ueber 1 steht
 * AM oder PM klein daneben (4 Spalten Luecke, 11 breit): "09:14:33 PM" passt so
 * bei Faktor 2 mit 109 Spalten, statt auf Faktor 1 zu fallen.
 */
function clock_width(bool $h24, bool $sec, int $z): int
{
    $digits = 5 + ($sec ? 3 : 0);
    if ($h24) {
        return $digits * 6 * $z - $z;
    }
    return $z > 1 ? $digits * 6 * $z - $z + 4 + 11 : ($digits + 3) * 6 - 1;
}

/** Ortszeit als Text, fuer ETA und Warteanzeige. */
function local_time(string $tz, ?int $ts = null, bool $h24 = true): string
{
    $dt = new DateTimeImmutable('@' . ($ts ?? time()));
    $dt = $dt->setTimezone(new DateTimeZone(tz_valid($tz) ? $tz : 'UTC'));
    return $h24 ? $dt->format('H:i') : $dt->format('h:i A');
}
