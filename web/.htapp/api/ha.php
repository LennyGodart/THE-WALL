<?php
/*
 * Schnittstelle fuer Home Assistant (Integration in homeassistant/, Domain thewall). Home
 * Assistant spricht nur mit dem Server, nie mit dem Geraet. Gekoppelt wird je Geraet mit
 * einem Code, der fuenf Minuten auf dem Panel steht (device/ha.php): wer ihn eintippt, steht
 * davor. Danach gilt ein eigener Schluessel je Home Assistant (twha_...), nicht der
 * API-Schluessel des Kontos. Der liegt im Flash des Geraets, und wer das Geraet in der Hand
 * hat, koennte ihn auslesen und damit sonst auch Einstellungen aendern.
 *
 *   POST /api/ha/v1/pair              {"device": uid}                 pairing, expires_in, length
 *   POST /api/ha/v1/pair/confirm      {"pairing", "code", "name"}     token, state
 *   GET  /api/ha/v1/state                                             state
 *   POST /api/ha/v1/panel             {"on", "bright", "mode", "rotation"}
 *   POST /api/ha/v1/note              {"line1", "line2"}
 *   POST /api/ha/v1/note/hide
 *   POST /api/ha/v1/timer             {"seconds", "label"}            timer, state
 *   POST /api/ha/v1/timer/cancel      {"id"} oder {} fuer alle
 *   POST /api/ha/v1/alarm             {"on", "time", "days"}
 *   POST /api/ha/v1/ring/stop
 *   POST /api/ha/v1/firmware/update                                   version, state
 *   POST /api/ha/v1/unlink
 *
 * Alles ausser den beiden Kopplungsaufrufen mit "Authorization: Bearer twha_...". Fehler wie
 * ueberall {"ok": false, "error": {en, de}}, dazu "code" fuer Home Assistant: bad_request,
 * invalid_token (401), unknown_device, unknown_pairing (404), device_offline, no_update
 * (409), pairing_expired (410), invalid_code mit attempts_left, invalid_value,
 * too_many_timers (422), rate_limited (429 mit Retry-After).
 */

declare(strict_types=1);

/* Ein Geraet, das so lange nicht gefragt hat, kann keinen Code zeigen. Testgeraete fragen
   nie, dort steht der Code in der Statuszeile der Geraeteseite. */
const HA_OFFLINE_SECONDS = 120;

function ha_fail(int $status, string $code, string $en, string $de, array $extra = []): never
{
    send_json_error($status, $en, $de, ['code' => $code] + $extra);
}

function ha_limit(string $key, int $max, int $window): void
{
    if (!rl_allow($key, $max, $window)) {
        header('Retry-After: ' . rl_retry_after($key, $window));
        ha_fail(429, 'rate_limited', 'Too many requests. Wait a moment.', 'Zu viele Anfragen. Einen Moment warten.');
    }
}

/**
 * Schluessel pruefen. Liefert [Verbindung, Geraet mit Besitzer]. Falsche Schluessel zaehlen
 * je Adresse, nach 30 in einer Stunde antwortet der Server sofort mit 401.
 */
function ha_auth(): array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    $token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : '';
    $failKey = 'hafail:' . ip_key();
    if ($token === '' || !str_starts_with($token, HA_TOKEN_PREFIX) || rl_blocked($failKey, 30, 3600)) {
        ha_fail(401, 'invalid_token', 'Missing or invalid token.', 'Schlüssel fehlt oder ist ungültig.');
    }
    $link = q1('SELECT * FROM ha_links WHERE token_hash = ?', [ha_token_hash($token)]);
    $device = $link ? q1('SELECT * FROM devices WHERE id = ?', [(int) $link['device_id']]) : null;
    if (!$link || !$device) {
        rl_allow($failKey, 30, 3600);
        ha_fail(401, 'invalid_token', 'Missing or invalid token.', 'Schlüssel fehlt oder ist ungültig.');
    }
    ha_limit('ha:' . $link['id'], 120, 60);
    // Zuletzt benutzt, fuer die Liste in den Einstellungen: hoechstens einmal pro Minute schreiben.
    if ((int) ($link['last_used_at'] ?? 0) < time() - 60) {
        q('UPDATE ha_links SET last_used_at = ? WHERE id = ?', [time(), (int) $link['id']]);
    }
    return [$link, $device];
}

/** Frisch aus der Datenbank, nach einer Aenderung. */
function ha_device_fresh(int $id): array
{
    return q1('SELECT * FROM devices WHERE id = ?', [$id]) ?? [];
}

/** Was das Panel gerade zeigt: aus dem letzten Abruf des Geraets, sonst geschaetzt. */
function ha_showing(array $device, array $settings, int $now): string
{
    if (ha_pairing_pending((int) $device['id'], $now) !== null) {
        return 'pairing';
    }
    if (ring_now($settings, $now)['since'] !== null) {
        return 'ring';
    }
    if (!empty($settings['off'])) {
        return 'off';
    }
    if (empty($settings['applied'])) {
        return 'hello';
    }
    $seen = cache_get('showing:' . (int) $device['id']);
    if (is_string($seen) && $seen !== '' && mode_get($seen) !== null) {
        return $seen;
    }
    if (note_front_left($settings, $now) > 0) {
        return 'notes';
    }
    return (string) $settings['mode'];
}

/** Der Stand eines Geraets fuer Home Assistant. Liest nur, fragt keinen Dienst draussen. */
function ha_state(array $device): array
{
    $now = time();
    $s = device_settings($device);
    $latest = firmware_available($device);
    $modes = [];
    foreach (modes() as $id => $m) {
        if ($m['available']) {
            $modes[] = ['id' => $id, 'en' => $m['label']['en'], 'de' => $m['label']['de'], 'rotatable' => (bool) $m['rotatable']];
        }
    }
    $flight = cache_get('flightnow:' . (int) $device['id']);
    $ring = ring_now($s, $now);
    return [
        'device' => [
            'uid' => (string) $device['uid'],
            'name' => (string) $device['name'],
            'model' => 'THE WALL 128x64',
            'fw' => $device['fw'] !== null && $device['fw'] !== '' ? (string) $device['fw'] : null,
            'fw_latest' => $latest !== null ? (string) $latest['version'] : null,
            'fw_requested' => $latest !== null && !empty($device['update_requested_at']) && (int) $device['update_requested_at'] > $now - 86400,
            'online' => device_online($device),
            'last_seen' => $device['last_seen_at'] !== null ? (int) $device['last_seen_at'] : null,
            'rssi' => $device['rssi'] !== null ? (int) $device['rssi'] : null,
            'test' => device_is_test($device),
            'url' => app_url() . '/device/' . (int) $device['id'],
        ],
        'panel' => [
            'on' => empty($s['off']),
            'bright' => (int) $s['bright'],
            'mode' => (string) $s['mode'],
            'rotation' => array_values($s['rotation']),
            'showing' => ha_showing($device, $s, $now),
            'modes' => $modes,
        ],
        'note' => [
            'line1' => (string) $s['notes']['line1'],
            'line2' => (string) $s['notes']['line2'],
            'front_left' => note_front_left($s, $now),
            'max' => PANEL_LINE_MAX,
        ],
        'timers' => timers_public($s, $now),
        'alarm' => alarm_public($s, $now),
        'ringing' => $ring['since'] !== null,
        'flight' => is_array($flight) && (($s['mode'] ?? '') === 'flight' || in_array('flight', $s['rotation'], true)) ? $flight : null,
        'time' => $now,
        'rev' => device_rev_value($device, $s),
    ];
}

function ha_send_state(int $deviceId, array $extra = []): never
{
    send_json(['ok' => true] + $extra + ['state' => ha_state(ha_device_fresh($deviceId))]);
}

/** Einstellungen aendern wie "Uebernehmen" mit Bedienrecht; jede Aenderung weckt das Geraet aus der Begruessung. */
function ha_update(int $deviceId, callable $fn): void
{
    $err = null;
    device_settings_update($deviceId, static function (array $cur) use ($fn, &$err): ?array {
        $res = $fn($cur);
        if (is_string($res[0] ?? null)) {
            $err = $res;
            return null;
        }
        if ($res === null) {
            return null;
        }
        [$new, $noteChanged] = $res;
        $new['applied'] = true;
        return [$new, $noteChanged];
    });
    if ($err !== null) {
        ha_fail(422, $err[0], $err[1], $err[2]);
    }
}

function ha_api_pair(array $params = []): void
{
    ha_limit('hapair-ip:' . ip_key(), 20, 3600);
    $uid = strtolower(trim((string) (req_json()['device'] ?? '')));
    if (!device_uid_valid($uid)) {
        ha_fail(400, 'bad_request', 'That is not a device ID. It looks like wall-7f3a91.', 'Das ist keine Geräte-ID. Sie sieht aus wie wall-7f3a91.');
    }
    ha_limit('hapair:' . $uid, 5, 600);
    // Dieselbe Kennung kann in zwei Konten stehen, wenn ein Geraet umgezogen ist: das, das
    // zuletzt gefragt hat, haengt an der Wand.
    $device = q1('SELECT * FROM devices WHERE uid = ? ORDER BY COALESCE(last_seen_at, 0) DESC, id DESC LIMIT 1', [$uid]);
    if (!$device) {
        ha_fail(404, 'unknown_device', 'No device with this ID. The ID is on the website under Settings, in the Home Assistant section.', 'Kein Gerät mit dieser ID. Die ID steht auf der Webseite unter Einstellungen im Abschnitt Home Assistant.');
    }
    $now = time();
    if (!device_is_test($device) && (int) ($device['last_seen_at'] ?? 0) < $now - HA_OFFLINE_SECONDS) {
        ha_fail(409, 'device_offline', 'The device has not been online for two minutes, so it cannot show a code.', 'Das Gerät war zwei Minuten nicht online und kann deshalb keinen Code zeigen.');
    }
    q('DELETE FROM ha_pairings WHERE device_id = ? OR expires_at < ?', [(int) $device['id'], $now - 3600]);
    $id = random_hex(16);
    db_insert('ha_pairings', [
        'id' => $id,
        'device_id' => (int) $device['id'],
        'code' => ha_code_new(),
        'attempts' => 0,
        'created_at' => $now,
        'expires_at' => $now + HA_PAIR_SECONDS,
    ]);
    device_bump_rev((int) $device['id']);
    send_json(['ok' => true, 'pairing' => $id, 'expires_in' => HA_PAIR_SECONDS, 'length' => HA_CODE_LENGTH]);
}

function ha_api_pair_confirm(array $params = []): void
{
    ha_limit('haconfirm-ip:' . ip_key(), 30, 600);
    $in = req_json();
    $id = strtolower((string) ($in['pairing'] ?? ''));
    if (!preg_match('/^[0-9a-f]{32}$/', $id)) {
        ha_fail(404, 'unknown_pairing', 'This pairing does not exist. Start again.', 'Diese Kopplung gibt es nicht. Bitte neu beginnen.');
    }
    $p = q1('SELECT * FROM ha_pairings WHERE id = ?', [$id]);
    if (!$p || (int) $p['attempts'] >= HA_PAIR_ATTEMPTS) {
        ha_fail(404, 'unknown_pairing', 'This pairing does not exist. Start again.', 'Diese Kopplung gibt es nicht. Bitte neu beginnen.');
    }
    $deviceId = (int) $p['device_id'];
    if ((int) $p['expires_at'] <= time()) {
        q('DELETE FROM ha_pairings WHERE id = ?', [$id]);
        device_bump_rev($deviceId);
        ha_fail(410, 'pairing_expired', 'The code has expired. Start again for a new one.', 'Der Code ist abgelaufen. Neu beginnen für einen neuen.');
    }
    if (!hash_equals((string) $p['code'], ha_code_normalize((string) ($in['code'] ?? '')))) {
        $attempts = (int) $p['attempts'] + 1;
        q('UPDATE ha_pairings SET attempts = ? WHERE id = ?', [$attempts, $id]);
        if ($attempts >= HA_PAIR_ATTEMPTS) {
            device_bump_rev($deviceId);
        }
        ha_fail(422, 'invalid_code', 'That code is not right. It is on the panel.', 'Der Code stimmt nicht. Er steht auf dem Panel.', ['attempts_left' => max(0, HA_PAIR_ATTEMPTS - $attempts)]);
    }
    q('DELETE FROM ha_pairings WHERE id = ?', [$id]);
    $name = str_cut(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($in['name'] ?? '')) ?? ''), 64);
    $token = ha_token_new();
    db_insert('ha_links', [
        'device_id' => $deviceId,
        'token_hash' => ha_token_hash($token),
        'name' => $name !== '' ? $name : 'Home Assistant',
        'created_at' => time(),
        'last_used_at' => time(),
    ]);
    // Hoechstens fuenf je Geraet: die aeltesten fallen weg, etwa nach mehrmaligem Neueinrichten.
    $ids = qall('SELECT id FROM ha_links WHERE device_id = ? ORDER BY created_at DESC, id DESC', [$deviceId]);
    foreach (array_slice($ids, HA_LINKS_MAX) as $old) {
        q('DELETE FROM ha_links WHERE id = ?', [(int) $old['id']]);
    }
    device_bump_rev($deviceId);
    ha_send_state($deviceId, ['token' => $token]);
}

function ha_api_state(array $params = []): void
{
    [, $device] = ha_auth();
    send_json(['ok' => true, 'state' => ha_state($device)]);
}

function ha_api_panel(array $params = []): void
{
    [, $device] = ha_auth();
    $in = req_json();
    $input = [];
    if (array_key_exists('on', $in)) {
        $input['off'] = !$in['on'];
    }
    if (array_key_exists('bright', $in)) {
        if (!is_numeric($in['bright']) || (int) $in['bright'] < 0 || (int) $in['bright'] > 255) {
            ha_fail(422, 'invalid_value', 'Brightness goes from 0 to 255.', 'Die Helligkeit geht von 0 bis 255.');
        }
        $input['bright'] = (int) $in['bright'];
    }
    if (array_key_exists('mode', $in)) {
        $input['mode'] = (string) $in['mode'];
    }
    if (array_key_exists('rotation', $in)) {
        if (!is_array($in['rotation'])) {
            ha_fail(422, 'invalid_value', 'The rotation is a list of modes.', 'Die Rotation ist eine Liste von Modi.');
        }
        $input['rotation'] = array_values(array_map('strval', $in['rotation']));
    }
    // Wer in Home Assistant einen Modus waehlt, will ihn sehen. Eine Rotation mit zwei oder mehr
    // Modi ginge sonst vor, und eine Automation "um sieben Uhr das Wetter" taete nichts (gefunden
    // im Durchlauf mit echtem Home Assistant am 26. September 2026). Deshalb endet sie dann; die
    // Schalter "Rotation" schalten sie wieder an. Die Webseite zeigt Modus und Rotation
    // nebeneinander und behaelt ihre Regel.
    $modeOnly = array_key_exists('mode', $input) && !array_key_exists('rotation', $input);
    ha_update((int) $device['id'], static function (array $cur) use ($input, $modeOnly): ?array {
        if ($modeOnly && count($cur['rotation'] ?? []) >= 2) {
            $input['rotation'] = [];
        }
        [$clean, $errors] = device_sanitize($input, $cur, 'edit');
        if ($errors) {
            return ['invalid_value', $errors[0][0], $errors[0][1]];
        }
        // Ein anderer Modus oder eine andere Rotation beendet den Vorrang einer Notiz, wie auf der Webseite.
        return device_note_after_apply($clean, $cur);
    });
    ha_send_state((int) $device['id']);
}

function ha_api_note(array $params = []): void
{
    [, $device] = ha_auth();
    $in = req_json();
    $notes = [];
    foreach (['line1', 'line2'] as $line) {
        if (array_key_exists($line, $in)) {
            $notes[$line] = (string) $in[$line];
        }
    }
    ha_update((int) $device['id'], static function (array $cur) use ($notes): ?array {
        [$clean] = device_sanitize(['notes' => $notes], $cur, 'edit');
        return device_note_after_apply($clean, $cur);
    });
    ha_send_state((int) $device['id']);
}

function ha_api_note_hide(array $params = []): void
{
    [, $device] = ha_auth();
    device_note_hide((int) $device['id']);
    ha_send_state((int) $device['id']);
}

function ha_api_timer(array $params = []): void
{
    [$link, $device] = ha_auth();
    $in = req_json();
    if (!isset($in['seconds']) || !is_numeric($in['seconds'])) {
        ha_fail(422, 'invalid_value', 'A timer needs its length in seconds.', 'Ein Timer braucht seine Länge in Sekunden.');
    }
    $seconds = (int) $in['seconds'];
    $label = (string) ($in['label'] ?? '');
    $timer = null;
    $now = time();
    ha_update((int) $device['id'], static function (array $cur) use ($seconds, $label, $now, &$timer): ?array {
        [$new, $timer, $err] = timer_add($cur, $seconds, $label, 'ha', $now);
        return $err !== null ? $err : [$new, false];
    });
    $t = $timer;
    ha_send_state((int) $device['id'], ['timer' => ['id' => $t['id'], 'label' => $t['label'], 'total' => $t['total'], 'end' => $t['end'], 'ringing' => false]]);
}

function ha_api_timer_cancel(array $params = []): void
{
    [, $device] = ha_auth();
    $in = req_json();
    $id = isset($in['id']) && is_string($in['id']) && $in['id'] !== '' ? $in['id'] : null;
    $now = time();
    device_settings_update((int) $device['id'], static fn(array $cur): ?array => timers_list($cur, $now) ? [timer_cancel($cur, $id, $now), false] : null);
    ha_send_state((int) $device['id']);
}

function ha_api_alarm(array $params = []): void
{
    [, $device] = ha_auth();
    $in = req_json();
    $now = time();
    ha_update((int) $device['id'], static function (array $cur) use ($in, $now): ?array {
        [$a, $err] = alarm_apply(alarm_get($cur), $in, $now);
        if ($err !== null) {
            return ['invalid_value', $err[0], $err[1]];
        }
        $cur['alarm'] = $a;
        return [$cur, false];
    });
    ha_send_state((int) $device['id']);
}

function ha_api_ring_stop(array $params = []): void
{
    [, $device] = ha_auth();
    $now = time();
    device_settings_update((int) $device['id'], static fn(array $cur): ?array => ring_now($cur, $now)['since'] === null ? null : [ring_stop($cur, $now), false]);
    ha_send_state((int) $device['id']);
}

/** Wie "Jetzt aktualisieren" auf der Webseite: das Geraet holt sich die Fassung beim naechsten Abruf. */
function ha_api_firmware_update(array $params = []): void
{
    [, $device] = ha_auth();
    $f = firmware_available($device);
    if ($f === null) {
        ha_fail(409, 'no_update', 'There is no newer firmware for this device.', 'Für dieses Gerät gibt es keine neuere Firmware.');
    }
    db_update('devices', ['update_requested_at' => time()], 'id = ?', [(int) $device['id']]);
    ha_send_state((int) $device['id'], ['version' => (string) $f['version']]);
}

/** Home Assistant entfernt die Integration: der Schluessel gilt danach nicht mehr. */
function ha_api_unlink(array $params = []): void
{
    [$link] = ha_auth();
    q('DELETE FROM ha_links WHERE id = ?', [(int) $link['id']]);
    send_json(['ok' => true]);
}
