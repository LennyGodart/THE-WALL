<?php
/*
 * Konfiguration. Feste Vorgaben stehen hier, die Zugangsdaten des Servers in
 * .htdata/config.php (nicht im Repo, Vorlage: .htapp/config.example.php).
 * Was der Admin im Browser aendert (SMTP, Impressum, Registrierung), liegt in
 * der Tabelle settings, siehe settings.php.
 *
 * Lokal: Umgebungsvariable TW_ENV=dev, dann SQLite in .htdata/dev.sqlite.
 */

declare(strict_types=1);

final class SetupMissing extends RuntimeException
{
}

function config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'env' => 'prod',
            'url' => 'https://thewall.godart.lu',
            'db' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'thewall',
                'user' => 'thewall',
                'pass' => '',
                'path' => TW_DATA . '/dev.sqlite',
            ],
        ];
        $file = TW_DATA . '/config.php';
        if (is_file($file)) {
            $local = require $file;
            if (is_array($local)) {
                $cfg = array_replace_recursive($cfg, $local);
            }
        }
        if (getenv('TW_ENV') === 'dev') {
            $cfg['env'] = 'dev';
            $cfg['db']['driver'] = getenv('TW_DB') ?: 'sqlite';
            if (getenv('TW_DB_PATH')) {
                $cfg['db']['path'] = getenv('TW_DB_PATH');
            }
        }
    }
    if ($key === null) {
        return $cfg;
    }
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

function is_dev(): bool
{
    return config('env') === 'dev';
}

/** Basisadresse fuer Links in Mails. Lokal die Adresse, unter der die Seite laeuft. */
/** Name der eigenen Instanz fuer Fusszeilen und SMTP, etwa thewall.godart.lu, aus config url. */
function site_host(): string
{
    $host = parse_url(rtrim((string) config('url'), '/'), PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : 'localhost';
}

function app_url(): string
{
    if (is_dev() && !empty($_SERVER['HTTP_HOST'])) {
        return 'http://' . preg_replace('/[^a-z0-9.:\-]/i', '', (string) $_SERVER['HTTP_HOST']);
    }
    return rtrim((string) config('url'), '/');
}

/**
 * Geheimnisse, die der Server selbst erzeugt: Schluessel fuer CSRF und
 * Verschluesselung, dazu der Einrichtungs-Token fuer das erste Admin-Konto.
 * Liegen in .htdata/secret.php und entstehen beim ersten Aufruf.
 */
function secret(string $name): string
{
    static $secrets = null;
    if ($secrets === null) {
        $file = TW_DATA . '/secret.php';
        if (is_file($file)) {
            $secrets = require $file;
        }
        if (!is_array($secrets) || empty($secrets['app_key'])) {
            $secrets = [
                'app_key' => random_hex(32),
                'setup_token' => random_hex(16),
            ];
            ensure_data_dir();
            atomic_write($file, "<?php\n// Von THE WALL erzeugt. Nicht teilen, nicht committen.\nreturn " . var_export($secrets, true) . ";\n");
        }
    }
    if (!isset($secrets[$name])) {
        throw new RuntimeException('Unbekanntes Geheimnis: ' . $name);
    }
    return (string) $secrets[$name];
}

/** .htdata anlegen, mit Sperre fuer Apache und ohne Verzeichnisliste. */
function ensure_data_dir(): void
{
    foreach ([TW_DATA, TW_DATA . '/logs', TW_DATA . '/firmware', TW_DATA . '/exports'] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }
    if (!is_file(TW_DATA . '/.htaccess')) {
        @file_put_contents(TW_DATA . '/.htaccess', "Require all denied\n");
    }
}
