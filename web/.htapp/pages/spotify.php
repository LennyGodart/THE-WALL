<?php
/*
 * Spotify verbinden. Der Knopf auf der Geraeteseite schickt ein Formular hierher, der
 * Server merkt sich einen Einmal-Wert (state, 10 Minuten, nur fuer dieses Konto) und
 * leitet zu Spotify weiter. Spotify leitet mit einem Code zurueck, der Server tauscht ihn
 * gegen Token und geht zur Geraeteseite zurueck. Das Ergebnis steht dort als Hinweis.
 */

declare(strict_types=1);

function action_spotify_connect(array $params = []): void
{
    $user = require_user();
    csrf_check();
    $deviceId = is_string($_POST['device'] ?? null) ? (int) $_POST['device'] : 0;
    $back = $deviceId > 0 && device_for_user($deviceId, (int) $user['id']) !== null ? '/device/' . $deviceId : '/device';
    if (!spotify_configured()) {
        redirect($back . '?spotify=setup#spotify');
    }
    if (!rl_allow('spotifyconnect:' . $user['id'], 10, 600)) {
        redirect($back . '?spotify=slow#spotify');
    }
    $link = spotify_link((int) $user['id']);
    if ($link === null && spotify_users_count() >= SPOTIFY_USERS_MAX) {
        redirect($back . '?spotify=full#spotify');
    }
    $state = token_create('spotify', ['user_id' => (int) $user['id'], 'device_id' => $deviceId > 0 ? $deviceId : null]);
    $url = spotify_authorize_url($state);
    // Nur zu Spotify selbst, nie woandershin.
    if (!str_starts_with($url, spotify_base('accounts') . '/authorize?')) {
        throw new HttpError(500);
    }
    header('Cache-Control: ' . NO_STORE);
    header('Location: ' . $url, true, 303);
    exit;
}

function page_spotify_callback(array $params = []): void
{
    // Link-Pruefer fragen oft nur mit HEAD: der Einmal-Wert bleibt dann unberuehrt.
    if (req_method() === 'HEAD') {
        return;
    }
    $user = require_user();
    // Nur Zeichenketten: ein Feld wie state[]=x ergaebe sonst einen Fehler statt einer Absage.
    $get = static fn(string $k): string => is_string($_GET[$k] ?? null) ? $_GET[$k] : '';
    $state = $get('state');
    $tok = $state !== '' ? token_find('spotify', $state) : null;
    // Der Einmal-Wert muss zu diesem Konto gehoeren, sonst koennte ein fremder Link ein
    // fremdes Spotify-Konto unterschieben.
    if ($tok === null || (int) $tok['user_id'] !== (int) $user['id'] || !token_consume((int) $tok['id'])) {
        redirect('/device?spotify=expired#spotify');
    }
    $deviceId = (int) ($tok['device_id'] ?? 0);
    $back = $deviceId > 0 && device_for_user($deviceId, (int) $user['id']) !== null ? '/device/' . $deviceId : '/device';
    $error = $get('error');
    if ($error !== '') {
        redirect($back . '?spotify=' . ($error === 'access_denied' ? 'denied' : 'failed') . '#spotify');
    }
    $code = $get('code');
    if ($code === '' || strlen($code) > 1024) {
        redirect($back . '?spotify=failed#spotify');
    }
    $r = spotify_connect_finish((int) $user['id'], $code);
    redirect($back . '?spotify=' . ($r['ok'] ? 'connected' : $r['code']) . '#spotify');
}
