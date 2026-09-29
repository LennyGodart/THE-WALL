<?php
/*
 * Nachbildung von Spotify fuer lokale Tests: Anmeldung, Token, "Laeuft gerade",
 * Warteschlange, Album, Profil und Bilder. Drei erfundene Titel mit 40, 35 und 50 Sekunden,
 * damit Ansage, Walze und Karussell schnell zu sehen sind. Die Bilder sind gerechnete Muster.
 *
 *   php -d extension=gd -S 127.0.0.1:8766 tools/spotify-mock.php
 *   TW_ENV=dev TW_SPOTIFY_MOCK=http://127.0.0.1:8766 php -S 127.0.0.1:8765 -t web tools/dev-router.php
 *
 * Steuern im Browser oder mit curl:
 *   /control?mode=play|pause|none|forbidden|limited|expire   Zustand
 *   /control?skip=1                                          zum naechsten Titel springen
 *   /control?deny=1                                          naechste Anmeldung abbrechen
 *   /control?delay=1200                                      Warteschlange antwortet so viele
 *                                                            ms spaeter, wie die echte oft
 *   /control?early=1500                                      jeder Titel meldet sich so viele ms
 *                                                            laenger, als er laeuft: der naechste
 *                                                            kommt vor dem geplanten Ende
 */

declare(strict_types=1);

const MOCK_TRACKS = [
    ['id' => 'mock0000000000000001', 'name' => 'Signal Fire', 'artists' => ['Mock Orchestra'], 'dur' => 40000, 'img' => 1],
    ['id' => 'mock0000000000000002', 'name' => 'An Unreasonably Long Song Title For Scrolling', 'artists' => ['The Test Cases', 'Loopback'], 'dur' => 35000, 'img' => 2],
    ['id' => 'mock0000000000000003', 'name' => 'Quiet Harbour', 'artists' => ['Mock Orchestra'], 'dur' => 50000, 'img' => 1],
];

$file = sys_get_temp_dir() . '/thewall-spotify-mock.json';
$state = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
$state += ['mode' => 'play', 'base' => (int) (microtime(true) * 1000), 'pausedAt' => 0, 'deny' => false, 'expire' => false, 'delay' => 0, 'early' => 0];
$save = static function () use (&$state, $file): void {
    file_put_contents($file, json_encode($state));
};
$json = static function (int $status, mixed $data, array $headers = []): void {
    http_response_code($status);
    foreach ($headers as $h) {
        header($h);
    }
    if ($data !== null) {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
};

/** Welcher Titel laeuft und wie weit: aus der Zeit seit base, die drei Titel im Kreis. */
function mock_position(array $state): array
{
    $total = array_sum(array_column(MOCK_TRACKS, 'dur'));
    $now = $state['mode'] === 'pause' && $state['pausedAt'] ? (int) $state['pausedAt'] : (int) (microtime(true) * 1000);
    $t = ($now - (int) $state['base']) % $total;
    foreach (MOCK_TRACKS as $i => $tr) {
        if ($t < $tr['dur']) {
            return [$i, $t];
        }
        $t -= $tr['dur'];
    }
    return [0, 0];
}

function mock_item(int $i, int $early = 0): array
{
    $tr = MOCK_TRACKS[$i % count(MOCK_TRACKS)];
    $host = 'http://' . $_SERVER['HTTP_HOST'];
    return [
        'type' => 'track',
        'id' => $tr['id'],
        'name' => $tr['name'],
        'duration_ms' => $tr['dur'] + $early,
        'track_number' => $i % count(MOCK_TRACKS) + 1,
        'disc_number' => 1,
        'is_local' => false,
        'artists' => array_map(static fn(string $n): array => ['name' => $n], $tr['artists']),
        'album' => [
            'id' => 'mockalbum00000000001',
            'name' => 'Mock Sessions',
            'album_type' => 'album',
            'release_date' => '2026-09-24',
            'total_tracks' => count(MOCK_TRACKS),
            'images' => [['url' => $host . '/img/' . $tr['img'] . '.png', 'width' => 300, 'height' => 300]],
        ],
        'external_urls' => ['spotify' => 'https://open.spotify.com/track/' . $tr['id']],
    ];
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/control') {
    if (isset($_GET['mode'])) {
        $mode = (string) $_GET['mode'];
        if ($mode === 'expire') {
            $state['expire'] = true;
        } else {
            if ($mode === 'pause' && $state['mode'] !== 'pause') {
                $state['pausedAt'] = (int) (microtime(true) * 1000);
            }
            if ($mode === 'play' && $state['mode'] === 'pause' && $state['pausedAt']) {
                $state['base'] += (int) (microtime(true) * 1000) - (int) $state['pausedAt'];
                $state['pausedAt'] = 0;
            }
            $state['mode'] = $mode;
        }
    }
    if (isset($_GET['skip'])) {
        [$i, $t] = mock_position($state);
        $state['base'] -= MOCK_TRACKS[$i]['dur'] - $t;
    }
    if (isset($_GET['deny'])) {
        $state['deny'] = true;
    }
    if (isset($_GET['delay'])) {
        $state['delay'] = max(0, min(5000, (int) $_GET['delay']));
    }
    if (isset($_GET['early'])) {
        $state['early'] = max(0, min(10000, (int) $_GET['early']));
    }
    $save();
    [$i, $t] = mock_position($state);
    $json(200, ['mode' => $state['mode'], 'track' => MOCK_TRACKS[$i]['name'], 'pos_ms' => $t]);
    return true;
}

if ($path === '/accounts/authorize') {
    $back = (string) ($_GET['redirect_uri'] ?? '');
    $q = ['state' => (string) ($_GET['state'] ?? '')];
    if ($state['deny']) {
        $q['error'] = 'access_denied';
        $state['deny'] = false;
        $save();
    } else {
        $q['code'] = 'mockcode' . bin2hex(random_bytes(4));
    }
    header('Location: ' . $back . '?' . http_build_query($q), true, 302);
    return true;
}

if ($path === '/accounts/api/token' && $method === 'POST') {
    if (!str_starts_with((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'Basic ')) {
        $json(401, ['error' => 'invalid_client']);
        return true;
    }
    $grant = (string) ($_POST['grant_type'] ?? '');
    if ($grant === 'client_credentials') {
        $json(200, ['access_token' => 'mockapp', 'token_type' => 'Bearer', 'expires_in' => 3600]);
    } elseif ($grant === 'authorization_code') {
        $json(200, ['access_token' => 'mockaccess1', 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => 'mockrefresh', 'scope' => 'user-read-currently-playing user-read-playback-state']);
    } elseif ($grant === 'refresh_token') {
        $json(200, ['access_token' => 'mockaccess' . random_int(2, 999), 'token_type' => 'Bearer', 'expires_in' => 3600]);
    } else {
        $json(400, ['error' => 'unsupported_grant_type']);
    }
    return true;
}

if (str_starts_with($path, '/v1/')) {
    if (!str_starts_with((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'Bearer ')) {
        $json(401, ['error' => ['status' => 401, 'message' => 'No token provided']]);
        return true;
    }
    if ($state['expire']) {
        $state['expire'] = false;
        $save();
        $json(401, ['error' => ['status' => 401, 'message' => 'The access token expired']]);
        return true;
    }
    if ($state['mode'] === 'forbidden') {
        $json(403, ['error' => ['status' => 403, 'message' => 'User not registered in the Developer Dashboard']]);
        return true;
    }
    if ($state['mode'] === 'limited') {
        $json(429, ['error' => ['status' => 429, 'message' => 'API rate limit exceeded']], ['Retry-After: 7']);
        return true;
    }
    if ($path === '/v1/me') {
        $json(200, ['id' => 'mocklistener', 'display_name' => 'Mock Listener']);
        return true;
    }
    if ($path === '/v1/me/player/currently-playing') {
        if ($state['mode'] === 'none') {
            $json(204, null);
            return true;
        }
        [$i, $t] = mock_position($state);
        $json(200, ['timestamp' => (int) (microtime(true) * 1000), 'progress_ms' => $t, 'is_playing' => $state['mode'] !== 'pause', 'currently_playing_type' => 'track', 'item' => mock_item($i, (int) $state['early'])]);
        return true;
    }
    if ($path === '/v1/me/player/queue') {
        if ($state['delay'] > 0) {
            usleep((int) $state['delay'] * 1000);
        }
        [$i] = mock_position($state);
        $e = (int) $state['early'];
        $json(200, ['currently_playing' => mock_item($i, $e), 'queue' => [mock_item($i + 1, $e), mock_item($i + 2, $e), mock_item($i + 3, $e)]]);
        return true;
    }
    if (str_starts_with($path, '/v1/albums/')) {
        $json(200, [
            'id' => 'mockalbum00000000001', 'name' => 'Mock Sessions', 'release_date' => '2026-09-24', 'total_tracks' => count(MOCK_TRACKS),
            'artists' => [['name' => 'Mock Orchestra']],
            'tracks' => ['items' => array_map(static fn(array $tr, int $i): array => ['id' => $tr['id'], 'track_number' => $i + 1, 'disc_number' => 1, 'duration_ms' => $tr['dur']], MOCK_TRACKS, array_keys(MOCK_TRACKS))],
        ]);
        return true;
    }
    $json(404, ['error' => ['status' => 404, 'message' => 'Not found']]);
    return true;
}

if (preg_match('#^/img/(\d)\.png$#', $path, $m)) {
    // Gerechnete Muster statt echter Cover: Kreise und Streifen in zwei Farbschemata.
    $im = imagecreatetruecolor(300, 300);
    [$bg, $fg, $ac] = $m[1] === '1' ? [[18, 30, 60], [255, 170, 0], [53, 214, 255]] : [[60, 16, 40], [61, 224, 124], [242, 244, 245]];
    imagefilledrectangle($im, 0, 0, 299, 299, imagecolorallocate($im, ...$bg));
    imagefilledellipse($im, 110, 120, 170, 170, imagecolorallocate($im, ...$fg));
    for ($k = 0; $k < 6; $k++) {
        imagefilledrectangle($im, 170, 40 + $k * 40, 280, 52 + $k * 40, imagecolorallocate($im, ...$ac));
    }
    header('Content-Type: image/png');
    imagepng($im);
    return true;
}

$json(404, ['error' => 'not found']);
return true;
