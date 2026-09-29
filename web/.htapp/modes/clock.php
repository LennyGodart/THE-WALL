<?php
/*
 * Modus Uhr. Die Zeit zeichnet das Geraet aus seiner RTC, der Server legt nur
 * Groesse, Farbe und Lage fest. Die Groesse sinkt, bis die Zeit in 126 Spalten
 * passt: "20:14:33" ist bei Faktor 3 schon 141 Spalten breit. Bei 12 Stunden steht
 * AM oder PM klein neben den Ziffern (clock_width()).
 */

declare(strict_types=1);

mode_register('clock', [
    'order' => 20,
    'label' => ['en' => 'Clock', 'de' => 'Uhr'],
    'rotatable' => true,
    'defaults' => ['face' => 'big', 'color' => '#FFAA00', 'h24' => true, 'sec' => false, 'wx' => true, 'night' => true],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (isset($in['face']) && in_array($in['face'], ['small', 'big', 'seg'], true)) {
            $out['face'] = $in['face'];
        }
        if (isset($in['color']) && valid_hex_colour((string) $in['color'])) {
            $out['color'] = strtoupper((string) $in['color']);
        }
        foreach (['h24', 'sec', 'wx', 'night'] as $b) {
            if (array_key_exists($b, $in)) {
                $out[$b] = (bool) $in[$b];
            }
        }
        return $out;
    },
    'build' => static fn(array $ctx, float $from, float $to): array => [frame_page($from, $to, clock_ops($ctx), ['id' => 'clock'])],
]);

function clock_ops(array $ctx): array
{
    $s = $ctx['settings'];
    $c = $s['clock'];
    $h24 = (bool) $c['h24'];
    $sec = (bool) $c['sec'];
    $scale = match ($c['face']) {
        'small' => 1,
        'seg' => 2,
        default => 3,
    };
    while ($scale > 1 && clock_width($h24, $sec, $scale) > 126) {
        $scale--;
    }
    $w = clock_width($h24, $sec, $scale);
    $x = max(1, (int) round((PANEL_COLS - $w) / 2));
    $tall = 7 * $scale;
    $y = !empty($c['wx']) ? (int) round((46 - $tall) / 2) : (int) round((58 - $tall) / 2);

    $ops = [
        op_clock($x, $y, $scale, hex6((string) $c['color']), $h24, $sec, $c['face'] === 'seg', true),
        op_date(min(!empty($c['wx']) ? 38 : 50, $y + $tall + 4), C_DIM, $ctx['lang']),
    ];

    if (!empty($c['wx'])) {
        $ops[] = op_rect(0, 48, 128, 1, C_LINE);
        $unit = $s['weather']['unit'] === 'F' ? 'F' : 'C';
        $wx = $ctx['wx'] ?? weather_get((float) $s['location']['lat'], (float) $s['location']['lon'], (string) $s['tz']);
        if ($wx) {
            $ops[] = op_text(4, 53, weather_convert($wx['temp'], $unit) . '°' . $unit, C_CYAN);
            $word = weather_word((int) $wx['code'], $ctx['lang']);
            $ops[] = op_text(PANEL_COLS - panel_width($word) - 4, 53, $word, C_DIM);
        } else {
            $ops[] = op_text(4, 53, '--°' . $unit, C_CYAN);
        }
    }
    return $ops;
}
