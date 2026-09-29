<?php
/*
 * Vorlage fuer .htdata/config.php. Die echte Datei liegt nur auf dem Server und
 * kommt nie ins Repo. Kopieren, Werte eintragen, fertig: alles andere hat eine
 * Vorgabe in core/config.php.
 *
 * Nicht hier: SMTP, Impressum und Registrierung stellt der Admin im Browser ein
 * (Tabelle settings). Die Geheimnisse fuer Sitzungen und Verschluesselung erzeugt
 * der Server beim ersten Aufruf selbst in .htdata/secret.php.
 */

return [
    'url' => 'https://REPLACE_WITH_DOMAIN',
    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'REPLACE_WITH_DB_NAME',
        'user' => 'REPLACE_WITH_DB_USER',
        'pass' => 'REPLACE_WITH_DB_PASSWORD',
    ],
];
