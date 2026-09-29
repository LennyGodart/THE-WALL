<?php
/*
 * Einstellungen des Projekts, die der Admin im Browser aendert: Registrierung,
 * SMTP-Zugang, Impressum, Firmware-Rollout. Schluessel-Wert-Paare in der Tabelle
 * settings, Werte als JSON.
 */

declare(strict_types=1);

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (qall('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = json_decode((string) $row['v'], true);
        }
    }
    return $cache;
}

function setting_set(string $key, mixed $value): void
{
    db_upsert('settings', [
        'k' => $key,
        'v' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated_at' => time(),
    ], ['k'], ['v' => null, 'updated_at' => null]);
    settings_all(true);
}

/** Registrierung: 'invite' (Standard) oder 'open'. */
function registration_mode(): string
{
    return setting('registration', 'invite') === 'open' ? 'open' : 'invite';
}

/** Impressum und Kontakt. Leerer Name oder Kontakt zeigt den Platzhalter aus dem Entwurf, die Adresse ist freiwillig. */
function imprint(): array
{
    $i = setting('imprint', []);
    return [
        'name' => trim((string) ($i['name'] ?? '')),
        'address' => trim((string) ($i['address'] ?? '')),
        'email' => trim((string) ($i['email'] ?? '')),
        'repo' => trim((string) ($i['repo'] ?? '')),
    ];
}

/** SMTP-Zugang, das Passwort entschluesselt nur fuer den Versand. */
function smtp_settings(bool $withPassword = false): array
{
    $s = setting('smtp', []);
    $s = is_array($s) ? $s : [];
    $security = (string) ($s['security'] ?? 'starttls');
    $out = [
        'host' => (string) ($s['host'] ?? ''),
        'port' => (int) ($s['port'] ?? 587),
        'security' => in_array($security, ['starttls', 'ssl'], true) ? $security : 'starttls',
        'user' => (string) ($s['user'] ?? ''),
        'from_email' => (string) ($s['from_email'] ?? ''),
        'from_name' => (string) ($s['from_name'] ?? 'THE WALL'),
        'has_password' => !empty($s['pass']),
        'tested_at' => isset($s['tested_at']) ? (int) $s['tested_at'] : null,
        'test_ok' => (bool) ($s['test_ok'] ?? false),
    ];
    if ($withPassword) {
        $out['pass'] = !empty($s['pass']) ? (unseal((string) $s['pass']) ?? '') : '';
    }
    return $out;
}

function smtp_configured(): bool
{
    $s = smtp_settings();
    return $s['host'] !== '' && $s['from_email'] !== '';
}
