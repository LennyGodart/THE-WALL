<?php
/*
 * Router. Die Routen stehen in routes.php als Liste aus Methode, Muster und
 * Handler "datei:funktion". Die Datei unter pages/ oder api/ wird erst geladen,
 * wenn ihre Route passt.
 */

declare(strict_types=1);

function dispatch(array $routes, string $method, string $path): void
{
    $allowed = [];
    foreach ($routes as [$m, $pattern, $handler]) {
        $regex = '#^' . preg_replace('#\{([a-z]+)\}#', '(?P<$1>[A-Za-z0-9_.\-]+)', $pattern) . '$#';
        if (!preg_match($regex, $path, $match)) {
            continue;
        }
        if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
            $allowed[] = $m;
            continue;
        }
        $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        [$file, $fn] = explode(':', $handler);
        require_once TW_APP . '/' . $file . '.php';
        $fn($params);
        return;
    }
    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        throw new HttpError(405, 'Method not allowed.', 'Methode nicht erlaubt.');
    }
    throw new HttpError(404);
}

/** Ganzer Ablauf eines Aufrufs, von index.php gerufen. */
function app_run(): void
{
    set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
        if (!(error_reporting() & $no)) {
            return false;
        }
        throw new ErrorException($str, 0, $no, $file, $line);
    });

    $method = req_method();
    $path = req_path();
    // Einmal pro Stunde aufraeumen, am Ende jedes Aufrufs, auch nach exit in send_json().
    // Ohne das raeumte nur eine HTML-Seite auf, der Geraeteabruf nie (Befund C1).
    register_shutdown_function(static function (): void {
        if (http_response_code() < 500) {
            housekeeping();
        }
    });
    /* Seiten entstehen Stueck fuer Stueck. Bricht eine mittendrin ab, soll keine
       halbe Seite mit Status 200 rausgehen, sondern die Fehlerseite. */
    $level = ob_get_level();
    ob_start();
    try {
        $routes = require TW_APP . '/routes.php';
        dispatch($routes, $method, $path);
    } catch (HttpError $e) {
        app_discard($level);
        app_error($e->status, $e->getMessage(), $e->messageDe);
    } catch (SetupMissing $e) {
        app_discard($level);
        log_error('Einrichtung fehlt: ' . $e->getMessage());
        app_error(503, 'The server is not set up yet.', 'Der Server ist noch nicht eingerichtet.');
    } catch (Throwable $e) {
        app_discard($level);
        log_error(get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'path' => $path]);
        app_error(500, 'Something went wrong on the server.', 'Auf dem Server ist etwas schiefgegangen.');
    }
    while (ob_get_level() > $level) {
        ob_end_flush();
    }
}

/** Verwirft, was eine abgebrochene Seite schon ausgegeben hat. */
function app_discard(int $level): void
{
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header_remove('Content-Disposition');
    }
}

function app_error(int $status, string $en = '', ?string $de = null): void
{
    if (headers_sent()) {
        return;
    }
    http_response_code($status);
    if (wants_json()) {
        send_json_error($status, $en !== '' ? $en : 'Error ' . $status, $de ?? ('Fehler ' . $status));
    }
    require_once TW_APP . '/pages/errors.php';
    page_error($status, $en, $de);
}
