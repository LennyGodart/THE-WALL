<?php
/*
 * Schnittstelle fuer das Geraet. Das Geraet fragt alle 10 Sekunden, der Server
 * schiebt nie etwas. Anmeldung mit dem API-Schluessel des Kontos:
 *
 *   GET /api/v1/frame
 *   Authorization: Bearer tw_live_0123456789abcdef
 *   X-Wall-Id: wall-7f3a91          Geraete-ID, aus der MAC
 *   X-Wall-Fw: 0.4.2                Firmware
 *   X-Wall-Ssid: Heimnetz-2G        Name des WLANs
 *   X-Wall-Rssi: -54                WLAN in dBm
 *   X-Wall-Uptime: 570000           Sekunden seit dem Start
 *   X-Wall-Temp: 41                 Grad Celsius
 *   X-Wall-Flash: 71                Prozent belegt
 *   X-Wall-Restarts: 2
 *   X-Wall-Fw-Bad: 0.1.11           nur nach einem Zurueckrollen, ab Firmware 0.1.10
 *
 * Antwort: siehe frame_build(), dazu user (Name fuer die Begruessung), rev und fw,
 * wenn das Geraet jetzt aktualisieren soll.
 *
 * Ab dem 26. September 2026 dazu timers, alarm und ring (ring_frame_fields()), fuer die
 * Firmware ab 0.2.1: sie klingelt damit auf die Sekunde und auch ohne Server.
 *
 *   GET /api/v1/rev                  dieselben Kopfzeilen Authorization und X-Wall-Id
 *
 * Antwort {"rev": "12.3.0"}. Das Geraet fragt das alle zwei Sekunden und holt sofort
 * einen neuen Frame, wenn die Zahl nicht mehr zur letzten Antwort passt. So kommt
 * eine Aenderung auf der Webseite in gut einer Sekunde aufs Panel, ohne dass der
 * Server etwas schieben muss.
 */

declare(strict_types=1);

/* Falsche Schluessel je Adresse, Schluessel und Stunde, danach antwortet der Server sofort mit
   401. Der Zaehler haengt am Schluessel, damit ein Geraet mit altem Schluessel nicht die anderen
   Geraete hinter derselben Adresse aussperrt (Fehlerliste 24.09.2026, P1.3). */
const DEVICE_AUTH_FAIL_MAX = 50;

/**
 * $bucket und $perMinute gelten je Adresse. Frame, Logo und Firmware teilen sich "devauth" mit
 * 120 pro Minute, der Revisionsabruf hat einen eigenen Topf. Wer zu oft fragt, bekommt 429 mit
 * Retry-After, nie 401: die Firmware hielte 401 fuer einen falschen Schluessel.
 */
function api_device_auth(string $bucket = 'devauth', int $perMinute = 120): array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    $key = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : '';
    if ($key === '') {
        send_json_error(401, 'Missing or invalid key.', 'Schlüssel fehlt oder ist ungültig.');
    }
    $failKey = 'devauth-fail:' . ip_key() . ':' . substr(hash('sha256', $key), 0, 16);
    // Derselbe falsche Schluessel eine Stunde lang: 401 ohne Datenbanksuche.
    if (rl_blocked($failKey, DEVICE_AUTH_FAIL_MAX, 3600)) {
        send_json_error(401, 'Missing or invalid key.', 'Schlüssel fehlt oder ist ungültig.');
    }
    $rate = $bucket . ':' . ip_key();
    if (!rl_allow($rate, $perMinute, 60)) {
        header('Retry-After: ' . rl_retry_after($rate, 60));
        send_json_error(429, 'Too many requests.', 'Zu viele Anfragen.');
    }
    $owner = user_by_api_key($key);
    if ($owner === null) {
        // Konto geloescht: einmal 410, die Firmware vergisst dann den Schluessel.
        if (api_key_revoked($key)) {
            send_json_error(410, 'The account for this key was deleted.', 'Das Konto zu diesem Schlüssel wurde gelöscht.');
        }
        rl_allow($failKey, DEVICE_AUTH_FAIL_MAX, 3600);
        send_json_error(401, 'Missing or invalid key.', 'Schlüssel fehlt oder ist ungültig.');
    }
    return $owner;
}

function api_device_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$key] ?? ''));
}

function api_frame(array $params = []): void
{
    $owner = api_device_auth();
    $uid = strtolower(api_device_header('X-Wall-Id'));
    if (!device_uid_valid($uid)) {
        send_json_error(400, 'X-Wall-Id is missing.', 'X-Wall-Id fehlt.');
    }
    // Gerät fragt alle 10 Sekunden. Mehr als eine Anfrage pro Sekunde ist ein Fehler.
    if (!rl_allow('frame:' . $owner['id'] . ':' . $uid, 1, 1)) {
        header('Retry-After: 2');
        send_json_error(429, 'Too fast, ask every ten seconds.', 'Zu schnell, bitte alle zehn Sekunden fragen.');
    }
    // Auf der Webseite entfernt: einmal 410, die Firmware vergisst dann den Schluessel.
    if (device_take_removed((int) $owner['id'], $uid)) {
        send_json_error(410, 'This device was removed from the account.', 'Dieses Geraet wurde aus dem Konto entfernt.');
    }
    $device = device_register_poll($owner, $uid, [
        'fw' => api_device_header('X-Wall-Fw'),
        'ssid' => api_device_header('X-Wall-Ssid'),
        'rssi' => api_device_header('X-Wall-Rssi'),
        'uptime' => api_device_header('X-Wall-Uptime'),
        'temp' => api_device_header('X-Wall-Temp'),
        'flash' => api_device_header('X-Wall-Flash'),
        'restarts' => api_device_header('X-Wall-Restarts'),
    ]);
    $settings = device_settings($device);
    $frame = frame_build($device, $settings, $owner, false);
    $frame['user'] = panel_text((string) $owner['username'], true, 10);
    $frame['device'] = ['id' => (int) $device['id'], 'name' => panel_text((string) $device['name'], false, 21)];
    $frame['rev'] = api_device_rev_value($device, $settings, (int) $owner['id']);
    $frame += ring_frame_fields($settings, time());
    $offer = firmware_offer($device, api_device_header('X-Wall-Fw-Bad'));
    if ($offer !== null) {
        $frame['fw'] = $offer;
    }
    // Was das Panel jetzt zeigt, fuer Home Assistant (Sensor "Zeigt"): die Seite, deren
    // Fenster gerade laeuft. So stimmt es auch mit Rotation, Notiz vorn und Spotify zuerst.
    foreach ($frame['pages'] as $page) {
        if ($page['from'] <= $frame['now'] && $frame['now'] < $page['to']) {
            cache_put('showing:' . (int) $device['id'], (string) ($page['mode'] ?? ''), 60);
            break;
        }
    }
    // Die Uhrzeit erst jetzt, nach dem Rechnen (frame_clock_ms). Server-Timing zeigt, wie lange.
    $received = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    $sent = microtime(true);
    $frame['now'] = frame_clock_ms($received, $sent);
    header('Server-Timing: app;dur=' . max(0, (int) round(($sent - $received) * 1000)));
    send_json($frame);
}

/**
 * Ab Firmware 0.2.1: ein Druck aufs Rad stoppt das Klingeln. Das Geraet ist dann schon
 * still, hier erfaehrt es der Server, damit das Panel und Home Assistant es auch wissen.
 *
 *   POST /api/v1/ring/stop    dieselben Kopfzeilen Authorization und X-Wall-Id
 */
function api_device_ring_stop(array $params = []): void
{
    $owner = api_device_auth();
    $uid = strtolower(api_device_header('X-Wall-Id'));
    if (!device_uid_valid($uid)) {
        send_json_error(400, 'X-Wall-Id is missing.', 'X-Wall-Id fehlt.');
    }
    if (!rl_allow('ringstop:' . $owner['id'] . ':' . $uid, 10, 60)) {
        header('Retry-After: 6');
        send_json_error(429, 'Too many requests.', 'Zu viele Anfragen.');
    }
    $id = qval('SELECT id FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid]);
    if (!$id) {
        send_json_error(404, 'Unknown device, ask /api/v1/frame first.', 'Unbekanntes Geraet, zuerst /api/v1/frame fragen.');
    }
    $now = time();
    device_settings_update((int) $id, static fn(array $cur): ?array => ring_now($cur, $now)['since'] === null ? null : [ring_stop($cur, $now), false]);
    send_json(['ok' => true]);
}

/**
 * Aendert sich bei neuen Einstellungen, einer neuen Notiz und "Jetzt aktualisieren". Zeigt
 * das Geraet Spotify, dazu bei einem anderen Titel, Pause oder Weiter: so kommt ein Sprung
 * am Handy in zwei bis vier Sekunden aufs Panel.
 */
function api_device_rev_value(array $device, ?array $settings = null, int $ownerId = 0): string
{
    return device_rev_value($device, $settings, $ownerId);
}

function api_device_rev(array $params = []): void
{
    // 600 pro Minute je Adresse: 20 Geraete hinter einem Router, jedes alle zwei Sekunden.
    $owner = api_device_auth('devrev', 600);
    $uid = strtolower(api_device_header('X-Wall-Id'));
    if (!device_uid_valid($uid)) {
        send_json_error(400, 'X-Wall-Id is missing.', 'X-Wall-Id fehlt.');
    }
    if (!rl_allow('rev:' . $owner['id'] . ':' . $uid, 2, 1)) {
        header('Retry-After: 2');
        send_json_error(429, 'Too fast, ask every two seconds.', 'Zu schnell, bitte alle zwei Sekunden fragen.');
    }
    $device = q1('SELECT settings, settings_rev, note_rev, update_requested_at FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid]);
    if (!$device) {
        send_json_error(404, 'Unknown device, ask /api/v1/frame first.', 'Unbekanntes Geraet, zuerst /api/v1/frame fragen.');
    }
    send_json(['rev' => api_device_rev_value($device, device_settings($device), (int) $owner['id'])]);
}

function api_device_logo(array $params = []): void
{
    api_device_auth();
    $rgb = logo_rgb((string) ($params['code'] ?? ''));
    if ($rgb === null) {
        send_json_error(404, 'No logo for this code.', 'Kein Logo fuer dieses Kuerzel.');
    }
    logo_send($rgb);
}

function api_firmware_download(array $params = []): void
{
    $owner = api_device_auth();
    $uid = strtolower(api_device_header('X-Wall-Id'));
    $device = q1('SELECT * FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid]);
    $f = $device ? firmware_available($device) : null;
    if ($f === null || $f['version'] !== (string) ($params['version'] ?? '')) {
        send_json_error(404, 'No firmware for this device.', 'Keine Firmware fuer dieses Geraet.');
    }
    $file = TW_DATA . '/firmware/' . basename((string) $f['file']);
    if (!is_file($file)) {
        send_json_error(404, 'Firmware file missing.', 'Firmware-Datei fehlt.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: ' . NO_STORE);
    header('X-Checksum-Sha256: ' . $f['sha256']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($file);
    exit;
}
