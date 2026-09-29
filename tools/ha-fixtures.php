<?php
// Prueft die Schnittstelle fuer Home Assistant (api/ha.php) ueber HTTP gegen einen laufenden
// Testserver: Koppeln mit dem Code vom Panel, Stand, Panel, Notiz, Timer, Wecker, Klingeln,
// Firmware, Trennen, Grenzen. Dazu die Felder fuer die Firmware im Rahmen und das Stoppen
// per Rad (POST /api/v1/ring/stop). CHANGELOG 63.
// Aufruf aus dem Hauptverzeichnis, der Testserver laeuft mit derselben Datenbank:
//   TW_ENV=dev php -S 127.0.0.1:8765 -t web tools/dev-router.php
//   TW_ENV=dev php tools/ha-fixtures.php [http://127.0.0.1:8765]
// Exit-Code 1 bei einem Fehler.
declare(strict_types=1);
require __DIR__ . '/../web/.htapp/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok     ' : 'FEHLER ') . $what . "\n";
    if (!$ok) {
        $fail++;
    }
}

/** HTTP-Anfrage, liefert [Status, JSON, Kopfzeilen]. */
function http(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $h = ['Accept: application/json'];
    if ($body !== null) {
        $h[] = 'Content-Type: application/json';
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", array_merge($h, $headers)),
        'content' => $body !== null ? json_encode($body) : '',
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $lines = $http_response_header ?? [];
    $status = isset($lines[0]) && preg_match('/\s(\d{3})\s/', $lines[0], $m) ? (int) $m[1] : 0;
    return [$status, json_decode((string) $raw, true), $lines];
}

$uid = 'wall-hatest' . random_int(10, 99);
$u = user_by_username('hatest') ?? user_by_id(user_create('hatest', 'hatest@example.invalid', 'geheim123', 'user', true));
$key = (string) qval('SELECT api_key FROM users WHERE id = ?', [(int) $u['id']]);
$dev = ['Authorization: Bearer ' . $key, 'X-Wall-Id: ' . $uid, 'X-Wall-Fw: 0.2.0'];
foreach (qall('SELECT id FROM devices WHERE owner_id = ?', [(int) $u['id']]) as $old) {
    device_delete((int) $old['id']);
}
q('DELETE FROM rate_limits');

/** Rahmen wie das Geraet, hoechstens einer pro Sekunde. */
function frame(): array
{
    global $base, $dev;
    static $last = 0.0;
    $wait = 1.15 - (microtime(true) - $last);
    if ($wait > 0) {
        usleep((int) ($wait * 1e6));
    }
    $last = microtime(true);
    return http('GET', $base . '/api/v1/frame', null, $dev);
}

$t1 = microtime(true);
[$st, $f, $head] = frame();
$t2 = microtime(true);
check('Geraet: erster Abruf legt es an', $st === 200 && isset($f['pages']));
check('Frame: Uhrzeit zwischen Anfrage und Antwort, in ms', ($f['now'] ?? 0) >= (int) floor($t1 * 1000) && ($f['now'] ?? 0) <= (int) ceil($t2 * 1000));
check('Frame: Server-Timing nennt die Rechenzeit', (bool) preg_grep('/^Server-Timing: app;dur=\d+$/i', $head));
$deviceId = (int) qval('SELECT id FROM devices WHERE uid = ?', [$uid]);

// ---------- Koppeln ----------
[$st, $j] = http('POST', $base . '/api/ha/v1/pair', ['device' => 'wall-gibtsnicht']);
check('Koppeln: unbekannte ID 404 unknown_device', $st === 404 && ($j['code'] ?? '') === 'unknown_device');
[$st, $j] = http('POST', $base . '/api/ha/v1/pair', ['device' => 'kein gueltiger name!']);
check('Koppeln: kaputte ID 400 bad_request', $st === 400 && ($j['code'] ?? '') === 'bad_request');
q('UPDATE devices SET last_seen_at = ? WHERE id = ?', [time() - 600, $deviceId]);
[$st, $j] = http('POST', $base . '/api/ha/v1/pair', ['device' => $uid]);
check('Koppeln: seit zehn Minuten offline 409 device_offline', $st === 409 && ($j['code'] ?? '') === 'device_offline');
frame();
[$st, $j] = http('POST', $base . '/api/ha/v1/pair', ['device' => strtoupper($uid)]);
check('Koppeln: Code angefragt, fuenf Minuten, sechs Zeichen', $st === 200 && preg_match('/^[0-9a-f]{32}$/', (string) ($j['pairing'] ?? '')) && $j['expires_in'] === 300 && $j['length'] === 6);
$pairing = (string) ($j['pairing'] ?? '');
$code = (string) qval('SELECT code FROM ha_pairings WHERE id = ?', [$pairing]);
[, $f] = frame();
$texts = array_column(array_filter($f['pages'][0]['ops'] ?? [], static fn($o) => $o['t'] === 'text'), 's');
check('Koppeln: der Code steht auf dem Panel', ($f['pages'][0]['mode'] ?? '') === 'pairing' && in_array(substr($code, 0, 3) . ' ' . substr($code, 3), $texts, true));
check('Koppeln: Helligkeit reicht zum Lesen', ($f['bright'] ?? 0) >= RING_BRIGHT_MIN);
$wrong = $code === 'AAAAAA' ? 'CCCCCC' : 'AAAAAA';
[$st, $j] = http('POST', $base . '/api/ha/v1/pair/confirm', ['pairing' => $pairing, 'code' => $wrong]);
check('Koppeln: falscher Code 422 mit vier Versuchen uebrig', $st === 422 && ($j['code'] ?? '') === 'invalid_code' && ($j['attempts_left'] ?? -1) === 4);
[$st, $j] = http('POST', $base . '/api/ha/v1/pair/confirm', ['pairing' => str_repeat('0', 32), 'code' => $code]);
check('Koppeln: unbekannte Kopplung 404 unknown_pairing', $st === 404 && ($j['code'] ?? '') === 'unknown_pairing');
$typed = strtolower(substr($code, 0, 3)) . ' - ' . strtolower(substr($code, 3));
[$st, $j] = http('POST', $base . '/api/ha/v1/pair/confirm', ['pairing' => $pairing, 'code' => $typed, 'name' => 'Home Assistant Test']);
$token = (string) ($j['token'] ?? '');
check('Koppeln: richtiger Code, klein und mit Strich, gibt einen Schluessel', $st === 200 && preg_match('/^twha_[A-Za-z0-9_-]{43}$/', $token) === 1);
check('Koppeln: Antwort bringt den Stand mit', ($j['state']['device']['uid'] ?? '') === $uid && ($j['state']['panel']['modes'][0]['id'] ?? '') === 'flight');
check('Koppeln: nur der Hash liegt in der Datenbank', (string) qval('SELECT token_hash FROM ha_links WHERE device_id = ?', [$deviceId]) === hash('sha256', $token));
[$st, $j] = http('POST', $base . '/api/ha/v1/pair/confirm', ['pairing' => $pairing, 'code' => $code]);
check('Koppeln: derselbe Code gilt nur einmal', $st === 404);
[, $f] = frame();
check('Koppeln: danach verschwindet der Code vom Panel', ($f['pages'][0]['mode'] ?? '') !== 'pairing');

$auth = ['Authorization: Bearer ' . $token];
$ha = static function (string $method, string $path, ?array $body = null) use ($base, $auth): array {
    return http($method, $base . '/api/ha/v1' . $path, $body, $auth);
};

// ---------- Stand und Panel ----------
[$st, $j] = $ha('GET', '/state');
$state = $j['state'] ?? [];
check('Stand: Geraet, Panel, Notiz, Timer, Wecker', $st === 200 && isset($state['device'], $state['panel'], $state['note'], $state['timers'], $state['alarm']) && $state['note']['max'] === 21);
check('Stand: Pixel-Editor ist nicht dabei (noch nicht verfuegbar)', !in_array('pixel', array_column($state['panel']['modes'] ?? [], 'id'), true));
check('Stand: online und mit Firmware', ($state['device']['online'] ?? false) === true && ($state['device']['fw'] ?? '') === '0.2.0');
[$st, $j] = $ha('POST', '/panel', ['mode' => 'clock', 'rotation' => []]);
check('Panel: Modus Uhr', $st === 200 && ($j['state']['panel']['mode'] ?? '') === 'clock' && ($j['state']['panel']['rotation'] ?? null) === []);
$ha('POST', '/panel', ['rotation' => ['flight', 'clock']]);
[$st, $j] = $ha('POST', '/panel', ['mode' => 'weather']);
[, $f] = frame();
check('Panel: Modus ohne Rotation beendet eine laufende Rotation, das Panel zeigt ihn', $st === 200 && ($j['state']['panel']['rotation'] ?? null) === [] && ($f['pages'][0]['mode'] ?? '') === 'weather');
$ha('POST', '/panel', ['mode' => 'clock']);
[$st, $j] = $ha('POST', '/panel', ['mode' => 'pixel']);
check('Panel: Modus, den es noch nicht gibt, 422', $st === 422 && ($j['code'] ?? '') === 'invalid_value');
[$st, $j] = $ha('POST', '/panel', ['on' => false]);
check('Panel: aus', $st === 200 && ($j['state']['panel']['on'] ?? true) === false && ($j['state']['panel']['showing'] ?? '') === 'off');
[, $f] = frame();
check('Panel aus: das Geraet bekommt Helligkeit 0', ($f['bright'] ?? -1) === 0);
[$st, $j] = $ha('POST', '/panel', ['on' => true, 'bright' => 50]);
check('Panel: an mit Helligkeit 50', $st === 200 && ($j['state']['panel']['on'] ?? false) === true && ($j['state']['panel']['bright'] ?? 0) === 50);
[$st] = $ha('POST', '/panel', ['bright' => 300]);
check('Panel: Helligkeit 300 abgelehnt', $st === 422);
[, $f] = frame();
check('Panel: Uhr kommt auf dem Geraet an', ($f['pages'][0]['mode'] ?? '') === 'clock' && ($f['bright'] ?? 0) > 0);
[, $j] = $ha('GET', '/state');
check('Stand: zeigt, was das Geraet zuletzt bekam', ($j['state']['panel']['showing'] ?? '') === 'clock');

// ---------- Notiz ----------
$noteRev = (int) qval('SELECT note_rev FROM devices WHERE id = ?', [$deviceId]);
[$st, $j] = $ha('POST', '/note', ['line1' => 'Waschmaschine', 'line2' => 'ist fertig, bitte ausraeumen']);
check('Notiz: steht zehn Minuten vorn', $st === 200 && ($j['state']['note']['front_left'] ?? 0) > 590);
check('Notiz: zweite Zeile auf 21 Zeichen gekuerzt', mb_strlen((string) ($j['state']['note']['line2'] ?? '')) === 21);
check('Notiz: note_rev steigt, das Panel blinkt', (int) qval('SELECT note_rev FROM devices WHERE id = ?', [$deviceId]) === $noteRev + 1);
[, $f] = frame();
check('Notiz: das Geraet zeigt sie', ($f['pages'][0]['mode'] ?? '') === 'notes');
[$st, $j] = $ha('POST', '/note/hide');
check('Notiz ausblenden', $st === 200 && ($j['state']['note']['front_left'] ?? -1) === 0);

// ---------- Timer ----------
[$st, $j] = $ha('POST', '/timer', ['seconds' => 120, 'label' => 'Pasta']);
$timerId = (string) ($j['timer']['id'] ?? '');
check('Timer: gestellt', $st === 200 && preg_match('/^[0-9a-f]{8}$/', $timerId) && ($j['timer']['label'] ?? '') === 'Pasta' && count($j['state']['timers'] ?? []) === 1);
[, $f] = frame();
$hasCount = (bool) array_filter($f['pages'][0]['ops'] ?? [], static fn($o) => $o['t'] === 'count' && $o['x'] === 128);
check('Timer: Ecke auf dem Panel und Ende fuer die Firmware', $hasCount && count($f['timers'] ?? []) === 1);
[$st, $j] = $ha('POST', '/timer', ['seconds' => 0]);
check('Timer: 0 Sekunden 422 invalid_value', $st === 422 && ($j['code'] ?? '') === 'invalid_value');
[$st, $j] = $ha('POST', '/timer', ['label' => 'ohne Zeit']);
check('Timer: ohne Sekunden 422', $st === 422);
for ($i = 0; $i < 4; $i++) {
    $ha('POST', '/timer', ['seconds' => 600 + $i]);
}
[$st, $j] = $ha('POST', '/timer', ['seconds' => 60]);
check('Timer: der sechste 422 too_many_timers', $st === 422 && ($j['code'] ?? '') === 'too_many_timers');
[$st, $j] = $ha('POST', '/timer/cancel', ['id' => $timerId]);
check('Timer: einen abbrechen', $st === 200 && count($j['state']['timers'] ?? []) === 4 && !in_array($timerId, array_column($j['state']['timers'], 'id'), true));
[$st, $j] = $ha('POST', '/timer/cancel', []);
check('Timer: alle abbrechen', $st === 200 && ($j['state']['timers'] ?? null) === []);

// ---------- Wecker ----------
[$st, $j] = $ha('POST', '/alarm', ['on' => true, 'time' => '06:30', 'days' => [1, 2, 3, 4, 5]]);
check('Wecker: gestellt, naechster Termin in der Zukunft', $st === 200 && ($j['state']['alarm']['on'] ?? false) === true && ($j['state']['alarm']['next'] ?? 0) > time());
[, $f] = frame();
check('Wecker: kommt als Stunde, Minute und Tage zur Firmware', ($f['alarm']['h'] ?? -1) === 6 && ($f['alarm']['m'] ?? -1) === 30 && ($f['alarm']['d'] ?? 0) === 31);
[$st, $j] = $ha('POST', '/alarm', ['time' => '25:00']);
check('Wecker: 25:00 422', $st === 422 && ($j['code'] ?? '') === 'invalid_value');
[$st, $j] = $ha('POST', '/alarm', ['on' => false]);
check('Wecker: aus, ohne naechsten Termin', $st === 200 && ($j['state']['alarm']['on'] ?? true) === false && array_key_exists('next', $j['state']['alarm'] ?? []) && $j['state']['alarm']['next'] === null);

// ---------- Klingeln ----------
[$st, $j] = $ha('POST', '/timer', ['seconds' => 1, 'label' => 'Eier']);
sleep(2);
[, $j] = $ha('GET', '/state');
check('Klingeln: abgelaufener Timer klingelt', ($j['state']['ringing'] ?? false) === true && ($j['state']['panel']['showing'] ?? '') === 'ring');
[, $f] = frame();
check('Klingeln: das Geraet zeigt es und bekommt ring', ($f['pages'][0]['mode'] ?? '') === 'ring' && isset($f['ring']['since']));
[$st] = http('POST', $base . '/api/v1/ring/stop', [], $dev);
[, $j] = $ha('GET', '/state');
check('Klingeln: Rad am Geraet stoppt es (ring/stop der Firmware)', $st === 200 && ($j['state']['ringing'] ?? true) === false && ($j['state']['timers'] ?? null) === []);
$ha('POST', '/timer', ['seconds' => 1]);
sleep(2);
[$st, $j] = $ha('POST', '/ring/stop');
check('Klingeln: Home Assistant stoppt es', $st === 200 && ($j['state']['ringing'] ?? true) === false);

// ---------- Firmware, Schluessel, Grenzen ----------
[$st, $j] = $ha('POST', '/firmware/update');
check('Firmware: ohne neuere Fassung 409 no_update', $st === 409 && ($j['code'] ?? '') === 'no_update');
[$st, $j] = http('GET', $base . '/api/ha/v1/state', null, ['Authorization: Bearer twha_' . str_repeat('A', 43)]);
check('Falscher Schluessel 401 invalid_token', $st === 401 && ($j['code'] ?? '') === 'invalid_token');
[$st] = http('GET', $base . '/api/ha/v1/state', null, ['Authorization: Bearer ' . $key]);
check('API-Schluessel des Kontos gilt hier nicht', $st === 401);
for ($i = 0; $i < 4; $i++) {
    http('POST', $base . '/api/ha/v1/pair', ['device' => $uid]);
}
[$st, $j, $hdr] = http('POST', $base . '/api/ha/v1/pair', ['device' => $uid]);
$retry = (bool) preg_grep('/^Retry-After:\s*\d+/i', $hdr);
check('Koppeln: der sechste Versuch in zehn Minuten 429 mit Retry-After', $st === 429 && ($j['code'] ?? '') === 'rate_limited' && $retry);
q('DELETE FROM ha_pairings WHERE device_id = ?', [$deviceId]);
[$st] = $ha('POST', '/unlink');
[$st2, $j] = $ha('GET', '/state');
check('Trennen: danach gilt der Schluessel nicht mehr', $st === 200 && $st2 === 401 && ($j['code'] ?? '') === 'invalid_token');
check('Trennen: nichts mehr in der Datenbank', (int) qval('SELECT COUNT(*) FROM ha_links WHERE device_id = ?', [$deviceId]) === 0);

device_delete($deviceId);
check('Geraet geloescht: Kopplungen mit weg', (int) qval('SELECT COUNT(*) FROM ha_pairings WHERE device_id = ?', [$deviceId]) === 0);
q('DELETE FROM rate_limits');

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
