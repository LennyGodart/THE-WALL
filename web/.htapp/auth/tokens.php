<?php
/*
 * Einmal-Links aus Mails und Einladungen. Gespeichert wird nur der Hash.
 *
 *   confirm  Adresse bestaetigen, 24 Stunden
 *   reset    neues Passwort, 1 Stunde, einmal
 *   share    Einladung zu einem Geraet, 7 Tage
 *   signup   Einladung zur Registrierung ohne Geraet, 7 Tage
 *   spotify  Rueckweg von der Anmeldung bei Spotify (state), 10 Minuten, einmal
 */

declare(strict_types=1);

const TOKEN_TTL = [
    'confirm' => 86400,
    'reset' => 3600,
    'share' => 7 * 86400,
    'signup' => 7 * 86400,
    'spotify' => 600,
];

function token_create(string $kind, array $data = []): string
{
    $raw = token_raw();
    db_insert('tokens', [
        'kind' => $kind,
        'token_hash' => token_hash($raw),
        'user_id' => $data['user_id'] ?? null,
        'email' => isset($data['email']) ? strtolower((string) $data['email']) : null,
        'device_id' => $data['device_id'] ?? null,
        'rights' => $data['rights'] ?? null,
        'created_by' => $data['created_by'] ?? null,
        'created_at' => time(),
        'expires_at' => time() + (TOKEN_TTL[$kind] ?? 3600),
        'used_at' => null,
    ]);
    return $raw;
}

function token_find(string $kind, string $raw): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $raw)) {
        return null;
    }
    return q1(
        'SELECT * FROM tokens WHERE kind = ? AND token_hash = ? AND used_at IS NULL AND expires_at > ?',
        [$kind, token_hash($raw), time()]
    );
}

/** Nur einer gewinnt, auch bei zwei gleichzeitigen Aufrufen. */
function token_consume(int $id): bool
{
    return q('UPDATE tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [time(), $id])->rowCount() === 1;
}

/** Einen verbrauchten Link wieder freigeben, wenn der Vorgang danach gescheitert ist. */
function token_release(int $id): void
{
    q('UPDATE tokens SET used_at = NULL WHERE id = ?', [$id]);
}
