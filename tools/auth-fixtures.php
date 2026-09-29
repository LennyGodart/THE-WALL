<?php
// Prueft die Herkunftspruefung same_origin_request() in core/http.php ohne Browser:
// welche Kombination aus Sec-Fetch-Site und Origin gilt als "von dieser Seite".
// Die Werte sind die, die Browser wirklich schicken, gemessen am 22. September 2026
// mit einem normalen Formular-POST (CHANGELOG 54).
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/auth-fixtures.php
// Exit-Code 1 bei einem Fehler.
declare(strict_types=1);
require __DIR__ . '/../web/.htapp/bootstrap.php';

$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok     ' : 'FEHLER ') . $what . "\n";
    if (!$ok) {
        $fail++;
    }
}

/** Eine Anfrage an die eigene Seite mit diesen Koepfen; null laesst den Kopf weg. */
function herkunft(?string $site, ?string $origin): bool
{
    $_SERVER['HTTP_HOST'] = 'wall.example';
    unset($_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_ORIGIN']);
    if ($site !== null) {
        $_SERVER['HTTP_SEC_FETCH_SITE'] = $site;
    }
    if ($origin !== null) {
        $_SERVER['HTTP_ORIGIN'] = $origin;
    }
    return same_origin_request();
}

$self = 'https://wall.example';

// Der Fehler: Formular auf einer Seite mit no-referrer, abgeschickt an sich selbst
check('Eigene Seite, no-referrer: Origin null, Sec-Fetch-Site same-origin', herkunft('same-origin', 'null'));
check('Eigene Seite, normal: Origin gesetzt, same-origin', herkunft('same-origin', $self));
check('Aufruf aus der Adresszeile: Sec-Fetch-Site none, ohne Origin', herkunft('none', null));

// Was fremd bleiben muss
check('Fremde Seite mit no-referrer: Origin null, cross-site', !herkunft('cross-site', 'null'));
check('Nachbar auf derselben Domain: Origin null, same-site', !herkunft('same-site', 'null'));
check('Fremde Seite: Origin gesetzt, cross-site', !herkunft('cross-site', 'https://example.com'));
check('same-origin, aber Origin eines fremden Hosts', !herkunft('same-origin', 'https://example.com'));

// Safari vor 16.4 schickt kein Sec-Fetch-Site: dort entscheidet Origin allein (Befund S3)
check('Ohne Sec-Fetch-Site: Origin null gilt als fremd', !herkunft(null, 'null'));
check('Ohne Sec-Fetch-Site: eigener Origin', herkunft(null, $self));
check('Ohne Sec-Fetch-Site: fremder Origin', !herkunft(null, 'https://example.com'));

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
