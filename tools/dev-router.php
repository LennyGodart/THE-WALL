<?php
/*
 * Lokaler Testserver fuer THE WALL, verhaelt sich wie nginx auf dem Server:
 * vorhandene Dateien direkt, alles unter /.ht... gesperrt, der Rest an index.php.
 *
 * Aufruf aus dem Hauptverzeichnis:
 *   TW_ENV=dev php -S 127.0.0.1:8765 -t web tools/dev-router.php
 */

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (preg_match('#/\.(ht|svn|git)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
$file = __DIR__ . '/../web' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/../web/index.php';
