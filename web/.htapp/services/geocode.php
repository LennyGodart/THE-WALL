<?php
/*
 * Ortssuche ueber Nominatim. Der Server fragt stellvertretend: so bekommt
 * Nominatim einen User-Agent, der die Anwendung nennt, wie es die Nutzungsregeln
 * verlangen, die Adresse des Besuchers bleibt hier, und der Admin-Bereich kann
 * mitzaehlen. Die Kontaktadresse bleibt dabei weg, mit ihr hat Nominatim beim
 * Test mit 403 geantwortet. Gesucht wird nur auf Absenden, hoechstens eine
 * Anfrage pro Sekunde fuer den ganzen Server.
 *
 * Der umgekehrte Weg, geocode_reverse(), nennt den Ort unter einem Flugzeug. Er
 * teilt sich die Sekunde mit der Suche und speichert je Kachel 30 Tage.
 */

declare(strict_types=1);

function geocode_search(string $query, string $lang): array
{
    $q = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
    if ($q === '' || mb_strlen($q) > 120) {
        return ['status' => 'empty'];
    }
    $key = 'geo:' . md5(mb_strtolower($q) . '|' . $lang);
    $hit = cache_get($key);
    if ($hit !== null) {
        budget_add('nominatim', true);
        return $hit;
    }
    if (!rl_allow('nominatim:global', 1, 1)) {
        usleep(1100000);
        if (!rl_allow('nominatim:global', 1, 1)) {
            return ['status' => 'busy'];
        }
    }
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'jsonv2',
        'limit' => 1,
        'q' => $q,
        'accept-language' => $lang === 'de' ? 'de,en' : 'en,de',
    ]);
    $r = http_get_json('nominatim', $url, 8, false);
    if ($r['status'] !== 200 || !is_array($r['data'])) {
        event_add('NOMINATIM', 'Place search did not answer (HTTP ' . $r['status'] . ')', 'Ortssuche hat nicht geantwortet (HTTP ' . $r['status'] . ')');
        return ['status' => 'error'];
    }
    $out = ['status' => 'none'];
    if (!empty($r['data'][0])) {
        $h = $r['data'][0];
        $name = trim(explode(',', (string) ($h['display_name'] ?? $q))[0]);
        $out = [
            'status' => 'ok',
            'lat' => round((float) $h['lat'], 5),
            'lon' => round((float) $h['lon'], 5),
            'name' => str_cut($name !== '' ? $name : $q, 60),
        ];
    }
    cache_put($key, $out, 86400);
    return $out;
}

/* Kachel fuer den umgekehrten Weg: 0,05 Grad, gut 5 km Nord-Sued. Ein Flugzeug in
   Reiseflughoehe braucht etwa 40 Sekunden hindurch, und Orte wandern nicht. */
const GEO_REVERSE_TILE = 0.05;
const GEO_REVERSE_TTL = 30 * 86400;
const GEO_REVERSE_DAY_MAX = 1500;

/* Laender, in denen man den Bundesstaat nennt statt des Landes, wie "Zanesville, Ohio". */
const GEO_REVERSE_STATES = ['us', 'ca', 'au', 'br', 'mx', 'in'];

/**
 * Ort unter einem Punkt, fuer "FLYING OVER" auf dem Panel. Nominatim reverse mit
 * zoom 10 (Stadt oder Gemeinde), je Kachel 30 Tage gespeichert, auch "nichts"
 * (Wasser bleibt Wasser). Gefragt wird nur fuer das Flugzeug, das gerade auf dem
 * Panel steht, hoechstens einmal pro Sekunde fuer den ganzen Server und
 * hoechstens GEO_REVERSE_DAY_MAX Mal am Tag. Ist die Sekunde vergeben, wartet der
 * Abruf nicht: das Panel zeigt dann Koordinaten, der naechste Abruf fragt erneut.
 *
 * Liefert ['status' => 'ok', 'place', 'region'] mit Region als Bundesstaat oder
 * Land, 'none' ueber Wasser oder ohne Ort, 'busy' ohne Antwort.
 */
function geocode_reverse(float $lat, float $lon, string $lang): array
{
    $lang = $lang === 'de' ? 'de' : 'en';
    $ty = (int) round($lat / GEO_REVERSE_TILE);
    $tx = (int) round($lon / GEO_REVERSE_TILE);
    // rgeo2: die ersten Minuten nach dem Deploy am 18. September fragten mit "en,de",
    // die Eintraege von damals (etwa Ewringen statt Evrange) bleiben so unbenutzt liegen.
    $key = 'rgeo2:' . $lang . ':' . $ty . ':' . $tx;
    $hit = cache_get($key);
    if (is_array($hit)) {
        budget_add('nominatim', true);
        return $hit;
    }
    if (cache_get('rgeo:fail') !== null || rl_blocked('nominatim:reverse', GEO_REVERSE_DAY_MAX, 86400) || !rl_allow('nominatim:global', 1, 1)) {
        return ['status' => 'busy'];
    }
    rl_allow('nominatim:reverse', GEO_REVERSE_DAY_MAX, 86400);
    $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
        'format' => 'jsonv2',
        'lat' => sprintf('%.4f', $ty * GEO_REVERSE_TILE),
        'lon' => sprintf('%.4f', $tx * GEO_REVERSE_TILE),
        'zoom' => 10,
        'addressdetails' => 1,
        // Nur eine Sprache: fehlt der Name darin, nimmt Nominatim den Namen vor Ort.
        // Mit "en,de" hiess Evrange in Lothringen auf Englisch Ewringen.
        'accept-language' => $lang,
    ]);
    $r = http_get_json('nominatim', $url, 4, false);
    if ($r['status'] !== 200 || !is_array($r['data'])) {
        // Eine Minute Ruhe fuer alle Kacheln, sonst fragte jeder Abruf erneut, waehrend Nominatim ausfaellt.
        cache_put('rgeo:fail', 1, 60);
        event_add('NOMINATIM', 'Reverse lookup did not answer (HTTP ' . $r['status'] . ')', 'Ortsbestimmung hat nicht geantwortet (HTTP ' . $r['status'] . ')');
        return ['status' => 'busy'];
    }
    $a = is_array($r['data']['address'] ?? null) ? $r['data']['address'] : [];
    $place = '';
    foreach (['city', 'town', 'village', 'municipality', 'hamlet', 'suburb', 'county', 'state_district', 'state'] as $k) {
        if (trim((string) ($a[$k] ?? '')) !== '') {
            $place = trim((string) $a[$k]);
            break;
        }
    }
    $cc = strtolower((string) ($a['country_code'] ?? ''));
    $region = in_array($cc, GEO_REVERSE_STATES, true) ? trim((string) ($a['state'] ?? '')) : trim((string) ($a['country'] ?? ''));
    $out = $place === '' ? ['status' => 'none'] : ['status' => 'ok', 'place' => str_cut($place, 60), 'region' => $region === $place ? '' : str_cut($region, 60)];
    cache_put($key, $out, GEO_REVERSE_TTL);
    return $out;
}
