<?php
/*
 * JSON fuer den Admin-Bereich. Nur Konten mit Rolle admin, jede Aenderung mit
 * CSRF-Pruefung. Zugangsdaten (SMTP-Schluessel) gehen nie zurueck an den Browser.
 */

declare(strict_types=1);

function api_registration(array $params = []): void
{
    require_admin();
    csrf_check();
    $mode = (req_json()['mode'] ?? '') === 'open' ? 'open' : 'invite';
    setting_set('registration', $mode);
    send_json(['ok' => true, 'mode' => $mode]);
}

function api_signup_link(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    $email = strtolower(trim((string) (req_json()['email'] ?? '')));
    if ($email !== '' && !valid_email($email)) {
        send_json_error(422, 'Please enter a complete email address or leave it empty.', 'Bitte eine vollständige E-Mail-Adresse, oder das Feld leer lassen.');
    }
    $raw = token_create('signup', ['email' => $email !== '' ? $email : null, 'created_by' => (int) $admin['id']]);
    send_json(['ok' => true, 'link' => app_url() . '/invite?t=' . $raw]);
}

function api_firmware(array $params = []): void
{
    require_admin();
    csrf_check();
    $in = req_json();
    if (array_key_exists('rollout', $in)) {
        setting_set('rollout', (int) (round(clamp_int($in['rollout'], 0, 100, 100) / 5) * 5));
    }
    if (array_key_exists('auto', $in)) {
        setting_set('auto_update', (bool) $in['auto']);
    }
    send_json(['ok' => true, 'rollout' => firmware_rollout(), 'auto' => firmware_auto()]);
}

/**
 * Neue Firmware als Rohdaten im Rumpf (application/octet-stream). Der Server legt
 * sie als .htdata/firmware/thewall-<version>.bin ab, die Version kommt aus der
 * Datei selbst (firmware_store()). Zehn in der Stunde.
 */
function api_firmware_upload(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!rl_allow('fwupload:' . $admin['id'], 10, 3600)) {
        send_json_error(429, 'Ten uploads an hour are enough.', 'Zehn Uploads in der Stunde reichen.');
    }
    $bin = file_get_contents('php://input', false, null, 0, FIRMWARE_MAX_BYTES + 1);
    [$version, $en, $de] = firmware_store(is_string($bin) ? $bin : '');
    if ($version === null) {
        send_json_error(422, $en, $de);
    }
    $f = q1('SELECT version, size, sha256 FROM firmware WHERE version = ?', [$version]);
    send_json(['ok' => true, 'version' => $version, 'size' => (int) ($f['size'] ?? 0), 'sha256' => (string) ($f['sha256'] ?? '')]);
}

function api_smtp(array $params = []): void
{
    require_admin();
    csrf_check();
    $in = req_json();
    $host = strtolower(trim((string) ($in['host'] ?? '')));
    $port = (int) ($in['port'] ?? 587);
    $security = ($in['security'] ?? 'starttls') === 'ssl' ? 'ssl' : 'starttls';
    $user = trim((string) ($in['user'] ?? ''));
    $pass = (string) ($in['pass'] ?? '');
    $fromEmail = strtolower(trim((string) ($in['from_email'] ?? '')));
    $fromName = trim((string) ($in['from_name'] ?? 'THE WALL'));

    $errors = [];
    if ($host === '' || !preg_match('/^[a-z0-9.\-]{3,190}$/', $host)) {
        $errors['host'] = ['Please enter the server name, for Brevo smtp-relay.brevo.com.', 'Bitte den Servernamen, bei Brevo smtp-relay.brevo.com.'];
    }
    if ($port < 1 || $port > 65535) {
        $errors['port'] = ['Please enter a port, usually 587.', 'Bitte einen Port, meist 587.'];
    }
    if ($fromEmail === '' || !valid_email($fromEmail)) {
        $errors['from_email'] = ['Please enter the sender address.', 'Bitte die Absenderadresse eintragen.'];
    }
    if (mb_strlen($fromName) > 60 || preg_match('/[\r\n]/', $fromName) || preg_match('/[\r\n]/', $user)) {
        $errors['from_name'] = ['The sender name is too long.', 'Der Absendername ist zu lang.'];
    }
    if ($errors) {
        $first = reset($errors);
        send_json_error(422, $first[0], $first[1], ['fields' => array_keys($errors)]);
    }
    $old = setting('smtp', []);
    $record = [
        'host' => $host,
        'port' => $port,
        'security' => $security,
        'user' => $user,
        'from_email' => $fromEmail,
        'from_name' => $fromName !== '' ? $fromName : 'THE WALL',
        'pass' => $pass !== '' ? seal($pass) : ($old['pass'] ?? null),
        'tested_at' => null,
        'test_ok' => false,
    ];
    setting_set('smtp', $record);
    send_json(['ok' => true, 'smtp' => smtp_settings()]);
}

function api_smtp_test(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!rl_allow('smtptest:' . $admin['id'], 5, 600)) {
        send_json_error(429, 'Five tests in ten minutes are enough. Wait a moment.', 'Fünf Tests in zehn Minuten reichen. Einen Moment warten.');
    }
    if (!smtp_configured()) {
        send_json_error(409, 'Save the mail settings first.', 'Erst die Mail-Einstellungen speichern.');
    }
    $to = strtolower(trim((string) (req_json()['to'] ?? '')));
    if ($to === '') {
        $to = (string) $admin['email'];
    }
    if (!valid_email($to)) {
        send_json_error(422, 'Please enter a complete email address.', 'Bitte eine vollständige E-Mail-Adresse.');
    }
    $result = mail_test($to);
    $record = setting('smtp', []);
    $record['tested_at'] = time();
    $record['test_ok'] = $result['ok'];
    setting_set('smtp', $record);
    if (!$result['ok']) {
        send_json_error(424, 'The test failed: ' . $result['error'], 'Der Test ist fehlgeschlagen: ' . $result['error']);
    }
    send_json(['ok' => true, 'to' => $to]);
}

/** Schluessel fuer mobiliteit.lu speichern. Er geht nie zurueck an den Browser. */
function api_transit(array $params = []): void
{
    require_admin();
    csrf_check();
    $key = trim((string) (req_json()['key'] ?? ''));
    if ($key === '') {
        send_json_error(422, 'Please paste the key from the mail of the ATP.', 'Bitte den Schlüssel aus der Mail der ATP einfügen.', ['fields' => ['key']]);
    }
    if (!preg_match('/^[A-Za-z0-9\-]{8,128}$/', $key)) {
        send_json_error(422, 'That does not look like a key. It only has letters, digits and hyphens.', 'Das sieht nicht nach einem Schlüssel aus. Er hat nur Buchstaben, Ziffern und Bindestriche.', ['fields' => ['key']]);
    }
    transit_save_key($key);
    send_json(['ok' => true, 'transit' => transit_settings()]);
}

/**
 * Spotify-App des Projekts: Client-ID und Secret aus dem Dashboard. Das Secret wird
 * verschluesselt gespeichert und nie zurueckgegeben; leer gelassen bleibt das alte.
 */
function api_spotify(array $params = []): void
{
    require_admin();
    csrf_check();
    $in = req_json();
    $id = is_string($in['client_id'] ?? null) ? strtolower(trim($in['client_id'])) : '';
    $secret = is_string($in['secret'] ?? null) ? strtolower(trim($in['secret'])) : '';
    if (!preg_match('/^[0-9a-f]{32}$/', $id)) {
        send_json_error(422, 'The client ID has 32 characters, digits and a to f. It is on the app page in the Spotify dashboard.', 'Die Client-ID hat 32 Zeichen, Ziffern und a bis f. Sie steht auf der Seite der App im Spotify-Dashboard.', ['fields' => ['client_id']]);
    }
    if ($secret === '' && !spotify_settings()['has_secret']) {
        send_json_error(422, 'Please paste the client secret as well.', 'Bitte auch das Client Secret einfügen.', ['fields' => ['secret']]);
    }
    if ($secret !== '' && !preg_match('/^[0-9a-f]{32}$/', $secret)) {
        send_json_error(422, 'The client secret has 32 characters, digits and a to f.', 'Das Client Secret hat 32 Zeichen, Ziffern und a bis f.', ['fields' => ['secret']]);
    }
    spotify_save_app($id, $secret === '' ? null : $secret);
    send_json(['ok' => true, 'spotify' => spotify_settings()]);
}

/** Zugang pruefen: Spotify gibt der App ein Token (Client Credentials), ganz ohne Konto. */
function api_spotify_test(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!rl_allow('spotifytest:' . $admin['id'], 10, 600)) {
        send_json_error(429, 'Ten tests in ten minutes are enough. Wait a moment.', 'Zehn Tests in zehn Minuten reichen. Einen Moment warten.');
    }
    if (!spotify_configured()) {
        send_json_error(409, 'Save the client ID and secret first.', 'Zuerst Client-ID und Secret speichern.');
    }
    $r = spotify_test_app();
    spotify_mark_tested($r['ok']);
    if (!$r['ok']) {
        send_json_error($r['status'], $r['en'], $r['de'], ['spotify' => spotify_settings()]);
    }
    send_json(['ok' => true, 'spotify' => spotify_settings(), 'en' => $r['en'], 'de' => $r['de']]);
}

/** Schluessel pruefen: Haltestellen am Hauptbahnhof und die naechsten Abfahrten der naechsten. */
function api_transit_test(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!rl_allow('transittest:' . $admin['id'], 10, 600)) {
        send_json_error(429, 'Ten tests in ten minutes are enough. Wait a moment.', 'Zehn Tests in zehn Minuten reichen. Einen Moment warten.');
    }
    [, $lat, $lon] = TRANSIT_SAMPLE_PLACES[0];
    $near = transit_nearby($lat, $lon, 400, 20);
    $stops = $near['ok'] ? $near['stops'] : [];
    $main = $stops ? transit_main_stops($stops)[0] : null;
    $dep = ($near['ok'] && $main) ? transit_departures($main['id'], 12, 90) : $near;
    if (!$near['ok'] || !$dep['ok']) {
        $r = !$near['ok'] ? $near : $dep;
        if (!in_array($r['code'], ['NO_KEY', 'QUOTA_LOCAL', 'NETWORK'], true)) {
            transit_mark_tested(false);
        }
        send_json_error($r['status'], $r['error_en'], $r['error_de'], ['transit' => transit_settings(), 'quota' => transit_quota()]);
    }
    if (!$stops) {
        transit_mark_tested(false);
        send_json_error(424, 'The key works, but mobiliteit.lu found no stop at Luxembourg station.', 'Der Schlüssel geht, aber mobiliteit.lu findet am Bahnhof Luxemburg keine Haltestelle.', ['transit' => transit_settings()]);
    }
    transit_mark_tested(true);
    $byMode = [];
    foreach ($dep['departures'] as $d) {
        $byMode[$d['mode']] = ($byMode[$d['mode']] ?? 0) + 1;
    }
    send_json([
        'ok' => true,
        'transit' => transit_settings(),
        'quota' => transit_quota(),
        'stops' => array_map(static fn(array $s): array => ['id' => $s['id'], 'name' => $s['name'], 'dist_m' => $s['dist_m'], 'weight' => $s['weight'], 'modes' => $s['modes'], 'lines' => count($s['lines'])], array_slice($stops, 0, 5)),
        'stop' => $main['name'],
        'by_mode' => $byMode,
        'departures' => array_map(static fn(array $d): array => [
            'time' => substr((string) ($d['realtime'] ?? $d['planned'] ?? ''), 11, 5),
            'label' => $d['label'],
            'mode' => $d['mode'],
            'direction' => $d['direction'],
            'delay_min' => $d['delay_min'],
            'platform' => $d['platform_hidden'] ? null : ($d['platform_realtime'] ?? $d['platform']),
            'cancelled' => $d['cancelled'],
            'additional' => $d['additional'],
            'replacement' => $d['replacement'],
            'redirected' => $d['redirected'],
        ], array_slice($dep['departures'], 0, 8)),
    ]);
}

/** Rohabfrage an mobiliteit.lu fuer die Fehlersuche, nur freigegebene Endpunkte und Parameter. */
function api_transit_raw(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!rl_allow('transitraw:' . $admin['id'], 30, 600)) {
        send_json_error(429, 'Thirty raw requests in ten minutes are enough. Wait a moment.', 'Dreißig Rohabfragen in zehn Minuten reichen. Einen Moment warten.');
    }
    $in = req_json();
    $r = transit_raw((string) ($in['endpoint'] ?? ''), is_array($in['params'] ?? null) ? $in['params'] : []);
    if (!$r['ok']) {
        send_json_error($r['status'], $r['error_en'], $r['error_de'], ['code' => $r['code'], 'quota' => transit_quota()]);
    }
    send_json(['ok' => true, 'code' => $r['code'], 'quota' => transit_quota(), 'data' => $r['data']]);
}

/** Datenprobe fuer Entwuerfe: sechs oeffentliche Haltestellen mit Bus, Zug und Tram. */
function api_transit_sample(array $params = []): void
{
    $admin = require_admin();
    csrf_check();
    if (!transit_configured()) {
        send_json_error(409, 'Save the key for mobiliteit.lu first.', 'Erst den Schlüssel für mobiliteit.lu speichern.');
    }
    if (!rl_allow('transitsample:' . $admin['id'], 5, 600)) {
        send_json_error(429, 'Five samples in ten minutes are enough. Wait a moment.', 'Fünf Proben in zehn Minuten reichen. Einen Moment warten.');
    }
    @set_time_limit(120);
    $sample = transit_sample();
    if ($sample['stops']) {
        // Die letzte Probe bleibt auf dem Server liegen (.htdata ist von aussen gesperrt),
        // fuer die Arbeit an Entwuerfen und am Modus ohne erneute Abfragen.
        try {
            ensure_data_dir();
            atomic_write(TW_DATA . '/transit/sample-latest.json', json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            log_error('transit sample speichern: ' . $e->getMessage());
        }
    }
    if (!$sample['stops']) {
        send_json_error(424, 'No stop answered: ' . ($sample['errors'][0] ?? 'unknown error'), 'Keine Haltestelle hat geantwortet: ' . ($sample['errors'][0] ?? 'unbekannter Fehler'));
    }
    send_json(['ok' => true, 'quota' => transit_quota(), 'sample' => $sample]);
}

/** Testgeraet fuer ein Konto anlegen (on true) oder entfernen (on false). */
function api_test_device(array $params = []): void
{
    require_admin();
    csrf_check();
    $in = req_json();
    $user = user_by_id((int) ($in['user'] ?? 0));
    if ($user === null) {
        send_json_error(404, 'Account not found.', 'Konto nicht gefunden.');
    }
    if (!empty($in['on'])) {
        $id = device_add_test($user);
        if ($id === null) {
            send_json_error(409, 'This account already has ' . DEVICE_MAX_PER_ACCOUNT . ' devices.', 'Dieses Konto hat schon ' . DEVICE_MAX_PER_ACCOUNT . ' Geräte.');
        }
    } else {
        device_remove_test((int) $user['id']);
    }
    send_json([
        'ok' => true,
        'test' => !empty($in['on']),
        'devices' => (int) qval('SELECT COUNT(*) FROM devices WHERE owner_id = ?', [(int) $user['id']]),
        'username' => $user['username'],
    ]);
}

function api_imprint(array $params = []): void
{
    require_admin();
    csrf_check();
    $in = req_json();
    $name = trim((string) ($in['name'] ?? ''));
    $address = trim(str_replace("\r", '', (string) ($in['address'] ?? '')));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $repo = trim((string) ($in['repo'] ?? ''));
    if (mb_strlen($name) > 120 || mb_strlen($address) > 240) {
        send_json_error(422, 'Name or address is too long.', 'Name oder Adresse ist zu lang.');
    }
    if ($email !== '' && !valid_email($email)) {
        send_json_error(422, 'Please enter a complete contact address.', 'Bitte eine vollständige Kontaktadresse.');
    }
    if ($repo !== '' && !preg_match('#^https://[A-Za-z0-9.\-]+(/[A-Za-z0-9._~\-/]*)?$#', $repo)) {
        send_json_error(422, 'The repository address has to start with https://', 'Die Adresse des Repositorys muss mit https:// beginnen.');
    }
    setting_set('imprint', ['name' => $name, 'address' => $address, 'email' => $email, 'repo' => $repo]);
    send_json(['ok' => true, 'user_agent' => user_agent()]);
}
