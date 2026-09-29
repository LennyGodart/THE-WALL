<?php
/*
 * Verschluesselung fuer gespeicherte Zugangsdaten wie den SMTP-Schluessel. Der
 * Schluessel dafuer liegt in .htdata/secret.php, nicht in der Datenbank. Wer nur
 * die Datenbank bekommt, bekommt den SMTP-Schluessel also nicht.
 */

declare(strict_types=1);

/** Schluessel fuer secretbox, abgeleitet aus app_key. */
function seal_key(): string
{
    return sodium_crypto_generichash('thewall-seal-v1', hex2bin(secret('app_key')), SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function seal(string $plain): string
{
    $key = seal_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
}

function unseal(string $sealed): ?string
{
    if (!str_starts_with($sealed, 'v1:')) {
        return null;
    }
    $raw = base64_decode(substr($sealed, 3), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $key = seal_key();
    $plain = sodium_crypto_secretbox_open(
        substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        $key
    );
    return $plain === false ? null : $plain;
}

/** Zufaelliger Token fuer Links, gespeichert wird nur sein Hash. */
function token_raw(): string
{
    return random_hex(24);
}

function token_hash(string $raw): string
{
    return hash('sha256', $raw);
}
