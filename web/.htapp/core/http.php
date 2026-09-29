<?php
/*
 * Anfrage und Antwort. Jede HTML-Antwort ist private und no-store: Cloudflare
 * speichert auf dieser Domain auch HTML zwischen, und eine gespeicherte
 * Geraeteseite ginge sonst an den naechsten Besucher.
 */

declare(strict_types=1);

/* no-transform: Cloudflare spritzt sonst Web Analytics und JavaScript Detections
   in jede HTML-Antwort. Beides verbietet CLAUDE.md, und die CSP blockt es ohnehin. */
const NO_STORE = 'private, no-store, max-age=0, no-transform';

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly ?string $messageDe = null)
    {
        parent::__construct($message !== '' ? $message : 'HTTP ' . $status);
    }
}

function req_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function req_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? rawurldecode($path) : '/';
    $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
    return $path;
}

/** JSON-Koerper einer Anfrage, hoechstens 256 KB. */
function req_json(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $raw = file_get_contents('php://input', false, null, 0, 262144);
    $data = [];
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new HttpError(400, 'The request body is not valid JSON.', 'Die Anfrage ist kein gueltiges JSON.');
        }
        $data = $decoded;
    }
    return $data;
}

/** Wert aus POST-Formular oder JSON-Koerper. */
function input(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($ct, 'application/json')) {
        $j = req_json();
        if (array_key_exists($key, $j)) {
            return $j[$key];
        }
    }
    return $default;
}

function input_str(string $key, int $max = 1000): string
{
    $v = input($key, '');
    if (!is_string($v) && !is_numeric($v)) {
        return '';
    }
    return str_cut(trim((string) $v), $max);
}

function wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    return str_contains($accept, 'application/json') || str_contains($ct, 'application/json') || str_starts_with(req_path(), '/api/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || !is_dev();
}

/**
 * Sicherheitskoepfe fuer HTML. $variant 'map' erlaubt Kartenkacheln von
 * OpenStreetMap, sonst laedt die Seite nur von sich selbst und Bunny Fonts.
 */
function send_page_headers(string $variant = 'page', string $referrer = 'same-origin'): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: ' . NO_STORE);
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: ' . $referrer);
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    $img = "'self' data:";
    $connect = "'self'";
    $frameAncestors = "'none'";
    // "Mit Spotify verbinden" ist ein Formular, das der Server zu Spotify weiterleitet. Browser
    // pruefen form-action auch fuer das Ziel der Weiterleitung, deshalb steht die Anmeldeseite
    // von Spotify hier, und nur sie (lokal die Nachbildung aus tools/spotify-mock.php).
    $formAction = "'self' https://accounts.spotify.com";
    if (function_exists('spotify_mock') && spotify_mock() !== '') {
        $formAction .= ' ' . spotify_mock();
    }
    if ($variant === 'map') {
        $img .= ' https://tile.openstreetmap.org';
        $frameAncestors = "'self'";
    }
    header('X-Frame-Options: ' . ($variant === 'map' ? 'SAMEORIGIN' : 'DENY'));
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src https://fonts.bunny.net; img-src $img; connect-src $connect; frame-src 'self'; frame-ancestors $frameAncestors; base-uri 'none'; form-action $formAction; object-src 'none'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** Schon gesetzter Kopf, etwa ein laengeres Cache-Control fuer Logos. */
function header_is_set(string $name): bool
{
    foreach (headers_list() as $line) {
        if (stripos($line, $name . ':') === 0) {
            return true;
        }
    }
    return false;
}

function send_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (!header_is_set('Cache-Control')) {
        header('Cache-Control: ' . NO_STORE);
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

/** Fehler als JSON mit beiden Sprachen, damit der Browser die richtige zeigt. */
function send_json_error(int $status, string $en, string $de, array $extra = []): never
{
    send_json(['ok' => false, 'error' => ['en' => $en, 'de' => $de]] + $extra, $status);
}

function redirect(string $to, int $status = 303): never
{
    if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
        $to = '/';
    }
    header('Cache-Control: ' . NO_STORE);
    header('Location: ' . $to, true, $status);
    exit;
}

/**
 * Cookie setzen. __Host- verlangt Secure, Path=/ und keine Domain. Lokal ueber
 * http://localhost akzeptieren Browser Secure-Cookies ebenfalls.
 */
function set_cookie(string $name, string $value, int $maxAge, string $sameSite = 'Lax'): void
{
    setcookie($name, $value, [
        'expires' => $maxAge > 0 ? time() + $maxAge : ($maxAge < 0 ? time() - 3600 : 0),
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
}

/**
 * Nur Anfragen von dieser Seite selbst duerfen etwas veraendern. Browser senden
 * Sec-Fetch-Site oder Origin mit, beides wird geprueft.
 */
function same_origin_request(): bool
{
    $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
        return false;
    }
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    /* "null" schicken Sandbox-iframes und file://, aber auch jede Seite mit
       Referrer-Policy no-referrer bei einem normalen Formular, selbst an sich selbst
       (gemessen am 22. September 2026: Origin null, Sec-Fetch-Site same-origin).
       Sagt Sec-Fetch-Site same-origin, ist es das Zweite: den Kopf setzt der Browser
       selbst, und ein Sandbox-iframe bekaeme dort cross-site. Fehlt Sec-Fetch-Site
       (Safari vor 16.4), gilt null weiter als fremd, sonst waere eine Anmeldung aus
       einem fremden iframe moeglich (Befund S3). */
    if ($origin === 'null') {
        return $site === 'same-origin';
    }
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        $self = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $selfHost = preg_replace('/:\d+$/', '', $self);
        if (!is_string($host) || strcasecmp($host, $selfHost) !== 0) {
            return false;
        }
    }
    return true;
}
