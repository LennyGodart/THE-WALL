<?php
/*
 * Airline-Logos, 32 x 34 LED-Pixel in Vollfarbe. Eingetragene Marken: sie gehen
 * nur an angemeldete Nutzer und an Geraete mit gueltigem Schluessel. Oeffentliche
 * Seiten zeigen den Farbblock mit dem Kuerzel (FEHLERLISTE 3.6).
 *
 * Quelle: .htdata/logos/<ICAO>.rgb, 3 264 Byte RGB888 zeilenweise, Schwarz ist aus.
 * Erzeugt von tools/logos-build.mjs aus github.com/Jxck-S/airline-logos und nur auf
 * dem Server abgelegt, nie im Repo. Fehlt eine Datei, gilt das Logo aus dem Entwurf
 * (.htapp/logos/logos.json, Luxair und Cargolux in Hausfarbe), sonst gibt es keins.
 * Zwei Buchstaben stehen fuer Betreiber ohne ICAO-Kuerzel (PL: Polizei Luxemburg), die
 * Firmware nimmt zwei oder drei.
 */

declare(strict_types=1);

const LOGO_W = 32;
const LOGO_H = 34;
const LOGO_BYTES = LOGO_W * LOGO_H * 3;

/** Logo als RGB888-Bytes oder null. */
function logo_rgb(string $icao): ?string
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $icao) ?? '');
    if (!preg_match('/^[A-Z0-9]{2,4}$/', $code)) {
        return null;
    }
    $file = TW_DATA . '/logos/' . $code . '.rgb';
    if (is_file($file) && filesize($file) === LOGO_BYTES) {
        $data = file_get_contents($file);
        if (is_string($data) && strlen($data) === LOGO_BYTES) {
            return $data;
        }
    }
    $old = logos_all()[$code] ?? null;
    return is_array($old) ? logo_rgb_from_rows($old) : null;
}

/** Entwurfsformat (Hausfarbe c, Zeilen mit 2 voll, 1 halb, . aus) in RGB888. */
function logo_rgb_from_rows(array $logo): string
{
    $c = array_map('intval', array_slice((array) ($logo['c'] ?? [255, 170, 0]), 0, 3));
    $out = str_repeat("\0", LOGO_BYTES);
    foreach (array_slice((array) ($logo['rows'] ?? []), 0, LOGO_H) as $y => $row) {
        $row = (string) $row;
        for ($x = 0; $x < LOGO_W && $x < strlen($row); $x++) {
            $k = $row[$x] === '2' ? 1.0 : ($row[$x] === '1' ? 0.45 : 0.0);
            if ($k === 0.0) {
                continue;
            }
            $o = ($y * LOGO_W + $x) * 3;
            for ($i = 0; $i < 3; $i++) {
                $out[$o + $i] = chr(max(0, min(255, (int) round(($c[$i] ?? 0) * $k))));
            }
        }
    }
    return $out;
}

/** Die Entwurfs-Logos aus .htapp/logos/logos.json. */
function logos_all(): array
{
    static $all = null;
    if ($all === null) {
        $file = TW_APP . '/logos/logos.json';
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    return $all;
}

/** Logo ausliefern: Binaerdaten, eine Woche im Browser oder Geraet zwischenspeicherbar. */
function logo_send(string $rgb): never
{
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($rgb));
    header('Cache-Control: private, max-age=604800, no-transform');
    header('X-Content-Type-Options: nosniff');
    header('X-Logo-Size: ' . LOGO_W . 'x' . LOGO_H);
    echo $rgb;
    exit;
}

/** Stand der Sammlung auf dem Server, fuer den Admin-Bereich. */
function logos_status(): array
{
    $file = TW_DATA . '/logos/index.json';
    $index = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($index)) {
        return ['count' => 0, 'ref' => '', 'built' => null];
    }
    $built = strtotime((string) ($index['built'] ?? ''));
    return [
        'count' => (int) ($index['count'] ?? 0),
        'ref' => substr((string) ($index['ref'] ?? ''), 0, 7),
        'built' => $built ?: null,
    ];
}
