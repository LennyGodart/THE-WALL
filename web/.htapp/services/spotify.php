<?php
/*
 * Spotify. Der App-Zugang (Client-ID und Secret) gehoert dem Projekt und liegt in der
 * Einstellung spotify, das Secret verschluesselt wie der SMTP-Schluessel. Jedes Konto
 * verbindet sein eigenes Spotify-Konto (OAuth mit Autorisierungscode), die Token liegen
 * verschluesselt in spotify_links. Gelesen wird nur, was gerade laeuft, was als Naechstes
 * kommt und das Album dazu. Gesteuert wird nichts.
 *
 * Entwicklungsmodus seit Februar 2026: der Besitzer der App braucht Premium, hoechstens
 * fuenf Nutzer, jeder muss im Spotify-Dashboard unter User Management eingetragen sein.
 * Sonst antwortet die Web API mit 403.
 *
 * Spotify erlaubt nur voruebergehendes Zwischenspeichern, deshalb so kurz wie moeglich:
 *   Laeuft gerade   5 Sekunden je Konto, nie ueber das Ende des Titels hinaus, und nur,
 *                   solange ein Geraet oder eine Vorschau fragt
 *   Warteschlange   30 Sekunden je Konto und Titel
 *   Album           ein Tag
 *   Cover           ein Tag, fertig fuer das Panel gerechnet; das Originalbild wird nie gespeichert
 *
 * Lokal ersetzt TW_SPOTIFY_MOCK (etwa http://127.0.0.1:8766) beide Spotify-Adressen,
 * siehe tools/spotify-mock.php. Das gilt nur mit TW_ENV=dev.
 */

declare(strict_types=1);

const SPOTIFY_SCOPES = 'user-read-currently-playing user-read-playback-state';
const SPOTIFY_NOW_TTL = 5;
const SPOTIFY_QUEUE_TTL = 30;
const SPOTIFY_ALBUM_TTL = 86400;
const SPOTIFY_COVER_TTL = 86400;
const SPOTIFY_USERS_MAX = 5;
/* Groessen, in denen ein Cover gebraucht wird: Gross und Album 64, Klassisch 48, Platte 44,
   Warteschlange 10. 16 ist fuer einen Songtext mit Cover im Kopf vorgesehen. */
const SPOTIFY_COVER_SIZES = [64, 48, 44, 16, 10];
/* Umrechnung fuer das Panel, Werte aus design/spotify/cover.php. */
const COVER_TARGET_MEAN = 0.42;  // mittlere Helligkeit, auf die ein helles Cover gedaempft wird
const COVER_GAIN_MIN = 0.58;     // so weit darf ein weisses Cover hoechstens abgedunkelt werden
const COVER_GAIN_MAX = 0.86;     // auch ein dunkles leuchtet nie voll, weisser Text bleibt das Hellste
const COVER_OFF_MAX = 18;        // bis zu diesem hellsten Kanal ist ein Punkt aus
const COVER_LIFT_MIN = 52;       // dunkle, aber farbige Punkte werden bis hierhin angehoben

/** Lokale Nachbildung von Spotify, nur mit TW_ENV=dev. */
function spotify_mock(): string
{
    return is_dev() ? rtrim((string) getenv('TW_SPOTIFY_MOCK'), '/') : '';
}

function spotify_base(string $kind): string
{
    $mock = spotify_mock();
    if ($mock !== '') {
        return $mock . ($kind === 'accounts' ? '/accounts' : '/v1');
    }
    return $kind === 'accounts' ? 'https://accounts.spotify.com' : 'https://api.spotify.com/v1';
}

/* ---------- App-Zugang ---------- */

/** Stand fuer den Admin-Bereich, ohne das Secret. */
function spotify_settings(): array
{
    $s = setting('spotify', []);
    $s = is_array($s) ? $s : [];
    return [
        'client_id' => (string) ($s['client_id'] ?? ''),
        'has_secret' => !empty($s['secret']),
        'tested_at' => isset($s['tested_at']) ? (int) $s['tested_at'] : null,
        'test_ok' => (bool) ($s['test_ok'] ?? false),
        'redirect' => spotify_redirect_uri(),
        'users' => spotify_users_count(),
        'users_max' => SPOTIFY_USERS_MAX,
    ];
}

/** Client-ID und Secret, oder null, solange im Admin-Bereich nichts steht. */
function spotify_app(): ?array
{
    $s = setting('spotify', []);
    if (!is_array($s) || empty($s['client_id']) || empty($s['secret'])) {
        return null;
    }
    $secret = unseal((string) $s['secret']);
    return $secret === null || $secret === '' ? null : ['id' => (string) $s['client_id'], 'secret' => $secret];
}

function spotify_configured(): bool
{
    return spotify_app() !== null;
}

/** $secret null behaelt das gespeicherte. Ein neuer Zugang gilt als ungetestet. */
function spotify_save_app(string $clientId, ?string $secret): void
{
    $old = setting('spotify', []);
    $old = is_array($old) ? $old : [];
    setting_set('spotify', [
        'client_id' => $clientId,
        'secret' => $secret !== null ? seal($secret) : ($old['secret'] ?? null),
        'tested_at' => null,
        'test_ok' => false,
    ]);
}

function spotify_mark_tested(bool $ok): void
{
    $s = setting('spotify', []);
    $s = is_array($s) ? $s : [];
    $s['tested_at'] = time();
    $s['test_ok'] = $ok;
    setting_set('spotify', $s);
}

/** Diese Adresse muss im Spotify-Dashboard unter Redirect URIs stehen, Zeichen fuer Zeichen. */
function spotify_redirect_uri(): string
{
    return rtrim(app_url(), '/') . '/spotify/callback';
}

function spotify_users_count(): int
{
    try {
        return (int) (q1('SELECT COUNT(*) AS n FROM spotify_links')['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------- HTTP ---------- */

/**
 * Eine Anfrage an Spotify. Liefert status (0 ohne Antwort), data (JSON oder null), body,
 * retry (Sekunden aus Retry-After) und error. Wirft nie.
 */
function spotify_http(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 6): array
{
    $retry = 0;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => user_agent(false),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_PROTOCOLS => spotify_mock() !== '' ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS,
        CURLOPT_ENCODING => '',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$retry): int {
            if (stripos($line, 'retry-after:') === 0) {
                $retry = max(0, (int) trim(substr($line, 12)));
            }
            return strlen($line);
        },
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body ?? '';
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    $text = is_string($raw) ? $raw : '';
    $data = $text !== '' && $text[0] === '{' ? json_decode($text, true) : null;
    return ['status' => $status, 'data' => is_array($data) ? $data : null, 'body' => $text, 'retry' => $retry, 'error' => $error];
}

/** POST an den Token-Dienst von Spotify, mit Client-ID und Secret. */
function spotify_token_request(array $fields): array
{
    $app = spotify_app();
    if ($app === null) {
        return ['status' => 0, 'data' => null, 'body' => '', 'retry' => 0, 'error' => 'no app'];
    }
    budget_add('spotify', false);
    return spotify_http('POST', spotify_base('accounts') . '/api/token', [
        'Authorization: Basic ' . base64_encode($app['id'] . ':' . $app['secret']),
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], http_build_query($fields), 8);
}

/**
 * Den App-Zugang pruefen: ein Token nur fuer die App (Client Credentials). Das geht ohne
 * ein verbundenes Konto. Liefert ok, status und die Meldung in beiden Sprachen.
 */
function spotify_test_app(): array
{
    $r = spotify_token_request(['grant_type' => 'client_credentials']);
    if ($r['status'] === 200 && !empty($r['data']['access_token'])) {
        return ['ok' => true, 'status' => 200, 'en' => 'Spotify accepts the client ID and secret.', 'de' => 'Spotify nimmt Client-ID und Secret an.'];
    }
    if ($r['status'] === 0) {
        return ['ok' => false, 'status' => 424, 'en' => 'Spotify is not reachable right now.', 'de' => 'Spotify ist gerade nicht erreichbar.'];
    }
    if ($r['status'] === 400 || $r['status'] === 401) {
        return ['ok' => false, 'status' => 422, 'en' => 'Spotify rejects the client ID or the secret. Copy both again from the dashboard.', 'de' => 'Spotify lehnt Client-ID oder Secret ab. Beide noch einmal aus dem Dashboard kopieren.'];
    }
    return ['ok' => false, 'status' => 424, 'en' => 'Spotify answered with HTTP ' . $r['status'] . '.', 'de' => 'Spotify antwortete mit HTTP ' . $r['status'] . '.'];
}

/** Nur einer auf einmal, etwa beim Erneuern eines Tokens, wenn zwei Geraete gleichzeitig fragen. */
function spotify_locked(string $name, callable $fn): mixed
{
    ensure_data_dir();
    $dir = TW_DATA . '/locks';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $fh = @fopen($dir . '/' . md5($name) . '.lock', 'c');
    if ($fh) {
        flock($fh, LOCK_EX);
    }
    try {
        return $fn();
    } finally {
        if ($fh) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

/* ---------- Verbindung eines Kontos ---------- */

function spotify_authorize_url(string $state): string
{
    $app = spotify_app();
    return spotify_base('accounts') . '/authorize?' . http_build_query([
        'client_id' => $app['id'] ?? '',
        'response_type' => 'code',
        'redirect_uri' => spotify_redirect_uri(),
        'scope' => SPOTIFY_SCOPES,
        'state' => $state,
    ]);
}

function spotify_link(int $userId): ?array
{
    try {
        return q1('SELECT * FROM spotify_links WHERE user_id = ?', [$userId]);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Den Code aus der Rueckleitung gegen Token tauschen und das Konto merken. Liefert
 * ['ok' => true] oder ['ok' => false, 'code' => exchange|not_allowed|full|network].
 */
function spotify_connect_finish(int $userId, string $code): array
{
    $old = spotify_link($userId);
    if ($old === null && spotify_users_count() >= SPOTIFY_USERS_MAX) {
        return ['ok' => false, 'code' => 'full'];
    }
    $r = spotify_token_request(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => spotify_redirect_uri()]);
    if ($r['status'] === 0) {
        return ['ok' => false, 'code' => 'network'];
    }
    $t = $r['data'] ?? [];
    if ($r['status'] !== 200 || empty($t['access_token']) || empty($t['refresh_token'])) {
        event_add('SPOTIFY', 'Code exchange failed (HTTP ' . $r['status'] . ')', 'Tausch des Codes fehlgeschlagen (HTTP ' . $r['status'] . ')');
        return ['ok' => false, 'code' => 'exchange'];
    }
    budget_add('spotify', false);
    $me = spotify_http('GET', spotify_base('api') . '/me', ['Authorization: Bearer ' . $t['access_token'], 'Accept: application/json'], null, 6);
    if ($me['status'] === 403) {
        // Im Entwicklungsmodus: das Konto steht nicht unter User Management im Dashboard.
        return ['ok' => false, 'code' => 'not_allowed'];
    }
    $sid = (string) ($me['data']['id'] ?? '');
    if ($me['status'] !== 200 || $sid === '') {
        return ['ok' => false, 'code' => 'exchange'];
    }
    $now = time();
    db_upsert('spotify_links', [
        'user_id' => $userId,
        'spotify_id' => mb_substr($sid, 0, 64),
        'display_name' => mb_substr((string) ($me['data']['display_name'] ?? $sid), 0, 190),
        'refresh_token' => seal((string) $t['refresh_token']),
        'access_token' => seal((string) $t['access_token']),
        'expires_at' => $now + max(60, (int) ($t['expires_in'] ?? 3600)),
        'scope' => mb_substr((string) ($t['scope'] ?? SPOTIFY_SCOPES), 0, 255),
        'created_at' => $old ? (int) $old['created_at'] : $now,
        'updated_at' => $now,
        'error' => null,
        'error_at' => null,
    ], ['user_id'], ['spotify_id' => null, 'display_name' => null, 'refresh_token' => null, 'access_token' => null, 'expires_at' => null, 'scope' => null, 'updated_at' => null, 'error' => null, 'error_at' => null]);
    spotify_forget_cache($userId);
    return ['ok' => true];
}

/** Verbindung trennen: Token weg, Zwischenspeicher des Kontos weg. */
function spotify_unlink(int $userId): void
{
    q('DELETE FROM spotify_links WHERE user_id = ?', [$userId]);
    spotify_forget_cache($userId);
}

function spotify_forget_cache(int $userId): void
{
    q('DELETE FROM cache WHERE k LIKE ?', ['spot:' . $userId . ':%']);
}

/** Nur "Laeuft" vergessen, der naechste Aufruf von spotify_now() fragt Spotify. */
function spotify_forget_now(int $userId): void
{
    q('DELETE FROM cache WHERE k = ?', ['spot:' . $userId . ':now']);
}

function spotify_link_error(int $userId, ?string $code): void
{
    q('UPDATE spotify_links SET error = ?, error_at = ? WHERE user_id = ?', [$code, $code === null ? null : time(), $userId]);
}

/**
 * Ein gueltiges Zugangs-Token, bei Bedarf erneuert. null, wenn das Konto nicht verbunden
 * ist oder Spotify die Erneuerung ablehnt; dann steht der Grund in spotify_links.error.
 */
function spotify_access(int $userId, bool $force = false): ?string
{
    $link = spotify_link($userId);
    if ($link === null) {
        return null;
    }
    if (!$force && (int) $link['expires_at'] > time() + 30 && !empty($link['access_token'])) {
        $t = unseal((string) $link['access_token']);
        if ($t !== null && $t !== '') {
            return $t;
        }
    }
    return spotify_locked('spotify-token-' . $userId, static function () use ($userId, $force, $link): ?string {
        // Hat ein anderer Abruf inzwischen erneuert, gilt dessen Token.
        $fresh = spotify_link($userId);
        if ($fresh === null) {
            return null;
        }
        if ((int) $fresh['updated_at'] > (int) $link['updated_at'] && (int) $fresh['expires_at'] > time() + 30) {
            $t = unseal((string) $fresh['access_token']);
            if ($t !== null && $t !== '') {
                return $t;
            }
        }
        $refresh = unseal((string) $fresh['refresh_token']);
        if ($refresh === null || $refresh === '') {
            spotify_link_error($userId, 'revoked');
            return null;
        }
        $r = spotify_token_request(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]);
        $t = $r['data'] ?? [];
        if ($r['status'] === 200 && !empty($t['access_token'])) {
            $set = [
                'access_token' => seal((string) $t['access_token']),
                'expires_at' => time() + max(60, (int) ($t['expires_in'] ?? 3600)),
                'updated_at' => time(),
                'error' => null,
                'error_at' => null,
            ];
            // Manchmal kommt ein neues Erneuerungs-Token mit, dann gilt nur noch das.
            if (!empty($t['refresh_token'])) {
                $set['refresh_token'] = seal((string) $t['refresh_token']);
            }
            db_update('spotify_links', $set, 'user_id = ?', [$userId]);
            return (string) $t['access_token'];
        }
        if ($r['status'] === 400 && ($t['error'] ?? '') === 'invalid_grant') {
            // Zugriff in Spotify widerrufen oder Passwort geaendert: neu verbinden.
            spotify_link_error($userId, 'revoked');
            event_add('SPOTIFY', 'Access for an account was revoked, it has to connect again', 'Zugang eines Kontos widerrufen, es muss neu verbinden');
        }
        return null;
    });
}

/**
 * GET auf die Web API im Namen eines Kontos. code: ok, none (204), not_allowed (403),
 * revoked, limited (429 oder Pause danach), network, error. Ein 401 erneuert das Token
 * einmal und fragt noch einmal.
 */
function spotify_api(int $userId, string $path, int $timeout = 5): array
{
    if (cache_get('spotpause') !== null) {
        return ['status' => 429, 'data' => null, 'code' => 'limited'];
    }
    $token = spotify_access($userId);
    if ($token === null) {
        $link = spotify_link($userId);
        return ['status' => 401, 'data' => null, 'code' => ($link['error'] ?? '') === 'revoked' ? 'revoked' : 'error'];
    }
    $r = null;
    for ($try = 0; $try < 2; $try++) {
        budget_add('spotify', false);
        $r = spotify_http('GET', spotify_base('api') . $path, ['Authorization: Bearer ' . $token, 'Accept: application/json'], null, $timeout);
        if ($r['status'] !== 401 || $try > 0) {
            break;
        }
        $token = spotify_access($userId, true);
        if ($token === null) {
            // Neu verbinden nur, wenn Spotify die Erneuerung wirklich abgelehnt hat, nicht bei
            // einem Netzfehler oder einer Stoerung beim Token-Dienst.
            $link = spotify_link($userId);
            return ['status' => 401, 'data' => null, 'code' => ($link['error'] ?? '') === 'revoked' ? 'revoked' : 'error'];
        }
    }
    $status = (int) $r['status'];
    if ($status === 200) {
        return ['status' => 200, 'data' => $r['data'] ?? [], 'code' => 'ok'];
    }
    if ($status === 204) {
        return ['status' => 204, 'data' => null, 'code' => 'none'];
    }
    if ($status === 403) {
        spotify_link_error($userId, 'not_allowed');
        return ['status' => 403, 'data' => $r['data'], 'code' => 'not_allowed'];
    }
    if ($status === 429) {
        // Spotify bremst die ganze App, nicht nur dieses Konto. So lange fragt niemand.
        cache_put('spotpause', 1, max(5, min(600, (int) $r['retry'])));
        event_add('SPOTIFY', 'Spotify slowed us down for ' . max(5, (int) $r['retry']) . ' s', 'Spotify bremst fuer ' . max(5, (int) $r['retry']) . ' s');
        return ['status' => 429, 'data' => null, 'code' => 'limited'];
    }
    if ($status === 0) {
        return ['status' => 0, 'data' => null, 'code' => 'network'];
    }
    event_add('SPOTIFY', 'Spotify answered ' . $path . ' with HTTP ' . $status, 'Spotify antwortete auf ' . $path . ' mit HTTP ' . $status);
    return ['status' => $status, 'data' => $r['data'], 'code' => 'error'];
}

/* ---------- Was laeuft ---------- */

/** Bild fuer das Cover: das kleinste ab 250 Pixeln, sonst das groesste. */
function spotify_image(array $images): string
{
    $best = '';
    $bestW = PHP_INT_MAX;
    $largest = '';
    $largestW = -1;
    foreach ($images as $im) {
        if (!is_array($im) || empty($im['url'])) {
            continue;
        }
        $w = (int) ($im['width'] ?? 0);
        if ($w >= 250 && $w < $bestW) {
            $best = (string) $im['url'];
            $bestW = $w;
        }
        if ($w > $largestW) {
            $largest = (string) $im['url'];
            $largestW = $w;
        }
    }
    return $best !== '' ? $best : $largest;
}

/** Titel oder Folge in der Form, die der Modus braucht. null bei etwas anderem. */
function spotify_item(mixed $it): ?array
{
    if (!is_array($it) || !isset($it['name'])) {
        return null;
    }
    $type = (string) ($it['type'] ?? 'track');
    $id = (string) ($it['id'] ?? '');
    if ($id === '') {
        // Lokale Dateien haben keine ID, nur eine URI.
        $id = 'local-' . substr(md5((string) ($it['uri'] ?? $it['name'])), 0, 12);
    }
    if ($type === 'episode') {
        $show = is_array($it['show'] ?? null) ? $it['show'] : [];
        return [
            'id' => $id,
            'kind' => 'episode',
            'title' => (string) $it['name'],
            'artists' => [(string) ($show['name'] ?? '')],
            'album' => ['id' => '', 'name' => (string) ($show['publisher'] ?? ''), 'year' => substr((string) ($it['release_date'] ?? ''), 0, 4), 'total' => 0, 'type' => 'show'],
            'no' => 0,
            'disc' => 1,
            'dur' => max(0, (int) ($it['duration_ms'] ?? 0)),
            'image' => spotify_image(is_array($it['images'] ?? null) && $it['images'] ? $it['images'] : (is_array($show['images'] ?? null) ? $show['images'] : [])),
            'url' => (string) ($it['external_urls']['spotify'] ?? ''),
        ];
    }
    $al = is_array($it['album'] ?? null) ? $it['album'] : [];
    $artists = [];
    foreach (is_array($it['artists'] ?? null) ? $it['artists'] : [] as $a) {
        if (is_array($a) && ($a['name'] ?? '') !== '') {
            $artists[] = (string) $a['name'];
        }
    }
    return [
        'id' => $id,
        'kind' => 'track',
        'title' => (string) $it['name'],
        'artists' => $artists ?: [''],
        'album' => [
            'id' => (string) ($al['id'] ?? ''),
            'name' => (string) ($al['name'] ?? ''),
            'year' => substr((string) ($al['release_date'] ?? ''), 0, 4),
            'total' => (int) ($al['total_tracks'] ?? 0),
            'type' => (string) ($al['album_type'] ?? ''),
        ],
        'no' => (int) ($it['track_number'] ?? 0),
        'disc' => (int) ($it['disc_number'] ?? 1),
        'dur' => max(0, (int) ($it['duration_ms'] ?? 0)),
        'image' => spotify_image(is_array($al['images'] ?? null) ? $al['images'] : []),
        'url' => (string) ($it['external_urls']['spotify'] ?? ''),
    ];
}

/**
 * Was gerade laeuft, fuer ein Konto. null, wenn Spotify nicht antwortet und kein Stand der
 * letzten Minute da ist. Sonst ein Array mit state:
 *   play, pause  dazu item, pos (ms zum Zeitpunkt at), at (ms seit 1970), start (Beginn des
 *                Titels, stabil ueber mehrere Abfragen), since (letzter Wechsel von Pause und Weiter)
 *   none         nichts laeuft
 *   ad           Werbung
 *   not_allowed  Konto nicht freigeschaltet (Entwicklungsmodus)
 *   revoked      Zugang widerrufen, neu verbinden
 *   unlinked     kein Spotify-Konto verbunden
 */
function spotify_now(int $userId): ?array
{
    if (spotify_link($userId) === null) {
        return ['state' => 'unlinked'];
    }
    $key = 'spot:' . $userId . ':now';
    $v = cache_remember($key, 'spotify', static function () use ($userId): ?array {
        $t0 = microtime(true);
        $r = spotify_api($userId, '/me/player/currently-playing?additional_types=episode', 5);
        // Die Position gilt ungefaehr fuer die Mitte der Anfrage.
        $at = (int) round((($t0 + microtime(true)) / 2) * 1000);
        if ($r['code'] === 'none') {
            return [['state' => 'none', 'at' => $at], SPOTIFY_NOW_TTL];
        }
        if ($r['code'] === 'not_allowed' || $r['code'] === 'revoked') {
            return [['state' => $r['code'], 'at' => $at], 30];
        }
        if ($r['code'] !== 'ok') {
            return null;
        }
        $d = $r['data'] ?? [];
        if (($d['currently_playing_type'] ?? '') === 'ad') {
            return [['state' => 'ad', 'at' => $at], SPOTIFY_NOW_TTL];
        }
        $item = spotify_item($d['item'] ?? null);
        if ($item === null) {
            return [['state' => 'none', 'at' => $at], SPOTIFY_NOW_TTL];
        }
        $state = [
            'state' => !empty($d['is_playing']) ? 'play' : 'pause',
            'item' => $item,
            'pos' => max(0, min($item['dur'], (int) ($d['progress_ms'] ?? 0))),
            'at' => $at,
        ];
        return [$state, spotify_now_ttl($state)];
    }, 10);
    $last = 'spot:' . $userId . ':last';
    if ($v === null) {
        // Spotify antwortet gerade nicht: der letzte Stand gilt noch eine Minute.
        $old = cache_get($last);
        return is_array($old) ? $old : null;
    }
    $v = spotify_stable($userId, $v);
    if (isset($v['item'])) {
        cache_put($last, $v, 60);
    }
    return $v;
}

/**
 * So lange gilt "Laeuft" im Zwischenspeicher: fuenf Sekunden, aber nie ueber das Ende des
 * Titels hinaus. Sonst rechnete ein Frame kurz nach dem Wechsel noch mit dem alten Titel,
 * verlaengerte ihn und holte das Panel mit einem Karussell vom neuen zurueck (26.09.2026).
 */
function spotify_now_ttl(array $state): int
{
    if (($state['state'] ?? '') !== 'play' || !isset($state['item'])) {
        return SPOTIFY_NOW_TTL;
    }
    $left = (int) $state['item']['dur'] - (int) $state['pos'];
    return max(1, min(SPOTIFY_NOW_TTL, (int) ceil($left / 1000)));
}

/**
 * Beginn des Titels stabil halten. Jede Abfrage schaetzt ihn neu, mit ein paar hundert
 * Millisekunden Unterschied. Die Seiten haengen daran, und das Geraet sieht an einem
 * neuen Beginn eine neue Seite. Erst ab 2,5 Sekunden Abweichung gilt ein Sprung im Titel.
 */
function spotify_stable(int $userId, array $v): array
{
    if (!isset($v['item'])) {
        return $v;
    }
    $id = (string) $v['item']['id'];
    $playing = $v['state'] === 'play';
    $at = (int) $v['at'];
    $pk = 'spot:' . $userId . ':play';
    $play = cache_get($pk);
    if (!is_array($play) || (bool) ($play['play'] ?? false) !== $playing) {
        $play = ['play' => $playing, 'at' => $at];
        cache_put($pk, $play, 43200);
    }
    $v['since'] = (int) $play['at'];
    if (!$playing) {
        $v['start'] = $at - (int) $v['pos'];
        return $v;
    }
    $est = $at - (int) $v['pos'];
    $sk = 'spot:' . $userId . ':start';
    $old = cache_get($sk);
    if (is_array($old) && ($old['id'] ?? '') === $id && abs((int) $old['start'] - $est) < 2500) {
        $v['start'] = (int) $old['start'];
    } else {
        $v['start'] = $est;
        cache_put($sk, ['id' => $id, 'start' => $est], 43200);
    }
    return $v;
}

/**
 * Die naechsten Titel, hoechstens sechs. Leer, wenn Spotify nichts liefert. null, wenn Spotify
 * schon einen anderen Titel spielt als $currentId: dann ist "Laeuft" veraltet, und die Liste
 * liegt unter dem Titel, der wirklich laeuft. Vorher stand sie unter dem alten, und das Panel
 * kuendigte den uebernaechsten Titel als naechsten an.
 */
function spotify_queue(int $userId, string $currentId): ?array
{
    $key = 'spot:' . $userId . ':queue:';
    $v = cache_remember($key . md5($currentId), 'spotify', static function () use ($userId, $currentId, $key): ?array {
        $r = spotify_api($userId, '/me/player/queue', 5);
        if ($r['code'] !== 'ok') {
            return null;
        }
        $list = [];
        foreach (is_array($r['data']['queue'] ?? null) ? $r['data']['queue'] : [] as $it) {
            $n = spotify_item($it);
            if ($n !== null) {
                $list[] = $n;
            }
            if (count($list) >= 6) {
                break;
            }
        }
        $playing = (string) (spotify_item($r['data']['currently_playing'] ?? null)['id'] ?? '');
        if ($playing !== '' && $playing !== $currentId) {
            cache_put($key . md5($playing), $list, SPOTIFY_QUEUE_TTL);
            return [['stale' => $playing], 3];
        }
        return [$list, SPOTIFY_QUEUE_TTL];
    }, 20);
    if (is_array($v) && isset($v['stale'])) {
        return null;
    }
    return is_array($v) ? $v : [];
}

/** Album mit den Laengen aller Titel, fuer die Albumansicht. */
function spotify_album(int $userId, string $albumId): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{8,40}$/', $albumId)) {
        return null;
    }
    $v = cache_remember('spotalb:' . $albumId, 'spotify', static function () use ($userId, $albumId): ?array {
        $r = spotify_api($userId, '/albums/' . $albumId, 5);
        if ($r['code'] !== 'ok') {
            return null;
        }
        $d = $r['data'] ?? [];
        $tracks = [];
        foreach (is_array($d['tracks']['items'] ?? null) ? $d['tracks']['items'] : [] as $t) {
            if (is_array($t)) {
                $tracks[] = ['id' => (string) ($t['id'] ?? ''), 'no' => (int) ($t['track_number'] ?? 0), 'disc' => (int) ($t['disc_number'] ?? 1), 'dur' => max(0, (int) ($t['duration_ms'] ?? 0))];
            }
        }
        $artists = [];
        foreach (is_array($d['artists'] ?? null) ? $d['artists'] : [] as $a) {
            if (is_array($a) && ($a['name'] ?? '') !== '') {
                $artists[] = (string) $a['name'];
            }
        }
        return [[
            'name' => (string) ($d['name'] ?? ''),
            'artists' => $artists ?: [''],
            'year' => substr((string) ($d['release_date'] ?? ''), 0, 4),
            'total' => (int) ($d['total_tracks'] ?? count($tracks)),
            'tracks' => $tracks,
        ], SPOTIFY_ALBUM_TTL];
    }, 300);
    return is_array($v) ? $v : null;
}

/* ---------- Cover ---------- */

/** Nur Bilder von Spotify selbst. */
function spotify_image_url_ok(string $url): bool
{
    $mock = spotify_mock();
    if ($mock !== '' && str_starts_with($url, $mock . '/img/')) {
        return true;
    }
    $host = (string) parse_url($url, PHP_URL_HOST);
    return str_starts_with($url, 'https://') && (str_ends_with($host, '.scdn.co') || str_ends_with($host, '.spotifycdn.com'));
}

/**
 * Cover in allen Groessen fuer das Panel, dazu die Leitfarbe. Einmal je Bild gerechnet und
 * einen Tag gemerkt. null, wenn es kein Bild gibt oder es sich nicht lesen laesst.
 */
function spotify_cover(string $url): ?array
{
    if ($url === '' || !spotify_image_url_ok($url)) {
        return null;
    }
    $v = cache_remember('spotcov:' . sha1($url), 'spotifyimg', static function () use ($url): ?array {
        budget_add('spotifyimg', false);
        $r = spotify_http('GET', $url, ['Accept: image/jpeg, image/png, image/webp'], null, 6);
        if ($r['status'] !== 200 || $r['body'] === '' || strlen($r['body']) > 2_000_000) {
            return null;
        }
        $c = cover_bitmaps($r['body']);
        return $c === null ? null : [$c, SPOTIFY_COVER_TTL];
    }, 120);
    return is_array($v) ? $v : null;
}

/**
 * Ein Bild in Bitmaps fuer den Zeichenbefehl bmp: je Groesse w, h, p (bis 15 Farben), d
 * (Base64, vier Bit je Punkt), dazu lead (kraeftigste Farbe fuer "Farbe aus dem Cover").
 * Verkleinert wird ueber das Vierfache, dann gemittelt. Helle Cover werden gedaempft,
 * sonst leuchtet ein weisses Cover als grelles Quadrat neben dem Text. Die aeusserste LED
 * jeder Ecke bleibt aus: Spotify verlangt runde Ecken.
 */
function cover_bitmaps(string $bytes, array $sizes = SPOTIFY_COVER_SIZES): ?array
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || $info[0] < 8 || $info[1] < 8 || $info[0] > 4000 || $info[1] > 4000) {
        return null;
    }
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return null;
    }
    $mean = cover_mean_luma(cover_shrink($src, 48));
    $gain = max(COVER_GAIN_MIN, min(COVER_GAIN_MAX, COVER_TARGET_MEAN / max(0.01, $mean)));
    $out = ['lead' => null];
    foreach ($sizes as $s) {
        $b = cover_bitmap($src, (int) $s, $gain);
        if ((int) $s === 48 || ($out['lead'] === null && (int) $s === 64)) {
            $out['lead'] = cover_lead($b) ?? $out['lead'];
        }
        unset($b['n']);
        $out[(string) $s] = $b;
    }
    return $out;
}

function cover_shrink(GdImage $src, int $size): GdImage
{
    $mid = imagecreatetruecolor($size * 4, $size * 4);
    imagecopyresampled($mid, $src, 0, 0, 0, 0, $size * 4, $size * 4, imagesx($src), imagesy($src));
    $img = imagecreatetruecolor($size, $size);
    imagecopyresampled($img, $mid, 0, 0, 0, 0, $size, $size, $size * 4, $size * 4);
    return $img;
}

/** Mittlere Helligkeit 0 bis 1 nach Rec. 709. */
function cover_mean_luma(GdImage $img): float
{
    $n = imagesx($img);
    $sum = 0.0;
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            $c = imagecolorat($img, $x, $y);
            $sum += (0.2126 * (($c >> 16) & 255) + 0.7152 * (($c >> 8) & 255) + 0.0722 * ($c & 255)) / 255;
        }
    }
    return $sum / max(1, $n * $n);
}

function cover_bitmap(GdImage $src, int $size, float $gain): array
{
    $img = cover_shrink($src, $size);
    $off = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $c = imagecolorat($img, $x, $y);
            $r = (($c >> 16) & 255) * $gain;
            $g = (($c >> 8) & 255) * $gain;
            $b = ($c & 255) * $gain;
            $m = max($r, $g, $b);
            if ($m <= COVER_OFF_MAX) {
                $off[$y * $size + $x] = true;
                imagesetpixel($img, $x, $y, 0);
                continue;
            }
            if ($m < COVER_LIFT_MIN) {
                $f = COVER_LIFT_MIN / $m;
                $r *= $f;
                $g *= $f;
                $b *= $f;
            }
            imagesetpixel($img, $x, $y, ((int) min(255, round($r)) << 16) | ((int) min(255, round($g)) << 8) | (int) min(255, round($b)));
        }
    }
    foreach ([0, $size - 1, ($size - 1) * $size, $size * $size - 1] as $i) {
        $off[$i] = true;
    }
    $pal = imagecreatetruecolor($size, $size);
    imagecopy($pal, $img, 0, 0, 0, 0, $size, $size);
    imagetruecolortopalette($pal, false, 15);
    $colours = [];
    $count = [];
    $map = [];
    for ($i = 0, $n = imagecolorstotal($pal); $i < $n; $i++) {
        $c = imagecolorsforindex($pal, $i);
        if (max($c['red'], $c['green'], $c['blue']) <= COVER_OFF_MAX) {
            $map[$i] = 0;
            continue;
        }
        $hex = sprintf('%02X%02X%02X', $c['red'], $c['green'], $c['blue']);
        $k = array_search($hex, $colours, true);
        if ($k === false) {
            $colours[] = $hex;
            $count[] = 0;
            $k = count($colours) - 1;
        }
        $map[$i] = $k + 1;
    }
    $nib = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $i = $y * $size + $x;
            $v = isset($off[$i]) ? 0 : ($map[imagecolorat($pal, $x, $y)] ?? 0);
            if ($v) {
                $count[$v - 1]++;
            }
            $nib[] = $v;
        }
    }
    $bytes = '';
    for ($i = 0, $n = count($nib); $i < $n; $i += 2) {
        $bytes .= chr(($nib[$i] << 4) | ($nib[$i + 1] ?? 0));
    }
    return ['w' => $size, 'h' => $size, 'p' => $colours, 'd' => base64_encode($bytes), 'n' => $count];
}

/** Leitfarbe: die kraeftigste Farbe mit mindestens 2 Prozent der Flaeche, auf volle Leuchtkraft gezogen. */
function cover_lead(array $bmp): ?string
{
    $total = array_sum($bmp['n']) ?: 1;
    $best = null;
    $score = 0.0;
    foreach ($bmp['p'] as $i => $hex) {
        $share = ($bmp['n'][$i] ?? 0) / $total;
        if ($share < 0.02) {
            continue;
        }
        [$r, $g, $b] = array_map(static fn(string $h): float => hexdec($h) / 255, str_split($hex, 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $sat = $max > 0 ? ($max - $min) / $max : 0;
        if ($sat < 0.35) {
            continue;
        }
        $s = $sat * sqrt($share);
        if ($s > $score) {
            $score = $s;
            $best = [$r, $g, $b];
        }
    }
    if ($best === null) {
        return null;
    }
    $max = max($best);
    return strtoupper(implode('', array_map(static fn(float $v): string => sprintf('%02X', (int) round(min(1, $v / $max) * 255)), $best)));
}

/* ---------- Geraet und Revision ---------- */

/** Zeigt dieses Geraet Spotify, im Wechsel oder mit Vorrang? Nur dann wird gefragt. */
function spotify_involved(array $settings): bool
{
    return ($settings['mode'] ?? '') === 'spotify'
        || in_array('spotify', is_array($settings['rotation'] ?? null) ? $settings['rotation'] : [], true)
        || !empty($settings['spotify']['first']);
}

/**
 * Teil der Revision: aendert sich bei einem anderen Titel, bei Pause oder Weiter und bei
 * einem Sprung im Titel. So holt das Geraet nach einem Wechsel am Handy in zwei bis vier
 * Sekunden einen neuen Frame.
 */
function spotify_rev(int $userId): string
{
    $v = spotify_now($userId);
    if ($v === null) {
        return '-';
    }
    if (!isset($v['item'])) {
        return (string) $v['state'];
    }
    $mark = $v['state'] === 'play' ? (int) $v['start'] : (int) $v['pos'];
    return substr(md5($v['item']['id'] . ':' . $v['state'] . ':' . $mark), 0, 8);
}
