<?php
/*
 * Geraete, Einstellungen und Rechte.
 *
 * Rollen: owner (Besitzer), edit (darf bedienen), view (nur ansehen). Gaeste mit
 * Leserecht duerfen Notizen senden, sonst nichts. Das prueft der Server selbst in
 * device_sanitize(), nicht nur der Knopf im Browser (FEHLERLISTE 6).
 */

declare(strict_types=1);

const DEVICE_ONLINE_SECONDS = 60;
/* Ein Geraet entsteht beim ersten Abruf mit gueltigem Schluessel. Die Grenze haelt
   einen entwendeten Schluessel davon ab, beliebig viele Geraete anzulegen. */
const DEVICE_MAX_PER_ACCOUNT = 20;
/* So lange merkt sich der Server ein entferntes Geraet, damit es beim naechsten Abruf 410 bekommt. */
const DEVICE_REMOVED_DAYS = 30;
/* Sekunden je Modus in der Rotation. Bis zum 4. Oktober 2026 fest 30, seitdem einstellbar.
   Der Regler auf der Geraeteseite rastet an diesen Stufen ein, der Server nimmt jede ganze
   Zahl zwischen der kleinsten und der groessten. Bei 10 Sekunden ist das Panel mit 1,6
   Sekunden Schub zwischen den Modi schon ein Sechstel der Zeit in Bewegung. */
const ROTATION_CYCLE = 30;
const ROTATION_CYCLES = [10, 15, 20, 30, 45, 60, 90, 120, 180, 300, 600];

function device_defaults(): array
{
    $s = [
        'mode' => 'flight',
        'rotation' => ['flight', 'clock'],
        'cycle' => ROTATION_CYCLE,
        'bright' => 168,
        // Bewegung reduzieren: kein Schub zwischen den Modi, kein Blinken, Laufschrift steht.
        'reduce' => false,
        'lang' => 'en',
        'tz' => 'Europe/Luxembourg',
        'location' => ['lat' => 49.6116, 'lon' => 6.1319, 'place' => 'Luxembourg'],
        'applied' => false,
        'note_at' => 0,
        // Panel aus: dunkel, bis es wieder angeht. Klingeln und ein Kopplungscode schalten es
        // dafuer trotzdem an (frame_build()). Home Assistant schaltet es als Licht.
        'off' => false,
        // Laufende Timer und der Wecker (device/timers.php). Beide aendert nur ihre eigene
        // Schnittstelle, nie "Uebernehmen": sonst setzte ein alter Tab sie still zurueck.
        'timers' => [],
        'alarm' => alarm_defaults(),
    ];
    foreach (modes() as $id => $m) {
        $s[$id] = $m['defaults'];
    }
    return $s;
}

/** Gespeicherte Einstellungen, ergaenzt um alles, was ein neuer Modus mitbringt. */
function device_settings(array $device): array
{
    $stored = json_decode((string) ($device['settings'] ?? '{}'), true);
    $stored = is_array($stored) ? $stored : [];
    $out = device_defaults();
    foreach ($out as $k => $v) {
        if (!array_key_exists($k, $stored)) {
            continue;
        }
        $out[$k] = is_array($v) && is_array($stored[$k]) && !array_is_list($v) ? array_replace($v, $stored[$k]) : $stored[$k];
    }
    return $out;
}

/**
 * Eingabe aus dem Browser pruefen und mit dem bisherigen Stand zusammenfuehren.
 * Liefert [bereinigt, fehler]. Fehler als Liste von [en, de].
 */
function device_sanitize(array $in, array $current, string $role): array
{
    $errors = [];
    $out = $current;

    // Notizen darf jede Rolle senden.
    if (isset($in['notes']) && is_array($in['notes'])) {
        foreach (['line1', 'line2'] as $line) {
            if (array_key_exists($line, $in['notes'])) {
                $out['notes'][$line] = str_cut(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $in['notes'][$line]) ?? '', PANEL_LINE_MAX);
            }
        }
    }
    if ($role === 'view') {
        return [$out, $errors];
    }

    if (isset($in['mode'])) {
        $m = mode_get((string) $in['mode']);
        if ($m === null) {
            $errors[] = ['Unknown mode.', 'Unbekannter Modus.'];
        } elseif (!$m['available']) {
            $errors[] = ['This mode comes after the 25th.', 'Dieser Modus kommt nach dem 25.'];
        } else {
            $out['mode'] = (string) $in['mode'];
        }
    }
    if (isset($in['rotation']) && is_array($in['rotation'])) {
        $rot = [];
        foreach (modes() as $id => $m) {
            if ($m['rotatable'] && $m['available'] && in_array($id, $in['rotation'], true)) {
                $rot[] = $id;
            }
        }
        $out['rotation'] = $rot;
    }
    if (array_key_exists('cycle', $in)) {
        $out['cycle'] = clamp_int($in['cycle'], ROTATION_CYCLES[0], ROTATION_CYCLES[count(ROTATION_CYCLES) - 1], (int) ($current['cycle'] ?? ROTATION_CYCLE));
    }
    if (array_key_exists('bright', $in)) {
        $out['bright'] = clamp_int($in['bright'], 0, 255, (int) $current['bright']);
    }
    if (array_key_exists('reduce', $in)) {
        $out['reduce'] = (bool) $in['reduce'];
    }
    if (array_key_exists('off', $in)) {
        $out['off'] = (bool) $in['off'];
    }
    if (isset($in['lang'])) {
        $out['lang'] = $in['lang'] === 'de' ? 'de' : 'en';
    }
    if (isset($in['tz'])) {
        if (tz_valid((string) $in['tz'])) {
            $out['tz'] = (string) $in['tz'];
        } else {
            $errors[] = ['Unknown time zone.', 'Unbekannte Zeitzone.'];
        }
    }
    if (isset($in['location']) && is_array($in['location'])) {
        $lat = $in['location']['lat'] ?? null;
        $lon = $in['location']['lon'] ?? null;
        if (is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180) {
            $out['location']['lat'] = round((float) $lat, 5);
            $out['location']['lon'] = round((float) $lon, 5);
            $place = trim((string) ($in['location']['place'] ?? ''));
            $out['location']['place'] = $place !== '' ? str_cut($place, 60) : (($out['lang'] ?? 'en') === 'de' ? 'Eigener Punkt' : 'Custom point');
        } else {
            $errors[] = ['That location is not valid.', 'Dieser Standort ist ungueltig.'];
        }
    }
    // Auf $out aufbauen, nicht auf $current: die Notizzeilen oben sind schon uebernommen.
    foreach (modes() as $id => $m) {
        if (isset($in[$id]) && is_array($in[$id])) {
            $out[$id] = ($m['sanitize'])($in[$id], $out[$id] ?? $m['defaults']);
        }
    }
    return [$out, $errors];
}

/** Eigene und freigegebene Geraete, jeweils mit 'role'. */
function devices_for_user(int $userId): array
{
    $own = qall('SELECT d.*, u.username AS owner_name FROM devices d JOIN users u ON u.id = d.owner_id WHERE d.owner_id = ? ORDER BY d.created_at, d.id', [$userId]);
    foreach ($own as &$d) {
        $d['role'] = 'owner';
    }
    unset($d);
    $shared = qall('SELECT d.*, s.rights AS role, u.username AS owner_name FROM shares s JOIN devices d ON d.id = s.device_id JOIN users u ON u.id = d.owner_id WHERE s.user_id = ? ORDER BY s.created_at', [$userId]);
    return array_merge($own, $shared);
}

function device_for_user(int $deviceId, int $userId): ?array
{
    foreach (devices_for_user($userId) as $d) {
        if ((int) $d['id'] === $deviceId) {
            return $d;
        }
    }
    return null;
}

function device_online(array $d): bool
{
    return !empty($d['last_seen_at']) && (int) $d['last_seen_at'] >= time() - DEVICE_ONLINE_SECONDS;
}

/** Statusleiste der Geraeteseite, je Eintrag [en, de]. Dieselbe Liste fuer Seite und /status. */
function device_telemetry(array $d): array
{
    if (device_is_test($d) && empty($d['last_seen_at'])) {
        $out = [['TEST DEVICE, IT NEVER POLLS THE SERVER', 'TESTGERÄT, ES FRAGT DEN SERVER NIE']];
        // Ein Testgeraet hat kein Panel, auf dem der Kopplungscode stehen koennte.
        $p = ha_pairing_pending((int) $d['id'], time());
        if ($p !== null) {
            $c = substr($p['code'], 0, 3) . ' ' . substr($p['code'], 3);
            $out[] = ['HOME ASSISTANT CODE ' . $c, 'HOME-ASSISTANT-CODE ' . $c];
        }
        return $out;
    }
    if (empty($d['last_seen_at'])) {
        return [['NOT SEEN YET', 'NOCH NICHT GESEHEN']];
    }
    $out = [];
    if (device_online($d)) {
        [$uEn, $uDe] = uptime_pair($d['uptime'] !== null ? (int) $d['uptime'] : null);
        $out[] = ['Online' . ($uEn !== '' ? ' ' . $uEn : ''), 'Online' . ($uDe !== '' ? ' seit ' . $uDe : '')];
    } else {
        [$aEn, $aDe] = ago_pair((int) $d['last_seen_at']);
        $out[] = ['Last seen ' . $aEn . ' ago', 'Zuletzt vor ' . $aDe];
    }
    if ($d['rssi'] !== null) {
        $w = "WLAN −" . abs((int) $d['rssi']) . ' dBm';
        $out[] = [$w, $w];
    }
    if ($d['fw'] !== null) {
        $out[] = ['FW ' . $d['fw'], 'FW ' . $d['fw']];
    }
    if ($d['temp'] !== null) {
        $out[] = [(int) $d['temp'] . " °C", (int) $d['temp'] . " °C"];
    }
    if ($d['flash'] !== null) {
        $out[] = ['Flash ' . (int) $d['flash'] . ' %', 'Speicher ' . (int) $d['flash'] . ' %'];
    }
    // Die Zeile steht in Grossbuchstaben, das Einheitenzeichen dBm aber nicht (Befund D7).
    $up = static fn(string $s): string => str_replace('DBM', 'dBm', mb_strtoupper($s));
    return array_map(static fn(array $p): array => [$up($p[0]), $up($p[1])], $out);
}

/**
 * Einstellungen lesen, aendern und nur dann zurueckschreiben, wenn niemand dazwischen
 * geschrieben hat: settings_rev dient als Vergleich, sonst von vorn, hoechstens fuenfmal.
 * Seit Home Assistant und Timer schreiben mehrere Stellen dieselbe Zeile; vorher haette ein
 * "Uebernehmen" einen eben gestellten Timer still ueberschrieben. Keine Transaktion: MariaDB
 * 12.3 bricht Lesen-dann-Schreiben mit 1020 ab (CHANGELOG 62).
 *
 * $fn bekommt die aktuellen Einstellungen und gibt [neue Einstellungen, Notiz geaendert]
 * zurueck, oder null fuer "nichts schreiben". Liefert die gespeicherten Einstellungen, bei
 * null die unveraenderten, und null, wenn es das Geraet nicht mehr gibt.
 */
function device_settings_update(int $deviceId, callable $fn): ?array
{
    for ($try = 0; $try < 5; $try++) {
        $row = q1('SELECT settings, settings_rev FROM devices WHERE id = ?', [$deviceId]);
        if (!$row) {
            return null;
        }
        $cur = device_settings($row);
        $res = $fn($cur);
        if ($res === null) {
            return $cur;
        }
        [$new, $noteChanged] = $res;
        $sql = 'UPDATE devices SET settings = ?, settings_rev = settings_rev + 1' . ($noteChanged ? ', note_rev = note_rev + 1' : '') . ', updated_at = ? WHERE id = ? AND settings_rev = ?';
        $st = q($sql, [json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time(), $deviceId, (int) $row['settings_rev']]);
        if ($st->rowCount() === 1) {
            return $new;
        }
    }
    throw new RuntimeException('Einstellungen von Geraet ' . $deviceId . ' fuenfmal gleichzeitig geaendert.');
}

/**
 * Revision fuer /api/v1/rev und Home Assistant: aendert sich mit neuen Einstellungen, einer
 * neuen Notiz, "Jetzt aktualisieren", wenn etwas zu klingeln beginnt oder aufhoert
 * (ring_rev()) und bei Spotify mit einem anderen Titel, Pause oder Weiter.
 */
function device_rev_value(array $device, ?array $settings = null, int $ownerId = 0): string
{
    $rev = (int) ($device['settings_rev'] ?? 0) . '.' . (int) ($device['note_rev'] ?? 0) . '.' . (int) ($device['update_requested_at'] ?? 0);
    if ($settings !== null) {
        $rev .= '.' . ring_rev($settings, time());
        if ($ownerId > 0 && spotify_involved($settings) && spotify_configured()) {
            $rev .= '.' . spotify_rev($ownerId);
        }
    }
    return $rev;
}

/**
 * Zeitpunkt der Notiz nach "Uebernehmen": eine neue Notiz steht ab jetzt zehn Minuten vorn.
 * Wer ohne neue Notiz einen anderen Modus, eine andere Rotation oder einen anderen Takt der
 * Rotation uebernimmt, will das sehen, die Notiz verliert ihren Vorrang sofort. Bis zum
 * 26. September 2026 stand sie dann noch bis zu zehn Minuten da, die Vorschau zeigte schon
 * den neuen Modus, und das Panel wirkte festgeklemmt. Liefert [Einstellungen, Notiz geaendert].
 */
function device_note_after_apply(array $clean, array $current, ?int $now = null): array
{
    $changed = $clean['notes']['line1'] !== $current['notes']['line1'] || $clean['notes']['line2'] !== $current['notes']['line2'];
    $cycle = (int) ($clean['cycle'] ?? ROTATION_CYCLE) !== (int) ($current['cycle'] ?? ROTATION_CYCLE);
    if ($changed && trim($clean['notes']['line1'] . $clean['notes']['line2']) !== '') {
        $clean['note_at'] = $now ?? time();
    } elseif (!$changed && ($clean['mode'] !== $current['mode'] || $clean['rotation'] !== $current['rotation'] || $cycle)) {
        $clean['note_at'] = 0;
    }
    return [$clean, $changed];
}

/**
 * Die Notiz verliert ihren Vorrang vor dem gewaehlten Modus. Sonst bleibt alles, wie es
 * ist, auch "applied"; die Revision steigt, damit das Panel in gut zwei Sekunden wechselt.
 */
function device_note_hide(int $deviceId): void
{
    device_settings_update($deviceId, static function (array $cur): ?array {
        if ((int) ($cur['note_at'] ?? 0) === 0) {
            return null;
        }
        $cur['note_at'] = 0;
        return [$cur, false];
    });
}

function device_uid_valid(string $uid): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9\-]{3,31}$/', $uid);
}

/** Geraet zu einem Schluessel finden oder beim ersten Abruf anlegen, dazu den Zustand merken. */
function device_register_poll(array $owner, string $uid, array $t): array
{
    $now = time();
    $d = q1('SELECT * FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid]);
    if (!$d) {
        $settings = device_defaults();
        $settings['lang'] = $owner['lang'] === 'de' ? 'de' : 'en';
        $count = (int) qval('SELECT COUNT(*) FROM devices WHERE owner_id = ?', [(int) $owner['id']]);
        if ($count >= DEVICE_MAX_PER_ACCOUNT) {
            send_json_error(403, 'This account already has ' . DEVICE_MAX_PER_ACCOUNT . ' devices.', 'Dieses Konto hat schon ' . DEVICE_MAX_PER_ACCOUNT . ' Geraete.');
        }
        $name = $count === 0 ? 'Wall' : 'Wall ' . ($count + 1);
        try {
            db_insert('devices', [
                'owner_id' => (int) $owner['id'],
                'uid' => $uid,
                'name' => $name,
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'settings_rev' => 1,
                'note_rev' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'last_seen_at' => $now,
            ]);
        } catch (PDOException $e) {
            // Zwei erste Abrufe kurz nacheinander: der andere hat das Geraet eben angelegt.
            if (!q1('SELECT 1 FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid])) {
                throw $e;
            }
        }
        $d = q1('SELECT * FROM devices WHERE owner_id = ? AND uid = ?', [(int) $owner['id'], $uid]);
    }
    $set = ['last_seen_at' => $now];
    if (isset($t['ssid']) && $t['ssid'] !== '') {
        $set['ssid'] = str_cut(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $t['ssid']) ?? '', 32);
    }
    if (isset($t['fw']) && $t['fw'] !== '') {
        $v = substr(preg_replace('/[^0-9A-Za-z.\-]/', '', (string) $t['fw']) ?? '', 0, 16);
        if ($v !== '') {
            $set['fw'] = $v;
        }
    }
    // Grenzen passend zu den Spalten: MariaDB im Strict-Mode lehnt zu grosse Werte ab,
    // und ein fehlerhaftes Geraet soll seinen eigenen Abruf nicht mit 500 beenden.
    foreach (['rssi' => [-150, 0], 'uptime' => [0, 99999999999], 'temp' => [-100, 200], 'flash' => [0, 100], 'restarts' => [0, 999999999]] as $k => [$min, $max]) {
        if (isset($t[$k]) && $t[$k] !== '' && is_numeric($t[$k])) {
            $set[$k] = (int) max($min, min($max, (float) $t[$k]));
        }
    }
    db_update('devices', $set, 'id = ?', [(int) $d['id']]);
    return array_merge($d, $set);
}

/**
 * Testgeraet fuer ein Konto: eine Geraeteseite mit allen Modi und der Vorschau,
 * ohne Hardware. Es fragt den Server nie selbst. Hoechstens eins je Konto, das
 * Anlegen gibt die ID des vorhandenen zurueck. null, wenn das Konto voll ist.
 */
function device_add_test(array $user): ?int
{
    $uid = (int) $user['id'];
    $existing = qval('SELECT id FROM devices WHERE owner_id = ? AND test = 1', [$uid]);
    if ($existing) {
        return (int) $existing;
    }
    if ((int) qval('SELECT COUNT(*) FROM devices WHERE owner_id = ?', [$uid]) >= DEVICE_MAX_PER_ACCOUNT) {
        return null;
    }
    $settings = device_defaults();
    $settings['lang'] = ($user['lang'] ?? '') === 'de' ? 'de' : 'en';
    $now = time();
    return db_insert('devices', [
        'owner_id' => $uid,
        'uid' => 'test-' . random_hex(4),
        'name' => $settings['lang'] === 'de' ? 'Testgerät' : 'Test device',
        'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'settings_rev' => 1,
        'note_rev' => 0,
        'test' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/** Testgeraete eines Kontos entfernen, samt Freigaben. Gibt die Anzahl zurueck. */
function device_remove_test(int $userId): int
{
    $n = 0;
    foreach (qall('SELECT id FROM devices WHERE owner_id = ? AND test = 1', [$userId]) as $d) {
        device_delete((int) $d['id']);
        $n++;
    }
    return $n;
}

function device_is_test(array $d): bool
{
    return (int) ($d['test'] ?? 0) === 1;
}

/**
 * Entfernt der Besitzer ein Geraet, merkt sich der Server Konto und Kennung. Fragt
 * das Geraet danach, antwortet api_frame() einmal mit 410, und die Firmware (ab
 * 0.1.1) vergisst den Schluessel und oeffnet ihr Einrichtungsnetz. Ohne das kaeme
 * es nach zehn Sekunden als neues Geraet zurueck (Befund D2).
 */
function device_mark_removed(int $ownerId, string $uid): void
{
    $list = setting('removed_devices', []);
    $list = is_array($list) ? $list : [];
    $cut = time() - DEVICE_REMOVED_DAYS * 86400;
    $list = array_filter($list, static fn(mixed $at): bool => (int) $at > $cut);
    $list[$ownerId . ':' . $uid] = time();
    setting_set('removed_devices', $list);
}

/** true, wenn dieses Geraet entfernt wurde. Der Eintrag gilt fuer einen einzigen Abruf. */
function device_take_removed(int $ownerId, string $uid): bool
{
    $list = setting('removed_devices', []);
    $k = $ownerId . ':' . $uid;
    if (!is_array($list) || !isset($list[$k])) {
        return false;
    }
    $fresh = (int) $list[$k] > time() - DEVICE_REMOVED_DAYS * 86400;
    unset($list[$k]);
    setting_set('removed_devices', $list);
    return $fresh;
}

function device_delete(int $id): void
{
    db_tx(static function () use ($id): void {
        q('DELETE FROM shares WHERE device_id = ?', [$id]);
        q('DELETE FROM tokens WHERE device_id = ?', [$id]);
        ha_forget_device($id);
        q('DELETE FROM devices WHERE id = ?', [$id]);
    });
}

/** Freigaben eines Geraets samt offener Einladungen. */
function device_shares(int $deviceId): array
{
    $users = qall('SELECT s.user_id, s.rights, u.username, u.email FROM shares s JOIN users u ON u.id = s.user_id WHERE s.device_id = ? ORDER BY s.created_at', [$deviceId]);
    $pending = qall("SELECT id, email, rights, expires_at FROM tokens WHERE kind = 'share' AND device_id = ? AND used_at IS NULL AND expires_at > ? ORDER BY created_at", [$deviceId, time()]);
    return ['users' => $users, 'pending' => $pending];
}

/** Alles zu einem Konto als Datei, fuer den Datenexport. */
function account_export(array $user): array
{
    $devices = [];
    foreach (qall('SELECT * FROM devices WHERE owner_id = ?', [(int) $user['id']]) as $d) {
        $devices[] = [
            'id' => $d['uid'],
            'name' => $d['name'],
            'settings' => device_settings($d),
            'firmware' => $d['fw'],
            'last_seen' => $d['last_seen_at'] ? gmdate('c', (int) $d['last_seen_at']) : null,
            'created' => gmdate('c', (int) $d['created_at']),
            'shared_with' => array_map(static fn(array $s): array => ['username' => $s['username'], 'rights' => $s['rights']], device_shares((int) $d['id'])['users']),
        ];
    }
    $shared = [];
    foreach (qall('SELECT d.name, s.rights, u.username AS owner FROM shares s JOIN devices d ON d.id = s.device_id JOIN users u ON u.id = d.owner_id WHERE s.user_id = ?', [(int) $user['id']]) as $s) {
        $shared[] = $s;
    }
    $spotify = spotify_link((int) $user['id']);
    return [
        'exported' => gmdate('c'),
        'spotify' => $spotify ? [
            'spotify_user' => $spotify['spotify_id'],
            'display_name' => $spotify['display_name'],
            'connected' => gmdate('c', (int) $spotify['created_at']),
            'note' => 'The access tokens are stored encrypted and are not part of the export.',
        ] : null,
        'account' => [
            'username' => $user['username'],
            'email' => $user['email'],
            'language' => $user['lang'],
            'registered' => gmdate('c', (int) $user['created_at']),
            'email_confirmed' => $user['email_verified_at'] ? gmdate('c', (int) $user['email_verified_at']) : null,
            'device_intro_seen' => !empty($user['tour_seen_at']) ? gmdate('c', (int) $user['tour_seen_at']) : null,
            'api_key' => $user['api_key'],
        ],
        'devices' => $devices,
        'devices_shared_with_you' => $shared,
    ];
}
