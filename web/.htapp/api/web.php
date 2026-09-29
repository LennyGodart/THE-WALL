<?php
/*
 * JSON-Schnittstellen fuer den Browser. Jede braucht eine Sitzung, jede
 * veraendernde prueft CSRF und Herkunft. Rechte am Geraet prueft der Server,
 * nicht der Knopf: Gaeste mit Leserecht aendern nur Notizen.
 */

declare(strict_types=1);

/** Geraet laden, auf das der angemeldete Nutzer Zugriff hat. */
function api_device_or_fail(array $params, array $user, array $roles = ['owner', 'edit', 'view']): array
{
    $device = device_for_user((int) ($params['id'] ?? 0), (int) $user['id']);
    if ($device === null) {
        send_json_error(404, 'Device not found.', 'Gerät nicht gefunden.');
    }
    if (!in_array($device['role'], $roles, true)) {
        send_json_error(403, 'Only the owner can do that.', 'Das darf nur der Besitzer.');
    }
    return $device;
}

function api_limit(string $key, int $max, int $window): void
{
    if (!rl_allow($key, $max, $window)) {
        header('Retry-After: ' . rl_retry_after($key, $window));
        send_json_error(429, 'Too many requests. Wait a moment.', 'Zu viele Anfragen. Einen Moment warten.');
    }
}

function api_preview(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user);
    // Mit Leserecht aendern sich nur die Notizen. Sonst koennte ein Gast ueber die Vorschau
    // beliebige Standorte und Haltestellen rechnen lassen, jede Anfrage ginge an adsb.lol,
    // Open-Meteo und mobiliteit.lu (Befund S1). Den Modus darf er sich aussuchen, der
    // kostet nur den gespeicherten Stand.
    $viewer = $device['role'] === 'view';
    api_limit('preview:' . $user['id'], $viewer ? 20 : 120, 60);
    $in = req_json();
    $draftIn = is_array($in['settings'] ?? null) ? $in['settings'] : [];
    [$draft] = device_sanitize($draftIn, device_settings($device), $viewer ? 'view' : 'edit');
    if ($viewer && is_string($draftIn['mode'] ?? null) && ($m = mode_get($draftIn['mode'])) !== null && $m['available']) {
        $draft['mode'] = $draftIn['mode'];
    }
    if (($draftIn['mode'] ?? '') === 'pixel') {
        $draft['mode'] = 'pixel';
    }
    $draft['lang'] = ($in['lang'] ?? 'en') === 'de' ? 'de' : 'en';
    $owner = user_by_id((int) $device['owner_id']) ?? $user;
    send_json(['ok' => true, 'frame' => frame_build($device, $draft, $owner, true)]);
}

function api_apply(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user);
    api_limit('apply:' . $user['id'], 30, 60);
    $in = req_json();
    $input = is_array($in['settings'] ?? null) ? $in['settings'] : [];
    // Timer und Wecker aendern nur ihre eigenen Schnittstellen.
    unset($input['timers'], $input['alarm']);
    $role = (string) $device['role'];
    $lang = ($in['lang'] ?? 'en') === 'de' ? 'de' : 'en';
    $errors = [];
    $denied = false;
    // Auf dem Stand von gerade eben aufbauen: haette Home Assistant inzwischen etwas
    // geaendert, bliebe es erhalten (device_settings_update()).
    device_settings_update((int) $device['id'], static function (array $current) use ($input, $role, $lang, &$errors, &$denied): ?array {
        [$clean, $errors] = device_sanitize($input, $current, $role);
        if ($errors) {
            return null;
        }
        if ($role !== 'view') {
            $clean['lang'] = $lang;
        }
        [$clean, $noteChanged] = device_note_after_apply($clean, $current);
        if ($role === 'view' && !$noteChanged) {
            $denied = true;
            return null;
        }
        $clean['applied'] = true;
        return [$clean, $noteChanged];
    });
    if ($errors) {
        send_json_error(422, $errors[0][0], $errors[0][1]);
    }
    if ($denied) {
        send_json_error(403, 'On this device you may only send notes.', 'Auf diesem Gerät darfst du nur Notizen senden.');
    }
    $fresh = q1('SELECT * FROM devices WHERE id = ?', [(int) $device['id']]);
    $settings = device_settings($fresh);
    send_json(['ok' => true, 'settings' => $settings, 'noteRev' => (int) $fresh['note_rev'], 'noteLeft' => note_front_left($settings)]);
}

/**
 * "Notiz jetzt ausblenden": die Notiz verliert ihren Vorrang, das Panel zeigt wieder den
 * gewaehlten Modus. Jede Rolle darf das, wie das Senden einer Notiz.
 */
function api_note_hide(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user);
    api_limit('apply:' . $user['id'], 30, 60);
    device_note_hide((int) $device['id']);
    send_json(['ok' => true, 'noteLeft' => 0]);
}

/** Einen Timer stellen. Wie alles ausser Notizen nur mit Besitz oder Bedienrecht. */
function api_timer_start(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner', 'edit']);
    api_limit('timer:' . $user['id'], 30, 60);
    $in = req_json();
    $seconds = clamp_int($in['seconds'] ?? 0, 0, TIMER_MAX_SECONDS + 1, 0);
    $label = (string) ($in['label'] ?? '');
    $err = null;
    $timer = null;
    $now = time();
    $saved = device_settings_update((int) $device['id'], static function (array $cur) use ($seconds, $label, $now, &$err, &$timer): ?array {
        [$new, $timer, $err] = timer_add($cur, $seconds, $label, 'web', $now);
        return $new === null ? null : [$new, false];
    });
    if ($err !== null) {
        send_json_error(422, $err[1], $err[2]);
    }
    send_json(['ok' => true, 'timer' => $timer] + ring_status_public($saved ?? []));
}

/** Einen Timer abbrechen ({"id"}) oder alle ({}). */
function api_timer_cancel(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner', 'edit']);
    api_limit('timer:' . $user['id'], 30, 60);
    $in = req_json();
    $id = isset($in['id']) && is_string($in['id']) ? $in['id'] : null;
    $now = time();
    $saved = device_settings_update((int) $device['id'], static fn(array $cur): ?array => timers_list($cur, $now) ? [timer_cancel($cur, $id, $now), false] : null);
    send_json(['ok' => true] + ring_status_public($saved ?? []));
}

/** Klingeln stoppen: klingelnde Timer fallen weg, der Wecker ist fuer heute still. */
function api_ring_stop(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner', 'edit']);
    api_limit('timer:' . $user['id'], 30, 60);
    $now = time();
    $saved = device_settings_update((int) $device['id'], static fn(array $cur): ?array => ring_now($cur, $now)['since'] === null ? null : [ring_stop($cur, $now), false]);
    send_json(['ok' => true] + ring_status_public($saved ?? []));
}

/** Den Wecker stellen: on, time "HH:MM", days. Gilt sofort, ohne "Uebernehmen". */
function api_alarm_save(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner', 'edit']);
    api_limit('alarm:' . $user['id'], 60, 60);
    $in = req_json();
    $err = null;
    $now = time();
    $saved = device_settings_update((int) $device['id'], static function (array $cur) use ($in, $now, &$err): ?array {
        [$a, $err] = alarm_apply(alarm_get($cur), $in, $now);
        if ($err !== null) {
            return null;
        }
        $cur['alarm'] = $a;
        return [$cur, false];
    });
    if ($err !== null) {
        send_json_error(422, $err[0], $err[1]);
    }
    send_json(['ok' => true] + ring_status_public($saved ?? []));
}

/** Eine gekoppelte Home-Assistant-Instanz trennen. Nur der Besitzer. */
function api_ha_unlink(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    q('DELETE FROM ha_links WHERE id = ? AND device_id = ?', [(int) (req_json()['link'] ?? 0), (int) $device['id']]);
    send_json(['ok' => true, 'links' => ha_links_public((int) $device['id'])]);
}


/** Name und Zeitzone aus den Einstellungen, nur fuer den Besitzer. */
function api_device_settings(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    $in = req_json();
    $set = [];
    if (array_key_exists('name', $in)) {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $in['name']) ?? '');
        if ($name === '' || mb_strlen($name) > 40) {
            send_json_error(422, 'A name needs 1 to 40 characters.', 'Ein Name braucht 1 bis 40 Zeichen.');
        }
        $set['name'] = $name;
    }
    $settings = device_settings($device);
    if (array_key_exists('tz', $in)) {
        if (!tz_valid((string) $in['tz'])) {
            send_json_error(422, 'Unknown time zone.', 'Unbekannte Zeitzone.');
        }
        $settings['tz'] = (string) $in['tz'];
        $set['settings'] = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($set) {
        $set['updated_at'] = time();
        db_update('devices', $set, 'id = ?', [(int) $device['id']]);
    }
    send_json(['ok' => true]);
}

function api_device_update(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    $f = firmware_available($device);
    if ($f === null) {
        send_json_error(409, 'There is no newer firmware for this device.', 'Für dieses Gerät gibt es keine neuere Firmware.');
    }
    db_update('devices', ['update_requested_at' => time()], 'id = ?', [(int) $device['id']]);
    send_json(['ok' => true, 'version' => $f['version']]);
}

function api_device_remove(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    if (!device_is_test($device)) {
        device_mark_removed((int) $device['owner_id'], (string) $device['uid']);
    }
    device_delete((int) $device['id']);
    send_json(['ok' => true]);
}

function api_share_invite(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    api_limit('invite:' . $user['id'], 10, 3600);
    $email = strtolower(trim((string) (req_json()['email'] ?? '')));
    if (!valid_email($email)) {
        send_json_error(422, 'Please enter a complete email address.', 'Bitte eine vollständige E-Mail-Adresse.');
    }
    if ($email === $user['email']) {
        send_json_error(422, 'That is your own address.', 'Das ist deine eigene Adresse.');
    }
    $existing = user_by_email($email);
    if ($existing && q1('SELECT 1 FROM shares WHERE device_id = ? AND user_id = ?', [(int) $device['id'], (int) $existing['id']])) {
        send_json_error(409, 'That person already has access.', 'Diese Person hat schon Zugang.');
    }
    q("UPDATE tokens SET used_at = ? WHERE kind = 'share' AND device_id = ? AND email = ? AND used_at IS NULL", [time(), (int) $device['id'], $email]);
    $raw = token_create('share', ['device_id' => (int) $device['id'], 'email' => $email, 'rights' => 'view', 'created_by' => (int) $user['id']]);
    $sent = mail_invite($email, $user, $device, $raw);
    $out = ['ok' => true, 'mailed' => $sent['ok']];
    if (!$sent['ok']) {
        $out['link'] = app_url() . '/invite?t=' . $raw;
    }
    send_json($out);
}

function api_share_rights(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    $in = req_json();
    $rights = ($in['rights'] ?? '') === 'edit' ? 'edit' : 'view';
    $n = db_update('shares', ['rights' => $rights], 'device_id = ? AND user_id = ?', [(int) $device['id'], (int) ($in['user_id'] ?? 0)]);
    if ($n === 0 && !q1('SELECT 1 FROM shares WHERE device_id = ? AND user_id = ?', [(int) $device['id'], (int) ($in['user_id'] ?? 0)])) {
        send_json_error(404, 'That person has no access.', 'Diese Person hat keinen Zugang.');
    }
    send_json(['ok' => true, 'rights' => $rights]);
}

function api_share_remove(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $device = api_device_or_fail($params, $user, ['owner']);
    $in = req_json();
    if (isset($in['invite_id'])) {
        q("UPDATE tokens SET used_at = ? WHERE id = ? AND kind = 'share' AND device_id = ?", [time(), (int) $in['invite_id'], (int) $device['id']]);
    } else {
        q('DELETE FROM shares WHERE device_id = ? AND user_id = ?', [(int) $device['id'], (int) ($in['user_id'] ?? 0)]);
    }
    send_json(['ok' => true]);
}

function api_device_status(array $params = []): void
{
    $user = require_user();
    $device = api_device_or_fail($params, $user);
    $settings = device_settings($device);
    send_json([
        'ok' => true,
        'online' => device_online($device),
        'items' => device_telemetry($device),
        // Hat inzwischen ein Gast eine Notiz geschickt, steht sie jetzt vorn.
        'noteLeft' => note_front_left($settings),
    ] + ring_status_public($settings));
}

/** Logo fuer die Vorschau, nur fuer angemeldete Nutzer (eingetragene Marken). */
function api_logo(array $params = []): void
{
    require_user();
    $rgb = logo_rgb((string) ($params['code'] ?? ''));
    if ($rgb === null) {
        send_json_error(404, 'No logo.', 'Kein Logo.');
    }
    logo_send($rgb);
}

/**
 * Flugzeuge fuer die Karte der Geraeteseite. Derselbe Zwischenspeicher wie fuer das
 * Panel (8 Sekunden je Standort und Umkreis): steht die Nadel am gespeicherten
 * Standort, kostet die Karte keine eigene Abfrage bei adsb.lol. Strecken nur aus
 * dem eigenen Speicher, die Karte fragt adsbdb und VRS nie, und wie auf dem Panel nur,
 * wenn sie zur Position passen (flight_route_pick).
 */
function api_map_aircraft(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('mapac:' . $user['id'], 90, 300);
    $in = req_json();
    $lat = $in['lat'] ?? null;
    $lon = $in['lon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
        send_json_error(422, 'No valid position.', 'Keine gültige Position.');
    }
    $nm = (int) (round(clamp_int($in['nm'] ?? 40, 5, 150, 40) / 5) * 5);
    $raw = adsb_nearby((float) $lat, (float) $lon, $nm);
    if ($raw === null) {
        send_json_error(424, 'adsb.lol is not answering right now.', 'adsb.lol antwortet gerade nicht.');
    }
    $air = array_slice(array_values(array_filter($raw, static fn(array $ac): bool => !$ac['ground'])), 0, ADSB_MAP_MAX);
    $routes = adsbdb_cached_routes(array_column($air, 'callsign'));
    $vrs = vrs_cached_routes(array_column($air, 'callsign'));
    $list = [];
    foreach ($air as $ac) {
        $cs = $ac['callsign'];
        $route = $cs !== '' ? flight_route_pick($ac, $routes[$cs] ?? null, $vrs[$cs] ?? null) : null;
        $list[] = [
            'hex' => $ac['hex'],
            'callsign' => $cs,
            'reg' => $ac['reg'],
            'type' => $ac['type'] !== '' ? aircraft_type_name($ac['type'], 20) : '',
            'airline' => FLIGHT_NAMES[flight_airline_icao($route, $cs, $ac)] ?? '',
            'route' => flight_route_text($route),
            'alt' => $ac['alt'],
            'gs' => $ac['gs'] === null ? null : (int) round($ac['gs']),
            'track' => $ac['track'],
            'lat' => round($ac['lat'], 4),
            'lon' => round($ac['lon'], 4),
            'dst' => $ac['dst'],
            'military' => $ac['military'],
        ];
    }
    send_json(['ok' => true, 'nm' => $nm, 'count' => count($list), 'aircraft' => $list]);
}

/**
 * Haltestellen im Umkreis von einem Kilometer um einen Punkt, fuer die Einstellungen
 * des Nahverkehrsmodus. Auf gut hundert Meter gerundet, damit derselbe Standort
 * denselben Zwischenspeicher trifft: ein Tag, also eine Abfrage bei mobiliteit.lu.
 */
function api_transit_stops(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('tstops:' . $user['id'], 30, 600);
    $in = req_json();
    $lat = $in['lat'] ?? null;
    $lon = $in['lon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
        send_json_error(422, 'No valid position.', 'Keine gültige Position.');
    }
    $r = transit_nearby(round((float) $lat, 3), round((float) $lon, 3), 1000, 15);
    if (!$r['ok']) {
        send_json_error($r['status'] === 403 ? 424 : $r['status'], $r['error_en'], $r['error_de'], ['code' => $r['code']]);
    }
    $stops = [];
    foreach ($r['stops'] as $s) {
        $stops[] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'short' => transit_short_name($s),
            'dist_m' => $s['dist_m'],
            'modes' => array_values(array_filter(TRANSIT_MODES, static fn(string $m): bool => in_array($m, $s['modes'], true))),
            'lines' => transit_stop_codes($s),
        ];
    }
    send_json(['ok' => true, 'stops' => $stops, 'source' => TRANSIT_SOURCE]);
}

/**
 * Alle Haltestellen fuer die Haltestellenkarte (services/stops.php). Fehlt der Stand
 * oder ist er aelter als 30 Tage, bringt jeder Aufruf das Raster ein Stueck weiter,
 * aber nur fuer Konten, die ein Geraet bedienen duerfen: lesen darf jeder, Abfragen
 * bei mobiliteit.lu stoesst kein Gast mit Leserecht an.
 */
function api_transit_all(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('tall:' . $user['id'], 240, 600);
    $work = false;
    foreach (devices_for_user((int) $user['id']) as $d) {
        if (in_array($d['role'], ['owner', 'edit'], true)) {
            $work = true;
            break;
        }
    }
    send_json(['ok' => true] + stops_for_map($work) + ['source' => TRANSIT_SOURCE]);
}

/** Eine Haltestelle aus der Karte, mit Kurzname und Linien, wie api_transit_stops() sie liefert. */
function api_transit_stop(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('tstop:' . $user['id'], 120, 600);
    $stop = stops_get((string) (req_json()['id'] ?? ''));
    if ($stop === null) {
        send_json_error(404, 'That stop is not on the map.', 'Diese Haltestelle ist nicht auf der Karte.');
    }
    send_json(['ok' => true, 'stop' => $stop]);
}

function api_geo(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('geo:' . $user['id'], 30, 3600);
    $in = req_json();
    $res = geocode_search((string) ($in['q'] ?? ''), ($in['lang'] ?? 'en') === 'de' ? 'de' : 'en');
    send_json(['ok' => true] + $res);
}

/** Geraete des Kontos. Die Seite "Noch kein Geraet" fragt alle vier Sekunden, bis das erste auftaucht. */
function api_devices(array $params = []): void
{
    $user = require_user();
    $out = [];
    foreach (devices_for_user((int) $user['id']) as $d) {
        $out[] = ['id' => (int) $d['id'], 'role' => $d['role']];
    }
    send_json(['ok' => true, 'devices' => $out]);
}

/** Die Einfuehrung ins Geraet lief oder wurde uebersprungen: sie oeffnet nicht mehr von selbst. */
function api_tour_seen(array $params = []): void
{
    $user = require_user();
    csrf_check();
    if (($user['tour_seen_at'] ?? null) === null) {
        q('UPDATE users SET tour_seen_at = ? WHERE id = ?', [time(), (int) $user['id']]);
    }
    send_json(['ok' => true]);
}

function api_rotate_key(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('rotate:' . $user['id'], 5, 3600);
    send_json(['ok' => true, 'key' => user_rotate_key((int) $user['id'])]);
}

function api_change_password(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $in = req_json();
    $key = 'pwchange:' . $user['id'];
    if (rl_blocked($key, 5, 900)) {
        send_json_error(429, 'Too many attempts. Try again later.', 'Zu viele Versuche. Später noch einmal.');
    }
    if (!password_check((string) ($in['current'] ?? ''), (string) $user['pass_hash'])) {
        rl_allow($key, 5, 900);
        send_json_error(422, 'The current password is not right.', 'Das bisherige Passwort stimmt nicht.', ['field' => 'current']);
    }
    $new = (string) ($in['password'] ?? '');
    $problem = password_problem($new);
    if ($problem !== null) {
        send_json_error(422, $problem[0], $problem[1], ['field' => 'password']);
    }
    user_set_password((int) $user['id'], $new);
    $session = session_row();
    sessions_revoke((int) $user['id'], $session ? (string) $session['id'] : null);
    rl_reset($key);
    send_json(['ok' => true]);
}

function api_export_mail(array $params = []): void
{
    $user = require_user();
    csrf_check();
    api_limit('exportmail:' . $user['id'], 3, 3600);
    $json = json_encode(account_export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $name = 'thewall-' . $user['username'] . '-' . gmdate('Y-m-d') . '.json';
    $sent = mail_export($user, $name, (string) $json);
    if (!$sent['ok']) {
        send_json_error(424, 'The email could not be sent. Use the download instead.', 'Die Mail konnte nicht verschickt werden. Nimm stattdessen den Download.');
    }
    send_json(['ok' => true]);
}

/**
 * Spotify auf der Geraeteseite: ob das Konto des Besitzers verbunden ist und was laeuft.
 * Die Verbindung gehoert dem Besitzer, Gaeste sehen nur den Stand.
 */
function api_spotify_status(array $params = []): void
{
    $user = require_user();
    $device = api_device_or_fail($params, $user);
    api_limit('spotifystatus:' . $user['id'], 60, 60);
    $ownerId = (int) $device['owner_id'];
    $owner = $device['role'] === 'owner' ? $user : user_by_id($ownerId);
    $link = spotify_link($ownerId);
    $out = [
        'configured' => spotify_configured(),
        'mine' => $device['role'] === 'owner',
        'owner' => (string) ($owner['username'] ?? ''),
        'linked' => $link !== null,
        'name' => $link ? (string) $link['display_name'] : '',
        'error' => $link ? ($link['error'] ?? null) : null,
        'full' => $link === null && spotify_users_count() >= SPOTIFY_USERS_MAX,
        'now' => null,
    ];
    if ($link !== null && $out['configured']) {
        $v = spotify_now($ownerId);
        if (is_array($v) && isset($v['item'])) {
            $out['now'] = [
                'title' => (string) $v['item']['title'],
                'artists' => implode(', ', $v['item']['artists']),
                'url' => str_starts_with((string) $v['item']['url'], 'https://open.spotify.com/') ? (string) $v['item']['url'] : '',
                'playing' => $v['state'] === 'play',
            ];
        } elseif (is_array($v) && in_array($v['state'], ['not_allowed', 'revoked'], true)) {
            $out['error'] = $v['state'];
        }
    }
    send_json(['ok' => true, 'spotify' => $out]);
}

/** Die eigene Spotify-Verbindung trennen. Die Token sind danach weg. */
function api_spotify_disconnect(array $params = []): void
{
    $user = require_user();
    csrf_check();
    spotify_unlink((int) $user['id']);
    send_json(['ok' => true]);
}

function api_delete_account(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $in = req_json();
    $key = 'delete:' . $user['id'];
    if (rl_blocked($key, 5, 900)) {
        send_json_error(429, 'Too many attempts. Try again later.', 'Zu viele Versuche. Später noch einmal.');
    }
    if (!password_check((string) ($in['password'] ?? ''), (string) $user['pass_hash'])) {
        rl_allow($key, 5, 900);
        send_json_error(422, 'The password is not right.', 'Das Passwort stimmt nicht.');
    }
    if (is_admin($user) && (int) qval("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
        send_json_error(409, 'You run this server. The only admin account cannot be deleted here.', 'Du betreibst diesen Server. Das einzige Admin-Konto lässt sich hier nicht löschen.');
    }
    user_delete((int) $user['id']);
    set_cookie(SESSION_COOKIE, '', -1);
    send_json(['ok' => true]);
}
