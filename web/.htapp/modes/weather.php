<?php
/*
 * Modus Wetter, Daten von Open-Meteo. Zwei Ansichten: nur jetzt und gross,
 * oder jetzt und drei Tage.
 *
 * Behoben gegenueber dem Entwurf (FEHLERLISTE 4.1 und 4.2): die Einheit steht
 * hinter der Zahl statt an fester Stelle, dreistellige Werte schrumpfen, und die
 * Windangabe in der Dreitagesansicht passt in die 58 Spalten ab x 70.
 */

declare(strict_types=1);

mode_register('weather', [
    'order' => 30,
    'label' => ['en' => 'Weather', 'de' => 'Wetter'],
    'rotatable' => true,
    'defaults' => ['unit' => 'C', 'wind' => false, 'view' => 'forecast'],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (isset($in['unit']) && in_array($in['unit'], ['C', 'F'], true)) {
            $out['unit'] = $in['unit'];
        }
        if (array_key_exists('wind', $in)) {
            $out['wind'] = (bool) $in['wind'];
        }
        if (isset($in['view']) && in_array($in['view'], ['now', 'forecast'], true)) {
            $out['view'] = $in['view'];
        }
        return $out;
    },
    'build' => static fn(array $ctx, float $from, float $to): array => [frame_page($from, $to, weather_ops($ctx), ['id' => 'weather'])],
]);

function weather_ops(array $ctx): array
{
    $s = $ctx['settings'];
    $w = $s['weather'];
    $lang = $ctx['lang'];
    // 20 Zeichen ab x 4: mit 21 fehlte dem letzten Buchstaben die rechte Spalte. Laeuft ein
    // Timer, steht oben rechts seine Ecke, dann enden es nach 15 Zeichen bei x 93.
    $ops = [op_text(4, 3, panel_text((string) $s['location']['place'], true, empty($ctx['corner']) ? 20 : 15), C_DIM)];
    // $ctx['wx'] setzt nur die Startseite (welcome_demo_pages), sonst Open-Meteo.
    $wx = $ctx['wx'] ?? weather_get((float) $s['location']['lat'], (float) $s['location']['lon'], (string) $s['tz']);
    if (!$wx) {
        $ops[] = op_text('c', 30, $lang === 'de' ? 'KEINE WETTERDATEN' : 'NO WEATHER DATA', C_DIM);
        return $ops;
    }
    $unit = $w['unit'] === 'F' ? 'F' : 'C';
    $t = (string) weather_convert((float) $wx['temp'], $unit);
    $word = weather_word((int) $wx['code'], $lang);

    if ($w['view'] === 'now') {
        $ops[] = op_text(4, 18, $t, C_ACCENT, 4);
        $ops[] = op_text(4 + panel_width($t, 4) + 10, 18, '°' . $unit, C_DIM, 2);
        $ops[] = op_text(4, 48, $word, C_WHITE);
        if (!empty($w['wind'])) {
            // y 56: die Lippe des Standfusses verdeckt die unterste Zeile halb.
            $ops[] = op_text(4, 56, weather_wind_text($wx, $unit, $lang, true), C_DIM);
        }
        return $ops;
    }

    $z = 3;
    $ux = 4 + panel_width($t, 3) + 7;
    if ($ux + panel_width('°' . $unit, 2) + 4 > 70) {
        $z = 2;
        $ux = 4 + panel_width($t, 2) + 7;
    }
    $ops[] = op_text(4, 15, $t, C_ACCENT, $z);
    $ops[] = op_text($ux, 15, '°' . $unit, C_DIM, 2);
    $ops[] = op_text(70, 15, $word, C_WHITE);
    if (!empty($w['wind'])) {
        $ops[] = op_text(70, 27, weather_wind_text($wx, $unit, $lang, false), C_DIM);
    }
    $ops[] = op_rect(0, 38, 128, 1, C_LINE);
    foreach (array_slice($wx['days'], 1, 3) as $i => $day) {
        $ops[] = op_text(4 + $i * 42, 43, weather_day_name((string) $day['date'], $lang), C_DIM);
        $ops[] = op_text(4 + $i * 42, 54, weather_convert((float) $day['max'], $unit) . '°', C_WHITE);
    }
    return $ops;
}

/** "12 KM/H NW" gross, "12KM/H NW" schmal fuer die 58 Spalten der Dreitagesansicht. */
function weather_wind_text(array $wx, string $unit, string $lang, bool $wide): string
{
    $speed = $unit === 'F' ? (int) round($wx['wind'] / 1.609) : (int) round($wx['wind']);
    $u = $unit === 'F' ? 'MPH' : ($speed >= 100 ? 'KMH' : 'KM/H');
    return $speed . ($wide ? ' ' : '') . $u . ' ' . wind_compass((int) $wx['wind_dir'], $lang);
}

function weather_day_name(string $date, string $lang): string
{
    $en = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    $de = ['SON', 'MON', 'DIE', 'MIT', 'DON', 'FRE', 'SAM'];
    $w = (int) date('w', strtotime($date . ' 12:00:00 UTC') ?: time());
    return ($lang === 'de' ? $de : $en)[$w];
}
