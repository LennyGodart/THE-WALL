<?php
/*
 * Wetter von Open-Meteo, ohne Schluessel. Der Server fragt je Ort hoechstens
 * alle 15 Minuten. Derselbe Wert steht auf der Geraeteseite und im Admin-Bereich
 * (FEHLERLISTE 3.11).
 */

declare(strict_types=1);

const WEATHER_TTL = 900;
/* So lange gilt die letzte gute Antwort, wenn Open-Meteo gerade nicht antwortet. */
const WEATHER_LAST_TTL = 10800;

function weather_get(float $lat, float $lon, string $tz): ?array
{
    $tz = tz_valid($tz) ? $tz : 'Europe/Luxembourg';
    $key = sprintf('meteo:%.2f:%.2f:%s', $lat, $lon, $tz);
    $last = 'meteolast:' . substr($key, 6);
    $v = cache_remember($key, 'meteo', static function () use ($lat, $lon, $tz, $last): ?array {
        $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
            'latitude' => sprintf('%.4f', $lat),
            'longitude' => sprintf('%.4f', $lon),
            'current' => 'temperature_2m,weather_code,wind_speed_10m,wind_direction_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
            'forecast_days' => 4,
            'wind_speed_unit' => 'kmh',
            'timezone' => $tz,
        ]);
        $r = http_get_json('meteo', $url, 6);
        $d = $r['data'];
        if ($r['status'] !== 200 || !isset($d['current']['temperature_2m'])) {
            event_add('METEO', 'No usable answer from Open-Meteo (HTTP ' . $r['status'] . ')', 'Keine brauchbare Antwort von Open-Meteo (HTTP ' . $r['status'] . ')');
            return null;
        }
        $days = [];
        foreach (($d['daily']['time'] ?? []) as $i => $date) {
            $days[] = [
                'date' => (string) $date,
                'max' => (float) ($d['daily']['temperature_2m_max'][$i] ?? 0),
                'min' => (float) ($d['daily']['temperature_2m_min'][$i] ?? 0),
                'code' => (int) ($d['daily']['weather_code'][$i] ?? 0),
            ];
        }
        $value = [
            'temp' => (float) $d['current']['temperature_2m'],
            'code' => (int) ($d['current']['weather_code'] ?? 0),
            'wind' => (float) ($d['current']['wind_speed_10m'] ?? 0),
            'wind_dir' => (int) ($d['current']['wind_direction_10m'] ?? 0),
            'days' => $days,
            'fetched_at' => time(),
        ];
        cache_put($last, $value, WEATHER_LAST_TTL);
        return [$value, WEATHER_TTL];
    }, 60);
    // Nach einem Fehler fragt der Server eine Minute lang nicht nach, und die letzte gute
    // Antwort bleibt drei Stunden stehen.
    return $v ?? cache_get($last);
}

/** Ein Wort fuer das Panel, hoechstens acht Zeichen. WMO-Codes laut Open-Meteo. */
function weather_word(int $code, string $lang): string
{
    $de = $lang === 'de';
    return match (true) {
        $code === 0 => $de ? 'SONNIG' : 'CLEAR',
        $code <= 2 => $de ? 'HEITER' : 'FAIR',
        $code === 3 => $de ? 'BEDECKT' : 'OVERCAST',
        $code === 45 || $code === 48 => $de ? 'NEBEL' : 'FOG',
        $code >= 51 && $code <= 57 => $de ? 'NIESELN' : 'DRIZZLE',
        $code >= 61 && $code <= 67 => $de ? 'REGEN' : 'RAIN',
        $code >= 71 && $code <= 77 => $de ? 'SCHNEE' : 'SNOW',
        $code >= 80 && $code <= 82 => $de ? 'SCHAUER' : 'SHOWERS',
        $code === 85 || $code === 86 => $de ? 'SCHNEE' : 'SNOW',
        $code >= 95 => $de ? 'GEWITTER' : 'STORM',
        default => $de ? 'WOLKIG' : 'CLOUDY',
    };
}

function wind_compass(int $deg, string $lang): string
{
    $en = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    $de = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];
    $i = (int) round((($deg % 360) + 360) % 360 / 45) % 8;
    return ($lang === 'de' ? $de : $en)[$i];
}

function weather_convert(float $celsius, string $unit): int
{
    return (int) round($unit === 'F' ? $celsius * 9 / 5 + 32 : $celsius);
}
