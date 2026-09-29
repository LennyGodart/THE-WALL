<?php
/*
 * Konten. Benutzername und E-Mail werden klein gespeichert. Ein API-Schluessel
 * pro Konto, dauerhaft sichtbar, so am 13. September entschieden.
 *
 * Passwort-Hash: bcrypt ueber SHA-256 des Passworts. bcrypt liest nur die ersten
 * 72 Byte, der Vor-Hash macht jedes Passwort gleich lang und ohne Nullbyte.
 */

declare(strict_types=1);

function password_make(string $plain): string
{
    return password_hash(base64_encode(hash('sha256', $plain, true)), PASSWORD_BCRYPT, ['cost' => 11]);
}

function password_check(string $plain, string $hash): bool
{
    return password_verify(base64_encode(hash('sha256', $plain, true)), $hash);
}

/** Gleiche Laufzeit, auch wenn es das Konto nicht gibt: ein bcrypt kostet so viel wie eine Pruefung. */
function password_dummy_check(string $plain): void
{
    password_make($plain);
}

function user_by_id(int $id): ?array
{
    return q1('SELECT * FROM users WHERE id = ?', [$id]);
}

function user_by_email(string $email): ?array
{
    return q1('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
}

function user_by_username(string $username): ?array
{
    return q1('SELECT * FROM users WHERE username = ?', [strtolower(trim($username))]);
}

function user_by_api_key(string $key): ?array
{
    if (!preg_match('/^tw_live_[0-9a-f]{16}$/', $key)) {
        return null;
    }
    return q1('SELECT * FROM users WHERE api_key = ?', [$key]);
}

function api_key_generate(): string
{
    do {
        $key = 'tw_live_' . random_hex(8);
    } while (qval('SELECT COUNT(*) FROM users WHERE api_key = ?', [$key]) > 0);
    return $key;
}

function user_create(string $username, string $email, string $password, string $role = 'user', bool $verified = false, string $lang = 'en'): int
{
    $now = time();
    return db_insert('users', [
        'username' => strtolower($username),
        'email' => strtolower($email),
        'pass_hash' => password_make($password),
        'role' => $role === 'admin' ? 'admin' : 'user',
        'api_key' => api_key_generate(),
        'lang' => $lang === 'de' ? 'de' : 'en',
        'email_verified_at' => $verified ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function user_set_password(int $id, string $password): void
{
    db_update('users', ['pass_hash' => password_make($password), 'updated_at' => time()], 'id = ?', [$id]);
}

function user_rotate_key(int $id): string
{
    $key = api_key_generate();
    db_update('users', ['api_key' => $key, 'updated_at' => time()], 'id = ?', [$id]);
    return $key;
}

/* So lange merkt sich der Server die Schluessel geloeschter Konten, damit deren Geraete 410
   bekommen und ihr Einrichtungsnetz oeffnen, statt ewig mit 401 zu fragen. */
const API_KEY_REVOKED_DAYS = 30;

function api_key_revoke(string $key): void
{
    $list = setting('revoked_keys', []);
    $list = is_array($list) ? $list : [];
    $cut = time() - API_KEY_REVOKED_DAYS * 86400;
    $list = array_filter($list, static fn(mixed $at): bool => (int) $at > $cut);
    $list[hash('sha256', $key)] = time();
    setting_set('revoked_keys', $list);
}

function api_key_revoked(string $key): bool
{
    $list = setting('revoked_keys', []);
    $at = is_array($list) ? (int) ($list[hash('sha256', $key)] ?? 0) : 0;
    return $at > time() - API_KEY_REVOKED_DAYS * 86400;
}

/** Konto samt Geraeten, Freigaben, Sitzungen, offenen Links und Spotify-Verbindung loeschen. */
function user_delete(int $id): void
{
    $key = (string) qval('SELECT api_key FROM users WHERE id = ?', [$id]);
    if ($key !== '') {
        api_key_revoke($key);
    }
    db_tx(static function () use ($id): void {
        $devices = qall('SELECT id FROM devices WHERE owner_id = ?', [$id]);
        foreach ($devices as $d) {
            q('DELETE FROM shares WHERE device_id = ?', [(int) $d['id']]);
            q('DELETE FROM tokens WHERE device_id = ?', [(int) $d['id']]);
            ha_forget_device((int) $d['id']);
        }
        q('DELETE FROM devices WHERE owner_id = ?', [$id]);
        q('DELETE FROM shares WHERE user_id = ?', [$id]);
        q('DELETE FROM sessions WHERE user_id = ?', [$id]);
        q('DELETE FROM tokens WHERE user_id = ? OR created_by = ?', [$id, $id]);
        q('DELETE FROM spotify_links WHERE user_id = ?', [$id]);
        q('DELETE FROM cache WHERE k LIKE ?', ['spot:' . $id . ':%']);
        q('DELETE FROM users WHERE id = ?', [$id]);
    });
}

function users_count(): int
{
    return (int) qval('SELECT COUNT(*) FROM users');
}

function is_admin(?array $user): bool
{
    return $user !== null && ($user['role'] ?? '') === 'admin';
}
