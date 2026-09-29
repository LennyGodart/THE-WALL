<?php
// Prueft Timer, Wecker, Klingeln, die Ecke eines laufenden Timers in den Modi, "Panel aus",
// den Kopplungscode fuer Home Assistant und das Speichern ohne verlorene Aenderungen
// (CHANGELOG 63). Ohne Netz: Flug und Wetter bekommen ihre Daten hier von Hand.
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/timer-fixtures.php
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

/** Alle Zeichenbefehle einer Seite eines Typs. */
function ops_of(array $page, string $t): array
{
    return array_values(array_filter($page['ops'], static fn(array $o): bool => ($o['t'] ?? '') === $t));
}

/** Liegt die Ecke auf der Seite: schwarzes Feld oben rechts und die Restzeit? */
function has_corner(array $page): bool
{
    foreach ($page['ops'] as $o) {
        if (($o['t'] ?? '') === 'rect' && $o['x'] === 96 && $o['c'] === '000000') {
            return true;
        }
    }
    return false;
}

$now = time();
$s = device_defaults();
$s['applied'] = true;
$s['mode'] = 'clock';
$s['rotation'] = [];
$s['tz'] = 'Europe/Luxembourg';

// ---------- Timer ----------
[$s1, $t1, $err] = timer_add($s, 300, 'Pasta mit Tomaten', 'web', $now);
check('Timer: gestellt, Ende in fuenf Minuten', $err === null && $t1['end'] === $now + 300 && $t1['total'] === 300);
check('Timer: Name auf 12 Zeichen gekuerzt', $t1['label'] === 'Pasta mit To');
[$x, , $err] = timer_add($s, 0, '', 'web', $now);
check('Timer: 0 Sekunden abgelehnt', $x === null && $err[0] === 'invalid_value');
[$x, , $err] = timer_add($s, TIMER_MAX_SECONDS + 1, '', 'web', $now);
check('Timer: mehr als 24 Stunden abgelehnt', $x === null && $err[0] === 'invalid_value');
$many = $s;
for ($i = 1; $i <= TIMER_MAX; $i++) {
    [$many] = timer_add($many, 60 * $i, '', 'ha', $now);
}
[$x, , $err] = timer_add($many, 60, '', 'ha', $now);
check('Timer: der sechste gleichzeitig abgelehnt', $x === null && $err[0] === 'too_many_timers');
check('Timer: nach Ende sortiert', array_column(timers_list($many, $now), 'total') === [60, 120, 180, 240, 300]);

$ended = $s;
$ended['timers'] = [['id' => 'aaaaaaaa', 'end' => $now - 10, 'total' => 60, 'label' => ''], ['id' => 'bbbbbbbb', 'end' => $now + 50, 'total' => 60, 'label' => '']];
$r = ring_now($ended, $now);
check('Klingeln: abgelaufener Timer klingelt seit seinem Ende', $r['since'] === $now - 10 && count($r['timers']) === 1);
$stopped = ring_stop($ended, $now);
check('Klingeln stoppen: der klingelnde faellt weg, der laufende bleibt', array_column(timers_list($stopped, $now), 'id') === ['bbbbbbbb']);
check('Klingeln: nach 15 Minuten von selbst vorbei', !in_array('aaaaaaaa', array_column(ring_now($ended, $now - 10 + RING_SECONDS)['timers'], 'id'), true));
check('Timer abbrechen: einer', array_column(timers_list(timer_cancel($ended, 'aaaaaaaa', $now), $now), 'id') === ['bbbbbbbb']);
check('Timer abbrechen: alle', timers_list(timer_cancel($ended, null, $now), $now) === []);
check('Revision: aendert sich, wenn ein Timer zu klingeln beginnt', ring_rev($ended, $now + 49) !== ring_rev($ended, $now + 50));
check('Revision: bleibt, solange nichts passiert', ring_rev($ended, $now + 20) === ring_rev($ended, $now + 30));
check('Naechstes Klingeln: Ende des laufenden Timers', ring_next($ended, $now) === $now + 50);

// ---------- Wecker ----------
[$a, $e] = alarm_apply(alarm_get($s), ['time' => '6:30', 'on' => true, 'days' => [5, 1, 2, 3, 4, 1]], 1000);
check('Wecker: 6:30 wird 06:30, Tage sortiert ohne Doppel', $e === null && $a['time'] === '06:30' && $a['days'] === [1, 2, 3, 4, 5] && $a['set'] === 1000);
[, $e] = alarm_apply(alarm_get($s), ['time' => '25:00'], 1000);
check('Wecker: 25:00 abgelehnt', $e !== null);
[, $e] = alarm_apply(alarm_get($s), ['days' => []], 1000);
check('Wecker: ohne Tag abgelehnt', $e !== null);
[, $e] = alarm_apply(alarm_get($s), ['days' => [8]], 1000);
check('Wecker: Tag 8 abgelehnt', $e !== null);

// Montag, 28. September 2026, 06:00 in Luxemburg (UTC+2).
$mon0600 = (new DateTimeImmutable('2026-09-28 06:00:00', new DateTimeZone('Europe/Luxembourg')))->getTimestamp();
$w = $s;
$w['alarm'] = ['on' => true, 'time' => '06:30', 'days' => [1, 2, 3, 4, 5], 'ack' => 0, 'set' => $mon0600 - 86400 * 7];
[$last, $next] = alarm_times(alarm_get($w), 'Europe/Luxembourg', $mon0600);
check('Wecker: naechster heute um 06:30', $next === $mon0600 + 1800);
check('Wecker: letzter am Freitag davor um 06:30', $last === $mon0600 - 3 * 86400 + 1800);
check('Wecker: klingelt um 06:31', alarm_ringing($w, $mon0600 + 1860) === $mon0600 + 1800);
check('Wecker: um 06:46 von selbst still', alarm_ringing($w, $mon0600 + 1800 + RING_SECONDS) === null);
$acked = ring_stop($w, $mon0600 + 1860);
check('Wecker gestoppt: still, morgen wieder', alarm_ringing($acked, $mon0600 + 1900) === null && ring_next($acked, $mon0600 + 1900) === $mon0600 + 86400 + 1800);
$late = $w;
$late['alarm']['set'] = $mon0600 + 2000;
check('Wecker: um 06:33 auf 06:30 gestellt, klingelt heute nicht mehr', alarm_ringing($late, $mon0600 + 2010) === null);
$sat = (new DateTimeImmutable('2026-10-03 06:31:00', new DateTimeZone('Europe/Luxembourg')))->getTimestamp();
check('Wecker: am Samstag still, wenn nur Werktage', alarm_ringing($w, $sat) === null);
// Sommerzeit 2027 beginnt am 28. Maerz um 02:00, 02:30 gibt es dann nicht.
$dst = (new DateTimeImmutable('2027-03-28 01:00:00', new DateTimeZone('Europe/Luxembourg')))->getTimestamp();
$d = $s;
$d['alarm'] = ['on' => true, 'time' => '02:30', 'days' => [7], 'ack' => 0, 'set' => 0];
[, $next] = alarm_times(alarm_get($d), 'Europe/Luxembourg', $dst);
check('Wecker: 02:30 in der Luecke der Sommerzeit liegt trotzdem in der Zukunft', $next !== null && $next > $dst && $next - $dst <= 2 * 3600);
$f = ring_frame_fields($w, $mon0600);
check('Firmware: Werktage als Bits 0 bis 4', ($f['alarm']['d'] ?? 0) === 31 && $f['alarm']['h'] === 6 && $f['alarm']['m'] === 30);
check('Firmware: Enden der Timer in ms, ring nur beim Klingeln', ring_frame_fields($ended, $now)['timers'] === [($now + 50) * 1000] && ring_frame_fields($ended, $now)['ring']['since'] === ($now - 10) * 1000);

// ---------- Rahmen mit Timer ----------
$owner = ['id' => 987654, 'username' => 'test', 'lang' => 'de'];
$dev = ['id' => 0, 'uid' => 'wall-test', 'name' => 'Test', 'note_rev' => 1, 'fw' => '0.2.0'];
$run = $s;
$run['timers'] = [['id' => 'cccccccc', 'end' => $now + 600, 'total' => 900, 'label' => 'Tee']];
$frame = frame_build($dev, $run, $owner, false);
$p0 = $frame['pages'][0];
$count = ops_of($p0, 'count');
check('Uhr mit Timer: Ecke oben rechts', has_corner($p0));
check('Uhr mit Timer: Restzeit zaehlt das Geraet selbst, rechtsbuendig in Gruen', $count && $count[0]['to'] === ($now + 600) * 1000 && $count[0]['a'] === 'r' && $count[0]['x'] === 128 && $count[0]['c'] === C_GREEN);
check('Uhr ohne Timer: keine Ecke', !has_corner(frame_build($dev, $s, $owner, false)['pages'][0]));
$old = frame_build(['fw' => '0.1.9'] + $dev, $run, $owner, false)['pages'][0];
check('Firmware vor 0.2.0: Restzeit als Text statt count', has_corner($old) && !ops_of($old, 'count'));
$long = $s;
$long['timers'] = [['id' => 'dddddddd', 'end' => $now + 6330, 'total' => 7200, 'label' => '']];
$lp = frame_build($dev, $long, $owner, false)['pages'][0];
$texts = array_column(ops_of($lp, 'text'), 's');
check('Ab 100 Minuten: 1H45 statt Minuten', in_array('1H45', $texts, true));
check('Vorschau: zeigt die Ecke auch', has_corner(frame_build($dev, $run, $owner, true)['pages'][0]));

$soon = $s;
$soon['timers'] = [['id' => 'eeeeeeee', 'end' => $now + 8, 'total' => 60, 'label' => 'Eier']];
$fr = frame_build($dev, $soon, $owner, false);
$last = $fr['pages'][count($fr['pages']) - 1];
check('Timer endet im Fenster: normale Seite endet genau dann', $fr['pages'][0]['to'] === ($now + 8) * 1000);
check('Timer endet im Fenster: danach die Seite fuers Klingeln, ohne flash', ($last['mode'] ?? '') === 'ring' && $last['from'] === ($now + 8) * 1000 && !isset($last['flash']));
check('Klingeln: Name des Timers oben', in_array('EIER', array_column(ops_of($last, 'text'), 's'), true));

$ringing = $ended;
$ringing['bright'] = 20;
$ringing['off'] = true;
$rf = frame_build($dev, $ringing, $owner, false);
check('Klingeln schlaegt Panel aus und dunkle Helligkeit', ($rf['pages'][0]['mode'] ?? '') === 'ring' && $rf['bright'] >= RING_BRIGHT_MIN);
check('Klingeln: Vorschau zeigt trotzdem den Modus', (frame_build($dev, $ringing, $owner, true)['pages'][0]['mode'] ?? '') === 'clock');
$alarmRing = $w;
$alarmRing['applied'] = true;
$aOps = ring_ops(ring_now($alarmRing, $mon0600 + 1860), ['settings' => $alarmRing, 'lang' => 'de', 'device' => $dev, 'preview' => false]);
check('Wecker klingelt: WECKER und die Uhrzeit gross', in_array('WECKER', array_column(array_filter($aOps, static fn($o) => $o['t'] === 'text'), 's'), true) && count(array_filter($aOps, static fn($o) => $o['t'] === 'clock' && $o['z'] === 3)) === 1);
check('Hinweis: Rad erst ab Firmware 0.2.1', in_array('STOPP UEBER WEBSEITE', array_column(ops_of(['ops' => $aOps], 'text'), 's'), true));

$off = $s;
$off['off'] = true;
$of = frame_build($dev, $off, $owner, false);
check('Panel aus: leere Seite, Helligkeit 0', count($of['pages']) === 1 && $of['pages'][0]['ops'] === [] && $of['bright'] === 0 && $of['pages'][0]['mode'] === 'off');
check('Panel aus: Vorschau zeigt den Modus', (frame_build($dev, $off, $owner, true)['pages'][0]['mode'] ?? '') === 'clock');
[$offSan] = device_sanitize(['off' => true], $s, 'edit');
[$offView] = device_sanitize(['off' => true], $s, 'view');
check('Panel aus: Bedienrecht darf, Leserecht nicht', $offSan['off'] === true && $offView['off'] === false);
[$noTimers] = device_sanitize(['timers' => [], 'alarm' => ['on' => true]], $run, 'owner');
check('Uebernehmen aendert weder Timer noch Wecker', $noTimers['timers'] === $run['timers'] && $noTimers['alarm'] === $run['alarm']);

// ---------- Die Modi machen Platz, nur solange ein Timer laeuft ----------
$ac = ['hex' => '3c4b21', 'callsign' => 'LCA8WE', 'reg' => 'D-ARJA', 'type' => 'A20N', 'military' => false, 'alt' => 12000, 'gs' => 300.0,
    'dst' => 10.0, 'track' => 90, 'vrate' => 0, 'lat' => 49.6, 'lon' => 6.2, 'ground' => false];
$route = ['airline' => 'Lufthansa City Airlines', 'airline_icao' => 'LCA', 'origin' => ['iata' => 'MUC', 'icao' => 'EDDM', 'city' => 'Munich', 'lat' => 48.354, 'lon' => 11.786],
    'destination' => ['iata' => 'LUX', 'icao' => 'ELLX', 'city' => 'Luxembourg', 'lat' => 49.626, 'lon' => 6.211]];
$fctx = ['settings' => $s, 'device' => $dev, 'owner' => $owner, 'lang' => 'de', 'preview' => false, 'now' => (float) $now];
$line1 = static function (array $ops): string {
    foreach ($ops as $o) {
        if ($o['t'] === 'text' && $o['x'] === 38 && $o['y'] === 2) {
            return $o['s'];
        }
    }
    return '';
};
check('Flug ohne Timer: LUFTHANSA CITY in voller Laenge', $line1(flight_ops($ac, $route, 'route', $fctx)) === 'LUFTHANSA CITY');
check('Flug mit Timer: Zeile 1 endet nach neun Zeichen', $line1(flight_ops($ac, $route, 'route', $fctx + ['corner' => $run['timers'][0]])) === 'LUFTHANSA');
check('Flug mit Timer: Rufzeichen passt ganz', $line1(flight_ops($ac, $route, 'position', $fctx + ['corner' => $run['timers'][0]])) === 'LCA8WE');

$ws = $s;
$ws['location']['place'] = 'Luxembourg-Kirchberg Nord';
$ws['weather']['view'] = 'now';
$ws['weather']['wind'] = false;
$wx = ['temp' => 14.2, 'code' => 3];
$wctx = ['settings' => $ws, 'device' => $dev, 'lang' => 'de', 'preview' => false, 'now' => (float) $now, 'wx' => $wx];
check('Wetter ohne Timer: Ort mit 20 Zeichen', mb_strlen(weather_ops($wctx)[0]['s']) === 20);
check('Wetter mit Timer: Ort mit 15 Zeichen', mb_strlen(weather_ops($wctx + ['corner' => $run['timers'][0]])[0]['s']) === 15);

$tops = [];
transit_header($tops, 'HAMILIUS QUAI', array_slice(TRANSIT_MODES, 0, 2));
$tcorner = [];
transit_header($tcorner, 'HAMILIUS QUAI', array_slice(TRANSIT_MODES, 0, 2), true);
$squares = static fn(array $ops): int => count(array_filter($ops, static fn($o) => $o['t'] === 'rect' && $o['w'] === 5));
check('Nahverkehr ohne Timer: Quadrate im Kopf', $squares($tops) === 2);
check('Nahverkehr mit Timer: keine Quadrate, der Name endet vor der Ecke', $squares($tcorner) === 0 && 2 + panel_width($tcorner[0]['s']) <= 94);

$sp = $s;
$sp['spotify']['layout'] = 'A2';
check('Spotify: Ecke unten rechts an der Zeitzeile', corner_spot('spotify', ['settings' => $sp])['y'] === 47 && corner_spot('spotify', ['settings' => $s])['y'] === 52);
check('Andere Modi: Ecke oben rechts', corner_spot('clock', ['settings' => $s]) === CORNER_SPOT);

// ---------- Datenbank: Kopplungscode, Speichern ohne Verlust ----------
$u = user_by_username('timertest') ?? user_by_id(user_create('timertest', 'timertest@example.invalid', 'geheim123', 'user', true));
$devId = device_add_test($u);
$row = q1('SELECT * FROM devices WHERE id = ?', [$devId]);
q('DELETE FROM ha_pairings WHERE device_id = ?', [$devId]);
db_insert('ha_pairings', ['id' => random_hex(16), 'device_id' => $devId, 'code' => 'K7Q2XM', 'attempts' => 0, 'created_at' => $now, 'expires_at' => $now + 300]);
$ps = device_settings($row);
$ps['applied'] = true;
$pf = frame_build($row, $ps, $u, false);
check('Kopplung: Code steht gross auf dem Panel', ($pf['pages'][0]['mode'] ?? '') === 'pairing' && in_array('K7Q 2XM', array_column(ops_of($pf['pages'][0], 'text'), 's'), true));
check('Kopplung: Vorschau zeigt ihn nie', (frame_build($row, $ps, $u, true)['pages'][0]['mode'] ?? '') !== 'pairing');
check('Kopplung: Testgeraet zeigt den Code in der Statuszeile', in_array('HOME ASSISTANT CODE K7Q 2XM', array_column(device_telemetry($row), 0), true));
q('UPDATE ha_pairings SET attempts = ? WHERE device_id = ?', [HA_PAIR_ATTEMPTS, $devId]);
check('Kopplung: nach fuenf falschen Versuchen weg', ha_pairing_pending($devId, $now) === null);
q('DELETE FROM ha_pairings WHERE device_id = ?', [$devId]);
check('Code: Eingabe mit Leerzeichen und klein', ha_code_normalize(' k7q-2xm ') === 'K7Q2XM');
$codes = '';
for ($i = 0; $i < 200; $i++) {
    $codes .= ha_code_new();
}
check('Code: nur Zeichen, die man auf dem Panel nicht verwechselt', strspn($codes, HA_CODE_ALPHABET) === strlen($codes) && !preg_match('/[0O1I2Z5S8B]/', $codes));

$calls = 0;
$saved = device_settings_update($devId, static function (array $cur) use (&$calls, $devId, $now): ?array {
    $calls++;
    if ($calls === 1) {
        // Waehrenddessen schreibt jemand anderes, etwa Home Assistant einen Timer.
        $other = $cur;
        $other['timers'] = [['id' => 'ffffffff', 'end' => $now + 999, 'total' => 999, 'label' => 'HA']];
        q('UPDATE devices SET settings = ?, settings_rev = settings_rev + 1 WHERE id = ?', [json_encode($other), $devId]);
    }
    $cur['mode'] = 'weather';
    return [$cur, false];
});
check('Gleichzeitig: zweiter Versuch auf dem neuen Stand', $calls === 2 && $saved['mode'] === 'weather' && array_column(timers_list($saved, $now), 'id') === ['ffffffff']);
$stored = device_settings(q1('SELECT * FROM devices WHERE id = ?', [$devId]));
check('Gleichzeitig: der Timer von Home Assistant ist nicht verloren', array_column(timers_list($stored, $now), 'id') === ['ffffffff'] && $stored['mode'] === 'weather');
check('Nichts zu tun: keine neue Revision', (function () use ($devId): bool {
    $before = (int) qval('SELECT settings_rev FROM devices WHERE id = ?', [$devId]);
    device_settings_update($devId, static fn(array $c): ?array => null);
    return (int) qval('SELECT settings_rev FROM devices WHERE id = ?', [$devId]) === $before;
})());
device_remove_test((int) $u['id']);

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
