<?php
/*
 * Texte fuer das Panel. Das Panel kennt nur die Glyphen aus pixelfont.js:
 * druckbares ASCII, dazu Pfeil und Gradzeichen. Der Server schreibt deshalb
 * alles andere um, bevor er sendet: ö wird o, é wird e, ß wird ss.
 *
 * Es gibt diese Umschreibung nur hier. Die Vorschau im Browser zeigt die
 * Zeichenbefehle, die der Server liefert, also genau das, was auch das Panel
 * bekommt (FEHLERLISTE 4.4).
 */

declare(strict_types=1);

const PANEL_COLS = 128;
const PANEL_ROWS = 64;
const PANEL_LINE_MAX = 21;

function panel_text(string $s, bool $upper = true, int $max = PANEL_LINE_MAX): string
{
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    $s = strtr($s, ['→' => "\x01", '°' => "\x02"]);
    static $tr = null;
    if ($tr === null && class_exists('Transliterator')) {
        $tr = Transliterator::create('Any-Latin; Latin-ASCII') ?: false;
    }
    if ($tr) {
        $out = $tr->transliterate($s);
        if (is_string($out)) {
            $s = $out;
        }
    } else {
        $out = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($out)) {
            $s = $out;
        }
    }
    $s = preg_replace('/[^\x01\x02\x20-\x7E]/', '', $s) ?? '';
    $s = preg_replace('/ {2,}/', ' ', $s) ?? '';
    if ($upper) {
        $s = strtoupper($s);
    }
    $s = strtr($s, ["\x01" => '→', "\x02" => '°']);
    return mb_substr($s, 0, $max);
}

/** Breite in Pixeln wie PX.width(): 6 Pixel Vorschub je Zeichen, das letzte ohne Luecke. */
function panel_width(string $s, int $scale = 1): int
{
    $n = mb_strlen($s);
    return $n > 0 ? $n * 6 * $scale - $scale : 0;
}

function panel_center_x(string $s, int $scale = 1): int
{
    return (int) max(0, round((PANEL_COLS - panel_width($s, $scale)) / 2));
}
