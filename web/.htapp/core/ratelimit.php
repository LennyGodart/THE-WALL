<?php
/*
 * Rate-Limits mit festen Zeitfenstern. Schluessel wie "login:ip:<hash>".
 *
 *   rl_allow('login:ip:' . ip_key(), 20, 900)   zaehlt mit und sagt, ob es noch geht
 *   rl_blocked(...)                              schaut nur nach, zaehlt nicht
 */

declare(strict_types=1);

/**
 * Zaehlt einen Versuch und sagt, ob er noch im Rahmen liegt. Eine einzige Anweisung statt
 * Lesen und Schreiben in einer Transaktion: kamen zwei Anfragen in derselben Sekunde
 * (Nominatim, eine Abfrage pro Sekunde fuer den ganzen Server), lehnte MariaDB die zweite
 * Transaktion mit 1020 "Record has changed since last read" ab, und der ganze Rahmen des
 * Flugmodus wurde zu FEHLER (ab 19. September 2026, CHANGELOG 62). Versuche ueber
 * der Grenze zaehlen mit, das Fenster verlaengern sie nicht. Kommen zwei gleichzeitig an die
 * Grenze, darf im Zweifel keiner, nie beide.
 */
function rl_allow(string $key, int $max, int $window): bool
{
    $sqlite = db_driver() === 'sqlite';
    $neu = $sqlite ? 'excluded.window_start' : 'VALUES(window_start)';
    $abgelaufen = 'old.window_start <= ' . $neu . ' - ' . max(1, $window);
    $wenn = static fn(string $a, string $b): string => $sqlite
        ? 'CASE WHEN ' . $abgelaufen . ' THEN ' . $a . ' ELSE ' . $b . ' END'
        : 'IF(' . $abgelaufen . ', ' . $a . ', ' . $b . ')';
    // Reihenfolge wichtig: MariaDB rechnet die Zuweisungen von links nach rechts, hits muss
    // noch das alte window_start sehen.
    db_upsert('rate_limits', ['k' => $key, 'window_start' => time(), 'hits' => 1], ['k'], [
        'hits' => $wenn('1', 'old.hits + 1'),
        'window_start' => $wenn($neu, 'old.window_start'),
    ]);
    return (int) qval('SELECT hits FROM rate_limits WHERE k = ?', [$key]) <= $max;
}

function rl_blocked(string $key, int $max, int $window): bool
{
    $row = q1('SELECT window_start, hits FROM rate_limits WHERE k = ?', [$key]);
    return $row && (int) $row['window_start'] > time() - $window && (int) $row['hits'] >= $max;
}

function rl_reset(string $key): void
{
    q('DELETE FROM rate_limits WHERE k = ?', [$key]);
}

/** Sekunden bis das Fenster wieder aufgeht, fuer Retry-After. */
function rl_retry_after(string $key, int $window): int
{
    $start = (int) qval('SELECT window_start FROM rate_limits WHERE k = ?', [$key]);
    return max(1, $start + $window - time());
}
