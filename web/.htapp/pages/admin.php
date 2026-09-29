<?php
/*
 * Admin-Bereich. Wichtiger als Nutzerzahlen sind die Abfragebudgets: die Dienste
 * kosten nichts, sperren aber aus, wenn der Server zu oft fragt. Dazu Geraete,
 * Firmware-Rollout, Registrierung, SMTP-Zugang, Schluessel fuer mobiliteit.lu,
 * Impressum und Fehlerprotokoll.
 */

declare(strict_types=1);

function page_admin(array $params = []): void
{
    $user = require_admin();
    $devices = qall('SELECT d.*, u.username AS owner_name FROM devices d JOIN users u ON u.id = d.owner_id ORDER BY d.last_seen_at IS NULL, d.last_seen_at DESC, d.id');
    $latest = firmware_latest();
    $online = 0;
    $old = 0;
    $onLatest = 0;
    foreach ($devices as $d) {
        if (device_is_test($d)) {
            continue;
        }
        if (device_online($d)) {
            $online++;
        }
        if ($latest && $d['fw'] !== null) {
            if (version_compare((string) $d['fw'], $latest['version'], '<')) {
                $old++;
            } else {
                $onLatest++;
            }
        }
    }
    page_open(['title' => 'Operations', 'title_de' => 'Verwaltung', 'page' => 'admin', 'noindex' => true]);
    view('admin', [
        'user' => $user,
        'devices' => $devices,
        'online' => $online,
        'old' => $old,
        'onLatest' => $onLatest,
        'latest' => $latest,
        'budgets' => [
            'adsblol' => budget_stats('adsblol'),
            'adsbfi' => budget_stats('adsbfi'),
            'adsbdb' => budget_stats('adsbdb'),
            'vrs' => budget_stats('vrs'),
            'meteo' => budget_stats('meteo'),
            'nominatim' => budget_stats('nominatim'),
            'transit' => budget_stats('transit'),
        ],
        'smtp' => smtp_settings(),
        'transit' => transit_settings(),
        'spotify' => spotify_settings(),
        'stops' => stops_status(),
        'stopsInUse' => admin_stops_in_use($devices),
        'imprint' => imprint(),
        'registration' => registration_mode(),
        'rollout' => firmware_rollout(),
        'auto' => firmware_auto(),
        'events' => events_recent(20),
        'userAgent' => user_agent(),
        'logos' => logos_status(),
        'users' => users_count(),
        'accounts' => qall('SELECT u.id, u.username, u.email, u.role, u.email_verified_at,
            (SELECT COUNT(*) FROM devices d WHERE d.owner_id = u.id) AS devices,
            (SELECT COUNT(*) FROM devices d WHERE d.owner_id = u.id AND d.test = 1) AS tests
            FROM users u ORDER BY u.created_at, u.id'),
    ]);
    page_close(['js/pages/admin.js'], [
        'devices' => count(array_filter($devices, static fn(array $d): bool => !device_is_test($d))),
        // Feste Plaetze der Geraete im Rollout (crc32 der Kennung modulo 100): der Hinweis am
        // Regler zaehlt genau die, die das Update bekommen, statt den Anteil zu runden (Befund B13).
        'buckets' => array_values(array_map(static fn(array $d): array => ['b' => firmware_bucket((string) $d['uid']), 'name' => (string) $d['name']], array_filter($devices, static fn(array $d): bool => !device_is_test($d)))),
        'latest' => $latest['version'] ?? null,
        'adminEmail' => $user['email'],
    ]);
}

/**
 * Verschiedene Haltestellen aller Geraete, die den Nahverkehr zeigen (als Modus oder in der
 * Rotation). Jede kostet eine Abfrage pro Minute; ab vier reicht das Tageskontingent nicht
 * mehr rund um die Uhr, dann greift die Bremse bei 4 500 (Befund D6). Testgeraete zaehlen mit,
 * sie fragen in der Vorschau ab.
 */
function admin_stops_in_use(array $devices): int
{
    $ids = [];
    foreach ($devices as $d) {
        $s = device_settings($d);
        $shown = ($s['mode'] ?? '') === 'transit' || in_array('transit', (array) ($s['rotation'] ?? []), true);
        if (!$shown) {
            continue;
        }
        foreach ((array) ($s['transit']['stops'] ?? []) as $stop) {
            if (is_array($stop) && ($stop['id'] ?? '') !== '') {
                $ids[(string) $stop['id']] = true;
            }
        }
    }
    return count($ids);
}
