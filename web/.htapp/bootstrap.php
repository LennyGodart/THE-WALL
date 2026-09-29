<?php
/*
 * THE WALL, Einstieg fuer jeden Aufruf. index.php laedt nur diese Datei.
 *
 * Alles unter /.htapp und /.htdata sperrt nginx nach aussen (CloudPanel-Vorlage:
 * location ~ /\.(ht|svn|git) { deny all; }). Fuer Apache liegen zusaetzlich
 * .htaccess-Dateien mit "Require all denied" in beiden Ordnern.
 *
 * Aufbau von .htapp:
 *   core/      Werkzeuge ohne Fachwissen: Konfiguration, Datenbank, HTTP, Ansicht
 *   auth/      Konten, Sitzungen, CSRF, Einmal-Links
 *   services/  alles, was nach aussen spricht: adsb.lol, adsbdb, Open-Meteo, Nominatim, mobiliteit.lu, SMTP
 *   device/    Geraete, Einstellungen, Zeichenbefehle, Firmware
 *   modes/     ein Modul pro Anzeigemodus, neue Modi kommen hier als Datei dazu
 *   pages/     Seiten fuer den Browser
 *   api/       JSON fuer Browser und Geraet
 *   views/     HTML-Vorlagen
 */

declare(strict_types=1);

define('TW_ROOT', dirname(__DIR__));
define('TW_APP', __DIR__);
define('TW_DATA', TW_ROOT . '/.htdata');
define('TW_VERSION', '0.1.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
mb_internal_encoding('UTF-8');

foreach ([
    'core/util', 'core/config', 'core/log', 'core/db', 'core/http', 'core/ip', 'core/router',
    'core/view', 'core/crypto', 'core/ratelimit', 'core/validate', 'core/settings', 'core/tz',
    'core/text',
    'auth/users', 'auth/session', 'auth/csrf', 'auth/tokens',
    'services/upstream', 'services/aircraft', 'services/adsb', 'services/weather', 'services/geocode', 'services/smtp',
    'services/mailer', 'services/logos', 'services/transit', 'services/stops', 'services/geomap', 'services/spotify',
    'device/modes', 'device/devices', 'device/frame', 'device/firmware', 'device/timers', 'device/ha',
] as $file) {
    require TW_APP . '/' . $file . '.php';
}

foreach (glob(TW_APP . '/modes/*.php') ?: [] as $mode) {
    require $mode;
}
