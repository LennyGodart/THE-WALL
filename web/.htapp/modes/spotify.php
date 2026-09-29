<?php
/*
 * Spotify auf dem Panel. Entwurf und Entscheidungen vom 24. September 2026 in
 * design/spotify/ENTWURF.md: Layout waehlbar (Klassisch, Gross, Farbe aus dem Cover,
 * Platte), zehn Sekunden vor dem Ende die Ansage des naechsten Titels, beim Ende die
 * Walze, beim Weiterspringen am Handy das Karussell, dazu die Ansichten Album, Als
 * Naechstes und Nur Cover im Wechsel mit "Laeuft".
 *
 * Zeit und Balken zaehlt ab Firmware 0.2.0 das Geraet selbst (Befehl prog), dazu kommen
 * count, disc und die Uebergaenge reel, carousel und lines. Sonst muesste der Server jede
 * Sekunde eine Seite mit Cover schicken, rund 430 MB am Tag. Aeltere Firmware bekommt
 * einen stehenden Balken, der mit jedem Abruf neu gezeichnet wird.
 *
 * Gezeigt wird immer das Spotify-Konto des Besitzers eines Geraets.
 */

declare(strict_types=1);

const SPOTIFY_FW = '0.2.0';
const SPOTIFY_TEASER_MS = 10000;
/* Zusaetzlich zu "Laeuft", das immer dabei ist. */
const SPOTIFY_VIEWS = ['album', 'queue', 'cover'];
const SPOTIFY_VIEW_MIN = 5;
const SPOTIFY_VIEW_MAX = 30;
/* So lange gilt eine Pause noch als Musik. Danach faellt der Modus aus der Rotation und
   zeigt allein die Uhr. */
const SPOTIFY_PAUSE_IDLE_MS = 600000;
/* Uebergaenge in Millisekunden, wie in der Firmware (main.cpp). */
const SPOTIFY_REEL_MS = 560;
const SPOTIFY_LINES_MS = 400;
/* So lange nach dem Rechnen, bis das Geraet eine Antwort zeigt: Weg zurueck und Lesen. */
const SPOTIFY_ARRIVE_MS = 300;
const C_SPOTIFY = '1ED760';
const C_DOTS = '4E5A63';
const C_GROOVE = '3B444B';
const C_LABEL = 'B37800';

/*
 * Raster je Layout, aus design/spotify/engine.js. cover: x, y, Kante. x und n: Beginn und
 * Zeichen der Textzeilen. time: x0, x1, Zeile der Zeit, Zeile des Balkens, Oberkante des
 * Kopfes, 0:00 links zeigen. cw, tw, bw: Fenster fuer Cover, Text und Zeitleiste in den
 * Uebergaengen (x0, y0, x1, y1).
 */
const SPOTIFY_LAYOUTS = [
    'A1' => ['cover' => [2, 2, 48], 'x' => 54, 'n' => 12, 'lines' => [2, 14, 26, 38], 'pitch' => 12,
        'time' => [0, 127, 52, 61, 59, true], 'cw' => [0, 0, 51, 50], 'tw' => [52, 0, 127, 50], 'bw' => [0, 51, 127, 63]],
    'A2' => ['cover' => [0, 0, 64], 'x' => 68, 'n' => 10, 'lines' => [2, 13, 24, 35], 'pitch' => 11,
        'time' => [68, 127, 47, 58, 56, false], 'cw' => [0, 0, 65, 63], 'tw' => [66, 0, 127, 44], 'bw' => [66, 45, 127, 63]],
    'A3' => ['cover' => [2, 2, 48], 'x' => 54, 'n' => 12, 'lines' => [2, 14, 26, 38], 'pitch' => 12, 'lead' => true,
        'time' => [0, 127, 52, 61, 59, true], 'cw' => [0, 0, 51, 50], 'tw' => [52, 0, 127, 50], 'bw' => [0, 51, 127, 63]],
    'A4' => ['cover' => [2, 3, 44], 'disc' => [40, 25, 21], 'x' => 66, 'n' => 10, 'lines' => [2, 14, 26, 38], 'pitch' => 12,
        'time' => [0, 127, 52, 61, 59, true], 'cw' => [0, 0, 63, 50], 'tw' => [64, 0, 127, 50], 'bw' => [0, 51, 127, 63]],
];

mode_register('spotify', [
    'order' => 38,
    'label' => ['en' => 'Spotify', 'de' => 'Spotify'],
    'rotatable' => true,
    // Oben rechts stehen Titel und die Ansage mit Countdown, die Ecke eines Timers kommt
    // deshalb nach unten rechts, ans Ende der Zeitzeile. Aufs Cover kommt nie etwas.
    'corner' => static function (array $ctx): array {
        $L = SPOTIFY_LAYOUTS[$ctx['settings']['spotify']['layout'] ?? 'A1'] ?? SPOTIFY_LAYOUTS['A1'];
        $y = (int) $L['time'][2];
        return ['box' => [96, $y - 1, 32, 9], 'right' => 128, 'y' => $y];
    },
    'defaults' => ['layout' => 'A1', 'views' => [], 'view' => 10, 'first' => false],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (isset($in['layout']) && is_string($in['layout']) && isset(SPOTIFY_LAYOUTS[$in['layout']])) {
            $out['layout'] = $in['layout'];
        }
        if (array_key_exists('views', $in)) {
            $want = is_array($in['views']) ? $in['views'] : [];
            $out['views'] = array_values(array_filter(SPOTIFY_VIEWS, static fn(string $v): bool => in_array($v, $want, true)));
        }
        if (array_key_exists('view', $in)) {
            $out['view'] = clamp_int($in['view'], SPOTIFY_VIEW_MIN, SPOTIFY_VIEW_MAX, (int) ($cur['view'] ?? 10));
        }
        if (array_key_exists('first', $in)) {
            $out['first'] = (bool) $in['first'];
        }
        return $out;
    },
    'build' => 'spotify_build',
    'active' => 'spotify_active',
]);

/** Kann das Geraet Zeitleiste, Platte und die neuen Uebergaenge? Die Vorschau immer. */
function spotify_fw_ok(array $ctx): bool
{
    if (!empty($ctx['preview'])) {
        return true;
    }
    $fw = (string) ($ctx['device']['fw'] ?? '');
    return $fw !== '' && version_compare($fw, SPOTIFY_FW, '>=');
}

/** Laeuft Musik? Fuer die Rotation: ohne Musik faellt der Modus heraus. */
function spotify_active(array $ctx): bool
{
    if (!spotify_configured()) {
        return false;
    }
    $v = spotify_now((int) ($ctx['owner']['id'] ?? 0));
    if (!is_array($v) || !isset($v['item'])) {
        return false;
    }
    if ($v['state'] === 'play') {
        return true;
    }
    $now = (int) round(((float) ($ctx['now'] ?? microtime(true))) * 1000);
    return $v['state'] === 'pause' && $now - (int) ($v['since'] ?? 0) < SPOTIFY_PAUSE_IDLE_MS;
}

function spotify_build(array $ctx, float $from, float $to): array
{
    $lang = $ctx['lang'];
    $uid = (int) ($ctx['owner']['id'] ?? 0);
    if (!spotify_configured()) {
        return [frame_page($from, $to, spotify_note_ops($lang, 'setup'), ['id' => 'spotify:setup'])];
    }
    $v = spotify_now($uid);
    $state = is_array($v) ? (string) $v['state'] : 'error';
    if ($state === 'unlinked') {
        return [frame_page($from, $to, spotify_note_ops($lang, 'connect'), ['id' => 'spotify:connect'])];
    }
    if (in_array($state, ['not_allowed', 'revoked', 'error', 'ad'], true)) {
        return [frame_page($from, $to, spotify_note_ops($lang, $state), ['id' => 'spotify:' . $state])];
    }
    if (!spotify_active($ctx)) {
        // Nichts laeuft, oder die Pause ist lang: die Uhr, wie im Uhrmodus eingestellt.
        return [frame_page($from, $to, clock_ops($ctx), ['id' => 'spotify:idle'])];
    }
    $item = $v['item'];
    $s = $ctx['settings']['spotify'];
    $queue = $v['state'] === 'play' ? spotify_queue($uid, $item['id']) : [];
    if ($queue === null) {
        // Spotify spielt schon den naechsten Titel, "Laeuft" war veraltet: einmal neu fragen.
        spotify_forget_now($uid);
        $v = spotify_now($uid);
        if (!is_array($v) || !isset($v['item']) || !in_array($v['state'], ['play', 'pause'], true)) {
            return [frame_page($from, $to, clock_ops($ctx), ['id' => 'spotify:idle'])];
        }
        $item = $v['item'];
        $queue = $v['state'] === 'play' ? (spotify_queue($uid, $item['id']) ?? []) : [];
    }
    $album = in_array('album', $s['views'], true) && $item['album']['id'] !== '' ? spotify_album($uid, $item['album']['id']) : null;
    $planKey = 'spot:' . $uid . ':plan:' . (!empty($ctx['preview']) ? 'p' : 'd') . (int) ($ctx['device']['id'] ?? 0);
    $plan = cache_get($planKey);
    $pages = spotify_pages($ctx, $v, $queue, $album, static fn(array $it): ?array => spotify_cover((string) ($it['image'] ?? '')), $from, $to);
    // Walze oder Karussell erst jetzt entscheiden, nach Warteschlange und Cover: das dauert kurz
    // nach einem Titelwechsel bis zu einer Sekunde. Gefragt ist, was das Panel zeigt, wenn die
    // Antwort ankommt. Mit dem Stand vom Beginn des Rechnens hatte das Geraet den Wechsel oft
    // schon nach dem alten Plan gezeigt und bekam die Walze ein zweites Mal.
    // $ctx['now'] ist der Beginn des Frames (frame_build), davor fragte schon die Auswahl der
    // Modi Spotify, was laeuft. Hoechstens 15 Sekunden, falls jemand eine feste Zeit uebergibt.
    if ($v['state'] === 'play') {
        $start = (float) ($ctx['now'] ?? microtime(true));
        $spent = min(15.0, max(0.0, microtime(true) - $start));
        $arrive = (int) round(($start + $spent) * 1000) + SPOTIFY_ARRIVE_MS;
        $pages = spotify_change_fx($pages, spotify_plan_shown($plan, $arrive), $v, SPOTIFY_LAYOUTS[$s['layout']] ?? SPOTIFY_LAYOUTS['A1']);
    }
    // Wann jeder Titel des Plans endet: daran erkennt der naechste Abruf einen Sprung.
    $ends = [];
    if ($v['state'] === 'play') {
        $end = (int) $v['start'] + max(1000, (int) $item['dur']);
        $ends[$item['id']] = $end;
        if (isset($queue[0])) {
            $ends[$queue[0]['id']] = $end + max(1000, (int) $queue[0]['dur']);
        }
    }
    cache_put($planKey, spotify_plan_of($pages, $ends), 120);
    return $pages;
}

/** Welcher Titel laut dem letzten Plan gerade auf dem Panel steht, oder null. */
function spotify_plan_shown(mixed $plan, int $nowMs): ?array
{
    foreach (is_array($plan) ? $plan : [] as $p) {
        if (is_array($p) && (int) ($p['a'] ?? 0) <= $nowMs && $nowMs < (int) ($p['b'] ?? 0)) {
            return $p;
        }
    }
    return null;
}

/**
 * Plan fuer den naechsten Abruf: von, bis, Titel, Ansicht, welcher Titel danach kam und wann
 * der Titel laut Plan endet ($ends, Titel => ms).
 */
function spotify_plan_of(array $pages, array $ends = []): array
{
    $out = [];
    $n = count($pages);
    foreach ($pages as $i => $p) {
        $parts = explode(':', (string) ($p['id'] ?? ''));
        $next = null;
        for ($j = $i + 1; $j < $n; $j++) {
            $q = explode(':', (string) ($pages[$j]['id'] ?? ''));
            if (($q[1] ?? '') !== ($parts[1] ?? '')) {
                $next = $q[1] ?? null;
                break;
            }
        }
        $id = $parts[1] ?? '';
        $out[] = ['a' => (int) $p['from'], 'b' => (int) $p['to'], 'id' => $id, 'v' => $parts[2] ?? '', 'next' => $next, 'end' => (int) ($ends[$id] ?? 0)];
    }
    return $out;
}

/**
 * Seiten fuer einen Stand von Spotify, ohne Netz: fuer die Pruefungen in
 * tools/spotify-fixtures.php genauso wie fuer den Abruf.
 *   $v      spotify_now(): state play oder pause, item, pos, start, since
 *   $queue  die naechsten Titel, der erste wird angesagt
 *   $album  spotify_album() oder null
 *   $cover  fn(array $item): ?array, die Bitmaps aus cover_bitmaps()
 *   $shown  was laut letztem Plan gerade auf dem Panel steht (Titel, Ansicht, naechster)
 */
function spotify_pages(array $ctx, array $v, array $queue, ?array $album, callable $cover, float $from, float $to, ?array $shown = null): array
{
    $s = $ctx['settings']['spotify'];
    $L = SPOTIFY_LAYOUTS[$s['layout']] ?? SPOTIFY_LAYOUTS['A1'];
    $nowMs = (int) round(((float) ($ctx['now'] ?? microtime(true))) * 1000);
    $fromMs = (int) round($from * 1000);
    $toMs = (int) round($to * 1000);
    $item = $v['item'];
    $opt = [
        'lang' => $ctx['lang'],
        'fw' => spotify_fw_ok($ctx),
        'cover' => $cover,
        'playing' => $v['state'] === 'play',
        'since' => (int) ($v['since'] ?? $nowMs),
    ];

    if ($v['state'] !== 'play') {
        // Pause: eine Seite, der Balken steht grau, darueber PAUSE.
        $st = ['item' => $item, 'start' => $nowMs - (int) $v['pos'], 'pos' => (int) $v['pos'], 'end' => 0];
        return [frame_page($from, $to, spotify_view_ops('now', $L, $st, $opt + ['queue' => $queue, 'album' => $album], $fromMs), ['id' => 'spotify:' . $item['id'] . ':now'])];
    }

    $viewMs = 1000 * max(SPOTIFY_VIEW_MIN, min(SPOTIFY_VIEW_MAX, (int) ($s['view'] ?? 10)));
    $extras = array_values(array_filter(is_array($s['views'] ?? null) ? $s['views'] : [], static fn($x): bool => in_array($x, SPOTIFY_VIEWS, true)));
    $start = (int) $v['start'];
    $dur = max(1000, (int) $item['dur']);
    $end = max($start + $dur, $nowMs + 1000);
    $next = $queue[0] ?? null;
    $teaser = $next !== null && $dur > 25000 ? $end - SPOTIFY_TEASER_MS : null;

    $segs = [];
    spotify_song_segs($segs, $item, $start, $end, $teaser, spotify_cycle($extras, $item, $album, $queue), $viewMs, $fromMs, $toMs);
    if ($next !== null && $end < $toMs) {
        $nextEnd = $end + max(1000, (int) $next['dur']);
        spotify_song_segs($segs, $next, $end, $nextEnd, null, spotify_cycle($extras, $next, null, array_slice($queue, 1)), $viewMs, $fromMs, $toMs);
    }

    $pages = [];
    $prevView = null;
    foreach ($segs as $k => [$a, $b, $it, $view]) {
        $pa = max($a, $fromMs);
        $pb = min($b, $toMs);
        if ($pb <= $pa) {
            $prevView = $view;
            continue;
        }
        $isNext = $next !== null && $it['id'] === $next['id'] && $a >= $end;
        $st = $isNext
            ? ['item' => $it, 'start' => $end, 'pos' => 0, 'end' => $end + max(1000, (int) $it['dur'])]
            : ['item' => $it, 'start' => $start, 'pos' => 0, 'end' => $end];
        $id = 'spotify:' . $it['id'] . ':' . $view;
        $last = count($pages) - 1;
        if ($last >= 0 && $pages[$last]['id'] === $id && $pages[$last]['to'] === $pa) {
            $pages[$last]['to'] = $pb;
            $prevView = $view;
            continue;
        }
        $extra = ['id' => $id];
        $nowish = in_array($view, ['now', 'teaser'], true) && in_array($prevView, ['now', 'teaser'], true);
        if ($pa === $a && $isNext && $a === $end && $nowish) {
            // Titel laeuft aus: die Walze. Nach der Ansage steht der neue Titel schon eine Zeile
            // tiefer da und schiebt sich hoch, ohne Ansage rollt jede Zeile um.
            $extra += spotify_fx('reel', $L, $teaser !== null);
        } elseif ($pa === $a && $view === 'teaser' && $prevView === 'now') {
            $extra += spotify_fx('lines', $L, false);
        }
        $opts = $opt + ['queue' => $isNext ? array_slice($queue, 1) : $queue, 'album' => $isNext ? null : $album, 'next' => $view === 'teaser' ? $next : null];
        $pages[] = frame_page($pa / 1000, $pb / 1000, spotify_view_ops($view, $L, $st, $opts, $pa), $extra);
        $prevView = $view;
    }
    return spotify_change_fx($pages, $shown, $v, $L);
}

/**
 * Auf dem Panel steht laut letztem Plan ($shown) noch ein anderer Titel. Kam der neue als
 * Naechster und hoechstens drei Sekunden vor dem geplanten Ende (Ueberblenden, Luecken), ist
 * der alte ausgelaufen: die Walze. Sonst wurde am Handy gesprungen, auch auf den naechsten
 * Titel der Warteschlange: das Karussell. Zeigt das Panel den Titel schon, kein Uebergang.
 */
function spotify_change_fx(array $pages, ?array $shown, array $v, array $L): array
{
    $item = $v['item'] ?? null;
    if (!$pages || $shown === null || !is_array($item) || ($shown['id'] ?? '') === '' || $shown['id'] === $item['id'] || isset($pages[0]['fx'])) {
        return $pages;
    }
    $firstView = explode(':', (string) $pages[0]['id'])[2] ?? '';
    if (in_array($firstView, ['now', 'teaser'], true) && in_array($shown['v'] ?? '', ['now', 'teaser'], true)) {
        $natural = ($shown['next'] ?? null) === $item['id'] && (int) $v['start'] >= (int) ($shown['end'] ?? PHP_INT_MAX) - 3000;
        $pages[0] += spotify_fx($natural ? 'reel' : 'carousel', $L, $natural && ($shown['v'] ?? '') === 'teaser');
    }
    return $pages;
}

/** Reihenfolge der Ansichten eines Titels: "Laeuft", dazwischen je eine der anderen. */
function spotify_cycle(array $extras, array $item, ?array $album, array $queue): array
{
    $cycle = ['now'];
    foreach ($extras as $e) {
        if ($e === 'album' && ($item['kind'] !== 'track' || $album === null)) {
            continue;
        }
        if ($e === 'queue' && !$queue) {
            continue;
        }
        $cycle[] = $e;
        $cycle[] = 'now';
    }
    if (count($cycle) > 1) {
        array_pop($cycle);
    }
    return $cycle;
}

/**
 * Abschnitte eines Titels: die Ansichten reihum, je $viewMs lang ab Beginn des Titels,
 * zuletzt die Ansage. Nur was [von, bis] beruehrt.
 */
function spotify_song_segs(array &$segs, array $item, int $start, int $end, ?int $teaser, array $cycle, int $viewMs, int $fromMs, int $toMs): void
{
    $mainEnd = $teaser ?? $end;
    $n = max(1, count($cycle));
    $i = $fromMs > $start ? intdiv($fromMs - $start, $viewMs) : 0;
    for ($t = $start + $i * $viewMs; $t < $mainEnd && $t < $toMs; $t += $viewMs, $i++) {
        $segs[] = [$t, min($mainEnd, $t + $viewMs), $item, $cycle[$i % $n]];
    }
    if ($teaser !== null && $teaser < $toMs) {
        $segs[] = [$teaser, $end, $item, 'teaser'];
    }
}

/** Uebergang fuer die Firmware ab 0.2.0: Fenster fuer Cover, Text und Zeitleiste. */
function spotify_fx(string $fx, array $L, bool $shift): array
{
    $w = ['t' => $L['tw'], 'l' => $L['lines'], 'p' => $L['pitch']];
    if ($fx !== 'lines') {
        $w += ['c' => $L['cw'], 'b' => $L['bw'], 's' => $shift ? 1 : 0];
    }
    return ['fx' => $fx, 'fxw' => $w];
}

/* ---------- Text ---------- */

/** Wie fold() im Entwurf: ohne Akzente, gross, nur was die Schrift kennt. */
function spotify_fold(string $s): string
{
    return panel_text($s, true, 400);
}

/** Zusaetze wie "- 2011 Remaster" oder "(Radio Edit)" fallen weg. */
function spotify_clean_title(string $s): string
{
    $s = preg_replace('/\s+-\s+(\d{4}\s+)?(Remaster(ed)?|Radio Edit|Mono|Stereo|Live)(\s+\d{4})?.*$/i', '', $s) ?? $s;
    $s = preg_replace('/\s*[\(\[](\d{4}\s+)?(Remaster(ed)?|Radio Edit|Mono|Stereo)(\s+\d{4})?(\s+Version)?[\)\]]\s*$/i', '', $s) ?? $s;
    return trim($s);
}

/** Auf n Zeichen, lieber an einer Wortgrenze, wenn die nicht zu weit vorn liegt. */
function spotify_cut(string $s, int $n): string
{
    if (strlen($s) <= $n) {
        return $s;
    }
    $sp = strrpos(substr($s, 0, $n + 1), ' ');
    if ($sp !== false && $sp >= max(3, $n - 6)) {
        return rtrim(substr($s, 0, $sp), ' ,.:;-');
    }
    return substr($s, 0, $n);
}

/** Kuenstler: alle, wenn sie passen, sonst der erste und "+2". */
function spotify_artists(array $list, int $n): string
{
    $list = array_values(array_filter(array_map(static fn($a): string => spotify_fold((string) $a), $list), static fn(string $a): bool => $a !== ''));
    if (!$list) {
        return '';
    }
    $all = implode(', ', $list);
    if (strlen($all) <= $n) {
        return $all;
    }
    $more = count($list) > 1 ? ' +' . (count($list) - 1) : '';
    return spotify_cut($list[0], $n - strlen($more)) . $more;
}

/** Umbruch an Wortgrenzen, hoechstens $max Zeilen zu $n Zeichen. over: passt nicht ganz. */
function spotify_wrap(string $s, int $n, int $max): array
{
    $lines = [];
    $cur = '';
    foreach (explode(' ', $s) as $w) {
        if ($w === '') {
            continue;
        }
        if ($cur === '') {
            $cur = $w;
        } elseif (strlen($cur . ' ' . $w) <= $n) {
            $cur .= ' ' . $w;
        } else {
            $lines[] = $cur;
            $cur = $w;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    $over = count($lines) > $max;
    foreach ($lines as $l) {
        if (strlen($l) > $n) {
            $over = true;
        }
    }
    return ['lines' => array_map(static fn(string $l): string => substr($l, 0, $n), array_slice($lines, 0, $max)), 'over' => $over];
}

function spotify_mmss(int $sec): string
{
    $sec = max(0, $sec);
    return intdiv($sec, 60) . ':' . str_pad((string) ($sec % 60), 2, '0', STR_PAD_LEFT);
}

function spotify_tr(string $lang, string $en, string $de): string
{
    return $lang === 'de' ? $de : $en;
}

/* ---------- Ansichten ---------- */

/** Zeichenbefehle einer Ansicht zum Zeitpunkt $atMs (fuer stehende Werte bei alter Firmware). */
function spotify_view_ops(string $view, array $L, array $st, array $opt, int $atMs): array
{
    return match ($view) {
        'album' => spotify_album_ops($st, $opt, $atMs),
        'queue' => spotify_queue_ops($st, $opt, $atMs),
        'cover' => spotify_cover_ops($st, $opt, $atMs),
        default => spotify_now_ops($L, $st, $opt, $atMs, $view === 'teaser' ? ($opt['next'] ?? null) : null),
    };
}

/** Cover als bmp, oder ein Rahmen, wenn es keins gibt (lokale Datei, Bild nicht lesbar). */
function spotify_cover_op(callable $cover, array $item, int $x, int $y, int $size): array
{
    $c = $cover($item);
    $b = is_array($c) ? ($c[(string) $size] ?? null) : null;
    if (!is_array($b) || empty($b['d'])) {
        return [op_frame($x, $y, $size, $size, '2C353C')];
    }
    return [['t' => 'bmp', 'x' => $x, 'y' => $y, 'w' => (int) $b['w'], 'h' => (int) $b['h'], 'p' => array_values($b['p']), 'd' => (string) $b['d']]];
}

/** Laufschrift ab x, links davon wird die Zeile geloescht (das Cover kommt spaeter darueber). */
function spotify_ticker(int $x, int $y, string $s, string $c): array
{
    return [op_ticker($x, $y, $s, $c, 12), op_rect(0, $y, $x, 7, '000000')];
}

/**
 * Titel, Kuenstler, Album in den Zeilen $ys wie drawSongText() im Entwurf. $three: nur drei
 * Zeilen (die Ansage laesst die vierte weg, sonst ragte sie unten aus dem Textfenster).
 * Heisst das Album wie der Titel, steht statt des doppelten Namens TITELSONG und das Jahr;
 * der Entwurf schrieb hier SINGLE, das stimmt bei einem Album mit mehreren Titeln nicht.
 */
function spotify_song_text(array $L, array $item, array $ys, string $lead, string $lang, bool $three = false): array
{
    $X = $L['x'];
    $N = $L['n'];
    $title = spotify_fold(spotify_clean_title((string) $item['title']));
    $album = spotify_fold((string) $item['album']['name']);
    $ops = [];
    $runTitle = strlen($title) > $N;
    if ($runTitle) {
        array_push($ops, ...spotify_ticker($X, $ys[0], $title, C_WHITE));
    } else {
        $ops[] = op_text($X, $ys[0], $title, C_WHITE);
    }
    $ops[] = op_text($X, $ys[1], spotify_artists($item['artists'], $N), $lead);
    $single = $item['kind'] === 'track' && ($item['album']['type'] === 'single' || (int) $item['album']['total'] === 1);
    if ($single || $album === '' || $album === $title) {
        $label = match (true) {
            $item['kind'] === 'episode' => 'PODCAST',
            $single || $album === '' => 'SINGLE',
            default => spotify_tr($lang, 'TITLE TRACK', 'TITELSONG'),
        };
        $ops[] = op_text($X, $ys[2], $label, C_DIM);
        if (!$three && $item['album']['year'] !== '') {
            $ops[] = op_text($X, $ys[3], (string) $item['album']['year'], C_DIM);
        }
        return $ops;
    }
    $w = spotify_wrap($album, $N, 2);
    if (!$w['over'] || $runTitle || $three) {
        foreach ($w['lines'] as $i => $l) {
            if ($three && $i > 0) {
                break;
            }
            $ops[] = op_text($X, $ys[2 + $i], $l, C_DIM);
        }
    } else {
        array_push($ops, ...spotify_ticker($X, $ys[2], $album, C_DIM));
    }
    return $ops;
}

/** "Laeuft" im gewaehlten Layout, mit Ansage, wenn $next gesetzt ist. */
function spotify_now_ops(array $L, array $st, array $opt, int $atMs, ?array $next): array
{
    $item = $st['item'];
    $lang = $opt['lang'];
    $covers = $opt['cover']($item);
    $lead = !empty($L['lead']) && is_array($covers) && !empty($covers['lead']) ? (string) $covers['lead'] : C_ACCENT;
    $ys = $L['lines'];
    $ops = [];
    if ($next !== null) {
        // Ansage: oben DANACH mit Countdown, darunter der naechste Titel eine Zeile tiefer, genau
        // so, wie er gleich dasteht.
        $ops[] = op_text($L['x'], $ys[0], $L['n'] >= 12 ? spotify_tr($lang, 'UP NEXT', 'DANACH') : spotify_tr($lang, 'NEXT', 'DANN'), C_DIM);
        if ($opt['fw']) {
            $ops[] = ['t' => 'count', 'x' => 127, 'y' => $ys[0], 'c' => C_ACCENT, 'to' => (int) $st['end'], 'a' => 'r'];
        }
        $nextCovers = $opt['cover']($next);
        $nextLead = !empty($L['lead']) && is_array($nextCovers) && !empty($nextCovers['lead']) ? (string) $nextCovers['lead'] : C_ACCENT;
        array_push($ops, ...spotify_song_text($L, $next, [$ys[1], $ys[2], $ys[3]], $nextLead, $lang, true));
    } else {
        array_push($ops, ...spotify_song_text($L, $item, $ys, $lead, $lang));
    }
    // Klassisch, Gross, Platte: der Balken gruen. Farbe aus dem Cover: in der Leitfarbe, ohne sie Bernstein.
    array_push($ops, ...spotify_time_ops($L['time'], $st, $opt, $atMs, !empty($L['lead']) ? $lead : C_GREEN, $next !== null));
    if (isset($L['disc']) && $opt['fw']) {
        [$cx, $cy, $r] = $L['disc'];
        $ops[] = ['t' => 'disc', 'cx' => $cx, 'cy' => $cy, 'r' => $r, 'x' => $L['cover'][0] + $L['cover'][2], 'sp' => $opt['playing'] ? 1 : 0, 't0' => (int) $opt['since']];
    }
    [$cx, $cy, $cs] = $L['cover'];
    array_push($ops, ...spotify_cover_op($opt['cover'], $item, $cx, $cy, $cs));
    return $ops;
}

/**
 * Zeitleiste: gespielt gruen (A3: Leitfarbe), Rest als Punkte, Kopf weiss, darueber 0:00, die
 * laufende Zeit ueber dem Kopf und das Ende. Mit Pause grau und PAUSE. Ab Firmware 0.2.0
 * zaehlt das Geraet selbst (prog), sonst ein stehender Balken zum Zeitpunkt $atMs.
 */
function spotify_time_ops(array $T, array $st, array $opt, int $atMs, string $bar, bool $soon): array
{
    [$x0, $x1, $ly, $by, $hy, $zero] = $T;
    $dur = max(1000, (int) $st['item']['dur']);
    $paused = !$opt['playing'];
    $played = $paused ? C_DIM : $bar;
    if ($opt['fw']) {
        $op = ['t' => 'prog', 'x' => $x0, 'x1' => $x1, 'y' => $ly, 'b' => $by, 'hy' => $hy, 't0' => (int) $st['start'], 'd' => $dur, 'c' => $played];
        if ($zero) {
            $op['s'] = 1;
        }
        if ($soon) {
            $op['soon'] = 1;
        }
        if ($paused) {
            $op['p'] = (int) $st['pos'];
            $op['pl'] = spotify_tr($opt['lang'], 'PAUSED', 'PAUSE');
        }
        return [$op];
    }
    // Firmware vor 0.2.0: Balken und Ende stehen, neu gezeichnet mit jedem Abruf.
    $span = $x1 - $x0 + 1;
    $pos = $paused ? (int) $st['pos'] : max(0, min($dur, $atMs - (int) $st['start']));
    $w = (int) round($span * $pos / $dur);
    $ops = [];
    if ($w < $span) {
        $ops[] = op_rect($x0 + $w, $by, $span - $w, 2, $soon ? C_ACCENT : C_DOTS) + ['dot' => 2];
    }
    if ($w > 0) {
        $ops[] = op_rect($x0, $by, $w, 2, $played);
    }
    $ops[] = op_rect($x0 + min($span - 1, $w), $hy, 1, $by + 2 - $hy, $paused ? C_DIM : C_WHITE);
    $endText = $paused ? spotify_tr($opt['lang'], 'PAUSED', 'PAUSE') : spotify_mmss(intdiv($dur, 1000));
    $ops[] = op_text($x1 - panel_width($endText), $ly, $endText, C_DIM);
    return $ops;
}

/** Album: Cover gross, Name, Kuenstler, Titelnummer, Jahr, je Titel ein Stueck Balken. */
function spotify_album_ops(array $st, array $opt, int $atMs): array
{
    $item = $st['item'];
    $alb = $opt['album'] ?? null;
    $X = 68;
    $N = 10;
    $ops = [];
    $name = spotify_fold((string) ($alb['name'] ?? $item['album']['name']));
    $w = spotify_wrap($name, $N, 3);
    foreach ($w['lines'] as $i => $l) {
        $ops[] = op_text($X, 2 + $i * 9, $l, C_WHITE);
    }
    $y = 2 + count($w['lines']) * 9 + 3;
    $ops[] = op_text($X, $y, spotify_artists(is_array($alb['artists'] ?? null) ? $alb['artists'] : $item['artists'], $N), C_ACCENT);
    $tracks = is_array($alb['tracks'] ?? null) ? $alb['tracks'] : [];
    $total = max(count($tracks), (int) ($alb['total'] ?? $item['album']['total']));
    $idx = -1;
    foreach ($tracks as $i => $t) {
        if ($t['id'] === $item['id'] || ((int) $t['no'] === (int) $item['no'] && (int) $t['disc'] === (int) $item['disc'] && $idx < 0)) {
            $idx = $i;
            if ($t['id'] === $item['id']) {
                break;
            }
        }
    }
    $no = $idx >= 0 ? $idx + 1 : max(1, (int) $item['no']);
    $ops[] = op_text($X, $y + 12, $no . '/' . $total, C_DIM);
    $year = (string) ($alb['year'] ?? $item['album']['year']);
    if ($year !== '') {
        $ops[] = op_text(127 - panel_width($year), $y + 12, $year, C_DIM);
    }
    $dur = max(1000, (int) $item['dur']);
    $pos = !$opt['playing'] ? (int) $st['pos'] : max(0, min($dur, $atMs - (int) $st['start']));
    $n = count($tracks);
    $span = 127 - $X - ($n - 1);
    if ($n >= 2 && $idx >= 0 && $span >= $n && $n === $total) {
        $sum = array_sum(array_map(static fn(array $t): int => max(1, (int) $t['dur']), $tracks));
        $x = $X;
        $acc = 0;
        foreach ($tracks as $i => $t) {
            $acc += max(1, (int) $t['dur']);
            $segEnd = $X + $i + (int) round($span * $acc / $sum);
            $sw = max(1, $segEnd - $x);
            if ($i < $idx) {
                $ops[] = op_rect($x, 57, $sw, 3, C_GREEN);
            } elseif ($i > $idx) {
                $ops[] = op_rect($x, 57, $sw, 3, C_DOTS);
            } else {
                $f = (int) round($sw * $pos / $dur);
                if ($f < $sw) {
                    $ops[] = op_rect($x + $f, 56, $sw - $f, 4, C_DOTS);
                }
                if ($f > 0) {
                    $ops[] = op_rect($x, 56, $f, 4, C_GREEN);
                }
                $ops[] = op_rect(min($x + $f, $x + $sw - 1), 54, 1, 6, C_WHITE);
            }
            $x = $segEnd + 1;
        }
    } else {
        // Ohne Titelliste (Folge, sehr langes Album) ein einfacher Balken fuer den Titel.
        $wbar = (int) round(59 * $pos / $dur);
        if ($wbar < 59) {
            $ops[] = op_rect($X + $wbar, 57, 59 - $wbar, 3, C_DOTS);
        }
        if ($wbar > 0) {
            $ops[] = op_rect($X, 57, $wbar, 3, C_GREEN);
        }
    }
    array_push($ops, ...spotify_cover_op($opt['cover'], $item, 0, 0, 64));
    return $ops;
}

/** Als Naechstes: wie eine Abfahrtstafel, jetzt und die drei folgenden mit Minuten bis dahin. */
function spotify_queue_ops(array $st, array $opt, int $atMs): array
{
    $lang = $opt['lang'];
    $ops = [op_text(2, 1, spotify_tr($lang, 'UP NEXT', 'ALS NAECHSTES'), C_DIM)];
    $ops[] = op_text(127 - panel_width('SPOTIFY'), 1, 'SPOTIFY', C_SPOTIFY);
    $ops[] = op_rect(0, 9, 128, 1, C_LINE);
    $list = array_merge([$st['item']], array_slice($opt['queue'] ?? [], 0, 3));
    $until = max(0, (int) $st['end'] - $atMs);
    foreach ($list as $i => $it) {
        $y = 12 + $i * 12;
        if ($i === 0) {
            $when = spotify_tr($lang, 'NOW', 'JETZT');
        } else {
            $when = (string) max(1, intdiv($until, 60000));
            $until += max(0, (int) $it['dur']);
        }
        $ww = panel_width($when);
        $ops[] = op_text(127 - $ww, $y, $when, $i === 0 ? C_GREEN : C_WHITE);
        $room = max(1, intdiv(127 - $ww - 4 - 16 + 1, 6));
        $ops[] = op_text(16, $y, spotify_cut(spotify_fold(spotify_clean_title((string) $it['title'])), $room), C_WHITE);
        array_push($ops, ...spotify_cover_op($opt['cover'], $it, 2, $y - 1, 10));
    }
    return $ops;
}

/** Nur Cover: gross in der Mitte, links die Zeit und ein kurzer Balken, rechts der Rest. */
function spotify_cover_ops(array $st, array $opt, int $atMs): array
{
    $item = $st['item'];
    $dur = max(1000, (int) $item['dur']);
    $playing = $opt['playing'];
    $pos = $playing ? max(0, min($dur, $atMs - (int) $st['start'])) : (int) $st['pos'];
    $ops = [];
    if ($opt['fw']) {
        // Ab Firmware 0.2.0 zaehlt das Geraet die Sekunden selbst, in der Pause stehen sie.
        $left = ['t' => 'count', 'x' => 16, 'y' => 22, 'c' => $playing ? C_WHITE : C_DIM, 'a' => 'c'];
        $right = ['t' => 'count', 'x' => 112, 'y' => 22, 'c' => C_DIM, 'a' => 'c', 'pre' => '-'];
        $ops[] = $playing ? $left + ['t0' => (int) $st['start'], 'm' => intdiv($dur, 1000)] : $left + ['v' => intdiv($pos, 1000)];
        $ops[] = $playing ? $right + ['to' => (int) $st['start'] + $dur] : $right + ['v' => intdiv($dur - $pos, 1000)];
        $bar = ['t' => 'prog', 'x' => 3, 'x1' => 28, 'y' => 0, 'b' => 34, 'hy' => 34, 't0' => (int) $st['start'], 'd' => $dur, 'c' => $playing ? C_GREEN : C_DIM, 'nl' => 1, 'nh' => 1];
        $ops[] = $playing ? $bar : $bar + ['p' => $pos];
    } else {
        // Aeltere Firmware kennt count und prog nicht: Zeit und Balken zum Zeitpunkt des Abrufs.
        $cur = spotify_mmss(intdiv($pos, 1000));
        $rest = '-' . spotify_mmss(intdiv($dur - $pos, 1000));
        $ops[] = op_text((int) round(16 - panel_width($cur) / 2), 22, $cur, $playing ? C_WHITE : C_DIM);
        $ops[] = op_text((int) round(112 - panel_width($rest) / 2), 22, $rest, C_DIM);
        $w = (int) round(26 * $pos / $dur);
        if ($w < 26) {
            $ops[] = op_rect(3 + $w, 34, 26 - $w, 2, C_DOTS) + ['dot' => 2];
        }
        if ($w > 0) {
            $ops[] = op_rect(3, 34, $w, 2, $playing ? C_GREEN : C_DIM);
        }
    }
    array_push($ops, ...spotify_cover_op($opt['cover'], $item, 32, 0, 64));
    return $ops;
}

/**
 * Beispiel fuer die Layout-Kacheln der Geraeteseite: ein erfundener Titel und ein gerechnetes
 * Motiv, kein echtes Cover. Fester Zeitpunkt, damit die Kacheln stillstehen. Liefert now und
 * je Layout die Zeichenbefehle.
 */
function spotify_demo(): array
{
    $cover = cache_remember('spotdemo:v1', 'spotifyimg', static function (): ?array {
        // Ohne GD gibt es kein Motiv, die Kacheln zeigen dann nur Text und Zeitleiste.
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $c = cover_bitmaps(spotify_demo_image());
        return $c === null ? null : [$c, 86400];
    }, 60);
    $nowMs = 1_000_000_000_000;
    $item = ['id' => 'demo', 'kind' => 'track', 'title' => 'Night Drive', 'artists' => ['The Wall'],
        'album' => ['id' => '', 'name' => 'Panel Sessions', 'year' => '2026', 'total' => 9, 'type' => 'album'],
        'no' => 3, 'disc' => 1, 'dur' => 192000, 'image' => '', 'url' => ''];
    $v = ['state' => 'play', 'item' => $item, 'pos' => 83000, 'at' => $nowMs, 'start' => $nowMs - 83000, 'since' => $nowMs - 600000];
    $out = [];
    foreach (array_keys(SPOTIFY_LAYOUTS) as $id) {
        $settings = device_defaults();
        $settings['spotify']['layout'] = $id;
        $ctx = ['settings' => $settings, 'device' => ['id' => 0], 'owner' => ['id' => 0], 'lang' => 'en', 'preview' => true, 'now' => $nowMs / 1000];
        $pages = spotify_pages($ctx, $v, [], null, static fn(array $it): ?array => is_array($cover) ? $cover : null, $nowMs / 1000, $nowMs / 1000 + 1);
        $out[$id] = $pages[0]['ops'] ?? [];
    }
    return ['now' => $nowMs, 'layouts' => $out];
}

/** Das Motiv der Beispiele: Nachthimmel, eine Sonne in Bernstein, eine Strasse in Cyan. */
function spotify_demo_image(): string
{
    $im = imagecreatetruecolor(300, 300);
    for ($y = 0; $y < 300; $y++) {
        imageline($im, 0, $y, 299, $y, imagecolorallocate($im, 12 + intdiv($y, 12), 16 + intdiv($y, 9), 44 + intdiv($y, 4)));
    }
    imagefilledellipse($im, 150, 150, 156, 156, imagecolorallocate($im, 255, 170, 0));
    $sky = imagecolorallocate($im, 30, 40, 90);
    for ($i = 0; $i < 5; $i++) {
        imagefilledrectangle($im, 0, 168 + $i * 13, 299, 171 + $i * 13, $sky);
    }
    imagefilledrectangle($im, 0, 232, 299, 299, imagecolorallocate($im, 10, 12, 24));
    imagesetthickness($im, 6);
    $road = imagecolorallocate($im, 53, 214, 255);
    imageline($im, 138, 232, 30, 299, $road);
    imageline($im, 162, 232, 270, 299, $road);
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

/** Hinweise ohne Musik: nicht verbunden, nicht freigeschaltet, neu verbinden, Werbung. */
function spotify_note_ops(string $lang, string $kind): array
{
    $lines = match ($kind) {
        'connect' => [spotify_tr($lang, 'CONNECT ON THE', 'AUF DER WEBSEITE'), spotify_tr($lang, 'DEVICE PAGE', 'VERBINDEN')],
        'not_allowed' => [spotify_tr($lang, 'ACCOUNT NOT', 'KONTO NOCH NICHT'), spotify_tr($lang, 'ENABLED YET', 'FREIGESCHALTET')],
        'revoked' => [spotify_tr($lang, 'CONNECT AGAIN ON', 'AUF DER WEBSEITE'), spotify_tr($lang, 'THE DEVICE PAGE', 'NEU VERBINDEN')],
        'setup' => [spotify_tr($lang, 'NOT SET UP', 'AUF DIESEM SERVER'), spotify_tr($lang, 'ON THIS SERVER', 'NICHT EINGERICHTET')],
        'ad' => [spotify_tr($lang, 'AD', 'WERBUNG'), ''],
        default => [spotify_tr($lang, 'NO ANSWER', 'ANTWORTET GERADE'), spotify_tr($lang, 'RIGHT NOW', 'NICHT')],
    };
    $ops = [op_text('c', 16, 'SPOTIFY', C_SPOTIFY)];
    if ($lines[0] !== '') {
        $ops[] = op_text('c', 32, $lines[0], C_DIM);
    }
    if ($lines[1] !== '') {
        $ops[] = op_text('c', 44, $lines[1], C_DIM);
    }
    return $ops;
}
