<?php
/*
 * Gemeinsames fuer alle Abfragen nach aussen: HTTP-Client mit User-Agent,
 * Zwischenspeicher in der Tabelle cache, und das Mitzaehlen fuer die
 * Abfragebudgets im Admin-Bereich.
 *
 * adsb.lol und adsbdb verlangen einen User-Agent mit Kontaktadresse, sonst kommt
 * statt JSON nur eine Textzeile. Die Adresse kommt aus dem Impressum.
 */

declare(strict_types=1);

/**
 * User-Agent fuer Abfragen nach aussen. adsb.lol und adsbdb wollen eine
 * Kontaktadresse. Nominatim lehnt Adressen mit Beispiel-Domains ab, dort reicht
 * die Adresse der Seite (config url), ueber die das Impressum erreichbar ist.
 * Lokal (TW_ENV=dev) steht das oeffentliche Repo darin: Ohne eigene config.php waere
 * die Adresse sonst die Voreinstellung thewall.godart.lu, und jeder Testlauf eines
 * Mitwirkenden gaebe sich als diese Instanz aus.
 */
function user_agent(bool $withContact = true): string
{
    $mail = $withContact ? imprint()['email'] : '';
    $contact = $mail !== '' ? '; ' . $mail : '';
    if (is_dev()) {
        return 'TheWall/' . TW_VERSION . '-dev (+https://github.com/LennyGodart/THE-WALL' . $contact . ')';
    }
    return 'TheWall/' . TW_VERSION . ' (+' . rtrim((string) config('url'), '/') . $contact . ')';
}

/** GET mit curl. Liefert status, body, error; wirft nie. */
function http_get(string $url, int $timeout = 6, array $headers = [], bool $withContact = true): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => user_agent($withContact),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $error];
}

/**
 * JSON von einem Dienst holen und mitzaehlen. $service ist der Name im
 * Admin-Bereich: adsblol, adsbdb, meteo, nominatim.
 */
function http_get_json(string $service, string $url, int $timeout = 6, bool $withContact = true): array
{
    budget_add($service, false);
    $r = http_get($url, $timeout, [], $withContact);
    $data = json_decode($r['body'], true);
    return ['status' => $r['status'], 'data' => is_array($data) ? $data : null, 'error' => $r['error']];
}

function budget_add(string $service, bool $cached): void
{
    try {
        $col = $cached ? 'cached' : 'requests';
        db_upsert('budget', [
            'service' => $service,
            'hour' => intdiv(time(), 3600),
            'requests' => $cached ? 0 : 1,
            'cached' => $cached ? 1 : 0,
        ], ['service', 'hour'], [$col => 'old.' . $col . ' + 1']);
    } catch (Throwable $e) {
        log_error('budget_add: ' . $e->getMessage());
    }
}

/** Summen fuer die letzte Stunde und die letzten 24 Stunden. */
function budget_stats(string $service): array
{
    $hour = intdiv(time(), 3600);
    $h = q1('SELECT COALESCE(SUM(requests), 0) AS r, COALESCE(SUM(cached), 0) AS c FROM budget WHERE service = ? AND hour >= ?', [$service, $hour - 1]);
    $d = q1('SELECT COALESCE(SUM(requests), 0) AS r, COALESCE(SUM(cached), 0) AS c FROM budget WHERE service = ? AND hour > ?', [$service, $hour - 24]);
    // Die laufende Stunde ist angebrochen, die vorige zaehlt anteilig mit.
    $cur = q1('SELECT COALESCE(SUM(requests), 0) AS r FROM budget WHERE service = ? AND hour = ?', [$service, $hour]);
    $prev = q1('SELECT COALESCE(SUM(requests), 0) AS r FROM budget WHERE service = ? AND hour = ?', [$service, $hour - 1]);
    $frac = (time() % 3600) / 3600;
    $perHour = (int) round((int) $cur['r'] + (int) $prev['r'] * (1 - $frac));
    $total = (int) $d['r'] + (int) $d['c'];
    return [
        'hour' => $perHour,
        'day' => (int) $d['r'],
        'cached_share' => $total > 0 ? (int) round(100 * (int) $d['c'] / $total) : null,
        'last_hour_raw' => (int) $h['r'],
    ];
}

function cache_get(string $key): mixed
{
    $row = q1('SELECT v FROM cache WHERE k = ? AND expires_at > ?', [$key, time()]);
    return $row ? json_decode((string) $row['v'], true) : null;
}

function cache_put(string $key, mixed $value, int $ttl): void
{
    db_upsert('cache', [
        'k' => $key,
        'v' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => time(),
        'expires_at' => time() + $ttl,
    ], ['k'], ['v' => null, 'created_at' => null, 'expires_at' => null]);
}

/**
 * Aus dem Zwischenspeicher oder frisch holen. Eine Dateisperre sorgt dafuer,
 * dass zwei Geraete im selben Umkreis nur eine Abfrage ausloesen.
 * $fn liefert [Wert, Lebensdauer] oder null bei Fehler. Ein Fehler bleibt $failTtl Sekunden
 * gemerkt, noch unter der Sperre: wer dahinter wartet, startet dieselbe langsame Abfrage
 * nicht noch einmal (Fehlerliste 24.09.2026, P1.7).
 */
function cache_remember(string $key, string $service, callable $fn, int $failTtl = 30): mixed
{
    $hit = cache_get($key);
    if ($hit !== null) {
        budget_add($service, true);
        return $hit;
    }
    if (cache_get($key . ':fail') !== null) {
        return null;
    }
    ensure_data_dir();
    $lockDir = TW_DATA . '/locks';
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0750, true);
    }
    $fh = @fopen($lockDir . '/' . md5($key) . '.lock', 'c');
    if ($fh) {
        flock($fh, LOCK_EX);
    }
    try {
        $hit = cache_get($key);
        if ($hit !== null) {
            budget_add($service, true);
            return $hit;
        }
        if (cache_get($key . ':fail') !== null) {
            return null;
        }
        $out = $fn();
        if ($out === null) {
            if ($failTtl > 0) {
                cache_put($key . ':fail', 1, $failTtl);
            }
            return null;
        }
        [$value, $ttl] = $out;
        cache_put($key, $value, $ttl);
        return $value;
    } finally {
        if ($fh) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
