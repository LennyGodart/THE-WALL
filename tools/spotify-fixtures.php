<?php
// Prueft den Spotify-Modus ohne Netz: Cover fuer das Panel, Texte, Seitenplan mit Ansage,
// Walze und Karussell, die Ansichten, Pause, alte Firmware, Rotation ohne Musik.
// Die Cover sind gerechnete Muster, keine echten Albumcover.
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/spotify-fixtures.php
// Exit-Code 1 bei einem Fehler.
declare(strict_types=1);
require __DIR__ . '/../web/.htapp/bootstrap.php';

$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok     ' : 'FEHLER ') . $what . "\n";
    if (!$ok) {
        $fail++;
    }
}

/** Ein Muster als JPEG: vier Farbfelder, ein heller Streifen, ein dunkler Rand. */
function pattern(bool $white = false): string
{
    $im = imagecreatetruecolor(300, 300);
    if ($white) {
        imagefilledrectangle($im, 0, 0, 299, 299, imagecolorallocate($im, 255, 255, 255));
    } else {
        imagefilledrectangle($im, 0, 0, 149, 149, imagecolorallocate($im, 200, 40, 40));
        imagefilledrectangle($im, 150, 0, 299, 149, imagecolorallocate($im, 30, 90, 200));
        imagefilledrectangle($im, 0, 150, 149, 299, imagecolorallocate($im, 240, 200, 40));
        imagefilledrectangle($im, 150, 150, 299, 299, imagecolorallocate($im, 10, 10, 12));
        imagefilledrectangle($im, 0, 140, 299, 160, imagecolorallocate($im, 250, 250, 250));
    }
    ob_start();
    imagejpeg($im, null, 92);
    return (string) ob_get_clean();
}

function nib(array $b, int $i): int
{
    $bytes = base64_decode($b['d']);
    $byte = ord($bytes[$i >> 1]);
    return ($i & 1) === 0 ? $byte >> 4 : $byte & 15;
}

function texts(array $ops): array
{
    return array_values(array_map(static fn($o) => (string) $o['s'], array_filter($ops, static fn($o) => $o['t'] === 'text')));
}

function fits(array $ops): bool
{
    foreach ($ops as $o) {
        if ($o['t'] === 'text' && is_int($o['x']) && ($o['x'] < 0 || $o['x'] + panel_width((string) $o['s']) > 128)) {
            return false;
        }
    }
    return true;
}

function ops_of(array $page, string $t): array
{
    return array_values(array_filter($page['ops'], static fn($o) => $o['t'] === $t));
}

// Cover.
$cov = cover_bitmaps(pattern());
check('Cover: fuenf Groessen', is_array($cov) && isset($cov['64'], $cov['48'], $cov['44'], $cov['16'], $cov['10']));
check('Cover: hoechstens 15 Farben', count($cov['48']['p']) <= 15 && count($cov['64']['p']) <= 15);
check('Cover: 48 x 48 sind 1 536 Zeichen Base64', strlen($cov['48']['d']) === 1536);
$b = $cov['48'];
check('Cover: die Ecken bleiben aus (runde Ecken)', nib($b, 0) === 0 && nib($b, 47) === 0 && nib($b, 47 * 48) === 0 && nib($b, 48 * 48 - 1) === 0);
check('Cover: das fast schwarze Feld ist aus', nib($b, 40 * 48 + 40) === 0);
check('Cover: Leitfarbe gefunden', is_string($cov['lead']) && preg_match('/^[0-9A-F]{6}$/', $cov['lead']) === 1);
$white = cover_bitmaps(pattern(true));
$maxChannel = 0;
foreach ($white['48']['p'] as $hex) {
    foreach (str_split($hex, 2) as $h) {
        $maxChannel = max($maxChannel, hexdec($h));
    }
}
check('Cover: ein weisses Cover wird gedaempft (hoechstens 58 Prozent)', $maxChannel <= 150 && $maxChannel >= 140);
check('Cover: Muell ist kein Bild', cover_bitmaps('kein bild') === null);

// Texte.
check('Titel: Remaster faellt weg', spotify_clean_title('Wish You Were Here - 2011 Remaster') === 'Wish You Were Here');
check('Titel: Remaster in Klammern faellt weg', spotify_clean_title('Bohemian Rhapsody (Remastered 2011)') === 'Bohemian Rhapsody');
check('Titel: Umlaute werden umgeschrieben', spotify_fold('Übers Meer') === 'UBERS MEER');
check('Kuenstler: der erste und +2', spotify_artists(['Daft Punk', 'Pharrell Williams', 'Nile Rodgers'], 12) === 'DAFT PUNK +2');
check('Kuenstler: alle, wenn sie passen', spotify_artists(['Queen'], 12) === 'QUEEN');
check('Kuerzen an der Wortgrenze', spotify_cut('THE DARK SIDE OF THE MOON', 12) === 'THE DARK');
$w = spotify_wrap('THE DARK SIDE OF THE MOON', 12, 2);
check('Umbruch: zwei Zeilen, passt nicht ganz', $w['lines'] === ['THE DARK', 'SIDE OF THE'] && $w['over'] === true);
check('Zeit: 5:34', spotify_mmss(334) === '5:34' && spotify_mmss(7) === '0:07');

// Einstellungen.
$m = mode_get('spotify');
$clean = ($m['sanitize'])(['layout' => 'A9', 'views' => ['album', 'lyrics', 'cover'], 'view' => 99, 'first' => 1], $m['defaults']);
check('Einstellungen: unbekanntes Layout bleibt A1', $clean['layout'] === 'A1');
check('Einstellungen: nur bekannte Ansichten, in fester Reihenfolge', $clean['views'] === ['album', 'cover']);
check('Einstellungen: Ansicht hoechstens 30 s', $clean['view'] === 30 && $clean['first'] === true);

// Seitenplan.
$now = 1790000000.0;
$nowMs = (int) ($now * 1000);
$settings = device_defaults();
$settings['applied'] = true;
$settings['mode'] = 'spotify';
$settings['lang'] = 'de';
$ctx = ['settings' => $settings, 'device' => ['id' => 7, 'fw' => '0.2.0'], 'owner' => ['id' => 1], 'lang' => 'de', 'preview' => false, 'now' => $now];
$item = ['id' => 'trk1', 'kind' => 'track', 'title' => 'Wish You Were Here - 2011 Remaster', 'artists' => ['Pink Floyd'],
    'album' => ['id' => 'alb1', 'name' => 'Wish You Were Here', 'year' => '1975', 'total' => 5, 'type' => 'album'],
    'no' => 4, 'disc' => 1, 'dur' => 334000, 'image' => 'x', 'url' => ''];
$next = ['id' => 'trk2', 'kind' => 'track', 'title' => 'Shine On You Crazy Diamond (Pts. 6-9)', 'artists' => ['Pink Floyd'],
    'album' => ['id' => 'alb1', 'name' => 'Wish You Were Here', 'year' => '1975', 'total' => 5, 'type' => 'album'],
    'no' => 5, 'disc' => 1, 'dur' => 747000, 'image' => 'x', 'url' => ''];
$cover = static fn(array $it): ?array => $cov;
$endMs = $nowMs + 12000;
$startMs = $endMs - 334000;
$v = ['state' => 'play', 'item' => $item, 'pos' => $nowMs - $startMs, 'at' => $nowMs, 'start' => $startMs, 'since' => $nowMs - 60000];
$pages = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25);
check('Plan: Laeuft, Ansage, naechster Titel', array_column($pages, 'id') === ['spotify:trk1:now', 'spotify:trk1:teaser', 'spotify:trk2:now']);
check('Plan: Ansage zehn Sekunden vor dem Ende', $pages[1]['from'] === $endMs - 10000 && $pages[1]['to'] === $endMs);
check('Plan: Ansage rollt zeilenweise herein', ($pages[1]['fx'] ?? '') === 'lines' && $pages[1]['fxw']['l'] === [2, 14, 26, 38]);
check('Plan: am Ende die Walze, nach der Ansage mit Schieben', ($pages[2]['fx'] ?? '') === 'reel' && $pages[2]['fxw']['s'] === 1 && $pages[2]['from'] === $endMs);
check('Plan: Walze kennt Cover- und Zeitfenster', $pages[2]['fxw']['c'] === [0, 0, 51, 50] && $pages[2]['fxw']['b'] === [0, 51, 127, 63]);
check('Plan: erste Seite ohne Uebergang', !isset($pages[0]['fx']));
$plan = spotify_plan_of($pages, ['trk1' => $endMs, 'trk2' => $endMs + 747000]);
$shownNow = spotify_plan_shown($plan, $nowMs);
check('Plan: merkt Titel, Ansicht, naechsten Titel und Ende', $shownNow['id'] === 'trk1' && $shownNow['v'] === 'now' && $shownNow['next'] === 'trk2' && $shownNow['end'] === $endMs);
$t = texts($pages[0]['ops']);
check('Laeuft: Titelsong statt doppeltem Namen, dazu das Jahr', in_array('PINK FLOYD', $t, true) && in_array('TITELSONG', $t, true) && in_array('1975', $t, true));
$tick = ops_of($pages[0], 'ticker');
check('Laeuft: langer Titel als Laufschrift ab x 54', count($tick) === 1 && $tick[0]['x'] === 54 && $tick[0]['s'] === 'WISH YOU WERE HERE');
check('Laeuft: links neben der Laufschrift wird geloescht', (bool) array_filter($pages[0]['ops'], static fn($o) => $o['t'] === 'rect' && $o['c'] === '000000' && $o['w'] === 54));
$prog = ops_of($pages[0], 'prog');
check('Laeuft: Zeitleiste zaehlt selbst (prog)', count($prog) === 1 && $prog[0]['t0'] === $startMs && $prog[0]['d'] === 334000 && $prog[0]['c'] === C_GREEN && ($prog[0]['s'] ?? 0) === 1);
$bmp = ops_of($pages[0], 'bmp');
check('Laeuft: Cover 48 bei 2, 2 als letzter Befehl', count($bmp) === 1 && $bmp[0]['x'] === 2 && $bmp[0]['w'] === 48 && end($pages[0]['ops'])['t'] === 'bmp');
check('Laeuft: alle Zeilen passen', fits($pages[0]['ops']) && fits($pages[1]['ops']) && fits($pages[2]['ops']));
$t = texts($pages[1]['ops']);
check('Ansage: DANACH, darunter der naechste Titel eine Zeile tiefer', $t[0] === 'DANACH' && (bool) array_filter($pages[1]['ops'], static fn($o) => ($o['t'] === 'ticker' || $o['t'] === 'text') && ($o['y'] ?? 0) === 14 && str_starts_with((string) $o['s'], 'SHINE ON')));
$cnt = ops_of($pages[1], 'count');
check('Ansage: Countdown bis zum Ende, rechtsbuendig', count($cnt) === 1 && $cnt[0]['to'] === $endMs && $cnt[0]['a'] === 'r' && $cnt[0]['x'] === 127);
check('Ansage: Restpunkte werden bernsteinfarben (soon)', (ops_of($pages[1], 'prog')[0]['soon'] ?? 0) === 1);
check('Ansage: vierte Zeile faellt weg (nichts unter y 38)', !array_filter($pages[1]['ops'], static fn($o) => $o['t'] === 'text' && is_int($o['x']) && $o['x'] >= 54 && $o['y'] > 38 && $o['y'] < 50));
check('Naechster Titel: Zeitleiste ab dem Ende', ops_of($pages[2], 'prog')[0]['t0'] === $endMs);
check('Frame bleibt klein (unter 20 KB)', strlen((string) json_encode($pages)) < 20000);

// Ohne naechsten Titel keine Ansage, kein Plan danach.
$pages = spotify_pages($ctx, $v, [], null, $cover, $now - 1, $now + 25);
check('Ohne Warteschlange: nur Laeuft, keine Ansage', array_column($pages, 'id') === ['spotify:trk1:now']);

// Weitergesprungen am Handy.
$pages = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25, ['id' => 'trkX', 'v' => 'now', 'next' => 'trk9']);
check('Sprung: erste Seite mit Karussell, jede Zeile rollt', ($pages[0]['fx'] ?? '') === 'carousel' && $pages[0]['fxw']['s'] === 0);
$pages = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25, ['id' => 'trk0', 'v' => 'teaser', 'next' => 'trk1', 'end' => $startMs + 2000]);
check('Zwei Sekunden frueher als gedacht zu Ende: Walze mit Schieben', ($pages[0]['fx'] ?? '') === 'reel' && $pages[0]['fxw']['s'] === 1);
$pages = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25, ['id' => 'trk0', 'v' => 'now', 'next' => 'trk1', 'end' => $startMs + 90000]);
check('Auf den naechsten Titel gesprungen: Karussell', ($pages[0]['fx'] ?? '') === 'carousel');
$pages = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25, ['id' => 'trk1', 'v' => 'now', 'next' => 'trk2']);
check('Derselbe Titel: kein Uebergang', !isset($pages[0]['fx']));

// Titelwechsel: Spotify meldet trk2 0,4 s nach dem geplanten Ende, der Abruf danach rechnet eine
// Sekunde. Entschieden wird nach dem Stand, wenn die Antwort ankommt (spotify_build).
$old = spotify_pages($ctx, $v, [$next], null, $cover, $now - 1, $now + 25);
$plan = spotify_plan_of($old, ['trk1' => $endMs, 'trk2' => $endMs + 747000]);
$v2 = ['state' => 'play', 'item' => $next, 'pos' => 1600, 'at' => $endMs + 2000, 'start' => $endMs + 400, 'since' => $nowMs - 60000];
$ctx2 = ['now' => ($endMs + 2000) / 1000] + $ctx;
$fresh = spotify_pages($ctx2, $v2, [], null, $cover, ($endMs + 1000) / 1000, ($endMs + 27000) / 1000);
$L = SPOTIFY_LAYOUTS['A1'];
check('Wechsel: vor dem geplanten Ende angekommen, die Walze', (spotify_change_fx($fresh, spotify_plan_shown($plan, $endMs - 1000), $v2, $L)[0]['fx'] ?? '') === 'reel');
check('Wechsel: nach dem geplanten Ende angekommen, keine zweite Walze', !isset(spotify_change_fx($fresh, spotify_plan_shown($plan, $endMs + 3300), $v2, $L)[0]['fx']));
check('Wechsel: ohne Plan kein Uebergang', !isset(spotify_change_fx($fresh, null, $v2, $L)[0]['fx']));

// "Laeuft" gilt nie ueber das Ende des Titels hinaus.
$t = ['state' => 'play', 'item' => ['dur' => 200000], 'pos' => 0];
check('Laeuft: mitten im Titel fuenf Sekunden', spotify_now_ttl($t) === 5);
check('Laeuft: 3,5 s vor dem Ende vier Sekunden', spotify_now_ttl(['pos' => 196500] + $t) === 4);
check('Laeuft: am Ende und danach eine Sekunde', spotify_now_ttl(['pos' => 200000] + $t) === 1 && spotify_now_ttl(['pos' => 199900] + $t) === 1);
check('Laeuft: Pause fuenf Sekunden', spotify_now_ttl(['state' => 'pause', 'pos' => 199900] + $t) === 5);

// Pause.
$p = ['state' => 'pause', 'item' => $item, 'pos' => 100000, 'at' => $nowMs, 'start' => $nowMs - 100000, 'since' => $nowMs - 5000];
$pages = spotify_pages($ctx, $p, [], null, $cover, $now - 1, $now + 25);
$prog = ops_of($pages[0], 'prog');
check('Pause: eine Seite, Balken grau, PAUSE', count($pages) === 1 && $prog[0]['p'] === 100000 && $prog[0]['pl'] === 'PAUSE' && $prog[0]['c'] === C_DIM);

// Firmware vor 0.2.0: stehender Balken, kein prog, count, disc.
$old = $ctx;
$old['device']['fw'] = '0.1.10';
$pages = spotify_pages($old, $v, [$next], null, $cover, $now - 1, $now + 25);
$all = array_merge(...array_map(static fn($pg) => $pg['ops'], $pages));
check('Alte Firmware: kein prog, count, disc', !array_filter($all, static fn($o) => in_array($o['t'], ['prog', 'count', 'disc'], true)));
check('Alte Firmware: Balken als Rechtecke, Ende rechts', (bool) array_filter($pages[0]['ops'], static fn($o) => $o['t'] === 'rect' && ($o['dot'] ?? 0) === 2) && in_array('5:34', texts($pages[0]['ops']), true));

// Ansichten im Wechsel.
$ctxV = $ctx;
$ctxV['settings']['spotify']['views'] = ['album', 'queue', 'cover'];
$ctxV['settings']['spotify']['view'] = 5;
$alb = ['name' => 'Wish You Were Here', 'artists' => ['Pink Floyd'], 'year' => '1975', 'total' => 5, 'tracks' => [
    ['id' => 't1', 'no' => 1, 'disc' => 1, 'dur' => 811000], ['id' => 't2', 'no' => 2, 'disc' => 1, 'dur' => 454000],
    ['id' => 't3', 'no' => 3, 'disc' => 1, 'dur' => 308000], ['id' => 'trk1', 'no' => 4, 'disc' => 1, 'dur' => 334000],
    ['id' => 'trk2', 'no' => 5, 'disc' => 1, 'dur' => 747000]]];
$mid = ['state' => 'play', 'item' => $item, 'pos' => 60000, 'at' => $nowMs, 'start' => $nowMs - 60000, 'since' => $nowMs - 60000];
$pages = spotify_pages($ctxV, $mid, [$next], $alb, $cover, $now, $now + 30);
$views = array_map(static fn($pg) => explode(':', $pg['id'])[2], $pages);
check('Ansichten: Laeuft im Wechsel mit Album, Warteschlange, Cover', $views === ['now', 'album', 'now', 'queue', 'now', 'cover']);
$a = $pages[1];
check('Album: Titel 4/5, Jahr, fuenf Stuecke Balken', in_array('4/5', texts($a['ops']), true) && in_array('1975', texts($a['ops']), true)
    && count(array_filter($a['ops'], static fn($o) => $o['t'] === 'rect' && in_array($o['y'], [56, 57], true))) >= 5 && fits($a['ops']));
$q = $pages[3];
$qt = texts($q['ops']);
check('Als Naechstes: JETZT, dann Minuten bis dahin', in_array('JETZT', $qt, true) && in_array('4', $qt, true) && fits($q['ops']));
$c = $pages[5];
check('Nur Cover: 64 bei x 32, Zeit links, Rest rechts', ops_of($c, 'bmp')[0]['x'] === 32 && count(ops_of($c, 'count')) === 2 && ops_of($c, 'count')[1]['pre'] === '-');

// Ein Album mit anderem Namen: zwei Zeilen Album.
$other = $item;
$other['title'] = 'Have a Cigar';
$pg = spotify_pages($ctx, ['state' => 'play', 'item' => $other, 'pos' => 60000, 'at' => $nowMs, 'start' => $nowMs - 60000, 'since' => $nowMs], [], null, $cover, $now, $now + 5)[0];
check('Laeuft: Album auf zwei Zeilen', array_slice(texts($pg['ops']), 0, 4) === ['HAVE A CIGAR', 'PINK FLOYD', 'WISH YOU', 'WERE HERE']);
$single = $item;
$single['album'] = ['id' => 'x', 'name' => 'Nightcall', 'year' => '2010', 'total' => 1, 'type' => 'single'];
$pg = spotify_pages($ctx, ['state' => 'play', 'item' => $single, 'pos' => 1000, 'at' => $nowMs, 'start' => $nowMs - 1000, 'since' => $nowMs], [], null, $cover, $now, $now + 5)[0];
check('Laeuft: Single mit Jahr', in_array('SINGLE', texts($pg['ops']), true) && in_array('2010', texts($pg['ops']), true));

// Layouts.
foreach (['A2' => [68, 64], 'A3' => [54, 48], 'A4' => [66, 44]] as $lay => [$x, $size]) {
    $ctxL = $ctx;
    $ctxL['settings']['spotify']['layout'] = $lay;
    $pages = spotify_pages($ctxL, $mid, [$next], null, $cover, $now, $now + 5);
    $pg = $pages[0];
    check('Layout ' . $lay . ': Text ab x ' . $x . ', Cover ' . $size, ops_of($pg, 'bmp')[0]['w'] === $size && (bool) array_filter($pg['ops'], static fn($o) => $o['t'] === 'text' && $o['x'] === $x) && fits($pg['ops']));
}
$ctxL = $ctx;
$ctxL['settings']['spotify']['layout'] = 'A4';
$pg = spotify_pages($ctxL, $mid, [$next], null, $cover, $now, $now + 5)[0];
$disc = ops_of($pg, 'disc');
check('Platte: dreht beim Spielen, rechts neben der Huelle', count($disc) === 1 && $disc[0]['sp'] === 1 && $disc[0]['x'] === 46);
$ctxL['settings']['spotify']['layout'] = 'A3';
$pg = spotify_pages($ctxL, $mid, [$next], null, $cover, $now, $now + 5)[0];
check('Farbe aus dem Cover: Kuenstler und Balken in der Leitfarbe', (bool) array_filter($pg['ops'], static fn($o) => $o['t'] === 'text' && $o['s'] === 'PINK FLOYD' && $o['c'] === $cov['lead']) && ops_of($pg, 'prog')[0]['c'] === $cov['lead']);

// Hinweise.
foreach (['connect', 'not_allowed', 'revoked', 'setup', 'error', 'ad'] as $k) {
    foreach (['en', 'de'] as $lang) {
        if (!fits(spotify_note_ops($lang, $k))) {
            check('Hinweis ' . $k . ' ' . $lang . ' passt', false);
        }
    }
}
check('Hinweise: alle passen in 21 Zeichen', true);

// Rotation: Spotify ohne Einrichtung faellt heraus, die Uhr bleibt allein.
$rot = $settings;
$rot['mode'] = 'clock';
$rot['rotation'] = ['spotify', 'clock'];
// Ein Konto ohne Spotify-Verbindung: so fragt die Pruefung nie ins Netz, auch wenn Spotify
// in der Testdatenbank eingerichtet ist.
$frame = frame_build(['id' => 7, 'fw' => '0.2.0', 'uid' => 'wall-test', 'name' => 'Test', 'note_rev' => 0], $rot, ['id' => 987654, 'username' => 'test'], false);
check('Rotation: Spotify ohne Musik faellt heraus', $frame['pages'] && !array_filter($frame['pages'], static fn($pg) => ($pg['mode'] ?? '') === 'spotify'));
check('Beteiligt: Modus, Rotation oder Vorrang', spotify_involved(['mode' => 'spotify', 'rotation' => []]) && spotify_involved(['mode' => 'clock', 'rotation' => ['spotify']]) && !spotify_involved(['mode' => 'clock', 'rotation' => ['clock']]));

echo $fail ? "\n$fail Fehler\n" : "\nAlles gut\n";
exit($fail ? 1 : 0);
