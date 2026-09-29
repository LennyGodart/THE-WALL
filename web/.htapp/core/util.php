<?php
/* Kleine Helfer, die ueberall gebraucht werden. */

declare(strict_types=1);

/** HTML-Ausgabe escapen. Jeder Wert von aussen geht hier durch. */
function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * data-de-Attribut fuer den Sprachschalter. Nur fuer feste Texte aus dem Code: der
 * Schalter tauscht zwar nur textContent, aber was hier steht, soll ein Mensch
 * geschrieben und geprueft haben, nicht ein Nutzer oder ein fremder Dienst.
 */
function de(string $german): string
{
    return ' data-de="' . h($german) . '"';
}

function random_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

function clamp_int(mixed $v, int $min, int $max, int $default): int
{
    if (is_bool($v) || $v === null || $v === '' || !is_numeric($v)) {
        return $default;
    }
    return max($min, min($max, (int) round((float) $v)));
}

/** JSON, das gefahrlos in <script type="application/json"> stehen kann. */
function json_for_html(mixed $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

/** Datei ueber eine Temp-Datei und rename() schreiben, damit nie eine halbe Datei liegt. */
function atomic_write(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Ordner nicht anlegbar: ' . $dir);
    }
    $tmp = $dir . '/.tmp-' . random_hex(6);
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Datei nicht schreibbar: ' . $path);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Datei nicht ersetzbar: ' . $path);
    }
}

/** Mehrbyte-sicher kuerzen. */
function str_cut(string $s, int $max): string
{
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
}

/** "vor 3 Tagen" und "3 days ago" als Paar fuer data-de. */
function ago_pair(?int $ts): array
{
    if (!$ts) {
        return ['never', 'nie'];
    }
    $d = max(0, time() - $ts);
    if ($d < 60) {
        return [$d . ' s', $d . ' s'];
    }
    if ($d < 3600) {
        $m = intdiv($d, 60);
        return [$m . ' min', $m . ' min'];
    }
    if ($d < 86400) {
        $hrs = intdiv($d, 3600);
        return [$hrs . ' h', $hrs . ' Std'];
    }
    $days = intdiv($d, 86400);
    return [$days . ' d', $days . ' T'];
}

/** Laufzeit in Sekunden als "6 d 14 h" beziehungsweise "6 T 14 STD". */
function uptime_pair(?int $secs): array
{
    if ($secs === null) {
        return ['', ''];
    }
    $d = intdiv($secs, 86400);
    $hr = intdiv($secs % 86400, 3600);
    $m = intdiv($secs % 3600, 60);
    if ($d > 0) {
        return [$d . ' d ' . $hr . ' h', $d . ' T ' . $hr . ' STD'];
    }
    if ($hr > 0) {
        return [$hr . ' h ' . $m . ' min', $hr . ' STD ' . $m . ' MIN'];
    }
    return [$m . ' min', $m . ' MIN'];
}
