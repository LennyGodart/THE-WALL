<?php
/*
 * Ansichten. Jede Seite unter views/ bekommt ihre Variablen und baut ihr HTML
 * selbst, mit den Bausteinen hier: Kopf, Sprachschalter, Sprungmarke, Wortmarke.
 *
 * Gestaltung: Werte aus den Entwuerfen stehen als Inline-Styles im Markup. Was
 * inline nicht geht (Hover, Fokus, Zustaende ueber aria-Attribute, Keyframes),
 * steht in assets/css/base.css.
 */

declare(strict_types=1);

function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require TW_APP . '/views/' . $name . '.php';
}

/** Adresse einer Datei unter /assets mit Versionsanhang, weil nginx sie ewig cachen laesst. */
function asset(string $path): string
{
    static $versions = [];
    if (!isset($versions[$path])) {
        $file = TW_ROOT . '/assets/' . $path;
        $versions[$path] = is_file($file) ? substr(md5((string) filemtime($file) . filesize($file)), 0, 10) : '0';
    }
    return '/assets/' . $path . '?v=' . $versions[$path];
}

/**
 * Anfang jeder Seite bis einschliesslich <body>.
 * $o: title, title_de, page (Name fuer data-page), fonts (Bunny-Familien), variant, referrer
 */
function page_open(array $o): void
{
    send_page_headers($o['variant'] ?? 'page', $o['referrer'] ?? 'same-origin');
    $fonts = $o['fonts'] ?? 'ibm-plex-mono:400,500,600,700|ibm-plex-sans:400,500,600';
    $csrf = csrf_token();
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    echo "<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo '<title' . (isset($o['title_de']) ? de($o['title_de']) : '') . '>' . h($o['title']) . "</title>\n";
    echo '<meta name="color-scheme" content="dark">' . "\n";
    if (($o['referrer'] ?? '') === 'no-referrer') {
        // nginx auf dem Server haengt ein zweites Referrer-Policy: same-origin an,
        // und bei zwei Koepfen gilt der letzte. Das Meta-Tag gilt fuer das Dokument.
        // Nicht fuer Seiten mit einem normalen Formular: dort schickt der Browser dann
        // Origin: null, und Safari vor 16.4 kommt nicht mehr durch csrf_check().
        echo '<meta name="referrer" content="no-referrer">' . "\n";
    }
    if (!empty($o['noindex'])) {
        echo '<meta name="robots" content="noindex">' . "\n";
    }
    if ($csrf !== '') {
        echo '<meta name="csrf-token" content="' . h($csrf) . '">' . "\n";
    }
    echo '<script src="' . h(asset('js/core/lang-early.js')) . '"></script>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>' . "\n";
    echo '<link href="https://fonts.bunny.net/css?family=' . h($fonts) . '" rel="stylesheet">' . "\n";
    echo '<link href="' . h(asset('css/base.css')) . '" rel="stylesheet">' . "\n";
    echo '<link rel="icon" href="' . h(asset('favicon.svg')) . '" type="image/svg+xml">' . "\n";
    echo "</head>\n<body data-page=\"" . h($o['page'] ?? '') . "\">\n";
}

/** Ende jeder Seite: Seitendaten als JSON, dann die Skripte in fester Reihenfolge. */
function page_close(array $scripts = [], array $data = []): void
{
    if ($data) {
        echo '<script type="application/json" id="tw-data">' . json_for_html($data) . "</script>\n";
    }
    foreach (array_merge(['js/lib/pixelfont.js', 'js/core/ui.js'], $scripts) as $s) {
        echo '<script src="' . h(asset($s)) . '" defer></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

function skip_link(string $href, string $en, string $de): string
{
    return '<a href="' . h($href) . '" data-skip="1"' . de($de) . ' style="position:fixed;top:8px;left:8px;z-index:60;padding:12px 18px;background:#FFAA00;color:#08090A;font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:600;border-radius:2px;transform:translateY(-200%);transition:transform 160ms cubic-bezier(0.23,1,0.32,1)">' . h($en) . '</a>';
}

/** Die Wortmarke THE WALL, gezeichnet von ui.js auf einem Canvas. */
function brand_mark(?string $href = '/'): string
{
    $canvas = '<canvas data-px="mark" role="img" aria-label="THE WALL"></canvas>';
    if ($href === null) {
        return '<div data-mark style="display:flex;align-items:center;gap:10px;flex:0 1 auto;min-width:0">' . $canvas . '</div>';
    }
    return '<a href="' . h($href) . '" data-mark style="display:flex;align-items:center;flex:0 1 auto;min-width:0">' . $canvas . '</a>';
}

/** EN/DE-Umschalter. Zustand ueber aria-pressed, Aussehen aus base.css. */
function lang_switch(): string
{
    return '<div style="display:flex;align-items:center;gap:6px;padding:3px;border:1px solid #232A30;border-radius:2px;font-family:\'IBM Plex Mono\',monospace;font-size:11px" role="group" aria-label="Language">'
        . '<button type="button" class="tw-lang" data-lang="en" lang="en" aria-pressed="true">EN</button>'
        . '<button type="button" class="tw-lang" data-lang="de" lang="de" aria-pressed="false">DE</button>'
        . '</div>';
}

/** Englischer Text mit deutschem Gegenstueck in einem span. Beide Texte aus dem Code. */
function t(string $en, string $de, string $tag = 'span', string $style = ''): string
{
    return '<' . $tag . ($style !== '' ? ' style="' . h($style) . '"' : '') . de($de) . '>' . h($en) . '</' . $tag . '>';
}

/** Inline-Style-Attribut. */
function st(string $css): string
{
    return ' style="' . h($css) . '"';
}
