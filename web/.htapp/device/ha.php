<?php
/*
 * Home Assistant, der Teil, den auch Rahmen, Geraeteseite und Loeschen brauchen. Die
 * Schnittstelle selbst steht in api/ha.php.
 *
 * Gekoppelt wird je Geraet: Home Assistant fragt einen Code an, der steht fuenf Minuten auf
 * dem Panel (frame_build()), wer ihn eintippt, steht davor. Danach gilt ein eigener
 * Schluessel je Home Assistant (Tabelle ha_links), nur als SHA-256 gespeichert.
 */

declare(strict_types=1);

const HA_PAIR_SECONDS = 300;
const HA_PAIR_ATTEMPTS = 5;
/* Ohne 0 und O, 1 und I, 2 und Z, 5 und S, 8 und B: auf dem Panel leicht zu verwechseln. */
const HA_CODE_ALPHABET = 'ACDEFHJKLMNPRTUVWXY34679';
const HA_CODE_LENGTH = 6;
const HA_LINKS_MAX = 5;
const HA_TOKEN_PREFIX = 'twha_';

/** Laufende Kopplung eines Geraets: [code, expires_at] oder null. */
function ha_pairing_pending(int $deviceId, int $now): ?array
{
    if ($deviceId <= 0) {
        return null;
    }
    $row = q1('SELECT code, expires_at FROM ha_pairings WHERE device_id = ? AND expires_at > ? AND attempts < ? ORDER BY created_at DESC LIMIT 1', [$deviceId, $now, HA_PAIR_ATTEMPTS]);
    return $row ? ['code' => (string) $row['code'], 'expires_at' => (int) $row['expires_at']] : null;
}

function ha_code_new(): string
{
    $out = '';
    $n = strlen(HA_CODE_ALPHABET);
    for ($i = 0; $i < HA_CODE_LENGTH; $i++) {
        $out .= HA_CODE_ALPHABET[random_int(0, $n - 1)];
    }
    return $out;
}

/** Eingabe wie "k7q 2xm" oder "K7Q-2XM" auf die Form des Codes bringen. */
function ha_code_normalize(string $in): string
{
    return strtoupper(preg_replace('/[\s\-]+/', '', $in) ?? '');
}

function ha_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ha_token_new(): string
{
    return HA_TOKEN_PREFIX . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** Gekoppelte Home-Assistant-Instanzen eines Geraets fuer die Einstellungen, Zeiten in Unix-Sekunden. */
function ha_links_public(int $deviceId): array
{
    return array_map(static fn(array $l): array => [
        'id' => (int) $l['id'],
        'name' => (string) $l['name'],
        'created' => (int) $l['created_at'],
        'used' => $l['last_used_at'] !== null ? (int) $l['last_used_at'] : null,
    ], qall('SELECT id, name, created_at, last_used_at FROM ha_links WHERE device_id = ? ORDER BY created_at', [$deviceId]));
}

/** Alles zu Home Assistant fuer ein Geraet entfernen, beim Loeschen des Geraets oder Kontos. */
function ha_forget_device(int $deviceId): void
{
    q('DELETE FROM ha_links WHERE device_id = ?', [$deviceId]);
    q('DELETE FROM ha_pairings WHERE device_id = ?', [$deviceId]);
}

/** Die Revision steigen lassen, damit das Panel in gut zwei Sekunden neu fragt. */
function device_bump_rev(int $deviceId): void
{
    q('UPDATE devices SET settings_rev = settings_rev + 1 WHERE id = ?', [$deviceId]);
}
