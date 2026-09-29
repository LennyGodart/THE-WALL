<?php
/*
 * Sitzungen. Ein einziges Cookie, die Sitzungskennung, 30 Tage gueltig und beim
 * Abmelden geloescht. So steht es in der Datenschutzerklaerung, deshalb gibt es
 * kein zweites: kein CSRF-Cookie, kein Sprach-Cookie.
 * In der Datenbank liegt nur der SHA-256 der Kennung.
 */

declare(strict_types=1);

const SESSION_COOKIE = '__Host-tw_session';
const SESSION_TTL = 30 * 86400;

function session_row(): ?array
{
    static $loaded = false;
    static $row = null;
    if ($loaded) {
        return $row;
    }
    $loaded = true;
    $raw = (string) ($_COOKIE[SESSION_COOKIE] ?? '');
    if (!preg_match('/^[0-9a-f]{64}$/', $raw)) {
        return null;
    }
    $found = q1('SELECT * FROM sessions WHERE id = ? AND expires_at > ?', [token_hash($raw), time()]);
    if (!$found) {
        set_cookie(SESSION_COOKIE, '', -1);
        return null;
    }
    if ((int) $found['last_seen_at'] < time() - 300) {
        db_update('sessions', ['last_seen_at' => time()], 'id = ?', [$found['id']]);
    }
    $row = $found;
    return $row;
}

function session_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $s = session_row();
    $user = $s ? user_by_id((int) $s['user_id']) : null;
    return $user;
}

function session_begin(int $userId): void
{
    $raw = random_hex(32);
    $now = time();
    db_insert('sessions', [
        'id' => token_hash($raw),
        'user_id' => $userId,
        'csrf' => random_hex(32),
        'created_at' => $now,
        'last_seen_at' => $now,
        'expires_at' => $now + SESSION_TTL,
    ]);
    set_cookie(SESSION_COOKIE, $raw, SESSION_TTL);
}

function session_end(): void
{
    $raw = (string) ($_COOKIE[SESSION_COOKIE] ?? '');
    if (preg_match('/^[0-9a-f]{64}$/', $raw)) {
        q('DELETE FROM sessions WHERE id = ?', [token_hash($raw)]);
    }
    set_cookie(SESSION_COOKIE, '', -1);
}

/** Alle Sitzungen eines Kontos beenden, etwa nach neuem Passwort. */
function sessions_revoke(int $userId, ?string $keepSessionId = null): void
{
    if ($keepSessionId !== null) {
        q('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$userId, $keepSessionId]);
    } else {
        q('DELETE FROM sessions WHERE user_id = ?', [$userId]);
    }
}

function require_user(): array
{
    $u = session_user();
    if ($u === null || $u['email_verified_at'] === null) {
        if (wants_json()) {
            send_json_error(401, 'Please sign in again.', 'Bitte melde dich erneut an.');
        }
        redirect('/account?next=' . rawurlencode(req_path()));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_user();
    if (!is_admin($u)) {
        throw new HttpError(404);
    }
    return $u;
}
